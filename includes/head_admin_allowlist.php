<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';

secure_session_start();

if (!function_exists('head_admin_role_key_from_session')) {
    function head_admin_role_key_from_session(): string
    {
        $raw = (string)(
            $_SESSION['role_name']
            ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
        );
        $role = strtolower(trim($raw));
        $role = str_replace([' ', '-'], '_', $role);
        $role = preg_replace('/_+/', '_', $role) ?? $role;
        return $role;
    }
}

if (!function_exists('head_admin_allowlist_ensure_table')) {
    function head_admin_allowlist_ensure_table(mysqli $conn): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS head_admin_ip_allowlist (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_or_cidr VARCHAR(80) NOT NULL,
                note VARCHAR(190) NULL DEFAULT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by_user_id INT NULL DEFAULT NULL,
                created_by_email VARCHAR(190) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_head_admin_ip_rule (ip_or_cidr),
                KEY idx_head_admin_allowlist_active (active),
                KEY idx_head_admin_allowlist_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        return (bool)$conn->query($sql);
    }
}

if (!function_exists('head_admin_normalize_ip_rule')) {
    function head_admin_normalize_ip_rule(string $rule): string
    {
        $rule = strtolower(trim($rule));
        $rule = preg_replace('/\s+/', '', $rule) ?? '';
        if ($rule === '') {
            return '';
        }

        if (strpos($rule, '/') === false) {
            return filter_var($rule, FILTER_VALIDATE_IP) ? $rule : '';
        }

        [$network, $prefix] = array_pad(explode('/', $rule, 2), 2, '');
        if (!filter_var($network, FILTER_VALIDATE_IP)) {
            return '';
        }
        if ($prefix === '' || !ctype_digit($prefix)) {
            return '';
        }

        $prefixInt = (int)$prefix;
        if (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($prefixInt < 0 || $prefixInt > 32) {
                return '';
            }
        } elseif (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($prefixInt < 0 || $prefixInt > 128) {
                return '';
            }
        } else {
            return '';
        }

        return $network . '/' . $prefixInt;
    }
}

if (!function_exists('head_admin_ip_matches_rule')) {
    function head_admin_ip_matches_rule(string $ip, string $rule): bool
    {
        $ip = strtolower(trim($ip));
        $rule = head_admin_normalize_ip_rule($rule);
        if ($ip === '' || $rule === '') {
            return false;
        }

        if (strpos($rule, '/') === false) {
            return hash_equals($rule, $ip);
        }

        [$network, $prefix] = explode('/', $rule, 2);
        $prefixInt = (int)$prefix;

        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);
        if (!is_string($ipBin) || !is_string($netBin) || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $fullBytes = intdiv($prefixInt, 8);
        $remainingBits = $prefixInt % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBin[$fullBytes]);
        $netByte = ord($netBin[$fullBytes]);
        return (($ipByte & $mask) === ($netByte & $mask));
    }
}

if (!function_exists('head_admin_ip_is_allowed')) {
    function head_admin_ip_is_allowed(mysqli $conn, string $ip): bool
    {
        if (!head_admin_allowlist_ensure_table($conn)) {
            return true;
        }

        $stmt = $conn->prepare(
            "SELECT ip_or_cidr
             FROM head_admin_ip_allowlist
             WHERE active = 1
             ORDER BY id ASC"
        );
        if (!$stmt) {
            return true;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rules = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $rule = trim((string)($row['ip_or_cidr'] ?? ''));
            if ($rule !== '') {
                $rules[] = $rule;
            }
        }
        $stmt->close();

        // Fail-open while no allowlist rules are configured.
        if (count($rules) === 0) {
            return true;
        }

        foreach ($rules as $rule) {
            if (head_admin_ip_matches_rule($ip, $rule)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('head_admin_allowlist_rows')) {
    function head_admin_allowlist_rows(mysqli $conn): array
    {
        if (!head_admin_allowlist_ensure_table($conn)) {
            return [];
        }

        $rows = [];
        $res = $conn->query(
            "SELECT id, ip_or_cidr, note, active, created_by_user_id, created_by_email, created_at, updated_at
             FROM head_admin_ip_allowlist
             WHERE active = 1
             ORDER BY id ASC"
        );
        if (!$res) {
            return [];
        }

        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('head_admin_allowlist_add')) {
    function head_admin_allowlist_add(mysqli $conn, string $ipOrCidr, string $note, int $actorUserId, string $actorEmail): array
    {
        if (!head_admin_allowlist_ensure_table($conn)) {
            return ['ok' => false, 'message' => 'Allowlist table unavailable.'];
        }

        $normalized = head_admin_normalize_ip_rule($ipOrCidr);
        if ($normalized === '') {
            return ['ok' => false, 'message' => 'Invalid IP/CIDR format.'];
        }

        $note = trim($note);
        if (strlen($note) > 190) {
            $note = substr($note, 0, 190);
        }

        $active = 1;
        $stmt = $conn->prepare(
            "INSERT INTO head_admin_ip_allowlist (ip_or_cidr, note, active, created_by_user_id, created_by_email)
             VALUES (?, ?, ?, NULLIF(?, 0), ?)
             ON DUPLICATE KEY UPDATE
                note = VALUES(note),
                active = 1,
                updated_at = CURRENT_TIMESTAMP,
                created_by_user_id = VALUES(created_by_user_id),
                created_by_email = VALUES(created_by_email)"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to save allowlist rule.'];
        }

        $stmt->bind_param('ssiis', $normalized, $note, $active, $actorUserId, $actorEmail);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['ok' => false, 'message' => 'Unable to save allowlist rule.'];
        }

        return ['ok' => true, 'message' => 'Allowlist rule saved.', 'rule' => $normalized];
    }
}

if (!function_exists('head_admin_allowlist_remove')) {
    function head_admin_allowlist_remove(mysqli $conn, int $rowId): array
    {
        if ($rowId <= 0 || !head_admin_allowlist_ensure_table($conn)) {
            return ['ok' => false, 'message' => 'Invalid allowlist item.'];
        }

        $inactive = 0;
        $stmt = $conn->prepare(
            "UPDATE head_admin_ip_allowlist
             SET active = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to remove allowlist rule.'];
        }

        $stmt->bind_param('ii', $inactive, $rowId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed <= 0) {
            return ['ok' => false, 'message' => 'Allowlist rule not found.'];
        }

        return ['ok' => true, 'message' => 'Allowlist rule removed.'];
    }
}

if (!function_exists('enforce_head_admin_ip_allowlist')) {
    function enforce_head_admin_ip_allowlist(?mysqli $conn = null): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $role = head_admin_role_key_from_session();
        if ($role !== 'head_admin') {
            return;
        }

        if (!$conn instanceof mysqli) {
            if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                $conn = $GLOBALS['conn'];
            } else {
                require_once dirname(__DIR__) . '/config/db.php';
                if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                    $conn = $GLOBALS['conn'];
                }
            }
        }

        if (!($conn instanceof mysqli)) {
            return;
        }

        $ip = security_client_ip();

        // ✅ If running on localhost, treat IP as the machine's LAN IP
        if ($ip === '127.0.0.1' || $ip === '::1') {
            $lan = gethostbyname(gethostname());
            if (filter_var($lan, FILTER_VALIDATE_IP)) {
                $ip = $lan;
            }
        }

        // ✅ IMPORTANT: if allowed, stop here (do NOT logout)
        if (head_admin_ip_is_allowed($conn, $ip)) {
            return;
        }



        $uid = (int)($_SESSION['user_id'] ?? 0);
        $email = (string)($_SESSION['user_email'] ?? '');

        if (function_exists('log_audit')) {
            log_audit(
                $uid,
                $email,
                'ACCESS_DENIED',
                'Security',
                'head_admin_allowlist',
                (string)$uid,
                'Head admin session blocked by IP allowlist.',
                ['ip' => $ip]
            );
        }

        if (function_exists('close_user_session_audit')) {
            close_user_session_audit('forced_logout', 'Head admin blocked by IP allowlist.');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }
        session_destroy();

        if (!headers_sent()) {
            header('Location: ' . url_with_base('auth/index.php?blocked=ip'));
            exit();
        }

        http_response_code(403);
        exit('Access denied.');
    }
}

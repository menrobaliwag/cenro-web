<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/includes/session_activity_audit.php';

requirePermission('palitbasura.view');

function pb_all_numeric_cols(): array {
    return [
        'pet_bottles',
        'sachet',
        'galon_tubig',
        'carton',
        'papel',
        'bote_litro',
        'bote_mantika',
        'bote_longneck',
        'bakal',
        'lata',
        'colored_plastic_bottles',
        'panligo',
        'tarpaulins',
        'cleared_plastic_bottles',
        'sachets_kg',
        'sando_bags',
        'styrofoam',
        'amount',
        'soft_plastic',
        'aling_tindera_amount',
        'client_amount',
    ];
}

function pb_to_ymd(?string $raw): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    $ts = strtotime($raw);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

function pb_num(string $key): float {
    $v = $_POST[$key] ?? 0;
    if (!is_scalar($v)) return 0.0;
    return is_numeric((string)$v) ? (float)$v : 0.0;
}

function pb_text(string $key, int $maxLen = 180): string {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return '';
    if (function_exists('mb_substr')) {
        return mb_substr($v, 0, $maxLen);
    }
    return substr($v, 0, $maxLen);
}

function pb_set_flash(string $variant, string $message): void {
    $_SESSION['pb_flash'] = [
        'variant' => $variant,
        'message' => $message,
    ];
}

function pb_pop_flash(): ?array {
    $flash = $_SESSION['pb_flash'] ?? null;
    unset($_SESSION['pb_flash']);
    return is_array($flash) ? $flash : null;
}

function pb_redirect_self(): never {
    $path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    if ($path === '') $path = url_with_base('modules/palitbasura/index.php');
    header('Location: ' . $path);
    exit;
}

function pb_bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): bool {
    $bind = [];
    $bind[] = &$types;
    foreach ($params as $idx => &$value) {
        $bind[] = &$value;
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $bind);
}

function pb_insert_record(mysqli $conn, string $category, string $recordDate, string $name, string $address, array $values): bool {
    $numCols = pb_all_numeric_cols();
    $cols = array_merge(['category', 'record_date', 'name', 'address'], $numCols);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = 'INSERT INTO palitbasura_records (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $params = [$category, $recordDate, $name, $address];
    foreach ($numCols as $col) {
        $params[] = (float)($values[$col] ?? 0.0);
    }

    $types = 'ssss' . str_repeat('d', count($numCols));
    if (!pb_bind_dynamic($stmt, $types, $params)) {
        $stmt->close();
        return false;
    }
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function pb_normalize_active_cols(array $activeNumericCols): array {
    $allowed = array_flip(pb_all_numeric_cols());
    $out = [];
    foreach ($activeNumericCols as $col) {
        $key = (string)$col;
        if (isset($allowed[$key])) {
            $out[$key] = true;
        }
    }
    return array_keys($out);
}

function pb_apply_computed_values(string $category, array &$values): void {
    if ($category !== 'aling_tindera_barangay') {
        return;
    }

    $values['amount'] =
        (float)($values['colored_plastic_bottles'] ?? 0) +
        (float)($values['panligo'] ?? 0) +
        (float)($values['tarpaulins'] ?? 0) +
        (float)($values['cleared_plastic_bottles'] ?? 0) +
        (float)($values['sachets_kg'] ?? 0) +
        (float)($values['sando_bags'] ?? 0) +
        (float)($values['styrofoam'] ?? 0);
}

function pb_update_record(mysqli $conn, string $category, int $id, string $recordDate, string $name, string $address, array $activeNumericCols, array $values): bool {
    if ($id <= 0) return false;

    $active = pb_normalize_active_cols($activeNumericCols);
    if (empty($active)) return false;

    $setParts = ['record_date = ?', 'name = ?', 'address = ?'];
    foreach ($active as $col) {
        $setParts[] = $col . ' = ?';
    }

    $sql = 'UPDATE palitbasura_records SET ' . implode(', ', $setParts) . ' WHERE id = ? AND category = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;

    $params = [$recordDate, $name, $address];
    foreach ($active as $col) {
        $params[] = (float)($values[$col] ?? 0.0);
    }
    $params[] = $id;
    $params[] = $category;

    $types = 'sss' . str_repeat('d', count($active)) . 'is';
    if (!pb_bind_dynamic($stmt, $types, $params)) {
        $stmt->close();
        return false;
    }

    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function pb_handle_actions(mysqli $conn, string $category, array $activeNumericCols): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    require_csrf();
    $activeNumericCols = pb_normalize_active_cols($activeNumericCols);

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $recordDate = pb_to_ymd((string)($_POST['record_date'] ?? ''));
        $name = pb_text('name', 150);
        $address = pb_text('address', 180);

        if ($recordDate === null || $name === '') {
            pb_set_flash('danger', 'Date and name are required.');
            pb_redirect_self();
        }

        $values = [];
        foreach (pb_all_numeric_cols() as $col) {
            $values[$col] = 0.0;
        }
        foreach ($activeNumericCols as $col) {
            $values[$col] = pb_num($col);
        }
        pb_apply_computed_values($category, $values);

        $ok = pb_insert_record($conn, $category, $recordDate, $name, $address, $values);
        pb_set_flash($ok ? 'success' : 'danger', $ok ? 'Record added successfully.' : 'Failed to add record.');
        pb_redirect_self();
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $recordDate = pb_to_ymd((string)($_POST['record_date'] ?? ''));
        $name = pb_text('name', 150);
        $address = pb_text('address', 180);

        if ($id <= 0 || $recordDate === null || $name === '') {
            pb_set_flash('danger', 'Invalid edit payload.');
            pb_redirect_self();
        }

        $values = [];
        foreach ($activeNumericCols as $col) {
            $values[$col] = pb_num($col);
        }
        pb_apply_computed_values($category, $values);

        $ok = pb_update_record($conn, $category, $id, $recordDate, $name, $address, $activeNumericCols, $values);
        pb_set_flash($ok ? 'success' : 'danger', $ok ? 'Record updated successfully.' : 'Failed to update record.');
        pb_redirect_self();
    }

    if ($action === 'archive' || $action === 'unarchive') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            pb_set_flash('danger', 'Invalid record ID.');
            pb_redirect_self();
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $email = (string)($_SESSION['user_email'] ?? '');

        if ($action === 'archive') {
            $result = archive_record_with_audit(
                $conn,
                'Palit Basura',
                'palitbasura_records',
                $id,
                $uid,
                $email,
                'Category: ' . $category
            );
        } else {
            $result = restore_archived_record_with_audit(
                $conn,
                'Palit Basura',
                'palitbasura_records',
                $id,
                $uid,
                $email
            );
        }

        pb_set_flash($result['ok'] ? 'success' : 'danger', (string)$result['message']);
        pb_redirect_self();
    }
}

function pb_date_range(): array {
    $rawStart = (string)($_GET['date_start'] ?? '');
    $rawEnd = (string)($_GET['date_end'] ?? '');

    $start = pb_to_ymd($rawStart) ?? date('Y-m-d');
    $end = pb_to_ymd($rawEnd) ?? date('Y-m-d');

    if ($start > $end) {
        $tmp = $start;
        $start = $end;
        $end = $tmp;
    }

    return [
        'start' => $start,
        'end' => $end,
        'start_display' => date('m/d/Y', strtotime($start)),
        'end_display' => date('m/d/Y', strtotime($end)),
    ];
}

function pb_fetch_active(mysqli $conn, string $category, string $startYmd, string $endYmd): array {
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT * FROM palitbasura_records
         WHERE category = ? AND archived = 0 AND record_date BETWEEN ? AND ?
         ORDER BY record_date DESC, id DESC'
    );
    if (!$stmt) return $rows;
    $stmt->bind_param('sss', $category, $startYmd, $endYmd);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

function pb_fetch_archived(mysqli $conn, string $category): array {
    $rows = [];
    $stmt = $conn->prepare(
        'SELECT * FROM palitbasura_records
         WHERE category = ? AND archived = 1
         ORDER BY record_date DESC, id DESC'
    );
    if (!$stmt) return $rows;
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

function pb_sum(array $rows, string $col): float {
    $total = 0.0;
    foreach ($rows as $r) {
        $total += (float)($r[$col] ?? 0);
    }
    return $total;
}

function pb_sum_many(array $rows, array $cols): float {
    $total = 0.0;
    foreach ($cols as $col) {
        $total += pb_sum($rows, $col);
    }
    return $total;
}

function pb_fmt_qty(float $v): string {
    if (abs($v - round($v)) < 0.00001) {
        return number_format($v, 0);
    }
    return number_format($v, 2);
}

function pb_fmt_money(float $v): string {
    return 'P' . number_format($v, 2);
}

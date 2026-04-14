<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/data_encryption.php';

if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$disableSelf = in_array('--disable-self', $args, true);
$batchSize = 500;

foreach ($args as $arg) {
    if (strpos($arg, '--batch=') === 0) {
        $parsed = (int)substr($arg, 8);
        if ($parsed > 0 && $parsed <= 5000) {
            $batchSize = $parsed;
        }
    }
}

$doneFile = __DIR__ . '/backfill_user_encryption.done';
if (is_file($doneFile) && !$force) {
    fwrite(STDOUT, "Backfill already completed. Use --force to run again.\n");
    exit(0);
}

if (!data_user_form_ensure_encryption_columns($conn)) {
    fwrite(STDERR, "Failed to ensure encrypted columns in user_form.\n");
    exit(1);
}

$total = 0;
$updated = 0;
$skipped = 0;
$failed = 0;
$lastId = 0;

$start = microtime(true);

while (true) {
    $stmt = $conn->prepare(
        'SELECT id, name, email, barangay, email_hash, email_enc, name_enc, barangay_enc
         FROM user_form
         WHERE id > ?
         ORDER BY id ASC
         LIMIT ?'
    );

    if (!$stmt) {
        fwrite(STDERR, "Failed to prepare batch query.\n");
        exit(1);
    }

    $stmt->bind_param('ii', $lastId, $batchSize);
    $stmt->execute();
    $res = $stmt->get_result();

    $batchRows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $batchRows[] = $row;
    }
    $stmt->close();

    if (count($batchRows) === 0) {
        break;
    }

    foreach ($batchRows as $row) {
        $total++;
        $userId = (int)($row['id'] ?? 0);
        if ($userId <= 0) {
            $skipped++;
            continue;
        }

        $name = (string)($row['name'] ?? '');
        $email = data_normalize_email((string)($row['email'] ?? ''));
        $barangay = (string)($row['barangay'] ?? '');

        if ($email === '') {
            $skipped++;
            $lastId = max($lastId, $userId);
            continue;
        }

        $expectedHash = data_email_hash($email);
        $hasEmailHash = trim((string)($row['email_hash'] ?? '')) !== '';
        $hasEmailEnc = trim((string)($row['email_enc'] ?? '')) !== '';
        $hasNameEnc = trim((string)($row['name_enc'] ?? '')) !== '' || $name === '';
        $hasBarangayEnc = trim((string)($row['barangay_enc'] ?? '')) !== '' || $barangay === '';
        $isHashCurrent = $expectedHash !== '' && hash_equals((string)($row['email_hash'] ?? ''), $expectedHash);

        if ($hasEmailHash && $hasEmailEnc && $hasNameEnc && $hasBarangayEnc && $isHashCurrent) {
            $skipped++;
        } else {
            if (data_sync_user_encryption($conn, $userId, $name, $email, $barangay)) {
                $updated++;
            } else {
                $failed++;
            }
        }

        $lastId = max($lastId, $userId);
    }

    $elapsed = microtime(true) - $start;
    fwrite(
        STDOUT,
        sprintf(
            "[%s] processed=%d updated=%d skipped=%d failed=%d last_id=%d elapsed=%.2fs\n",
            date('Y-m-d H:i:s'),
            $total,
            $updated,
            $skipped,
            $failed,
            $lastId,
            $elapsed
        )
    );
}

$summary = [
    'total' => $total,
    'updated' => $updated,
    'skipped' => $skipped,
    'failed' => $failed,
    'batch_size' => $batchSize,
    'duration_seconds' => round(microtime(true) - $start, 2),
];

if ($failed === 0) {
    @file_put_contents($doneFile, json_encode(['completed_at' => date('c'), 'summary' => $summary], JSON_UNESCAPED_SLASHES));

    if ($disableSelf) {
        $disabledPath = __FILE__ . '.disabled';
        @rename(__FILE__, $disabledPath);
    }
}

echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed > 0 ? 1 : 0);

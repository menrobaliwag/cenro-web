<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

date_default_timezone_set('Asia/Manila');

function backup_arg_value(array $argv, string $prefix, string $default = ''): string
{
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function backup_log(string $message): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
}

function backup_resolve_dump_bin(string $input): string
{
    $candidate = trim($input);
    if ($candidate === '') {
        $candidate = 'mysqldump';
    }

    // Explicit absolute/relative path.
    if (strpbrk($candidate, '\\/') !== false && is_file($candidate)) {
        return $candidate;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $xamppDump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (is_file($xamppDump)) {
            return $xamppDump;
        }
    }

    return $candidate;
}

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);

$host = (string)(getenv('DB_HOST') ?: '127.0.0.1');
$user = (string)(getenv('DB_USER') ?: 'root');
$password = (string)(getenv('DB_PASS') ?: '');
$dbName = (string)(getenv('DB_NAME') ?: 'newcityenro');
$port = (string)(getenv('DB_PORT') ?: '3307');

$backupDirDefault = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'city_enro_db_backups';
$backupDir = backup_arg_value($args, '--dir=', (string)(getenv('BACKUP_DIR') ?: $backupDirDefault));
$retentionDaysRaw = backup_arg_value($args, '--retention=', (string)(getenv('BACKUP_RETENTION_DAYS') ?: '14'));
$dumpBin = backup_resolve_dump_bin(
    backup_arg_value($args, '--mysqldump=', (string)(getenv('MYSQLDUMP_BIN') ?: 'mysqldump'))
);

$retentionDays = (int)$retentionDaysRaw;
if ($retentionDays <= 0) {
    $retentionDays = 14;
}

if ($dbName === '') {
    backup_log('Missing DB_NAME.');
    exit(1);
}

if ($backupDir === '') {
    backup_log('Missing backup directory.');
    exit(1);
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    backup_log('Unable to create backup directory: ' . $backupDir);
    exit(1);
}

$timestamp = date('Ymd_His');
$baseName = 'db_' . $timestamp . '.sql';
$sqlPath = rtrim($backupDir, '\\/') . DIRECTORY_SEPARATOR . $baseName;
$gzPath = $sqlPath . '.gz';

backup_log('Backup directory: ' . $backupDir);
backup_log('Database: ' . $dbName . ' @ ' . $host . ':' . $port);
backup_log('Retention days: ' . $retentionDays);

if ($dryRun) {
    backup_log('Dry-run mode. No dump executed.');
    exit(0);
}

$commandParts = [
    $dumpBin,
    '--single-transaction',
    '--quick',
    '--routines',
    '--events',
    '--triggers',
    '--default-character-set=utf8mb4',
    '--host=' . $host,
    '--port=' . $port,
    '--user=' . $user,
    $dbName,
];
$escaped = array_map('escapeshellarg', $commandParts);
$command = implode(' ', $escaped);

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['file', $sqlPath, 'w'],
    2 => ['pipe', 'w'],
];

$env = $_ENV;
$env['MYSQL_PWD'] = $password;

$process = proc_open($command, $descriptorSpec, $pipes, null, $env);
if (!is_resource($process)) {
    backup_log('Failed to start mysqldump process.');
    exit(1);
}

if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}

$stderr = '';
if (isset($pipes[2]) && is_resource($pipes[2])) {
    $stderr = (string)stream_get_contents($pipes[2]);
    fclose($pipes[2]);
}

$exitCode = proc_close($process);
if ($exitCode !== 0 || !is_file($sqlPath) || filesize($sqlPath) === 0) {
    @unlink($sqlPath);
    backup_log('mysqldump failed with exit code ' . $exitCode . '.');
    if ($stderr !== '') {
        backup_log('stderr: ' . trim($stderr));
    }
    exit(1);
}

$finalPath = $sqlPath;
if (function_exists('gzencode')) {
    $sqlData = file_get_contents($sqlPath);
    if (is_string($sqlData) && $sqlData !== '') {
        $compressed = gzencode($sqlData, 9);
        if (is_string($compressed) && file_put_contents($gzPath, $compressed) !== false) {
            @unlink($sqlPath);
            $finalPath = $gzPath;
        }
    }
}

$cutoffTs = time() - ($retentionDays * 86400);
$deleted = 0;
$patternSql = rtrim($backupDir, '\\/') . DIRECTORY_SEPARATOR . 'db_*.sql';
$patternGz = rtrim($backupDir, '\\/') . DIRECTORY_SEPARATOR . 'db_*.sql.gz';
$files = array_merge(glob($patternSql) ?: [], glob($patternGz) ?: []);
foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }
    if (filemtime($file) !== false && filemtime($file) < $cutoffTs) {
        if (@unlink($file)) {
            $deleted++;
        }
    }
}

backup_log('Backup completed: ' . $finalPath);
backup_log('Old backups deleted: ' . $deleted);
exit(0);

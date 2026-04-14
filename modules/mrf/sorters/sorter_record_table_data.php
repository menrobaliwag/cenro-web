<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');
header('Content-Type: application/json; charset=utf-8');

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || $params === []) return true;

    $bind = [$types];
    foreach ($params as $idx => $value) {
        $bind[] = &$params[$idx];
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $bind);
}

function fetch_count(mysqli $conn, string $sql, string $types, array $params): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    if (!bind_dynamic_params($stmt, $types, $params)) {
        $stmt->close();
        return 0;
    }

    $count = 0;
    if ($stmt->execute()) {
        $stmt->bind_result($count);
        $stmt->fetch();
    }
    $stmt->close();
    return (int)$count;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_float_cell($value): string
{
    $num = (float)$value;
    if (abs($num - round($num)) < 0.00001) {
        return (string)(int)round($num);
    }
    return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
}

/** =========================
 * DataTables params
 * ========================= */
$draw  = max(0, (int)($_GET['draw'] ?? 0));
$start = max(0, (int)($_GET['start'] ?? 0));

$lengthRaw = $_GET['length'] ?? 10;
$lengthRawInt = is_numeric($lengthRaw) ? (int)$lengthRaw : 10;

// DataTables sometimes uses length = -1 meaning "all"
$isExportAll = isset($_GET['export_all']) || $lengthRawInt === -1;

if ($isExportAll) {
    $start = 0;
    $length = 50000; // safety cap
} else {
    $length = $lengthRawInt;
    if ($length < 1 || $length > 500) $length = 25;
}

/** =========================
 * Date bounds (Y-m-d)
 * ========================= */
$rawStart = trim((string)($_GET['date_start'] ?? ''));
$rawEnd   = trim((string)($_GET['date_end'] ?? ''));

$dateStart = $rawStart !== '' ? security_parse_date_to_ymd($rawStart) : null;
$dateEnd   = $rawEnd !== '' ? security_parse_date_to_ymd($rawEnd) : null;

$startBound = $dateStart ?? '2000-01-01';
$endBound   = $dateEnd ?? '2100-12-31';
if ($startBound > $endBound) {
    [$startBound, $endBound] = [$endBound, $startBound];
}

/** =========================
 * Search + ordering
 * ========================= */
$searchValue = trim((string)($_GET['search']['value'] ?? ''));

$orderColumn = (int)($_GET['order'][0]['column'] ?? 2);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

// Map table column index -> DB column (skip 0=# and 14=Action)
$orderMap = [
    1  => 'r.name',
    2  => 'r.date',
    3  => 'r.mrf',
    4  => 'r.puti',
    5  => 'r.assorted',
    6  => 'r.karton',
    7  => 'r.pet',
    8  => 'r.sibak',
    9  => 'r.lata',
    10 => 'r.aluminum',
    11 => 'r.bakal',
    12 => 'r.yero',
    13 => 'r.glass',
];
$orderBy = $orderMap[$orderColumn] ?? 'r.date';

/** =========================
 * Base where
 * ========================= */
$baseWhere  = " FROM scavenger r WHERE r.archived = 0 AND DATE(r.date) BETWEEN ? AND ? ";
$baseTypes  = 'ss';
$baseParams = [$startBound, $endBound];

$recordsTotal = fetch_count($conn, "SELECT COUNT(*)" . $baseWhere, $baseTypes, $baseParams);

/** =========================
 * Search filter
 * ========================= */
$searchWhere = '';
$searchTypes = '';
$searchParams = [];

if ($searchValue !== '') {
    $searchWhere = " AND (
        r.name LIKE ? OR
        r.mrf LIKE ? OR
        DATE_FORMAT(r.date, '%Y-%m-%d') LIKE ? OR
        CAST(r.puti AS CHAR) LIKE ? OR
        CAST(r.assorted AS CHAR) LIKE ? OR
        CAST(r.karton AS CHAR) LIKE ? OR
        CAST(r.pet AS CHAR) LIKE ? OR
        CAST(r.sibak AS CHAR) LIKE ? OR
        CAST(r.lata AS CHAR) LIKE ? OR
        CAST(r.aluminum AS CHAR) LIKE ? OR
        CAST(r.bakal AS CHAR) LIKE ? OR
        CAST(r.yero AS CHAR) LIKE ? OR
        CAST(r.glass AS CHAR) LIKE ?
    )";
    $like = '%' . $searchValue . '%';
    $searchTypes  = str_repeat('s', 13);
    $searchParams = array_fill(0, 13, $like);
}

$filteredTypes  = $baseTypes . $searchTypes;
$filteredParams = array_merge($baseParams, $searchParams);

$recordsFiltered = fetch_count(
    $conn,
    "SELECT COUNT(*)" . $baseWhere . $searchWhere,
    $filteredTypes,
    $filteredParams
);

/** =========================
 * Data query
 * ========================= */
$dataSql = "
    SELECT
        r.id, r.date, r.name, r.mrf,
        r.puti, r.assorted, r.karton, r.pet, r.sibak,
        r.lata, r.aluminum, r.bakal, r.yero, r.glass
    " . $baseWhere . $searchWhere . "
    ORDER BY {$orderBy} {$orderDir}
    LIMIT ?, ?
";

$dataTypes  = $filteredTypes . 'ii';
$dataParams = array_merge($filteredParams, [$start, $length]);

$stmtData = $conn->prepare($dataSql);
if (!$stmtData) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => 'Unable to prepare table query.',
    ]);
    exit;
}

if (!bind_dynamic_params($stmtData, $dataTypes, $dataParams) || !$stmtData->execute()) {
    $stmtData->close();
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => 'Unable to execute table query.',
    ]);
    exit;
}

$result = $stmtData->get_result();
$rows = [];
$offset = $start;

while ($result && ($row = $result->fetch_assoc())) {
    $id   = (int)($row['id'] ?? 0);
    $name = (string)($row['name'] ?? '');
    $mrf  = (string)($row['mrf'] ?? '');

    $rawDate = (string)($row['date'] ?? '');
    $dateTs = $rawDate !== '' ? strtotime($rawDate) : false;
    $dateYmd = $dateTs ? date('Y-m-d', $dateTs) : '';
    $dateDisplay = $dateTs ? date('F j, Y', $dateTs) : '';

    // Name column with eye button (same style)
    $nameHtml = '<div class="enro-name-inline">'
        . '<button class="btn-eye view_details mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="View Record" data-id="' . $id . '">'
        . '<i class="fa fa-eye"></i></button>'
        . '<span class="enro-name-text" title="' . h($name) . '">' . h($name) . '</span>'
        . '</div>';

    // Action dropdown (same style)
    $actionHtml = '<div class="dropdown">'
        . '<button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">'
        . '<i class="fa fa-cog me-1 spinning-gear always-spin"></i></button>'
        . '<ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">'
        . '<li><a class="dropdown-item d-flex align-items-center gap-2 edit_btn" href="javascript:void(0)"'
        . ' data-id="' . $id . '"'
        . ' data-date="' . h($dateYmd) . '"'
        . ' data-name="' . h($name) . '"'
        . ' data-mrf="' . h($mrf) . '"'
        . ' data-puti="' . h((string)($row['puti'] ?? '0')) . '"'
        . ' data-assorted="' . h((string)($row['assorted'] ?? '0')) . '"'
        . ' data-karton="' . h((string)($row['karton'] ?? '0')) . '"'
        . ' data-pet="' . h((string)($row['pet'] ?? '0')) . '"'
        . ' data-sibak="' . h((string)($row['sibak'] ?? '0')) . '"'
        . ' data-lata="' . h((string)($row['lata'] ?? '0')) . '"'
        . ' data-aluminum="' . h((string)($row['aluminum'] ?? '0')) . '"'
        . ' data-bakal="' . h((string)($row['bakal'] ?? '0')) . '"'
        . ' data-yero="' . h((string)($row['yero'] ?? '0')) . '"'
        . ' data-glass="' . h((string)($row['glass'] ?? '0')) . '">'
        . '<i class="fa fa-pen text-primary"></i> <span>Edit</span></a></li>'
        . '<li><hr class="dropdown-divider my-1"></li>'
        . '<li><a class="dropdown-item d-flex align-items-center gap-2 archive_data text-warning" href="javascript:void(0)" data-id="' . $id . '">'
        . '<i class="fa fa-archive"></i> <span>Archive</span></a></li>'
        . '</ul></div>';

    $rows[] = [
        (string)(++$offset),                 // #
        $nameHtml,                           // Name
        $dateDisplay,                        // Date
        h($mrf),                             // Type of sorter (MRF)
        format_float_cell($row['puti'] ?? 0),
        format_float_cell($row['assorted'] ?? 0),
        format_float_cell($row['karton'] ?? 0),
        format_float_cell($row['pet'] ?? 0),
        format_float_cell($row['sibak'] ?? 0),
        format_float_cell($row['lata'] ?? 0),
        format_float_cell($row['aluminum'] ?? 0),
        format_float_cell($row['bakal'] ?? 0),
        format_float_cell($row['yero'] ?? 0),
        format_float_cell($row['glass'] ?? 0),
        $actionHtml,                         // Action
    ];
}

$stmtData->close();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $rows,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

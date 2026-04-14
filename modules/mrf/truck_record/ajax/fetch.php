<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/includes/security.php';
secure_session_start();

require_once dirname(__DIR__, 4) . '/config/db.php';
require_once dirname(__DIR__, 4) . '/includes/auth.php';
require_once dirname(__DIR__, 4) . '/includes/permissions.php';

requirePermission('mrf.truck');
header('Content-Type: application/json; charset=utf-8');

/**
 * Accepts:
 * - "January 1, 2024"
 * - "2024-01-01"
 * returns "Y-m-d" or null
 */
function parse_date_to_ymd(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;

    // if already Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }

    $ts = strtotime($s);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/** safe bind for dynamic param arrays */
function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || $params === []) return true;

    $bind = [$types];
    foreach ($params as $i => $val) {
        $bind[] = &$params[$i];
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

// ------------------------
// DataTables Inputs
// ------------------------
$draw  = max(0, (int)($_GET['draw'] ?? 0));
$start = max(0, (int)($_GET['start'] ?? 0));

$lengthRaw   = (int)($_GET['length'] ?? 10);
$isExportAll = isset($_GET['export_all']) || $lengthRaw === -1;

// ✅ allow export all (but cap for safety)
if ($isExportAll) {
    $start  = 0;
    $length = 50000; // adjust if needed (20k / 50k)
} else {
    $length = $lengthRaw;
    if ($length < 1 || $length > 500) $length = 10;
}

$searchValue = trim((string)($_GET['search']['value'] ?? ''));

// Dates (from table data attrs)
$rawStart = (string)($_GET['date_start'] ?? '');
$rawEnd   = (string)($_GET['date_end'] ?? '');

$dateStart = parse_date_to_ymd($rawStart);
$dateEnd   = parse_date_to_ymd($rawEnd);

$startBound = $dateStart ?: '2000-01-01';
$endBound   = $dateEnd ?: '2100-12-31';
if ($startBound > $endBound) {
    [$startBound, $endBound] = [$endBound, $startBound];
}

// Ordering
$columns = [
    0 => null,
    1 => 'r.date_created', // Date
    2 => null,             // Time In (computed)
    3 => null,             // Time Out (computed)
    4 => null,             // Total Hours (computed)
    5 => 'r.truck',
    6 => 'r.name',
    7 => 'r.area',
    8 => 'r.dumping',
    9 => null,             // Action
];

$orderColIndex = (int)($_GET['order'][0]['column'] ?? 1);
$orderDirRaw   = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc'));
$orderDir      = ($orderDirRaw === 'desc') ? 'DESC' : 'ASC';

$orderBy = $columns[$orderColIndex] ?? 'r.date_created';
if (!$orderBy) $orderBy = 'r.date_created';

// ------------------------
// WHERE
// ------------------------
$baseWhere   = " FROM truck_record r WHERE r.archived = 0 AND DATE(r.date_created) BETWEEN ? AND ? ";
$baseTypes   = "ss";
$baseParams  = [$startBound, $endBound];

$searchWhere  = '';
$searchTypes  = '';
$searchParams = [];

if ($searchValue !== '') {
    $searchWhere = " AND (r.truck LIKE ? OR r.name LIKE ? OR r.area LIKE ? OR r.dumping LIKE ?) ";
    $like = '%' . $searchValue . '%';
    $searchTypes  = 'ssss';
    $searchParams = [$like, $like, $like, $like];
}

$typesAll  = $baseTypes . $searchTypes;
$paramsAll = array_merge($baseParams, $searchParams);

// recordsTotal = date filter only (no search)
$recordsTotal = fetch_count(
    $conn,
    "SELECT COUNT(*)" . $baseWhere,
    $baseTypes,
    $baseParams
);

// recordsFiltered = date + search
$recordsFiltered = fetch_count(
    $conn,
    "SELECT COUNT(*)" . $baseWhere . $searchWhere,
    $typesAll,
    $paramsAll
);

// ------------------------
// DATA QUERY
// ------------------------
$dataSql = "
    SELECT r.*
    " . $baseWhere . $searchWhere . "
    ORDER BY {$orderBy} {$orderDir}
    LIMIT ?, ?
";

$dataTypes  = $typesAll . 'ii';
$dataParams = array_merge($paramsAll, [$start, $length]);

$stmt = $conn->prepare($dataSql);
if (!$stmt) {
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => 'Unable to prepare query.',
    ]);
    exit;
}

if (!bind_dynamic_params($stmt, $dataTypes, $dataParams) || !$stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => [],
        'error' => 'Unable to execute query.',
    ]);
    exit;
}

$res = $stmt->get_result();
$data = [];
$no = $start + 1;

while ($row = $res->fetch_assoc()) {
    $id = (int)($row['id'] ?? 0);

    $dateCreated = (string)($row['date_created'] ?? '');
    $timeInRaw   = (string)($row['time_in'] ?? '');
    $timeOutRaw  = (string)($row['time_out'] ?? '');

    $time_in_ts = strtotime($dateCreated . ' ' . $timeInRaw);

    $date_out = !empty($row['date_out']) ? (string)$row['date_out'] : $dateCreated;
    $time_out_ts = strtotime($date_out . ' ' . $timeOutRaw);

    if ($time_in_ts === false) $time_in_ts = 0;
    if ($time_out_ts === false) $time_out_ts = 0;

    // overnight fix
    if ($time_out_ts < $time_in_ts) $time_out_ts += 86400;

    $total_seconds = max(0, $time_out_ts - $time_in_ts);
    $hours = (int)floor($total_seconds / 3600);
    $minutes = (int)round(($total_seconds % 3600) / 60);
    $duration = $hours . " hr" . ($minutes > 0 ? " {$minutes} min" : '');

    $timeInHtml = '
      <button class="btn-eye view_details mb-2"
        data-bs-toggle="tooltip" data-bs-placement="top" title="View Record"
        data-id="' . $id . '">
        <i class="fa fa-eye"></i>
      </button>
      <small class="text-muted">' . ($time_in_ts ? date('h:i A', $time_in_ts) : '') . '</small>
    ';

    $actionHtml = '
      <div class="dropdown">
        <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-3"
          type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fa fa-cog me-1 spinning-gear always-spin"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2 edit_btn" href="javascript:void(0)"
              data-id="' . $id . '"
              data-date="' . h(date('Y-m-d', strtotime($dateCreated))) . '"
              data-timein="' . h($timeInRaw) . '"
              data-timeout="' . h($timeOutRaw) . '"
              data-truck="' . h((string)($row['truck'] ?? '')) . '"
              data-name="' . h((string)($row['name'] ?? '')) . '"
              data-area="' . h((string)($row['area'] ?? '')) . '"
              data-dumping="' . h((string)($row['dumping'] ?? '')) . '">
              <i class="fa fa-pen text-primary"></i> <span>Edit</span>
            </a>
          </li>
          <li><hr class="dropdown-divider my-1"></li>
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2 archive_data text-warning" href="javascript:void(0)"
              data-id="' . $id . '">
              <i class="fa fa-archive"></i> <span>Archive</span>
            </a>
          </li>
        </ul>
      </div>
    ';

    $data[] = [
        (string)$no++,
        $dateCreated ? date('F j, Y', strtotime($dateCreated)) : '',
        $timeInHtml,
        '<small class="text-muted">' . ($time_out_ts ? date('h:i A', $time_out_ts) : '') . '</small>',
        '<span class="badge text-dark px-3 py-2 rounded-pill">' . h($duration) . '</span>',
        h((string)($row['truck'] ?? '')),
        h((string)($row['name'] ?? '')),
        h((string)($row['area'] ?? '')),
        h((string)($row['dumping'] ?? '')),
        $actionHtml,
    ];
}

$stmt->close();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

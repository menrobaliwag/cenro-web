<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/includes/security.php';
secure_session_start();

require_once dirname(__DIR__, 4) . '/config/db.php';
require_once dirname(__DIR__, 4) . '/includes/auth.php';
require_once dirname(__DIR__, 4) . '/includes/permissions.php';

requirePermission('mrf.other_waste');
header('Content-Type: application/json; charset=utf-8');

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): bool {
  if ($types === '' || $params === []) return true;
  $bind = [$types];
  foreach ($params as $i => $v) $bind[] = &$params[$i];
  return (bool)call_user_func_array([$stmt, 'bind_param'], $bind);
}

function fetch_count(mysqli $conn, string $sql, string $types, array $params): int {
  $stmt = $conn->prepare($sql);
  if (!$stmt) return 0;
  if (!bind_dynamic_params($stmt, $types, $params)) { $stmt->close(); return 0; }
  $count = 0;
  if ($stmt->execute()) { $stmt->bind_result($count); $stmt->fetch(); }
  $stmt->close();
  return (int)$count;
}

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function fmt_num($value): string {
  $num = (float)$value;
  if (abs($num - round($num)) < 0.00001) return (string)(int)round($num);
  return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
}

$draw  = max(0, (int)($_GET['draw'] ?? 0));
$start = max(0, (int)($_GET['start'] ?? 0));

$lengthRaw = $_GET['length'] ?? 10;
$lengthRawInt = is_numeric($lengthRaw) ? (int)$lengthRaw : 10;
$isExportAll = isset($_GET['export_all']) || $lengthRawInt === -1;

if ($isExportAll) {
  $start = 0;
  $length = 50000;
} else {
  $length = $lengthRawInt;
  if ($length < 1 || $length > 500) $length = 25;
}

$rawStart = trim((string)($_GET['date_start'] ?? ''));
$rawEnd   = trim((string)($_GET['date_end'] ?? ''));

$dateStart = $rawStart !== '' ? security_parse_date_to_ymd($rawStart) : null;
$dateEnd   = $rawEnd !== '' ? security_parse_date_to_ymd($rawEnd) : null;

$startBound = $dateStart ?? '2000-01-01';
$endBound   = $dateEnd ?? '2100-12-31';
if ($startBound > $endBound) { [$startBound, $endBound] = [$endBound, $startBound]; }

$searchValue = trim((string)($_GET['search']['value'] ?? ''));

$orderColumn = (int)($_GET['order'][0]['column'] ?? 2);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

/**
 * columns:
 * 0 #, 1 NAME, 2 DATE, 3 ECO, 4 PAVEMENT, 5 CEMENT, 6 COAL, 7 HOLLOW, 8 ACTION
 */
$orderMap = [
  1 => 'r.name',
  2 => 'r.date',
  3 => 'r.eco',
  4 => 'r.pavement',
  5 => 'r.cement',
  6 => 'r.coal',
  7 => 'r.hollow',
];
$orderBy = $orderMap[$orderColumn] ?? 'r.date';

$baseWhere  = " FROM waste r WHERE r.archived IS NULL AND DATE(r.date) BETWEEN ? AND ? ";
$baseTypes  = 'ss';
$baseParams = [$startBound, $endBound];

$recordsTotal = fetch_count($conn, "SELECT COUNT(*)" . $baseWhere, $baseTypes, $baseParams);

$searchWhere = '';
$searchTypes = '';
$searchParams = [];

if ($searchValue !== '') {
  $searchWhere = " AND (
    r.name LIKE ? OR
    DATE_FORMAT(r.date, '%Y-%m-%d') LIKE ? OR
    CAST(r.eco AS CHAR) LIKE ? OR
    CAST(r.pavement AS CHAR) LIKE ? OR
    CAST(r.cement AS CHAR) LIKE ? OR
    CAST(r.coal AS CHAR) LIKE ? OR
    CAST(r.hollow AS CHAR) LIKE ?
  )";
  $like = '%' . $searchValue . '%';
  $searchTypes = str_repeat('s', 7);
  $searchParams = array_fill(0, 7, $like);
}

$filteredTypes  = $baseTypes . $searchTypes;
$filteredParams = array_merge($baseParams, $searchParams);

$recordsFiltered = fetch_count(
  $conn,
  "SELECT COUNT(*)" . $baseWhere . $searchWhere,
  $filteredTypes,
  $filteredParams
);

$sql = "
  SELECT r.id, r.name, r.date, r.eco, r.pavement, r.cement, r.coal, r.hollow
  " . $baseWhere . $searchWhere . "
  ORDER BY {$orderBy} {$orderDir}
  LIMIT ?, ?
";

$dataTypes  = $filteredTypes . 'ii';
$dataParams = array_merge($filteredParams, [$start, $length]);

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => [],
    'error' => 'Unable to prepare table query.'
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
    'error' => 'Unable to execute table query.'
  ]);
  exit;
}

$res = $stmt->get_result();
$rows = [];
$offset = $start;

while ($res && ($row = $res->fetch_assoc())) {
  $id = (int)($row['id'] ?? 0);
  $name = (string)($row['name'] ?? '');
  $rawDate = (string)($row['date'] ?? '');
  $ts = $rawDate !== '' ? strtotime($rawDate) : false;

  $dateYmd = $ts ? date('Y-m-d', $ts) : '';
  $dateDisplay = $ts ? date('F j, Y', $ts) : '';

  $nameHtml = '<div class="enro-name-inline">'
    . '<button class="btn-eye view_details mb-2" data-bs-toggle="tooltip" data-bs-placement="top" title="View Record" data-id="' . $id . '">'
    . '<i class="fa fa-eye"></i></button>'
    . '<span class="enro-name-text" title="' . h($name) . '">' . h($name) . '</span>'
    . '</div>';

  $actionHtml = '<div class="dropdown">'
    . '<button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">'
    . '<i class="fa fa-cog me-1 spinning-gear always-spin"></i></button>'
    . '<ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">'
    . '<li><a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 edit_btn"'
    . ' data-id="' . $id . '"'
    . ' data-date="' . h($dateYmd) . '"'
    . ' data-name="' . h($name) . '"'
    . ' data-eco="' . h((string)($row['eco'] ?? '0')) . '"'
    . ' data-pavement="' . h((string)($row['pavement'] ?? '0')) . '"'
    . ' data-cement="' . h((string)($row['cement'] ?? '0')) . '"'
    . ' data-coal="' . h((string)($row['coal'] ?? '0')) . '"'
    . ' data-hollow="' . h((string)($row['hollow'] ?? '0')) . '">'
    . '<i class="fa fa-pen text-primary"></i> <span>Edit</span></a></li>'
    . '<li><hr class="dropdown-divider my-1"></li>'
    . '<li><a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 archive_data text-warning" data-id="' . $id . '">'
    . '<i class="fa fa-archive"></i> <span>Archive</span></a></li>'
    . '</ul></div>';

  $rows[] = [
    (string)(++$offset),
    $nameHtml,
    $dateDisplay,
    fmt_num($row['eco'] ?? 0),
    fmt_num($row['pavement'] ?? 0),
    fmt_num($row['cement'] ?? 0),
    fmt_num($row['coal'] ?? 0),
    fmt_num($row['hollow'] ?? 0),
    $actionHtml,
  ];
}
$stmt->close();

echo json_encode([
  'draw' => $draw,
  'recordsTotal' => $recordsTotal,
  'recordsFiltered' => $recordsFiltered,
  'data' => $rows,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

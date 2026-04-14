<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');

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

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '' || $params === []) {
        return true;
    }

    $bind = [$types];
    foreach ($params as $idx => $value) {
        $bind[] = &$params[$idx];
    }

    return (bool)call_user_func_array([$stmt, 'bind_param'], $bind);
}

$rawStart = trim((string)($_GET['date_start'] ?? ''));
$rawEnd = trim((string)($_GET['date_end'] ?? ''));
$dateStart = $rawStart !== '' ? security_parse_date_to_ymd($rawStart) : null;
$dateEnd = $rawEnd !== '' ? security_parse_date_to_ymd($rawEnd) : null;
$startBound = $dateStart ?? '2000-01-01';
$endBound = $dateEnd ?? '2100-12-31';
if ($startBound > $endBound) {
    [$startBound, $endBound] = [$endBound, $startBound];
}

$searchValue = trim((string)($_GET['search'] ?? ''));
$orderColumn = (int)($_GET['order_col'] ?? 2);
$orderDir = strtolower((string)($_GET['order_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$returnRaw = (string)($_GET['return'] ?? url_with_base('modules/mrf/sorters/index.php'));
$returnUrl = url_with_base('modules/mrf/sorters/index.php');

if ($returnRaw !== '') {
    $parts = parse_url($returnRaw);
    if ($parts !== false) {
        $path = (string)($parts['path'] ?? '');
        $query = (string)($parts['query'] ?? '');
        if ($path !== '') {
            $normalizedPath = normalize_public_app_path($path);
            if ($normalizedPath !== '') {
                $returnUrl = $normalizedPath . ($query !== '' ? ('?' . $query) : '');
            }
        }
    }
}

$orderMap = [
    1 => 'r.name',
    2 => 'r.date',
    3 => 'r.mrf',
    4 => 'r.puti',
    5 => 'r.assorted',
    6 => 'r.karton',
    7 => 'r.pet',
    8 => 'r.sibak',
    9 => 'r.lata',
    10 => 'r.aluminum',
    11 => 'r.bakal',
    12 => 'r.yero',
    13 => 'r.glass',
];
$orderBy = $orderMap[$orderColumn] ?? 'r.date';

$sql = "
    SELECT
        r.id, r.date, r.name, r.mrf,
        r.puti, r.assorted, r.karton, r.pet, r.sibak,
        r.lata, r.aluminum, r.bakal, r.yero, r.glass
    FROM scavenger r
    WHERE r.archived = 0
      AND DATE(r.date) BETWEEN ? AND ?
";

$types = 'ss';
$params = [$startBound, $endBound];

if ($searchValue !== '') {
    $sql .= " AND (
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
    $types .= str_repeat('s', 13);
    $params = array_merge($params, array_fill(0, 13, $like));
}

$sql .= " ORDER BY {$orderBy} {$orderDir}";

$stmt = $conn->prepare($sql);
$rows = [];
if ($stmt && bind_dynamic_params($stmt, $types, $params) && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sorters Report Print</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 18px; color: #111827; }
    h2 { margin: 0 0 6px; font-size: 18px; }
    .meta { margin: 0 0 10px; font-size: 12px; color: #374151; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #d1d5db; padding: 6px 4px; text-align: center; vertical-align: middle; word-wrap: break-word; }
    th { background: #f3f4f6; font-weight: 700; }
    td.name { text-align: left; }
    @media print {
      @page { size: landscape; margin: 10mm; }
      body { margin: 0; }
    }
  </style>
</head>
<body>
  <h2>Sorter In and Out Record</h2>
  <p class="meta">
    Date Range:
    <?= h($startBound) ?> to <?= h($endBound) ?>
    <?php if ($searchValue !== ''): ?>
      | Search: "<?= h($searchValue) ?>"
    <?php endif; ?>
  </p>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Date</th>
        <th>Type of Sorter</th>
        <th>Papel Puti</th>
        <th>Papel Assorted</th>
        <th>Karton</th>
        <th>PET Bottle</th>
        <th>Sibak (Plastic)</th>
        <th>Lata</th>
        <th>Aluminum</th>
        <th>Bakal</th>
        <th>Yero</th>
        <th>Glass Bottles</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="14">No records found.</td></tr>
      <?php else: ?>
        <?php $i = 1; foreach ($rows as $row): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td class="name"><?= h((string)($row['name'] ?? '')) ?></td>
            <td><?= h(date('F j, Y', strtotime((string)($row['date'] ?? 'now')))) ?></td>
            <td><?= h((string)($row['mrf'] ?? '')) ?></td>
            <td><?= h(format_float_cell($row['puti'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['assorted'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['karton'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['pet'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['sibak'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['lata'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['aluminum'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['bakal'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['yero'] ?? 0)) ?></td>
            <td><?= h(format_float_cell($row['glass'] ?? 0)) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <script>
    (function () {
      const returnUrl = <?= json_encode($returnUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
      let finished = false;

      function returnToSystem() {
        if (finished) return;
        finished = true;

        try {
          if (window.opener && !window.opener.closed) {
            window.opener.focus();
          }
        } catch (e) {}

        try {
          window.close();
        } catch (e) {}

        setTimeout(function () {
          if (!window.closed) {
            window.location.replace(returnUrl);
          }
        }, 100);
      }

      window.addEventListener('afterprint', returnToSystem);

      if (window.matchMedia) {
        const mq = window.matchMedia('print');
        const onChange = function (ev) {
          if (!ev.matches) {
            setTimeout(returnToSystem, 50);
          }
        };

        if (typeof mq.addEventListener === 'function') {
          mq.addEventListener('change', onChange);
        } else if (typeof mq.addListener === 'function') {
          mq.addListener(onChange);
        }
      }

      window.addEventListener('load', function () {
        window.print();
      });
    })();

  </script>
</body>
</html>

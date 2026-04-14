<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('eco.violators');

function eco_attendance_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function eco_attendance_page_url(array $params = []): string
{
    $base = url_with_base('modules/eco_police/attendance.php');
    if ($params === []) {
        return $base;
    }
    return $base . '?' . http_build_query($params);
}

function eco_attendance_excel_escape($value): string
{
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$value);
    return htmlspecialchars((string)$sanitized, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function eco_attendance_excel_column_name(int $columnNumber): string
{
    $name = '';
    while ($columnNumber > 0) {
        $mod = ($columnNumber - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $columnNumber = intdiv($columnNumber - 1, 26);
    }

    return $name;
}

function eco_attendance_stream_xlsx(string $filename, array $headers, array $rows, array $columnWidths = []): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'eco_attendance_xlsx_');
    if ($tempFile === false) {
        return false;
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openResult !== true) {
        @unlink($tempFile);
        return false;
    }

    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $lastColumn = eco_attendance_excel_column_name(max(count($headers), 1));
    $lastRowNumber = max(count($rows) + 1, 1);

    $colsXml = '';
    foreach ($columnWidths as $index => $width) {
        $columnNumber = (int)$index + 1;
        $safeWidth = max(8, (float)$width);
        $colsXml .= '<col min="' . $columnNumber . '" max="' . $columnNumber . '" width="' . $safeWidth . '" customWidth="1"/>';
    }

    $sheetRowsXml = '';
    $headerCellsXml = '';
    foreach ($headers as $index => $value) {
        $cellRef = eco_attendance_excel_column_name($index + 1) . '1';
        $headerCellsXml .= '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t xml:space="preserve">'
            . eco_attendance_excel_escape($value)
            . '</t></is></c>';
    }
    $sheetRowsXml .= '<row r="1">' . $headerCellsXml . '</row>';

    foreach ($rows as $rowIndex => $row) {
        $rowNumber = $rowIndex + 2;
        $rowCellsXml = '';
        foreach ($headers as $columnIndex => $_header) {
            $cellRef = eco_attendance_excel_column_name($columnIndex + 1) . $rowNumber;
            $cellValue = $row[$columnIndex] ?? '';
            $rowCellsXml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t xml:space="preserve">'
                . eco_attendance_excel_escape($cellValue)
                . '</t></is></c>';
        }
        $sheetRowsXml .= '<row r="' . $rowNumber . '">' . $rowCellsXml . '</row>';
    }

    $contentTypesXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;

    $rootRelsXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;

    $appXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Excel</Application>
  <DocSecurity>0</DocSecurity>
  <ScaleCrop>false</ScaleCrop>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>1</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="1" baseType="lpstr">
      <vt:lpstr>Attendance</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
  <Company>CITY ENRO</Company>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>false</SharedDoc>
  <HyperlinksChanged>false</HyperlinksChanged>
  <AppVersion>16.0300</AppVersion>
</Properties>
XML;

    $coreXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:creator>CITY ENRO</dc:creator>
  <cp:lastModifiedBy>CITY ENRO</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
  <dc:title>Eco-Police Attendance Export</dc:title>
</cp:coreProperties>
XML;

    $workbookXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Attendance" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;

    $workbookRelsXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

    $stylesXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
</styleSheet>
XML;

    $sheetXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetViews>
    <sheetView workbookViewId="0"/>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="18"/>
  <cols>{$colsXml}</cols>
  <sheetData>{$sheetRowsXml}</sheetData>
  <autoFilter ref="A1:{$lastColumn}{$lastRowNumber}"/>
  <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>
</worksheet>
XML;

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $rootRelsXml);
    $zip->addFromString('docProps/app.xml', $appXml);
    $zip->addFromString('docProps/core.xml', $coreXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    readfile($tempFile);
    @unlink($tempFile);
    return true;
}

function eco_attendance_parse_date($raw, string $fallback): string
{
    $candidate = trim((string)$raw);
    if ($candidate === '') {
        return $fallback;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $candidate);
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }

    $timestamp = strtotime($candidate);
    if ($timestamp === false) {
        return $fallback;
    }
    return date('Y-m-d', $timestamp);
}

function eco_attendance_normalize_category($raw): string
{
    $value = strtolower(trim((string)$raw));
    return in_array($value, ['eco_police', 'monitoring'], true) ? $value : 'eco_police';
}

function eco_attendance_category_label(string $category): string
{
    return $category === 'monitoring' ? 'Monitoring' : 'Eco-Police';
}

function eco_attendance_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    $exists = (int)($row['total'] ?? 0) > 0;
    $stmt->close();
    return $exists;
}

function eco_attendance_tables_ready(mysqli $conn): bool
{
    return eco_attendance_table_exists($conn, 'eco_attendance_sheets')
        && eco_attendance_table_exists($conn, 'eco_attendance_entries');
}

function eco_attendance_ensure_tables(mysqli $conn): bool
{
    $sqlSheets = "CREATE TABLE IF NOT EXISTS eco_attendance_sheets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        attendance_date DATE NOT NULL,
        category VARCHAR(40) NOT NULL,
        created_by INT(11) DEFAULT NULL,
        updated_by INT(11) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ux_eco_attendance_sheet_date_category (attendance_date, category),
        KEY idx_eco_attendance_sheet_category_date (category, attendance_date),
        KEY idx_eco_attendance_sheet_created_by (created_by),
        KEY idx_eco_attendance_sheet_updated_by (updated_by),
        CONSTRAINT fk_eco_attendance_sheet_created_by
          FOREIGN KEY (created_by) REFERENCES user_form(id) ON DELETE SET NULL,
        CONSTRAINT fk_eco_attendance_sheet_updated_by
          FOREIGN KEY (updated_by) REFERENCES user_form(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sqlEntries = "CREATE TABLE IF NOT EXISTS eco_attendance_entries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sheet_id BIGINT UNSIGNED NOT NULL,
        employee_user_id INT(11) DEFAULT NULL,
        employee_name VARCHAR(190) NOT NULL,
        attendance_status VARCHAR(20) NOT NULL DEFAULT 'absent',
        note_reason VARCHAR(255) DEFAULT NULL,
        sort_order INT(11) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_eco_attendance_entries_sheet (sheet_id),
        KEY idx_eco_attendance_entries_employee (employee_user_id),
        KEY idx_eco_attendance_entries_status (attendance_status),
        CONSTRAINT fk_eco_attendance_entries_sheet
          FOREIGN KEY (sheet_id) REFERENCES eco_attendance_sheets(id) ON DELETE CASCADE,
        CONSTRAINT fk_eco_attendance_entries_employee
          FOREIGN KEY (employee_user_id) REFERENCES user_form(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Create sheets table first, then entries table.
    if (!$conn->query($sqlSheets)) {
        return false;
    }
    if (!$conn->query($sqlEntries)) {
        return false;
    }

    return eco_attendance_tables_ready($conn);
}

function eco_attendance_fetch_available_employees(mysqli $conn, string $category): array
{
    $roleKey = ($category === 'monitoring') ? 'monitoring' : 'eco_police';
    $sql = "SELECT u.id, u.name, u.email
            FROM user_form u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.role_name = ?
              AND u.status = 'active'
            ORDER BY u.name ASC, u.email ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $roleKey);
    $stmt->execute();
    $res = $stmt->get_result();

    $employees = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $display = trim((string)($row['name'] ?? ''));
        if ($display === '') {
            $display = trim((string)($row['email'] ?? ''));
        }
        if ($display === '') {
            continue;
        }
        $employees[] = [
            'id' => (int)$row['id'],
            'name' => $display,
        ];
    }

    $stmt->close();
    return $employees;
}

function eco_attendance_find_sheet(mysqli $conn, string $date, string $category): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, attendance_date, category
         FROM eco_attendance_sheets
         WHERE attendance_date = ? AND category = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ss', $date, $category);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? $row : null;
}

function eco_attendance_fetch_entries(mysqli $conn, int $sheetId): array
{
    if ($sheetId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT employee_user_id, employee_name, attendance_status, note_reason, sort_order
         FROM eco_attendance_entries
         WHERE sheet_id = ?
         ORDER BY sort_order ASC, id ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $sheetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function eco_attendance_compose_rows(array $employees, array $existingEntries): array
{
    $employeeMap = [];
    foreach ($employees as $emp) {
        $employeeMap[(int)$emp['id']] = (string)$emp['name'];
    }

    $rows = [];
    if (!empty($existingEntries)) {
        foreach ($existingEntries as $entry) {
            $uid = (int)($entry['employee_user_id'] ?? 0);
            $name = trim((string)($entry['employee_name'] ?? ''));
            if ($name === '' && $uid > 0 && isset($employeeMap[$uid])) {
                $name = $employeeMap[$uid];
            }
            if ($name === '' && $uid > 0) {
                $name = 'User #' . $uid;
            }
            if ($name === '' && $uid <= 0) {
                continue;
            }

            $status = strtolower(trim((string)($entry['attendance_status'] ?? 'absent')));
            if ($status !== 'present') {
                $status = 'absent';
            }

            $rows[] = [
                'employee_user_id' => $uid,
                'employee_name' => $name,
                'attendance_status' => $status,
                'note_reason' => trim((string)($entry['note_reason'] ?? '')),
                'locked' => $uid > 0,
            ];
        }
    }

    if (empty($rows)) {
        $rows[] = [
            'employee_user_id' => 0,
            'employee_name' => '',
            'attendance_status' => 'absent',
            'note_reason' => '',
            'locked' => false,
        ];
    }

    return $rows;
}

function eco_attendance_normalize_row(array $row, array $employeeNameById = []): ?array
{
    $uid = (int)($row['employee_user_id'] ?? 0);
    $name = trim((string)($row['employee_name'] ?? ''));
    if ($name === '' && $uid > 0 && isset($employeeNameById[$uid])) {
        $name = trim((string)$employeeNameById[$uid]);
    }
    if ($name === '' && $uid > 0) {
        $name = 'User #' . $uid;
    }
    if ($name === '' && $uid <= 0) {
        return null;
    }

    $status = strtolower(trim((string)($row['attendance_status'] ?? 'absent')));
    if ($status !== 'present') {
        $status = 'absent';
    }

    $note = trim((string)($row['note_reason'] ?? ''));
    if (strlen($name) > 190) {
        $name = substr($name, 0, 190);
    }
    if (strlen($note) > 255) {
        $note = substr($note, 0, 255);
    }

    return [
        'employee_user_id' => $uid,
        'employee_name' => $name,
        'attendance_status' => $status,
        'note_reason' => $note,
    ];
}

function eco_attendance_employee_filter_key(int $employeeUserId, string $employeeName): string
{
    if ($employeeUserId > 0) {
        return 'user:' . $employeeUserId;
    }

    $normalizedName = strtolower(trim($employeeName));
    if ($normalizedName === '') {
        return '';
    }

    return 'name:' . sha1($normalizedName);
}

function eco_attendance_merge_rows(array $existingEntries, array $submittedRows, array $employeeNameById = []): array
{
    $merged = [];

    foreach ($existingEntries as $entry) {
        $normalized = eco_attendance_normalize_row($entry, $employeeNameById);
        if ($normalized === null) {
            continue;
        }

        $identityKey = eco_attendance_employee_filter_key(
            (int)$normalized['employee_user_id'],
            (string)$normalized['employee_name']
        );
        if ($identityKey === '') {
            continue;
        }

        $merged[$identityKey] = $normalized;
    }

    foreach ($submittedRows as $row) {
        $normalized = eco_attendance_normalize_row($row, $employeeNameById);
        if ($normalized === null) {
            continue;
        }

        $identityKey = eco_attendance_employee_filter_key(
            (int)$normalized['employee_user_id'],
            (string)$normalized['employee_name']
        );
        if ($identityKey === '') {
            continue;
        }

        $merged[$identityKey] = $normalized;
    }

    return array_values($merged);
}

function eco_attendance_save_sheet(mysqli $conn, string $date, string $category, int $actorId, array $rows): bool
{
    if (empty($rows)) {
        return false;
    }

    $conn->begin_transaction();

    try {
        $sheetStmt = $conn->prepare(
            "INSERT INTO eco_attendance_sheets
             (attendance_date, category, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, NULLIF(?, 0), NULLIF(?, 0), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                updated_by = NULLIF(?, 0),
                updated_at = NOW()"
        );
        if (!$sheetStmt) {
            throw new RuntimeException('Unable to prepare sheet upsert.');
        }

        $sheetStmt->bind_param('ssiii', $date, $category, $actorId, $actorId, $actorId);
        if (!$sheetStmt->execute()) {
            throw new RuntimeException('Unable to upsert attendance sheet.');
        }
        $sheetStmt->close();

        $sheetFindStmt = $conn->prepare(
            "SELECT id
             FROM eco_attendance_sheets
             WHERE attendance_date = ? AND category = ?
             LIMIT 1"
        );
        if (!$sheetFindStmt) {
            throw new RuntimeException('Unable to prepare sheet lookup.');
        }
        $sheetFindStmt->bind_param('ss', $date, $category);
        $sheetFindStmt->execute();
        $sheetResult = $sheetFindStmt->get_result();
        $sheetRow = $sheetResult ? $sheetResult->fetch_assoc() : null;
        $sheetFindStmt->close();

        $sheetId = (int)($sheetRow['id'] ?? 0);
        if ($sheetId <= 0) {
            throw new RuntimeException('Attendance sheet ID not found.');
        }

        $deleteStmt = $conn->prepare('DELETE FROM eco_attendance_entries WHERE sheet_id = ?');
        if (!$deleteStmt) {
            throw new RuntimeException('Unable to clear old entries.');
        }
        $deleteStmt->bind_param('i', $sheetId);
        if (!$deleteStmt->execute()) {
            throw new RuntimeException('Unable to clear old entries.');
        }
        $deleteStmt->close();

        $insertStmt = $conn->prepare(
            "INSERT INTO eco_attendance_entries
             (sheet_id, employee_user_id, employee_name, attendance_status, note_reason, sort_order, created_at, updated_at)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, NOW(), NOW())"
        );
        if (!$insertStmt) {
            throw new RuntimeException('Unable to prepare entry insert.');
        }

        $sortOrder = 0;
        foreach ($rows as $row) {
            $uid = (int)($row['employee_user_id'] ?? 0);
            $name = trim((string)($row['employee_name'] ?? ''));
            $status = strtolower(trim((string)($row['attendance_status'] ?? 'absent'))) === 'present' ? 'present' : 'absent';
            $note = trim((string)($row['note_reason'] ?? ''));

            if ($name === '' && $uid <= 0) {
                continue;
            }
            if ($name === '' && $uid > 0) {
                $name = 'User #' . $uid;
            }

            $sortOrder++;
            $insertStmt->bind_param('iisssi', $sheetId, $uid, $name, $status, $note, $sortOrder);
            if (!$insertStmt->execute()) {
                throw new RuntimeException('Unable to insert attendance row.');
            }
        }

        $insertStmt->close();
        $conn->commit();
        return true;
    } catch (Throwable $t) {
        $conn->rollback();
        return false;
    }
}

function eco_attendance_delete_sheet(mysqli $conn, int $sheetId): bool
{
    if ($sheetId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('DELETE FROM eco_attendance_sheets WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $sheetId);
    $deleted = $stmt->execute();
    $stmt->close();

    return $deleted;
}

function eco_attendance_delete_rows(
    mysqli $conn,
    string $date,
    string $category,
    array $targets,
    int $actorId,
    array $employeeNameById = []
): bool {
    $sheet = eco_attendance_find_sheet($conn, $date, $category);
    if (!is_array($sheet)) {
        return false;
    }

    $targetKeys = [];
    foreach ($targets as $target) {
        $targetUserId = (int)($target['employee_user_id'] ?? 0);
        $targetName = trim((string)($target['employee_name'] ?? ''));
        $targetKey = eco_attendance_employee_filter_key($targetUserId, $targetName);
        if ($targetKey === '') {
            continue;
        }

        $targetKeys[$targetKey] = true;
    }

    if (empty($targetKeys)) {
        return false;
    }

    $existingRows = eco_attendance_fetch_entries($conn, (int)($sheet['id'] ?? 0));
    if (empty($existingRows)) {
        return false;
    }

    $remainingRows = [];
    $deleted = false;

    foreach ($existingRows as $entry) {
        $normalized = eco_attendance_normalize_row($entry, $employeeNameById);
        if ($normalized === null) {
            continue;
        }

        $entryKey = eco_attendance_employee_filter_key(
            (int)$normalized['employee_user_id'],
            (string)$normalized['employee_name']
        );

        if (isset($targetKeys[$entryKey])) {
            $deleted = true;
            continue;
        }

        $remainingRows[] = $normalized;
    }

    if (!$deleted) {
        return false;
    }

    if (empty($remainingRows)) {
        return eco_attendance_delete_sheet($conn, (int)($sheet['id'] ?? 0));
    }

    return eco_attendance_save_sheet($conn, $date, $category, $actorId, $remainingRows);
}

function eco_attendance_delete_row(
    mysqli $conn,
    string $date,
    string $category,
    int $employeeUserId,
    string $employeeName,
    int $actorId,
    array $employeeNameById = []
): bool {
    return eco_attendance_delete_rows(
        $conn,
        $date,
        $category,
        [[
            'employee_user_id' => $employeeUserId,
            'employee_name' => $employeeName,
        ]],
        $actorId,
        $employeeNameById
    );
}

function eco_attendance_fetch_report_rows(
    mysqli $conn,
    string $attendanceDate,
    string $category
): array {
    $rows = [];

    $sql = "SELECT
                COALESCE(e.employee_user_id, 0) AS employee_user_id,
                TRIM(COALESCE(e.employee_name, '')) AS employee_name,
                TRIM(COALESCE(e.attendance_status, 'absent')) AS attendance_status,
                TRIM(COALESCE(e.note_reason, '')) AS note_reason
            FROM eco_attendance_entries e
            INNER JOIN eco_attendance_sheets s ON s.id = e.sheet_id
            WHERE s.attendance_date = ?
              AND s.category = ?
            ORDER BY e.sort_order ASC, e.id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ss', $attendanceDate, $category);

    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $name = trim((string)($row['employee_name'] ?? ''));
        $uid = (int)($row['employee_user_id'] ?? 0);
        if ($name === '') {
            $name = $uid > 0 ? 'User #' . $uid : 'Unnamed Employee';
        }

        $status = strtolower(trim((string)($row['attendance_status'] ?? 'absent')));
        if ($status !== 'present') {
            $status = 'absent';
        }
        $note = trim((string)($row['note_reason'] ?? ''));
        $uid = (int)($row['employee_user_id'] ?? 0);

        $rows[] = [
            'employee_user_id' => $uid,
            'employee_name' => $name,
            'attendance_status' => $status,
            'note_reason' => $note,
        ];
    }
    $stmt->close();

    return $rows;
}

$today = date('Y-m-d');
$selectedDate = eco_attendance_parse_date($_REQUEST['attendance_date'] ?? '', $today);
$selectedCategory = eco_attendance_normalize_category($_REQUEST['attendance_category'] ?? 'eco_police');

$reportDate = eco_attendance_parse_date($_GET['report_date'] ?? '', $selectedDate);
$reportCategory = eco_attendance_normalize_category($_GET['report_category'] ?? $selectedCategory);
$statusFlag = trim((string)($_GET['status'] ?? ''));
$reportStatusFlag = trim((string)($_GET['report_status'] ?? ''));
$focusCreateSection = (trim((string)($_GET['open_create'] ?? '')) === '1');

$tablesReady = eco_attendance_tables_ready($conn);
if (!$tablesReady) {
    eco_attendance_ensure_tables($conn);
    $tablesReady = eco_attendance_tables_ready($conn);
}
if ($tablesReady && $statusFlag === 'table_missing') {
    $statusFlag = '';
}
if (in_array($statusFlag, ['saved', 'save_failed', 'no_rows', 'table_missing'], true)) {
    $focusCreateSection = true;
}
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'save_attendance_sheet') {
        if (!$tablesReady) {
            header('Location: ' . eco_attendance_page_url([
                'attendance_date' => $selectedDate,
                'attendance_category' => $selectedCategory,
                'status' => 'table_missing',
                'open_create' => '1',
            ]));
            exit;
        }

        $postedUserIds = $_POST['employee_user_id'] ?? [];
        $postedNames = $_POST['attendance_employee_name'] ?? [];
        $postedStatuses = $_POST['attendance_status'] ?? [];
        $postedNotes = $_POST['attendance_note_reason'] ?? [];

        if (!is_array($postedUserIds)) {
            $postedUserIds = [];
        }
        if (!is_array($postedNames)) {
            $postedNames = [];
        }
        if (!is_array($postedStatuses)) {
            $postedStatuses = [];
        }
        if (!is_array($postedNotes)) {
            $postedNotes = [];
        }

        $employeesForCategory = eco_attendance_fetch_available_employees($conn, $selectedCategory);
        $employeeNameById = [];
        foreach ($employeesForCategory as $emp) {
            $employeeNameById[(int)$emp['id']] = (string)$emp['name'];
        }

        $maxRows = max(count($postedNames), count($postedUserIds), count($postedStatuses), count($postedNotes));
        $maxRows = min($maxRows, 300);

        $rowsToSave = [];
        for ($i = 0; $i < $maxRows; $i++) {
            $uid = isset($postedUserIds[$i]) ? (int)$postedUserIds[$i] : 0;
            $normalizedRow = eco_attendance_normalize_row([
                'employee_user_id' => $uid,
                'employee_name' => $postedNames[$i] ?? '',
                'attendance_status' => $postedStatuses[$i] ?? 'absent',
                'note_reason' => $postedNotes[$i] ?? '',
            ], $employeeNameById);
            if ($normalizedRow === null) {
                continue;
            }

            $rowsToSave[] = $normalizedRow;
        }

        if (empty($rowsToSave)) {
            header('Location: ' . eco_attendance_page_url([
                'attendance_date' => $selectedDate,
                'attendance_category' => $selectedCategory,
                'status' => 'no_rows',
                'open_create' => '1',
            ]));
            exit;
        }

        $sheetForSave = eco_attendance_find_sheet($conn, $selectedDate, $selectedCategory);
        if (is_array($sheetForSave)) {
            $existingRows = eco_attendance_fetch_entries($conn, (int)($sheetForSave['id'] ?? 0));
            $rowsToSave = eco_attendance_merge_rows($existingRows, $rowsToSave, $employeeNameById);
        }

        $saved = eco_attendance_save_sheet($conn, $selectedDate, $selectedCategory, $currentUserId, $rowsToSave);
        header('Location: ' . eco_attendance_page_url([
            'attendance_date' => $selectedDate,
            'attendance_category' => $selectedCategory,
            'status' => $saved ? 'saved' : 'save_failed',
            'open_create' => '1',
        ]));
        exit;
    } elseif ($action === 'delete_attendance_row') {
        if (!$tablesReady) {
            header('Location: ' . eco_attendance_page_url([
                'attendance_date' => $selectedDate,
                'attendance_category' => $selectedCategory,
                'status' => 'table_missing',
            ]));
            exit;
        }

        $preserveAttendanceDate = eco_attendance_parse_date($_POST['attendance_date'] ?? '', $selectedDate);
        $preserveAttendanceCategory = eco_attendance_normalize_category($_POST['attendance_category'] ?? $selectedCategory);
        $deleteReportDate = eco_attendance_parse_date($_POST['report_date'] ?? '', $reportDate);
        $deleteReportCategory = eco_attendance_normalize_category($_POST['report_category'] ?? $reportCategory);
        $deleteEmployeeUserId = (int)($_POST['delete_employee_user_id'] ?? 0);
        $deleteEmployeeName = trim((string)($_POST['delete_employee_name'] ?? ''));

        $employeesForDelete = eco_attendance_fetch_available_employees($conn, $deleteReportCategory);
        $employeeNameById = [];
        foreach ($employeesForDelete as $emp) {
            $employeeNameById[(int)$emp['id']] = (string)$emp['name'];
        }

        $deleted = eco_attendance_delete_row(
            $conn,
            $deleteReportDate,
            $deleteReportCategory,
            $deleteEmployeeUserId,
            $deleteEmployeeName,
            $currentUserId,
            $employeeNameById
        );

        header('Location: ' . eco_attendance_page_url([
            'attendance_date' => $preserveAttendanceDate,
            'attendance_category' => $preserveAttendanceCategory,
            'report_date' => $deleteReportDate,
            'report_category' => $deleteReportCategory,
            'report_status' => $deleted ? 'deleted' : 'delete_failed',
        ]));
        exit;
    } elseif ($action === 'delete_attendance_rows') {
        if (!$tablesReady) {
            header('Location: ' . eco_attendance_page_url([
                'attendance_date' => $selectedDate,
                'attendance_category' => $selectedCategory,
                'status' => 'table_missing',
            ]));
            exit;
        }

        $preserveAttendanceDate = eco_attendance_parse_date($_POST['attendance_date'] ?? '', $selectedDate);
        $preserveAttendanceCategory = eco_attendance_normalize_category($_POST['attendance_category'] ?? $selectedCategory);
        $deleteReportDate = eco_attendance_parse_date($_POST['report_date'] ?? '', $reportDate);
        $deleteReportCategory = eco_attendance_normalize_category($_POST['report_category'] ?? $reportCategory);
        $deleteEmployeeUserIds = $_POST['delete_employee_user_id'] ?? [];
        $deleteEmployeeNames = $_POST['delete_employee_name'] ?? [];

        if (!is_array($deleteEmployeeUserIds)) {
            $deleteEmployeeUserIds = [];
        }
        if (!is_array($deleteEmployeeNames)) {
            $deleteEmployeeNames = [];
        }

        $targets = [];
        $deleteTargetCount = max(count($deleteEmployeeUserIds), count($deleteEmployeeNames));
        for ($i = 0; $i < $deleteTargetCount; $i++) {
            $targets[] = [
                'employee_user_id' => isset($deleteEmployeeUserIds[$i]) ? (int)$deleteEmployeeUserIds[$i] : 0,
                'employee_name' => $deleteEmployeeNames[$i] ?? '',
            ];
        }

        $employeesForDelete = eco_attendance_fetch_available_employees($conn, $deleteReportCategory);
        $employeeNameById = [];
        foreach ($employeesForDelete as $emp) {
            $employeeNameById[(int)$emp['id']] = (string)$emp['name'];
        }

        $deleted = eco_attendance_delete_rows(
            $conn,
            $deleteReportDate,
            $deleteReportCategory,
            $targets,
            $currentUserId,
            $employeeNameById
        );

        header('Location: ' . eco_attendance_page_url([
            'attendance_date' => $preserveAttendanceDate,
            'attendance_category' => $preserveAttendanceCategory,
            'report_date' => $deleteReportDate,
            'report_category' => $deleteReportCategory,
            'report_status' => $deleted ? 'deleted' : 'delete_failed',
        ]));
        exit;
    }
}

$sheetExists = false;
$sheetEntryCount = 0;
$createSheetRows = [];
$existingSheet = null;

$createEmployees = $tablesReady ? eco_attendance_fetch_available_employees($conn, $selectedCategory) : [];
$createSheetRows = eco_attendance_compose_rows($createEmployees, []);

if ($tablesReady) {
    $existingSheet = eco_attendance_find_sheet($conn, $selectedDate, $selectedCategory);
    if (is_array($existingSheet)) {
        $sheetExists = true;
        $entries = eco_attendance_fetch_entries($conn, (int)$existingSheet['id']);
        $sheetEntryCount = count($entries);
    }
}

$reportSheet = $tablesReady ? eco_attendance_find_sheet($conn, $reportDate, $reportCategory) : null;
$reportSheetExists = is_array($reportSheet);
$reportAllRows = $tablesReady
    ? eco_attendance_fetch_report_rows($conn, $reportDate, $reportCategory)
    : [];
$reportSheetEntryCount = count($reportAllRows);
$reportRows = $reportAllRows;

$downloadParams = [
    'attendance_date' => $selectedDate,
    'attendance_category' => $selectedCategory,
    'report_date' => $reportDate,
    'report_category' => $reportCategory,
    'download' => 'excel',
];
$downloadUrl = eco_attendance_page_url($downloadParams);

if (in_array((string)($_GET['download'] ?? ''), ['excel', 'csv'], true)) {
    if (!$tablesReady) {
        header('Location: ' . eco_attendance_page_url([
            'attendance_date' => $selectedDate,
            'attendance_category' => $selectedCategory,
            'status' => 'table_missing',
        ]));
        exit;
    }

    if (($_GET['download'] ?? '') === 'excel') {
        $excelRows = [];
        foreach ($reportRows as $row) {
            $excelRows[] = [
                date('m/d/Y', strtotime($reportDate)),
                eco_attendance_category_label($reportCategory),
                $row['employee_name'],
                ucfirst((string)$row['attendance_status']),
                (string)$row['note_reason'],
            ];
        }

        $excelFilename = 'eco-police-attendance-' . $reportDate . '-' . $reportCategory . '.xlsx';
        $excelExported = eco_attendance_stream_xlsx(
            $excelFilename,
            ['Date', 'Category', 'Employee Name', 'Status', 'Note / Reason'],
            $excelRows,
            [16, 18, 28, 14, 36]
        );
        if ($excelExported) {
            exit;
        }
    }

    $csvName = 'eco-police-attendance-' . $reportDate . '-' . $reportCategory . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $csvName . '"');

    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, ['Date', 'Category', 'Employee Name', 'Status', 'Note / Reason']);
        foreach ($reportRows as $row) {
            fputcsv($out, [
                "'" . date('m/d/Y', strtotime($reportDate)),
                eco_attendance_category_label($reportCategory),
                $row['employee_name'],
                ucfirst((string)$row['attendance_status']),
                $row['note_reason'],
            ]);
        }
        fclose($out);
    }
    exit;
}

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>
<style>
  html,
  body {
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  html::-webkit-scrollbar,
  body::-webkit-scrollbar {
    width: 0;
    height: 0;
  }
  .page-wrapper {
    background:
      radial-gradient(circle at top, rgba(86, 155, 118, 0.08), transparent 28%),
      linear-gradient(180deg, #edf3f6 0%, #f6f9fb 55%, #eef4f7 100%);
  }
  .page-wrapper .page-breadcrumb {
    display: none !important;
  }
  .eco-attendance-module {
    max-width: 1180px;
    margin: 0 auto;
    padding: 22px 18px 32px;
    color: #203548;
  }
  .eco-attendance-module .card {
    border: 1px solid #dde7ee;
    border-radius: 20px;
    box-shadow: 0 18px 46px rgba(15, 23, 42, 0.07);
    background: rgba(255, 255, 255, 0.96);
    overflow: hidden;
  }
  .eco-attendance-module .card .card-header {
    border-bottom: 1px solid #e3ebf1;
    background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%) !important;
    padding: 16px 20px;
  }
  .eco-attendance-module .card .card-header h5,
  .eco-attendance-module .card .card-header h6 {
    margin: 0;
    font-weight: 700;
    color: #15364d;
    font-size: 1.25rem;
  }
  .eco-attendance-module .card .card-body {
    padding: 18px 20px;
  }
  .eco-attendance-module .attendance-hero-card {
    background:
      radial-gradient(circle at top left, rgba(255, 255, 255, 0.98), rgba(247, 251, 253, 0.95)),
      linear-gradient(180deg, #fbfdff 0%, #f3f8fb 100%);
  }
  .eco-attendance-module .attendance-hero-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
    padding: 18px 22px 16px;
  }
  .eco-attendance-module .attendance-hero-copy {
    max-width: 920px;
  }
  .eco-attendance-module .attendance-hero-eyebrow {
    font-size: 0.74rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #61778b;
    letter-spacing: 0.14em;
    margin-bottom: 0.2rem;
  }
  .eco-attendance-module .attendance-hero-title {
    margin: 0;
    font-size: clamp(1.75rem, 2.7vw, 2.45rem);
    line-height: 1.05;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: #22384d;
    max-width: 780px;
    margin-left: auto;
    margin-right: auto;
  }
  .eco-attendance-module .attendance-hero-actions {
    display: inline-flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
    width: min(100%, 760px);
    padding: 10px 12px;
    border: 1px solid rgba(210, 222, 231, 0.9);
    background: rgba(255, 255, 255, 0.72);
    border-radius: 18px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
  }
  .eco-attendance-module .attendance-hero-filter-shell {
    width: min(100%, 1120px);
    margin-top: 2px;
    padding-top: 16px;
    border-top: 1px solid rgba(210, 222, 231, 0.92);
  }
  .eco-attendance-module .attendance-hero-filter-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 12px;
    font-size: 1.02rem;
    font-weight: 700;
    color: #173b60;
    text-align: left;
  }
  .eco-attendance-module .attendance-hero-filter-form {
    text-align: left;
  }
  .eco-attendance-module .attendance-toolbar-btn.btn {
    min-width: 160px;
    min-height: 48px;
    border-radius: 15px;
    padding: 0.72rem 1.15rem;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    border: 1px solid transparent !important;
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
    transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease, border-color 0.18s ease;
  }
  .eco-attendance-module .attendance-toolbar-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
  }
  .eco-attendance-module .attendance-toolbar-btn-primary.btn {
    background: linear-gradient(135deg, #1b8f5c 0%, #157347 100%);
    border-color: #157347 !important;
    color: #fff !important;
  }
  .eco-attendance-module .attendance-toolbar-btn-primary.btn:hover {
    background: linear-gradient(135deg, #22a768 0%, #176940 100%);
    color: #fff !important;
  }
  .eco-attendance-module .attendance-toolbar-btn-secondary.btn {
    background: rgba(255, 255, 255, 0.82);
    border-color: #cfe0d7 !important;
    color: #1a6e4a !important;
  }
  .eco-attendance-module .attendance-toolbar-btn-secondary.btn:hover {
    background: #ffffff;
    color: #145c3e !important;
  }
  .eco-attendance-module .btn:not(.attendance-toolbar-btn),
  .eco-attendance-module .btn-action-left,
  .eco-attendance-module .btn-outline-secondary {
    background: linear-gradient(180deg, #ffffff 0%, #f4f7fa 100%);
    border: 1px solid #d2dde6 !important;
    color: #27455a !important;
    font-size: 0.9rem;
    font-weight: 700;
    border-radius: 14px;
    padding: 0.72rem 1.15rem;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
  }
  .eco-attendance-module .btn:not(.attendance-toolbar-btn):hover {
    background: #ffffff;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.1);
  }
  .eco-attendance-module .btn-action-left[type="submit"]:not(.btn-sm) {
    background: linear-gradient(135deg, #1b8f5c 0%, #157347 100%);
    border-color: #157347 !important;
    color: #fff !important;
    min-width: 210px;
    box-shadow: 0 14px 28px rgba(21, 115, 71, 0.22);
  }
  .eco-attendance-module .form-group-card {
    margin-bottom: 10px !important;
    background: #f7fafc;
    border: 1px solid #dce6ee;
    border-radius: 16px;
    padding: 12px 12px 4px;
  }
  .eco-attendance-module .form-group-card .form-label {
    font-size: 0.85rem;
    font-weight: 700;
    margin-bottom: 4px;
    color: #4b6578;
  }
  .eco-attendance-module .form-control,
  .eco-attendance-module .form-select {
    border: 1px solid #cad8e3;
    border-radius: 14px;
    min-height: 44px;
    font-size: 0.95rem;
    color: #173b60;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
  }
  .eco-attendance-module .alert {
    border-radius: 16px;
    border-color: #d8e3ea;
    font-size: 0.92rem;
    padding: 12px 14px;
  }
  .eco-attendance-module .alert-secondary {
    background: #eef4f8;
    color: #476171;
  }
  .eco-attendance-module .scrollable-table-wrapper table {
    margin: 0;
  }
  .eco-attendance-module .table.table-bordered {
    border-color: #dfe8ef;
  }
  .eco-attendance-module .table.table-bordered thead th {
    background: linear-gradient(180deg, #f8fbfd 0%, #edf3f8 100%) !important;
    border-color: #dce6ee;
    font-size: 0.82rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    color: #13344f;
    text-align: center;
    white-space: nowrap;
    padding: 14px 12px;
  }
  .eco-attendance-module .table.table-bordered td {
    border-color: #e4edf3;
    background: #fff;
    font-size: 0.95rem;
    padding: 14px 12px;
    vertical-align: middle;
    color: #27455a;
  }
  .eco-attendance-module .table.table-hover tbody tr:hover td {
    background: #f8fbfd;
  }
  .eco-attendance-module .attendance-status-pill {
    min-width: 56px;
    border: 0 !important;
    background: transparent !important;
    font-size: 0.86rem;
    font-weight: 700;
    color: #66788a;
    padding: 0;
  }
  .eco-attendance-module .attendance-status-pill.bg-success {
    color: #1b7b53;
  }
  .eco-attendance-module .attendance-status-pill.bg-secondary {
    color: #8b5b5b;
  }
  .eco-attendance-module .attendance-status-switch {
    width: 2.2em;
    height: 1.2em;
    cursor: pointer;
    border-color: #98acbb;
  }
  .eco-attendance-module .attendance-status-switch:checked {
    background-color: #7ca489;
    border-color: #688c74;
  }
  .eco-attendance-module .attendance-report-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
  }
  .eco-attendance-module .attendance-report-period {
    font-size: 0.82rem;
    color: #66788a;
    text-align: right;
  }
  .eco-attendance-module .attendance-report-status-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    min-height: 34px;
    width: 100%;
  }
  .eco-attendance-module .attendance-report-state {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0.42rem 0.72rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1;
  }
  .eco-attendance-module .attendance-report-state.available {
    background: #edf7f1;
    border: 1px solid #cfe6d8;
    color: #1b7b53;
  }
  .eco-attendance-module .attendance-report-state.empty {
    background: #f3f6f9;
    border: 1px solid #d9e3ea;
    color: #5f7387;
  }
  .eco-attendance-module .attendance-filter-card .card-header,
  .eco-attendance-module .attendance-report-card .card-header {
    padding-top: 15px;
    padding-bottom: 15px;
  }
  .eco-attendance-module .attendance-filter-card .card-body {
    padding-top: 14px;
    padding-bottom: 16px;
  }
  .eco-attendance-module .attendance-filter-form .form-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #5b7086;
    margin-bottom: 6px;
  }
  .eco-attendance-module .attendance-filter-form .form-control,
  .eco-attendance-module .attendance-filter-form .form-select {
    min-height: 40px;
    font-size: 0.92rem;
    border-radius: 13px;
    padding: 0.55rem 0.9rem;
  }
  .eco-attendance-module .attendance-report-card .card-body {
    padding-top: 18px;
  }
  .eco-attendance-module .attendance-report-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 230px;
    padding: 20px;
    text-align: center;
    border: 1px dashed #d7e2ea;
    border-radius: 18px;
    background: linear-gradient(180deg, #fbfdff 0%, #f4f8fb 100%);
  }
  .eco-attendance-module .attendance-report-empty-icon {
    width: 56px;
    height: 56px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: #edf3f7;
    color: #5f7387;
    font-size: 1.35rem;
    margin-bottom: 12px;
  }
  .eco-attendance-module .attendance-report-empty h6 {
    margin: 0 0 6px;
    font-size: 1.12rem;
    font-weight: 700;
    color: #213a4f;
  }
  .eco-attendance-module .attendance-report-empty p {
    margin: 0;
    max-width: 430px;
    font-size: 0.92rem;
    color: #698095;
    line-height: 1.5;
  }
  .eco-attendance-module .attendance-report-feedback {
    margin-bottom: 14px;
  }
  .eco-attendance-module .attendance-search-empty {
    display: none;
  }
  .eco-attendance-module .attendance-report-actions .btn {
    min-height: 46px;
    padding: 0.68rem 1.05rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1.15;
  }
  .eco-attendance-module .attendance-report-actions .btn-outline-secondary,
  .eco-attendance-module .attendance-report-actions .btn-action-left {
    font-size: 0.92rem;
  }
  .eco-attendance-module .attendance-bulk-delete-form {
    display: none;
    margin: 0;
  }
  .eco-attendance-module .attendance-bulk-delete-form.is-visible {
    display: block;
  }
  .eco-attendance-module .attendance-bulk-delete-btn.btn {
    min-height: 34px;
    padding: 0.5rem 0.92rem;
    border-radius: 999px;
    border: 1px solid #d8c2c7 !important;
    background: linear-gradient(180deg, #fff8f8 0%, #fff1f3 100%);
    color: #a23f54 !important;
    font-size: 0.8rem;
    font-weight: 700;
    box-shadow: none;
    white-space: nowrap;
  }
  .eco-attendance-module .attendance-bulk-delete-btn.btn:hover {
    background: #fff7f8;
    color: #8f3146 !important;
    transform: none;
    box-shadow: 0 10px 20px rgba(162, 63, 84, 0.08);
  }
  .eco-attendance-module .attendance-row-select-cell {
    width: 60px;
  }
  .eco-attendance-module .attendance-row-select-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .eco-attendance-module .attendance-row-select,
  .eco-attendance-module .attendance-select-all {
    width: 18px;
    height: 18px;
    accent-color: #198754;
    cursor: pointer;
  }
  .eco-attendance-module .attendance-row-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .eco-attendance-module .attendance-row-action-btn.btn {
    min-height: 36px;
    padding: 0.5rem 0.95rem;
    border-radius: 12px;
    border: 1px solid #d3e0e9 !important;
    background: linear-gradient(180deg, #ffffff 0%, #f4f8fb 100%);
    color: #244a66 !important;
    font-size: 0.84rem;
    font-weight: 700;
    box-shadow: none;
  }
  .eco-attendance-module .attendance-row-action-btn.btn:hover {
    transform: none;
    background: #ffffff;
    color: #173b60 !important;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
  }
  html.create-attendance-modal-active,
  body.create-attendance-modal-active {
    overflow: hidden !important;
  }
  body.create-attendance-modal-active {
    padding-right: 0 !important;
  }
  .create-attendance-modal {
    padding-right: 0 !important;
    overflow-y: hidden !important;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .create-attendance-modal::-webkit-scrollbar {
    width: 0;
    height: 0;
  }
  .create-attendance-modal .modal-dialog {
    width: min(1180px, calc(100vw - 2rem));
    max-width: 1180px;
    margin: 1rem auto;
  }
  .create-attendance-modal .modal-content {
    width: 100%;
    max-height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
    border: 1px solid #d8e3ea;
    border-radius: 24px;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fbfd 0%, #eef4f8 100%);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
  }
  .create-attendance-modal .modal-header {
    background: linear-gradient(180deg, #ffffff 0%, #f3f8fb 100%);
    border-bottom: 1px solid #dbe6ee;
    padding: 16px 20px;
  }
  .create-attendance-modal .modal-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #173b60;
  }
  .create-attendance-modal .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    max-height: calc(100vh - 180px);
    background: transparent;
    padding: 20px;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .create-attendance-modal .modal-body::-webkit-scrollbar {
    width: 0;
    height: 0;
  }
  .create-attendance-modal .alert {
    border-radius: 16px;
    font-size: 0.92rem;
    margin-bottom: 12px;
  }
  .create-modal-controls {
    margin: 0 0 14px !important;
    padding: 16px;
    border: 1px solid #dce7ee;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.82);
  }
  .create-modal-controls .form-label {
    font-size: 0.9rem;
    font-weight: 700;
    color: #35556d;
    margin-bottom: 6px;
  }
  .create-modal-controls .form-control,
  .create-modal-controls .form-select,
  .create-modal-table .form-control {
    border: 1px solid #cad9e4;
    border-radius: 14px;
    min-height: 54px;
    font-size: 0.98rem;
    color: #173b60;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.94);
  }
  .create-modal-sheet-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #27455a;
  }
  .create-modal-sheet-title .text-muted {
    color: #74889b !important;
  }
  .create-modal-table {
    border: 1px solid #dce5ec;
    border-radius: 20px;
    overflow-x: auto;
    overflow-y: hidden;
    background: rgba(255, 255, 255, 0.88);
  }
  .create-modal-table .table {
    border-color: #dce5ec;
    margin: 0;
  }
  .create-modal-table .table th {
    position: static !important;
    top: auto !important;
    z-index: auto !important;
    background: linear-gradient(180deg, #f8fbfd 0%, #edf3f8 100%) !important;
    border-color: #dce6ee;
    color: #173b60;
    font-size: 0.82rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 14px 12px;
  }
  .create-modal-table .table td {
    border-color: #e4edf3;
    background: rgba(255, 255, 255, 0.92);
    font-size: 0.95rem;
    color: #173b60;
    padding: 14px 12px;
    vertical-align: middle;
  }
  .create-modal-table .row-index {
    font-size: 0.96rem;
    color: #244c74;
  }
  .create-modal-table .status-switch-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  .create-modal-table .attendance-status-switch {
    appearance: none;
    -webkit-appearance: none;
    width: 42px;
    height: 24px;
    border: 1px solid #95a6b8;
    border-radius: 999px;
    background: #adb8c4;
    position: relative;
    cursor: pointer;
    margin: 0;
  }
  .create-modal-table .attendance-status-switch::after {
    content: "";
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.28);
    transition: left 0.15s ease;
  }
  .create-modal-table .attendance-status-switch:checked {
    background: #7ca489;
    border-color: #688c74;
  }
  .create-modal-table .attendance-status-switch:checked::after {
    left: 20px;
  }
  .create-modal-table .attendance-status-pill {
    font-size: 14px;
    min-width: 66px;
    text-align: left;
  }
  .create-modal-actions {
    margin-top: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }
  .create-modal-add-btn {
    border: 2px solid #198754 !important;
    background: #edf8f2 !important;
    color: #198754 !important;
    border-radius: 16px;
    font-size: 0.98rem;
    font-weight: 700;
    min-height: 54px;
    padding: 8px 20px;
  }
  .create-modal-save-btn {
    background: #198754 !important;
    border: 1px solid #167a4a !important;
    color: #fff !important;
    border-radius: 16px;
    font-size: 0.98rem;
    font-weight: 700;
    min-height: 54px;
    min-width: 360px;
    padding: 8px 18px;
    box-shadow: 0 8px 18px rgba(25, 135, 84, 0.28);
  }
  .create-modal-save-btn:hover {
    background: #157347 !important;
    color: #fff !important;
  }
  @media (max-width: 991.98px) {
    .eco-attendance-module {
      padding: 18px 12px 28px;
    }
    .eco-attendance-module .card .card-header,
    .eco-attendance-module .card .card-body {
      padding-left: 16px;
      padding-right: 16px;
    }
    .eco-attendance-module .attendance-hero-body {
      padding: 16px 16px 14px;
      gap: 12px;
    }
    .eco-attendance-module .attendance-hero-actions .btn {
      flex: 1 1 calc(50% - 6px);
      min-width: 0;
    }
    .eco-attendance-module .attendance-hero-filter-shell {
      padding-top: 14px;
    }
    .eco-attendance-module .attendance-hero-title {
      font-size: 1.95rem;
    }
    .create-modal-controls .form-control,
    .create-modal-controls .form-select,
    .create-modal-table .form-control {
      min-height: 44px;
      font-size: 14px;
      padding: 8px 10px;
    }
    .create-modal-add-btn,
    .create-modal-save-btn {
      min-height: 44px;
      font-size: 14px;
      min-width: 0;
    }
    .create-modal-sheet-title {
      font-size: 18px;
    }
    .create-modal-table .table th,
    .create-modal-table .table td,
    .create-modal-table .attendance-status-pill,
    .create-modal-table .row-index {
      font-size: 13px;
    }
    .create-modal-actions {
      flex-direction: column;
      align-items: stretch;
    }
    .create-attendance-modal .modal-dialog {
      width: auto;
      margin: 0.5rem auto;
    }
    .eco-attendance-module .attendance-report-meta {
      align-items: flex-start;
      width: 100%;
    }
    .eco-attendance-module .attendance-report-period {
      text-align: left;
    }
    .eco-attendance-module .attendance-report-section .card-header {
      gap: 12px;
      align-items: flex-start !important;
      flex-direction: column;
    }
  }
  @media (max-width: 575.98px) {
    .eco-attendance-module .attendance-hero-actions .btn {
      flex-basis: 100%;
    }
    .eco-attendance-module .attendance-hero-title {
      font-size: 1.55rem;
    }
  }
  @media print {
    .no-print,
    .left-sidebar,
    .topbar,
    footer {
      display: none !important;
    }
    .page-wrapper {
      margin: 0 !important;
      padding: 0 !important;
      background: #fff !important;
    }
    .eco-attendance-module .card {
      border: 1px solid #c8c8c8 !important;
      box-shadow: none !important;
    }
  }
</style>

<div class="page-wrapper">
  <div class="page-breadcrumb no-print">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title mb-0">Eco-Police Attendance</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>">Home</a></li>
              <li class="breadcrumb-item active" aria-current="page">Attendance Module</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid eco-attendance-module">
    <div class="card fade-in mb-3 no-print attendance-hero-card">
      <div class="card-body attendance-hero-body">
        <div class="attendance-hero-copy">
          <div class="attendance-hero-eyebrow">CENRO Attendance Management System</div>
          <h4 class="attendance-hero-title">Eco-Police &amp; Sanitation Attendance Module</h4>
        </div>
        <div class="attendance-hero-actions">
          <button type="button" class="btn attendance-toolbar-btn attendance-toolbar-btn-primary" data-bs-toggle="modal" data-bs-target="#createAttendanceModal">Create Attendance</button>
          <a href="<?= eco_attendance_e($downloadUrl) ?>" class="btn attendance-toolbar-btn attendance-toolbar-btn-secondary">Download Excel</a>
          <button type="button" id="printAttendanceReport" class="btn attendance-toolbar-btn attendance-toolbar-btn-secondary">Print</button>
        </div>
        <div class="attendance-hero-filter-shell no-print">
          <div class="attendance-hero-filter-title">
            <i class="fa fa-filter" aria-hidden="true"></i>Daily Attendance Filters
          </div>
          <form method="GET" id="attendanceReportFilterForm" class="row g-3 attendance-filter-form attendance-hero-filter-form">
            <input type="hidden" name="attendance_date" value="<?= eco_attendance_e($selectedDate) ?>">
            <input type="hidden" name="attendance_category" value="<?= eco_attendance_e($selectedCategory) ?>">

            <div class="col-md-4">
              <label for="report_date" class="form-label small mb-1">Attendance Date:</label>
              <input type="date" id="report_date" name="report_date" value="<?= eco_attendance_e($reportDate) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
              <label for="report_category" class="form-label small mb-1">Category:</label>
              <select id="report_category" name="report_category" class="form-select form-select-sm">
                <option value="eco_police" <?= $reportCategory === 'eco_police' ? 'selected' : '' ?>>Eco-Police</option>
                <option value="monitoring" <?= $reportCategory === 'monitoring' ? 'selected' : '' ?>>Monitoring</option>
              </select>
            </div>
            <div class="col-md-4">
              <label for="report_search" class="form-label small mb-1">Search:</label>
              <input type="search" id="report_search" class="form-control form-control-sm" placeholder="Search employee, status, or note">
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade create-attendance-modal no-print" id="createAttendanceModal" tabindex="-1" aria-labelledby="createAttendanceModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title mb-0" id="createAttendanceModalLabel">
              <i class="fa fa-list-alt me-2"></i><span id="createAttendanceModalTitleText">Create Attendance</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php if ($statusFlag === 'saved'): ?>
              <div class="alert alert-success no-print">Attendance sheet saved successfully.</div>
            <?php elseif ($statusFlag === 'save_failed'): ?>
              <div class="alert alert-danger no-print">Unable to save attendance. Please try again.</div>
            <?php elseif ($statusFlag === 'no_rows'): ?>
              <div class="alert alert-warning no-print">Add at least one employee row before saving.</div>
            <?php elseif ($statusFlag === 'table_missing'): ?>
              <div class="alert alert-warning no-print">
                Attendance tables are missing. Run SQL file:
                <code>sql/20260224_eco_police_attendance.sql</code>
              </div>
            <?php endif; ?>

            <form
              method="GET"
              id="createAttendanceLoadForm"
              class="row g-3 align-items-end mb-3 no-print create-modal-controls"
            >
              <input type="hidden" name="open_create" value="1">
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="attendance_date">Date:</label>
                <input type="date" id="attendance_date" name="attendance_date" class="form-control" value="<?= eco_attendance_e($selectedDate) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" for="attendance_category">Category:</label>
                <select id="attendance_category" name="attendance_category" class="form-select">
                  <option value="eco_police" <?= $selectedCategory === 'eco_police' ? 'selected' : '' ?>>Eco-Police</option>
                  <option value="monitoring" <?= $selectedCategory === 'monitoring' ? 'selected' : '' ?>>Monitoring</option>
                </select>
              </div>
            </form>

            <?php if ($tablesReady): ?>
              <form method="POST" id="createAttendanceSaveForm" autocomplete="off" data-lpignore="true">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_attendance_sheet">
                <input type="hidden" name="attendance_date" value="<?= eco_attendance_e($selectedDate) ?>">
                <input type="hidden" name="attendance_category" value="<?= eco_attendance_e($selectedCategory) ?>">

                <div class="create-modal-sheet-title mb-2" id="createAttendanceSheetTitle">
                  <span id="createAttendanceSheetLabel"><?= eco_attendance_e(eco_attendance_category_label($selectedCategory)) ?> Attendance Sheet</span>
                  <span class="text-muted" id="createAttendanceSheetDate">| <?= eco_attendance_e(date('M j, Y', strtotime($selectedDate))) ?></span>
                </div>

                <div class="create-modal-table">
                  <table class="table table-bordered table-hover align-middle text-center mb-0">
                    <thead class="table-light text-nowrap">
                      <tr>
                        <th style="width:7%;">#</th>
                        <th style="width:37%;">Employee Name</th>
                        <th style="width:18%;">Status</th>
                        <th style="width:38%;">Note / Reason</th>
                      </tr>
                    </thead>
                    <tbody id="ecoAttendanceSheetBody">
                      <?php foreach ($createSheetRows as $idx => $row): ?>
                        <tr class="attendance-row">
                          <td class="text-center fw-semibold row-index"><?= (int)($idx + 1) ?></td>
                          <td>
                            <input type="hidden" name="employee_user_id[]" value="<?= (int)$row['employee_user_id'] ?>">
                            <input
                              type="text"
                              name="attendance_employee_name[]"
                              class="form-control"
                              value="<?= eco_attendance_e($row['employee_name']) ?>"
                              placeholder="Employee name"
                              autocomplete="off"
                              autocorrect="off"
                              spellcheck="false"
                              data-lpignore="true"
                              <?= !empty($row['locked']) ? 'readonly' : '' ?>
                            >
                          </td>
                          <td>
                            <input type="hidden" class="attendance-status-input" name="attendance_status[]" value="<?= eco_attendance_e($row['attendance_status']) ?>">
                            <div class="status-switch-wrap">
                              <input class="attendance-status-switch" type="checkbox" <?= ($row['attendance_status'] ?? '') === 'present' ? 'checked' : '' ?>>
                              <span class="badge attendance-status-pill <?= ($row['attendance_status'] ?? '') === 'present' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= (($row['attendance_status'] ?? '') === 'present') ? 'Present' : 'Absent' ?>
                              </span>
                            </div>
                          </td>
                          <td>
                            <input type="text" name="attendance_note_reason[]" class="form-control" value="<?= eco_attendance_e($row['note_reason']) ?>" maxlength="255" placeholder="Optional note or reason" autocomplete="off" spellcheck="false" data-lpignore="true">
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div class="create-modal-actions no-print">
                  <button type="button" id="addAttendanceEmployee" class="btn create-modal-add-btn">
                    <i class="fa fa-plus me-1"></i> Add Employee
                  </button>
                  <button type="submit" class="btn create-modal-save-btn">
                    <i class="fa fa-save me-1"></i> Save Attendance Sheet
                  </button>
                </div>
              </form>
            <?php else: ?>
              <div class="alert alert-warning mb-0">
                Attendance tables are missing. Run SQL file:
                <code>sql/20260224_eco_police_attendance.sql</code>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="attendance-report-section mb-3" id="viewAttendanceSection">
      <div class="card fade-in attendance-report-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Daily Attendance Report</h5>
          <div class="attendance-report-meta">
            <div class="attendance-report-period">
              <?= eco_attendance_e(eco_attendance_category_label($reportCategory)) ?> | <?= eco_attendance_e(date('M j, Y', strtotime($reportDate))) ?> | <?= (int)$reportSheetEntryCount ?> employee record(s)
            </div>
            <div class="attendance-report-status-row">
              <div id="attendanceReportState" class="attendance-report-state <?= $reportSheetExists ? 'available' : 'empty' ?>">
                <i class="fa <?= $reportSheetExists ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" aria-hidden="true"></i>
                <?= $reportSheetExists ? 'Attendance sheet available' : 'No attendance record for this day' ?>
              </div>
              <form method="POST" id="attendanceBulkDeleteForm" class="attendance-bulk-delete-form no-print">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete_attendance_rows">
                <input type="hidden" name="attendance_date" value="<?= eco_attendance_e($selectedDate) ?>">
                <input type="hidden" name="attendance_category" value="<?= eco_attendance_e($selectedCategory) ?>">
                <input type="hidden" name="report_date" value="<?= eco_attendance_e($reportDate) ?>">
                <input type="hidden" name="report_category" value="<?= eco_attendance_e($reportCategory) ?>">
                <div id="attendanceBulkDeleteTargets"></div>
                <button type="submit" class="btn attendance-bulk-delete-btn">
                  Delete Selected (<span id="attendanceBulkDeleteCount">0</span>)
                </button>
              </form>
            </div>
          </div>
        </div>
        <div class="card-body">
          <?php if ($reportStatusFlag === 'deleted'): ?>
            <div class="alert alert-success no-print attendance-report-feedback">Selected attendance row(s) deleted successfully.</div>
          <?php elseif ($reportStatusFlag === 'delete_failed'): ?>
            <div class="alert alert-danger no-print attendance-report-feedback">Unable to delete the selected attendance row(s).</div>
          <?php endif; ?>
          <?php if (empty($reportRows)): ?>
            <div class="attendance-report-empty">
              <div class="attendance-report-empty-icon">
                <i class="fa fa-calendar-times-o" aria-hidden="true"></i>
              </div>
              <h6>No daily attendance data yet</h6>
              <p>Create an attendance sheet for this date and category, or switch filters to view an existing record.</p>
            </div>
          <?php else: ?>
            <div class="scrollable-table-wrapper">
              <table class="table table-bordered table-hover table-sm align-middle mb-0">
                <thead class="table-light text-nowrap">
                  <tr>
                    <th class="text-center no-print attendance-row-select-cell">
                      <div class="attendance-row-select-wrap">
                        <input type="checkbox" id="attendanceReportSelectAll" class="attendance-select-all" aria-label="Select all attendance rows">
                      </div>
                    </th>
                    <th style="width: 80px;" class="text-center">#</th>
                    <th>Employee Name</th>
                    <th class="text-center" style="width: 160px;">Status</th>
                    <th>Note / Reason</th>
                    <th class="text-center no-print" style="width: 110px;">Action</th>
                  </tr>
                </thead>
                <tbody id="dailyAttendanceReportBody">
                  <?php foreach ($reportRows as $idx => $row): ?>
                    <tr class="daily-attendance-report-row" data-search-text="<?= eco_attendance_e(strtolower(trim(($row['employee_name'] ?? '') . ' ' . (($row['attendance_status'] ?? '') === 'present' ? 'present' : 'absent') . ' ' . (($row['note_reason'] ?? '') !== '' ? $row['note_reason'] : '-')))) ?>">
                      <td class="text-center no-print attendance-row-select-cell">
                        <div class="attendance-row-select-wrap">
                          <input
                            type="checkbox"
                            class="attendance-row-select"
                            data-employee-user-id="<?= (int)($row['employee_user_id'] ?? 0) ?>"
                            data-employee-name="<?= eco_attendance_e($row['employee_name']) ?>"
                            aria-label="Select <?= eco_attendance_e($row['employee_name']) ?>"
                          >
                        </div>
                      </td>
                      <td class="text-center daily-attendance-report-index"><?= (int)($idx + 1) ?></td>
                      <td><?= eco_attendance_e($row['employee_name']) ?></td>
                      <td class="text-center">
                        <span class="badge <?= ($row['attendance_status'] ?? '') === 'present' ? 'bg-success' : 'bg-secondary' ?>">
                          <?= (($row['attendance_status'] ?? '') === 'present') ? 'Present' : 'Absent' ?>
                        </span>
                      </td>
                      <td><?= eco_attendance_e($row['note_reason'] !== '' ? $row['note_reason'] : '-') ?></td>
                      <td class="text-center no-print">
                        <div class="attendance-row-actions">
                          <button
                            type="button"
                            class="btn attendance-row-action-btn attendance-edit-row-btn"
                            data-report-date="<?= eco_attendance_e($reportDate) ?>"
                            data-report-category="<?= eco_attendance_e($reportCategory) ?>"
                            data-employee-user-id="<?= (int)($row['employee_user_id'] ?? 0) ?>"
                            data-employee-name="<?= eco_attendance_e($row['employee_name']) ?>"
                            data-attendance-status="<?= eco_attendance_e($row['attendance_status'] ?? 'absent') ?>"
                            data-note-reason="<?= eco_attendance_e($row['note_reason'] ?? '') ?>"
                          >Edit</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="dailyAttendanceSearchEmptyRow" class="attendance-search-empty">
                    <td colspan="6" class="text-center text-muted py-4">No matching attendance record found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('ecoAttendanceSheetBody');
    const addRowButton = document.getElementById('addAttendanceEmployee');
    const createModalEl = document.getElementById('createAttendanceModal');
    const createLoadForm = document.getElementById('createAttendanceLoadForm');
    const createSaveForm = document.getElementById('createAttendanceSaveForm');
    const createModalTitleText = document.getElementById('createAttendanceModalTitleText');
    const createSheetLabel = document.getElementById('createAttendanceSheetLabel');
    const createSheetDate = document.getElementById('createAttendanceSheetDate');
    const createModalBody = createModalEl ? createModalEl.querySelector('.modal-body') : null;
    const reportFilterForm = document.getElementById('attendanceReportFilterForm');
    const attendanceDateInput = document.getElementById('attendance_date');
    const attendanceCategorySelect = document.getElementById('attendance_category');
    const reportDateInput = document.getElementById('report_date');
    const reportCategorySelect = document.getElementById('report_category');
    const reportSearchInput = document.getElementById('report_search');
    const reportTableBody = document.getElementById('dailyAttendanceReportBody');
    const reportSearchEmptyRow = document.getElementById('dailyAttendanceSearchEmptyRow');
    const reportSelectAllCheckbox = document.getElementById('attendanceReportSelectAll');
    const attendanceReportState = document.getElementById('attendanceReportState');
    const attendanceBulkDeleteForm = document.getElementById('attendanceBulkDeleteForm');
    const attendanceBulkDeleteTargets = document.getElementById('attendanceBulkDeleteTargets');
    const attendanceBulkDeleteCount = document.getElementById('attendanceBulkDeleteCount');
    const createSaveDateInput = createSaveForm ? createSaveForm.querySelector('input[name="attendance_date"]') : null;
    const createSaveCategoryInput = createSaveForm ? createSaveForm.querySelector('input[name="attendance_category"]') : null;
    const shouldFocusCreateSection = <?= $focusCreateSection ? 'true' : 'false' ?>;
    const initialTableBodyMarkup = tableBody ? tableBody.innerHTML : '';
    const initialCreateModalTitle = createModalTitleText ? createModalTitleText.textContent : '';
    const initialCreateSheetLabel = createSheetLabel ? createSheetLabel.textContent : '';
    const initialCreateSheetDate = createSheetDate ? createSheetDate.textContent : '';

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, function (char) {
        switch (char) {
          case '&':
            return '&amp;';
          case '<':
            return '&lt;';
          case '>':
            return '&gt;';
          case '"':
            return '&quot;';
          case "'":
            return '&#39;';
          default:
            return char;
        }
      });
    }

    function getCategoryLabel(category) {
      return category === 'monitoring' ? 'Monitoring' : 'Eco-Police';
    }

    function formatAttendanceDate(dateValue) {
      if (!dateValue) {
        return '';
      }

      const parsed = new Date(dateValue + 'T00:00:00');
      if (Number.isNaN(parsed.getTime())) {
        return dateValue;
      }

      return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    }

    function updateCreateSheetHeading(category, dateValue) {
      if (createSheetLabel) {
        createSheetLabel.textContent = getCategoryLabel(category) + ' Attendance Sheet';
      }
      if (createSheetDate) {
        createSheetDate.textContent = '| ' + formatAttendanceDate(dateValue);
      }
    }

    function buildAttendanceRowMarkup(rowData) {
      const employeeUserId = Number(rowData.employeeUserId || 0);
      const employeeName = String(rowData.employeeName || '');
      const attendanceStatus = String(rowData.attendanceStatus || 'absent') === 'present' ? 'present' : 'absent';
      const noteReason = String(rowData.noteReason || '');
      const isLocked = employeeUserId > 0;

      return `
        <tr class="attendance-row">
          <td class="text-center fw-semibold row-index">1</td>
          <td>
            <input type="hidden" name="employee_user_id[]" value="${employeeUserId}">
            <input
              type="text"
              name="attendance_employee_name[]"
              class="form-control"
              value="${escapeHtml(employeeName)}"
              placeholder="Employee name"
              autocomplete="off"
              autocorrect="off"
              spellcheck="false"
              data-lpignore="true"
              ${isLocked ? 'readonly' : ''}
            >
          </td>
          <td>
            <input type="hidden" class="attendance-status-input" name="attendance_status[]" value="${attendanceStatus}">
            <div class="status-switch-wrap">
              <input class="attendance-status-switch" type="checkbox" ${attendanceStatus === 'present' ? 'checked' : ''}>
              <span class="badge attendance-status-pill ${attendanceStatus === 'present' ? 'bg-success' : 'bg-secondary'}">
                ${attendanceStatus === 'present' ? 'Present' : 'Absent'}
              </span>
            </div>
          </td>
          <td>
            <input
              type="text"
              name="attendance_note_reason[]"
              class="form-control"
              value="${escapeHtml(noteReason)}"
              maxlength="255"
              placeholder="Optional note or reason"
              autocomplete="off"
              spellcheck="false"
              data-lpignore="true"
            >
          </td>
        </tr>
      `;
    }

    function cleanupTransientUrlParams() {
      if (!window.history || typeof window.history.replaceState !== 'function') return;

      const currentUrl = new URL(window.location.href);
      let changed = false;
      ['status', 'open_create', 'report_status'].forEach(function (key) {
        if (currentUrl.searchParams.has(key)) {
          currentUrl.searchParams.delete(key);
          changed = true;
        }
      });

      if (!changed) return;

      const nextUrl = currentUrl.pathname
        + (currentUrl.searchParams.toString() ? '?' + currentUrl.searchParams.toString() : '')
        + currentUrl.hash;

      window.history.replaceState({}, document.title, nextUrl);
    }

    function submitCreateLoadForm() {
      if (!createLoadForm) return;
      if (typeof createLoadForm.requestSubmit === 'function') {
        createLoadForm.requestSubmit();
        return;
      }
      createLoadForm.submit();
    }

    function submitReportFilterForm() {
      if (!reportFilterForm) return;
      if (typeof reportFilterForm.requestSubmit === 'function') {
        reportFilterForm.requestSubmit();
        return;
      }
      reportFilterForm.submit();
    }

    function updateRowIndexes() {
      if (!tableBody) return;
      const rows = tableBody.querySelectorAll('.attendance-row');
      rows.forEach(function (row, idx) {
        const indexCell = row.querySelector('.row-index');
        if (indexCell) {
          indexCell.textContent = String(idx + 1);
        }
      });
    }

    function filterReportRows() {
      if (!reportTableBody || !reportSearchInput) return;

      const query = reportSearchInput.value.trim().toLowerCase();
      const rows = Array.from(reportTableBody.querySelectorAll('.daily-attendance-report-row'));
      if (rows.length === 0) return;

      let visibleCount = 0;
      rows.forEach(function (row) {
        const haystack = String(row.getAttribute('data-search-text') || '').toLowerCase();
        const matched = query === '' || haystack.indexOf(query) !== -1;
        row.style.display = matched ? '' : 'none';
        if (matched) {
          visibleCount += 1;
          const indexCell = row.querySelector('.daily-attendance-report-index');
          if (indexCell) {
            indexCell.textContent = String(visibleCount);
          }
        }
      });

      if (reportSearchEmptyRow) {
        reportSearchEmptyRow.style.display = visibleCount === 0 ? '' : 'none';
      }

      syncBulkDeleteSelectionState();
    }

    function syncStatusRow(row) {
      if (!row) return;
      const statusInput = row.querySelector('.attendance-status-input');
      const statusSwitch = row.querySelector('.attendance-status-switch');
      const statusPill = row.querySelector('.attendance-status-pill');
      if (!statusInput || !statusSwitch || !statusPill) return;

      const nextStatus = statusSwitch.checked ? 'present' : 'absent';
      statusInput.value = nextStatus;
      statusPill.textContent = nextStatus.charAt(0).toUpperCase() + nextStatus.slice(1);
      statusPill.classList.toggle('bg-success', nextStatus === 'present');
      statusPill.classList.toggle('bg-secondary', nextStatus !== 'present');
    }

    function restoreCreateModalState() {
      if (tableBody) {
        tableBody.innerHTML = initialTableBodyMarkup;
      }
      if (createLoadForm) {
        createLoadForm.reset();
      }
      if (createSaveForm) {
        createSaveForm.reset();
      }
      if (createModalTitleText) {
        createModalTitleText.textContent = initialCreateModalTitle;
      }
      if (createSheetLabel) {
        createSheetLabel.textContent = initialCreateSheetLabel;
      }
      if (createSheetDate) {
        createSheetDate.textContent = initialCreateSheetDate;
      }
      if (createModalBody) {
        createModalBody.scrollTop = 0;
      }
      if (tableBody) {
        tableBody.querySelectorAll('.attendance-row').forEach(function (row) {
          syncStatusRow(row);
        });
      }
      updateRowIndexes();
    }

    function getReportRowCheckboxes() {
      if (!reportTableBody) {
        return [];
      }

      return Array.from(reportTableBody.querySelectorAll('.attendance-row-select'));
    }

    function syncBulkDeleteSelectionState() {
      const checkboxes = getReportRowCheckboxes();
      const selected = checkboxes.filter(function (checkbox) {
        return checkbox.checked;
      });

      if (attendanceBulkDeleteCount) {
        attendanceBulkDeleteCount.textContent = String(selected.length);
      }

      if (attendanceBulkDeleteTargets) {
        attendanceBulkDeleteTargets.innerHTML = selected.map(function (checkbox) {
          const employeeUserId = String(checkbox.getAttribute('data-employee-user-id') || '0');
          const employeeName = escapeHtml(String(checkbox.getAttribute('data-employee-name') || ''));
          return ''
            + '<input type="hidden" name="delete_employee_user_id[]" value="' + employeeUserId + '">'
            + '<input type="hidden" name="delete_employee_name[]" value="' + employeeName + '">';
        }).join('');
      }

      if (attendanceBulkDeleteForm) {
        attendanceBulkDeleteForm.classList.toggle('is-visible', selected.length > 0);
      }
      if (attendanceReportState) {
        attendanceReportState.hidden = selected.length > 0;
      }

      if (reportSelectAllCheckbox) {
        const total = checkboxes.length;
        reportSelectAllCheckbox.checked = total > 0 && selected.length === total;
        reportSelectAllCheckbox.indeterminate = selected.length > 0 && selected.length < total;
      }
    }

    function openEditAttendanceModal(button) {
      if (!button || !tableBody || !createSaveForm) {
        return;
      }

      restoreCreateModalState();

      const editDate = String(button.getAttribute('data-report-date') || '');
      const editCategory = String(button.getAttribute('data-report-category') || 'eco_police');
      const editEmployeeUserId = Number(button.getAttribute('data-employee-user-id') || 0);
      const editEmployeeName = String(button.getAttribute('data-employee-name') || '');
      const editAttendanceStatus = String(button.getAttribute('data-attendance-status') || 'absent');
      const editNoteReason = String(button.getAttribute('data-note-reason') || '');

      if (attendanceDateInput) {
        attendanceDateInput.value = editDate;
      }
      if (attendanceCategorySelect) {
        attendanceCategorySelect.value = editCategory;
      }
      if (createSaveDateInput) {
        createSaveDateInput.value = editDate;
      }
      if (createSaveCategoryInput) {
        createSaveCategoryInput.value = editCategory;
      }
      if (createModalTitleText) {
        createModalTitleText.textContent = 'Edit Attendance';
      }

      updateCreateSheetHeading(editCategory, editDate);
      tableBody.innerHTML = buildAttendanceRowMarkup({
        employeeUserId: editEmployeeUserId,
        employeeName: editEmployeeName,
        attendanceStatus: editAttendanceStatus,
        noteReason: editNoteReason
      });

      const editableRow = tableBody.querySelector('.attendance-row');
      if (editableRow) {
        syncStatusRow(editableRow);
      }
      updateRowIndexes();

      if (createModalBody) {
        createModalBody.scrollTop = 0;
      }

      if (createModalEl && window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(createModalEl);
        modal.show();

        window.setTimeout(function () {
          const nameInput = tableBody.querySelector('input[name="attendance_employee_name[]"]');
          if (nameInput && !nameInput.readOnly) {
            nameInput.focus();
          }
        }, 120);
      }
    }

    if (tableBody) {
      tableBody.querySelectorAll('.attendance-row').forEach(function (row) {
        syncStatusRow(row);
      });

      tableBody.addEventListener('change', function (event) {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.classList.contains('attendance-status-switch')) {
          syncStatusRow(target.closest('.attendance-row'));
        }
      });
    }

    if (tableBody && addRowButton) {
      addRowButton.addEventListener('click', function () {
        const tr = document.createElement('tr');
        tr.className = 'attendance-row';
        tr.innerHTML = `
          <td class="text-center fw-semibold row-index"></td>
          <td>
            <input type="hidden" name="employee_user_id[]" value="0">
            <input type="text" name="attendance_employee_name[]" class="form-control" placeholder="Employee name" autocomplete="off" autocorrect="off" spellcheck="false" data-lpignore="true">
          </td>
          <td>
            <input type="hidden" class="attendance-status-input" name="attendance_status[]" value="absent">
            <div class="status-switch-wrap">
              <input class="attendance-status-switch" type="checkbox">
              <span class="badge attendance-status-pill bg-secondary">Absent</span>
            </div>
          </td>
          <td>
            <input type="text" name="attendance_note_reason[]" class="form-control" maxlength="255" placeholder="Optional note or reason" autocomplete="off" spellcheck="false" data-lpignore="true">
          </td>
        `;

        tableBody.appendChild(tr);
        updateRowIndexes();
        syncStatusRow(tr);
        const input = tr.querySelector('input[name="attendance_employee_name[]"]');
        if (input) input.focus();
      });
    }

    updateRowIndexes();

    [attendanceDateInput, attendanceCategorySelect].forEach(function (field) {
      if (!field || !createLoadForm) return;
      field.addEventListener('change', submitCreateLoadForm);
    });

    [reportDateInput, reportCategorySelect].forEach(function (field) {
      if (!field || !reportFilterForm) return;
      field.addEventListener('change', submitReportFilterForm);
    });

    if (reportSearchInput) {
      reportSearchInput.addEventListener('input', filterReportRows);
      filterReportRows();
    }

    if (reportTableBody) {
      reportTableBody.addEventListener('change', function (event) {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.classList.contains('attendance-row-select')) {
          syncBulkDeleteSelectionState();
        }
      });

      reportTableBody.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }

        const editButton = target.closest('.attendance-edit-row-btn');
        if (editButton instanceof HTMLElement) {
          openEditAttendanceModal(editButton);
        }
      });
    }

    if (reportSelectAllCheckbox) {
      reportSelectAllCheckbox.addEventListener('change', function () {
        getReportRowCheckboxes().forEach(function (checkbox) {
          checkbox.checked = reportSelectAllCheckbox.checked;
        });
        syncBulkDeleteSelectionState();
      });
    }

    if (attendanceBulkDeleteForm) {
      attendanceBulkDeleteForm.addEventListener('submit', function (event) {
        const selectedCount = attendanceBulkDeleteCount ? Number(attendanceBulkDeleteCount.textContent || '0') : 0;
        if (selectedCount <= 0) {
          event.preventDefault();
          return;
        }

        if (!window.confirm('Delete ' + selectedCount + ' selected attendance row(s)?')) {
          event.preventDefault();
        }
      });
    }

    if (createModalEl && window.bootstrap && bootstrap.Modal) {
      createModalEl.addEventListener('show.bs.modal', function () {
        document.documentElement.classList.add('create-attendance-modal-active');
        document.body.classList.add('create-attendance-modal-active');
      });
      createModalEl.addEventListener('shown.bs.modal', function () {
        if (createModalBody) {
          createModalBody.scrollTop = 0;
        }
      });
      createModalEl.addEventListener('hidden.bs.modal', function () {
        document.documentElement.classList.remove('create-attendance-modal-active');
        document.body.classList.remove('create-attendance-modal-active');
        restoreCreateModalState();
      });
    }

    if (shouldFocusCreateSection) {
      if (createModalEl && window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(createModalEl).show();
      }
    }

    cleanupTransientUrlParams();
    syncBulkDeleteSelectionState();

    document.querySelectorAll('[data-scroll-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const selector = btn.getAttribute('data-scroll-target');
        if (!selector) return;
        const target = document.querySelector(selector);
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    const printBtn = document.getElementById('printAttendanceReport');
    if (printBtn) {
      printBtn.addEventListener('click', function () {
        window.print();
      });
    }
  });
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
</body>
</html>

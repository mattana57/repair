<?php
// ใช้ผ่าน technician_home.php หลังตรวจสิทธิ์แล้วเท่านั้น
if (!defined('MBS_TECH_HISTORY')) {
    http_response_code(403);
    exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}

$thEscape = static function ($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$thGet = static function ($key, $default) {
    return isset($_GET[$key]) && is_string($_GET[$key])
        ? trim($_GET[$key])
        : $default;
};

// โหมดประวัติรายผู้แจ้งเปิดได้ผ่านหน้าเจ้าหน้าที่ที่ผ่าน Guard แล้วเท่านั้น
$thReporterMode = defined('MBS_TECH_REPORTER_HISTORY') && MBS_TECH_REPORTER_HISTORY;
$thReporterLineId = $thReporterMode ? $thGet('th_line_user_id', '') : '';

$thToday = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$thDay = $thGet('hd', $thToday->format('j'));
if ($thDay !== 'all') {
    $thDay = ctype_digit($thDay) && (int)$thDay >= 1 && (int)$thDay <= 31
        ? (string)(int)$thDay : $thToday->format('j');
}
$thMonth = $thGet('hm', $thToday->format('m'));
$thYear = $thGet('hy', $thToday->format('Y'));
$thStatus = $thGet('hs', 'all');
$thSearch = $thGet('hq', '');

if ($thMonth !== 'all' && !preg_match('/\A(?:0[1-9]|1[0-2])\z/', $thMonth)) {
    $thMonth = $thToday->format('m');
}

if ($thYear !== 'all' && !preg_match('/\A[1-9][0-9]{3}\z/', $thYear)) {
    $thYear = $thToday->format('Y');
}

$thStatuses = [
    'all' => 'ทั้งหมด',
    'รอรับเรื่อง' => 'รอรับเรื่อง',
    'กำลังดำเนินการ' => 'กำลังดำเนินการ',
    'ซ่อมเสร็จแล้ว' => 'ซ่อมเสร็จแล้ว',
];

if (!isset($thStatuses[$thStatus])) {
    $thStatus = 'all';
}

if (!preg_match('/\A.{0,200}\z/us', $thSearch)) {
    $thSearch = '';
}

$thPage = filter_var(
    $thGet('hp', '1'),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
) ?: 1;

$thTotal = 0;
$thPages = 1;
$thRows = [];
$thOnHistory = $thGet('tab', '') === 'history';

// ใช้เงื่อนไขเจ้าของจากหน้าเดิมที่ตรวจสิทธิ์แล้ว
// ไม่รับรหัสเจ้าหน้าที่จาก GET
if ($thOnHistory) {
    // หน้าประวัติหลักมีคิวรอร่วมกัน แต่ป๊อปอัปผู้แจ้งจำกัดเฉพาะงานของตน
    $thWhere = $thReporterMode
        ? '(' . $where . ')'
        : "(TRIM(status) = 'รอรับเรื่อง' OR (" . $where . '))';
    $thTypes = '';
    $thParams = [];

    if ($thReporterMode) {
        // จำกัดผู้แจ้งด้วย LINE ID และคงเงื่อนไขเจ้าของงานจากหน้าเดิม
        $thWhere .= ' AND BINARY line_user_id = BINARY ?';
        $thTypes .= 's';
        $thParams[] = $thReporterLineId;
    }

    if ($thDay !== 'all') {
        $thWhere .= ' AND DAY(created_at) = ?';
        $thTypes .= 'i';
        $thParams[] = (int) $thDay;
    }

    if ($thMonth !== 'all') {
        $thWhere .= ' AND MONTH(created_at) = ?';
        $thTypes .= 'i';
        $thParams[] = (int) $thMonth;
    }

    if ($thYear !== 'all') {
        $thWhere .= ' AND YEAR(created_at) = ?';
        $thTypes .= 'i';
        $thParams[] = (int) $thYear;
    }

    if ($thStatus !== 'all') {
        $thWhere .= ' AND TRIM(status) = ?';
        $thTypes .= 's';
        $thParams[] = $thStatus;
    }

    if ($thSearch !== '') {
        // ค้นหาฟิลด์ใบงานเหมือนตารางเดิม
        // ชื่อคอลัมน์อ่านจากฐานข้อมูล ไม่รับจากผู้ใช้
        $thColumns = [];
        $thSchema = $conn->query('SHOW COLUMNS FROM repairs');

        if (!$thSchema) {
            throw new RuntimeException(
                'ไม่สามารถอ่านโครงสร้างรายการงานได้'
            );
        }

        while ($thColumn = $thSchema->fetch_assoc()) {
            $columnName = str_replace(
                '`',
                '``',
                $thColumn['Field']
            );

            $thColumns[] = 'CAST(`' . $columnName . '` AS CHAR)';
        }

        // ข้อมูลเจ้าหน้าที่ที่นำมาแสดงจากตาราง technicians
        $thStaffInfo = $tech_info_map[$full_name] ?? [];

        $thStaffText = implode(' ', [
            (string) $full_name,
            (string) ($thStaffInfo['eng'] ?? ''),
            (string) ($thStaffInfo['pos'] ?? ''),
            (string) ($tech_dept_map[$full_name] ?? ''),
        ]);

        // ค้นหาฟิลด์ใบงานทั้งหมด พร้อมข้อมูลเจ้าหน้าที่ที่แสดงในตาราง
        $thWhere .=
            " AND (LOCATE(LOWER(?), LOWER(CONCAT_WS(' ', "
            . implode(', ', $thColumns)
            . '))) > 0'
            . ' OR ((' . $where . ') AND LOCATE(LOWER(?), LOWER(?)) > 0)';

        $thTypes .= 'sss';
        $thParams[] = $thSearch;
        $thParams[] = $thSearch;
        $thParams[] = $thStaffText;

        // ค้นหาชื่อจริงผู้แจ้งที่แสดงจาก line_users ได้ด้วย
        if (!empty($line_users_map)) {
            $thWhere .=
                ' OR EXISTS (
                    SELECT 1
                    FROM line_users AS th_reporter
                    WHERE
                        CONVERT(th_reporter.line_display_name USING utf8mb4)
                            COLLATE utf8mb4_unicode_ci
                        =
                        CONVERT(repairs.reporter_name USING utf8mb4)
                            COLLATE utf8mb4_unicode_ci
                    AND LOCATE(
                        LOWER(?),
                        LOWER(COALESCE(th_reporter.real_name, \'\'))
                    ) > 0
                )';

            $thTypes .= 's';
            $thParams[] = $thSearch;
        }

        $thWhere .= ')';
    }

    $thRun = static function ($sql, $types, $params) use ($conn) {
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'ไม่สามารถเตรียมรายการประวัติงานได้'
            );
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'ไม่สามารถอ่านประวัติงานได้'
            );
        }

        $result = $stmt->get_result();
        $stmt->close();

        return $result;
    };

    $thCount = $thRun(
        'SELECT COUNT(*) AS total FROM repairs WHERE ' . $thWhere,
        $thTypes,
        $thParams
    );

    $thTotal = (int) $thCount->fetch_assoc()['total'];
    $thPages = max(1, (int) ceil($thTotal / 50));
    $thPage = min($thPage, $thPages);

    $thResult = $thRun(
        'SELECT repairs.*, CASE WHEN (' . $where . ') THEN 1 ELSE 0 END AS th_can_edit FROM repairs WHERE ' . $thWhere
        . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
        $thTypes . 'ii',
        array_merge(
            $thParams,
            [50, ($thPage - 1) * 50]
        )
    );

    while ($thRow = $thResult->fetch_assoc()) {
        $thRows[] = $thRow;
    }
}

$thUrl = static function ($page, array $changes = []) use (
    $thDay,
    $thMonth,
    $thYear,
    $thStatus,
    $thSearch,
    $thReporterMode,
    $thReporterLineId
) {
    $query = array_merge(
        [
            'tab' => 'history',
            'hd' => $thDay,
            'hm' => $thMonth,
            'hy' => $thYear,
            'hs' => $thStatus,
            'hq' => $thSearch,
            'hp' => $page,
        ],
        $changes
    );

    if ($thReporterMode) {
        $query['th_line_user_id'] = $thReporterLineId;
        $query['th_fragment'] = '1';
    }

    return 'technician_home.php?' . http_build_query(
        $query,
        '',
        '&',
        PHP_QUERY_RFC3986
    ) . '#tab-content-history';
};

$thDate = static function ($value, $complete = false) use ($thEscape) {
    if (!$value
        || $value === '-'
        || strpos($value, '0000-00-00') === 0) {
        return '<span class="text-rose-500">-</span>';
    }

    $parts = explode(' ', (string) $value, 2);
    $color = $complete ? 'text-emerald-700' : 'text-slate-700';

    return '<div class="th-date ' . $color . '">'
        . $thEscape((date('d/m/', strtotime($parts[0])) . ((int)date('Y', strtotime($parts[0])) + 543)))
        . '</div>'
        . (
            !empty($parts[1])
            ? '<div class="text-blue-600 font-bold">'
                . $thEscape(substr($parts[1], 0, 5))
                . '</div>'
            : ''
        );
};

$thYears = array_unique(
    array_merge($available_years, [(int) date('Y')])
);

if ($thYear !== 'all') {
    $thYears[] = (int) $thYear;
}

$thYears = array_unique($thYears);
rsort($thYears);
?>

<style>
/* ปรับเฉพาะตารางประวัติงาน */
.tech-dashboard-ui #repairsMainCard #historyTableFull {
    table-layout: fixed !important;
    min-width: 0 !important;
    width: 100% !important;
}

.tech-dashboard-ui #repairsMainCard #historyTableFull th {
    padding: 14px 5px !important;
    font-size: 9.5px !important;
    white-space: nowrap !important;
    overflow-wrap: normal;
    word-break: normal !important;
}

.tech-dashboard-ui #repairsMainCard #historyTableFull td {
    padding: 14px 6px !important;
    font-size: 12px !important;
    line-height: 1.6;
    vertical-align: top;
}

.tech-dashboard-ui #repairsMainCard #historyTableFull td:first-child,
.tech-dashboard-ui #repairsMainCard #historyTableFull th:first-child {
    padding-left: 10px !important;
}

/* รวมความกว้างทั้งหมด 100% */
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(1) {
    width: 4%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(2) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(3) {
    width: 6%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(4) {
    width: 12%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(5) {
    width: 11%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(6) {
    width: 13%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(7) {
    width: 11%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(8) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(9) {
    width: 7%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(10) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(11) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(12) {
    width: 4%;
}

.tech-dashboard-ui #repairsMainCard #historyTableFull .th-date,
.tech-dashboard-ui #repairsMainCard #historyTableFull .th-status {
    white-space: nowrap !important;
    overflow-wrap: normal;
}

.tech-dashboard-ui #repairsMainCard #historyTableFull .th-status {
    display: inline-block;
    padding: 3px 8px;
    font-size: 10px;
}

.th-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.th-filters input,
.th-filters select {
    min-width: 0;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
    padding: 10px 12px;
    font: inherit;
    font-size: 13px;
}

.th-filters input[type="search"] {
    flex: 1 1 220px;
}

.th-filters button,
.th-clear {
    padding: 10px 15px;
    border-radius: 12px;
    font-size: 13px;
}

.th-filters button {
    background: #4f46e5;
    color: white;
    font-weight: 700;
}

.th-clear {
    border: 1px solid #e2e8f0;
    color: #64748b;
}

.th-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    justify-content: space-between;
    align-items: center;
    padding: 18px 22px;
    background: #f3f5ff;
    border-top: 1px solid #e2e8f0;
    font-size: 12px;
    color: #64748b;
}

.th-pages {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.th-pages a,
.th-pages span {
    padding: 9px 12px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: white;
}

.th-pages [aria-current="page"] {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
}

.th-pages [aria-disabled="true"] {
    opacity: .45;
}

.tech-dashboard-ui #repairsMainCard.is-fullscreen .history-table-scroll {
    flex: 1;
    max-height: none;
}

@media (max-width: 1199px) {
    .tech-dashboard-ui #repairsMainCard #historyTableFull td {
        padding: 12px 0 !important;
    }

    .tech-dashboard-ui #repairsMainCard #historyTableFull td:first-child {
        padding-left: 0 !important;
    }

    .tech-dashboard-ui #repairsMainCard #historyTableFull .th-status {
        white-space: normal !important;
    }
}
/* ช่องค้นหาและตัวกรอง: ขนาดตามฝั่งแอดมิน */
.th-filter-row {
    position: relative;
    z-index: 40;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.th-search {
    position: relative;
    flex: 1;
    min-width: 120px;
}

.th-search input {
    width: 100%;
    height: 42px;
    padding: 10px 95px 10px 40px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    color: #334155;
    font: 500 14px 'Sarabun', sans-serif;
    box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
    outline: none;
}

.th-search input:focus {
    border-color: #a5b4fc;
    box-shadow: 0 0 0 2px #e0e7ff;
}

.th-search input::placeholder {
    color: #94a3b8;
}

.th-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
    pointer-events: none;
}

.th-search-clear {
    position: absolute;
    right: 0;
    top: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 100%;
    padding: 0 14px;
    border-left: 1px solid #e2e8f0;
    border-radius: 0 12px 12px 0;
    color: #94a3b8;
    font: 500 13px 'Sarabun', sans-serif;
}

.th-search-clear:hover {
    background: #fff1f2;
    color: #f43f5e;
}

.th-filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.th-dropdown {
    position: relative;
    width: 110px;
    font-family: 'Sarabun', sans-serif;
}

.th-status-dropdown {
    width: 140px;
}

.th-dropdown summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    height: 42px;
    padding: 10px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    color: #334155;
    font-size: 14px;
    font-weight: 700;
    list-style: none;
    cursor: pointer;
    box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
}

.th-dropdown summary::-webkit-details-marker {
    display: none;
}

.th-dropdown summary > span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.th-dropdown summary > i {
    color: #94a3b8;
    font-size: 10px;
}

.th-dropdown[open] summary,
.th-dropdown summary:focus-visible {
    outline: 2px solid #818cf8;
    outline-offset: 1px;
}

.th-dropdown-menu {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    z-index: 50;
    width: 100%;
    max-height: 192px;
    overflow-y: auto;
    padding: 8px 0;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    background: #fff;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    box-shadow:
        0 20px 25px -5px rgb(0 0 0 / 10%),
        0 8px 10px -6px rgb(0 0 0 / 10%);
}

.th-dropdown-menu::-webkit-scrollbar {
    width: 5px;
}

.th-dropdown-menu::-webkit-scrollbar-thumb {
    border-radius: 10px;
    background: #cbd5e1;
}

.th-dropdown:not([open]) > .th-dropdown-menu {
    display: none;
}

.th-dropdown-menu a {
    display: block;
    padding: 6px 12px;
    margin: 0 8px 2px;
    border-radius: 12px;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.th-dropdown-menu a:hover,
.th-dropdown-menu a:focus-visible {
    background: #f1f5f9;
    color: #4f46e5;
}

.th-dropdown-menu a.th-all-period,
.th-dropdown-menu a.th-all-period:hover,
.th-dropdown-menu a.th-all-period:focus-visible {
    background: #eef2ff;
    color: #4f46e5;
}

.th-status-menu {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 155px;
    padding: 8px;
}

.th-status-menu a {
    margin: 0;
    padding: 6px 10px;
    border-radius: 999px;
    text-align: center;
}

.th-status-menu a:hover {
    filter: brightness(.97);
}

/* หัวตาราง: สีและตัวพิมพ์แบบแอดมิน */
.tech-dashboard-ui #repairsMainCard #historyTableFull thead {
    background: #fef9c3;
    color: #854d0e;
    border-bottom: 1px solid #fef08a;
}

.tech-dashboard-ui #repairsMainCard #historyTableFull th {
    text-transform: uppercase;
    font-weight: 700;
}

/* แถบแบ่งหน้า: ใช้ค่ารูปแบบเดียวกับฝั่งแอดมิน */
.th-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 18px 24px;
    border-top: 1px solid #e2e8f0;
    background: linear-gradient(110deg, #f8fafc, #eef2ff);
    flex-shrink: 0;
    font-family: 'Sarabun', sans-serif;
}

.th-summary {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.th-summary strong {
    color: #4338ca;
    font-weight: 700;
}

.th-pages {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.th-pages a,
.th-pages span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0 12px;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
    background: white;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.th-pages a:hover {
    border-color: #a5b4fc;
    background: #eef2ff;
    color: #4338ca;
}

.th-pages a:focus-visible {
    outline: 3px solid #a5b4fc;
    outline-offset: 2px;
}

.th-pages [aria-current="page"] {
    background: #4f46e5;
    color: #fff;
    border-color: #4f46e5;
    box-shadow: 0 3px 9px #c7d2fe;
}

.th-pages [aria-disabled="true"] {
    opacity: 1;
    color: #94a3b8;
    background: #f1f5f9;
}

.th-pages .th-dots {
    min-width: 0;
    padding: 0 3px;
    border: 0;
    background: transparent;
    color: #94a3b8;
}

@media (min-width: 1280px) {
    .th-search {
        flex: none;
        width: 440px;
    }
}

@media (min-width: 1536px) {
    .th-search {
        width: 440px;
    }
}

@media (max-width: 640px) {
    .th-search {
        flex: 1 1 100%;
    }

    .th-filter-group {
        width: 100%;
    }

    .th-dropdown {
        flex: 1;
        min-width: 0;
        width: auto;
    }

    .th-dropdown summary {
        padding: 8px 10px;
        font-size: 12px;
        height: 38px;
    }

    .th-pagination {
        padding: 14px;
        justify-content: center;
        gap: 10px;
    }

    .th-summary {
        text-align: center;
        width: 100%;
    }

    .th-pages {
        justify-content: center;
    }
}

/* ช่องว่างอยู่ให้ซ่อนปุ่ม พอพิมพ์ให้แสดงทันที */
.th-search:has(input[name="hq"]:placeholder-shown) .th-search-clear {
    display: none;
}

.th-search-clear {
    border-top: 0;
    border-right: 0;
    border-bottom: 0;
    background: transparent;
    cursor: pointer;
}

/* มือถือ/iPad: คงตารางและเลื่อนแนวนอนภายในกรอบ */
@media (max-width: 767px), (any-pointer: coarse) {
    .tech-dashboard-ui #repairsMainCard .history-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .tech-dashboard-ui #repairsMainCard #historyTableFull {
        display: table;
        min-width: 1280px !important;
    }
    .tech-dashboard-ui #repairsMainCard #historyTableFull thead {display: table-header-group;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull tbody {display: table-row-group;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull tbody tr {
        display: table-row;
        padding: 0;
    }
    .tech-dashboard-ui #repairsMainCard #historyTableFull tbody td {
        display: table-cell;
        width: auto !important;
        padding: 14px 6px !important;
    }
    .tech-dashboard-ui #repairsMainCard #historyTableFull tbody td:first-child {padding-left: 10px !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull tbody td::before {display: none;content: none;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull .th-status {white-space: nowrap !important;}
}

/* iPad ทั้งสองแนว / มือถือแนวนอน: ตารางครบทุกคอลัมน์ */
@media (min-width: 768px) and (any-pointer: coarse), (max-width: 767px) and (orientation: landscape) {
    .tech-dashboard-ui #repairsMainCard .history-table-scroll {overflow-x:hidden;overflow-y:auto;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull {width:100% !important;min-width:0 !important;table-layout:fixed !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th, .tech-dashboard-ui #repairsMainCard #historyTableFull td {
        min-width:0 !important;max-width:none;white-space:normal !important;
        word-break:normal !important;overflow-wrap:anywhere;letter-spacing:0 !important;
        padding:10px 3px !important;
    }
    .tech-dashboard-ui #repairsMainCard #historyTableFull th {font-size:clamp(8px, .8vw, 9.5px) !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull td {font-size:clamp(9px, .95vw, 12px) !important;line-height:1.6;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull td :is(div,span) {min-width:0;max-width:100%;white-space:normal !important;overflow-wrap:anywhere;font-size:inherit;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull td .th-status {padding:3px 4px;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull tbody td, .tech-dashboard-ui #repairsMainCard #historyTableFull tbody td:first-child {padding:10px 3px !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull td .th-date, .tech-dashboard-ui #repairsMainCard #historyTableFull td .th-status {white-space:normal !important;overflow-wrap:anywhere;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(1) {width:3% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(2) {width:8% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(3) {width:8% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(4) {width:11% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(5) {width:10% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(6) {width:11% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(7) {width:10% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(8) {width:8% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(9) {width:6% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(10) {width:9% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(11) {width:8% !important;}
    .tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(12) {width:8% !important;}
}
</style>

<div id="tab-content-history"
     class="<?= $thOnHistory ? '' : 'hidden ' ?>animate-fade-in no-print">

    <div class="modern-card overflow-hidden flex flex-col bg-white"
         id="repairsMainCard">

        <div class="p-4 md:p-6 border-b border-slate-100 bg-white shrink-0">

            <div class="flex justify-between items-start gap-3 mb-4">
                <div>
                    <h2 class="text-xl font-extrabold text-slate-800">
                        ประวัติการรับแจ้งซ่อมทั้งหมด
                    </h2>
                    <p class="text-sm text-slate-400">
                        All repair transactions
                    </p>
                </div>

                <div class="flex gap-2 shrink-0">
                    <button type="button"
                            onclick="toggleMaximizeRepairs()"
                            class="w-9 h-9 border border-slate-200 rounded-xl text-slate-400"
                            title="สลับเต็มจอ">
                        <i class="fas fa-expand"
                           id="maximizeRepairsIcon"></i>
                    </button>

                    <button type="button"
                            onclick="if(document.getElementById('repairsMainCard').classList.contains('is-fullscreen')) toggleMaximizeRepairs(); else showTab('dash');"
                            class="w-9 h-9 border border-slate-200 rounded-xl text-slate-400"
                            title="ปิด">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <?php
            $thStatusColors = [
                'all' => 'background:#f8fafc;color:#334155;',
                'รอรับเรื่อง' => 'background:#fef3c7;color:#d97706;',
                'กำลังดำเนินการ' => 'background:#e0e7ff;color:#4f46e5;',
                'ซ่อมเสร็จแล้ว' => 'background:#d1fae5;color:#059669;',
            ];
            ?>

            <div class="th-filter-row">

                <form method="get"
                    action="technician_home.php#tab-content-history"
                    class="th-search">

                    <input type="hidden" name="tab" value="history">
                    <input type="hidden" name="hd"
                        value="<?= $thEscape($thDay) ?>">
                    <input type="hidden" name="hm"
                        value="<?= $thEscape($thMonth) ?>">
                    <input type="hidden" name="hy"
                        value="<?= $thEscape($thYear) ?>">
                    <input type="hidden" name="hs"
                        value="<?= $thEscape($thStatus) ?>">

                    <i class="fas fa-search th-search-icon"
                    aria-hidden="true"></i>

                    <input type="text"
                        name="hq"
                        value="<?= $thEscape($thSearch) ?>"
                        maxlength="200"
                        placeholder="ค้นหาข้อมูลในตาราง..."
                        aria-label="ค้นหาข้อมูลในตาราง กด Enter เพื่อค้นหา">

                    <button type="button"
                        class="th-search-clear"
                        aria-label="ล้างคำค้น"
                        onclick="
                            const input = this.form.elements.namedItem('hq');
                            input.value = '';
                            input.focus({ preventScroll: true });
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        ">
                    <i class="fas fa-times" aria-hidden="true"></i>
                    ล้างค่า
                </button>
                </form>

                <div class="th-filter-group">

                    <details class="th-dropdown" name="th-history-filters">
                        <summary>
                            <span><?= $thDay === 'all' ? 'ทุกวัน' : 'วันที่ ' . $thEscape($thDay) ?></span>
                            <i class="fas fa-caret-down" aria-hidden="true"></i>
                        </summary>
                        <div class="th-dropdown-menu">
                            <a class="th-all-period" href="<?= $thEscape($thUrl(1, ['hd' => 'all'])) ?>">ทุกวัน</a>
                            <?php for ($thDayNumber = 1; $thDayNumber <= 31; $thDayNumber++): ?>
                                <a href="<?= $thEscape($thUrl(1, ['hd' => (string)$thDayNumber])) ?>"
                                   class="<?= $thDay === (string)$thDayNumber ? 'is-selected' : '' ?>">วันที่ <?= $thDayNumber ?></a>
                            <?php endfor; ?>
                        </div>
                    </details>

                    <details class="th-dropdown" name="th-history-filters">
                        <summary>
                            <span>
                                <?= $thMonth === 'all' ? 'ทุกเดือน' : $thEscape($thai_months[(int) $thMonth]) ?>
                            </span>
                            <i class="fas fa-caret-down" aria-hidden="true"></i>
                        </summary>

                        <div class="th-dropdown-menu">

                            <a class="th-all-period" href="<?= $thEscape($thUrl(1, ['hm' => 'all'])) ?>">ทุกเดือน</a>
                            <?php foreach ($thai_months as $number => $label):
                                $value = sprintf('%02d', $number);
                            ?>
                                <a href="<?= $thEscape(
                                    $thUrl(1, ['hm' => $value])
                                ) ?>"
                                class="<?= $thMonth === $value
                                    ? 'is-selected' : '' ?>">
                                    <?= $thEscape($label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <details class="th-dropdown">
                        <summary>
                            <span>
                                <?= $thYear === 'all' ? 'ทุกปี' : (int) $thYear + 543 ?>
                            </span>
                            <i class="fas fa-caret-down" aria-hidden="true"></i>
                        </summary>

                        <div class="th-dropdown-menu">

                            <a class="th-all-period" href="<?= $thEscape($thUrl(1, ['hy' => 'all'])) ?>">ทุกปี</a>
                            <?php foreach ($thYears as $year): ?>
                                <a href="<?= $thEscape(
                                    $thUrl(1, ['hy' => (string) $year])
                                ) ?>"
                                class="<?= $thYear === (string) $year
                                    ? 'is-selected' : '' ?>">
                                    <?= (int) $year + 543 ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>

                    <details class="th-dropdown th-status-dropdown" name="th-history-filters">
                        <summary style="<?= $thStatus === 'all'
                            ? ''
                            : $thStatusColors[$thStatus] ?>">
                            <span><?= $thEscape($thStatuses[$thStatus]) ?></span>
                            <i class="fas fa-caret-down" aria-hidden="true"></i>
                        </summary>

                        <div class="th-dropdown-menu th-status-menu">
                            <?php foreach ($thStatuses as $value => $label): ?>
                                <a href="<?= $thEscape(
                                    $thUrl(1, ['hs' => $value])
                                ) ?>"
                                style="<?= $thStatusColors[$value] ?>">
                                    <?= $thEscape($label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>

                </div>
            </div>
        </div>

        <div class="history-table-scroll w-full custom-scrollbar"
             id="repairsTableContainer">

            <table class="w-full text-left" id="historyTableFull">
                <thead class="sticky top-0 z-20">
                    <tr>
                        <?php foreach ([
                            'ID',
                            'Date / Time',
                            'Ticket No.',
                            'Reporter',
                            'Equipment',
                            'Department',
                            'Technician',
                            'Received At',
                            'Root Cause',
                            'Status',
                            'Completed At',
                            'Action',
                        ] as $heading): ?>
                            <th><?= $thEscape($heading) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white"
                       id="historyTableBody">

                    <?php if (!$thRows): ?>
                        <tr>
                            <td colspan="12"
                                class="text-center text-slate-400">
                                ไม่พบข้อมูลใบงานที่ตรงกับเงื่อนไข
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($thRows as $r):
                        $reporter = (string) ($r['reporter_name'] ?? '');
                        $reporter = $line_users_map[$reporter]
                            ?? ($reporter !== '' ? $reporter : 'ไม่ระบุ');

                        $staff = (string) ($r['technician_name'] ?? '');
                        $info = $tech_info_map[$staff]
                            ?? ['eng' => '', 'pos' => ''];

                        $status = trim(
                            (string) ($r['status'] ?? 'รอดำเนินการ')
                        );

                        $badge = in_array(
                            $status,
                            ['ซ่อมเสร็จแล้ว', 'เสร็จสิ้น'],
                            true
                        )
                            ? 'badge-success'
                            : (
                                $status === 'กำลังดำเนินการ'
                                ? 'badge-progress'
                                : 'badge-pending'
                            );
                    ?>
                        <tr class="hover:bg-slate-50/50">
                            <td class="font-mono font-semibold text-slate-600">
                                <?= (int) $r['id'] ?>
                            </td>
                            <td>
                                <?= $thDate($r['created_at'] ?? '') ?>
                            </td>

                            <td class="font-mono text-slate-500">
                                <?= $thEscape(
                                    trim((string) ($r['ticket_no'] ?? '')) !== ''
                                        ? $r['ticket_no']
                                        : '-'
                                ) ?>
                            </td>

                            <td>
                                <div class="font-bold text-slate-800">
                                    <?= $thEscape($reporter) ?>
                                </div>
                                <div class="text-slate-500 text-[11px]">
                                    <?= $thEscape($r['phone_number'] ?? '-') ?>
                                </div>
                            </td>

                            <td>
                                <div class="font-bold text-slate-800">
                                    <?= $thEscape(
                                        trim((string) ($r['equipment_type'] ?? '')) !== ''
                                            ? $r['equipment_type']
                                            : 'ไม่ระบุ'
                                    ) ?>

                                    <?= dm_icons($conn, $r) ?>
                                </div>

                                <div class="text-slate-500 text-[11px]">
                                    <?= $thEscape($r['problem_desc'] ?? '-') ?>
                                </div>
                            </td>

                            <td>
                                <div class="inline-block rounded-lg bg-slate-100 border border-slate-200 px-2 py-1 font-bold">
                                    <?= $thEscape($tech_dept_map[$staff] ?? '-') ?>
                                </div>
                                <div class="text-slate-500 text-[11px] font-bold mt-1">
                                    <?= $thEscape($info['pos']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="text-indigo-600 font-bold">
                                    <?= $thEscape($staff ?: '-') ?>
                                </div>
                                <div class="text-slate-400 text-[10px] mt-1">
                                    <?= $thEscape($info['eng']) ?>
                                </div>
                            </td>

                            <td>
                                <?= $thDate($r['received_at'] ?? '') ?>
                            </td>

                            <td>
                                <?= formatEmptyOrDash($r['root_cause'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="th-status <?= $badge ?>">
                                    <?= $thEscape($status) ?>
                                </span>
                            </td>

                            <td>
                                <?= $thDate($r['completed_at'] ?? '', true) ?>
                            </td>

                            <td>
                                <?php if ((int)($r['th_can_edit'] ?? 0) === 1): ?>
                                <a target="_blank"
                                   href="update_repair.php?id=<?= (int) $r['id'] ?>"
                                   onclick="window.open(this.href, 'mbsTechnicianRepair'); return false;"
                                   class="w-8 h-8 rounded-xl bg-slate-50 text-slate-500 flex items-center justify-center border border-slate-100"
                                   title="เปิดใบงาน">
                                    <i class="fas fa-pen-to-square"></i>
                                </a>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400 font-medium whitespace-nowrap">รับงานผ่าน LINE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="th-pagination">
            <p class="th-summary">
                แสดงรายการ
                <strong><?= $thTotal
                    ? number_format(($thPage - 1) * 50 + 1)
                    : '0' ?>–<?= number_format(
                        min($thPage * 50, $thTotal)
                    ) ?></strong>
                จากทั้งหมด
                <strong><?= number_format($thTotal) ?></strong>
                รายการ · หน้า <?= number_format($thPage) ?>
                / <?= number_format($thPages) ?>
            </p>

            <nav class="th-pages" aria-label="แบ่งหน้าประวัติงาน">
                <?php if ($thPage > 1): ?>
                    <a href="<?= $thEscape($thUrl($thPage - 1)) ?>">
                        ‹ ก่อนหน้า
                    </a>
                <?php else: ?>
                    <span aria-disabled="true">‹ ก่อนหน้า</span>
                <?php endif; ?>

                <?php
                $thNumbers = array_unique(array_merge(
                    [1],
                    range(
                        max(1, $thPage - 1),
                        min($thPages, $thPage + 1)
                    ),
                    [$thPages]
                ));

                sort($thNumbers);
                $thPrevious = 0;

                foreach ($thNumbers as $number):
                    if ($thPrevious && $number > $thPrevious + 1) {
                        echo '<span class="th-dots">…</span>';
                    }

                    $thPrevious = $number;
                ?>
                    <?php if ($number === $thPage): ?>
                        <span aria-current="page">
                            <?= number_format($number) ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= $thEscape($thUrl($number)) ?>">
                            <?= number_format($number) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($thPage < $thPages): ?>
                    <a href="<?= $thEscape($thUrl($thPage + 1)) ?>">
                        ถัดไป ›
                    </a>
                <?php else: ?>
                    <span aria-disabled="true">ถัดไป ›</span>
                <?php endif; ?>
            </nav>
        </div>
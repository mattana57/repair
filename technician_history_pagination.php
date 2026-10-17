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

$thMonth = $thGet('hm', date('m'));
$thYear = $thGet('hy', date('Y'));
$thStatus = $thGet('hs', 'all');
$thSearch = $thGet('hq', '');

if ($thMonth !== 'all'
    && !preg_match('/\A(?:0[1-9]|1[0-2])\z/', $thMonth)) {
    $thMonth = date('m');
}

if ($thYear !== 'all'
    && !preg_match('/\A[1-9][0-9]{3}\z/', $thYear)) {
    $thYear = date('Y');
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
    $thWhere = '(' . $where . ')';
    $thTypes = '';
    $thParams = [];

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

        $thWhere .=
            " AND LOCATE(LOWER(?), LOWER(CONCAT_WS(' ', "
            . implode(', ', $thColumns)
            . '))) > 0';

        $thTypes .= 's';
        $thParams[] = $thSearch;
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
        'SELECT * FROM repairs WHERE ' . $thWhere
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

$thUrl = static function ($page) use (
    $thMonth,
    $thYear,
    $thStatus,
    $thSearch
) {
    return 'technician_home.php?' . http_build_query(
        [
            'tab' => 'history',
            'hm' => $thMonth,
            'hy' => $thYear,
            'hs' => $thStatus,
            'hq' => $thSearch,
            'hp' => $page,
        ],
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
        . $thEscape($parts[0])
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
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(2) {
    width: 6%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(3) {
    width: 12%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(4) {
    width: 11%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(5) {
    width: 16%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(6) {
    width: 11%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(7) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(8) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(9) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(10) {
    width: 8%;
}
.tech-dashboard-ui #repairsMainCard #historyTableFull th:nth-child(11) {
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

            <form method="get"
                  action="technician_home.php#tab-content-history"
                  class="th-filters">

                <input type="hidden" name="tab" value="history">

                <input type="search"
                       name="hq"
                       value="<?= $thEscape($thSearch) ?>"
                       maxlength="200"
                       placeholder="ค้นหาในตาราง..."
                       aria-label="ค้นหาในประวัติงาน">

                <select name="hm" aria-label="เดือน">
                    <option value="all"
                        <?= $thMonth === 'all' ? 'selected' : '' ?>>
                        ทุกเดือน
                    </option>

                    <?php foreach ($thai_months as $number => $label):
                        $value = sprintf('%02d', $number);
                    ?>
                        <option value="<?= $value ?>"
                            <?= $thMonth === $value ? 'selected' : '' ?>>
                            <?= $thEscape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="hy" aria-label="ปี">
                    <option value="all"
                        <?= $thYear === 'all' ? 'selected' : '' ?>>
                        ทุกปี
                    </option>

                    <?php foreach ($thYears as $year): ?>
                        <option value="<?= (int) $year ?>"
                            <?= $thYear === (string) $year ? 'selected' : '' ?>>
                            <?= (int) $year + 543 ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="hs" aria-label="สถานะ">
                    <?php foreach ($thStatuses as $value => $label): ?>
                        <option value="<?= $thEscape($value) ?>"
                            <?= $thStatus === $value ? 'selected' : '' ?>>
                            <?= $thEscape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">ค้นหา / กรอง</button>

                <a class="th-clear"
                   href="<?= $thEscape(
                       'technician_home.php?' . http_build_query([
                           'tab' => 'history',
                           'hm' => $thMonth,
                           'hy' => $thYear,
                           'hs' => $thStatus,
                       ]) . '#tab-content-history'
                   ) ?>">
                    ล้างคำค้น
                </a>
            </form>
        </div>

        <div class="history-table-scroll w-full custom-scrollbar"
             id="repairsTableContainer">

            <table class="w-full text-left" id="historyTableFull">
                <thead class="sticky top-0 z-20">
                    <tr>
                        <?php foreach ([
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
                            <td colspan="11"
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
                            <td>
                                <?= $thDate($r['created_at'] ?? '') ?>
                            </td>

                            <td class="font-mono text-slate-500">
                                <?= $thEscape(
                                    $r['repair_code'] ?? ('MR-' . $r['id'])
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
                                        $r['equipment']
                                        ?? $r['problem']
                                        ?? 'ไม่ระบุ'
                                    ) ?>

                                    <?php if (!empty($r['image_path'])): ?>
                                        <i class="fas fa-image text-slate-400 ml-1"
                                           title="มีรูปภาพแนบ"></i>
                                    <?php endif; ?>
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
                                <a target="_blank"
                                   href="update_repair.php?id=<?= (int) $r['id'] ?>"
                                   onclick="window.open(this.href, 'mbsTechnicianRepair'); return false;"
                                   class="w-8 h-8 rounded-xl bg-slate-50 text-slate-500 flex items-center justify-center border border-slate-100"
                                   title="เปิดใบงาน">
                                    <i class="fas fa-pen-to-square"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="th-pagination">
            <div>
                แสดงรายการ
                <?= $thTotal ? number_format(($thPage - 1) * 50 + 1) : '0' ?>
                –<?= number_format(min($thPage * 50, $thTotal)) ?>
                จากทั้งหมด <?= number_format($thTotal) ?> รายการ
                · หน้า <?= $thPage ?> / <?= $thPages ?>
            </div>

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
                    [1, $thPages],
                    range(
                        max(1, $thPage - 2),
                        min($thPages, $thPage + 2)
                    )
                ));

                sort($thNumbers);
                $thPrevious = 0;

                foreach ($thNumbers as $number):
                    if ($thPrevious && $number > $thPrevious + 1) {
                        echo '<span>…</span>';
                    }
                    $thPrevious = $number;
                ?>
                    <?php if ($number === $thPage): ?>
                        <span aria-current="page"><?= $number ?></span>
                    <?php else: ?>
                        <a href="<?= $thEscape($thUrl($number)) ?>">
                            <?= $number ?>
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
    </div>
</div>
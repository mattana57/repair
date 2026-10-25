<?php
require_once __DIR__.'/dashboard_features.php';
// ปฏิเสธการเปิดไฟล์ช่วยผ่าน URL โดยตรง
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง');
}

// ใช้หลังตรวจสิทธิ์ผู้ใช้แล้วเท่านั้น
// ไฟล์นี้อ่านข้อมูล ไม่แก้โครงสร้างฐานข้อมูล

function tp_text($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function tp_get($key, $default = '') {
    return isset($_GET[$key]) && is_string($_GET[$key])
        ? trim($_GET[$key])
        : $default;
}

// วันที่ของ Transactions เท่านั้น: ใช้เวลาไทยเมื่อไม่ได้เลือกวันที่ไว้ใน URL
function tp_repairs_day() {
    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('j');
    $day = tp_get('pg_repairs_day', $today);
    if ($day === 'all') return 'all';
    if (ctype_digit($day) && (int)$day >= 1 && (int)$day <= 31) {
        return (string)(int)$day;
    }
    return $today;
}

// เดือน/ปีของ Transactions: ค่าเริ่มต้นปัจจุบัน และรองรับ all แยกจากกัน
function tp_repairs_period($part) {
    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $default = $today->format($part === 'month' ? 'm' : 'Y');
    $value = tp_get('pg_repairs_' . $part, $default);
    if ($value === 'all') return 'all';
    if ($part === 'month' && ctype_digit($value) && (int)$value >= 1 && (int)$value <= 12) {
        return str_pad((string)(int)$value, 2, '0', STR_PAD_LEFT);
    }
    if ($part === 'year' && preg_match('/^[1-9][0-9]{3}$/D', $value)) return $value;
    return $default;
}

function tp_url(array $changes = [], array $remove = []) {
    $query = [];

    foreach ($_GET as $key => $value) {
        if (
            is_string($value)
            && (
                $key === 'tab' || preg_match('/^mbs_[a-zA-Z-]+_(start|end|day|month|year)$/D', $key)
                || preg_match(
                    '/^pg_(repairs|admins|techs|assets|reporters|logs|history)_(page|q|month|year|status|department)$/D',
                    $key
                )
                || in_array($key, ['pg_repairs_day', 'pg_logs_day', 'pg_logs_role', 'pg_logs_full', 'pg_history_day'], true)
                || in_array(
                    $key,
                    ['pg_history_type', 'pg_history_name', 'pg_history_technician_id', 'pg_history_line_user_id', 'pg_full'],
                    true
                )
            )
        ) {
            $query[$key] = $value;
        }
    }

    foreach ($remove as $key) {
        unset($query[$key]);
    }

    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = (string)$value;
        }
    }

    return '?' . http_build_query(
        $query,
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

function tp_query($conn, $sql, $types = '', array $values = []) {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('โหลดรายการไม่สำเร็จ');
    }

    try {
        if (
            $types !== ''
            && !$stmt->bind_param($types, ...$values)
        ) {
            throw new RuntimeException('โหลดรายการไม่สำเร็จ');
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('โหลดรายการไม่สำเร็จ');
        }

        $result = $stmt->get_result();

        if (!$result) {
            throw new RuntimeException('โหลดรายการไม่สำเร็จ');
        }

        return $result;
    } finally {
        $stmt->close();
    }
}

function tp_page(
    $conn,
    $select,
    $count,
    $types,
    array $values,
    $name,
    $tab
) {
    $countResult = tp_query($conn, $count, $types, $values);
    $total = (int)$countResult->fetch_assoc()['total'];
    $countResult->free();

    $pages = max(1, (int)ceil($total / 50));
    $raw = tp_get('pg_' . $name . '_page', '1');

    $page = ctype_digit($raw)
        ? max(1, min($pages, (int)$raw))
        : 1;

    $offset = ($page - 1) * 50;
    $params = array_merge($values, [50, $offset]);

    return [
        'result' => tp_query(
            $conn,
            $select . ' LIMIT ? OFFSET ?',
            $types . 'ii',
            $params
        ),
        'total' => $total,
        'pages' => $pages,
        'page' => $page,
        'offset' => $offset,
        'name' => $name,
        'tab' => $tab
    ];
}

function tp_like($query) {
    return '%' . strtr(
        $query,
        [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_'
        ]
    ) . '%';
}

function tp_search(
    $prefix,
    array $columns,
    array $nameMap = [],
    $nameColumn = ''
) {
    $q = tp_get('pg_' . $prefix . '_q');

    if ($q === '') {
        return ['1=1', '', []];
    }

    $parts = [];
    $values = [];

    foreach ($columns as $column) {
        $parts[] = $column . " LIKE ? ESCAPE '!'";
        $values[] = tp_like($q);
    }

    // ค้นชื่อจริงที่หน้าเว็บแสดงด้วย
    // แม้ฐานข้อมูลใบงานเก็บชื่อ LINE
    if ($nameColumn !== '') {
        foreach ($nameMap as $raw => $info) {
            $text = is_array($info)
                ? implode(' ', array_filter($info, 'is_scalar'))
                : (string)$info;

            $matched = function_exists('mb_stripos')
                ? mb_stripos($text, $q, 0, 'UTF-8') !== false
                : stripos($text, $q) !== false;

            if ($matched) {
                $parts[] = $nameColumn . ' = ?';
                $values[] = (string)$raw;
            }
        }
    }

    return [
        '(' . implode(' OR ', $parts) . ')',
        str_repeat('s', count($values)),
        $values
    ];
}

function tp_repairs(
    $conn,
    $prefix,
    $tab,
    array $lineMap = [],
    array $techMap = [],
    $baseWhere = '1=1',
    $baseTypes = '',
    array $baseValues = [],
    array $deptMap = []
) {
    list($search, $types, $values) = tp_search(
        $prefix,
        [
            'r.ticket_no',
            'r.reporter_name',
            'r.phone_number',
            'r.equipment_type',
            'r.problem_desc',
            'r.technician_name',
            'r.root_cause',
            'r.status',
            'r.created_at',
            'r.received_at',
            'r.completed_at'
        ],
        $lineMap,
        'r.reporter_name'
    );

    $q = tp_get('pg_' . $prefix . '_q');

    if ($q !== '') {
        foreach ($techMap as $raw => $info) {
            $text = (
                is_array($info)
                    ? implode(' ', array_filter($info, 'is_scalar'))
                    : (string)$info
            ) . ' ' . ($deptMap[$raw] ?? '');

            $matched = function_exists('mb_stripos')
                ? mb_stripos($text, $q, 0, 'UTF-8') !== false
                : stripos($text, $q) !== false;

            if ($matched) {
                $search = '(' . $search . ' OR r.technician_name = ?)';
                $types .= 's';
                $values[] = (string)$raw;
            }
        }
    }

    $where = '(' . $baseWhere . ') AND '.mbs_active_repair_sql('r').' AND ' . $search;
    $types = $baseTypes . $types;
    $values = array_merge($baseValues, $values);
    $where .= mbs_range_clause('table','r.created_at',$types,$values);

    $month = tp_get('pg_' . $prefix . '_month');
    $year = tp_get('pg_' . $prefix . '_year');
    $status = tp_get('pg_' . $prefix . '_status');

    // จำกัดเฉพาะ Transactions ของแอดมิน ไม่เปลี่ยนตัวกรองประวัติหรือหน้าอื่น
    if ($prefix === 'repairs') {
        $month = tp_repairs_period('month');
        $year = tp_repairs_period('year');
        $day = tp_repairs_day();
        if ($day !== 'all') {
            $where .= ' AND DAY(r.created_at) = ?';
            $types .= 'i';
            $values[] = (int)$day;
        }
    }

    if (
        ctype_digit($month)
        && (int)$month >= 1
        && (int)$month <= 12
    ) {
        $where .= ' AND MONTH(r.created_at) = ?';
        $types .= 'i';
        $values[] = (int)$month;
    }

    if (preg_match('/^[1-9][0-9]{3}$/D', $year)) {
        $where .= ' AND YEAR(r.created_at) = ?';
        $types .= 'i';
        $values[] = (int)$year;
    }

    if (
        in_array(
            $status,
            ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
            true
        )
    ) {
        $where .= ' AND r.status = ?';
        $types .= 's';
        $values[] = $status;
    }

    return tp_page(
        $conn,
        'SELECT r.* FROM repairs r WHERE '
            . $where
            . ' ORDER BY r.created_at DESC, r.id DESC',
        'SELECT COUNT(*) AS total FROM repairs r WHERE ' . $where,
        $types,
        $values,
        $prefix,
        $tab
    );
}

function tp_styles() {
    ?>
    <style>
        .tp-footer {
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

        .tp-summary {
            font-size: 13px;
            color: #64748b;
        }

        .tp-summary strong {
            color: #4338ca;
        }

        .tp-nav {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .tp-link,
        .tp-disabled {
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

        .tp-link:hover {
            border-color: #a5b4fc;
            background: #eef2ff;
            color: #4338ca;
        }

        .tp-link:focus-visible {
            outline: 3px solid #a5b4fc;
            outline-offset: 2px;
        }

        .tp-current {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
            box-shadow: 0 3px 9px #c7d2fe;
        }

        .tp-disabled {
            color: #94a3b8;
            background: #f1f5f9;
        }

        .tp-dots {
            color: #94a3b8;
            padding: 0 3px;
        }

        .tp-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: end;
            width: 100%;
        }

        .tp-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }

        .tp-field input,
        .tp-field select {
            height: 42px;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            background: white;
            color: #334155;
            font: 500 13px 'Sarabun', sans-serif;
            padding: 0 12px;
            max-width: 100%;
        }

        .tp-field input {
            width: 260px;
        }

        .tp-submit,
        .tp-reset {
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            border-radius: 11px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .tp-submit {
            background: #4f46e5;
            color: white;
            border: 0;
            cursor: pointer;
        }

        .tp-reset {
            color: #64748b;
            background: #fff;
            border: 1px solid #e2e8f0;
        }

        .tp-modal {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .tp-overlay {
            position: absolute;
            inset: 0;
            background: #0f172a66;
            backdrop-filter: blur(4px);
        }

        .tp-dialog {
            position: relative;
            display: flex;
            flex-direction: column;
            background: #fff;
            width: 95%;
            max-width: 1200px;
            height: 85vh;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 60px #0f172a40;
        }

        .tp-dialog-full {
            width: 100%;
            height: 100%;
            max-width: none;
            border-radius: 0;
        }

        .tp-modal-full {
            padding: 0;
        }

        .tp-dialog-head {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0;
        }

        .tp-dialog-title {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 12px;
            margin-bottom: 14px;
        }

        .tp-dialog-scroll {
            flex: 1;
            min-height: 0;
            overflow: auto;
        }

        .tp-empty {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .tp-footer {
                padding: 14px;
                justify-content: center;
                gap: 10px;
            }

            .tp-summary {
                text-align: center;
                width: 100%;
            }

            .tp-nav {
                justify-content: center;
            }

            .tp-field input {
                width: 100%;
            }

            .tp-field:first-of-type {
                flex: 1;
                min-width: 160px;
            }

            .tp-dialog {
                width: 100%;
                height: 90vh;
            }

            .tp-dialog-full {
                height: 100%;
            }

            .tp-dialog-head {
                padding: 16px;
            }
        }
    </style>
    <?php
}

function tp_footer(array $paging) {
    $total = $paging['total'];
    $page = $paging['page'];
    $pages = $paging['pages'];

    $first = $total === 0 ? 0 : $paging['offset'] + 1;
    $last = min($total, $paging['offset'] + 50);

    echo '<div class="tp-footer" id="pg-'
        . tp_text($paging['name'])
        . '-footer"><p class="tp-summary">แสดงรายการ <strong>'
        . number_format($first)
        . '–'
        . number_format($last)
        . '</strong> จากทั้งหมด <strong>'
        . number_format($total)
        . '</strong> รายการ · หน้า '
        . number_format($page)
        . ' / '
        . number_format($pages)
        . '</p><nav class="tp-nav" aria-label="แบ่งหน้ารายการ">';

    $link = function($target, $label, $current = false) use ($paging) {
        $url = tp_url([
            'tab' => $paging['tab'],
            'pg_' . $paging['name'] . '_page' => $target
        ]);

        if ($current) {
            echo '<span class="tp-link tp-current" aria-current="page">'
                . $label
                . '</span>';
        } else {
            echo '<a class="tp-link" href="'
                . tp_text($url)
                . '">'
                . $label
                . '</a>';
        }
    };

    if ($page > 1) {
        $link($page - 1, '‹ ก่อนหน้า');
    } else {
        echo '<span class="tp-disabled" aria-disabled="true">‹ ก่อนหน้า</span>';
    }

    $numbers = array_unique(array_merge(
        [1],
        range(max(1, $page - 1), min($pages, $page + 1)),
        [$pages]
    ));

    sort($numbers);
    $previous = 0;

    foreach ($numbers as $number) {
        if ($previous && $number > $previous + 1) {
            echo '<span class="tp-dots">…</span>';
        }

        $link($number, number_format($number), $number === $page);
        $previous = $number;
    }

    if ($page < $pages) {
        $link($page + 1, 'ถัดไป ›');
    } else {
        echo '<span class="tp-disabled" aria-disabled="true">ถัดไป ›</span>';
    }

    echo '</nav></div>';
}

function tp_form(
    $prefix,
    $tab,
    array $months = [],
    array $years = [],
    $repair = false,
    array $departments = []
) {
    $params = [];
    parse_str(ltrim(tp_url(['tab' => $tab]), '?'), $params);

    foreach (array_keys($params) as $key) {
        if (strpos($key, 'pg_' . $prefix . '_') === 0) {
            unset($params[$key]);
        }
    }

    echo '<form method="get" class="tp-form">';

    foreach ($params as $key => $value) {
        echo '<input type="hidden" name="'
            . tp_text($key)
            . '" value="'
            . tp_text($value)
            . '">';
    }

    echo '<label class="tp-field">ค้นหา'
        . '<input type="search" name="pg_'
        . $prefix
        . '_q" value="'
        . tp_text(tp_get('pg_' . $prefix . '_q'))
        . '" placeholder="ค้นหาจากข้อมูลทั้งหมด"></label>';

    $select = function($suffix, $label, array $options) use ($prefix) {
        $name = 'pg_' . $prefix . '_' . $suffix;

        echo '<label class="tp-field">'
            . tp_text($label)
            . '<select name="'
            . $name
            . '"><option value="">ทั้งหมด</option>';

        foreach ($options as $key => $value) {
            echo '<option value="'
                . tp_text($key)
                . '"'
                . ((string)$key === tp_get($name) ? ' selected' : '')
                . '>'
                . tp_text($value)
                . '</option>';
        }

        echo '</select></label>';
    };

    if ($repair) {
        $select('month', 'เดือน', $months);

        $yearOptions = [];

        foreach ($years as $year) {
            $yearOptions[(string)$year] = (int)$year + 543;
        }

        $select('year', 'ปี', $yearOptions);

        $select('status', 'สถานะ', [
            'รอรับเรื่อง' => 'รอรับเรื่อง',
            'กำลังดำเนินการ' => 'กำลังดำเนินการ',
            'ซ่อมเสร็จแล้ว' => 'ซ่อมเสร็จแล้ว'
        ]);
    }

    if ($departments) {
        $select('department', 'ฝ่ายงาน', $departments);
    }

    $remove = [
        'pg_' . $prefix . '_page',
        'pg_' . $prefix . '_q',
        'pg_' . $prefix . '_month',
        'pg_' . $prefix . '_year',
        'pg_' . $prefix . '_status',
        'pg_' . $prefix . '_department'
    ];

    echo '<button type="submit" class="tp-submit">ค้นหา</button>'
        . '<a class="tp-reset" href="'
        . tp_text(tp_url(['tab' => $tab], $remove))
        . '">ล้างตัวกรอง</a></form>';
}

function tp_repair_rows(
    $res,
    array $line_users_map,
    array $tech_info_map,
    array $tech_dept_map,
    $viewer
) {
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $stClass = ($row['status'] == 'รอรับเรื่อง')
                ? 'badge-pending'
                : (
                    ($row['status'] == 'กำลังดำเนินการ')
                        ? 'badge-progress'
                        : 'badge-success'
                );

            $action_url = (
                $viewer === 'executive'
                    ? 'view_repair.php?id='
                    : 'update_repair.php?id='
            )
                . (int)$row['id']
                . (
                    $viewer === 'technician'
                        ? ''
                        : '&source=tech_reviews'
                );

            $action_url = tp_text($action_url);
            $action_icon = $viewer === 'executive'
                ? 'fa-eye'
                : 'fa-pen-to-square';

            $ticket_no = formatEmptyOrDash($row['ticket_no']);

            $raw_rep = trim((string)$row['reporter_name']);
            $disp_name = $raw_rep;

            if (isset($line_users_map[$raw_rep])) {
                $entry = $line_users_map[$raw_rep];

                $disp_name = is_array($entry)
                    ? ($entry['real_name'] ?: $raw_rep)
                    : ($entry ?: $raw_rep);
            }

            $reporter_name = formatEmptyOrDash($disp_name);
            $phone_number = formatEmptyOrDash($row['phone_number']);
            $equipment_type = formatEmptyOrDash($row['equipment_type']);
            $problem_desc = formatEmptyOrDash($row['problem_desc']);

            $t_pos = '';
            $t_eng = '';
            $t_th = '';

            if (
                !empty($row['technician_name'])
                && $row['technician_name'] !== '-'
            ) {
                $t_raw = $row['technician_name'];

                if (isset($tech_info_map[$t_raw])) {
                    $t_th = htmlspecialchars($tech_info_map[$t_raw]['th']);
                    $t_eng = htmlspecialchars($tech_info_map[$t_raw]['eng']);
                    $t_pos = htmlspecialchars($tech_info_map[$t_raw]['pos']);
                } else {
                    list($th_name, $en_name) = function_exists('splitThaiEngName')
                        ? splitThaiEngName($t_raw, '')
                        : [$t_raw, ''];

                    $t_th = htmlspecialchars($th_name);
                    $t_eng = htmlspecialchars($en_name);

                    $t_pos = htmlspecialchars(
                        function_exists('getAutoPosition')
                            ? getAutoPosition($th_name)
                            : ''
                    );
                }

                $techHtml = "<div class='text-blue-600 font-bold hover:text-blue-500 transition-colors cursor-default'>{$t_th}</div>";

                if (!empty($t_eng)) {
                    $techHtml .= "<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>{$t_eng}</div>";
                }

                $techName = $techHtml;
            } else {
                $techName = "<span class='text-rose-500 font-bold'>-</span>";
            }

            $dept_str = tp_text(
                isset($tech_dept_map[$row['technician_name']])
                    ? $tech_dept_map[$row['technician_name']]
                    : 'General'
            );

            if (
                empty($row['technician_name'])
                || $row['technician_name'] === '-'
            ) {
                $deptEng = "<span class='text-rose-500 font-bold'>-</span>";
            } else {
                $deptEng = "<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold mb-1 shadow-sm'>{$dept_str}</div>";

                if (!empty($t_pos)) {
                    $deptEng .= "<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>{$t_pos}</div>";
                }
            }

            $has_created = (
                !empty($row['created_at'])
                && $row['created_at'] != '0000-00-00 00:00:00'
            );

            $created_date = $has_created
                ? (date('d/m/', strtotime($row['created_at'])) . ((int)date('Y', strtotime($row['created_at'])) + 543))
                : "<span class='text-rose-500 font-bold'>-</span>";

            $created_time = $has_created
                ? date('H:i', strtotime($row['created_at']))
                : '';

            $created_time_html = $created_time
                ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$created_time}</div>"
                : '';

            // คงรูปแบบแสดง Received At ตามโค้ดเดิม
            $raw_rec = (
                !empty($row['received_at'])
                && $row['received_at'] != '0000-00-00 00:00:00'
                && $row['received_at'] != '-'
            )
                ? $row['received_at']
                : (
                    $row['status'] !== 'รอรับเรื่อง'
                        ? date('Y-m-d H:i:s')
                        : ''
                );

            $has_received = !empty($raw_rec);

            $received_date = $has_received
                ? (date('d/m/', strtotime($raw_rec)) . ((int)date('Y', strtotime($raw_rec)) + 543))
                : "<span class='text-rose-500 font-bold'>-</span>";

            $received_time = $has_received
                ? date('H:i', strtotime($raw_rec))
                : '';

            $received_time_html = $received_time
                ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$received_time}</div>"
                : '';

            $has_completed = (
                !empty($row['completed_at'])
                && $row['completed_at'] != '0000-00-00 00:00:00'
            );

            $completed_date = $has_completed
                ? (date('d/m/', strtotime($row['completed_at'])) . ((int)date('Y', strtotime($row['completed_at'])) + 543))
                : "<span class='text-rose-500 font-bold'>-</span>";

            $completed_time = $has_completed
                ? date('H:i', strtotime($row['completed_at']))
                : '';

            $completed_time_html = $completed_time
                ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$completed_time}</div>"
                : '';

            $rootCause = (
                !empty($row['root_cause'])
                && $row['root_cause'] !== '-'
            )
                ? "<span class='text-slate-700 font-medium'>"
                    . htmlspecialchars($row['root_cause'])
                    . "</span>"
                : "<span class='text-rose-500 font-bold'>-</span>";

            $imageIcon = '';

            if (
                isset($row['image_path'])
                && !empty($row['image_path'])
            ) {
                $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
            }

            echo "<tr data-mbs-repair-id='".(int)$row['id']."' class='hover:bg-slate-50/50 transition-colors search-row'>
                <td class='px-6 py-4 align-top font-mono font-semibold text-slate-600'>" . (int)$row['id'] . "</td>
                <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                    <div class='font-medium text-slate-700'>{$created_date}</div>
                    {$created_time_html}
                </td>
                <td class='px-6 py-4 align-top font-mono font-semibold text-slate-600'>
                    {$ticket_no}
                </td>
                <td class='px-6 py-4 align-top'>
                    <div class='text-slate-800 font-bold'>{$reporter_name}</div>
                    <div class='text-slate-500 text-[11px] font-medium mt-0.5'>{$phone_number}</div>
                </td>
                <td class='px-6 py-4 align-top'>
                    <div class='text-slate-800 font-bold'>{$equipment_type} {$imageIcon}</div>
                    <div class='text-slate-500 text-[11px] font-medium mt-0.5 max-w-[150px] truncate'
                         title='" . strip_tags($problem_desc) . "'>{$problem_desc}</div>
                </td>
                <td class='px-6 py-4 align-top'>{$deptEng}</td>
                <td class='px-6 py-4 align-top'>{$techName}</td>
                <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                    <div class='font-medium text-slate-700'>{$received_date}</div>
                    {$received_time_html}
                </td>
                <td class='px-6 py-4 align-top'>{$rootCause}</td>
                <td class='px-6 py-4 align-middle text-center'>
                    <span class='{$stClass}'>{$row['status']}</span>
                </td>
                <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                    <div class='font-medium text-emerald-700'>{$completed_date}</div>
                    {$completed_time_html}
                </td>
                <td class='px-6 py-4 align-middle text-center'>
                    <div class='flex items-center justify-center'>
                        <a target='_blank'
                           href='{$action_url}'
                           class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100 shadow-2xs'
                           title='Edit'>
                            <i class='fas {$action_icon}'></i>
                        </a>
                    </div>
                </td>
            </tr>";
        }
    } else {
        echo "<tr>
            <td colspan='12'
                class='px-6 h-[400px] text-center align-middle text-slate-400 font-medium'>
                ยังไม่เคยรับงานซ่อมในระบบ
            </td>
        </tr>";
    }
}

function tp_history_modal(
    $conn,
    $tab,
    array $lineMap,
    array $techMap,
    array $deptMap,
    array $months,
    array $years,
    $viewer
) {
    $type = tp_get('pg_history_type');
    $name = tp_get('pg_history_name');

    if (
        !in_array($type, ['technician', 'reporter'], true)
        || $name === ''
    ) {
        return;
    }

    $column = $type === 'reporter'
        ? 'r.reporter_name'
        : 'r.technician_name';

    $paging = tp_repairs(
        $conn,
        'history',
        $tab,
        $lineMap,
        $techMap,
        'TRIM(' . $column . ') = ?',
        's',
        [$name],
        $deptMap
    );

    $title = $type === 'reporter'
        ? 'ประวัติการแจ้งซ่อม: '
        : 'ประวัติงานเจ้าหน้าที่: ';

    $display = $name;

    if ($type === 'reporter' && isset($lineMap[$name])) {
        $display = is_array($lineMap[$name])
            ? ($lineMap[$name]['real_name'] ?: $name)
            : ($lineMap[$name] ?: $name);
    }

    $historyKeys = [
        'pg_history_type',
        'pg_history_name',
        'pg_full',
        'pg_history_page',
        'pg_history_q',
        'pg_history_month',
        'pg_history_year',
        'pg_history_status'
    ];

    $close = tp_url([], $historyKeys);
    $full = tp_get('pg_full') === '1';
    ?>
    <div
        id="serverHistoryModal"
        class="tp-modal <?php echo $full ? 'tp-modal-full' : ''; ?>"
        role="dialog"
        aria-modal="true"
        aria-labelledby="serverHistoryTitle"
    >
        <a
            class="tp-overlay"
            href="<?php echo tp_text($close); ?>"
            aria-label="ปิดประวัติ"
        ></a>

        <div class="tp-dialog <?php echo $full ? 'tp-dialog-full' : ''; ?>">
            <div class="tp-dialog-head">
                <div class="tp-dialog-title">
                    <h2
                        id="serverHistoryTitle"
                        class="text-lg font-extrabold text-slate-800"
                    >
                        <?php echo tp_text($title . $display); ?>
                    </h2>

                    <div class="flex gap-2 shrink-0">
                        <?php
                        if (
                            $viewer === 'admin'
                            && $type === 'reporter'
                            && $tab !== 'users'
                        ):
                        ?>
                            <a
                                class="tp-link"
                                href="<?php
                                echo tp_text(
                                    tp_url(['tab' => 'users'], $historyKeys)
                                );
                                ?>"
                                title="Contacts"
                            >
                                <i class="fas fa-address-book"></i>
                            </a>
                        <?php endif; ?>

                        <a
                            class="tp-link"
                            href="<?php
                            echo tp_text(
                                tp_url(['pg_full' => $full ? null : '1'])
                            );
                            ?>"
                            aria-label="สลับเต็มจอ"
                        >
                            <i class="fas <?php echo $full ? 'fa-compress' : 'fa-expand'; ?>"></i>
                        </a>

                        <a
                            class="tp-link"
                            href="<?php echo tp_text($close); ?>"
                            aria-label="ปิดประวัติ"
                        >
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>

                <?php tp_form('history', $tab, $months, $years, true); ?>
            </div>

            <div class="tp-dialog-scroll custom-scrollbar">
                <table class="w-full text-left whitespace-nowrap min-w-[1200px]">
                    <thead class="bg-[#fef9c3] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20">
                        <tr>
                            <?php
                            foreach (
                                [
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
                                    'Action'
                                ] as $heading
                            ) {
                                echo '<th class="px-6 py-4">'
                                    . $heading
                                    . '</th>';
                            }
                            ?>
                        </tr>
                    </thead>

                    <tbody class="text-sm divide-y divide-slate-100 bg-white">
                        <?php
                        tp_repair_rows(
                            $paging['result'],
                            $lineMap,
                            $techMap,
                            $deptMap,
                            $viewer
                        );
                        ?>
                    </tbody>
                </table>
            </div>

            <?php tp_footer($paging); ?>
        </div>
    </div>
    <?php
}
<?php
// ปฏิเสธการเปิดไฟล์ช่วยผ่าน URL โดยตรง
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง');
}

// โหลดจาก dashboard.php หลังตรวจสิทธิ์แล้วเท่านั้น
require_once __DIR__ . '/table_pagination.php';

function ch_compact_sql($sql) {
    return "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($sql, ''), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '')";
}

function ch_case($column, array $map, $fallback, array &$params) {
    $sql = 'CASE';

    foreach ($map as $raw => $display) {
        $sql .= " WHEN $column = ? THEN ?";
        $params[] = (string)$raw;
        $params[] = (string)$display;
    }

    return $map ? $sql . " ELSE $fallback END" : $fallback;
}

function ch_contacts_page($conn, array $lineMap) {
    $params = [];
    $having = '';
    $q = preg_replace('/\s+/u', '', tp_get('pg_reporters_q'));

    if ($q !== '') {
        $names = [];
        $phones = [];

        foreach ($lineMap as $raw => $info) {
            $line = !empty($info['line_display_name'])
                ? $info['line_display_name']
                : $raw;

            $real = !empty($info['real_name'])
                && $info['real_name'] !== $line
                ? $info['real_name']
                : '';

            $names[$raw] = $line . $real;

            if (!empty($info['phone_number'])) {
                $phones[$raw] = $info['phone_number'];
            }
        }

        $name = ch_case(
            'reporter_name',
            $names,
            'reporter_name',
            $params
        );

        $phone = ch_case(
            'reporter_name',
            $phones,
            "COALESCE(MAX(phone_number), '-')",
            $params
        );

        $having = " HAVING "
            . ch_compact_sql("CONCAT($name, $phone, COUNT(id), 'View')")
            . " LIKE ? ESCAPE '!'";

        $params[] = tp_like($q);
    }

    $group = " FROM repairs
        WHERE reporter_name IS NOT NULL AND reporter_name != ''
        GROUP BY reporter_name" . $having;

    return tp_page(
        $conn,
        'SELECT reporter_name,
            MAX(phone_number) AS fallback_phone,
            COUNT(id) AS total_repairs'
            . $group
            . ' ORDER BY MAX(created_at) DESC, reporter_name ASC',
        'SELECT COUNT(*) AS total FROM (
            SELECT reporter_name' . $group . '
        ) ch_reporters',
        str_repeat('s', count($params)),
        $params,
        'reporters',
        'users'
    );
}

function ch_history_page(
    $conn,
    array $lineMap,
    array $techMap,
    array $deptMap
) {
    $type = tp_get('pg_history_type');
    $name = tp_get('pg_history_name');

    if (
        !in_array($type, ['reporter', 'technician'], true)
        || $name === ''
    ) {
        throw new RuntimeException('ไม่พบชื่อเจ้าของประวัติ');
    }

    if ($type === 'technician') {
        $technicianId = filter_var(
            tp_get('pg_history_technician_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($technicianId === false) {
            throw new RuntimeException('ไม่พบรหัสเจ้าของประวัติ');
        }

        $where = 'r.technician_id = ?';
        $params = [$technicianId];
        $types = 'i';
    } elseif (isset($_GET['pg_history_line_user_id'])) {
        $reporterLineId = tp_get('pg_history_line_user_id');
        if (!is_string($_GET['pg_history_line_user_id']) || $reporterLineId === '' || strlen($reporterLineId) > 200) {
            throw new RuntimeException('รหัสผู้แจ้งไม่ถูกต้อง');
        }
        $where = 'BINARY r.line_user_id = BINARY ?';
        $params = [$reporterLineId];
        $types = 's';
    } else {
        $where = 'BINARY TRIM(r.reporter_name) = BINARY ?';
        $params = [$name];
        $types = 's';
    }

    $q = preg_replace('/\s+/u', '', tp_get('pg_history_q'));

    if ($q !== '') {
        $mapped = [];

        foreach ($lineMap as $raw => $info) {
            if (!empty($info['real_name'])) {
                $mapped[$raw] = $info['real_name'];
            }
        }

        $reporter = ch_case(
            'r.reporter_name',
            $mapped,
            "COALESCE(r.reporter_name, '-')",
            $params
        );

        $mapped = [];

        foreach ($techMap as $raw => $info) {
            $mapped[$raw] = ($deptMap[$raw] ?? 'General')
                . ($info['pos'] ?? '')
                . ($info['th'] ?? $raw)
                . ($info['eng'] ?? '');
        }

        $tech = ch_case(
            'r.technician_name',
            $mapped,
            "COALESCE(r.technician_name, '-')",
            $params
        );

        $text = "CONCAT_WS('',
            r.created_at,
            r.ticket_no,
            $reporter,
            r.phone_number,
            r.equipment_type,
            r.problem_desc,
            $tech,
            r.received_at,
            r.root_cause,
            r.status,
            r.completed_at
        )";

        $where .= ' AND '
            . ch_compact_sql($text)
            . " LIKE ? ESCAPE '!'";

        $params[] = tp_like($q);
        $types .= str_repeat('s', count($params) - strlen($types));
    }

    $month = tp_get('pg_history_month', 'all');
    $year = tp_get('pg_history_year', 'all');

    if (
        ctype_digit($month)
        && (int)$month >= 1
        && (int)$month <= 12
    ) {
        $where .= ' AND MONTH(r.created_at) = ?';
        $types .= 'i';
        $params[] = (int)$month;
    }

    if (preg_match('/^[1-9][0-9]{3}$/D', $year)) {
        $where .= ' AND YEAR(r.created_at) = ?';
        $types .= 'i';
        $params[] = (int)$year;
    }

    $tab = tp_get('tab', 'dash');

    if (!in_array($tab, [
        'dash', 'repairs', 'admins', 'technicians',
        'team_cards', 'assets', 'users', 'reports', 'login_logs'
    ], true)) {
        $tab = 'dash';
    }

    return tp_page(
        $conn,
        'SELECT r.* FROM repairs r WHERE '
            . $where
            . ' ORDER BY r.created_at DESC, r.id DESC',
        'SELECT COUNT(*) AS total FROM repairs r WHERE ' . $where,
        $types,
        $params,
        'history',
        $tab
    );
}

function ch_contacts_rows($reporter_res, $line_users_map) {
    if ($reporter_res && $reporter_res->num_rows > 0) {
        while ($r = $reporter_res->fetch_assoc()) {
            $raw_name = trim((string)$r['reporter_name']);

            $line_id = $raw_name;
            $real_name = "";
            $display_phone = trim((string)$r['fallback_phone']);

            if (isset($line_users_map[$raw_name])) {
                if (!empty($line_users_map[$raw_name]['line_display_name'])) {
                    $line_id = $line_users_map[$raw_name]['line_display_name'];
                }

                if (!empty($line_users_map[$raw_name]['real_name'])) {
                    $real_name = $line_users_map[$raw_name]['real_name'];
                }

                if (!empty($line_users_map[$raw_name]['phone_number'])) {
                    $display_phone = $line_users_map[$raw_name]['phone_number'];
                }
            }

            $main_name_html = formatEmptyOrDash($line_id);

            $sub_name_html = ($real_name !== '' && $real_name !== $line_id)
                ? "<div class='text-slate-500 font-medium mt-0.5'>"
                    . htmlspecialchars($real_name) . "</div>"
                : "";

            $js_view_name = htmlspecialchars($raw_name, ENT_QUOTES);
            $rep_phone_html = formatEmptyOrDash($display_phone);

            echo "<tr class='hover:bg-slate-50/50 transition-colors user-row'>
                <td class='px-6 py-4 align-top'>
                    <div class='flex items-center'>
                        <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3 shrink-0'><i class='fas fa-user text-xs'></i></div>
                        <div>
                            <div class='text-slate-800 font-bold'>{$main_name_html}</div>
                            {$sub_name_html}
                        </div>
                    </div>
                </td>
                <td class='px-6 py-4 align-top text-slate-500 font-medium'>{$rep_phone_html}</td>
                <td class='px-6 py-4 align-middle text-center'>
                    <span class='px-3 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600'>{$r['total_repairs']}</span>
                </td>
                <td class='px-6 py-4 align-middle text-right'>
                    <div class='flex items-center justify-end space-x-2'>
                        <button onclick=\"viewHistory('{$js_view_name}', 'reporter')\" class='bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>View</span></button>
                        <button onclick=\"confirmDeleteReporter('{$js_view_name}')\" class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
                    </div>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='px-6 py-12 text-center text-slate-400'>No history found</td></tr>";
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && in_array(tp_get('pg_ch_fragment'), ['contacts', 'history'], true)
) {
    date_default_timezone_set('Asia/Bangkok');
    session_write_close();

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    $bufferLevel = ob_get_level();

    try {
        $lineMap = [];
        $check = $conn->query("SHOW TABLES LIKE 'line_users'");

        if ($check && $check->num_rows > 0) {
            $result = tp_query(
                $conn,
                'SELECT line_display_name, real_name, phone_number FROM line_users'
            );

            while ($info = $result->fetch_assoc()) {
                foreach (['line_display_name', 'real_name'] as $key) {
                    if (!empty($info[$key])) {
                        $lineMap[$info[$key]] = $info;
                    }
                }
            }
        }

        $rows = [];
        $html = '';

        if (tp_get('pg_ch_fragment') === 'contacts') {
            $paging = ch_contacts_page($conn, $lineMap);

            ob_start();
            ch_contacts_rows($paging['result'], $lineMap);
            $html = ob_get_clean();
        } else {
            $techMap = [];
            $deptMap = [];

            $result = tp_query(
                $conn,
                'SELECT full_name, department, english_name, position FROM technicians'
            );

            while ($info = $result->fetch_assoc()) {
                list($th, $eng) = splitThaiEngName(
                    $info['full_name'],
                    $info['english_name']
                );

                $deptMap[$info['full_name']] = !empty($info['department'])
                    ? $info['department']
                    : 'ฝ่ายงานทั่วไป';

                $techMap[$info['full_name']] = [
                    'th' => $th,
                    'eng' => $eng,
                    'pos' => !empty($info['position'])
                        ? $info['position']
                        : getAutoPosition($th)
                ];
            }

            $paging = ch_history_page(
                $conn,
                $lineMap,
                $techMap,
                $deptMap
            );

            while ($row = $paging['result']->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        ob_start();
        tp_footer($paging);
        $footer = ob_get_clean();
        // คง ID ในลิงก์แบ่งหน้าที่สร้างจาก helper เดิม โดยไม่แก้ helper ส่วนกลาง
        if (tp_get('pg_ch_fragment') === 'history' && isset($_GET['pg_history_line_user_id'])) {
            $footer = str_replace(
                'href="?',
                'href="?pg_history_line_user_id=' . rawurlencode(tp_get('pg_history_line_user_id')) . '&amp;',
                $footer
            );
        }

        $json = json_encode(
            [
                'rows' => $rows,
                'html' => $html,
                'footer' => $footer,
                'page' => $paging['page']
            ],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            throw new RuntimeException('โหลดรายการไม่สำเร็จ');
        }

        echo $json;
    } catch (Throwable $error) {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        http_response_code(500);
        echo '{"error":"โหลดรายการไม่สำเร็จ"}';
    }

    exit();
}
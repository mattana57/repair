<?php
require_once __DIR__.'/history_focus.php';
require_once __DIR__.'/dashboard_features.php';
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

function ch_contacts_page($conn, array $lineMap, $all = false) {
    // แสดงผู้ลงทะเบียนแม้ไม่มีใบงาน และเก็บรายชื่อจากใบงานเก่าที่ยังไม่มีทะเบียน
    // เชื่อมเฉพาะรหัส LINE แบบตรงตัว ไม่ใช้ชื่อหรือเบอร์โทรจับคู่
    $contacts = "SELECT COALESCE(history.id, 0) AS id,
        CONVERT(lu.line_user_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AS line_user_id,
        COALESCE(NULLIF(CONVERT(lu.line_display_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), NULLIF(CONVERT(history.line_display_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), '') AS line_display_name,
        COALESCE(NULLIF(CONVERT(lu.real_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), NULLIF(CONVERT(history.reporter_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), NULLIF(CONVERT(lu.line_display_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), 'ไม่ระบุชื่อผู้แจ้ง') AS reporter_name,
        COALESCE(NULLIF(CONVERT(lu.phone_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), NULLIF(CONVERT(history.phone_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), '') AS fallback_phone,
        COALESCE(history.total_repairs, 0) AS total_repairs,
        COALESCE(history.created_at, lu.created_at) AS created_at
        FROM line_users lu LEFT JOIN (SELECT latest.id, latest.line_user_id, latest.line_display_name,
        latest.reporter_name, latest.phone_number, latest.created_at, grouped.total_repairs
        FROM (SELECT MAX(id) AS latest_id, COUNT(*) AS total_repairs FROM repairs
        WHERE deleted_at IS NULL AND (technician_id IS NULL OR technician_id=0 OR EXISTS (SELECT 1 FROM technicians mbs_contact_tech WHERE mbs_contact_tech.id=repairs.technician_id AND mbs_contact_tech.deleted_at IS NULL))
        GROUP BY CAST(CASE WHEN line_user_id IS NULL OR TRIM(line_user_id) = ''
            THEN CONCAT('repair:',id) ELSE CONCAT('line:',line_user_id) END AS BINARY)) grouped JOIN repairs latest ON latest.id = grouped.latest_id) history
          ON TRIM(lu.line_user_id) <> '' AND BINARY history.line_user_id = BINARY lu.line_user_id
        WHERE COALESCE(history.total_repairs, 0) > 0
           OR NOT EXISTS (SELECT 1 FROM repairs archived
               WHERE archived.deleted_at IS NOT NULL
                 AND TRIM(lu.line_user_id) <> ''
                 AND BINARY archived.line_user_id = BINARY lu.line_user_id)
        UNION ALL
        SELECT history.id,
        CONVERT(history.line_user_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AS line_user_id,
        COALESCE(NULLIF(CONVERT(history.line_display_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), '') AS line_display_name,
        COALESCE(NULLIF(CONVERT(history.reporter_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), NULLIF(CONVERT(history.line_display_name USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), 'ไม่ระบุชื่อผู้แจ้ง') AS reporter_name,
        COALESCE(CONVERT(history.phone_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, '') AS fallback_phone,
        history.total_repairs, history.created_at
        FROM (SELECT latest.id, latest.line_user_id, latest.line_display_name,
        latest.reporter_name, latest.phone_number, latest.created_at, grouped.total_repairs
        FROM (SELECT MAX(id) AS latest_id, COUNT(*) AS total_repairs FROM repairs
        WHERE deleted_at IS NULL AND (technician_id IS NULL OR technician_id=0 OR EXISTS (SELECT 1 FROM technicians mbs_contact_tech WHERE mbs_contact_tech.id=repairs.technician_id AND mbs_contact_tech.deleted_at IS NULL))
        GROUP BY CAST(CASE WHEN line_user_id IS NULL OR TRIM(line_user_id) = ''
            THEN CONCAT('repair:',id) ELSE CONCAT('line:',line_user_id) END AS BINARY)) grouped JOIN repairs latest ON latest.id = grouped.latest_id) history
        WHERE NOT EXISTS (SELECT 1 FROM line_users lu
          WHERE TRIM(lu.line_user_id) <> '' AND BINARY lu.line_user_id = BINARY history.line_user_id)";
    $from = ' FROM (' . $contacts . ') contacts';
    $where = '1=1'; $params = []; $types = '';
    $q = preg_replace('/\s+/u', '', tp_get('pg_reporters_q'));
    if ($q !== '') {
        $where .= ' AND ' . ch_compact_sql("CONCAT_WS('', contacts.line_display_name, contacts.reporter_name, contacts.fallback_phone, contacts.total_repairs, 'View')") . " LIKE ? ESCAPE '!'";
        $params[] = tp_like($q); $types = 's';
    }
    if ($all) return tp_query($conn, 'SELECT contacts.*' . $from . ' WHERE ' . $where . ' ORDER BY contacts.created_at DESC, contacts.id DESC, BINARY contacts.line_user_id ASC', $types, $params);
    return tp_page($conn,
        'SELECT contacts.*' . $from . ' WHERE ' . $where . ' ORDER BY contacts.created_at DESC, contacts.id DESC, BINARY contacts.line_user_id ASC',
        'SELECT COUNT(*) AS total' . $from . ' WHERE ' . $where,
        $types, $params, 'reporters', 'users'
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

    $where = '(' . $where . ') AND '.mbs_active_repair_sql('r');

    $exactHistoryJob = isset($_GET['pg_history_repair_id']);
    if ($exactHistoryJob) {
        $jobId = filter_var(tp_get('pg_history_repair_id'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($jobId === false || $type !== 'reporter') $where .= ' AND 1=0';
        // The ID is a focus anchor, not an SQL row filter.
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

    $where .= mbs_range_clause('history','r.created_at',$types,$params);
    $day = tp_get('pg_history_day', 'all');
    if (ctype_digit($day) && (int)$day >= 1 && (int)$day <= 31) {
        $where .= ' AND DAY(r.created_at) = ?';
        $types .= 'i'; $params[] = (int)$day;
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

    if ($exactHistoryJob && $jobId !== false && tp_get('pg_history_focus') === '1') {
        $_GET['pg_history_page'] = (string)mbs_history_focus_page(
            static function ($sql, $args) use ($conn) {
                $types = ''; foreach ($args as $value) $types .= is_int($value) ? 'i' : 's';
                return tp_query($conn, $sql, $types, $args);
            }, 'SELECT r.id, r.created_at FROM repairs r WHERE '.$where, $params, (int)$jobId
        );
    }
    unset($_GET['pg_history_focus']);
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

            $line_id = trim((string)($r['line_display_name'] ?? ''));
            $real_name = $raw_name;
            $contactLineUserId = (string)($r['line_user_id'] ?? '');
            $canViewHistory = trim($contactLineUserId) !== '';
            $canMoveHistory = (int)$r['id'] > 0 && (int)$r['total_repairs'] > 0;
            $historyButton = $canViewHistory ? 'onclick="viewHistory(%s)"' : 'disabled aria-disabled="true" title="ไม่มีรหัสบัญชี LINE สำหรับเชื่อมประวัติ"';
            $deleteButton = $canMoveHistory ? 'onclick="confirmDeleteReporter(' . (int)$r['id'] . ')"' : 'disabled aria-disabled="true" title="ยังไม่มีประวัติใบงานให้ย้ายเข้าถังขยะ"';
            $display_phone = trim((string)$r['fallback_phone']);
            if ($line_id === '') $line_id = $raw_name;
            $reporterArgs = htmlspecialchars(
                json_encode($raw_name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                . ", 'reporter', false, false, null, "
                . json_encode($contactLineUserId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                ENT_QUOTES, 'UTF-8'
            );
            if ($canViewHistory) $historyButton = sprintf($historyButton, $reporterArgs);
            $main_name_html = formatEmptyOrDash($line_id);

            $sub_name_html = ($real_name !== '' && $real_name !== $line_id)
                ? "<div class='text-slate-500 font-medium mt-0.5'>"
                    . htmlspecialchars($real_name) . "</div>"
                : "";

            $js_view_name = htmlspecialchars($raw_name, ENT_QUOTES);
            $rep_phone_html = formatEmptyOrDash($display_phone);

            $selectionCell = '';
            if (defined('MBS_ADMIN_TABLE_BULK')) {
                $selectionLine = htmlspecialchars($contactLineUserId, ENT_QUOTES, 'UTF-8');
                $selectionEnabled = $canMoveHistory ? '1' : '0';
                $selectionDisabled = $canMoveHistory ? '' : ' disabled title="ยังไม่มีประวัติใบงานให้ย้ายเข้าถังขยะ"';
                $selectionId = (int)$r['id'];
                $selectionCell = "<td data-ab-cell class='hidden px-4 py-4 text-center'><input type='checkbox' class='ab-check' data-ab-row='reporter' data-id='{$selectionId}' data-line-id='{$selectionLine}' data-eligible='{$selectionEnabled}'{$selectionDisabled} aria-label='เลือกประวัติผู้แจ้ง'></td>";
            }
            echo "<tr class='hover:bg-slate-50/50 transition-colors user-row'>{$selectionCell}
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
                        <button {$historyButton} class='bg-white border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>View</span></button>
                        <button {$deleteButton} class='w-8 h-8 rounded-lg bg-slate-50 text-slate-500 hover:text-red-600 hover:bg-red-50 transition-all flex items-center justify-center'><i class='fas fa-trash-alt'></i></button>
                    </div>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='4' data-ab-empty='4' class='px-6 py-12 text-center text-slate-400'>No history found</td></tr>";
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
                $rows[] = function_exists('dm_enrich') ? dm_enrich($conn, $row) : $row;
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
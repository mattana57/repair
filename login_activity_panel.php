<?php
if (!defined('MBS_ACTIVITY_PANEL')
    || strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}

try {
    $lh_role_filter = tp_get('pg_logs_status', '');
    $lh_role = in_array(
        $lh_role_filter,
        ['admin', 'executive', 'technician'],
        true
    ) ? $lh_role_filter : '';

    $lh_user = filter_var(
        tp_get('pg_logs_q', '0'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $lh_user = $lh_user === false ? 0 : $lh_user;

    $lh_where = " WHERE role IN ('admin', 'executive', 'technician')";
    $lh_types = '';
    $lh_values = [];

    if ($lh_role !== '') {
        $lh_where .= ' AND role = ?';
        $lh_types = 's';
        $lh_values[] = $lh_role;
    }

    $lh_groups =
        "SELECT user_id, role,
                SUM(action_code = 'LOGIN') AS login_count,
                COUNT(*) AS event_count,
                MAX(id) AS latest_id,
                MAX(created_at) AS latest_time
         FROM user_activity_logs"
        . $lh_where
        . ' GROUP BY user_id, role';

    $lh_count_result = tp_query(
        $conn,
        'SELECT COUNT(*) AS total FROM (' . $lh_groups . ') AS grouped_users',
        $lh_types,
        $lh_values
    );
    $lh_people_total = (int) $lh_count_result->fetch_assoc()['total'];
    $lh_count_result->free();

    $lh_people_pages = max(1, (int) ceil($lh_people_total / 50));
    $lh_people_page = max(
        1,
        min($lh_people_pages, (int) tp_get('lh_people_page', '1'))
    );
    $lh_people_offset = ($lh_people_page - 1) * 50;

    $lh_people_result = tp_query(
        $conn,
        'SELECT g.*, a.username, a.full_name
         FROM (' . $lh_groups . ') AS g
         JOIN user_activity_logs AS a ON a.id = g.latest_id
         ORDER BY g.latest_id DESC LIMIT ? OFFSET ?',
        $lh_types . 'ii',
        array_merge($lh_values, [50, $lh_people_offset])
    );

    $lh_event_where = $lh_where;
    $lh_event_types = $lh_types;
    $lh_event_values = $lh_values;

    if ($lh_user > 0) {
        $lh_event_where .= ' AND user_id = ?';
        $lh_event_types .= 'i';
        $lh_event_values[] = $lh_user;
    }

    $lh_event_count = tp_query(
        $conn,
        'SELECT COUNT(*) AS total FROM user_activity_logs'
            . $lh_event_where,
        $lh_event_types,
        $lh_event_values
    );
    $lh_event_total = (int) $lh_event_count->fetch_assoc()['total'];
    $lh_event_count->free();

    $lh_event_pages = max(1, (int) ceil($lh_event_total / 50));
    $lh_event_page = max(
        1,
        min($lh_event_pages, (int) tp_get('lh_activity_page', '1'))
    );
    $lh_event_offset = ($lh_event_page - 1) * 50;

    $lh_events = tp_query(
        $conn,
        'SELECT * FROM user_activity_logs'
            . $lh_event_where
            . ' ORDER BY id DESC LIMIT ? OFFSET ?',
        $lh_event_types . 'ii',
        array_merge($lh_event_values, [50, $lh_event_offset])
    );

    $lh_roles = [
        'admin' => 'แอดมิน',
        'executive' => 'ผู้บริหาร',
        'technician' => 'เจ้าหน้าที่',
    ];
    $lh_actions = mbsActivityLabels();
?>

<?php if ($lh_user === 0): ?>
<div class="modern-card overflow-hidden">
    <div class="p-6 border-b border-slate-100">
        <h3 class="text-lg font-extrabold text-slate-800">
            <i class="fas fa-users text-indigo-500 mr-2"></i>
            สรุปการใช้งานรายบัญชี
        </h3>
        <p class="mt-1 text-sm text-slate-500">
            นับจากประวัติกิจกรรมใหม่ตั้งแต่เปิดใช้ส่วนนี้
            รวมการเปิดหน้า กดปุ่ม ค้นหา เปลี่ยนตัวกรอง ส่งคำขอ และรายการสำเร็จที่ระบบบันทึกไว้
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e]">
                <tr>
                    <th style="color:#854d0e" class="p-4">บัญชีผู้ใช้</th>
                    <th style="color:#854d0e" class="p-4">บทบาทขณะทำรายการ</th>
                    <th style="color:#854d0e" class="p-4 text-center">เข้าสู่ระบบสำเร็จ</th>
                    <th style="color:#854d0e" class="p-4 text-center">กิจกรรมทั้งหมด</th>
                    <th style="color:#854d0e" class="p-4">กิจกรรมล่าสุด</th>
                    <th style="color:#854d0e" class="p-4 text-right">รายละเอียด</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($lh_people_result->num_rows === 0): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400">
                            ยังไม่มีประวัติกิจกรรมใหม่ของกลุ่มนี้
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($lh_person = $lh_people_result->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-4">
                            <p class="font-bold text-slate-800">
                                <?= tp_text(
                                    $lh_person['full_name'] !== ''
                                        ? $lh_person['full_name']
                                        : $lh_person['username']
                                ) ?>
                            </p>
                            <p class="text-xs text-slate-400 mt-1">
                                <?= tp_text($lh_person['username']) ?>
                            </p>
                        </td>
                        <td class="p-4">
                            <?= tp_text($lh_roles[$lh_person['role']] ?? '') ?>
                        </td>
                        <td class="p-4 text-center font-bold text-indigo-600">
                            <?= number_format((int) $lh_person['login_count']) ?>
                            ครั้ง
                        </td>
                        <td class="p-4 text-center font-bold text-indigo-600"><?= number_format((int)$lh_person['event_count']); ?> ครั้ง</td>
                        <td class="p-4 text-slate-500">
                            <div><?= tp_text((date('d/m/', strtotime($lh_person['latest_time'])) . ((int)date('Y', strtotime($lh_person['latest_time'])) + 543))) ?></div>
                            <div class="text-[11px] text-blue-600 font-bold mt-0.5"><?= tp_text(date('H:i', strtotime($lh_person['latest_time']))) ?></div>
                        </td>
                        <td class="p-4 text-right">
                            <a
                                href="<?= tp_text(mbsActivityUrl([
                                    'pg_logs_status' => $lh_person['role'],
                                    'pg_logs_q' => $lh_person['user_id'],
                                    'lh_activity_page' => null,
                                ])) ?>"
                                class="inline-block rounded-xl bg-indigo-50 px-4 py-2 font-bold text-indigo-600 hover:bg-indigo-100"
                            >
                                ดูกิจกรรม
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php mbsActivityPager(
        $lh_people_page,
        $lh_people_pages,
        'lh_people_page',
        $lh_people_total
    ); ?>
</div>
<?php endif; ?>

<?php if ($lh_user > 0): ?>
<div class="modern-card overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-extrabold text-slate-800">
                <i class="fas fa-list-alt text-violet-500 mr-2"></i>
                กิจกรรมที่บันทึก
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                <?= number_format($lh_event_total) ?> รายการ
                · หน้าละ 50 รายการ
            </p>
        </div>

        <?php if ($lh_user > 0): ?>
            <a
                href="<?= tp_text(mbsActivityUrl([
                    'pg_logs_q' => null,
                    'lh_activity_page' => null,
                ])) ?>"
                class="text-sm font-bold text-indigo-600 hover:underline"
            >
                ดูทุกบัญชีในกลุ่มนี้
            </a>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm min-w-[650px]">
            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e]">
                <tr>
                    <th style="color:#854d0e" class="p-4 whitespace-nowrap">วัน / เวลา</th>
                    <th style="color:#854d0e" class="p-4">ผู้ทำรายการ</th>
                    <th style="color:#854d0e" class="p-4">กิจกรรม</th>
                    <th style="color:#854d0e" class="p-4">รายละเอียด</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($lh_events->num_rows === 0): ?>
                    <tr>
                        <td colspan="4" class="p-8 text-center text-slate-400">
                            ยังไม่มีกิจกรรมที่บันทึกตามเงื่อนไขนี้
                        </td>
                    </tr>
                <?php endif; ?>

                <?php while ($lh_event = $lh_events->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="p-4 whitespace-nowrap text-slate-500">
                            <div><?= tp_text((date('d/m/', strtotime($lh_event['created_at'])) . ((int)date('Y', strtotime($lh_event['created_at'])) + 543))) ?></div>
                            <div class="text-[11px] text-blue-600 font-bold mt-0.5"><?= tp_text(date('H:i', strtotime($lh_event['created_at']))) ?></div>
                        </td>
                        <td class="p-4">
                            <p class="font-bold text-slate-800">
                                <?= tp_text(
                                    $lh_event['full_name'] !== ''
                                        ? $lh_event['full_name']
                                        : $lh_event['username']
                                ) ?>
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                <?= tp_text($lh_roles[$lh_event['role']] ?? '') ?>
                                · <?= tp_text($lh_event['username']) ?>
                            </p>
                        </td>
                        <td class="p-4 font-semibold text-indigo-600">
                            <?= tp_text(
                                $lh_actions[$lh_event['action_code']]
                                    ?? $lh_event['action_code']
                            ) ?>
                        </td>
                        <td class="p-4 text-slate-600 break-words">
                            <?= tp_text($lh_event['summary']) ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php mbsActivityPager(
        $lh_event_page,
        $lh_event_pages,
        'lh_activity_page',
        $lh_event_total
    ); ?>
</div>
<?php endif; ?>

<?php
    $lh_people_result->free();
    $lh_events->free();
} catch (Throwable $error) {
    error_log('User activity panel load failed');
?>
    <div class="modern-card p-6 text-sm text-rose-600">
        โหลดประวัติกิจกรรมไม่สำเร็จ กรุณาตรวจว่าติดตั้งตารางประวัติแล้ว
    </div>
<?php
}

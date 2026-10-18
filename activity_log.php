<?php

function mbsActivityLabels()
{
    return [
        'VIEW_DASHBOARD' => 'เปิดหน้า/เปลี่ยนมุมมอง Dashboard',
        'UI_CLICK' => 'กดเมนู/ปุ่ม/เปิดรายละเอียด',
        'UI_FILTER' => 'เปลี่ยนตัวกรอง/แก้ไขช่องข้อมูล',
        'UI_SEARCH' => 'ค้นหาข้อมูล',
        'UI_SUBMIT' => 'ส่งฟอร์ม/คำขอ (ยังไม่ใช่ผลสำเร็จ)',
        'TRASH_MOVE' => 'ย้ายข้อมูลเข้าถังขยะสำเร็จ',
        'TRASH_RESTORE' => 'กู้คืนข้อมูลสำเร็จ',
        'TRASH_PURGE' => 'ลบข้อมูลถาวรสำเร็จ',
        'SAVE_ASSET' => 'บันทึกข้อมูลครุภัณฑ์สำเร็จ',
        'LOGIN' => 'เข้าสู่ระบบสำเร็จ',
        'SAVE_REPAIR' => 'บันทึกข้อมูลใบงาน',
        'CLOSE_REPAIR' => 'บันทึกสถานะซ่อมเสร็จ',
        'OPEN_REPORT' => 'เปิดหน้ารายงาน',
        'EXPORT_EXCEL' => 'สร้างไฟล์ Excel',
        'CREATE_TECHNICIAN' => 'เพิ่มข้อมูลเจ้าหน้าที่',
        'SAVE_TECHNICIAN' => 'บันทึกข้อมูลเจ้าหน้าที่',
        'CREATE_ACCOUNT' => 'สร้างบัญชีผู้ใช้',
        'SAVE_ACCOUNT' => 'บันทึกข้อมูลบัญชี',
        'RESET_ACCOUNT' => 'บันทึกบัญชีพร้อมตั้งรหัสผ่านชั่วคราว',
        'LINK_ACCOUNT' => 'เชื่อมบัญชีกับเจ้าหน้าที่',
        'ARCHIVE_TECHNICIAN' => 'นำเจ้าหน้าที่ออกจาก Dashboard',
        'PROFILE_PHOTO' => 'จัดการรูปโปรไฟล์',
    ];
}

/*
 * เรียกจาก PHP หลังตรวจสิทธิ์และทำรายการสำเร็จแล้วเท่านั้น
 * ตัวตนผู้ทำรายการอ่านจาก Session ไม่รับจากฟอร์ม
 * ไม่เก็บรหัสผ่าน OTP Token หรือเนื้อหาคำขอทั้งหมด
 */
function mbsActivityLog(
    $conn,
    $action_code,
    $target_type,
    $target_id,
    $summary
) {
    $statement = null;

    try {
        $user_id = (int) ($_SESSION['user_id'] ?? 0);
        $role = strtolower((string) ($_SESSION['role'] ?? ''));

        if ($user_id < 1
            || !in_array($role, ['admin', 'executive', 'technician'], true)
            || !array_key_exists($action_code, mbsActivityLabels())) {
            throw new RuntimeException('Invalid activity actor or action');
        }

        $username = (string) ($_SESSION['username'] ?? '');
        $full_name = (string) ($_SESSION['full_name'] ?? '');
        $target_id = (int) $target_id > 0 ? (int) $target_id : null;

        $statement = $conn->prepare(
            'INSERT INTO user_activity_logs
                (user_id, username, full_name, role,
                 action_code, target_type, target_id, summary)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$statement
            || !$statement->bind_param(
                'isssssis',
                $user_id,
                $username,
                $full_name,
                $role,
                $action_code,
                $target_type,
                $target_id,
                $summary
            )
            || !$statement->execute()
            || $statement->affected_rows !== 1) {
            throw new RuntimeException('Activity write failed');
        }

        return true;
    } catch (Throwable $error) {
        // ประวัติล้มเหลวต้องไม่ทำให้รายการเดิมถูกทำซ้ำ
        error_log('User activity history write failed');
        return false;
    } finally {
        if ($statement instanceof mysqli_stmt) {
            try {
                $statement->close();
            } catch (Throwable $error) {
                error_log('User activity history cleanup failed');
            }
        }
    }
}

function mbsActivityUrl(array $changes = [])
{
    $query = ['tab' => 'login_logs'];

    foreach ([
        'pg_logs_status',
        'pg_logs_q',
        'pg_logs_page',
        'lh_people_page',
        'lh_activity_page',
        'lh_q', 'lh_day', 'lh_month', 'lh_year',
    ] as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            $query[$key] = $_GET[$key];
        }
    }

    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = (string) $value;
        }
    }

    return '?' . http_build_query(
        $query,
        '',
        '&',
        PHP_QUERY_RFC3986
    );
}

function mbsActivityPager($page, $pages, $key, $total = 0)
{
    $first = $total ? ($page - 1) * 50 + 1 : 0;
    $last = min($page * 50, $total);
    echo '<div class="tp-footer"><p class="tp-summary">แสดงรายการ <strong>' . number_format($first) . '–' . number_format($last)
        . '</strong> จากทั้งหมด <strong>' . number_format($total) . '</strong> รายการ · หน้า ' . number_format($page) . ' / ' . number_format($pages)
        . '</p><nav class="tp-nav" aria-label="แบ่งหน้ากิจกรรม">';
    $link = static function ($number, $label) use ($page, $pages, $key) {
        if ($number < 1 || $number > $pages) echo '<span class="tp-disabled" aria-disabled="true">' . $label . '</span>';
        elseif ($number === $page) echo '<span class="tp-link tp-current" aria-current="page">' . $label . '</span>';
        else echo '<a class="tp-link" href="' . tp_text(mbsActivityUrl([$key => $number])) . '">' . $label . '</a>';
    };
    $link($page - 1, '‹ ก่อนหน้า');
    $numbers = array_unique(array_merge([1], range(max(1, $page - 1), min($pages, $page + 1)), [$pages]));
    sort($numbers); $previous = 0;
    foreach ($numbers as $number) {
        if ($previous && $number > $previous + 1) echo '<span class="tp-dots">…</span>';
        $link($number, number_format($number)); $previous = $number;
    }
    $link($page + 1, 'ถัดไป ›');
    echo '</nav></div>';
}

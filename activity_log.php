<?php

function mbsActivityLabels()
{
    return [
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

function mbsActivityPager($page, $pages, $key)
{
    echo '<div class="flex flex-wrap items-center justify-end gap-3 p-4 border-t border-slate-100 text-sm">';

    if ($page > 1) {
        echo '<a class="rounded-xl border border-slate-200 px-4 py-2 font-bold text-indigo-600 hover:bg-indigo-50" href="'
            . tp_text(mbsActivityUrl([$key => $page - 1]))
            . '">ก่อนหน้า</a>';
    }

    echo '<span class="font-semibold text-slate-500">หน้า '
        . number_format($page)
        . ' / '
        . number_format($pages)
        . '</span>';

    if ($page < $pages) {
        echo '<a class="rounded-xl bg-indigo-600 px-4 py-2 font-bold text-white hover:bg-indigo-700" href="'
            . tp_text(mbsActivityUrl([$key => $page + 1]))
            . '">ถัดไป</a>';
    }

    echo '</div>';
}
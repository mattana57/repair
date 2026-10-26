<?php
// ✨ 1. เรียกใช้ auth_guard.php ก่อนอ่านข้อมูลหรือรับ POST เสมอ
// (ด่านนี้จัดการ: ตรวจว่าบัญชียังมีอยู่ เปิดใช้งาน, auth_version, อายุ Session, และเตะไปหน้าเปลี่ยนรหัสชั่วคราว)
require_once 'auth_guard.php';

// ✨ 2. ตรวจสอบว่าเป็นบทบาท Technician หรือไม่ (เผื่อคนแปลกหน้า หรือแอดมินพิมพ์ URL เข้ามา)
if (strtolower($_SESSION['role']) !== 'technician') {
    header("Location: dashboard.php");
    exit();
}

// ✨ 3. ตรวจว่ารหัสช่างตรงกับ Session หรือไม่ (ค่านี้ได้มาจาก auth_guard.php)
$tech_id_session = $_SESSION['technician_id'] ?? 0;
if (empty($tech_id_session)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';

// ✨ 4. ใช้การตรวจบัญชีล่าสุด: ตรวจว่าบัญชีเชื่อมกับช่างที่มีอยู่จริงในระบบหรือไม่
// (ป้องกันกรณีตาราง users ยังอยู่ แต่ชื่อช่างในตาราง technicians ถูกแอดมินลบทิ้งไปแล้ว)
$stmt_tech = $conn->prepare("SELECT id, approval_status, full_name, english_name FROM technicians WHERE id = ? AND deleted_at IS NULL");
$stmt_tech->bind_param("i", $tech_id_session);
$stmt_tech->execute();
$res_tech = $stmt_tech->get_result();

if ($res_tech->num_rows === 0) {
    // ช่างคนนี้ไม่มีอยู่จริงแล้ว -> ใช้ Session เดิมไม่ได้
    $stmt_tech->close();
    session_unset();
    session_destroy();
    header("Location: login.php?error=tech_not_found");
    exit();
}

$tech_data = $res_tech->fetch_assoc();
$stmt_tech->close();

// ✨ 5. ตรวจสอบสถานะการเชื่อมต่อไลน์ (เผื่อแอดมินกด "ยกเลิกผูกบัญชี")
// เจ้าหน้าที่ทุกบัญชีต้องผูก LINE สำเร็จก่อนเข้า Dashboard
if ($tech_data['approval_status'] !== 'อนุมัติแล้ว') {
    session_unset();
    session_destroy();
    header("Location: login.php?error=tech_unlinked");
    exit();
}

define('MBS_DASHBOARD_MEDIA', true);
require_once __DIR__ . '/dashboard_media_icons.php';

define('MBS_DASHBOARD_ACTIVITY', true);
require_once __DIR__ . '/dashboard_activity.php';
require_once __DIR__.'/dashboard_features.php';
mbs_feature_dispatch($conn);

// ====================================================================
// ✅ สามารถดึงข้อมูลได้อย่างปลอดภัย 100%
// ====================================================================

// ✨ ปิดช่องทางบันทึกหมายเหตุที่ข้ามหน้าใบงานโดยสมบูรณ์
// ลบการประมวลผล POST action=update_remark ทิ้ง เพื่อบังคับให้ช่างเข้าไปอัปเดตงานผ่านหน้า update_repair.php เท่านั้น

$user_id = $_SESSION['user_id'];
$full_name = $tech_data['full_name']; // ดึงชื่อล่าสุดจากตาราง technicians โดยตรง

// รูปส่วนตัวแยกจากรูปข้อมูลเจ้าหน้าที่ที่แอดมินดูแล
define('MBS_TECH_PERSONAL_PROFILE', true);
require __DIR__ . '/technician_profile_avatar.php';

$tech_profile_english = (string) ($tech_data['english_name'] ?? '');

// ใช้ตัวตนที่ Guard ตรวจแล้ว ไม่เปลี่ยนรหัสช่างของคำขอนี้จากการอ่านซ้ำ
$tech_id = (int) $tech_id_session;

require_once __DIR__.'/line_job_sync.php';
if (empty($_SESSION['job_action_csrf'])) { $_SESSION['job_action_csrf']=bin2hex(random_bytes(32)); }
$job_action_notice=$_SESSION['job_action_notice'] ?? '';unset($_SESSION['job_action_notice']);
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['job_action'])) {
    $csrf=$_POST['job_action_csrf'] ?? null;
    $jobId=filter_var($_POST['repair_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $action=$_POST['job_action'];
    if (!is_string($csrf) || !hash_equals($_SESSION['job_action_csrf'],$csrf) || !$jobId || !is_string($action) || !in_array($action,['accept','close'],true)) {
        http_response_code(400);exit('คำขอไม่ถูกต้อง กรุณาเปิดหน้ารายการใหม่');
    }
    try {
        $result=mbsSyncTransition($conn,(int)$jobId,$tech_id,$action);
        $_SESSION['job_action_notice']=$result['label'].($action==='accept'?' รับงานเรียบร้อยค่ะ':' ปิดงานเรียบร้อยค่ะ');
        mbsSyncDispatchSafe($conn,(int)$jobId);
    } catch(Throwable $e) {
        error_log('Web job action failed: '.get_class($e));
        $_SESSION['job_action_notice']='ยังทำรายการไม่ได้ค่ะ รายการอาจมีผู้รับแล้ว ปิดงานแล้ว หรือสิทธิ์เปลี่ยน กรุณาตรวจรายการล่าสุด';
    }
    header('Location: technician_home.php?tab=history');exit;
}


// สรุปเฉพาะคิวรอร่วมกันและงานตามรหัสเจ้าหน้าที่ ไม่รับรหัสจาก GET และไม่จับคู่ชื่อ
function techSummaryCounts($conn, $techId) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(status = 'รอรับเรื่อง'), 0) AS pending,
        COALESCE(SUM(status = 'กำลังดำเนินการ'), 0) AS in_progress,
        COALESCE(SUM(status = 'ซ่อมเสร็จแล้ว'), 0) AS completed
        FROM repairs WHERE ".mbs_active_repair_sql('repairs').mbs_overview_sql()."
        AND (status = 'รอรับเรื่อง' OR technician_id = ?)");
    if (!$stmt) throw new RuntimeException('โหลดสรุปสถานะใบงานไม่สำเร็จ');
    try {
        if (!$stmt->bind_param('i', $techId) || !$stmt->execute()) {
            throw new RuntimeException('โหลดสรุปสถานะใบงานไม่สำเร็จ');
        }
        $result = $stmt->get_result();
        if (!$result) throw new RuntimeException('โหลดสรุปสถานะใบงานไม่สำเร็จ');
        $counts = array_map('intval', $result->fetch_assoc());
        $result->free();
        return $counts;
    } finally {
        $stmt->close();
    }
}
// ผ่าน Guard/ตรวจผูก LINE แล้ว อ่านเฉพาะยอด ไม่แก้ไขข้อมูลใบงาน
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && isset($_GET['tech_summary_fragment']) && $_GET['tech_summary_fragment'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    try {
        echo json_encode(['summary_counts' => techSummaryCounts($conn, $tech_id)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        http_response_code(503);
        echo json_encode(['error' => 'โหลดสรุปสถานะใบงานไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}


// 3. ดึง LINE ID ของช่าง (เก็บไว้เผื่อมีเรียกใช้ส่วนอื่น)
$line_id = '';
if (!empty($tech_id)) {
    $res_l = $conn->query("SELECT line_user_id FROM technicians WHERE id = $tech_id");
    if ($res_l && $res_l->num_rows > 0) {
        $line_id = $res_l->fetch_assoc()['line_user_id'];
    }
}

// ใช้รหัสเจ้าหน้าที่เป็นหลัก
$safe_tech_id = intval($tech_id);
$where = "technician_id = $safe_tech_id";

// รองรับข้อมูลจากขั้นตอน LINE เดิมที่บันทึกชื่อ แต่ยังไม่มีรหัส
// อ่านเพื่อแสดงผลเท่านั้น ไม่ผูกรหัสหรือแก้ข้อมูลอัตโนมัติ
$legacy_staff_name = (string) $full_name;

if (trim($legacy_staff_name) !== '') {
    $escaped_staff_name = $conn->real_escape_string($legacy_staff_name);

    $where = "(
        technician_id = $safe_tech_id
        OR (
            (technician_id IS NULL OR technician_id = 0)
            AND BINARY technician_name = BINARY '$escaped_staff_name'
            AND (
                SELECT COUNT(*)
                FROM technicians AS legacy_staff
                WHERE legacy_staff.full_name = '$escaped_staff_name'
            ) = 1
        )
    )";
}

// 4. การ์ดใช้ยอดเดียวกับ endpoint ไม่รวมถังขยะและไม่อิงตัวกรองตาราง
$stats = techSummaryCounts($conn, $tech_id);

// 5. ดึงประวัติรายการแจ้งซ่อมทั้งหมด
$repairs = [];
$res_repairs = $conn->query("SELECT * FROM repairs WHERE $where ORDER BY created_at DESC");
if ($res_repairs) {
    while ($row = $res_repairs->fetch_assoc()) {
        $repairs[] = dm_enrich($conn, $row);
    }
}

// ดึงข้อมูลแผนกและตำแหน่งของช่างทั้งหมดเพื่อนำไปใช้ในตาราง
$tech_dept_map = [];
$tech_info_map = [];
$td_res = $conn->query("SELECT full_name, department, english_name, position FROM technicians WHERE deleted_at IS NULL");
if($td_res) {
    while($tr = $td_res->fetch_assoc()) {
        $tech_dept_map[$tr['full_name']] = !empty($tr['department']) ? $tr['department'] : 'ฝ่ายงานทั่วไป';
        $tech_info_map[$tr['full_name']] = [
            'th' => $tr['full_name'],
            'eng' => $tr['english_name'] ?? '',
            'pos' => $tr['position'] ?? ''
        ];
    }
}

// ฟังก์ชันจัดฟอร์แมตข้อมูลว่าให้เป็น -
function formatEmptyOrDash($val) {
    $val = trim((string)$val);
    if (empty($val) || $val === '-') return "<span class='text-rose-500 font-bold'>-</span>";
    if ($val === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
    return htmlspecialchars($val);
}

// ดึงข้อมูล Line Users มาแมปเพื่อแสดงชื่อจริง (เหมือนฝั่ง Admin)
$line_users_map = [];
$check_lu = $conn->query("SHOW TABLES LIKE 'line_users'");
if($check_lu && $check_lu->num_rows > 0) {
    $lu_res = $conn->query("SELECT line_display_name, real_name, phone_number FROM line_users");
    if($lu_res) {
        while($lu = $lu_res->fetch_assoc()) {
            if(!empty($lu['line_display_name']) && !empty($lu['real_name'])) {
                $line_users_map[$lu['line_display_name']] = $lu['real_name'];
            }
        }
    }
}
$line_users_map_json = json_encode($line_users_map, JSON_UNESCAPED_UNICODE);

// ดึงปีที่มีการซ่อม
$years_query = $conn->query("SELECT DISTINCT YEAR(created_at) as y FROM repairs WHERE created_at IS NOT NULL ORDER BY y DESC");
$available_years = [];
if($years_query && $years_query->num_rows > 0) {
    while($y_row = $years_query->fetch_assoc()) {
        if(!empty($y_row['y'])) $available_years[] = $y_row['y'];
    }
} else {
    $available_years[] = date('Y');
}
$thai_months = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
$current_month_name = $thai_months[date('n')];
$current_thai_year = date('Y') + 543;

// ส่งเฉพาะส่วนประวัติงานให้ช่องค้นหา
// มาถึงจุดนี้ได้หลังผ่าน Guard และตรวจเจ้าหน้าที่ตามเดิมแล้ว
if (isset($_GET['th_fragment'])
    && is_string($_GET['th_fragment'])
    && $_GET['th_fragment'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    if (isset($_GET['th_line_user_id'])) {
        if (!is_string($_GET['th_line_user_id']) || !preg_match('/\A.{1,200}\z/us', trim($_GET['th_line_user_id']))) {
            http_response_code(400);
            exit('รหัสผู้แจ้งไม่ถูกต้อง');
        }
        define('MBS_TECH_REPORTER_HISTORY', true);
    }
    define('MBS_TECH_HISTORY', true);
    require __DIR__ . '/technician_history_pagination.php';
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard | MBS Repair</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- ✨ เปลี่ยนมาใช้ชุดฟอนต์เดียวกับฝั่ง Admin (Plus Jakarta Sans, Kanit, Sarabun) ✨ -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ✨ นำ Style ธีมของ Admin มาใช้ 100% ✨ */
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; background-color: #f8fafc; color: #1e293b; }

        /* ส่วนใหม่ซ่อนในคอมพิวเตอร์และมือถือแนวนอน */
        .tech-mobile-menu,
        .tech-mobile-profile {
            display: none;
        }

        #tech-profile-viewer:not([open]) {
            display: none;
        }

    /* Use the existing hamburger menu on all screen sizes. */
    @media all {
            nav .tech-desktop-actions {
                display: none !important;
            }

            .tech-mobile-menu,
            .tech-mobile-profile {
                display: block;
                position: relative;
            }

            .tech-mobile-menu > summary,
            .tech-mobile-profile > summary {
                list-style: none;
                cursor: pointer;
            }

            .tech-mobile-menu > summary::-webkit-details-marker,
            .tech-mobile-profile > summary::-webkit-details-marker {
                display: none;
            }

            .tech-mobile-popover {
                position: absolute;
                top: calc(100% + 14px);
                right: 0;
                width: min(280px, calc(100vw - 32px));
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background: #fff;
                color: #1e293b;
                box-shadow: 0 16px 40px rgb(15 23 42 / 18%);
                padding: 16px;
                z-index: 50;
            }

            .tech-mobile-popover a {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px;
                border-radius: 12px;
                font-size: 14px;
                font-weight: 600;
            }

            .tech-mobile-popover a:hover {
                background: #f1f5f9;
            }

            .tech-mobile-photo {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid #fff;
            }

            .tech-mobile-photo-large {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid #c4b5fd;
            }

            .tech-avatar-actions {
                position: relative;
            }

            .tech-avatar-actions > summary {
                position: relative;
                display: block;
                width: 64px;
                height: 64px;
                border-radius: 50%;
                overflow: hidden;
                list-style: none;
                cursor: pointer;
            }

            .tech-avatar-actions > summary::-webkit-details-marker {
                display: none;
            }

            .tech-avatar-actions > summary::marker {
                content: "";
            }

            .tech-avatar-camera {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                height: 23px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 0 0 32px 32px;
                background: rgb(31 41 55 / 90%);
                color: #fff;
                font-size: 12px;
            }

            .tech-avatar-action-menu {
                position: absolute;
                top: calc(100% + 8px);
                left: 0;
                width: max-content;
                padding: 5px;
                border: 1px solid rgb(255 255 255 / 12%);
                border-radius: 12px;
                background: #272a33;
                box-shadow: 0 16px 36px rgb(15 23 42 / 25%);
                z-index: 70;
            }

            .tech-avatar-action-menu > button {
                display: flex;
                align-items: center;
                gap: 8px;
                width: auto;
                padding: 8px 10px;
                border: 0;
                border-radius: 8px;
                background: transparent;
                color: #fff;
                font: inherit;
                font-size: 14px;
                font-weight: 700;
                text-align: left;
                white-space: nowrap;
                cursor: pointer;
            }

            .tech-avatar-action-menu > button:hover,
            .tech-avatar-action-menu > button:focus-visible {
                background: rgb(255 255 255 / 10%);
            }

            #tech-profile-viewer[open] {
                position: fixed;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100vw;
                height: 100vh;
                height: 100dvh;
                max-width: none;
                max-height: none;
                margin: 0;
                padding: 70px 16px;
                border: 0;
                border-radius: 0;
                background: transparent;
                overflow: auto;
            }

            #tech-profile-viewer::backdrop {
                background: rgb(15 23 42 / 78%);
                backdrop-filter: blur(7px);
                -webkit-backdrop-filter: blur(7px);
            }

            #tech-profile-viewer .tech-profile-close {
                position: fixed;
                top: 16px;
                right: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                padding: 0;
                border: 1px solid rgb(255 255 255 / 30%);
                border-radius: 50%;
                background: rgb(255 255 255 / 12%);
                color: #fff;
                font-size: 21px;
                cursor: pointer;
                z-index: 1;
            }

            #tech-profile-viewer img {
                display: block;
                width: auto;
                height: auto;
                max-width: 100%;
                max-height: calc(100vh - 140px);
                max-height: calc(100dvh - 140px);
                object-fit: contain;
                border: 3px solid #fff;
                border-radius: 12px;
                box-shadow: 0 20px 50px rgb(0 0 0 / 30%);
            }

        }

        @media (max-width: 639px) and (orientation: portrait) {
            .tech-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .tech-stats-grid > .modern-card {
                min-width: 0;
                padding: 16px;
            }

            .tech-stats-grid > .modern-card > div {
                flex-direction: column-reverse;
                gap: 12px;
                margin-bottom: 0;
            }

            .tech-stats-grid .text-4xl {
                font-size: 28px;
                line-height: 1.2;
            }

            .tech-stats-grid .text-sm {
                font-size: 12px;
                line-height: 1.5;
                overflow-wrap: anywhere;
            }
        }

        /* ซ่อนหน้าต่างรูปนอกมือถือแนวตั้ง */
        @media (min-width: 640px), (orientation: landscape) {
            #tech-profile-viewer {
                display: none;
            }
        }
        .modern-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); border: 1px solid #f1f5f9; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 12px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 3px solid #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .badge-pending { background-color: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-progress { background-color: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background-color: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        /* ✨ บังคับตารางให้เลื่อนและพับเก็บในหน้าจอเล็กอย่างสวยงามเหมือน Admin ✨ */
        @media (min-width: 1024px) {
            table {
                width: 100% !important;
                min-width: 0 !important;
                table-layout: auto !important;
            }
            table th {
                padding: 0.85rem 3px !important;
                font-size: 9.5px !important;
                letter-spacing: 0px !important;
                white-space: nowrap !important;
                word-break: keep-all !important;
            }
            table th:first-child, table td:first-child { padding-left: 10px !important; }
            table th:last-child, table td:last-child { padding-right: 10px !important; }
            table td {
                padding: 0.75rem 3px !important;
                font-size: 11.5px !important;
                white-space: nowrap !important;
            }
        }
    </style>

    <style>
        body.tech-dashboard-ui {
            background: #f5f7fc;
            color: #17213a;
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* ส่วนท้ายเจ้าหน้าที่เต็มหน้าจอ อยู่นอกกรอบเนื้อหาที่จำกัดความกว้าง */
        body.tech-dashboard-ui > .mbs-project-footer {
            align-self: stretch;
            width: 100%;
            max-width: none;
            margin: 0;
            border-radius: 0;
        }

        .tech-dashboard-ui .tech-topbar {
            background: linear-gradient(
                110deg, #3730a3, #4f46e5 65%, #6366f1
            );
            border-bottom: 1px solid rgb(255 255 255 / 15%);
            box-shadow: 0 4px 18px rgb(49 46 129 / 12%);
        }

        .tech-dashboard-ui .tech-page-shell {
            max-width: 1320px;
            padding-top: 24px;
            padding-bottom: 32px;
        }

        .tech-dashboard-ui .modern-card {
            min-width: 0;
            border: 1px solid #e5eaf4;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgb(30 41 59 / 4%);
        }

        /* แบนเนอร์ข้อมูลเจ้าหน้าที่ */
        .tech-dashboard-ui .tech-welcome {
            padding: 24px;
            gap: 20px;
            margin-bottom: 20px;
            border: 1px solid #cfe7fa;
            border-radius: 24px;
            background: linear-gradient(
                115deg, #fff, #eff8ff 72%, #e0f4ff
            );
            color: #123a63;
            box-shadow: 0 6px 24px rgb(14 116 190 / 5%);
        }

        .tech-dashboard-ui .tech-welcome > div:first-child {
            min-width: 0;
            gap: 16px;
        }

        .tech-dashboard-ui .tech-welcome > div:first-child > div:first-child {
            width: 60px;
            height: 60px;
            border: 1px solid #b9defa;
            background: #e0f2fe;
            color: #0284c7;
            box-shadow: none;
        }

        .tech-dashboard-ui .tech-welcome > div:first-child > div:last-child {
            min-width: 0;
        }

        .tech-dashboard-ui .tech-welcome .drop-shadow-md {
            filter: none;
        }

        .tech-dashboard-ui .tech-welcome h1 {
            font-size: clamp(22px, 2.2vw, 28px);
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .tech-dashboard-ui .tech-welcome h2 {
            margin-top: 3px;
            color: #147bb5;
            font-size: 16px;
            line-height: 1.5;
        }

        .tech-dashboard-ui .tech-welcome p {
            margin-top: 4px;
            color: #778098;
            font-size: 12px;
            letter-spacing: 0;
        }

        .tech-dashboard-ui .tech-welcome > div:last-child {
            max-width: 100%;
            padding: 12px 16px;
            border: 1px solid #cce5f6;
            background: rgb(255 255 255 / 85%);
            color: #17658f;
            text-align: left;
        }

        .tech-dashboard-ui .tech-welcome > div:last-child > span:first-child {
            color: #718da4;
            letter-spacing: 0;
        }

        .tech-dashboard-ui .tech-welcome > div:last-child > span:last-child {
            overflow-wrap: anywhere;
        }

        .tech-dashboard-ui .tech-welcome .animate-pulse {
            animation: none;
        }

        /* การ์ดสถิติ */
        .tech-dashboard-ui .tech-stats-grid {
            gap: 16px;
            margin-bottom: clamp(36px, 4vw, 44px);
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card {
            --stat-color: #7c3aed;
            --stat-bg: #f3edff;
            padding: 20px;
            border-bottom-width: 1px;
            border-bottom-color: #e5eaf4;
            border-left: 4px solid var(--stat-color);
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card:nth-child(2) {
            --stat-color: #d49a16;
            --stat-bg: #fff7df;
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card:nth-child(3) {
            --stat-color: #4f65df;
            --stat-bg: #edf1ff;
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card:nth-child(4) {
            --stat-color: #059669;
            --stat-bg: #e8f8f1;
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card:hover {
            transform: none;
            border-left-color: var(--stat-color);
            box-shadow: 0 8px 24px rgb(49 46 129 / 10%);
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card > div {
            flex-direction: row;
            align-items: center;
            gap: 12px;
            margin-bottom: 0;
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card > div > div:first-child {
            flex: 1;
            min-width: 0;
        }

        .tech-dashboard-ui .tech-stats-grid > .modern-card > div > div:last-child {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 14px;
            background: var(--stat-bg);
            color: var(--stat-color);
            font-size: 19px;
            box-shadow: none;
        }

        .tech-dashboard-ui .tech-stats-grid .text-4xl {
            margin-bottom: 5px;
            color: #202942;
            font-size: 34px;
            line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }

        .tech-dashboard-ui .tech-stats-grid .text-sm {
            color: #758096;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        /* แท็บเลือกหน้า */
        .tech-dashboard-ui .tech-tabs {
            width: fit-content;
            max-width: 100%;
            gap: 4px;
            padding: 5px;
            margin-bottom: 20px;
            border: 1px solid #e2e7f2;
            border-radius: 16px;
            background: #eaeef7;
        }

        .tech-dashboard-ui .tech-tabs .capsule-btn {
            min-height: 42px;
            padding: 9px 24px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: transparent;
            color: #647089;
            font-size: 13px;
            box-shadow: none;
        }

        .tech-dashboard-ui .tech-tabs .capsule-btn.bg-indigo-600 {
            background: #fff;
            color: #4f46c9;
            border-color: #e2e5f5;
            box-shadow: 0 2px 7px rgb(49 46 129 / 8%);
        }

        /* การ์ดกราฟและตัวกรอง */
        .tech-dashboard-ui #tab-content-dash > .grid {
            gap: 20px;
            margin-top: 20px;
        }

        .tech-dashboard-ui .tech-chart-card {
            padding: 22px;
        }

        .tech-dashboard-ui .tech-chart-card > div:first-child {
            row-gap: 12px;
            margin-bottom: 20px;
        }

        .tech-dashboard-ui .tech-chart-card h3 {
            white-space: normal;
            overflow: visible;
            font-size: 16px;
            line-height: 1.5;
        }

        .tech-dashboard-ui .tech-chart-card p {
            white-space: normal;
            font-size: 12px;
            line-height: 1.5;
        }

        .tech-dashboard-ui .tech-chart-card > div:last-child {
            min-height: 240px;
            height: 240px;
            max-height: 240px;
        }

        .tech-dashboard-ui .tech-chart-card [id$="Container"] > div:first-child {
            min-height: 36px;
            border-color: #e3e7f2;
            background: #f8f9fd;
            box-shadow: none;
        }

        /* ตาราง */
        .tech-dashboard-ui .tech-recent-card {
            margin-top: 24px;
            margin-bottom: 24px;
        }

        .tech-dashboard-ui .tech-recent-card > div:first-child {
            padding: 20px 22px;
            gap: 12px;
        }

        .tech-dashboard-ui main table {
            width: 100% !important;
            table-layout: auto !important;
        }

        .tech-dashboard-ui .tech-recent-card table {
            min-width: 720px !important;
        }

        /* ตารางประวัติงาน: เลื่อนขึ้น–ลงและแสดงข้อมูลครบ */
        .tech-dashboard-ui #repairsMainCard .history-table-scroll {
            width: 100%;
            min-width: 0;
            min-height: 0;
            max-height: 70vh;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .tech-dashboard-ui #repairsMainCard #historyTableFull {
            width: 100% !important;
            min-width: 0 !important;
            table-layout: fixed !important;
            white-space: normal !important;
        }

        .tech-dashboard-ui #historyTableFull th,
        .tech-dashboard-ui #historyTableFull td {
            padding: 14px 8px !important;
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-break: normal !important;
            letter-spacing: 0 !important;
        }

        .tech-dashboard-ui #historyTableFull td > *,
        .tech-dashboard-ui #historyTableFull td div,
        .tech-dashboard-ui #historyTableFull td span {
            min-width: 0;
            max-width: 100%;
            white-space: normal !important;
            overflow-wrap: anywhere;
        }

        /* แสดงรายละเอียดเต็ม ไม่ตัดข้อความด้วยจุดไข่ปลา */
        .tech-dashboard-ui #historyTableFull .truncate {
            overflow: visible;
            text-overflow: clip;
            white-space: normal !important;
        }

        /* ให้ชื่อผู้แจ้งตัดบรรทัดได้ภายในช่อง */
        .tech-dashboard-ui #historyTableFull td:nth-child(4) > .flex > div:last-child {
            flex: 1;
            min-width: 0;
        }

        /* สัดส่วนคอลัมน์รวม 100% */
        .tech-dashboard-ui #historyTableFull th:nth-child(1) { width: 4%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(2) { width: 8%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(3) { width: 6%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(4) { width: 12%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(5) { width: 11%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(6) { width: 13%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(7) { width: 11%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(8) { width: 8%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(9) { width: 7%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(10) { width: 8%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(11) { width: 8%; }
        .tech-dashboard-ui #historyTableFull th:nth-child(12) { width: 4%; }

        .tech-dashboard-ui #historyTableFull td:last-child,
        .tech-dashboard-ui #historyTableFull th:last-child {
            padding-left: 3px !important;
            padding-right: 3px !important;
        }

        .tech-dashboard-ui #historyTableFull td:last-child a {
            max-width: 100%;
        }

        /* จอเล็ก: แสดงข้อมูลครบโดยเรียงลงแทนการบีบ 11 คอลัมน์ */
        @media (max-width: 1199px) {
            .tech-dashboard-ui #historyTableFull,
            .tech-dashboard-ui #historyTableFull tbody {
                display: block;
            }

            .tech-dashboard-ui #historyTableFull thead {
                display: none;
            }

            .tech-dashboard-ui #historyTableFull tbody tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0 18px;
                padding: 12px 16px;
                border-bottom: 1px solid #e2e8f0;
            }

            .tech-dashboard-ui #historyTableFull tbody td {
                display: block;
                width: auto !important;
                min-width: 0;
                padding: 12px 0 !important;
                text-align: left;
            }

            .tech-dashboard-ui #historyTableFull tbody td::before {
                display: block;
                margin-bottom: 6px;
                color: #64748b;
                font-size: 11px;
                font-weight: 600;
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(1)::before {
                content: "ID";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(2)::before {
                content: "วันที่ / เวลาแจ้ง";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(3)::before {
                content: "เลขที่ใบงาน";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(4)::before {
                content: "ผู้แจ้ง";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(5)::before {
                content: "อุปกรณ์ / รายละเอียด";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(6)::before {
                content: "ฝ่ายงาน / ตำแหน่ง";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(7)::before {
                content: "เจ้าหน้าที่";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(8)::before {
                content: "วันที่ / เวลารับงาน";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(9)::before {
                content: "สาเหตุ / หมายเหตุ";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(10)::before {
                content: "สถานะ";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(11)::before {
                content: "วันที่ / เวลาซ่อมเสร็จ";
            }

            .tech-dashboard-ui #historyTableFull td:nth-child(12)::before {
                content: "จัดการใบงาน";
            }

            .tech-dashboard-ui #historyTableFull td:last-child > .flex {
                justify-content: flex-start;
            }

            /* แถวแจ้งว่าไม่พบข้อมูล */
            .tech-dashboard-ui #historyTableFull td[colspan] {
                grid-column: 1 / -1;
                text-align: center;
            }

            .tech-dashboard-ui #historyTableFull td[colspan]::before {
                content: none;
            }
        }

        @media (max-width: 479px) {
            .tech-dashboard-ui #historyTableFull tbody tr {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .tech-dashboard-ui main table thead {
            border-bottom: 1px solid #fef08a;
            background: #fef9c3;
            color: #854d0e;
        }

        .tech-dashboard-ui main table th {
            padding: 14px 16px !important;
            font-size: 11px !important;
            font-weight: 600;
            letter-spacing: 0 !important;
        }

        .tech-dashboard-ui main table td {
            padding: 15px 16px !important;
            font-size: 13px !important;
            line-height: 1.6;
        }

        .tech-dashboard-ui main table tbody tr:hover {
            background: #f7f8fe;
        }

        .tech-dashboard-ui main :is(button, a, input, select, [tabindex]):focus-visible,
        .tech-dashboard-ui .tech-topbar :is(button, a, summary):focus-visible {
            outline: 3px solid #a5b4fc;
            outline-offset: 3px;
        }

        /* มือถือและแท็บเล็ตขนาดเล็ก */
        @media (max-width: 767px) {
            .tech-dashboard-ui .tech-page-shell {
                padding-top: 16px;
            }

            .tech-dashboard-ui .tech-welcome {
                padding: 20px;
                gap: 16px;
            }

            .tech-dashboard-ui .tech-welcome > div:last-child {
                align-self: flex-start;
            }
        }

        @media (max-width: 639px) {
            .tech-dashboard-ui .tech-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .tech-dashboard-ui .tech-stats-grid > .modern-card {
                padding: 14px 12px;
                border-radius: 16px;
            }

            .tech-dashboard-ui .tech-stats-grid > .modern-card > div {
                gap: 8px;
            }

            .tech-dashboard-ui .tech-stats-grid > .modern-card > div > div:last-child {
                width: 32px;
                height: 32px;
                border-radius: 10px;
                font-size: 15px;
            }

            .tech-dashboard-ui .tech-stats-grid .text-4xl {
                font-size: 28px;
            }

            .tech-dashboard-ui .tech-stats-grid .text-sm {
                font-size: 11px;
            }

            .tech-dashboard-ui .tech-tabs {
                display: flex;
                width: 100%;
            }

            .tech-dashboard-ui .tech-tabs .capsule-btn {
                flex: 1;
                padding: 9px 12px;
            }

            .tech-dashboard-ui .tech-chart-card {
                padding: 18px 16px;
            }

            .tech-dashboard-ui .tech-recent-card > div:first-child {
                padding: 18px 16px;
                align-items: flex-start;
            }
        }
    </style>
    <link rel="stylesheet" href="table-empty-state.css?v=20261018-1">
<script src="dashboard_media_icons.js?v=20261019-2"></script>

    <style>
    /* เปิดเมนูเหนือเนื้อหาทุกหน้า เฉพาะมือถือ/iPad */
    @media (max-width: 767px), (any-pointer: coarse) {
        .tech-dashboard-ui .tech-topbar:has(.tech-mobile-menu[open]) {z-index: 12000;}
        .tech-dashboard-ui .tech-mobile-popover {
            max-height: calc(100dvh - 96px);
            overflow-y: auto;
        }
    }
    </style>

    <style>
    @media (max-width: 767px), (any-pointer: coarse) {
        .tech-dashboard-ui .tech-status-chart-frame {
            display:block;
            flex:0 0 auto;
            position:relative;
            height:clamp(310px, 60vw, 370px);
            min-height:310px;
            max-height:none;
        }
        .tech-dashboard-ui .tech-status-chart-frame > canvas {
            display:block;
            width:100% !important;
            height:100% !important;
        }
    }
    </style>

    <style>
    /* โดนัทเฉพาะ iPad แนวนอน: พื้นที่กราฟเท่ากับกราฟด้านซ้าย */
    @media (min-width:768px) and (min-height:500px) and (any-pointer:coarse) and (orientation:landscape) {
        .tech-dashboard-ui .tech-status-chart-frame {
            height:240px;
            min-height:240px;
            max-height:240px;
        }
    }
    /* เมนูรูปไปด้านซ้ายบน iPad/คอม และมือถือแนวนอน */
    @media (min-width:768px), (orientation:landscape) {
        .tech-dashboard-ui .tech-avatar-action-menu {
            top:0;
            left:auto;
            right:calc(100% + 28px);
        }
        .tech-dashboard-ui .tech-mobile-popover:has(.tech-avatar-actions[open]) {
            overflow:visible;
            max-height:none;
        }
    }
    </style>
<link rel="stylesheet" href="repairs_calendar.css?v=20261025-1">
    <link rel="stylesheet" href="project_footer.css?v=20261019-2">
    <link rel="stylesheet" href="technician_layout.css?v=20261019-1">
    <style id="calendar-first-paint">
        /* ปฏิทินใช้ค่าฟิลด์เดิม แต่ไม่แสดงดรอปดาวน์เก่าระหว่างโหลด */
        [id$="-DayContainer"],
        [id$="-MonthContainer"],
        [id$="-YearContainer"],
        #monthDropdownContainer,
        #yearDropdownContainer,
        #repairsMainCard .th-filter-group > details:nth-of-type(-n+3) {
            display: none !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style id="tech-personal-avatar-ui">
        .tech-dashboard-ui .tech-avatar-action-menu { min-width:192px; padding:12px; border-radius:16px; }
        .tech-dashboard-ui .tech-avatar-action-menu > button { width:100%; gap:12px; padding:12px 8px; }
        .tech-dashboard-ui .tech-avatar-action-menu .tpa-delete { color:#fb7185; }
        .tech-dashboard-ui .tech-avatar-action-menu i { width:16px; text-align:center; }
        #avatarPreviewConfirmModal.tpa-preview { position:fixed; inset:0; width:100vw; height:100vh; height:100dvh; max-width:none; max-height:none; margin:0; padding:0; border:0; background:#0f0f0f; color:white; overflow:hidden; }
        #avatarPreviewConfirmModal.tpa-preview:not([open]) { display:none; }
        #avatarPreviewConfirmModal.tpa-preview[open] { display:flex; align-items:center; justify-content:center; }
        #avatarPreviewConfirmModal::backdrop { background:#0f0f0f; }
        #avatarPreviewConfirmModal #cropCircleMask { width:min(320px,calc(100vw - 64px),calc(100dvh - 200px)); height:auto; aspect-ratio:1; flex-shrink:0; }
        #avatarPreviewConfirmModal > div:last-child { max-height:calc(100dvh - 24px); max-width:calc(100vw - 24px); }
        @media(min-width:640px) { #avatarPreviewConfirmModal > div:last-child { max-height:none; max-width:none; } }
    </style>
<link rel="stylesheet" href="self_profile.css?v=20261020-1">
    <style id="tech-header-avatar-ui">
        .tech-header-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, .9);
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            cursor: pointer;
            padding: 0;
            user-select: none;
        }
        .tech-header-avatar:disabled { cursor: default; }
        .tech-header-avatar:focus-visible { outline: 3px solid #c7d2fe; outline-offset: 3px; }
        .tech-header-avatar > img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
    <style>
    /* Expand only the equipment chart on desktop computers. */
    @media (min-width: 1024px) and (hover: hover) and (pointer: fine) {
        .tech-dashboard-ui .tech-chart-card > .tech-equipment-chart-frame {
            flex: 1 1 auto;
            height: 280px;
            min-height: 280px;
            max-height: none;
        }
        .tech-dashboard-ui .tech-equipment-chart-frame > canvas {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
        }
    }
    </style>

<style>#equip-DayContainer,#equip-MonthContainer,#equip-YearContainer,#status-DayContainer,#status-MonthContainer,#status-YearContainer,#loc-DayContainer,#loc-MonthContainer,#loc-YearContainer,#tech-DayContainer,#tech-MonthContainer,#tech-YearContainer,#rating-DayContainer,#rating-MonthContainer,#rating-YearContainer,#reporter-DayContainer,#reporter-MonthContainer,#reporter-YearContainer,#rep-DayContainer,#rep-MonthContainer,#rep-YearContainer{display:none!important}</style>
<link rel="stylesheet" href="dashboard_features.css?v=20261025-1">
</head>
<body class="tech-dashboard-ui flex flex-col min-h-screen">

    <!-- Top Navbar -->
       <nav class="tech-topbar bg-indigo-600 text-white shadow-md sticky top-0 z-40 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl">
                        <i class="fas fa-tools text-xl"></i>
                    </div>
                    <div>
                        <span class="font-bold text-lg tracking-wide">MBS <span class="font-light">Technician</span></span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" class="tech-header-avatar" title="ดูรูปโปรไฟล์" aria-label="ดูรูปโปรไฟล์เจ้าหน้าที่" onclick="document.getElementById('tech-profile-viewer')?.showModal()" <?= $tech_profile_image === '' ? 'disabled' : '' ?>>
                        <?php if ($tech_profile_image !== ''): ?>
                            <img src="<?= htmlspecialchars($tech_profile_image, ENT_QUOTES, 'UTF-8') ?>" alt="รูปโปรไฟล์เจ้าหน้าที่" draggable="false">
                        <?php else: ?>
                            <i class="fas fa-user" role="img" aria-label="รูปโปรไฟล์เจ้าหน้าที่"></i>
                        <?php endif; ?>
                    </button>
                    <details class="tech-mobile-menu">
                        <summary class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center"
                                 aria-label="เมนูเจ้าหน้าที่">
                            <i class="fas fa-bars text-xl" aria-hidden="true"></i>
                        </summary>

                        <div class="tech-mobile-popover">
                            <div class="flex items-center gap-3 pb-4 mb-2 border-b border-slate-100">
                                <div class="shrink-0">
                                    <?php if ($tech_profile_image !== ''): ?>
                                        <details class="tech-avatar-actions">
                                            <summary aria-label="เปิดเมนูรูปโปรไฟล์">
                                                <img
                                                    src="<?= htmlspecialchars($tech_profile_image, ENT_QUOTES, 'UTF-8') ?>"
                                                    alt="รูปโปรไฟล์เจ้าหน้าที่"
                                                    class="tech-mobile-photo-large"
                                                >
                                                <span class="tech-avatar-camera">
                                                    <i class="fas fa-camera" aria-hidden="true"></i>
                                                </span>
                                            </summary>

                                            <div class="tech-avatar-action-menu">
                                                <button type="button" onclick="this.closest('details').removeAttribute('open'); document.getElementById('tech-profile-viewer').showModal();"><i class="fas fa-eye" aria-hidden="true"></i><span>ดูรูปภาพ</span></button>
                                                <button type="button" onclick="tpaOpenPicker()"><i class="fas fa-camera" aria-hidden="true"></i><span>เปลี่ยนรูปภาพ</span></button>
                                                <?php if ($tech_has_personal_avatar): ?>
                                                <button type="button" class="tpa-delete" onclick="tpaDelete()"><i class="fas fa-trash-alt" aria-hidden="true"></i><span>ลบรูปภาพ</span></button>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="tech-mobile-photo-large bg-indigo-50 inline-flex items-center justify-center text-indigo-500">
                                            <i class="fas fa-user text-2xl" aria-hidden="true"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="min-w-0">
                                    <p class="font-bold text-sm break-words">
                                        <?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <?php if ($tech_profile_english !== ''): ?>
                                        <p class="text-xs text-slate-400 break-words mt-1">
                                            <?= htmlspecialchars($tech_profile_english, ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>

                                    <span class="inline-block mt-2 rounded-lg bg-violet-50 text-violet-600 text-xs font-semibold px-2 py-1">
                                        เจ้าหน้าที่
                                    </span>
                                </div>
                            </div>

                            <button type="button" class="sp-personal-menu-button" data-self-profile-open>
    <span class="sp-menu-icon"><i class="fas fa-user-edit" aria-hidden="true"></i></span>
    <span class="sp-menu-copy"><strong>แก้ไขข้อมูลส่วนตัว</strong><small>ชื่อ, เบอร์โทร, ชื่อผู้ใช้</small></span>
    <i class="fas fa-chevron-right sp-menu-arrow" aria-hidden="true"></i>
</button>
                            <a href="verify_email_page.php">
                                <i class="fas fa-envelope text-indigo-500" aria-hidden="true"></i>
                                ยืนยันอีเมล
                            </a>
                            <a href="logout.php" class="text-rose-600">
                                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                                ออกจากระบบ
                            </a>
                        </div>
                    </details>

                    <!-- โปรไฟล์มือถือย้ายเข้าเมนูแฮมเบอร์เกอร์แล้ว -->

                    <div class="tech-desktop-actions flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-indigo-700/50 px-3 py-1.5 rounded-xl border border-indigo-500/30">
                        <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-sm">
                            <?= mb_substr($full_name, 0, 1) ?>
                        </div>
                        <span class="text-sm font-medium hidden sm:block"><?= htmlspecialchars($full_name) ?></span>
                    </div>
                    <a href="verify_email_page.php" class="bg-indigo-500 hover:bg-indigo-600 px-3.5 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-envelope"></i> ยืนยันอีเมล
                    </a>
                    <a href="logout.php" class="bg-rose-500 hover:bg-rose-600 px-3.5 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">ออกจากระบบ</span>
                    </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <?php if ($tech_profile_image !== ''): ?>
        <dialog id="tech-profile-viewer"
                aria-label="ดูรูปโปรไฟล์เจ้าหน้าที่">
            <button
                type="button"
                class="tech-profile-close"
                onclick="document.getElementById('tech-profile-viewer').close()"
                aria-label="ปิดรูปภาพ"
            >
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>

            <img
                src="<?= htmlspecialchars($tech_profile_image, ENT_QUOTES, 'UTF-8') ?>"
                alt="รูปโปรไฟล์ของ <?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?>"
            >
        </dialog>
    <?php endif; ?>

    <!-- Header & Tabs (ซ่อนไว้เพราะใช้แบบ Capsule แทน) -->
    <div class="bg-white border-b border-slate-200 shrink-0 hidden" id="headerTabs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex pt-4 pb-0 gap-2 overflow-x-auto">
                <button onclick="showTab('dash')" id="tab-dash" class="px-6 py-3 bg-indigo-600 text-white text-sm font-medium rounded-t-lg shadow-sm tab-btn">
                หน้าแรก
            </button>
            <button onclick="showTab('history')" id="tab-history" class="px-6 py-3 bg-white text-slate-500 hover:text-indigo-600 text-sm font-medium rounded-t-lg border-b-2 border-transparent hover:border-indigo-200 transition-colors tab-btn">
                ประวัติงาน
            </button>
            </div>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto w-full relative mbs-tech-footer-main">
        <div class="tech-page-shell max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php if (is_array($tech_profile_notice)): ?>
                <p role="status" class="rounded-xl border px-4 py-3 mb-4 <?= !empty($tech_profile_notice['ok']) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' ?>"><?= htmlspecialchars((string)$tech_profile_notice['text'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>


            <?php
            // ดึงข้อมูลตำแหน่งและฝ่ายงานเพื่อมาแสดงใน Banner
            $tech_position = 'ไม่ระบุตำแหน่ง';
            $tech_department = 'ไม่ระบุฝ่ายงาน';
            $tech_icon = 'fas fa-tools'; // ไอคอนเริ่มต้น

            if (!empty($full_name)) {
                if (isset($tech_info_map[$full_name]) && !empty($tech_info_map[$full_name]['pos'])) {
                    $tech_position = $tech_info_map[$full_name]['pos'];
                }
                if (isset($tech_dept_map[$full_name]) && !empty($tech_dept_map[$full_name])) {
                    $tech_department = $tech_dept_map[$full_name];
                }
            }

            // กำหนดไอคอนตามฝ่ายงานให้เหมือนฝั่งแอดมิน
            if (strpos($tech_department, 'เทคโนโลยีดิจิทัล') !== false) {
                $tech_icon = 'fas fa-laptop-code';
            } elseif (strpos($tech_department, 'ยานยนต์') !== false) {
                $tech_icon = 'fas fa-car';
            } elseif (strpos($tech_department, 'โสตทัศนูปกรณ์') !== false) {
                $tech_icon = 'fas fa-video';
            } elseif (strpos($tech_department, 'แม่บ้าน') !== false) {
                $tech_icon = 'fas fa-broom';
            }
            ?>
            <!-- Welcome Banner -->
            <div class="tech-welcome bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
                <div class="flex items-center gap-4 relative z-10">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-inner shrink-0">
                        <i class="<?= $tech_icon ?> text-2xl sm:text-3xl drop-shadow-md"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold mb-1 drop-shadow-md"><?= htmlspecialchars($full_name) ?> 👋</h1>
                        <h2 class="text-lg md:text-xl font-bold text-indigo-100 drop-shadow-md"><?= htmlspecialchars($tech_department) ?></h2>
                        <p class="text-xs md:text-sm font-medium text-indigo-200 mt-0.5 tracking-wider">ทีมเจ้าหน้าที่ผู้รับผิดชอบประจำฝ่าย</p>
                    </div>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center relative z-10 shrink-0">
                    <span class="block text-[10px] uppercase tracking-wider text-indigo-200 mb-1">ตำแหน่งงาน</span>
                    <span class="flex items-center gap-2 font-bold text-sm"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> <?= htmlspecialchars($tech_position) ?></span>
                </div>
            </div>

            <!-- Stats Cards Grid -->
            <div class="tech-stats-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="modern-card p-6 border-b-4 border-b-violet-500 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'all', true)">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1" id="techCount-total"><?= $stats['total'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">งานที่รับผิดชอบทั้งหมด</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-violet-50 border border-violet-100 shadow-inner flex items-center justify-center text-violet-600 text-xl group-hover:bg-violet-600 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>
                <div class="modern-card p-6 border-b-4 border-b-amber-400 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'รอรับเรื่อง', true)">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1" id="techCount-pending"><?= $stats['pending'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">รอรับเรื่อง</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-[#fef3c7]/70 border border-amber-200/70 shadow-inner flex items-center justify-center text-[#d97706] text-xl group-hover:bg-amber-500 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="modern-card p-6 border-b-4 border-b-indigo-500 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'กำลังดำเนินการ', true)">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1" id="techCount-in_progress"><?= $stats['in_progress'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">กำลังดำเนินการ</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-[#e0e7ff]/70 border border-indigo-200/70 shadow-inner flex items-center justify-center text-[#4f46e5] text-xl group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                </div>
                <div class="modern-card p-6 border-b-4 border-b-emerald-500 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'ซ่อมเสร็จแล้ว', true)">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1" id="techCount-completed"><?= $stats['completed'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">ซ่อมเสร็จแล้ว</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-[#d1fae5]/70 border border-emerald-200/70 shadow-inner flex items-center justify-center text-[#059669] text-xl group-hover:bg-emerald-500 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ปุ่มเมนูแท็บแบบ Capsule -->
            <div class="tech-tabs flex items-center gap-2.5 mb-8">
                <button onclick="showTab('dash')" id="capsule-dash" class="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-full border border-indigo-600 shadow-md shadow-indigo-200 transition-colors cursor-pointer capsule-btn">
                    หน้าแรก
                </button>
                <button onclick="showTab('history')" id="capsule-history" class="px-6 py-2 bg-white text-slate-600 text-sm font-bold rounded-full shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors cursor-pointer capsule-btn">
                    ประวัติงาน
                </button>
            </div>

            <!-- ========================================== -->
            <!-- ส่วนที่ 1: Dashboard Overview (id="tab-content-dash") -->
            <!-- ========================================== -->
            <div id="tab-content-dash" class="<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'history') ? 'hidden ' : ''; ?>space-y-8 animate-fade-in">
                <!-- กราฟแถวที่ 1 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="tech-chart-card modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start sm:items-center gap-2 mb-4 w-full flex-col sm:flex-row flex-wrap">
                            <div class="flex-1 min-w-0 pr-2">
                                <h3 class="font-extrabold text-slate-800 text-lg truncate">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">สถิติอุปกรณ์ที่คุณได้รับมอบหมาย</p>
                            </div>
                            <div class="flex items-center justify-start sm:justify-end gap-2 shrink-0">
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="equip-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'equip-Month', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'equip-Month')">
                                        <span id="equip-MonthText" class="truncate"><?php echo $current_month_name; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="equip-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกเดือน" onclick="selectChartDropdown('equip-Month', 'all', 'ทุกเดือน', renderAllCharts)">ทุกเดือน</div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('equip-Month', '{$num_pad}', '{$name}', renderAllCharts)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="equipMonth" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('m'); ?>">
                                </div>
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="equip-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'equip-Year', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'equip-Year')">
                                        <span id="equip-YearText" class="truncate"><?php echo $current_thai_year; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="equip-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกปี" onclick="selectChartDropdown('equip-Year', 'all', 'ทุกปี', renderAllCharts)">ทุกปี</div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='{$thai_y}' onclick=\"selectChartDropdown('equip-Year', '{$y}', '{$thai_y}', renderAllCharts)\">{$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="equipYear" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="tech-equipment-chart-frame flex-1 relative w-full h-[280px]">
                            <canvas id="eqChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start sm:items-center gap-2 mb-4 w-full flex-col sm:flex-row flex-wrap">
                            <div class="flex-1 min-w-0 pr-2">
                                <h3 class="font-extrabold text-slate-800 text-lg truncate">สัดส่วนสถานะการดำเนินงาน</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">สถานะงานซ่อมทั้งหมดของคุณ</p>
                            </div>
                            <div class="flex items-center justify-start sm:justify-end gap-2 shrink-0">
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="status-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'status-Month', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'status-Month')">
                                        <span id="status-MonthText" class="truncate"><?php echo $current_month_name; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="status-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกเดือน" onclick="selectChartDropdown('status-Month', 'all', 'ทุกเดือน', renderAllCharts)">ทุกเดือน</div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('status-Month', '{$num_pad}', '{$name}', renderAllCharts)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="statusMonth" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('m'); ?>">
                                </div>
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="status-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'status-Year', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'status-Year')">
                                        <span id="status-YearText" class="truncate"><?php echo $current_thai_year; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="status-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกปี" onclick="selectChartDropdown('status-Year', 'all', 'ทุกปี', renderAllCharts)">ทุกปี</div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='{$thai_y}' onclick=\"selectChartDropdown('status-Year', '{$y}', '{$thai_y}', renderAllCharts)\">{$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="statusYear" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="tech-status-chart-frame flex-1 relative w-full h-[280px] flex justify-center items-center">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- กราฟแถวที่ 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start sm:items-center gap-2 mb-4 w-full flex-col sm:flex-row flex-wrap">
                            <div class="flex-1 min-w-0 pr-2">
                                <h3 class="font-extrabold text-slate-800 text-lg truncate">สถานที่เกิดปัญหาบ่อยที่สุด</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">ห้องหรืออาคารที่คุณไปซ่อมบ่อยๆ</p>
                            </div>
                            <div class="flex items-center justify-start sm:justify-end gap-2 shrink-0">
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="loc-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'loc-Month', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'loc-Month')">
                                        <span id="loc-MonthText" class="truncate"><?php echo $current_month_name; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="loc-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกเดือน" onclick="selectChartDropdown('loc-Month', 'all', 'ทุกเดือน', renderAllCharts)">ทุกเดือน</div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('loc-Month', '{$num_pad}', '{$name}', renderAllCharts)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="locMonth" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('m'); ?>">
                                </div>
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="loc-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'loc-Year', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'loc-Year')">
                                        <span id="loc-YearText" class="truncate"><?php echo $current_thai_year; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="loc-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกปี" onclick="selectChartDropdown('loc-Year', 'all', 'ทุกปี', renderAllCharts)">ทุกปี</div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='{$thai_y}' onclick=\"selectChartDropdown('loc-Year', '{$y}', '{$thai_y}', renderAllCharts)\">{$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="locYear" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="relative w-full h-[280px]"> 
                            <canvas id="locChart"></canvas>
                        </div>
                    </div>

                    <div class="modern-card overflow-hidden flex flex-col h-full">
                        <div class="p-4 md:p-5 border-b border-slate-100 flex justify-between items-start sm:items-center shrink-0 gap-2 w-full flex-col sm:flex-row flex-wrap">
                            <div class="flex-1 min-w-0 pr-2">
                                <h3 class="font-extrabold text-slate-800 text-lg truncate">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">รายชื่อผู้ใช้งานที่คุณให้บริการบ่อย</p>
                            </div>
                            <div class="flex items-center justify-start sm:justify-end gap-2 shrink-0">
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="rep-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'rep-Month', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'rep-Month')">
                                        <span id="rep-MonthText" class="truncate"><?php echo $current_month_name; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="rep-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกเดือน" onclick="selectChartDropdown('rep-Month', 'all', 'ทุกเดือน', renderAllCharts)">ทุกเดือน</div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('rep-Month', '{$num_pad}', '{$name}', renderAllCharts)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="repMonth" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('m'); ?>">
                                </div>
                                <div class="relative w-[90px] sm:w-[100px] md:w-24 outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="rep-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'rep-Year', renderAllCharts)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm" onclick="toggleChartDropdown(event, 'rep-Year')">
                                        <span id="rep-YearText" class="truncate"><?php echo $current_thai_year; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="rep-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                                        <div class="chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกปี" onclick="selectChartDropdown('rep-Year', 'all', 'ทุกปี', renderAllCharts)">ทุกปี</div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='{$thai_y}' onclick=\"selectChartDropdown('rep-Year', '{$y}', '{$thai_y}', renderAllCharts)\">{$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="repYear" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="px-4 md:px-5 py-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row flex-wrap sm:justify-between items-start sm:items-center shrink-0 z-10 shadow-sm gap-3">
                            <div class="flex flex-wrap items-center justify-between sm:justify-start w-full sm:w-auto gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mr-1">จัดอันดับ:</span>
                                    <div class="flex flex-wrap items-center gap-1.5" id="techReportersFilterContainer">
                                        <button id="btnTechReporterTop3" onclick="setTechReporterLimit(3)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 3</button>
                                        <button id="btnTechReporterTop5" onclick="setTechReporterLimit(5)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 5</button>
                                        <button id="btnTechReporterTop10" onclick="setTechReporterLimit(10)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 10</button>
                                        <button id="btnTechReporterTopAll_mobile" onclick="setTechReporterLimit('all')" class="sm:hidden px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700">ทั้งหมด</button>
                                    </div>
                                </div>
                            </div>
                            <button id="btnTechReporterTopAll" onclick="setTechReporterLimit('all')" class="hidden sm:block px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700 mt-1 sm:mt-0">ทั้งหมด</button>
                        </div>
                        <div class="p-0 overflow-y-auto flex-1 bg-white custom-scrollbar max-h-[380px]">
                            <div class="divide-y divide-slate-100" id="topReportersContainer">
                                <!-- Javascript จะวาดรายชื่อตรงนี้ -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ตารางหน้าแรก (สไตล์เดียวกับ Admin) -->
                <div class="tech-recent-card modern-card overflow-hidden flex flex-col mb-12">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">รายการงานซ่อมล่าสุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">งานซ่อมที่คุณรับผิดชอบ 5 รายการล่าสุด</p>
                        </div>
                        <button onclick="showTab('history')" class="flex items-center text-sm text-slate-600 font-bold hover:text-indigo-600 transition-colors group">
                            See All <i class="fas fa-arrow-right ml-2 text-xs text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-1"></i>
                        </button>
                    </div>
                    <div class="overflow-x-auto pb-4 custom-scrollbar table-wrapper-fix">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-[#fef9c3] text-[#854d0e] text-xs uppercase tracking-widest font-bold border-b border-[#fef08a]">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Date / Time</th>
                                    <th class="px-6 py-4">Ticket No.</th>
                                    <th class="px-6 py-4">Reporter</th>
                                    <th class="px-6 py-4">Equipment</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100" id="dashTableBody">
                                <!-- JS จะใส่ข้อมูล 5 งานล่าสุดที่นี่ -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- ส่วนที่ 2: ประวัติงานเต็มรูปแบบ (id="tab-content-history") -->
            <!-- ========================================== -->
            <?php
            define('MBS_TECH_HISTORY', true);
            require __DIR__ . '/technician_history_pagination.php';
            ?>

        </div>
    </main>
    <?php include __DIR__ . '/project_footer.php'; ?>

    <!-- Javascript สำหรับวาดกราฟและตาราง -->
    <script>
        const repairsData = <?php echo json_encode($repairs); ?>;
        const lineUsersMap = <?php echo $line_users_map_json; ?>;
        const techDeptMap = <?php echo json_encode($tech_dept_map); ?>;
        const techInfoMap = <?php echo json_encode($tech_info_map); ?>;
        
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Kanit', sans-serif";
        Chart.defaults.color = '#64748b';

        // ✨ การตั้งค่า Tooltip ให้เหมือน Admin แบบ 100% ✨
        const commonTooltipOptions = {
            backgroundColor: '#1e293b', // สีดำ/เทาเข้ม
            titleColor: '#ffffff',
            bodyColor: '#e2e8f0',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 10,
            displayColors: true,
            boxPadding: 4,
            usePointStyle: true, // ทำให้กล่องสีใน Tooltip เป็นวงกลม
            titleFont: { size: 13, weight: 'bold', family: "'Kanit', sans-serif" },
            bodyFont: { size: 12, family: "'Kanit', sans-serif" }
        };

        // ✨ ควบคุมปุ่มล้างค่าค้นหา
        function toggleClearBtn(inputId, btnId) {
            const input = document.getElementById(inputId);
            const btn = document.getElementById(btnId);
            if (!input || !btn) return;
            if (input.value.length > 0) {
                btn.classList.remove('hidden'); btn.classList.add('flex');
            } else {
                btn.classList.add('hidden'); btn.classList.remove('flex');
            }
        }

        function clearSearchInput(inputId, callbackFunction) {
            const input = document.getElementById(inputId);
            if (input) {
                input.value = '';
                toggleClearBtn(inputId, 'clearHistoryBtn');
                if (typeof callbackFunction === 'function') {
                    callbackFunction();
                }
            }
        }

        // ✨ ระบบ Custom Dropdown (เดือน, ปี, สถานะ) เหมือน Admin เป๊ะ 100% ✨
        let currentChartFocus = -1;
        let activeChartListId = '';

        function selectChartDropdown(idPrefix, val, display, renderCallback) {
            const list = document.getElementById(idPrefix + 'List');
            const actualInputId = idPrefix.replace('-', ''); 
            document.getElementById(actualInputId).value = val;
            document.getElementById(idPrefix + 'Text').innerText = display;
            list.classList.add('hidden'); list.classList.remove('flex');

            list.querySelectorAll('.chart-dropdown-item').forEach(item => {
                if (item.getAttribute('data-value') === 'all') {
                    item.classList.add('bg-indigo-50', 'text-indigo-600');
                    item.classList.remove('text-slate-700', 'hover:bg-slate-100');
                } else {
                    item.classList.remove('bg-indigo-50', 'text-indigo-600');
                    item.classList.add('text-slate-700', 'hover:bg-slate-100', 'hover:text-indigo-600');
                }
            });

            if (val !== 'all') {
                const selectedItem = list.querySelector(`.chart-dropdown-item[data-value="${val}"]`);
                if (selectedItem) {
                    selectedItem.classList.add('bg-indigo-50', 'text-indigo-600');
                    selectedItem.classList.remove('text-slate-700', 'hover:bg-slate-100');
                }
            }
            
            if (typeof renderCallback === 'function') renderCallback();
        }

        let lastChartKeyTime = 0;
        function handleChartKeydown(e, idPrefix, renderCallback) {
            const list = document.getElementById(idPrefix + 'List');
            if (list.classList.contains('hidden')) {
                if (e.key === "ArrowDown" || e.key === "Enter") toggleChartDropdown(null, idPrefix);
                return;
            }

            if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                const now = Date.now();
                if (now - lastChartKeyTime < 120) { e.preventDefault(); return; }
                lastChartKeyTime = now;
            }

            let items = list.querySelectorAll('.chart-dropdown-item');
            if (e.key === "ArrowDown") {
                currentChartFocus++; addChartActive(items); e.preventDefault();
            } else if (e.key === "ArrowUp") {
                currentChartFocus--; addChartActive(items); e.preventDefault();
            } else if (e.key === "Enter") {
                e.preventDefault();
                if (currentChartFocus > -1 && items[currentChartFocus]) {
                    items[currentChartFocus].click();
                }
            }
        }

        function addChartActive(x) {
            if (!x) return false;
            removeChartActive(x);
            if (currentChartFocus >= x.length) currentChartFocus = 0;
            if (currentChartFocus < 0) currentChartFocus = (x.length - 1);
            x[currentChartFocus].classList.add("kb-active-item");
            x[currentChartFocus].scrollIntoView({ behavior: 'auto', block: 'nearest' });
        }

        function removeChartActive(x) {
            for (let i = 0; i < x.length; i++) {
                x[i].classList.remove("kb-active-item");
            }
        }

        function toggleChartDropdown(e, idPrefix) {
            if(e) e.stopPropagation();
            const list = document.getElementById(idPrefix + 'List');
            const container = document.getElementById(idPrefix + 'Container');

            document.querySelectorAll('.chart-dropdown-list').forEach(l => {
                if (l.id !== list.id) { l.classList.add('hidden'); l.classList.remove('flex'); }
            });

            list.classList.toggle('hidden');
            list.classList.toggle('flex');

            if (!list.classList.contains('hidden')) {
                container.focus();
            }
        }

        document.addEventListener('click', function(e) {
            document.querySelectorAll('.chart-dropdown-list').forEach(list => {
                if (!list.parentElement.contains(e.target)) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                }
            });
        });

        // 1. ฟังก์ชันจัดการสลับหน้า (Tabs)
        function showTab(
            tabId,
            statusFilter = 'all',
            allDates = false,
            initial = false
        ) {
            // เปิดประวัติผ่าน PHP ไม่สร้างหรือแบ่งแถวด้วย JavaScript
            if (tabId === 'history' && !initial) {
                const url = new URL(
                    'technician_home.php',
                    window.location.href
                );

                url.searchParams.set('tab', 'history');
                url.searchParams.set('hs', statusFilter);

                if (allDates) {
                    url.searchParams.set('hm', 'all');
                    url.searchParams.set('hy', 'all');
                }

                url.hash = 'tab-content-history';
                window.location.assign(url.href);
                return;
            }

            document.getElementById('tab-content-dash')
                .classList.add('hidden');

            document.getElementById('tab-content-history')
                .classList.add('hidden');

            document.getElementById('tab-content-' + tabId)
                .classList.remove('hidden');

            document.querySelectorAll('.capsule-btn').forEach(b => {
                b.className =
                    "px-6 py-2 bg-white text-slate-600 text-sm font-bold "
                    + "rounded-full shadow-sm border border-slate-200 "
                    + "hover:bg-indigo-50 hover:text-indigo-600 "
                    + "hover:border-indigo-300 transition-colors "
                    + "cursor-pointer capsule-btn";
            });

            document.getElementById('capsule-' + tabId).className =
                "px-6 py-2 bg-indigo-600 text-white text-sm font-bold "
                + "rounded-full border border-indigo-600 shadow-md "
                + "shadow-indigo-200 transition-colors cursor-pointer capsule-btn";

            if (tabId === 'dash') {
                const homeUrl = new URL(window.location.href);
                homeUrl.searchParams.set('tab', 'dash');
                ['hd','hm','hy','hs','hq','hp','th_fragment','th_line_user_id'].forEach(key => homeUrl.searchParams.delete(key));
                homeUrl.hash = '';
                window.history.replaceState(null, '', homeUrl.pathname + homeUrl.search);
                renderDashTable();
                if (!initial) window.scrollTo({top:0, behavior:'smooth'});
            }
        }

        function thaiRepairTableDate(value) {
            const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T]|$)/.exec(String(value || ''));
            if (!match) return '-';
            const year = Number(match[1]), month = Number(match[2]), day = Number(match[3]);
            const check = new Date(Date.UTC(year, month - 1, day));
            if (year < 1000 || check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day) return '-';
            return match[3] + '/' + match[2] + '/' + String(year + 543);
        }

        function getFilteredRepairsByMonthYear(m, y) {
            if (m === 'all' && y === 'all') return repairsData;
            return repairsData.filter(r => {
                if (!r.created_at || r.created_at === '0000-00-00 00:00:00') return false;
                let datePart = r.created_at.split(' ')[0]; // YYYY-MM-DD
                if (!datePart) return false;
                let parts = datePart.split('-');
                if (parts.length < 3) return false;
                let rYear = parts[0];
                let rMonth = parts[1];
                
                let matchMonth = (m === 'all' || rMonth === m);
                let matchYear = (y === 'all' || rYear === y);
                return matchMonth && matchYear;
            });
        }

        // ใช้เงื่อนไขเดียวกับรูปแบบตารางบนมือถือ/iPad ทั้งสองแนว
        function techCompactViewport() {
            return window.matchMedia('(max-width: 767px), (any-pointer: coarse)').matches;
        }
        function techLocationAxisWidth(chart) {
            // ให้พื้นที่ตามชื่อจริง ชื่อสั้นไม่ต้องเว้นช่องว่างกว้างเท่าชื่อยาว
            const ctx = chart.ctx;
            ctx.save(); ctx.font = "bold 12px 'Plus Jakarta Sans', 'Kanit', sans-serif";
            const longest = Math.max(0, ...chart.data.labels.map(label => ctx.measureText(String(label)).width));
            ctx.restore();
            return Math.min(Math.max(70, Math.min(150, chart.width * 0.30)), Math.max(30, Math.ceil(longest) + 16));
        }
        function techLocationTick(value) {
            const label = String(this.getLabelForValue(value));
            if (!techCompactViewport()) return label;
            const width = techLocationAxisWidth(this.chart) - 16;
            const chars = typeof Intl.Segmenter === 'function'
                ? Array.from(new Intl.Segmenter('th', {granularity:'grapheme'}).segment(label), part => part.segment)
                : (label.match(/[^\u0E31\u0E34-\u0E3A\u0E47-\u0E4E][\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]*/gu) || [label]);
            const lines = []; let line = '';
            this.ctx.save(); this.ctx.font = "bold 12px 'Plus Jakarta Sans', 'Kanit', sans-serif";
            for (const char of chars) {
                if (line && this.ctx.measureText(line + char).width > width) {lines.push(line); line = '';}
                line += char;
            }
            if (line) lines.push(line);
            this.ctx.restore();
            return lines.length ? lines : label;
        }

        // 2. ฟังก์ชันวาดกราฟ
        let techReporterLimit = 'all';

        function setTechReporterLimit(limit) {
            if (![3, 5, 10, 'all'].includes(limit)) return;
            techReporterLimit = limit;
            for (const [id, value] of [
                ['btnTechReporterTop3', 3], ['btnTechReporterTop5', 5],
                ['btnTechReporterTop10', 10], ['btnTechReporterTopAll', 'all'],
                ['btnTechReporterTopAll_mobile', 'all']
            ]) {
                const button = document.getElementById(id);
                if (!button) continue;
                const active = value === limit;
                for (const name of ['bg-indigo-600', 'text-white', 'border-indigo-600', 'hover:bg-indigo-700']) button.classList.toggle(name, active);
                for (const name of ['bg-white', 'text-slate-600', 'border-slate-200', 'hover:bg-slate-50']) button.classList.toggle(name, !active);
                button.setAttribute('aria-pressed', String(active));
            }
            renderTechReporterRanking();
        }

        // ตัวตนผู้แจ้งอ้างอิง LINE ID เท่านั้น ชื่อจากใบงานล่าสุดใช้แสดงผล
        function addLineReporter(map, repair) {
            const id = String(repair.line_user_id ?? '').trim();
            if (!id || id === '-') return;
            const name = String(repair.reporter_name ?? '').trim() || 'ไม่ระบุชื่อผู้แจ้ง';
            const stamp = String(repair.created_at || '');
            const rowId = Number(repair.id) || 0;
            if (!map.has(id)) map.set(id, {id, name, count:0, stamp, rowId});
            const item = map.get(id);
            item.count++;
            if (stamp > item.stamp || (stamp === item.stamp && rowId >= item.rowId)) {
                Object.assign(item, {name, stamp, rowId});
            }
        }
        function escapeReporterHtml(value) {
            return String(value).replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
        }

        function renderTechReporterRanking(reporterMap = null) {
            if (reporterMap === null) {
                reporterMap = new Map();
                const rows = getFilteredRepairsByMonthYear(
                    document.getElementById('repMonth').value,
                    document.getElementById('repYear').value
                ).filter(r => dashboardCalendarMatches(r, 'rep'));
                rows.forEach(r => addLineReporter(reporterMap, r));
            }
            let ranked = Array.from(reporterMap.values()).sort((a, b) => b.count - a.count);
            if (techReporterLimit !== 'all') ranked = ranked.slice(0, techReporterLimit);
            const container = document.getElementById('topReportersContainer');
            if (!container) return;
            if (!ranked.length) {
                container.innerHTML = `<div class="p-10 flex flex-col items-center justify-center text-center min-h-[250px]">
                    <i class="fas fa-user-tag text-4xl mb-3 text-slate-200"></i>
                    <p class="text-slate-400 font-medium text-sm mt-2">ยังไม่มีผู้แจ้งซ่อม</p>
                </div>`;
                return;
            }
            const escape = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
            container.innerHTML = ranked.map((r, index) => {
                const rankColor = index === 0 ? 'text-[#eab308] bg-[#fefce8] border-[#fde047]' :
                    index === 1 ? 'text-[#94a3b8] bg-[#f8fafc] border-[#e2e8f0]' :
                    index === 2 ? 'text-[#d97706] bg-[#fffbeb] border-[#fde68a]' :
                    'text-indigo-500 bg-indigo-50 border-indigo-100';
                const rankIcon = index === 0 ? '<i class="fas fa-trophy text-lg drop-shadow-sm"></i>' :
                    index < 3 ? '<i class="fas fa-medal text-lg drop-shadow-sm"></i>' :
                    `<span class="text-sm font-black">#${index + 1}</span>`;
                // ใช้หน้าประวัติและการค้นหาเดิม ซึ่งยังตรวจสิทธิ์เจ้าของงานฝั่ง PHP
                const query = new URLSearchParams({
                    tab: 'history', hd: 'all',
                    hm: document.getElementById('repMonth').value,
                    hy: document.getElementById('repYear').value,
                    hs: 'all', hq: '', hp: '1', th_line_user_id: r.id, th_fragment: '1'
                });
                const href = escape('technician_home.php?' + query.toString() + '#tab-content-history');
                return `<a href="${href}" data-tech-reporter="${escape(r.name)}" data-tech-line-id="${escape(r.id)}" class="p-4 md:p-5 hover:bg-slate-50 transition-colors flex items-center justify-between border-b border-slate-50 last:border-0 cursor-pointer group no-underline" title="ดูประวัติการแจ้งซ่อมของ ${escape(r.name)}">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center border shadow-sm ${rankColor} shrink-0 group-hover:scale-110 transition-transform">${rankIcon}</div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors break-words">${escape(r.name)}</div>
                            <div class="text-[11px] text-slate-400 font-medium mt-0.5">บุคลากรผู้แจ้งซ่อม</div>
                        </div>
                    </div>
                    <div class="text-right flex items-center shrink-0 ml-3">
                        <div class="flex flex-col items-end">
                            <span class="text-xl font-extrabold text-indigo-600">${r.count}</span>
                            <span class="text-[11px] text-slate-500 font-medium ml-1">รายการ</span>
                        </div>
                        <div class="ml-4 w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center group-hover:bg-indigo-50 group-hover:border-indigo-200 transition-all shadow-sm"><i class="fas fa-chevron-right text-slate-400 group-hover:text-indigo-600 transition-all group-hover:translate-x-0.5 text-xs"></i></div>
                    </div>
                </a>`;
            }).join('');
        }

        function renderAllCharts() {
            let pending = 0, progress = 0, completed = 0;
            let eqMap = {}, locMap = {}, reporterMap = new Map();

            const periodRows = prefix => new Set(getFilteredRepairsByMonthYear(
                document.getElementById(prefix + 'Month').value,
                document.getElementById(prefix + 'Year').value
            ).filter(r => dashboardCalendarMatches(r, prefix)));
            const equipRows = periodRows('equip');
            const statusRows = periodRows('status');
            const locRows = periodRows('loc');
            const reporterRows = periodRows('rep');

            repairsData.forEach(r => {
                let st = (r.status || '').trim();
                if (statusRows.has(r)) {
                    if (st === 'รอรับเรื่อง' || st === 'รอดำเนินการ') pending++;
                    else if (st === 'กำลังดำเนินการ') progress++;
                    else if (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') completed++;
                }

                // ✨ ดึงข้อมูลอุปกรณ์ให้ลึกขึ้น ถ้าไม่มีให้ดึงปัญหามาแสดงแทน เพื่อไม่ให้กลายเป็น "ไม่ระบุ" กระจุกเดียว
                let eq1 = (r.equipment && r.equipment.trim() !== '' && r.equipment !== '-' && r.equipment !== 'ไม่ระบุ') ? r.equipment : null;
                let eq2 = (r.equipment_type && r.equipment_type.trim() !== '' && r.equipment_type !== '-' && r.equipment_type !== 'ไม่ระบุ') ? r.equipment_type : null;
                let p1 = (r.problem && r.problem.trim() !== '' && r.problem !== '-' && r.problem !== 'ไม่ระบุ') ? r.problem : null;
                let p2 = (r.problem_desc && r.problem_desc.trim() !== '' && r.problem_desc !== '-' && r.problem_desc !== 'ไม่ระบุ') ? r.problem_desc : null;
                
                let eq = eq1 || eq2 || p1 || p2 || 'ไม่ระบุ';
                if (eq.length > 20) eq = eq.substring(0, 20) + '...';
                if (equipRows.has(r)) eqMap[eq] = (eqMap[eq] || 0) + 1;

                let loc = r.location || 'ไม่ระบุสถานที่';
                if (locRows.has(r) && loc !== 'ไม่ระบุสถานที่' && loc.trim() !== '') locMap[loc] = (locMap[loc] || 0) + 1;

                if (reporterRows.has(r)) addLineReporter(reporterMap, r);
            });

            // ==========================================
            // Equipment Chart
            let eqSorted = Object.keys(eqMap).map(k => ({name: k, count: eqMap[k]})).sort((a,b) => b.count - a.count).slice(0, 6);
            
            // ทริคแก้ปัญหากราฟเส้น: ถ้ามีข้อมูลแค่จุดเดียว ให้เพิ่มจุดซ้าย-ขวาหลอกๆ ให้มันวาดเป็นเส้นทรงภูเขาได้สวยงาม
            if (eqSorted.length === 1 && eqSorted[0].name !== 'ไม่มีข้อมูล') {
                eqSorted.unshift({ name: '', count: 0 });
                eqSorted.push({ name: ' ', count: 0 });
            }

            const eqCtx = document.getElementById('eqChart').getContext('2d');
            if (window.eqChartInstance) window.eqChartInstance.destroy(); 

            let gradient = eqCtx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(37, 99, 235, 0.16)'); gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)'); 
            
            window.eqChartInstance = new Chart(eqCtx, {
                type: 'line',
                data: {
                    labels: eqSorted.length ? eqSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        label: 'จำนวน (ครั้ง)',
                        data: eqSorted.length ? eqSorted.map(e => e.count) : [0],
                        borderColor: '#2563eb', backgroundColor: gradient, borderWidth: 3,
                        pointBackgroundColor: '#ffffff', pointBorderColor: '#2563eb', pointBorderWidth: 2, pointRadius: 4,
                        fill: true, tension: 0.4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: commonTooltipOptions // ✨ ใส่ Tooltip สีดำ
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        x: { grid: { display: false }, border: {display: false}, ticks: { minRotation: 0, maxRotation: 0, font: { weight: 'bold' } } }
                    }
                }
            });

            // ==========================================
            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            if (window.statusChartInstance) window.statusChartInstance.destroy();

            let totalStatus = pending + progress + completed;
            window.statusChartInstance = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: totalStatus === 0 ? ['ไม่มีข้อมูล'] : ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                    datasets: [{
                        label: 'จำนวนงาน',
                        data: totalStatus === 0 ? [1] : [pending, progress, completed],
                        backgroundColor: totalStatus === 0 ? ['#f1f5f9'] : ['#fbbf24', '#38bdf8', '#10b981'],
                        borderWidth: 0, hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: 'bold' } } },
                        tooltip: Object.assign({}, commonTooltipOptions, { // ✨ ใส่ Tooltip สีดำ + ปิดการแสดงผลถ้าไม่มีข้อมูล
                            enabled: totalStatus > 0,
                            callbacks: {
                                label: function(context) { return ' ' + context.formattedValue + ' งาน'; }
                            }
                        })
                    }
                }
            });

            // ==========================================
            // Location Chart
            let locSorted = Object.keys(locMap).map(k => ({name: k, count: locMap[k]})).sort((a,b) => b.count - a.count).slice(0, 5);
            const locCtx = document.getElementById('locChart').getContext('2d');
            if (window.locChartInstance) window.locChartInstance.destroy();

            window.locChartInstance = new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: locSorted.length ? locSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        label: 'แจ้งซ่อม (ครั้ง)',
                        data: locSorted.length ? locSorted.map(e => e.count) : [0],
                        backgroundColor: '#0284c7', borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: commonTooltipOptions // ✨ ใส่ Tooltip สีดำ
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        y: {
                            afterFit: scale => {if (techCompactViewport()) scale.width = techLocationAxisWidth(scale.chart);},
                            grid: { display: false }, border: {display: false},
                            ticks: {
                                font: { weight: 'bold' },
                                crossAlign: () => techCompactViewport() ? 'far' : 'near',
                                autoSkip: false,
                                callback: techLocationTick
                            }
                        }
                    }
                }
            });

            // ==========================================
            // จัดอันดับเฉพาะรายชื่อในช่วงเดือน/ปีที่เลือก
            renderTechReporterRanking(reporterMap);
        }

        // 3. ฟังก์ชันวาดตารางย่อ (หน้า Dash) ดึงแค่ 5 อันล่าสุด
        function renderDashTable() {
            const tbody = document.getElementById('dashTableBody');
            if (!tbody) return;
            
            let recentRepairs = repairsData.slice(0, 5);
            if (recentRepairs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-400"><i class="fas fa-folder-open text-4xl mb-3 text-slate-200 block"></i>ยังไม่มีประวัติการรับงานในระบบ</td></tr>`;
                return;
            }

            let html = '';
            recentRepairs.forEach(r => {
                let st = (r.status || 'รอดำเนินการ').trim();
                
                let bClass = (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') ? 'badge-success' :
                            (st === 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-pending';
                
                let dt = (r.created_at && r.created_at !== '0000-00-00 00:00:00') ? r.created_at.split(' ') : ['-', ''];
                
                let raw_name = r.reporter_name || '';
                let dName = lineUsersMap[raw_name] ? lineUsersMap[raw_name] : (raw_name !== '' ? raw_name : 'ไม่ระบุ');
                let dPhone = r.phone_number || '-';
                
                let imgIcon = dashboardMediaIcons(r);

                html += `
                    <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0">
                        <td class="px-6 py-4 align-top font-mono font-semibold text-slate-600">${Number(r.id)}</td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class="font-medium text-slate-700">${thaiRepairTableDate(dt[0])}</div>
                            ${dt[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${dt[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-top font-mono font-semibold text-slate-600">${r.ticket_no || '-'}</td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex items-center gap-3">
                                <!-- ✨ ปรับสีไอคอนประจำตัวผู้แจ้งให้เข้มขึ้นเหมือนแอดมิน ✨ -->
                                <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 shrink-0"><i class="fas fa-user text-xs"></i></div>
                                <div>
                                    <div class="font-bold text-slate-800">${dName}</div>
                                    <div class="text-[11px] text-slate-500 font-medium mt-0.5">${dPhone}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="font-bold text-slate-800">${r.equipment_type || 'ไม่ระบุ'} ${imgIcon}</div>
                            <div class="text-[11px] text-slate-500 font-medium mt-0.5 max-w-[180px] truncate" title="${r.location || '-'}">${r.location || '-'}</div>
                        </td>
                        <td class="px-6 py-4 align-middle text-center">
                            <span class="${bClass}">${st}</span>
                        </td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        // 4. ฟังก์ชันวาดตารางใหญ่ (หน้า History) แบบ Filter ได้
        
        // เริ่มวาดข้อมูลตอนโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', () => {
            renderAllCharts();
            renderDashTable();

            if (new URLSearchParams(window.location.search).get('tab') === 'history') {
                showTab('history', 'all', false, true);
            } else {
                showTab('dash', 'all', false, true);
            }
        });

        // 5. โหมดเต็มจอของตาราง (Fullscreen)
        function toggleMaximizeRepairs() {
            const card = document.getElementById('repairsMainCard');
            const icon = document.getElementById('maximizeRepairsIcon');
            const tableContainer = document.getElementById('repairsTableContainer');
            
            if (!card.classList.contains('is-fullscreen')) {
                const rect = card.getBoundingClientRect();
                
                let placeholder = document.getElementById('repairsPlaceholder');
                if (!placeholder) {
                    placeholder = document.createElement('div');
                    placeholder.id = 'repairsPlaceholder';
                    placeholder.style.width = rect.width + 'px';
                    placeholder.style.height = rect.height + 'px';
                    placeholder.className = 'flex-1';
                    card.parentNode.insertBefore(placeholder, card);
                }

                card.style.transition = 'none';
                card.style.position = 'fixed';
                card.style.top = rect.top + 'px';
                card.style.left = rect.left + 'px';
                card.style.width = rect.width + 'px';
                card.style.height = rect.height + 'px';
                card.style.zIndex = '9999';
                card.style.margin = '0';
                
                if (tableContainer) tableContainer.classList.remove('max-h-[70vh]');
                void card.offsetWidth;
                
                card.style.transition = 'top 0.25s, left 0.25s, width 0.25s, height 0.25s, border-radius 0.25s';
                card.classList.add('is-fullscreen');
                
                card.style.top = '0px';
                card.style.left = '0px';
                card.style.width = '100vw';
                card.style.height = '100vh';
                card.style.borderRadius = '0px';
                
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
                document.body.classList.add('overflow-hidden');
            } else {
                const placeholder = document.getElementById('repairsPlaceholder');
                if (placeholder) {
                    const rect = placeholder.getBoundingClientRect();
                    card.style.top = rect.top + 'px';
                    card.style.left = rect.left + 'px';
                    card.style.width = rect.width + 'px';
                    card.style.height = rect.height + 'px';
                    card.style.borderRadius = '20px';
                }
                
                icon.classList.add('fa-expand');
                icon.classList.remove('fa-compress');
                document.body.classList.remove('overflow-hidden');
                card.classList.remove('is-fullscreen');
                
                setTimeout(() => {
                    if (!card.classList.contains('is-fullscreen')) {
                        card.style.transition = 'none';
                        card.style.position = '';
                        card.style.top = '';
                        card.style.left = '';
                        card.style.width = '';
                        card.style.height = '';
                        card.style.zIndex = '';
                        card.style.margin = '';
                        
                        if (tableContainer) tableContainer.classList.add('max-h-[70vh]');
                        if (placeholder) placeholder.remove();
                    }
                }, 250);
            }
        }
    </script>

    <script>
        (() => {
            const portraitMobile = window.matchMedia(
                '(max-width: 639px), (max-width: 1366px) and (any-pointer: coarse)'
            );

            function closeProfileOutsidePortrait() {
                const viewer = document.getElementById('tech-profile-viewer');

                if (!portraitMobile.matches && viewer && viewer.open) {
                    viewer.close();
                }
            }

            if (portraitMobile.addEventListener) {
                portraitMobile.addEventListener(
                    'change',
                    closeProfileOutsidePortrait
                );
            } else {
                portraitMobile.addListener(closeProfileOutsidePortrait);
            }
        })();
    </script>

    <script>
    (() => {
        const form = document.querySelector('#repairsMainCard .th-search');
        if (!form) return;

        const input = form.elements.namedItem('hq');

        let timer;
        let controller;
        let version = 0;
        let composing = false;
        let pendingSearch = false;

        async function search(currentVersion, refresh = false, navigationUrl = null) {
            const requestController = new AbortController();
            controller = requestController;
            if (!refresh) pendingSearch = false;

            const url = new URL(navigationUrl || form.action, window.location.href);
            if (!navigationUrl) url.search = new URLSearchParams(new FormData(form)).toString();
            url.searchParams.set('tab', 'history');
            url.searchParams.set('hq', input.value.trim());
            const currentQuery = window.location.search;
            const currentPage = new URLSearchParams(currentQuery).get('hp') || '1';
            if (!navigationUrl) url.searchParams.set('hp', refresh ? currentPage : '1');
            url.searchParams.set('th_fragment', '1');
            url.hash = '';

            const tbody = document.getElementById('historyTableBody');
            tbody.setAttribute('aria-busy', 'true');

            const oldError = form.querySelector('.th-search-error');
            if (oldError) oldError.remove();

            try {
                const response = await fetch(url.href, {
                    signal: requestController.signal,
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (currentVersion !== version) return;

                if (response.redirected) {
                    window.location.assign(response.url);
                    return;
                }

                if (!response.ok) {
                    throw new Error('Search failed');
                }

                const html = await response.text();

                // ไม่ให้คำขอเก่าทับผลใหม่หรือเปลี่ยนตารางหลังสลับหน้า
                if (currentVersion !== version || currentQuery !== window.location.search) return;

                const doc = new DOMParser().parseFromString(
                    html,
                    'text/html'
                );

                const nextBody = doc.getElementById('historyTableBody');
                const nextFooter = doc.querySelector('.th-pagination');

                if (!nextBody || !nextFooter) {
                    throw new Error('Invalid response');
                }

                const nextForm = doc.querySelector('#repairsMainCard .th-search');
                const nextFilters = doc.querySelector('#repairsMainCard .th-filter-group');
                if (navigationUrl && (!nextForm || !nextFilters)) throw new Error('Invalid filters');

                // PHP สร้างผลลัพธ์ตามเดิม เก็บ card/ฟอร์มค้นหา/สถานะเต็มจอเดิมไว้
                const scroll = document.getElementById('repairsTableContainer');
                const scrollTop = scroll.scrollTop;
                const scrollLeft = scroll.scrollLeft;
                if (tbody.innerHTML !== nextBody.innerHTML) tbody.innerHTML = nextBody.innerHTML;

                const footer = document.querySelector('#repairsMainCard .th-pagination');
                if (footer.innerHTML !== nextFooter.innerHTML) {
                    footer.replaceWith(document.importNode(nextFooter, true));
                }
                if (navigationUrl) {
                    // เปลี่ยนเฉพาะชุดตัวกรอง ไม่แตะกรอบเต็มจอหรือช่องค้นหาที่กำลังพิมพ์
                    document.querySelector('#repairsMainCard .th-filter-group')
                        .replaceWith(document.importNode(nextFilters, true));
                    for (const key of ['hd', 'hm', 'hy', 'hs']) {
                        const selected = nextForm.elements.namedItem(key);
                        const current = form.elements.namedItem(key);
                        if (selected && current) {
                            current.value = selected.value;
                            url.searchParams.set(key, selected.value);
                        }
                    }
                }

                // อัปเดตคิวไม่กระโดดกลับแถวแรก ส่วนค้นหาใหม่ทำงานตามเดิม
                scroll.scrollTop = refresh ? scrollTop : 0;
                scroll.scrollLeft = scrollLeft;
                const activePage = nextFooter.querySelector('[aria-current="page"]');
                const pageNumber = activePage ? activePage.textContent.replace(/,/g, '').trim() : '';
                if (/^[1-9][0-9]*$/.test(pageNumber)) url.searchParams.set('hp', pageNumber);

                url.searchParams.delete('th_fragment');
                url.hash = window.location.hash;

                window.history.replaceState(
                    window.history.state,
                    '',
                    url.href
                );

                // ให้ตัวกรองเก็บคำค้นล่าสุดไว้
                document.querySelectorAll(
                    '#repairsMainCard .th-filter-group a'
                ).forEach(link => {
                    const target = new URL(link.href);
                    target.searchParams.set('hq', input.value.trim());
                    target.searchParams.set('hp', '1');
                    link.href = target.href;
                });

            } catch (error) {
                if (error.name === 'AbortError'
                    || currentVersion !== version) {
                    return;
                }

                const message = document.createElement('span');
                message.className = 'th-search-error';
                message.setAttribute('role', 'alert');
                message.style.cssText =
                    'display:block;color:#e11d48;font-size:12px;margin-top:6px';

                message.textContent =
                    refresh ? 'อัปเดตรายการไม่สำเร็จ ระบบจะลองใหม่อัตโนมัติ' : 'ค้นหาไม่สำเร็จ กรุณากด Enter เพื่อลองอีกครั้ง';

                form.appendChild(message);

            } finally {
                if (currentVersion === version) {
                    tbody.removeAttribute('aria-busy');
                }
                if (controller === requestController) controller = null;
            }
        }

        function refreshHistoryQueue() {
            if (document.hidden || composing || pendingSearch || controller) return;
            if (new URLSearchParams(window.location.search).get('tab') !== 'history') return;
            if (document.querySelector('#repairsMainCard details[open]')) return;
            search(++version, true);
        }

        setInterval(refreshHistoryQueue, 5000);
        window.addEventListener('focus', refreshHistoryQueue);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshHistoryQueue();
        });

        function queue(immediate = false) {
            clearTimeout(timer);

            if (controller) controller.abort();

            const currentVersion = ++version;
            pendingSearch = true;

            if (composing) return;

            if (immediate) {
                search(currentVersion);
            } else {
                timer = setTimeout(
                    () => search(currentVersion),
                    350
                );
            }
        }

        // ดักเฉพาะลิงก์ตัวกรองและเลขหน้าของตารางนี้ คงพฤติกรรมเปิดแท็บใหม่ตามปกติ
        document.getElementById('repairsMainCard').addEventListener('click', event => {
            const link = event.target.closest('.th-filter-group a, .th-pages a');
            if (!link || event.defaultPrevented || event.button !== 0
                || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
                || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
            const target = new URL(link.href, window.location.href);
            if (target.origin !== window.location.origin || target.pathname !== window.location.pathname) return;
            event.preventDefault();
            if (composing) return;
            clearTimeout(timer);
            controller?.abort();
            pendingSearch = true;
            document.querySelectorAll('#repairsMainCard details[open]').forEach(detail => detail.open = false);
            search(++version, false, target.href);
        });

        input.addEventListener('compositionstart', () => {
            composing = true;
            queue();
        });

        input.addEventListener('compositionend', () => {
            composing = false;
            queue();
        });

        input.addEventListener('input', () => queue());

        form.addEventListener('submit', event => {
            event.preventDefault();

            if (!composing) queue(true);
        });
    })();
    </script>


    <style>
        #techReporterModal {position:fixed;inset:0;margin:auto;padding:0;border:0;width:95vw;max-width:95vw;height:85vh;max-height:850px;border-radius:24px;overflow:hidden;background:#fff;color:#334155;box-shadow:0 25px 60px #0f172a40;transition:width .28s ease,height .28s ease,max-width .28s ease,max-height .28s ease,border-radius .28s ease}
        #techReporterModal[open] {display:flex;flex-direction:column}
        #techReporterModal::backdrop {background:rgba(15,23,42,.4);backdrop-filter:blur(4px)}
        #techReporterModal.tech-reporter-expanded {width:100vw;max-width:100vw;height:100vh;max-height:100vh;border-radius:0}
        #techReporterModal .tech-reporter-scroll {overflow:auto;flex:1;min-height:0;background:#fff}
        #techReporterTable {width:100%;min-width:1400px;border-collapse:collapse;text-align:left}
        #techReporterTable th {padding:16px 12px;font-size:10px;white-space:nowrap}
        #techReporterTable td {padding:16px 12px;font-size:12px;line-height:1.6;vertical-align:top}
        #techReporterModalFooter {flex-shrink:0}
        #techReporterModalFooter .th-pagination {margin:0}

/* iPad ทั้งสองแนว / มือถือแนวนอน: ตารางครบทุกคอลัมน์ */
@media (min-width: 768px) and (any-pointer: coarse), (max-width: 767px) and (orientation: landscape) {
    .tech-dashboard-ui #techReporterModal .tech-reporter-scroll {overflow-x:hidden;overflow-y:auto;}
    .tech-dashboard-ui #techReporterModal #techReporterTable {width:100% !important;min-width:0 !important;table-layout:fixed !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th, .tech-dashboard-ui #techReporterModal #techReporterTable td {
        min-width:0 !important;max-width:none;white-space:normal !important;
        word-break:normal !important;overflow-wrap:anywhere;letter-spacing:0 !important;
        padding:10px 3px !important;
    }
    .tech-dashboard-ui #techReporterModal #techReporterTable th {font-size:clamp(8px, .8vw, 9.5px) !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable td {font-size:clamp(9px, .95vw, 12px) !important;line-height:1.6;}
    .tech-dashboard-ui #techReporterModal #techReporterTable td :is(div,span) {min-width:0;max-width:100%;white-space:normal !important;overflow-wrap:anywhere;font-size:inherit;}
    .tech-dashboard-ui #techReporterModal #techReporterTable td .th-status {padding:3px 4px;}
    .tech-dashboard-ui #techReporterModal #techReporterTable tbody td, .tech-dashboard-ui #techReporterModal #techReporterTable tbody td:first-child {padding:10px 3px !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable td .th-date, .tech-dashboard-ui #techReporterModal #techReporterTable td .th-status {white-space:normal !important;overflow-wrap:anywhere;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(1) {width:3% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(2) {width:8% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(3) {width:8% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(4) {width:11% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(5) {width:10% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(6) {width:11% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(7) {width:10% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(8) {width:8% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(9) {width:6% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(10) {width:9% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(11) {width:8% !important;}
    .tech-dashboard-ui #techReporterModal #techReporterTable th:nth-child(12) {width:8% !important;}
}
        @media(prefers-reduced-motion:reduce) {#techReporterModal {transition:none}}
        @media(max-width:640px) {#techReporterModal {width:100vw;max-width:100vw;height:100dvh;max-height:100dvh;border-radius:0}}
    </style>
    <dialog id="techReporterModal" aria-labelledby="techReporterModalTitle">
        <div class="px-5 py-4 xl:pb-3 border-b border-slate-100 flex flex-col gap-4 xl:gap-3 bg-slate-50 shrink-0 relative z-30">
            <div class="flex justify-between items-start sm:items-center w-full">
                <p class="text-lg md:text-xl font-extrabold text-slate-800 flex-1 leading-tight" id="techReporterModalTitle">ประวัติการแจ้งซ่อม</p>
                <div class="flex items-center gap-2 shrink-0 ml-4">
                    <button type="button" onclick="toggleTechReporterMaximize()" class="text-slate-400 hover:text-indigo-600 bg-white border border-slate-200 rounded-xl w-9 h-9 md:w-[42px] md:h-[42px] flex items-center justify-center shadow-sm" title="สลับเต็มจอ"><i class="fas fa-expand" id="techReporterMaximizeIcon"></i></button>
                    <button type="button" onclick="closeTechReporterHistory()" class="text-slate-400 hover:text-rose-500 bg-white border border-slate-200 rounded-xl w-9 h-9 md:w-[42px] md:h-[42px] flex items-center justify-center shadow-sm" title="ปิด"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <div class="relative mr-auto flex-1 min-w-[120px] xl:flex-none xl:w-[450px] 2xl:w-[500px]">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" id="techReporterSearch" maxlength="200" oninput="queueTechReporterHistory()" placeholder="ค้นหาข้อมูลในตาราง..." class="w-full bg-white border border-slate-200 text-sm rounded-xl pl-10 pr-[95px] py-2.5 h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-medium shadow-sm">
                    <button type="button" id="techReporterSearchClear" onclick="document.getElementById('techReporterSearch').value=''; queueTechReporterHistory();" class="absolute right-0 top-0 h-full px-4 text-sm font-medium text-slate-400 border-l border-slate-200 hidden items-center justify-center rounded-r-xl"><i class="fas fa-times mr-1.5"></i>ล้างค่า</button>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <div class="relative w-[110px] portrait:w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="techReporter-DayContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'techReporter-Day', queueTechReporterHistory)" style="font-family: 'Sarabun', sans-serif;">
                                <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm portrait:text-xs text-slate-700 rounded-xl px-4 portrait:px-3 py-2.5 portrait:py-2 h-[42px] portrait:h-[38px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleChartDropdown(event, 'techReporter-Day')">
                                    <span id="techReporter-DayText" class="truncate"><?php echo 'วันที่ ' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('j'); ?></span>
                                    <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                </div>
                                <div id="techReporter-DayList" class="chart-dropdown-list absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                <div class="chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกวัน" onclick="selectChartDropdown('techReporter-Day', 'all', 'ทุกวัน', queueTechReporterHistory)">ทุกวัน</div>
                                    <?php for ($reporterDay = 1; $reporterDay <= 31; $reporterDay++): ?>
                                    <div class="chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600" data-value="<?= $reporterDay ?>" data-display="วันที่ <?= $reporterDay ?>" onclick="selectChartDropdown('techReporter-Day', '<?= $reporterDay ?>', 'วันที่ <?= $reporterDay ?>', queueTechReporterHistory)">วันที่ <?= $reporterDay ?></div>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" id="techReporterDay" value="<?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('j'); ?>">
                            </div>
                            <div class="relative w-[110px] portrait:w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="techReporter-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'techReporter-Month', queueTechReporterHistory)" style="font-family: 'Sarabun', sans-serif;">
                                <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm portrait:text-xs text-slate-700 rounded-xl px-4 portrait:px-3 py-2.5 portrait:py-2 h-[42px] portrait:h-[38px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleChartDropdown(event, 'techReporter-Month')">
                                    <span id="techReporter-MonthText" class="truncate"><?php echo $current_month_name; ?></span>
                                    <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                </div>
                                <div id="techReporter-MonthList" class="chart-dropdown-list absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                <div class="chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกเดือน" onclick="selectChartDropdown('techReporter-Month', 'all', 'ทุกเดือน', queueTechReporterHistory)">ทุกเดือน</div>
                                    <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('techReporter-Month', '{$num_pad}', '{$name}', queueTechReporterHistory)\">{$name}</div>"; } ?>
                                </div>
                                <input type="hidden" id="techReporterMonth" value="<?php echo date('m'); ?>">
                            </div>
                            <div class="relative w-[110px] portrait:w-[85px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="techReporter-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'techReporter-Year', queueTechReporterHistory)" style="font-family: 'Sarabun', sans-serif;">
                                <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm portrait:text-xs text-slate-700 rounded-xl px-4 portrait:px-2.5 py-2.5 portrait:py-2 h-[42px] portrait:h-[38px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleChartDropdown(event, 'techReporter-Year')">
                                    <span id="techReporter-YearText" class="truncate"><?php echo $current_thai_year; ?></span>
                                    <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                </div>
                                <div id="techReporter-YearList" class="chart-dropdown-list absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                <div class="chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all bg-indigo-50 text-indigo-600 hover:text-indigo-600" data-value="all" data-display="ทุกปี" onclick="selectChartDropdown('techReporter-Year', 'all', 'ทุกปี', queueTechReporterHistory)">ทุกปี</div>
                                    <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='{$thai_y}' onclick=\"selectChartDropdown('techReporter-Year', '{$y}', '{$thai_y}', queueTechReporterHistory)\">{$thai_y}</div>"; } ?>
                                </div>
                                <input type="hidden" id="techReporterYear" value="<?php echo date('Y'); ?>">
                            </div>
                </div>
            </div>
        </div>
        <div class="p-0 overflow-hidden flex-1 min-h-0 bg-white">
            <div class="tech-reporter-scroll h-full custom-scrollbar">
                <table id="techReporterTable">
                    <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                            <tr>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Date / Time</th>
                                <th class="px-6 py-4">Ticket No.</th>
                                <th class="px-6 py-4">Reporter</th>
                                <th class="px-6 py-4">Equipment</th>
                                <th class="px-6 py-4">Department</th>
                                <th class="px-6 py-4">Technician</th>
                                <th class="px-6 py-4">Received At</th>
                                <th class="px-6 py-4">Root Cause</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4">Completed At</th>
                                <th class="px-6 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                    <tbody id="techReporterTableBody" class="divide-y divide-slate-100 bg-white"></tbody>
                </table>
            </div>
        </div>
        <div id="techReporterModalFooter"></div>
    </dialog>
    <script>
        const techReporterHistory = {lineId:'', name:'', page:1, timer:null, controller:null, version:0, previousOverflow:''};
        function openTechReporterHistory(name, lineId, repairId = null, reportedDate = null) {
            const jobId = Number(repairId);
            techReporterHistory.repairId = Number.isSafeInteger(jobId) && jobId > 0 ? jobId : null;
            if (typeof lineId !== 'string' || !lineId.trim() || lineId.length > 200) return;
            techReporterHistory.lineId = lineId;
            const modal = document.getElementById('techReporterModal');
            techReporterHistory.name = String(name);
            techReporterHistory.page = 1;
            document.getElementById('techReporterModalTitle').textContent = 'ประวัติการแจ้งซ่อม: ' + name;
            document.getElementById('techReporterSearch').value = '';
            document.getElementById('techReporterSearchClear').classList.add('hidden');
            document.getElementById('techReporterSearchClear').classList.remove('flex');
            const reporterDay = document.getElementById('repDay')?.value || 'all';
            selectChartDropdown('techReporter-Day', reporterDay, reporterDay === 'all' ? 'ทุกวัน' : 'วันที่ ' + reporterDay, null);
            for (const part of ['Month', 'Year']) {
                const value = document.getElementById('rep' + part).value;
                const list = document.getElementById('techReporter-' + part + 'List');
                const item = Array.from(list.querySelectorAll('[data-value]')).find(item => item.dataset.value === value);
                selectChartDropdown('techReporter-' + part, value, item ? item.dataset.display || item.textContent : (part === 'Year' && value !== 'all' ? String(Number(value) + 543) : value), null);
            }
            document.getElementById('techReporterTableBody').innerHTML = '<tr><td colspan="12" class="p-10 text-center text-slate-400">กำลังโหลดรายการ...</td></tr>';
            document.getElementById('techReporterModalFooter').innerHTML = '';
            if (!modal.open) {
                techReporterHistory.previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                modal.showModal();
            }
            if (techReporterHistory.repairId) {
                const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(reportedDate || '');
                const months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                for (const [part,value,label] of parts ? [['Day',String(Number(parts[3])),'วันที่ '+Number(parts[3])],['Month',parts[2],months[Number(parts[2])-1]],['Year',parts[1],String(Number(parts[1])+543)]] : [['Day','all','ทุกวัน'],['Month','all','ทุกเดือน'],['Year','all','ทุกปี']])
                    selectChartDropdown('techReporter-' + part, value, label, null);
            }
            loadTechReporterHistory(1);
        }
        function closeTechReporterHistory() {document.getElementById('techReporterModal').close();}
        function toggleTechReporterMaximize() {
            const expanded = document.getElementById('techReporterModal').classList.toggle('tech-reporter-expanded');
            document.getElementById('techReporterMaximizeIcon').className = expanded ? 'fas fa-compress' : 'fas fa-expand';
        }
        function queueTechReporterHistory() {
            clearTimeout(techReporterHistory.timer);
            techReporterHistory.version++;
            techReporterHistory.controller?.abort();
            const visible = document.getElementById('techReporterSearch').value !== '';
            document.getElementById('techReporterSearchClear').classList.toggle('hidden', !visible);
            document.getElementById('techReporterSearchClear').classList.toggle('flex', visible);
            techReporterHistory.timer = setTimeout(() => loadTechReporterHistory(1), 250);
        }
        async function loadTechReporterHistory(page) {
            const modal = document.getElementById('techReporterModal');
            if (!modal.open) return;
            clearTimeout(techReporterHistory.timer);
            techReporterHistory.controller?.abort();
            const controller = new AbortController();
            techReporterHistory.controller = controller;
            const version = ++techReporterHistory.version;
            const url = new URL('technician_home.php', window.location.href);
            url.search = new URLSearchParams({tab:'history', th_fragment:'1', th_line_user_id:techReporterHistory.lineId, hd:document.getElementById('techReporterDay').value, hm:document.getElementById('techReporterMonth').value, hy:document.getElementById('techReporterYear').value, hs:'all', hq:document.getElementById('techReporterSearch').value.trim(), hp:String(page)}).toString();
            if (techReporterHistory.repairId) url.searchParams.set('th_repair_id', String(techReporterHistory.repairId));
            const body = document.getElementById('techReporterTableBody');
            const footer = document.getElementById('techReporterModalFooter');
            body.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(url.href, {signal:controller.signal, credentials:'same-origin', cache:'no-store'});
                if (version !== techReporterHistory.version || !modal.open) return;
                if (response.redirected) {window.location.assign(response.url); return;}
                if (!response.ok) throw new Error('request');
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (version !== techReporterHistory.version || !modal.open) return;
                const nextBody = doc.getElementById('historyTableBody');
                const nextFooter = doc.querySelector('.th-pagination');
                if (!nextBody || !nextFooter) throw new Error('response');
                body.innerHTML = nextBody.innerHTML;
                footer.replaceChildren(document.importNode(nextFooter, true));
                const selected = nextFooter.querySelector('[aria-current="page"]');
                techReporterHistory.page = selected ? Number(selected.textContent.replace(/,/g, '').trim()) || 1 : 1;
                modal.querySelector('.tech-reporter-scroll').scrollTop = 0;
            } catch (error) {
                if (error.name !== 'AbortError' && version === techReporterHistory.version && modal.open) {
                    footer.innerHTML = '<div class="th-pagination"><span class="text-rose-600" role="alert">โหลดรายการไม่สำเร็จ กรุณาลองใหม่</span><button type="button" onclick="loadTechReporterHistory(' + (Number(page) || 1) + ')" class="text-indigo-600 font-bold">ลองใหม่</button></div>';
                }
            } finally {
                if (version === techReporterHistory.version) body.removeAttribute('aria-busy');
            }
        }
        document.addEventListener('click', event => {
            const rank = event.target.closest('a[data-tech-reporter]');
            if (rank && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                event.preventDefault(); openTechReporterHistory(rank.dataset.techReporter, rank.dataset.techLineId); return;
            }
            const link = event.target.closest('#techReporterModalFooter .th-pages a');
            if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                event.preventDefault(); loadTechReporterHistory(new URL(link.href).searchParams.get('hp') || 1);
            }
        });
        document.getElementById('techReporterModal').addEventListener('close', () => {
            clearTimeout(techReporterHistory.timer);
            techReporterHistory.version++;
            techReporterHistory.controller?.abort();
            document.body.style.overflow = techReporterHistory.previousOverflow;
            document.getElementById('techReporterModal').classList.remove('tech-reporter-expanded');
            document.getElementById('techReporterMaximizeIcon').className = 'fas fa-expand';
        });
        document.getElementById('techReporterModal').addEventListener('click', event => {
            if (event.target !== event.currentTarget) return;
            const bounds = event.currentTarget.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeTechReporterHistory();
        });
    </script>


    <script>
        // อ่านเฉพาะยอดจาก PHP ไม่รีหน้า ไม่เปลี่ยนตัวกรองหรือตารางที่กำลังใช้งาน
        function applyTechnicianSummary(counts) {
            const keys = ['total', 'pending', 'in_progress', 'completed'];
            if (!counts || !keys.every(key => Number.isSafeInteger(counts[key]) && counts[key] >= 0)) return;
            keys.forEach(key => {
                const el = document.getElementById('techCount-' + key);
                if (el) el.textContent = String(counts[key]);
            });
        }
        let technicianSummaryBusy = false;
        async function refreshTechnicianSummary() {
            if (document.hidden || technicianSummaryBusy) return;
            technicianSummaryBusy = true;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const url = new URL('technician_home.php', window.location.href);
                url.search = new URLSearchParams({tech_summary_fragment:'1'}).toString();
                const response = await fetch(url.href, {signal:controller.signal, credentials:'same-origin', cache:'no-store'});
                if (response.redirected) { window.location.assign(response.url); return; }
                if (!response.ok) throw new Error('summary-request');
                const data = await response.json();
                applyTechnicianSummary(data.summary_counts);
            } catch (error) {
                // คงยอดเดิมเมื่อเครือข่ายขัดข้อง แล้วตรวจใหม่ตามรอบถัดไป
            } finally {
                clearTimeout(timeout);
                technicianSummaryBusy = false;
            }
        }
        setInterval(refreshTechnicianSummary, 1000);
        window.addEventListener('focus', refreshTechnicianSummary);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshTechnicianSummary();
        });
    </script>
<?php mbsDashboardActivityScript(); ?>
<script src="repairs_calendar.js?v=20261025-1"></script>
<script src="dashboard_calendars.js?v=20261025-1"></script>
<?php require __DIR__ . '/technician_profile_avatar_panel.php'; ?>
<?php require __DIR__ . '/self_profile_panel.php'; ?>
<?php mbs_feature_assets($conn); ?>
</body>
</html>

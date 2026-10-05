<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์เบื้องต้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Technician') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// 1. ดึง ID ช่างล่าสุดจากตาราง users
$res_u = $conn->query("SELECT technician_id FROM users WHERE id = $user_id");
$tech_id = ($res_u && $res_u->num_rows > 0) ? $res_u->fetch_assoc()['technician_id'] : 0;

// 2. ถ้ายังไม่มี tech_id ให้พยายามดึงจากตาราง technicians อัตโนมัติ (Fallback)
if (empty($tech_id)) {
    $safe_name = $conn->real_escape_string($full_name);
    $res_f = $conn->query("SELECT id FROM technicians WHERE full_name = '$safe_name' LIMIT 1");
    if ($res_f && $res_f->num_rows > 0) {
        $tech_id = $res_f->fetch_assoc()['id'];
        $conn->query("UPDATE users SET technician_id = $tech_id WHERE id = $user_id");
    }
}

// 3. ดึง LINE ID ของช่าง
$line_id = '';
if (!empty($tech_id)) {
    $res_l = $conn->query("SELECT line_user_id FROM technicians WHERE id = $tech_id");
    if ($res_l && $res_l->num_rows > 0) {
        $line_id = $res_l->fetch_assoc()['line_user_id'];
    }
}

// 4. สร้างเงื่อนไข "Ultra-Link" ควานหางานจากทุกรูปแบบ (ID, LINE ID, ชื่อ)
$safe_tech_id = intval($tech_id);
$safe_line_id = $conn->real_escape_string($line_id);
$safe_full_name = $conn->real_escape_string($full_name);

$where = "(technician_id = '$safe_tech_id'";
if (!empty($safe_line_id)) {
    $where .= " OR technician_id = '$safe_line_id'";
}
// เช็คว่าฐานข้อมูลมีคอลัมน์ชื่อช่างไหม ถ้ามีให้เอามาค้นหาด้วย
if ($conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'")->num_rows > 0) {
    $where .= " OR technician_name = '$safe_full_name'";
}
$where .= ")";

// จัดการการอัปเดตหมายเหตุจากหน้าเว็บ (ไม่ได้ใช้ Modal แล้ว แต่เก็บ API ไว้เผื่อจำเป็น)
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_remark') {
    $repair_id = intval($_POST['repair_id']);
    $remark = $conn->real_escape_string($_POST['remark']);
    
    // อัปเดตงานโดยใช้เงื่อนไข Ultra-Link ป้องกันการแก้งานคนอื่น
    if($conn->query("UPDATE repairs SET remark = '$remark' WHERE id = $repair_id AND $where")) {
        $msg = "บันทึกหมายเหตุสำเร็จเรียบร้อยครับ";
    }
}

// 5. ดึงสถิติภาพรวม 4 สถานะ
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
$res_stats = $conn->query("SELECT status, COUNT(*) as count FROM repairs WHERE $where GROUP BY status");
if ($res_stats) {
    while ($row = $res_stats->fetch_assoc()) {
        $stats['total'] += $row['count'];
        $db_status = trim($row['status']);
        if ($db_status === 'รอดำเนินการ' || $db_status === 'รอรับเรื่อง') {
            $stats['pending'] += $row['count'];
        } elseif ($db_status === 'กำลังดำเนินการ') {
            $stats['in_progress'] += $row['count'];
        } elseif ($db_status === 'เสร็จสิ้น' || $db_status === 'ซ่อมเสร็จแล้ว') {
            $stats['completed'] += $row['count'];
        }
    }
}

// 6. ดึงประวัติรายการแจ้งซ่อมทั้งหมด
$repairs = [];
$res_repairs = $conn->query("SELECT * FROM repairs WHERE $where ORDER BY created_at DESC");
if ($res_repairs) {
    while ($row = $res_repairs->fetch_assoc()) {
        $repairs[] = $row;
    }
}

// ดึงข้อมูลแผนกและตำแหน่งของช่างทั้งหมดเพื่อนำไปใช้ในตาราง
$tech_dept_map = [];
$tech_info_map = [];
$td_res = $conn->query("SELECT full_name, department, english_name, position FROM technicians");
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
</head>
<body class="flex flex-col min-h-screen">

    <!-- Top Navbar -->
    <nav class="bg-indigo-600 text-white shadow-md sticky top-0 z-40 shrink-0">
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
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-indigo-700/50 px-3 py-1.5 rounded-xl border border-indigo-500/30">
                        <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-sm">
                            <?= mb_substr($full_name, 0, 1) ?>
                        </div>
                        <span class="text-sm font-medium hidden sm:block"><?= htmlspecialchars($full_name) ?></span>
                    </div>
                    <a href="logout.php" class="bg-rose-500 hover:bg-rose-600 px-3.5 py-2 rounded-xl text-sm font-medium transition-colors shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header & Tabs (ซ่อนไว้เพราะใช้แบบ Capsule แทน) -->
    <div class="bg-white border-b border-slate-200 shrink-0 hidden" id="headerTabs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex pt-4 pb-0 gap-2 overflow-x-auto">
                <button onclick="showTab('dash')" id="tab-dash" class="px-6 py-3 bg-indigo-600 text-white text-sm font-medium rounded-t-lg shadow-sm tab-btn">
                    ทั้งหมด
                </button>
                <button onclick="showTab('history')" id="tab-history" class="px-6 py-3 bg-white text-slate-500 hover:text-indigo-600 text-sm font-medium rounded-t-lg border-b-2 border-transparent hover:border-indigo-200 transition-colors tab-btn">
                    ประวัติงาน
                </button>
            </div>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto w-full relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <?php if (!empty($msg)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl mb-6 flex items-center shadow-sm">
                    <i class="fas fa-check-circle mr-2.5 text-lg"></i> <?= $msg ?>
                </div>
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
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="modern-card p-6 border-b-4 border-b-violet-500 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history')">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['total'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">งานที่รับผิดชอบทั้งหมด</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-violet-50 border border-violet-100 shadow-inner flex items-center justify-center text-violet-600 text-xl group-hover:bg-violet-600 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>
                <div class="modern-card p-6 border-b-4 border-b-amber-400 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'รอรับเรื่อง')">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['pending'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">รอรับเรื่อง</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-[#fef3c7]/70 border border-amber-200/70 shadow-inner flex items-center justify-center text-[#d97706] text-xl group-hover:bg-amber-500 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="modern-card p-6 border-b-4 border-b-indigo-500 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'กำลังดำเนินการ')">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['in_progress'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">กำลังดำเนินการ</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-[#e0e7ff]/70 border border-indigo-200/70 shadow-inner flex items-center justify-center text-[#4f46e5] text-xl group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                </div>
                <div class="modern-card p-6 border-b-4 border-b-emerald-500 hover:-translate-y-0.5 transition-all duration-300 cursor-pointer group" onclick="showTab('history', 'ซ่อมเสร็จแล้ว')">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="text-4xl font-extrabold text-slate-800 mb-1"><?= $stats['completed'] ?></div>
                            <div class="text-sm text-slate-500 font-bold">ซ่อมเสร็จแล้ว</div>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-[#d1fae5]/70 border border-emerald-200/70 shadow-inner flex items-center justify-center text-[#059669] text-xl group-hover:bg-emerald-500 group-hover:text-white transition-colors duration-300">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ปุ่มเมนูแท็บแบบ Capsule -->
            <div class="flex items-center gap-2.5 mb-8">
                <button onclick="showTab('dash')" id="capsule-dash" class="px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-full border border-indigo-600 shadow-md shadow-indigo-200 transition-colors cursor-pointer capsule-btn">
                    ทั้งหมด
                </button>
                <button onclick="showTab('history')" id="capsule-history" class="px-6 py-2 bg-white text-slate-600 text-sm font-bold rounded-full shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors cursor-pointer capsule-btn">
                    ประวัติงาน
                </button>
            </div>

            <!-- ========================================== -->
            <!-- ส่วนที่ 1: Dashboard Overview (id="tab-content-dash") -->
            <!-- ========================================== -->
            <div id="tab-content-dash" class="space-y-8 animate-fade-in">
                <!-- กราฟแถวที่ 1 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">สถิติอุปกรณ์ที่คุณได้รับมอบหมาย</p>
                        </div>
                        <div class="flex-1 relative w-full h-[280px]">
                            <canvas id="eqChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">สัดส่วนสถานะการดำเนินงาน</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">สถานะงานซ่อมทั้งหมดของคุณ</p>
                        </div>
                        <div class="flex-1 relative w-full h-[280px] flex justify-center items-center">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- กราฟแถวที่ 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">สถานที่เกิดปัญหาบ่อยที่สุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">ห้องหรืออาคารที่คุณไปซ่อมบ่อยๆ</p>
                        </div>
                        <div class="relative w-full h-[280px]"> 
                            <canvas id="locChart"></canvas>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">รายชื่อผู้ใช้งานที่คุณให้บริการบ่อย</p>
                        </div>
                        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar max-h-[280px]" id="topReportersContainer">
                            <!-- Javascript จะวาดรายชื่อตรงนี้ -->
                        </div>
                    </div>
                </div>

                <!-- ตารางหน้าแรก (สไตล์เดียวกับ Admin) -->
                <div class="modern-card overflow-hidden flex flex-col mb-12">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">Recent Transactions</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">Latest 5 repairs in system</p>
                        </div>
                        <button onclick="showTab('history')" class="flex items-center text-sm text-slate-600 font-bold hover:text-indigo-600 transition-colors group">
                            See All <i class="fas fa-arrow-right ml-2 text-xs text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-1"></i>
                        </button>
                    </div>
                    <div class="overflow-x-auto pb-4 custom-scrollbar table-wrapper-fix">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-[#fef9c3] text-[#854d0e] text-xs uppercase tracking-widest font-bold border-b border-[#fef08a]">
                                <tr>
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
            <div id="tab-content-history" class="hidden animate-fade-in no-print">
                <div class="modern-card overflow-hidden flex flex-col transition-all duration-300 bg-white" id="repairsMainCard">
                    <!-- Header ส่วนค้นหา -->
                    <div class="p-4 md:p-6 border-b border-slate-100 flex flex-col gap-4 bg-white shrink-0 relative z-30">
                        <div class="flex justify-between items-start w-full">
                            <div class="shrink-0 flex-1">
                                <h2 class="text-xl font-extrabold text-slate-800">ประวัติการรับแจ้งซ่อมทั้งหมด</h2>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">All repair transactions</p>
                            </div>
                            <!-- ปุ่ม ขยายเต็มจอ + ปิด -->
                            <div class="flex items-center gap-2 shrink-0 ml-4">
                                <button onclick="toggleMaximizeRepairs()" class="text-slate-400 hover:text-indigo-600 transition-colors bg-white border border-slate-200 rounded-xl w-9 h-9 md:w-[42px] md:h-[42px] flex items-center justify-center shadow-sm shrink-0 hover:bg-indigo-50" title="สลับเต็มจอ">
                                    <i class="fas fa-expand text-sm md:text-base" id="maximizeRepairsIcon"></i>
                                </button>
                                <button onclick="if(document.getElementById('repairsMainCard').classList.contains('is-fullscreen')) toggleMaximizeRepairs(); else showTab('dash');" class="text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 rounded-xl w-9 h-9 md:w-[42px] md:h-[42px] flex items-center justify-center shadow-sm shrink-0 hover:bg-rose-50" title="ปิด">
                                    <i class="fas fa-times text-sm md:text-base"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ตัวค้นหาและกรองสถานะ -->
                        <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                            <div class="relative flex-1 min-w-[120px] xl:flex-none xl:w-[450px] group">
                                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="text" id="searchHistoryInput" oninput="renderHistoryTable(); toggleClearBtn('searchHistoryInput', 'clearHistoryBtn');" placeholder="ค้นหาข้อมูลในตาราง..." class="w-full bg-white border border-slate-200 text-sm rounded-xl pl-10 pr-[95px] py-2.5 h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium shadow-sm">
                                <button type="button" id="clearHistoryBtn" onclick="clearSearchInput('searchHistoryInput', renderHistoryTable)" class="absolute right-0 top-0 h-full px-4 text-sm font-medium text-slate-400 hover:text-rose-500 hover:bg-rose-50 border-l border-slate-200 hidden items-center justify-center transition-colors rounded-r-xl"><i class="fas fa-times mr-1.5 text-sm"></i>ล้างค่า</button>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <!-- ✨ Dropdown สถานะแบบ Custom เหมือน Admin ✨ -->
                                <div class="relative w-[135px] portrait:w-[115px] sm:w-[140px] landscape:w-[140px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="table-StatusContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'table-Status', renderHistoryTable)" style="font-family: 'Sarabun', sans-serif;">
                                    <div id="table-StatusTrigger" class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm portrait:text-xs sm:text-sm landscape:text-sm text-slate-700 rounded-xl px-3.5 portrait:px-2.5 sm:px-3.5 landscape:px-3.5 py-2.5 portrait:py-2 sm:py-2.5 landscape:py-2.5 h-[42px] portrait:h-[38px] sm:h-[42px] landscape:h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleChartDropdown(event, 'table-Status')">
                                        <span id="table-StatusText" class="truncate">ทั้งหมด</span>
                                        <i id="table-StatusCaret" class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="table-StatusList" class="chart-dropdown-list absolute z-50 w-[155px] right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col p-2 space-y-1.5" style="font-family: 'Sarabun', sans-serif;">
                                        <div class="chart-dropdown-item px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 bg-slate-50 hover:bg-slate-100 text-center" data-value="all" data-display="ทั้งหมด" onclick="selectTableStatusDropdown('all', 'ทั้งหมด')">ทั้งหมด</div>
                                        <div class="chart-dropdown-item px-3 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-all bg-[#fef3c7] text-[#d97706] hover:brightness-95 text-center shadow-2xs" data-value="รอรับเรื่อง" data-display="รอรับเรื่อง" onclick="selectTableStatusDropdown('รอรับเรื่อง', 'รอรับเรื่อง')">รอรับเรื่อง</div>
                                        <div class="chart-dropdown-item px-3 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-all bg-[#e0e7ff] text-[#4f46e5] hover:brightness-95 text-center shadow-2xs" data-value="กำลังดำเนินการ" data-display="กำลังดำเนินการ" onclick="selectTableStatusDropdown('กำลังดำเนินการ', 'กำลังดำเนินการ')">กำลังดำเนินการ</div>
                                        <div class="chart-dropdown-item px-3 py-1.5 rounded-full text-xs font-bold cursor-pointer transition-all bg-[#d1fae5] text-[#059669] hover:brightness-95 text-center shadow-2xs" data-value="ซ่อมเสร็จแล้ว" data-display="ซ่อมเสร็จแล้ว" onclick="selectTableStatusDropdown('ซ่อมเสร็จแล้ว', 'ซ่อมเสร็จแล้ว')">ซ่อมเสร็จแล้ว</div>
                                    </div>
                                    <input type="hidden" id="filterStatus" value="all">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางแบบ History Modal (แสดงครบถ้วนเหมือน Admin) -->
                    <div class="overflow-x-auto w-full pb-4 custom-scrollbar table-wrapper-fix">
                        <table class="w-full text-left whitespace-nowrap min-w-[1100px]" id="historyTableFull">
                            <thead class="bg-[#fef9c3] text-[#854d0e] text-xs uppercase tracking-widest font-bold border-b border-[#fef08a] sticky top-0 z-20 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4 border-0">Date / Time</th>
                                    <th class="px-6 py-4 border-0">Ticket No.</th>
                                    <th class="px-6 py-4 border-0">Reporter</th>
                                    <th class="px-6 py-4 border-0">Equipment</th>
                                    <th class="px-6 py-4 border-0">Department</th>
                                    <th class="px-6 py-4 border-0">Technician</th>
                                    <th class="px-6 py-4 border-0">Received At</th>
                                    <th class="px-6 py-4 border-0">Root Cause</th>
                                    <th class="px-6 py-4 text-center border-0">Status</th>
                                    <th class="px-6 py-4 border-0">Completed At</th>
                                    <th class="px-6 py-4 text-center border-0">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white" id="historyTableBody">
                                <!-- JS จะใส่ข้อมูลตารางใหญ่ที่นี่ -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Javascript สำหรับวาดกราฟและตาราง -->
    <script>
        const repairsData = <?php echo json_encode($repairs); ?>;
        const lineUsersMap = <?php echo $line_users_map_json; ?>;
        const techDeptMap = <?php echo json_encode($tech_dept_map); ?>;
        const techInfoMap = <?php echo json_encode($tech_info_map); ?>;
        
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Kanit', sans-serif";
        Chart.defaults.color = '#64748b';

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

        // ✨ ระบบ Custom Dropdown (สถานะ)
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

        function selectTableStatusDropdown(val, display) {
            const list = document.getElementById('table-StatusList');
            const input = document.getElementById('filterStatus');
            const textEl = document.getElementById('table-StatusText');
            const trigger = document.getElementById('table-StatusTrigger');
            const caret = document.getElementById('table-StatusCaret');

            if (input) input.value = val;
            if (textEl) textEl.innerText = display;
            if (list) { list.classList.add('hidden'); list.classList.remove('flex'); }

            if (trigger && caret) {
                // ล้างสีสถานะเดิมออกก่อน
                trigger.classList.remove(
                    'bg-white', 'text-slate-700', 'border-slate-200', 'hover:bg-slate-50',
                    'bg-[#fef3c7]', 'text-[#d97706]', 'border-[#fde68a]',
                    'bg-[#e0e7ff]', 'text-[#4f46e5]', 'border-[#c7d2fe]',
                    'bg-[#d1fae5]', 'text-[#059669]', 'border-[#a7f3d0]'
                );
                caret.classList.remove('text-slate-400', 'text-[#d97706]', 'text-[#4f46e5]', 'text-[#059669]');

                if (val === 'รอรับเรื่อง') {
                    trigger.classList.add('bg-[#fef3c7]', 'text-[#d97706]', 'border-[#fde68a]');
                    caret.classList.add('text-[#d97706]');
                } else if (val === 'กำลังดำเนินการ') {
                    trigger.classList.add('bg-[#e0e7ff]', 'text-[#4f46e5]', 'border-[#c7d2fe]');
                    caret.classList.add('text-[#4f46e5]');
                } else if (val === 'ซ่อมเสร็จแล้ว') {
                    trigger.classList.add('bg-[#d1fae5]', 'text-[#059669]', 'border-[#a7f3d0]');
                    caret.classList.add('text-[#059669]');
                } else {
                    trigger.classList.add('bg-white', 'text-slate-700', 'border-slate-200', 'hover:bg-slate-50');
                    caret.classList.add('text-slate-400');
                }
            }

            renderHistoryTable();
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
        function showTab(tabId, statusFilter = 'all') {
            document.getElementById('tab-content-dash').classList.add('hidden');
            document.getElementById('tab-content-history').classList.add('hidden');
            
            document.getElementById('tab-content-' + tabId).classList.remove('hidden');

            document.querySelectorAll('.capsule-btn').forEach(b => {
                b.className = "px-6 py-2 bg-white text-slate-600 text-sm font-bold rounded-full shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 transition-colors cursor-pointer capsule-btn";
            });
            document.getElementById('capsule-' + tabId).className = "px-6 py-2 bg-indigo-600 text-white text-sm font-bold rounded-full border border-indigo-600 shadow-md shadow-indigo-200 transition-colors cursor-pointer capsule-btn";

            if (tabId === 'history') {
                const disp = (statusFilter === 'all') ? 'ทั้งหมด' : statusFilter;
                selectTableStatusDropdown(statusFilter, disp);
                renderHistoryTable();
            } else if (tabId === 'dash') {
                renderDashTable();
            }
        }

        // 2. ฟังก์ชันวาดกราฟ
        function renderAllCharts() {
            let pending = 0, progress = 0, completed = 0;
            let eqMap = {}, locMap = {}, reporterMap = {};

            repairsData.forEach(r => {
                let st = (r.status || '').trim();
                if (st === 'รอรับเรื่อง' || st === 'รอดำเนินการ') pending++;
                else if (st === 'กำลังดำเนินการ') progress++;
                else if (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') completed++;

                // ✨ ดึงข้อมูลอุปกรณ์ให้ลึกขึ้น ถ้าไม่มีให้ดึงปัญหามาแสดงแทน เพื่อไม่ให้กลายเป็น "ไม่ระบุ" กระจุกเดียว
                let eq1 = (r.equipment && r.equipment.trim() !== '' && r.equipment !== '-' && r.equipment !== 'ไม่ระบุ') ? r.equipment : null;
                let eq2 = (r.equipment_type && r.equipment_type.trim() !== '' && r.equipment_type !== '-' && r.equipment_type !== 'ไม่ระบุ') ? r.equipment_type : null;
                let p1 = (r.problem && r.problem.trim() !== '' && r.problem !== '-' && r.problem !== 'ไม่ระบุ') ? r.problem : null;
                let p2 = (r.problem_desc && r.problem_desc.trim() !== '' && r.problem_desc !== '-' && r.problem_desc !== 'ไม่ระบุ') ? r.problem_desc : null;
                
                let eq = eq1 || eq2 || p1 || p2 || 'ไม่ระบุ';
                if (eq.length > 20) eq = eq.substring(0, 20) + '...';
                eqMap[eq] = (eqMap[eq] || 0) + 1;

                let loc = r.location || 'ไม่ระบุสถานที่';
                if (loc !== 'ไม่ระบุสถานที่' && loc.trim() !== '') locMap[loc] = (locMap[loc] || 0) + 1;

                let repNameRaw = r.reporter_name || 'ไม่ระบุผู้แจ้ง';
                let repName = lineUsersMap[repNameRaw] ? lineUsersMap[repNameRaw] : repNameRaw;
                reporterMap[repName] = (reporterMap[repName] || 0) + 1;
            });

            // Equipment Chart
            let eqSorted = Object.keys(eqMap).map(k => ({name: k, count: eqMap[k]})).sort((a,b) => b.count - a.count).slice(0, 6);
            
            // ✨ ทริคแก้ปัญหากราฟเส้น: ถ้ามีข้อมูลแค่จุดเดียว ให้เพิ่มจุดซ้าย-ขวาหลอกๆ ให้มันวาดเป็นเส้นทรงภูเขาได้สวยงาม ✨
            if (eqSorted.length === 1 && eqSorted[0].name !== 'ไม่มีข้อมูล') {
                eqSorted.unshift({ name: '', count: 0 });
                eqSorted.push({ name: ' ', count: 0 });
            }

            const eqCtx = document.getElementById('eqChart').getContext('2d');
            if (window.eqChartInstance) window.eqChartInstance.destroy(); // ล้างของเก่ากันซ้อน

            let gradient = eqCtx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)'); gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            window.eqChartInstance = new Chart(eqCtx, {
                type: 'line',
                data: {
                    labels: eqSorted.length ? eqSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        data: eqSorted.length ? eqSorted.map(e => e.count) : [0],
                        borderColor: '#8b5cf6', backgroundColor: gradient, borderWidth: 3,
                        pointBackgroundColor: '#ffffff', pointBorderColor: '#8b5cf6', pointBorderWidth: 2, pointRadius: 4,
                        fill: true, tension: 0.4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        x: { grid: { display: false }, border: {display: false}, ticks: { font: { weight: 'bold' } } }
                    }
                }
            });

            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            if (window.statusChartInstance) window.statusChartInstance.destroy();

            let totalStatus = pending + progress + completed;
            window.statusChartInstance = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: totalStatus === 0 ? ['ไม่มีข้อมูล'] : ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                    datasets: [{
                        data: totalStatus === 0 ? [1] : [pending, progress, completed],
                        backgroundColor: totalStatus === 0 ? ['#f1f5f9'] : ['#fbbf24', '#38bdf8', '#10b981'],
                        borderWidth: 0, hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: 'bold' } } },
                        tooltip: { enabled: totalStatus > 0 }
                    }
                }
            });

            // Location Chart
            let locSorted = Object.keys(locMap).map(k => ({name: k, count: locMap[k]})).sort((a,b) => b.count - a.count).slice(0, 5);
            const locCtx = document.getElementById('locChart').getContext('2d');
            if (window.locChartInstance) window.locChartInstance.destroy();

            window.locChartInstance = new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: locSorted.length ? locSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        data: locSorted.length ? locSorted.map(e => e.count) : [0],
                        backgroundColor: '#f43f5e', borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        y: { grid: { display: false }, border: {display: false}, ticks: { font: { weight: 'bold' } } }
                    }
                }
            });

            // Top Reporters List (การ์ดเหรียญรางวัล)
            let repSorted = Object.keys(reporterMap).map(k => ({name: k, count: reporterMap[k]})).sort((a,b) => b.count - a.count).slice(0, 5);
            const repContainer = document.getElementById('topReportersContainer');
            if (repSorted.length === 0) {
                repContainer.innerHTML = `<div class='py-10 flex flex-col items-center justify-center text-center'>
                    <div class='w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mb-3'><i class='fas fa-user-tag text-2xl text-slate-300'></i></div>
                    <p class='text-slate-400 font-bold text-sm'>ยังไม่มีผู้แจ้งซ่อม</p>
                </div>`;
            } else {
                let html = '<ul class="divide-y divide-slate-100">';
                repSorted.forEach((r, index) => {
                    let rankIcon = index === 0 ? '<i class="fas fa-trophy text-lg text-amber-500 drop-shadow-sm"></i>' :
                                   index === 1 ? '<i class="fas fa-medal text-lg text-slate-400 drop-shadow-sm"></i>' :
                                   index === 2 ? '<i class="fas fa-medal text-lg text-amber-600 drop-shadow-sm"></i>' :
                                   `<span class="text-sm font-black text-indigo-500">#${index + 1}</span>`;
                    let rankBg = index === 0 ? 'bg-[#fefce8] border-[#fde047]' : 
                                 index === 1 ? 'bg-[#f8fafc] border-[#e2e8f0]' : 
                                 index === 2 ? 'bg-[#fffbeb] border-[#fde68a]' : 'bg-indigo-50 border-indigo-100';
                    
                    html += `
                        <li class="py-3 flex justify-between items-center hover:bg-slate-50/50 p-2 rounded-xl transition-colors cursor-default">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center border shadow-sm shrink-0 ${rankBg}">${rankIcon}</div>
                                <div>
                                    <p class="font-bold text-slate-800 text-sm">${r.name}</p>
                                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">บุคลากรผู้แจ้งซ่อม</p>
                                </div>
                            </div>
                            <div class="text-right flex items-center gap-3">
                                <div>
                                    <p class="font-extrabold text-indigo-600 text-xl leading-none">${r.count}</p>
                                    <p class="text-[10px] text-slate-400 font-medium mt-1">รายการ</p>
                                </div>
                            </div>
                        </li>`;
                });
                html += '</ul>';
                repContainer.innerHTML = html;
            }
        }


        // 3. ฟังก์ชันวาดตารางย่อ (หน้า Dash) ดึงแค่ 5 อันล่าสุด
        function renderDashTable() {
            const tbody = document.getElementById('dashTableBody');
            if (!tbody) return;
            
            let recentRepairs = repairsData.slice(0, 5);
            if (recentRepairs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-slate-400"><i class="fas fa-folder-open text-4xl mb-3 text-slate-200 block"></i>ยังไม่มีประวัติการรับงานในระบบ</td></tr>`;
                return;
            }

            let html = '';
            recentRepairs.forEach(r => {
                let st = (r.status || 'รอดำเนินการ').trim();
                let bClass = (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') ? 'text-emerald-600 bg-emerald-50 border-emerald-200' :
                             (st === 'กำลังดำเนินการ') ? 'text-sky-600 bg-sky-50 border-sky-200' : 'text-amber-600 bg-amber-50 border-amber-200';
                
                let dt = (r.created_at && r.created_at !== '0000-00-00 00:00:00') ? r.created_at.split(' ') : ['-', ''];
                
                let raw_name = r.reporter_name || '';
                let dName = lineUsersMap[raw_name] ? lineUsersMap[raw_name] : (raw_name !== '' ? raw_name : 'ไม่ระบุ');
                let dPhone = r.phone_number || '-';
                
                let imgIcon = r.image_path ? `<i class="fas fa-image text-slate-300 ml-1" title="มีรูปภาพ"></i>` : '';

                html += `
                    <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0">
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class="font-medium text-slate-700">${dt[0]}</div>
                            ${dt[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${dt[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-top font-mono font-semibold text-slate-600">${r.repair_code || 'MR-'+r.id}</td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex items-center gap-3">
                                <!-- ✨ ปรับสีไอคอนประจำตัวผู้แจ้งให้เข้มขึ้นเหมือนแอดมิน ✨ -->
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0"><i class="fas fa-user text-xs"></i></div>
                                <div>
                                    <div class="font-bold text-slate-800">${dName}</div>
                                    <div class="text-[11px] text-slate-500 font-medium mt-0.5">${dPhone}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="font-bold text-slate-800">${r.equipment || r.problem || 'ไม่ระบุ'} ${imgIcon}</div>
                            <div class="text-[11px] text-slate-500 font-medium mt-0.5 max-w-[180px] truncate" title="${r.location || '-'}">${r.location || '-'}</div>
                        </td>
                        <td class="px-6 py-4 align-middle text-center">
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold border ${bClass} inline-block shadow-sm">${st}</span>
                        </td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        // 4. ฟังก์ชันวาดตารางใหญ่ (หน้า History) แบบ Filter ได้
        function renderHistoryTable() {
            const tbody = document.getElementById('historyTableBody');
            if (!tbody) return;

            const searchVal = document.getElementById('searchHistoryInput').value.toLowerCase().trim();
            const statusVal = document.getElementById('filterStatus').value;

            let filtered = repairsData.filter(r => {
                let st = (r.status || 'รอดำเนินการ').trim();
                let isStatusMatch = (statusVal === 'all') ? true : (st === statusVal);
                
                let textToSearch = Object.values(r).join(' ').toLowerCase();
                let isTextMatch = (searchVal === '') ? true : textToSearch.includes(searchVal);

                return isStatusMatch && isTextMatch;
            });

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="px-6 h-[400px] text-center align-middle text-slate-400 font-medium">ไม่พบข้อมูลใบงานที่ตรงกับเงื่อนไข</td></tr>`;
                return;
            }

            let html = '';
            filtered.forEach(r => {
                let st = (r.status || 'รอดำเนินการ').trim();
                let bClass = (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') ? 'text-emerald-600 bg-emerald-50 border-emerald-200' :
                             (st === 'กำลังดำเนินการ') ? 'text-sky-600 bg-sky-50 border-sky-200' : 'text-amber-600 bg-amber-50 border-amber-200';
                
                let dt = (r.created_at && r.created_at !== '0000-00-00 00:00:00') ? r.created_at.split(' ') : ['-', ''];
                
                // ✨ บังคับให้เวลา RECEIVED AT ขึ้นพร้อมกับสถานะ 'กำลังดำเนินการ' ทันที ✨
                let dtNow = new Date();
                let nowStr = dtNow.getFullYear() + '-' + String(dtNow.getMonth()+1).padStart(2,'0') + '-' + String(dtNow.getDate()).padStart(2,'0') + ' ' + String(dtNow.getHours()).padStart(2,'0') + ':' + String(dtNow.getMinutes()).padStart(2,'0') + ':00';
                let raw_rec_js = (r.received_at && r.received_at != '0000-00-00 00:00:00' && r.received_at != '-') ? r.received_at : (st !== 'รอรับเรื่อง' ? nowStr : '');
                let has_received = (raw_rec_js !== '');
                let rec = has_received ? raw_rec_js.split(' ') : ['-', ''];
                
                let com = (r.completed_at && r.completed_at !== '0000-00-00 00:00:00') ? r.completed_at.split(' ') : ['-', ''];
                
                // ดึงชื่อและเบอร์โทรแบบเต็มพิกัดเหมือนแอดมิน
                let raw_name = r.reporter_name || '';
                let dName = lineUsersMap[raw_name] ? lineUsersMap[raw_name] : (raw_name !== '' ? raw_name : 'ไม่ระบุ');
                let dPhone = r.phone_number || '-';

                // ข้อมูลช่างและแผนก
                let tNameHtml = "<span class='text-rose-500 font-bold'>-</span>";
                let deptEng = "<span class='text-rose-500 font-bold'>-</span>";
                if (r.technician_name && r.technician_name !== '-') {
                    let info = techInfoMap[r.technician_name] || { th: r.technician_name, eng: '', pos: '' };
                    tNameHtml = `<div class='text-indigo-600 font-bold'>${info.th}</div>`;
                    if(info.eng) tNameHtml += `<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>${info.eng}</div>`;
                    
                    let dName = techDeptMap[r.technician_name] || 'General';
                    deptEng = `<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold tracking-wider mb-1 shadow-sm'>${dName}</div>`;
                    if (info.pos) deptEng += `<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>${info.pos}</div>`;
                }

                let imgIcon = r.image_path ? `<i class="fas fa-image text-slate-300 ml-1" title="มีรูปภาพ"></i>` : '';
                let cause = (!r.root_cause || r.root_cause === '-') ? `<span class='text-rose-500 font-bold'>-</span>` : `<span class='text-slate-700 font-medium'>${r.root_cause}</span>`;

                // ✨ แก้ลิงก์ปุ่ม Action ให้ไปหน้า update_repair.php เหมือนแอดมิน ✨
                let actionBtn = `<a target="_blank" href="update_repair.php?id=${r.id}" class="w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100 shadow-sm" title="Edit"><i class="fas fa-pen-to-square"></i></a>`;

                html += `
                    <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-100 last:border-0">
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class="font-medium text-slate-700">${dt[0]}</div>
                            ${dt[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${dt[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-top font-mono font-semibold text-slate-600">${r.repair_code || 'MR-'+r.id}</td>
                        <td class="px-6 py-4 align-top">
                            <div class='flex items-center gap-3'>
                                <!-- ✨ ปรับสีไอคอนประจำตัวผู้แจ้งให้เข้มขึ้นเหมือนแอดมิน ✨ -->
                                <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0'><i class='fas fa-user text-xs'></i></div>
                                <div>
                                    <div class="text-slate-800 font-bold">${dName}</div>
                                    <div class="text-slate-500 text-[11px] font-medium mt-0.5">${dPhone}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="text-slate-800 font-bold">${r.equipment || r.problem || 'ไม่ระบุ'} ${imgIcon}</div>
                            <div class="text-slate-500 text-[11px] font-medium mt-0.5 max-w-[180px] truncate" title="${r.problem_desc || '-'}">${r.problem_desc || '-'}</div>
                        </td>
                        <td class="px-6 py-4 align-top">${deptEng}</td>
                        <td class="px-6 py-4 align-top">${tNameHtml}</td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-medium text-slate-700'>${rec[0]}</div>
                            ${rec[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${rec[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-top">${cause}</td>
                        <td class="px-6 py-4 align-middle text-center"><span class="px-3 py-1 rounded-full text-[11px] font-bold border ${bClass} inline-block shadow-sm">${st}</span></td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-medium text-emerald-700'>${com[0]}</div>
                            ${com[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${com[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-middle text-center">
                            <div class='flex items-center justify-center'>
                                ${actionBtn}
                            </div>
                        </td>
                    </tr>`;
            });
            tbody.innerHTML = html;
        }

        // เริ่มวาดข้อมูลตอนโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', () => {
            renderAllCharts();
            renderDashTable();
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
</body>
</html>
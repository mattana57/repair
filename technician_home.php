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

// จัดการการอัปเดตหมายเหตุจากหน้าเว็บ
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

    <!-- Header & Tabs (แบบใหม่ ไม่มีแถบสีเทา คลีนๆ) -->
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

            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">ยินดีต้อนรับ คุณ<?= htmlspecialchars($full_name) ?> 👋</h1>
                    <p class="text-indigo-100 text-sm max-w-xl font-light">จัดการใบงาน ตรวจสอบสถิติ และอัปเดตหมายเหตุงานซ่อมของคุณได้จากแดชบอร์ดส่วนตัวนี้ หรือกดรับงานผ่าน LINE Bot ตามปกติ</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
                    <span class="block text-xs uppercase tracking-wider text-indigo-200 mb-1">สถานะระบบ</span>
                    <span class="flex items-center gap-2 font-bold text-sm"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> พร้อมปฏิบัติงาน</span>
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
                            <h3 class="font-extrabold text-slate-800 text-lg">รายการรับแจ้งซ่อมล่าสุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">ประวัติการทำงานของคุณ</p>
                        </div>
                        <button onclick="showTab('history')" class="flex items-center text-sm text-slate-600 font-bold hover:text-indigo-600 transition-colors group">
                            See All <i class="fas fa-arrow-right ml-2 text-xs text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-1"></i>
                        </button>
                    </div>
                    <!-- ✨ เปลี่ยนโครงสร้าง Class ให้เหมือนฝั่ง Admin 100% ✨ -->
                    <div class="overflow-x-auto w-full pb-4 custom-scrollbar table-wrapper-fix">
                        <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-extrabold">
                                <tr>
                                    <th class="px-6 py-4 border-0">Date / Time</th>
                                    <th class="px-6 py-4 border-0">Ticket No.</th>
                                    <th class="px-6 py-4 border-0">Reporter</th>
                                    <th class="px-6 py-4 border-0">Equipment</th>
                                    <th class="px-6 py-4 text-center border-0">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white" id="dashTableBody">
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
                <!-- ✨ โครงสร้างตารางแบบหน้า Team Management ✨ -->
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
                                <input type="text" id="searchHistoryInput" oninput="renderHistoryTable()" placeholder="ค้นหา รหัสงาน, ชื่อผู้แจ้ง, อุปกรณ์..." class="w-full bg-white border border-slate-200 text-sm rounded-xl pl-10 pr-4 py-2.5 h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium shadow-sm">
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <select id="filterStatus" onchange="renderHistoryTable()" class="bg-white border border-slate-200 text-sm text-slate-700 rounded-xl px-3 py-2.5 h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold shadow-sm outline-none cursor-pointer">
                                    <option value="all">ทุกสถานะ</option>
                                    <option value="รอรับเรื่อง">รอรับเรื่อง</option>
                                    <option value="กำลังดำเนินการ">กำลังดำเนินการ</option>
                                    <option value="ซ่อมเสร็จแล้ว">ซ่อมเสร็จแล้ว</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- ตารางแบบ History Modal (แสดงครบถ้วนเหมือนรูปที่ 40) -->
                    <!-- ✨ เปลี่ยนโครงสร้าง Class ให้เหมือนฝั่ง Admin 100% ✨ -->
                    <div class="overflow-x-auto w-full pb-4 custom-scrollbar table-wrapper-fix">
                        <table class="w-full text-left whitespace-nowrap min-w-[1100px]" id="historyTableFull">
                            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-extrabold sticky top-0 z-20 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4 border-0">Date / Time</th>
                                    <th class="px-6 py-4 border-0">Ticket No.</th>
                                    <th class="px-6 py-4 border-0">Reporter</th>
                                    <th class="px-6 py-4 border-0">Equipment</th>
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

    <!-- ✨ ลบ Modal ทิ้งทั้งหมด (เพราะเราจะให้ปุ่ม Action ลิงก์ไปที่ update_repair.php แทน) ✨ -->

    <!-- Javascript สำหรับวาดกราฟและตาราง -->
    <script>
        const repairsData = <?php echo json_encode($repairs); ?>;
        const lineUsersMap = <?php echo $line_users_map_json; ?>;
        
        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Kanit', sans-serif";
        Chart.defaults.color = '#64748b';

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
                document.getElementById('filterStatus').value = statusFilter;
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

                let eq = r.equipment || r.problem || 'ไม่ระบุ';
                eqMap[eq] = (eqMap[eq] || 0) + 1;

                let loc = r.location || 'ไม่ระบุสถานที่';
                if (loc !== 'ไม่ระบุสถานที่' && loc.trim() !== '') locMap[loc] = (locMap[loc] || 0) + 1;

                let repNameRaw = r.reporter_name || 'ไม่ระบุผู้แจ้ง';
                let repName = lineUsersMap[repNameRaw] ? lineUsersMap[repNameRaw] : repNameRaw;
                reporterMap[repName] = (reporterMap[repName] || 0) + 1;
            });

            // Equipment Chart
            let eqSorted = Object.keys(eqMap).map(k => ({name: k, count: eqMap[k]})).sort((a,b) => b.count - a.count).slice(0, 6);
            const eqCtx = document.getElementById('eqChart').getContext('2d');
            let gradient = eqCtx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)'); gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            new Chart(eqCtx, {
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
            let totalStatus = pending + progress + completed;
            new Chart(statusCtx, {
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
            new Chart(locCtx, {
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
                let dName = lineUsersMap[r.reporter_name] ? lineUsersMap[r.reporter_name] : (r.reporter_name || 'ไม่ระบุ');
                let imgIcon = r.image_path ? `<i class="fas fa-image text-slate-300 ml-1" title="มีรูปภาพ"></i>` : '';

                html += `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 align-top">
                            <div class="text-slate-800 font-bold">${dt[0]}</div>
                            <div class="text-blue-600 font-bold text-[11px] mt-0.5">${dt[1].substring(0, 5)}</div>
                        </td>
                        <td class="px-6 py-4 align-top font-mono font-bold text-slate-600">${r.repair_code || 'MR-'+r.id}</td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-400 flex items-center justify-center text-xs shrink-0"><i class="fas fa-user"></i></div>
                                <div>
                                    <div class="font-bold text-slate-800">${dName}</div>
                                    <div class="text-[11px] text-slate-500 font-medium mt-0.5">${r.reporter_phone || '-'}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="font-bold text-slate-800">${r.equipment || r.problem || 'ไม่ระบุ'} ${imgIcon}</div>
                            <div class="text-[11px] text-slate-500 font-medium mt-0.5">${r.location || '-'}</div>
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
                // ✨ อัปเดตคลาสสีสถานะให้สว่างและสวยเหมือน Admin ✨
                let bClass = (st === 'ซ่อมเสร็จแล้ว' || st === 'เสร็จสิ้น') ? 'text-emerald-600 bg-emerald-50 border-emerald-200' :
                             (st === 'กำลังดำเนินการ') ? 'text-sky-600 bg-sky-50 border-sky-200' : 'text-amber-600 bg-amber-50 border-amber-200';
                
                let dt = (r.created_at && r.created_at !== '0000-00-00 00:00:00') ? r.created_at.split(' ') : ['-', ''];
                let rec = (r.received_at && r.received_at !== '0000-00-00 00:00:00') ? r.received_at.split(' ') : ['-', ''];
                let com = (r.completed_at && r.completed_at !== '0000-00-00 00:00:00') ? r.completed_at.split(' ') : ['-', ''];
                
                let dName = lineUsersMap[r.reporter_name] ? lineUsersMap[r.reporter_name] : (r.reporter_name || 'ไม่ระบุ');
                let imgIcon = r.image_path ? `<i class="fas fa-image text-slate-300 ml-1" title="มีรูปภาพ"></i>` : '';
                let cause = (!r.root_cause || r.root_cause === '-') ? `<span class='text-rose-500 font-bold'>-</span>` : `<span class='text-slate-700 font-medium'>${r.root_cause}</span>`;

                // ✨ แก้ลิงก์ปุ่ม Action ให้ไปหน้า update_repair.php เหมือนแอดมิน ✨
                let actionBtn = `<a target="_blank" href="update_repair.php?id=${r.id}" class="w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100 shadow-sm" title="Edit"><i class="fas fa-pen-to-square"></i></a>`;

                html += `
                    <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-100 last:border-0">
                        <td class="px-6 py-4 align-top">
                            <div class="font-bold text-slate-800">${dt[0]}</div>
                            ${dt[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${dt[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-top font-mono font-bold text-slate-600">${r.repair_code || 'MR-'+r.id}</td>
                        <td class="px-6 py-4 align-top">
                            <div class='flex items-center gap-3'>
                                <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shrink-0'><i class='fas fa-user text-xs'></i></div>
                                <div>
                                    <div class="text-slate-800 font-bold">${dName}</div>
                                    <div class="text-slate-500 text-[11px] font-medium mt-0.5">${r.reporter_phone || '-'}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="text-slate-800 font-bold">${r.equipment || r.problem || 'ไม่ระบุ'} ${imgIcon}</div>
                            <div class="text-slate-500 text-[11px] font-medium mt-0.5 max-w-[180px] truncate" title="${r.problem_desc || '-'}">${r.problem_desc || '-'}</div>
                        </td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-bold text-slate-800'>${rec[0]}</div>
                            ${rec[1] ? `<div class="text-[11px] text-blue-600 font-bold mt-0.5">${rec[1].substring(0, 5)}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 align-top">${cause}</td>
                        <td class="px-6 py-4 align-middle text-center"><span class="px-3 py-1 rounded-full text-[11px] font-bold border ${bClass} inline-block shadow-sm">${st}</span></td>
                        <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-bold text-emerald-700'>${com[0]}</div>
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
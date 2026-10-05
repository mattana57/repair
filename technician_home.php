<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ ต้องเป็น Technician
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
if ($conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'")->num_rows > 0) {
    $where .= " OR technician_name = '$safe_full_name'";
}
$where .= ")";

// จัดการการอัปเดตหมายเหตุจากหน้าเว็บ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_remark') {
    $repair_id = intval($_POST['repair_id']);
    $remark = $conn->real_escape_string($_POST['remark']);
    
    // อัปเดตงานโดยใช้เงื่อนไข Ultra-Link
    if($conn->query("UPDATE repairs SET remark = '$remark' WHERE id = $repair_id AND $where")) {
        $msg = "บันทึกหมายเหตุสำเร็จเรียบร้อยครับ";
    }
}

// 5. ดึงสถิติภาพรวม 4 สถานะด้วยระบบ Ultra-Link
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

// 6. ดึงประวัติรายการแจ้งซ่อมทั้งหมดด้วยระบบ Ultra-Link
$repairs = [];
$res_repairs = $conn->query("SELECT * FROM repairs WHERE $where ORDER BY created_at DESC");
if ($res_repairs) {
    while ($row = $res_repairs->fetch_assoc()) {
        $repairs[] = $row;
    }
}

// ดึงข้อมูล Line Users มาแมปเพื่อแสดงชื่อจริง
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

// ดึงข้อมูล Technician สำหรับแผนกและตำแหน่ง
$tech_info_map = [];
$tech_dept_map = [];
$res_tech_info = $conn->query("SELECT full_name, english_name, position, department FROM technicians");
if ($res_tech_info) {
    while ($t = $res_tech_info->fetch_assoc()) {
        $tech_info_map[$t['full_name']] = [
            'th' => $t['full_name'],
            'eng' => $t['english_name'] ?? '',
            'pos' => $t['position'] ?? ''
        ];
        $tech_dept_map[$t['full_name']] = $t['department'] ?? 'General';
    }
}

// ฟังก์ชันแปลงชื่อไทย-อังกฤษ กรณีไม่มีใน Map
function splitThaiEngName($fullName, $engName) {
    $th = trim((string)$fullName);
    $en = trim((string)$engName);
    if (empty($en) && !empty($th)) {
        if (preg_match('/^(.*?)\s*\((.*?)\)$/', $th, $matches)) {
            $th = trim($matches[1]);
            $en = trim($matches[2]);
        } elseif (preg_match('/^(.*?)\s+(Mr\.|Mrs\.|Miss|Ms\.)\s*(.*)$/i', $th, $matches)) {
            $th = trim($matches[1]);
            $en = trim($matches[2]) . ' ' . trim($matches[3]);
        }
    }
    return array($th, $en);
}

function getAutoPosition($th_name) {
    $map = [
        'สมพร วงษ์จำปา' => 'นักวิชาการคอมพิวเตอร์',
        'ปริญญา จันทรภา' => 'นักวิชาการคอมพิวเตอร์',
        'ทองสน พลมีศักดิ์' => 'นักวิชาการคอมพิวเตอร์',
        'ธีรศักดิ์ พาโคกทม' => 'นักวิชาการคอมพิวเตอร์',
        'จิตรณรงค์ นาใจคง' => 'นักวิชาการโสตทัศนศึกษา',
        'ลำไพร ทองบ่อ' => 'นักวิชาการโสตทัศนศึกษา',
        'รักชาติ แดงเทโพธิ์' => 'นักวิชาการโสตทัศนศึกษา',
        'ปิยะสันต์ บุญพระ' => 'นักวิชาการโสตทัศนศึกษา',
        'จตุพล ฤทธิสิงห์' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'อาทิตย์ บรรเทา' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'ธวัชชัย รัสสมบัติ' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'ทรงภพ จันทร์ลอย' => 'เจ้าหน้าที่บริหารงานทั่วไป',
        'รนภักดี ลิงลม' => 'พนักงานขับรถยนต์',
        'กิตติภณ รัดถา' => 'พนักงานขับรถยนต์',
        'ทิวา เนื่องทะบาล' => 'พนักงานขับรถยนต์',
        'นิรุตติ์ กองเงิน' => 'พนักงานขับรถยนต์',
        'อุทัย หาหอม' => 'พนักงานขับรถยนต์'
    ];
    foreach($map as $key => $val) {
        if(mb_strpos($th_name, $key) !== false) return $val;
    }
    return '';
}

// เตรียมข้อมูลเดือน/ปี สำหรับ Dropdown กรองข้อมูล
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

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dash';

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard | MBS Repair</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Prompt & Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .modern-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); border: 1px solid #f1f5f9; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .table-wrapper-fix { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        
        /* สไตล์ตารางฝั่ง History (แบบเดียวกับ Admin) */
        #historyTable th {
            padding: 0.85rem 3px !important;
            font-size: 9.5px !important;
            letter-spacing: 0px !important;
            white-space: nowrap !important;
            word-break: keep-all !important;
            background-color: #fef9c3 !important; 
            border-bottom: 1px solid #fef08a !important;
        }
        #historyTable th:first-child, #historyTable td:first-child { padding-left: 10px !important; }
        #historyTable th:last-child, #historyTable td:last-child { padding-right: 10px !important; }
        #historyTable td {
            padding: 0.75rem 3px !important;
            font-size: 11.5px !important;
            white-space: nowrap !important;
        }
        #historyTable td:nth-child(4) { white-space: normal !important; max-width: 115px !important; }
        #historyTable td:nth-child(4) > div.truncate { max-width: 110px !important; }
        #historyTable td:nth-child(8) { white-space: normal !important; max-width: 90px !important; }
        
        .badge-pending { background-color: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #fde68a;}
        .badge-progress { background-color: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #c7d2fe;}
        .badge-success { background-color: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; border: 1px solid #a7f3d0;}
    </style>
</head>
<body class="text-slate-700 flex flex-col h-screen overflow-hidden">

    <!-- Top Navbar -->
    <nav class="bg-indigo-600 text-white shadow-md sticky top-0 z-40 shrink-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-xl">
                        <i class="fas fa-tools text-xl"></i>
                    </div>
                    <div>
                        <span class="font-bold text-lg tracking-wide">MBS <span class="font-light">Technician Portal</span></span>
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

    <!-- Main Content Area with Scrollbar -->
    <main id="mainScrollContainer" class="flex-1 overflow-y-auto w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <?php if (!empty($msg)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl mb-6 flex items-center shadow-sm">
                    <i class="fas fa-check-circle mr-2.5 text-lg"></i> <?= $msg ?>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-3xl p-6 md:p-8 text-white shadow-lg mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">ยินดีต้อนรับกลับ, คุณ<?= htmlspecialchars($full_name) ?> 👋</h1>
                    <p class="text-indigo-100 text-sm max-w-xl font-light">จัดการใบงาน ตรวจสอบสถิติส่วนตัว และดูผลการดำเนินงานของคุณได้จากหน้านี้</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
                    <span class="block text-xs uppercase tracking-wider text-indigo-200 mb-1">สถานะระบบ</span>
                    <span class="flex items-center gap-2 font-bold text-sm"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span> พร้อมปฏิบัติงาน</span>
                </div>
            </div>

            <!-- Stats Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="modern-card relative overflow-hidden p-4 sm:p-6 flex flex-col justify-between border-b-4 border-b-purple-500 shadow-[0_6px_20px_-4px_rgba(15,23,42,0.05)] hover:shadow-[0_12px_25px_-4px_rgba(168,85,247,0.18)] hover:-translate-y-0.5 transition-all duration-300">
                    <div class="flex flex-col xl:flex-row justify-between items-start mb-3 gap-2">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-purple-50 border border-purple-100 shadow-inner flex items-center justify-center text-purple-600 text-lg sm:text-xl shrink-0">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-800"><?= $stats['total'] ?></h3>
                        <p class="text-[11px] sm:text-sm font-bold text-slate-500 mt-0.5 sm:mt-1 truncate">งานที่รับผิดชอบทั้งหมด</p>
                    </div>
                </div>
                
                <div class="modern-card relative overflow-hidden p-4 sm:p-6 flex flex-col justify-between border-b-4 border-b-amber-400 shadow-[0_6px_20px_-4px_rgba(15,23,42,0.05)] hover:shadow-[0_12px_25px_-4px_rgba(251,191,36,0.18)] hover:-translate-y-0.5 transition-all duration-300">
                    <div class="flex flex-col xl:flex-row justify-between items-start mb-3 gap-2">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-[#fef3c7]/70 border border-amber-200/70 shadow-inner flex items-center justify-center text-[#d97706] text-lg sm:text-xl shrink-0">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-800"><?= $stats['pending'] ?></h3>
                        <p class="text-[11px] sm:text-sm font-bold text-slate-500 mt-0.5 sm:mt-1 truncate">รอรับเรื่อง</p>
                    </div>
                </div>

                <div class="modern-card relative overflow-hidden p-4 sm:p-6 flex flex-col justify-between border-b-4 border-b-sky-400 shadow-[0_6px_20px_-4px_rgba(15,23,42,0.05)] hover:shadow-[0_12px_25px_-4px_rgba(56,189,248,0.18)] hover:-translate-y-0.5 transition-all duration-300">
                    <div class="flex flex-col xl:flex-row justify-between items-start mb-3 gap-2">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-[#e0f2fe]/70 border border-sky-200/70 shadow-inner flex items-center justify-center text-sky-500 text-lg sm:text-xl shrink-0">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-800"><?= $stats['in_progress'] ?></h3>
                        <p class="text-[11px] sm:text-sm font-bold text-slate-500 mt-0.5 sm:mt-1 truncate">กำลังดำเนินการ</p>
                    </div>
                </div>

                <div class="modern-card relative overflow-hidden p-4 sm:p-6 flex flex-col justify-between border-b-4 border-b-emerald-400 shadow-[0_6px_20px_-4px_rgba(15,23,42,0.05)] hover:shadow-[0_12px_25px_-4px_rgba(52,211,153,0.18)] hover:-translate-y-0.5 transition-all duration-300">
                    <div class="flex flex-col xl:flex-row justify-between items-start mb-3 gap-2">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-[#d1fae5]/70 border border-emerald-200/70 shadow-inner flex items-center justify-center text-[#059669] text-lg sm:text-xl shrink-0">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-800"><?= $stats['completed'] ?></h3>
                        <p class="text-[11px] sm:text-sm font-bold text-slate-500 mt-0.5 sm:mt-1 truncate">ซ่อมเสร็จแล้ว</p>
                    </div>
                </div>
            </div>

            <!-- ปุ่มเมนูแท็บแบบ Capsule -->
            <div class="flex items-center gap-2.5 mb-8">
                <button onclick="showTab('dash')" id="btn-dash" class="px-6 py-2 <?= $active_tab === 'dash' ? 'bg-indigo-600 text-white border border-indigo-600 shadow-md shadow-indigo-200' : 'bg-white text-slate-600 shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300' ?> text-sm font-bold rounded-full transition-colors cursor-pointer">
                    ทั้งหมด
                </button>
                <button onclick="showTab('history')" id="btn-history" class="px-6 py-2 <?= $active_tab === 'history' ? 'bg-indigo-600 text-white border border-indigo-600 shadow-md shadow-indigo-200' : 'bg-white text-slate-600 shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300' ?> text-sm font-bold rounded-full transition-colors cursor-pointer">
                    ประวัติงาน
                </button>
            </div>

            <!-- ========================================== -->
            <!-- SECTION: Dashboard Overview (ทั้งหมด) -->
            <div id="dash" class="section <?= $active_tab === 'dash' ? '' : 'hidden' ?> space-y-6">
                <!-- กราฟแถวที่ 1: Equipment & Status -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Line Chart -->
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">สถิติอุปกรณ์ที่คุณได้รับมอบหมาย</p>
                        </div>
                        <div class="h-64 w-full">
                            <canvas id="eqChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Doughnut Chart -->
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">สัดส่วนสถานะการดำเนินงาน</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">สถานะงานซ่อมทั้งหมดของคุณ</p>
                        </div>
                        <div class="h-64 w-full flex justify-center pb-4">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- กราฟแถวที่ 2: Locations & Top Reporters -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Bar Chart (Horizontal) -->
                    <div class="modern-card p-6 flex flex-col">
                        <div class="mb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">สถานที่เกิดปัญหาบ่อยที่สุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">ห้องหรืออาคารที่คุณไปซ่อมบ่อยๆ</p>
                        </div>
                        <div class="h-64 w-full">
                            <canvas id="locChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Reporters List -->
                    <div class="modern-card p-6 flex flex-col h-full">
                        <div class="mb-4 border-b border-slate-100 pb-4">
                            <h3 class="font-extrabold text-slate-800 text-lg truncate">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5 truncate">รายชื่อผู้ใช้งานที่คุณให้บริการบ่อย</p>
                        </div>
                        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar max-h-[256px]" id="topReportersContainer">
                            <!-- Javascript จะวาดรายชื่อตรงนี้ -->
                        </div>
                    </div>
                </div>

                <!-- Table: รายการรับแจ้งซ่อมล่าสุด -->
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 bg-white flex justify-between items-center">
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">รายการรับแจ้งซ่อมล่าสุด</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">ประวัติการทำงานของคุณ</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full pb-4 custom-scrollbar table-wrapper-fix">
                        <table class="w-full text-left whitespace-nowrap min-w-[900px]">
                            <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                                <tr>
                                    <th class="px-6 py-4">DATE / TIME</th>
                                    <th class="px-6 py-4">TICKET NO.</th>
                                    <th class="px-6 py-4">REPORTER</th>
                                    <th class="px-6 py-4">EQUIPMENT</th>
                                    <th class="px-6 py-4 text-center">STATUS</th>
                                    <th class="px-6 py-4 text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php 
                                $recent_repairs = array_slice($repairs, 0, 5); // เอาแค่ 5 งานล่าสุด
                                if (count($recent_repairs) > 0): 
                                ?>
                                    <?php foreach ($recent_repairs as $job): 
                                        $created_at = !empty($job['created_at']) ? strtotime($job['created_at']) : time();
                                        $date_str = date('Y-m-d', $created_at);
                                        $time_str = date('H:i', $created_at);
                                        
                                        $status = trim($job['status'] ?? 'รอดำเนินการ');
                                        if ($status === 'เสร็จสิ้น' || $status === 'ซ่อมเสร็จแล้ว') {
                                            $badgeClass = 'badge-success';
                                        } elseif ($status === 'กำลังดำเนินการ') {
                                            $badgeClass = 'badge-progress';
                                        } else {
                                            $badgeClass = 'badge-pending';
                                        }

                                        $raw_name = trim($job['reporter_name'] ?? '');
                                        $display_name = isset($line_users_map[$raw_name]) ? $line_users_map[$raw_name] : ($raw_name !== '' ? $raw_name : 'ไม่ระบุ');
                                    ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="text-slate-800 font-bold"><?= $date_str ?></div>
                                                <div class="text-blue-600 font-bold text-[11px] mt-0.5"><?= $time_str ?></div>
                                            </td>
                                            <td class="px-6 py-4 font-mono font-bold text-slate-600"><?= htmlspecialchars($job['repair_code'] ?? 'MR-'.$job['id']) ?></td>
                                            <td class="px-6 py-4 flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-400 flex items-center justify-center text-xs"><i class="fas fa-user"></i></div>
                                                <div>
                                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($display_name) ?></div>
                                                    <div class="text-[11px] text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($job['reporter_phone'] ?? '-') ?></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-slate-800">
                                                    <?= htmlspecialchars($job['equipment'] ?? $job['problem'] ?? 'ไม่ระบุ') ?>
                                                    <?php if(isset($job['image_path']) && !empty($job['image_path'])): ?>
                                                        <i class="fas fa-image text-slate-300 ml-1" title="มีรูปภาพ"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-[11px] text-slate-500 font-medium mt-0.5 max-w-[200px] truncate" title="<?= htmlspecialchars(strip_tags($job['problem_desc'] ?? '-')) ?>"><?= htmlspecialchars($job['problem_desc'] ?? '-') ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="<?= $badgeClass ?> inline-block shadow-sm"><?= htmlspecialchars($status) ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <button onclick="openModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['remark'] ?? '')) ?>')" class="w-8 h-8 rounded border border-slate-200 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors inline-flex items-center justify-center shadow-sm" title="เพิ่ม/แก้ไขหมายเหตุ">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-slate-400">
                                            <i class="fas fa-folder-open text-4xl mb-3 text-slate-200 block"></i>
                                            ยังไม่มีประวัติการรับงานในระบบ
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION: ประวัติงาน (History) -->
            <div id="history" class="section <?= $active_tab === 'history' ? '' : 'hidden' ?> space-y-6">
                <div class="modern-card overflow-hidden flex flex-col h-[75vh] max-h-[850px]">
                    <div class="px-5 py-4 xl:pb-3 border-b border-slate-100 flex flex-col gap-4 xl:gap-3 bg-slate-50 rounded-t-3xl shrink-0 transition-all duration-300 relative z-30">
                        <div class="flex justify-between items-start sm:items-center w-full">
                            <p class="text-lg md:text-xl font-extrabold text-slate-800 sm:truncate flex-1 leading-tight">
                                ประวัติงานเจ้าหน้าที่: <span class="block sm:inline mt-0.5 sm:mt-0"><?= htmlspecialchars($full_name) ?></span>
                            </p>
                        </div>
                        <div class="flex flex-wrap landscape:flex-nowrap items-center justify-end portrait:justify-start md:portrait:justify-end gap-3 w-full">
                            
                            <!-- Search -->
                            <div class="relative flex-1 min-w-[120px] xl:flex-none xl:w-[450px] 2xl:w-[500px] group">
                                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input type="text" id="searchHistoryInput" oninput="renderHistoryTable()" placeholder="ค้นหาข้อมูลในตาราง..." class="w-full bg-white border border-slate-200 text-sm rounded-xl pl-10 pr-[95px] py-2.5 h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium shadow-sm">
                            </div>

                            <div class="flex portrait:flex-nowrap flex-wrap items-center gap-2 portrait:gap-1.5 sm:gap-2 landscape:gap-2 shrink-0">
                                <!-- Month Dropdown -->
                                <div class="relative w-[110px] portrait:w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="history-MonthContainer" tabindex="0" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm portrait:text-xs text-slate-700 rounded-xl px-4 portrait:px-3 py-2.5 portrait:py-2 h-[42px] portrait:h-[38px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleDropdown('historyMonth')">
                                        <span id="historyMonthText" class="truncate"><?php echo $current_month_name; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="historyMonthList" class="absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <?php foreach($thai_months as $num => $name) { 
                                            $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); 
                                            echo "<div class='px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' onclick=\"selectFilter('historyMonth', '{$num_pad}', '{$name}')\">{$name}</div>"; 
                                        } ?>
                                    </div>
                                    <input type="hidden" id="historyMonth" value="<?= str_pad(date('n'), 2, '0', STR_PAD_LEFT) ?>">
                                </div>

                                <!-- Year Dropdown -->
                                <div class="relative w-[110px] portrait:w-[85px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="history-YearContainer" tabindex="0" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm portrait:text-xs text-slate-700 rounded-xl px-4 portrait:px-2.5 py-2.5 portrait:py-2 h-[42px] portrait:h-[38px] focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleDropdown('historyYear')">
                                        <span id="historyYearText" class="truncate"><?php echo $current_thai_year; ?></span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="historyYearList" class="absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <?php foreach($available_years as $y) { 
                                            $thai_y = $y + 543; 
                                            echo "<div class='px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' onclick=\"selectFilter('historyYear', '{$y}', '{$thai_y}')\">{$thai_y}</div>"; 
                                        } ?>
                                    </div>
                                    <input type="hidden" id="historyYear" value="<?= date('Y') ?>">
                                </div>
                                <button onclick="resetFilters()" class="h-[42px] portrait:h-[38px] w-[42px] portrait:w-[38px] flex justify-center items-center rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-400 hover:text-rose-500 shadow-sm transition-colors" title="รีเซ็ตตัวกรอง">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table Data (History Modal Style) -->
                    <div class="p-0 md:p-6 xl:pt-3 overflow-hidden flex-1 bg-[#f8fafc]">
                        <div class="w-full h-full overflow-x-auto md:rounded-2xl md:border border-slate-200 shadow-sm relative custom-scrollbar bg-white" id="historyTableContainer">
                            <table class="w-full text-left whitespace-nowrap min-w-[1200px]" id="historyTable">
                                <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                                    <tr>
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
                                <tbody class="text-sm divide-y divide-slate-100 bg-white" id="historyTableBody">
                                    <!-- Javascript จะวาดตารางตรงนี้ -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- Modal เพิ่ม/แก้ไขหมายเหตุ -->
    <div id="noteModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6 shadow-xl transform transition-all">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-slate-800">เพิ่ม/แก้ไขหมายเหตุ</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-rose-500 transition-colors bg-slate-50 hover:bg-rose-50 w-8 h-8 rounded-full flex justify-center items-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_remark">
                <input type="hidden" name="repair_id" id="modal_repair_id" value="">
                
                <div class="mb-5">
                    <textarea name="remark" id="modal_remark" rows="4" class="w-full border border-slate-200 rounded-xl p-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none resize-none text-slate-700 bg-slate-50" placeholder="พิมพ์ข้อความที่ต้องการแจ้งให้ผู้ใช้งานทราบ..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-bold transition-colors">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-md shadow-indigo-200 transition-all">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Javascript สำหรับวาดกราฟและตารางทั้งหมด -->
    <script>
        const repairsData = <?php echo json_encode($repairs); ?>;
        const lineUsersMap = <?php echo $line_users_map_json; ?>;
        const techInfoMap = <?php echo json_encode($tech_info_map); ?>;
        const techDeptMap = <?php echo json_encode($tech_dept_map); ?>;
        
        Chart.defaults.font.family = "'Prompt', sans-serif";
        Chart.defaults.color = '#64748b';

        // --------------------------------------------------------
        // ระบบ Tab Navigation
        // --------------------------------------------------------
        function showTab(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            
            // รีเซ็ตปุ่ม
            const btnDash = document.getElementById('btn-dash');
            const btnHist = document.getElementById('btn-history');
            const activeStyle = 'px-6 py-2 bg-indigo-600 text-white border border-indigo-600 shadow-md shadow-indigo-200 text-sm font-bold rounded-full transition-colors cursor-pointer';
            const inactiveStyle = 'px-6 py-2 bg-white text-slate-600 shadow-sm border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-300 text-sm font-bold rounded-full transition-colors cursor-pointer';
            
            btnDash.className = inactiveStyle;
            btnHist.className = inactiveStyle;
            
            if(id === 'dash') {
                btnDash.className = activeStyle;
                renderAllCharts(); // ให้วาดกราฟเมื่อกลับมาหน้า Dashboard
            } else if(id === 'history') {
                btnHist.className = activeStyle;
                renderHistoryTable(); // ให้วาดตารางเมื่อมาหน้า History
            }

            // Update URL query string
            history.replaceState(null, '', '?tab=' + id);
        }

        // --------------------------------------------------------
        // ระบบ Filter Dropdown ในหน้า History
        // --------------------------------------------------------
        function toggleDropdown(idPrefix) {
            const list = document.getElementById(idPrefix + 'List');
            document.querySelectorAll('.chart-dropdown-list').forEach(l => {
                if (l.id !== list.id) { l.classList.add('hidden'); l.classList.remove('flex'); }
            });
            list.classList.toggle('hidden');
            list.classList.toggle('flex');
        }

        function selectFilter(idPrefix, val, display) {
            document.getElementById(idPrefix).value = val;
            document.getElementById(idPrefix + 'Text').innerText = display;
            const list = document.getElementById(idPrefix + 'List');
            list.classList.add('hidden'); list.classList.remove('flex');
            
            renderHistoryTable();
        }

        function resetFilters() {
            document.getElementById('searchHistoryInput').value = '';
            selectFilter('historyMonth', 'all', 'ทั้งหมด');
            selectFilter('historyYear', 'all', 'ทุกปี');
        }

        document.addEventListener('click', function(e) {
            document.querySelectorAll('.chart-dropdown-list').forEach(list => {
                if (!list.parentElement.contains(e.target)) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                }
            });
        });

        function formatValJS(val) {
            if (!val || String(val).trim() === '-' || String(val).trim() === '') return "<span class='text-rose-500 font-bold'>-</span>";
            if (String(val).trim() === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
            return val;
        }

        // --------------------------------------------------------
        // ฟังก์ชันวาดตารางหน้า History (History Modal Style)
        // --------------------------------------------------------
        function renderHistoryTable() {
            const tbody = document.getElementById('historyTableBody');
            if(!tbody) return;

            let filterText = document.getElementById('searchHistoryInput').value.toLowerCase().replace(/\s+/g, '');
            let monthFilter = document.getElementById('historyMonth').value;
            let yearFilter = document.getElementById('historyYear').value;

            // กรองข้อมูล
            let filteredData = repairsData.filter(r => {
                let dateMatch = true;
                let textMatch = true;

                // กรองข้อความ
                if (filterText !== '') {
                    let searchStr = (
                        (r.repair_code || '') + 
                        (r.reporter_name || '') + 
                        (lineUsersMap[r.reporter_name] || '') + 
                        (r.equipment || '') + 
                        (r.problem || '') + 
                        (r.location || '') + 
                        (r.status || '')
                    ).toLowerCase().replace(/\s+/g, '');
                    
                    if (!searchStr.includes(filterText)) textMatch = false;
                }

                // กรองเดือน/ปี
                if (monthFilter !== 'all' || yearFilter !== 'all') {
                    if (!r.created_at || r.created_at === '0000-00-00 00:00:00') {
                        dateMatch = false;
                    } else {
                        let datePart = r.created_at.split(' ')[0];
                        let parts = datePart.split('-');
                        let rYear = parts[0];
                        let rMonth = parts[1];
                        
                        if (monthFilter !== 'all' && rMonth !== monthFilter) dateMatch = false;
                        if (yearFilter !== 'all' && rYear !== yearFilter) dateMatch = false;
                    }
                }

                return textMatch && dateMatch;
            });

            // วาดตาราง
            tbody.innerHTML = '';
            if(filteredData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="11" class="px-6 h-[400px] text-center align-middle text-slate-400 font-medium">ไม่พบข้อมูลใบงาน</td></tr>`;
            } else {
                filteredData.forEach(r => {
                    let statusClass = 'badge-pending';
                    if(r.status === 'กำลังดำเนินการ') statusClass = 'badge-progress';
                    else if(r.status === 'ซ่อมเสร็จแล้ว' || r.status === 'เสร็จสิ้น') statusClass = 'badge-success';

                    let statusText = formatValJS(r.status || 'รอดำเนินการ');
                    let ticket_no = formatValJS(r.repair_code || 'MR-'+r.id);

                    // เวลาแจ้ง (Date / Time)
                    let createdDate = "<span class='text-rose-500 font-bold'>-</span>";
                    let createdTime = '';
                    if(r.created_at && r.created_at != '0000-00-00 00:00:00') {
                        let parts = r.created_at.split(' ');
                        createdDate = parts[0];
                        createdTime = parts[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${parts[1].substring(0, 5)}</div>` : '';
                    }

                    // ผู้แจ้ง (Reporter)
                    let rNameRaw = (r.reporter_name || 'ไม่ระบุ').trim();
                    let dispName = lineUsersMap[rNameRaw] ? lineUsersMap[rNameRaw] : rNameRaw;
                    let rName = formatValJS(dispName);
                    let rPhone = formatValJS(r.phone_number);

                    // อุปกรณ์ (Equipment)
                    let eqType = formatValJS(r.equipment || r.problem);
                    let pDesc = formatValJS(r.location || r.problem_desc);
                    let imageIcon = (r.image_path && r.image_path !== '') ? "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>" : "";

                    // ช่าง (Technician) & แผนก (Department)
                    let techNameHtml = "<span class='text-rose-500 font-bold'>-</span>";
                    let deptEng = "<span class='text-rose-500 font-bold'>-</span>";
                    
                    if (r.technician_name && r.technician_name !== '-') {
                        let info = techInfoMap[r.technician_name] || { th: r.technician_name, eng: '', pos: '' };
                        techNameHtml = `<div class='text-indigo-600 font-bold'>${info.th}</div>`;
                        if(info.eng) techNameHtml += `<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>${info.eng}</div>`;

                        let dName = techDeptMap[r.technician_name] || 'General';
                        deptEng = `<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold tracking-wider mb-1 shadow-sm'>${dName}</div>`;
                        if (info && info.pos) deptEng += `<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>${info.pos}</div>`;
                    }

                    // เวลาเข้างาน (Received At)
                    let raw_rec_js = (r.received_at && r.received_at != '0000-00-00 00:00:00' && r.received_at != '-') ? r.received_at : '';
                    let has_received = (raw_rec_js !== '');
                    let received_date = has_received ? raw_rec_js.split(' ')[0] : "<span class='text-rose-500 font-bold'>-</span>";
                    let received_time = has_received && raw_rec_js.split(' ')[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${raw_rec_js.split(' ')[1].substring(0, 5)}</div>` : '';

                    // เวลาปิดงาน (Completed At)
                    let has_completed = (r.completed_at && r.completed_at != '0000-00-00 00:00:00');
                    let completed_date = has_completed ? r.completed_at.split(' ')[0] : "<span class='text-rose-500 font-bold'>-</span>";
                    let completed_time = has_completed && r.completed_at.split(' ')[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${r.completed_at.split(' ')[1].substring(0, 5)}</div>` : '';

                    // สาเหตุการซ่อม (Root Cause)
                    let rootCause = (!r.root_cause || r.root_cause === '-') ? "<span class='text-rose-500 font-bold'>-</span>" : `<span class='text-slate-700 font-medium'>${r.root_cause}</span>`;

                    let escRemark = (r.remark || '').replace(/"/g, '&quot;').replace(/'/g, '\\\'');

                    tbody.innerHTML += `
                        <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-100 last:border-0">
                            <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                                <div class="font-medium text-slate-700">${createdDate}</div>
                                ${createdTime}
                            </td>
                            <td class="px-6 py-4 align-top font-mono font-semibold text-slate-600">${ticket_no}</td>
                            <td class="px-6 py-4 align-top">
                                <div class='flex items-center'>
                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mr-3 shrink-0'><i class='fas fa-user text-xs text-slate-400'></i></div>
                                    <div>
                                        <div class="text-slate-800 font-bold">${rName}</div>
                                        <div class="text-slate-500 text-[11px] font-medium mt-0.5">${rPhone}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-slate-800 font-bold">${eqType} ${imageIcon}</div>
                                <div class="text-slate-500 text-[11px] font-medium mt-0.5 max-w-[180px] truncate" title="${pDesc.replace(/<[^>]*>?/gm, '')}">${pDesc}</div>
                            </td>
                            <td class="px-6 py-4 align-top">${deptEng}</td>
                            <td class="px-6 py-4 align-top">${techNameHtml}</td>
                            <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                                <div class='font-medium text-slate-700'>${received_date}</div>
                                ${received_time}
                            </td>
                            <td class="px-6 py-4 align-top">${rootCause}</td>
                            <td class="px-6 py-4 align-middle text-center"><span class="${statusClass}">${statusText}</span></td>
                            <td class="px-6 py-4 align-top text-xs whitespace-nowrap">
                                <div class='font-medium text-emerald-700'>${completed_date}</div>
                                ${completed_time}
                            </td>
                            <td class="px-6 py-4 align-middle text-center">
                                <div class='flex items-center justify-center'>
                                    <div onclick="openModal(${r.id}, '${escRemark}')" class='cursor-pointer w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all flex items-center justify-center border border-slate-100 shadow-sm' title='เพิ่ม/แก้ไขหมายเหตุ'><i class='fas fa-edit'></i></div>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
        }


        // --------------------------------------------------------
        // วาดกราฟหน้า Dashboard (ทั้งหมด)
        // --------------------------------------------------------
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
                if (loc !== 'ไม่ระบุสถานที่' && loc.trim() !== '') {
                    locMap[loc] = (locMap[loc] || 0) + 1;
                }

                let repNameRaw = r.reporter_name || 'ไม่ระบุผู้แจ้ง';
                let repName = lineUsersMap[repNameRaw] ? lineUsersMap[repNameRaw] : repNameRaw;
                reporterMap[repName] = (reporterMap[repName] || 0) + 1;
            });

            // 1. Equipment Chart
            let eqSorted = Object.keys(eqMap).map(k => ({name: k, count: eqMap[k]})).sort((a,b) => b.count - a.count).slice(0, 6);
            const eqCtx = document.getElementById('eqChart').getContext('2d');
            let gradient = eqCtx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            new Chart(eqCtx, {
                type: 'line',
                data: {
                    labels: eqSorted.length ? eqSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        data: eqSorted.length ? eqSorted.map(e => e.count) : [0],
                        borderColor: '#8b5cf6',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#8b5cf6',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4
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

            // 2. Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            let totalStatus = pending + progress + completed;
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: totalStatus === 0 ? ['ไม่มีข้อมูล'] : ['รอรับเรื่อง', 'กำลังดำเนินการ', 'ซ่อมเสร็จแล้ว'],
                    datasets: [{
                        data: totalStatus === 0 ? [1] : [pending, progress, completed],
                        backgroundColor: totalStatus === 0 ? ['#f1f5f9'] : ['#fbbf24', '#38bdf8', '#10b981'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: 'bold' } } },
                        tooltip: { enabled: totalStatus > 0 }
                    }
                }
            });

            // 3. Location Chart
            let locSorted = Object.keys(locMap).map(k => ({name: k, count: locMap[k]})).sort((a,b) => b.count - a.count).slice(0, 5);
            const locCtx = document.getElementById('locChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: locSorted.length ? locSorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{
                        data: locSorted.length ? locSorted.map(e => e.count) : [0],
                        backgroundColor: '#f43f5e',
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f8fafc' }, border: {display: false} },
                        y: { grid: { display: false }, border: {display: false}, ticks: { font: { weight: 'bold' } } }
                    }
                }
            });

            // 4. Render Top Reporters List
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
                                <div class="w-10 h-10 rounded-full flex items-center justify-center border shadow-sm shrink-0 ${rankBg}">
                                    ${rankIcon}
                                </div>
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
                        </li>
                    `;
                });
                html += '</ul>';
                repContainer.innerHTML = html;
            }
        }

        // --------------------------------------------------------
        // Control Modals & Initialize
        // --------------------------------------------------------
        const modal = document.getElementById('noteModal');
        const repairInput = document.getElementById('modal_repair_id');
        const remarkInput = document.getElementById('modal_remark');

        function openModal(id, currentRemark) {
            repairInput.value = id;
            remarkInput.value = currentRemark;
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        // ทำงานทันทีเมื่อหน้าเว็บโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'dash';
            showTab(tab);
        });

    </script>
</body>
</html>
<?php 
session_start();

// 1. เช็คว่าได้ล็อกอินเข้ามาหรือยัง? 
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

// 2. ป้องกันผู้บริหาร (Executive) แอบเข้ามาดูหน้าจัดการช่าง
if (strtolower($_SESSION['role']) === 'executive') {
    header("Location: executive_dashboard.php");
    exit();
}

include 'db_connect.php';

// ================= ฟังก์ชันแปลงตัวเลขเป็นเลขไทย (สำหรับ PHP) =================
function thaiNum($num) {
    return str_replace(
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        array('๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'),
        $num
    );
}

// ================= ปรับปรุงฐานข้อมูลอัตโนมัติ (Auto-Fix DB) =================
$conn->query("CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    status VARCHAR(20) DEFAULT 'ใช้งานปกติ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NULL,
    department VARCHAR(100) NULL,
    role VARCHAR(50) DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) DEFAULT 'User'");
$check_fullname = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if($check_fullname->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER username");

$check_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if($check_phone->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER full_name");

$check_dept = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if($check_dept->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL AFTER phone");

$check_pwd = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
if($check_pwd->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN password VARCHAR(255) NULL AFTER username");
}

$check_created = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if($check_created->num_rows == 0) $conn->query("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

$check_tech_name = $conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'");
if($check_tech_name->num_rows == 0) {
    $conn->query("ALTER TABLE repairs ADD COLUMN technician_name VARCHAR(100) NULL");
}

$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs->num_rows > 0) {
    $conn->query("INSERT INTO users (username, full_name, phone, department, role) 
                  SELECT CONCAT('U', REPLACE(phone_number, '-', '')), reporter_name, phone_number, 'บุคลากรทั่วไป', 'User' 
                  FROM repairs 
                  WHERE reporter_name IS NOT NULL AND reporter_name != '' AND reporter_name NOT IN (SELECT full_name FROM users WHERE full_name IS NOT NULL) 
                  GROUP BY reporter_name, phone_number");
}

// ================= จัดการข้อมูล =================
if (isset($_GET['delete_asset'])) {
    $del_id = intval($_GET['delete_asset']);
    $conn->query("DELETE FROM assets WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=assets';</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_asset'])) {
    $asset_id = $_POST['asset_id'];
    $asset_code = $_POST['asset_code'];
    $asset_name = $_POST['asset_name'];
    $category = $_POST['category'];
    $status = $_POST['status'];

    if (empty($asset_id)) {
        $stmt = $conn->prepare("INSERT INTO assets (asset_code, asset_name, category, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $asset_code, $asset_name, $category, $status);
    } else {
        $stmt = $conn->prepare("UPDATE assets SET asset_code=?, asset_name=?, category=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $asset_code, $asset_name, $category, $status, $asset_id);
    }
    $stmt->execute();
    echo "<script>window.location.href='dashboard.php?tab=assets';</script>";
}

if (isset($_GET['delete_user'])) {
    $del_id = intval($_GET['delete_user']);
    $conn->query("DELETE FROM users WHERE id = $del_id");
    echo "<script>window.location.href='dashboard.php?tab=technicians';</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $password = $_POST['password']; 
    
    $full_name = !empty($_POST['full_name']) ? $_POST['full_name'] : NULL;
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : NULL;
    $role = $_POST['role']; 
    
    if (isset($_POST['admin_level']) && ($role === 'Admin' || $role === 'Executive')) {
        $role = $_POST['admin_level'];
        $department = NULL; 
    } else {
        $department = isset($_POST['department_select']) ? $_POST['department_select'] : NULL;
        if ($department === 'อื่นๆ' && !empty($_POST['department_custom'])) {
            $department = $_POST['department_custom'];
        }
    }

    $tab_redirect = ($role == 'User') ? 'users' : 'technicians';

    if (empty($user_id)) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, phone, department, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $password, $full_name, $phone, $department, $role);
        $msg = 'บันทึกข้อมูลสำเร็จ!';
    } else {
        if (!empty($password)) {
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=?, phone=?, department=?, role=? WHERE id=?");
            $stmt->bind_param("ssssssi", $username, $password, $full_name, $phone, $department, $role, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, phone=?, department=?, role=? WHERE id=?");
            $stmt->bind_param("sssssi", $username, $full_name, $phone, $department, $role, $user_id);
        }
        $msg = 'อัปเดตข้อมูลสำเร็จ!';
    }
    
    if ($stmt->execute()) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: '$msg', confirmButtonColor: '#0284c7' }).then(() => { window.location.href='dashboard.php?tab=$tab_redirect'; }); });</script>";
    }
}

if (isset($_GET['delete_reporter'])) {
    $del_name = $_GET['delete_reporter'];
    $stmt = $conn->prepare("DELETE FROM repairs WHERE reporter_name = ?");
    $stmt->bind_param("s", $del_name);
    $stmt->execute();
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'ลบประวัติสำเร็จ!', showConfirmButton: false, timer: 1500 }).then(() => { window.location.href='dashboard.php?tab=users'; }); });</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_reporter'])) {
    $old_name = $_POST['old_name'];
    $new_name = $_POST['new_name'];
    $new_phone = $_POST['new_phone'];
    
    $stmt = $conn->prepare("UPDATE repairs SET reporter_name = ?, phone_number = ? WHERE reporter_name = ?");
    $stmt->bind_param("sss", $new_name, $new_phone, $old_name);
    $stmt->execute();
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon: 'success', title: 'อัปเดตข้อมูลผู้แจ้งสำเร็จ!', confirmButtonColor: '#0284c7' }).then(() => { window.location.href='dashboard.php?tab=users'; }); });</script>";
}

// ================= เตรียมข้อมูลประวัติและสถิติ =================
$all_repairs_json = "[]";

if($check_repairs->num_rows > 0) {
    $has_tech_name = ($conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'")->num_rows > 0);
    $select_query = $has_tech_name ? "SELECT ticket_no, equipment_type, status, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at_fmt, reporter_name, technician_name FROM repairs ORDER BY created_at DESC" : "SELECT ticket_no, equipment_type, status, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at_fmt, reporter_name, '' as technician_name FROM repairs ORDER BY created_at DESC";
    
    $rep_res = $conn->query($select_query);
    $reps = [];
    if($rep_res) {
        while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
        $all_repairs_json = json_encode($reps);
    }
}

// ดึงรายชื่อช่างทั้งหมดสำหรับ Dropdown (ที่มีบทบาทเป็น Technician หรือ Admin)
$tech_options = [];
$tech_list_res = $conn->query("SELECT DISTINCT full_name FROM users WHERE role IN ('Technician', 'Admin') AND full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
if($tech_list_res){
    while($t = $tech_list_res->fetch_assoc()){
        $tech_options[] = $t['full_name'];
    }
}

// เตรียมข้อมูลวันที่สำหรับเอกสารราชการ
$thai_months_doc = [
    "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน",
    "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม",
    "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
];
$report_month = $thai_months_doc[date('m')];
$current_date_thai = thaiNum(date('j')) . " " . $report_month . " " . thaiNum(date('Y')+543);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS Smart Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- นำเข้าฟอนต์ Kanit สำหรับหน้าเว็บ และ TH Sarabun สำหรับพิมพ์เอกสาร -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* ฟอนต์สำหรับพิมพ์เอกสารราชการ TH Sarabun New มาตรฐานสากล */
        @font-face {
            font-family: 'THSarabunNew';
            src: url('https://cdn.jsdelivr.net/gh/lazywasabi/thai-web-fonts@7/fonts/THSarabunNew/THSarabunNew.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'THSarabunNew';
            src: url('https://cdn.jsdelivr.net/gh/lazywasabi/thai-web-fonts@7/fonts/THSarabunNew/THSarabunNew%20Bold.woff2') format('woff2');
            font-weight: bold;
            font-style: normal;
        }

        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); }
        .nav-btn { width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; margin-bottom: 0.25rem; border-radius: 0.75rem; color: #64748b; font-weight: 500; transition: all 0.2s; }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.25rem; margin-right: 0.75rem; color: #94a3b8; transition: all 0.2s; }
        .nav-btn:hover { background-color: #f8fafc; color: #0284c7; }
        .active-btn { background-color: #f0f9ff; color: #0369a1; font-weight: 600; box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1); border: 1px solid #bae6fd; }
        .active-btn i { color: #0284c7; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        
        /* สไตล์สำหรับเอกสารราชการ (TH Sarabun New) */
        .official-doc {
            font-family: 'THSarabunNew', sans-serif !important;
            font-size: 16pt !important;
            color: #000000 !important;
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 2.5cm 2cm 2cm 3cm; 
            margin: 0 auto;
            box-sizing: border-box;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
            line-height: 1.15 !important; 
        }
        
        .official-doc * {
            font-family: 'THSarabunNew', sans-serif !important;
            color: #000000 !important;
            font-size: 16pt;
        }

        .official-doc .title-doc {
            font-size: 29pt !important;
            font-weight: bold !important;
            text-align: center;
            margin-top: -10pt;
            margin-bottom: 5pt;
        }

        .official-doc .bold-text { font-weight: bold !important; }
        
        .official-doc p { 
            text-align: justify; 
            margin-bottom: 2pt;
            margin-top: 0;
        }
        
        .official-doc .thai-indent { text-indent: 2.5cm; }
        .official-doc .thai-sub-indent { padding-left: 2.5cm; }

        .official-doc .doc-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 2pt;
        }

        .keep-together {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        @media print {
            @page { 
                size: A4; 
                margin: 0; 
            }
            body, html { 
                height: 100% !important; 
                overflow: visible !important; 
                background: #ffffff !important; 
            }
            .flex, .h-screen, .overflow-hidden {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
            }
            aside, header, .no-print, #sidebarOverlay, #dash, #repairs, #technicians, #assets, #users { 
                display: none !important; 
            }
            main, .flex-1 { 
                display: block !important; 
                height: auto !important; 
                overflow: visible !important; 
                padding: 0 !important; 
            }
            #reports { 
                display: block !important; 
                margin: 0 !important; 
            }
            .official-doc {
                width: 210mm !important;
                height: auto !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 2.5cm 2cm 2cm 3cm !important; 
                box-shadow: none !important;
                border: none !important;
                page-break-after: always;
            }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-200">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden transition-opacity" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 shadow-[4px_0_24px_rgba(0,0,0,0.02)] no-print">
        <div class="h-20 md:h-24 flex items-center justify-between px-5 md:px-8 border-b border-slate-100">
            <div class="flex items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-sky-400 flex items-center justify-center shadow-lg shadow-sky-500/30 mr-3 shrink-0">
                    <i class="fas fa-tools text-white text-lg md:text-xl"></i>
                </div>
                <div class="overflow-hidden flex-1">
                    <h1 class="text-lg md:text-xl font-bold text-slate-800 leading-tight tracking-tight">MBS REPAIR</h1>
                    <p class="text-[10px] md:text-xs text-sky-500 font-semibold tracking-widest uppercase mt-0.5">Admin Portal</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-red-500 focus:outline-none">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <nav class="flex-1 px-4 md:px-5 py-6 md:py-8 flex flex-col overflow-y-auto">
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">ระบบจัดการหลัก</p>
            <button onclick="show('dash')" class="nav-btn active-btn" id="btn-dash"><i class="fas fa-chart-pie"></i> ภาพรวมระบบ</button>
            <button onclick="show('repairs')" class="nav-btn" id="btn-repairs"><i class="fas fa-layer-group"></i> รายการแจ้งซ่อม</button>
            <button onclick="show('technicians')" class="nav-btn" id="btn-technicians"><i class="fas fa-user-shield"></i> ทีมงานระบบ</button>
            
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 mt-8">การตั้งค่าและรายงาน</p>
            <button onclick="show('assets')" class="nav-btn" id="btn-assets"><i class="fas fa-server"></i> จัดการอุปกรณ์</button>
            <button onclick="show('users')" class="nav-btn" id="btn-users"><i class="fas fa-users"></i> ประวัติผู้แจ้งซ่อม</button>
            <button onclick="show('reports')" class="nav-btn" id="btn-reports"><i class="fas fa-file-invoice"></i> รายงานสรุป</button>
            
            <div class="mt-auto pt-4 border-t border-slate-100">
                <a href="logout.php" class="nav-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-sign-out-alt text-rose-400"></i> ออกจากระบบ</a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-sky-100/50 to-transparent -z-10 no-print"></div>
        
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 sticky top-0 no-print">
            <div class="flex items-center overflow-hidden">
                <button onclick="toggleSidebar()" class="md:hidden mr-4 text-slate-500 hover:text-sky-600 focus:outline-none shrink-0">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl md:text-2xl font-bold text-slate-800 tracking-wide truncate" id="headerTitle">ภาพรวมระบบ (Dashboard)</h2>
            </div>
            
            <div class="flex items-center space-x-3 md:space-x-6 shrink-0">
                <div class="relative hidden lg:block">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" id="searchInput" placeholder="ค้นหาข้อมูลในตาราง..." class="bg-white border border-slate-200 text-sm rounded-full pl-11 pr-5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-100 transition-all w-72 shadow-sm">
                </div>
                <div class="flex items-center space-x-3 cursor-pointer p-1.5 md:pr-4 rounded-full border border-slate-200 bg-white hover:bg-slate-50 transition-all shadow-sm">
                    <div class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold"><i class="fas fa-user text-sm"></i></div>
                    <div class="hidden sm:block text-left">
                        <span class="block text-sm font-semibold text-slate-700 leading-none mb-1">
                            <?php echo isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Administrator'); ?>
                        </span>
                        <span class="block text-[11px] text-slate-500 uppercase tracking-wide leading-none">ผู้ดูแลระบบ</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-10 print:p-0 print:overflow-visible bg-slate-100/50 print:bg-white">
            
            <!-- Dashboard Section -->
            <div id="dash" class="section space-y-6 animate-fade-in no-print">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                    <?php 
                    if($check_repairs->num_rows > 0) {
                        $stats = [
                            ["งานทั้งหมด", "repairs", "fa-briefcase", "text-blue-600", "bg-blue-100", "border-blue-200"], 
                            ["รอรับเรื่อง", "status='รอรับเรื่อง'", "fa-clock", "text-amber-500", "bg-amber-100", "border-amber-200"], 
                            ["กำลังดำเนินการ", "status='กำลังดำเนินการ'", "fa-tools", "text-sky-500", "bg-sky-100", "border-sky-200"], 
                            ["ซ่อมเสร็จแล้ว", "status='ซ่อมเสร็จแล้ว'", "fa-check-circle", "text-emerald-500", "bg-emerald-100", "border-emerald-200"]
                        ];
                        foreach($stats as $s) {
                            $res = $conn->query("SELECT count(*) as c FROM repairs ".($s[1] != "repairs" ? "WHERE {$s[1]}" : ""));
                            $c = $res ? $res->fetch_assoc()['c'] : 0;
                            echo "<div class='modern-card p-5 md:p-6 border-b-4 {$s[5]}'><div class='flex justify-between items-start'><div><p class='text-slate-500 text-xs md:text-sm font-medium mb-1 md:mb-2'>{$s[0]}</p><h3 class='text-3xl md:text-4xl font-extrabold text-slate-800'>{$c}</h3></div><div class='w-12 h-12 md:w-14 md:h-14 rounded-2xl {$s[4]} flex items-center justify-center {$s[3]} shadow-sm'><i class='fas {$s[2]} text-xl md:text-2xl'></i></div></div></div>";
                        }
                    }
                    ?>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                    <div class="lg:col-span-2 modern-card bg-white overflow-hidden flex flex-col">
                        <div class="p-4 md:p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h3 class="font-bold text-slate-800 text-sm md:text-base"><i class="fas fa-bolt text-amber-500 mr-2"></i> งานแจ้งซ่อมล่าสุด</h3>
                            <button onclick="show('repairs')" class="text-xs font-bold text-sky-600 hover:text-sky-800 bg-sky-100 px-3 py-1.5 rounded-lg transition-colors">ดูทั้งหมด</button>
                        </div>
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left whitespace-nowrap">
                                <tbody class="text-sm divide-y divide-slate-100">
                                    <?php
                                    if($check_repairs->num_rows > 0) {
                                        $recent_dash = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                        if($recent_dash && $recent_dash->num_rows > 0){
                                            while($rd = $recent_dash->fetch_assoc()) {
                                                $time_ago = date("d/m H:i", strtotime($rd['created_at']));
                                                $stColor = ($rd['status'] == 'รอรับเรื่อง') ? 'text-amber-500 bg-amber-50 border-amber-100' : (($rd['status'] == 'กำลังดำเนินการ') ? 'text-sky-500 bg-sky-50 border-sky-100' : 'text-emerald-500 bg-emerald-50 border-emerald-100');
                                                echo "<tr class='hover:bg-slate-50 transition-colors'>
                                                    <td class='px-4 md:px-5 py-3 md:py-4 font-semibold text-slate-800'>{$rd['equipment_type']}</td>
                                                    <td class='px-4 md:px-5 py-3 md:py-4 text-slate-500 text-xs'><i class='fas fa-map-marker-alt text-slate-300 mr-1'></i> {$rd['location']}</td>
                                                    <td class='px-4 md:px-5 py-3 md:py-4 text-right'><span class='px-2.5 py-1 rounded-full text-[11px] font-bold border {$stColor}'>{$rd['status']}</span></td>
                                                    <td class='px-4 md:px-5 py-3 md:py-4 text-right text-slate-400 text-xs'>{$time_ago}</td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' class='px-5 py-8 text-center text-slate-400'>ไม่มีงานแจ้งซ่อม</td></tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modern-card bg-white overflow-hidden flex flex-col">
                        <div class="p-4 md:p-5 border-b border-slate-100 bg-red-50/30">
                            <h3 class="font-bold text-slate-800 text-sm md:text-base"><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> อุปกรณ์ชำรุด (รอซ่อม)</h3>
                        </div>
                        <div class="p-4 md:p-5 flex-1 overflow-y-auto">
                            <div class="space-y-3">
                                <?php
                                $check_assets = $conn->query("SHOW TABLES LIKE 'assets'");
                                if($check_assets->num_rows > 0) {
                                    $broken_assets = $conn->query("SELECT * FROM assets WHERE status = 'ชำรุด/ส่งซ่อม' LIMIT 4");
                                    if($broken_assets && $broken_assets->num_rows > 0){
                                        while($ba = $broken_assets->fetch_assoc()) {
                                            echo "<div class='flex items-center justify-between p-3 rounded-xl border border-red-100 bg-white shadow-sm hover:shadow-md transition-shadow'>
                                                <div>
                                                    <p class='text-sm font-bold text-slate-800'>{$ba['asset_name']}</p>
                                                    <p class='text-[11px] text-slate-500'>{$ba['asset_code']}</p>
                                                </div>
                                                <span class='text-[10px] font-bold text-red-500 bg-red-50 px-2 py-1 rounded-lg border border-red-100'>ชำรุด</span>
                                            </div>";
                                        }
                                    } else {
                                        echo "<div class='text-center py-8 text-slate-400'>
                                                <i class='fas fa-check-circle text-4xl text-emerald-200 mb-3 block'></i> 
                                                <p class='text-sm font-medium'>ไม่มีอุปกรณ์ชำรุดในระบบ</p>
                                              </div>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repairs Section -->
            <div id="repairs" class="section hidden space-y-4 md:space-y-6 no-print">
                <div class="modern-card overflow-hidden">
                    <div class="p-4 md:p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center bg-white gap-3">
                        <h2 class="text-lg md:text-xl font-bold text-slate-800">รายการแจ้งซ่อมทั้งหมด</h2>
                        <div class="w-full md:w-auto relative lg:hidden">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchInputMobile" placeholder="ค้นหาใบงาน..." class="w-full bg-slate-50 border border-slate-200 text-sm rounded-lg pl-9 pr-4 py-2 focus:outline-none focus:border-sky-400">
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left whitespace-nowrap min-w-[800px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">วัน/เวลาที่แจ้ง</th>
                                    <th class="px-6 py-4">เลขที่ใบงาน</th>
                                    <th class="px-6 py-4">ข้อมูลผู้แจ้ง</th>
                                    <th class="px-6 py-4">อุปกรณ์ / อาการเสีย</th>
                                    <th class="px-6 py-4 text-center">ผู้รับผิดชอบ (ช่าง)</th>
                                    <th class="px-6 py-4 text-center">สถานะ</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                if($check_repairs->num_rows > 0) {
                                    $has_tech_name = ($conn->query("SHOW COLUMNS FROM repairs LIKE 'technician_name'")->num_rows > 0);
                                    $select_query = $has_tech_name ? "SELECT * FROM repairs ORDER BY created_at DESC" : "SELECT *, '' as technician_name FROM repairs ORDER BY created_at DESC";
                                    
                                    $res = $conn->query($select_query);
                                    if($res && $res->num_rows > 0){
                                        while($row = $res->fetch_assoc()) {
                                            $date = !empty($row['created_at']) ? date("d/m/Y H:i", strtotime($row['created_at'])) : "-";
                                            $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                            if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                            elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                            elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";
                                            
                                            $techName = !empty($row['technician_name']) ? "<div class='flex items-center justify-center text-indigo-600 font-semibold'><i class='fas fa-hard-hat mr-2'></i>{$row['technician_name']}</div>" : "<span class='text-slate-400'>- ไม่ระบุ -</span>";

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                                <td class='px-6 py-4 font-bold text-sky-600'>{$row['ticket_no']}</td>
                                                <td class='px-6 py-4'><div class='text-slate-800 font-semibold'>{$row['reporter_name']}</div><div class='text-slate-500 text-[11px] md:text-xs mt-1'><i class='fas fa-phone-alt mr-1 text-slate-400'></i> {$row['phone_number']}</div></td>
                                                <td class='px-6 py-4'>
                                                    <div class='text-slate-800 font-semibold'>{$row['equipment_type']}</div>
                                                    <div class='text-slate-500 text-[11px] md:text-xs mt-1 max-w-[150px] truncate' title='{$row['problem_desc']}'>{$row['problem_desc']}</div>
                                                </td>
                                                <td class='px-6 py-4 text-center'>{$techName}</td>
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-[11px] md:text-xs font-bold border {$statusClass}'>{$row['status']}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <a href='update_repair.php?id={$row['id']}' class='w-8 h-8 md:w-9 md:h-9 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-all flex items-center justify-center border border-emerald-100 shadow-sm'><i class='fas fa-clipboard-check'></i></a>
                                                        <a href='view_repair.php?id={$row['id']}' class='w-8 h-8 md:w-9 md:h-9 rounded-xl bg-slate-50 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center border border-slate-200 shadow-sm'><i class='fas fa-eye'></i></a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='7' class='px-6 py-16 text-center text-slate-400 font-medium'>ยังไม่มีข้อมูลการแจ้งซ่อมในระบบ</td></tr>"; }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Technicians Section -->
            <div id="technicians" class="section hidden space-y-6 no-print">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
                    <div>
                        <h2 class="text-xl md:text-2xl font-bold text-slate-800">ทีมงานระบบ</h2>
                        <p class="text-sm text-slate-500 mt-1">จัดการรายชื่อผู้ดูแลและช่างซ่อม</p>
                    </div>
                    <div class="flex w-full md:w-auto gap-2">
                        <button onclick="openTechAdminModal('Admin')" class="flex-1 md:flex-none bg-purple-600 hover:bg-purple-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center justify-center"><i class="fas fa-user-shield mr-2"></i> เพิ่มผู้ดูแล/ผู้บริหาร</button>
                        <button onclick="openTechAdminModal('Technician')" class="flex-1 md:flex-none bg-sky-600 hover:bg-sky-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center justify-center"><i class="fas fa-hard-hat mr-2"></i> เพิ่มช่างซ่อม</button>
                    </div>
                </div>

                <!-- ตาราง Admin & Executive -->
                <div>
                    <h3 class="text-base md:text-lg font-bold text-slate-700 mb-3 md:mb-4 flex items-center"><i class="fas fa-user-shield text-purple-500 mr-2 text-xl"></i> ผู้ดูแลระบบ และ ผู้บริหาร</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">Username</th>
                                        <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                                        <th class="px-6 py-4">เบอร์โทรศัพท์</th>
                                        <th class="px-6 py-4 text-center">สิทธิ์</th>
                                        <th class="px-6 py-4 text-right">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $admin_res = $conn->query("SELECT * FROM users WHERE LOWER(role) IN ('admin', 'executive') ORDER BY id DESC");
                                    if($admin_res && $admin_res->num_rows > 0){
                                        while($u = $admin_res->fetch_assoc()) {
                                            $r_lower = strtolower($u['role']);
                                            $roleDisplay = ($r_lower == 'executive') ? 'ผู้บริหาร' : 'Admin';
                                            $roleClass = ($r_lower == 'executive') ? "bg-pink-50 text-pink-600 border-pink-200" : "bg-purple-50 text-purple-600 border-purple-200";
                                            $iconClass = ($r_lower == 'executive') ? "fa-user-tie text-pink-600 bg-pink-50 border-pink-100" : "fa-user-shield text-purple-600 bg-purple-50 border-purple-100";
                                            $icon = ($r_lower == 'executive') ? "fa-user-tie" : "fa-user-shield";
                                            
                                            $js_uid = $u['id']; 
                                            $js_uname = htmlspecialchars($u['username'], ENT_QUOTES); 
                                            $js_fname = htmlspecialchars($u['full_name'] ?? '', ENT_QUOTES); 
                                            $js_phone = htmlspecialchars($u['phone'] ?? '', ENT_QUOTES); 
                                            $js_dept = htmlspecialchars($u['department'] ?? '', ENT_QUOTES);
                                            $js_role = htmlspecialchars($u['role'], ENT_QUOTES);

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-slate-700'>{$u['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full flex items-center justify-center mr-3 border {$iconClass}'><i class='fas {$icon} text-xs'></i></div>
                                                        ".(!empty($u['full_name']) ? $u['full_name'] : '- ไม่ระบุ -')."
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600'>".(!empty($u['phone']) ? $u['phone'] : '-')."</td>
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {$roleClass}'>{$roleDisplay}</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"openTechAdminModal('{$js_role}', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$u['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>ยังไม่มีข้อมูลผู้ดูแลระบบและผู้บริหาร</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ตาราง Technician -->
                <div class="mt-6 md:mt-8">
                    <h3 class="text-base md:text-lg font-bold text-slate-700 mb-3 md:mb-4 flex items-center"><i class="fas fa-hard-hat text-sky-500 mr-2 text-xl"></i> ช่างซ่อม (Technician)</h3>
                    <div class="modern-card overflow-hidden">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="px-6 py-4 w-48">Username</th>
                                        <th class="px-6 py-4">ชื่อ-นามสกุล</th>
                                        <th class="px-6 py-4">ความเชี่ยวชาญ</th>
                                        <th class="px-6 py-4 text-center">งานที่รับ</th>
                                        <th class="px-6 py-4 text-right">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                    <?php
                                    $tech_res = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'technician' ORDER BY id DESC");
                                    
                                    if($tech_res && $tech_res->num_rows > 0){
                                        while($t = $tech_res->fetch_assoc()) {
                                            $js_uid = $t['id']; 
                                            $js_uname = htmlspecialchars($t['username'], ENT_QUOTES); 
                                            $js_fname = htmlspecialchars($t['full_name'] ?? '', ENT_QUOTES); 
                                            $js_phone = htmlspecialchars($t['phone'] ?? '', ENT_QUOTES); 
                                            $js_dept = htmlspecialchars($t['department'] ?? '', ENT_QUOTES);
                                            $js_role = htmlspecialchars($t['role'], ENT_QUOTES);
                                            
                                            $total_jobs = 0;
                                            if(!empty($t['full_name'])) {
                                                $safe_tech_name = $conn->real_escape_string($t['full_name']);
                                                $job_res = $conn->query("SELECT COUNT(id) as c FROM repairs WHERE technician_name = '{$safe_tech_name}'");
                                                if($job_res) $total_jobs = $job_res->fetch_assoc()['c'];
                                            }

                                            echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                                <td class='px-6 py-4 font-bold text-slate-700'>{$t['username']}</td>
                                                <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full flex items-center justify-center mr-3 border bg-sky-50 text-sky-600 border-sky-100'><i class='fas fa-hard-hat text-xs'></i></div>
                                                        ".(!empty($t['full_name']) ? $t['full_name'] : '- ไม่ระบุ -')."
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 text-slate-600'>".(!empty($t['department']) ? $t['department'] : '-')."</td>
                                                <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-[11px] md:text-xs font-bold border bg-indigo-50 text-indigo-600 border-indigo-200'>{$total_jobs} งาน</span></td>
                                                <td class='px-6 py-4 text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <button onclick=\"viewHistory('{$js_fname}', 'technician')\" class='bg-white border border-slate-200 text-slate-600 hover:text-sky-600 hover:bg-sky-50 px-2 md:px-3 py-1.5 rounded-lg text-xs font-bold transition-colors shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>ดูผลงาน</span></button>
                                                        <button onclick=\"openTechAdminModal('{$js_role}', '$js_uid', '$js_uname', '$js_fname', '$js_phone', '$js_dept')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                        <button onclick=\"confirmDelete('user', {$t['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>ยังไม่มีข้อมูลช่างซ่อม</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Management -->
            <div id="assets" class="section hidden space-y-4 md:space-y-6 no-print">
                <div class="modern-card overflow-hidden">
                    <div class="p-4 md:p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center bg-white gap-3">
                        <h2 class="text-lg md:text-xl font-bold text-slate-800">ฐานข้อมูลอุปกรณ์</h2>
                        <button onclick="openAddAssetModal()" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center justify-center"><i class="fas fa-plus mr-2"></i> เพิ่มอุปกรณ์ใหม่</button>
                    </div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left whitespace-nowrap min-w-[600px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">รหัส</th>
                                    <th class="px-6 py-4">ชื่ออุปกรณ์</th>
                                    <th class="px-6 py-4">หมวดหมู่</th>
                                    <th class="px-6 py-4 text-center">สถานะ</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $asset_res = $conn->query("SELECT * FROM assets ORDER BY created_at DESC");
                                if($asset_res && $asset_res->num_rows > 0){
                                    while($a = $asset_res->fetch_assoc()) {
                                        $a_statusClass = ($a['status'] == 'ใช้งานปกติ') ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200';
                                        $js_id = $a['id']; $js_code = htmlspecialchars($a['asset_code'], ENT_QUOTES); $js_name = htmlspecialchars($a['asset_name'], ENT_QUOTES); $js_cat = htmlspecialchars($a['category'], ENT_QUOTES); $js_status = htmlspecialchars($a['status'], ENT_QUOTES);

                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 font-bold text-indigo-600'>{$a['asset_code']}</td>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>{$a['asset_name']}</td>
                                            <td class='px-6 py-4 text-slate-600'><span class='bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-medium border border-slate-200'>{$a['category']}</span></td>
                                            <td class='px-6 py-4 text-center'><span class='inline-flex items-center px-3 py-1 rounded-full text-[11px] md:text-xs font-bold border {$a_statusClass}'>{$a['status']}</span></td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"openEditAssetModal('$js_id', '$js_code', '$js_name', '$js_cat', '$js_status')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDelete('asset', {$a['id']})\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='5' class='px-6 py-12 text-center text-slate-400'>ยังไม่มีข้อมูลครุภัณฑ์</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Section (ประวัติผู้แจ้งซ่อม ที่จัดการได้) -->
            <div id="users" class="section hidden space-y-4 md:space-y-6 no-print">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-2">
                    <div>
                        <h2 class="text-xl md:text-2xl font-bold text-slate-800">ประวัติผู้แจ้งซ่อม</h2>
                        <p class="text-sm text-slate-500 mt-1">รายชื่อบุคลากรและประวัติการแจ้งซ่อมทั้งหมด</p>
                    </div>
                </div>

                <div class="modern-card overflow-hidden">
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left whitespace-nowrap min-w-[700px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-6 py-4">ชื่อ-นามสกุล (ผู้แจ้ง)</th>
                                    <th class="px-6 py-4">เบอร์โทรศัพท์ / แผนก</th>
                                    <th class="px-6 py-4 text-center">จำนวนที่แจ้ง</th>
                                    <th class="px-6 py-4 text-right">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $reporter_res = $conn->query("SELECT reporter_name, MAX(phone_number) as phone_number, COUNT(id) as total_repairs FROM repairs WHERE reporter_name IS NOT NULL AND reporter_name != '' GROUP BY reporter_name ORDER BY MAX(created_at) DESC");
                                
                                if($reporter_res && $reporter_res->num_rows > 0){
                                    while($r = $reporter_res->fetch_assoc()) {
                                        $js_old_name = htmlspecialchars($r['reporter_name'], ENT_QUOTES);
                                        $js_old_phone = htmlspecialchars($r['phone_number'], ENT_QUOTES);
                                        
                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 text-slate-800 font-semibold'>
                                                <div class='flex items-center'>
                                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3 border border-slate-200'><i class='fas fa-user text-xs'></i></div>
                                                    {$r['reporter_name']}
                                                </div>
                                            </td>
                                            <td class='px-6 py-4'>
                                                <div class='text-slate-700 font-medium'><i class='fas fa-phone-alt text-slate-400 mr-1.5'></i> ".($r['phone_number'] ? $r['phone_number'] : '-')."</div>
                                            </td>
                                            <td class='px-6 py-4 text-center'>
                                                <span class='inline-flex items-center px-3 py-1 rounded-full text-[11px] md:text-xs font-bold border bg-sky-50 text-sky-600 border-sky-200'>{$r['total_repairs']} งาน</span>
                                            </td>
                                            <td class='px-6 py-4 text-right'>
                                                <div class='flex items-center justify-end space-x-2'>
                                                    <button onclick=\"viewHistory('{$js_old_name}', 'reporter')\" class='bg-white border border-slate-200 text-slate-600 hover:text-sky-600 hover:bg-sky-50 px-2 md:px-4 py-1.5 md:py-2 rounded-xl text-xs font-bold transition-colors shadow-sm'><i class='fas fa-eye md:mr-1'></i> <span class='hidden md:inline'>ดูประวัติ</span></button>
                                                    <button onclick=\"openEditReporterModal('{$js_old_name}', '{$js_old_phone}')\" class='w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white transition-all flex items-center justify-center border border-amber-100 shadow-sm' title='แก้ไขข้อมูล'><i class='fas fa-edit'></i></button>
                                                    <button onclick=\"confirmDeleteReporter('{$js_old_name}')\" class='w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white transition-all flex items-center justify-center border border-red-100 shadow-sm' title='ลบประวัติ'><i class='fas fa-trash-alt'></i></button>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='4' class='px-6 py-12 text-center text-slate-400'>ยังไม่มีประวัติการแจ้งซ่อม</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Report Summary Section (เอกสารบันทึกข้อความราชการ 100% พร้อม Filter บุคคล) -->
            <div id="reports" class="section hidden space-y-6">
                
                <!-- แถบปุ่มกดด้านบน และตัวกรองช่าง (ไม่แสดงตอนพิมพ์) -->
                <div class="modern-card bg-white p-4 md:p-6 no-print">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                        <div>
                            <h2 class="text-xl md:text-2xl font-bold text-slate-800">รายงานสรุปผลการปฏิบัติงาน</h2>
                            <p class="text-sm text-slate-500 mt-1">สามารถเลือกพิมพ์ภาพรวมทั้งหมด หรือพิมพ์เฉพาะผลงานของช่างรายบุคคลได้</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                            <!-- ปุ่มดาวน์โหลด Excel -->
                            <a href="export_excel.php" id="exportExcelBtn" target="_blank" class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm flex items-center justify-center transition-colors">
                                <i class="fas fa-file-excel mr-2 text-lg"></i> ดาวน์โหลดตาราง Excel
                            </a>
                            <!-- ปุ่มพิมพ์เอกสาร -->
                            <button onclick="window.print()" class="w-full sm:w-auto bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-sky-600 px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm flex items-center justify-center transition-colors">
                                <i class="fas fa-print mr-2 text-lg"></i> พิมพ์บันทึกข้อความ
                            </button>
                        </div>
                    </div>

                    <!-- แถบตัวกรองรายบุคคล -->
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-3">
                        <label class="font-bold text-slate-700 text-sm"><i class="fas fa-filter text-sky-500 mr-1"></i> เลือกดูรายงาน:</label>
                        <select id="techFilter" onchange="updateReportData()" class="bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg px-4 py-2 focus:outline-none focus:border-sky-500 font-medium min-w-[200px]">
                            <option value="all">ภาพรวมระบบทั้งหมด (All)</option>
                            <?php 
                                foreach($tech_options as $tech) {
                                    echo "<option value=\"".htmlspecialchars($tech)."\">เฉพาะผลงานของ: ".htmlspecialchars($tech)."</option>"; 
                                }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- หน้าเอกสารสำหรับพิมพ์ (A4 Style - บันทึกข้อความแบบทางการ 100%) -->
                <div class="official-doc">
                    
                    <!-- ส่วนหัวบันทึกข้อความ มีครุฑ -->
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c3/Thai_government_Garuda_emblem_%28Version_2%29.svg" style="width: 1.5cm; position: absolute; left: 3cm; top: 1.5cm; filter: grayscale(100%);">
                    
                    <div class="title-doc">บันทึกข้อความ</div>
                    
                    <div class="doc-row" style="margin-top: 10pt;">
                        <span class="bold-text" style="width: 2.5cm;">ส่วนราชการ</span>
                        <span>ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between;">
                        <div class="doc-row" style="width: 50%;">
                            <span class="bold-text" style="width: 1cm;">ที่</span>
                            <span>ศธ ๐๕๓๐.๑๑/......................</span>
                        </div>
                        <div class="doc-row" style="width: 50%;">
                            <span class="bold-text" style="width: 1.2cm;">วันที่</span>
                            <span><?php echo $current_date_thai; ?></span>
                        </div>
                    </div>
                    
                    <div class="doc-row">
                        <span class="bold-text" style="width: 1.5cm;">เรื่อง</span>
                        <span id="docSubject">รายงานสรุปผลการปฏิบัติงานระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) ประจำเดือน <?php echo $report_month; ?></span>
                    </div>
                    
                    <div class="doc-row" style="margin-bottom: 10pt;">
                        <span class="bold-text" style="width: 1.5cm;">เรียน</span>
                        <span>คณบดีคณะการบัญชีและการจัดการ / หัวหน้าฝ่ายเทคโนโลยีสารสนเทศ</span>
                    </div>
                    
                    <!-- เนื้อหารายงาน -->
                    <div>
                        <p class="thai-indent" id="docContext">ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการเปิดรับแจ้งซ่อมและบำรุงรักษาอุปกรณ์คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ ผ่านระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) นั้น</p>
                        <p class="thai-indent">ในการนี้ ทางผู้ดูแลระบบได้รวบรวมข้อมูลสถิติการปฏิบัติงาน ประจำเดือน <?php echo $report_month; ?> เพื่อรายงานผลการดำเนินงานให้รับทราบ โดยมีรายละเอียดดังต่อไปนี้</p>
                        
                        <div class="keep-together">
                            <p class="bold-text" style="margin-top: 5pt;">๑. สรุปภาพรวมสถานะการดำเนินงาน</p>
                            <p class="thai-indent">มีจำนวนการแจ้งซ่อมในระบบทั้งสิ้น <b id="docTotal">๐</b> รายการ โดยแบ่งตามสถานะการดำเนินงาน ดังนี้</p>
                            <div class="thai-sub-indent">
                                <p>๑.๑ ดำเนินการซ่อมแซมเสร็จสิ้นแล้ว จำนวน <b id="docCompleted">๐</b> รายการ (คิดเป็นร้อยละ <span id="docPctCompleted">๐</span>)</p>
                                <p>๑.๒ อยู่ระหว่างดำเนินการ จำนวน <b id="docProgress">๐</b> รายการ (คิดเป็นร้อยละ <span id="docPctProgress">๐</span>)</p>
                                <p>๑.๓ รอดำเนินการ/รอรับเรื่อง จำนวน <b id="docPending">๐</b> รายการ (คิดเป็นร้อยละ <span id="docPctPending">๐</span>)</p>
                            </div>
                        </div>

                        <div class="keep-together">
                            <p class="bold-text" style="margin-top: 5pt;">๒. สถิติอุปกรณ์ที่พบปัญหาความชำรุดบกพร่องสูงสุด</p>
                            <p class="thai-indent">ข้อมูลประเภทครุภัณฑ์และอุปกรณ์ที่มีสถิติการแจ้งซ่อมสูงสุด ประกอบด้วย</p>
                            <div class="thai-sub-indent" id="docTopEquip">
                                <!-- อัปเดตผ่าน JS -->
                            </div>
                        </div>

                        <p class="thai-indent keep-together" style="margin-top: 5pt;" id="docFooterText">ข้อมูลดังกล่าวสามารถนำไปใช้วางแผนการจัดซื้อวัสดุอุปกรณ์สำรอง และกำหนดแนวทางการบำรุงรักษาเชิงป้องกัน (Preventive Maintenance) ในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น</p>

                        <p class="thai-indent keep-together" style="margin-top: 10pt;">จึงเรียนมาเพื่อโปรดทราบ</p>
                    </div>

                    <!-- ลายเซ็น -->
                    <div class="keep-together" style="margin-top: 25pt; text-align: center; float: right; width: 6.5cm;">
                        <p style="margin-bottom: 20pt;">(ลงชื่อ)...................................................</p>
                        <p>( <?php echo isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'ผู้ดูแลระบบ'; ?> )</p>
                        <p id="docSignatureRole">ผู้รายงาน / ผู้จัดทำ</p>
                    </div>
                    <div style="clear: both;"></div>

                </div>
            </div>

        </div>
    </main>

    <!-- ================== MODALS ================== -->

    <!-- Modal เพิ่ม/แก้ไข อุปกรณ์ -->
    <div id="assetModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('assetModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-5 md:px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-base md:text-lg font-bold text-slate-800" id="assetModalTitle">เพิ่มอุปกรณ์ใหม่</p>
                <button onclick="toggleModal('assetModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-5 md:p-6">
                <input type="hidden" name="save_asset" value="1"><input type="hidden" name="asset_id" id="asset_id" value="">
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">รหัสครุภัณฑ์ <span class="text-red-500">*</span></label><input type="text" name="asset_code" id="asset_code" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่ออุปกรณ์ <span class="text-red-500">*</span></label><input type="text" name="asset_name" id="asset_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">หมวดหมู่ <span class="text-red-500">*</span></label><select name="category" id="asset_category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"><option value="IT Support">IT Support</option><option value="ไฟฟ้า/แอร์">ไฟฟ้า/แอร์</option><option value="อาคารสถานที่">อาคารสถานที่</option><option value="อื่นๆ">อื่นๆ</option></select></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">สถานะ</label><select name="status" id="asset_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"><option value="ใช้งานปกติ">ใช้งานปกติ</option><option value="ชำรุด/ส่งซ่อม">ชำรุด/ส่งซ่อม</option><option value="แทงจำหน่าย">แทงจำหน่าย</option></select></div>
                </div>
                <div class="mt-6 md:mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('assetModal')" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-4 md:px-5 py-2 md:py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-500 shadow-md">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไข ทีมงาน (Admin & Tech) พร้อมฟังก์ชัน Toggle รหัสผ่านและเว้นชื่อได้ -->
    <div id="techAdminModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('techAdminModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto max-h-[90vh] transform transition-all">
            <div class="px-5 md:px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl sticky top-0 z-10">
                <p class="text-base md:text-lg font-bold text-slate-800" id="techAdminModalTitle">เพิ่มทีมงานระบบ</p>
                <button onclick="toggleModal('techAdminModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-5 md:p-6">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" id="techAdmin_id" value="">
                <input type="hidden" name="role" id="techAdmin_role" value="">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Username / รหัสประจำตัว <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="techAdmin_username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">รหัสผ่าน (Password) <span class="text-slate-400 font-normal text-xs" id="pwdHint"></span></label>
                        <div class="relative">
                            <input type="password" name="password" id="techAdmin_password" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-10 py-2 text-sm text-slate-700" placeholder="ตั้งรหัสผ่าน">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-sky-600 focus:outline-none" onclick="togglePasswordVisibility('techAdmin_password', 'eyeIcon')">
                                <i id="eyeIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div id="adminLevelDiv" class="hidden">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">ระดับสิทธิ์ (Role) <span class="text-red-500">*</span></label>
                        <select name="admin_level" id="techAdmin_level" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700">
                            <option value="Admin">ผู้ดูแลระบบ (Admin)</option>
                            <option value="Executive">ผู้บริหาร (Executive)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" id="techAdmin_fullname" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" id="techAdmin_phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700">
                    </div>
                    
                    <div id="deptDiv">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">แผนก / ความเชี่ยวชาญ <span class="text-red-500">*</span></label>
                        <select name="department_select" id="techAdmin_department_select" onchange="toggleCustomDept(this, 'techAdmin_department_custom')" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 mb-2">
                            <option value="" disabled selected>-- เลือกแผนก --</option>
                            <option value="แผนกช่าง">แผนกช่าง</option>
                            <option value="แผนกไฟฟ้า">แผนกไฟฟ้า</option>
                            <option value="แผนกโสต">แผนกโสต</option>
                            <option value="แม่บ้าน">แม่บ้าน</option>
                            <option value="อื่นๆ">อื่นๆ (พิมพ์เอง)</option>
                        </select>
                        <input type="text" name="department_custom" id="techAdmin_department_custom" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 hidden" placeholder="ระบุแผนก/ความเชี่ยวชาญ">
                    </div>
                </div>
                <div class="mt-6 md:mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('techAdminModal')" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-4 md:px-5 py-2 md:py-2.5 bg-sky-600 text-white rounded-xl text-sm font-bold hover:bg-sky-500 shadow-md">บันทึกข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal แก้ไขข้อมูลผู้แจ้งซ่อม (เฉพาะอัปเดตชื่อใน repairs) -->
    <div id="editReporterModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('editReporterModal')"></div>
        <div class="modal-container bg-white w-full max-w-md mx-auto rounded-2xl shadow-2xl z-50 overflow-y-auto transform transition-all">
            <div class="px-5 md:px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <p class="text-base md:text-lg font-bold text-slate-800"><i class="fas fa-user-edit text-amber-500 mr-2"></i> แก้ไขผู้แจ้งซ่อม</p>
                <button onclick="toggleModal('editReporterModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form action="" method="POST" class="p-5 md:p-6">
                <input type="hidden" name="edit_reporter" value="1">
                <input type="hidden" name="old_name" id="edit_rep_old_name" value="">
                <div class="bg-blue-50 border border-blue-100 text-blue-700 text-xs p-3 rounded-xl mb-4">
                    <i class="fas fa-info-circle mr-1"></i> อัปเดตไปยังประวัติการแจ้งซ่อมที่ผ่านมาทั้งหมด
                </div>
                <div class="space-y-4">
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">ชื่อ-นามสกุล <span class="text-red-500">*</span></label><input type="text" name="new_name" id="edit_rep_new_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                    <div><label class="block text-sm font-semibold text-slate-700 mb-1">เบอร์โทรศัพท์ <span class="text-red-500">*</span></label><input type="text" name="new_phone" id="edit_rep_new_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700"></div>
                </div>
                <div class="mt-6 md:mt-8 flex justify-end gap-3"><button type="button" onclick="toggleModal('editReporterModal')" class="px-4 md:px-5 py-2 md:py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">ยกเลิก</button><button type="submit" class="px-4 md:px-5 py-2 md:py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-400 shadow-md">อัปเดตข้อมูล</button></div>
            </form>
        </div>
    </div>

    <!-- Modal ดูประวัติการแจ้งซ่อม/รับงาน (👁️) -->
    <div id="historyModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-2 md:px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('historyModal')"></div>
        <div class="modal-container bg-white w-full max-w-3xl mx-auto rounded-2xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[80vh] max-h-[800px]">
            <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
                <p class="text-base md:text-lg font-bold text-slate-800 truncate pr-4" id="historyModalTitle">ประวัติ</p>
                <button onclick="toggleModal('historyModal')" class="text-slate-400 hover:text-red-500 transition-colors shrink-0"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-2 md:p-6 overflow-y-auto flex-1 bg-slate-50/50">
                <div class="w-full overflow-x-auto rounded-xl border border-slate-200 shadow-sm bg-white">
                    <table class="w-full text-left whitespace-nowrap min-w-[500px]">
                        <thead class="bg-slate-100 border-b border-slate-200 text-slate-600 text-[10px] md:text-xs uppercase tracking-wider font-bold">
                            <tr>
                                <th class="px-3 md:px-4 py-3">เลขที่ใบงาน</th>
                                <th class="px-3 md:px-4 py-3">อุปกรณ์ / อาการ</th>
                                <th class="px-3 md:px-4 py-3 text-center">สถานะ</th>
                                <th class="px-3 md:px-4 py-3">วันที่แจ้ง</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm divide-y divide-slate-100" id="historyTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="px-4 md:px-6 py-3 border-t border-slate-100 bg-white shrink-0 flex justify-end">
                <button onclick="toggleModal('historyModal')" class="px-5 md:px-6 py-2 md:py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-700 shadow-md">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ================== JAVASCRIPT ================== -->
    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;
        const reportMonthText = "<?php echo $report_month; ?>";
        
        const pageTitles = {
            'dash': 'ภาพรวมระบบ (Dashboard)',
            'repairs': 'ตรวจสอบงานแจ้งซ่อมทั้งหมด',
            'technicians': 'ทีมงานระบบ (แอดมิน & ช่าง)',
            'assets': 'ฐานข้อมูลอุปกรณ์และครุภัณฑ์',
            'users': 'ประวัติผู้แจ้งซ่อม (รายบุคคล)',
            'reports': 'รายงานสรุปผลการปฏิบัติงาน'
        };
        
        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'ระบบจัดการ';
            
            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
            }

            let searchInputs = [document.getElementById('searchInput'), document.getElementById('searchInputMobile')];
            searchInputs.forEach(input => {
                if(input) {
                    input.value = '';
                    let activeSection = document.getElementById(id);
                    if(activeSection) {
                        activeSection.querySelectorAll('table tbody tr').forEach(row => row.style.display = '');
                    }
                }
            });

            // ถ้าเข้ามาหน้ารายงาน ให้สั่งอัปเดตข้อมูลสักครั้งเพื่อให้ตัวเลขขึ้น 
            if(id === 'reports') {
                updateReportData();
            }
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if(tab) { show(tab); } else { show('dash'); }

            ['searchInput', 'searchInputMobile'].forEach(id => {
                const inputElement = document.getElementById(id);
                if(inputElement) {
                    inputElement.addEventListener('input', function() {
                        let filter = this.value.toLowerCase();
                        let activeSection = document.querySelector('.section:not(.hidden)');
                        if (!activeSection) return;
                        
                        let rows = activeSection.querySelectorAll('table tbody tr');
                        rows.forEach(row => {
                            if (row.innerText.toLowerCase().includes(filter)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                }
            });
        });

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        // ฟังก์ชันแปลงเลขเป็นเลขไทยใน JS
        function thaiNum(num) {
            if(num === null || num === undefined) return '๐';
            const thaiDigits = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
            return String(num).replace(/[0-9]/g, function(d) {
                return thaiDigits[d];
            });
        }

        // ฟังก์ชันหลักสำหรับอัปเดตเอกสารรายงาน
        function updateReportData() {
            const filterValue = document.getElementById('techFilter').value;
            let filteredRepairs = allRepairs;
            
            // กรองข้อมูลถ้าไม่ได้เลือก "ทั้งหมด"
            if (filterValue !== 'all') {
                filteredRepairs = allRepairs.filter(r => r.technician_name === filterValue);
                document.getElementById('docSubject').innerText = `รายงานสรุปผลการปฏิบัติงานรายบุคคล (ช่าง: ${filterValue}) ประจำเดือน ${reportMonthText}`;
                document.getElementById('docContext').innerText = `ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้มอบหมายให้บุคลากรรับผิดชอบการแจ้งซ่อมและบำรุงรักษาอุปกรณ์คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ ผ่านระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) นั้น`;
                document.getElementById('docFooterText').innerText = `ข้อมูลดังกล่าวสามารถนำไปใช้เป็นหลักฐานประกอบการประเมินผลการปฏิบัติงาน และกำหนดแนวทางการบำรุงรักษาในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น`;
                document.getElementById('docSignatureRole').innerText = `ผู้รับผิดชอบงานซ่อม`;
                document.getElementById('exportExcelBtn').href = `export_excel.php?tech=${encodeURIComponent(filterValue)}`;
            } else {
                document.getElementById('docSubject').innerText = `รายงานสรุปผลการปฏิบัติงานระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) ประจำเดือน ${reportMonthText}`;
                document.getElementById('docContext').innerText = `ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการเปิดรับแจ้งซ่อมและบำรุงรักษาอุปกรณ์คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ ผ่านระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) นั้น`;
                document.getElementById('docFooterText').innerText = `ข้อมูลดังกล่าวสามารถนำไปใช้วางแผนการจัดซื้อวัสดุอุปกรณ์สำรอง และกำหนดแนวทางการบำรุงรักษาเชิงป้องกัน (Preventive Maintenance) ในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น`;
                document.getElementById('docSignatureRole').innerText = `ผู้รายงาน / ผู้จัดทำ`;
                document.getElementById('exportExcelBtn').href = `export_excel.php`;
            }

            // คำนวณสถานะใหม่
            let pending = 0, progress = 0, completed = 0;
            let equipCountMap = {};

            filteredRepairs.forEach(r => {
                if(r.status === 'รอรับเรื่อง') pending++;
                else if(r.status === 'กำลังดำเนินการ') progress++;
                else if(r.status === 'ซ่อมเสร็จแล้ว') completed++;

                // นับความถี่อุปกรณ์
                if(r.equipment_type) {
                    equipCountMap[r.equipment_type] = (equipCountMap[r.equipment_type] || 0) + 1;
                }
            });

            let total = pending + progress + completed;
            let pctCompleted = total > 0 ? ((completed / total) * 100).toFixed(2) : '0.00';
            let pctProgress = total > 0 ? ((progress / total) * 100).toFixed(2) : '0.00';
            let pctPending = total > 0 ? ((pending / total) * 100).toFixed(2) : '0.00';

            // นำตัวเลขที่คำนวณได้ไปใส่ใน DOM
            document.getElementById('docTotal').innerText = thaiNum(total);
            document.getElementById('docCompleted').innerText = thaiNum(completed);
            document.getElementById('docPctCompleted').innerText = thaiNum(pctCompleted);
            
            document.getElementById('docProgress').innerText = thaiNum(progress);
            document.getElementById('docPctProgress').innerText = thaiNum(pctProgress);
            
            document.getElementById('docPending').innerText = thaiNum(pending);
            document.getElementById('docPctPending').innerText = thaiNum(pctPending);

            // เรียงลำดับอุปกรณ์ที่พบปัญหามากสุด 5 อันดับ
            let sortedEquip = Object.keys(equipCountMap).map(key => {
                return { name: key, count: equipCountMap[key] };
            }).sort((a, b) => b.count - a.count).slice(0, 5);

            let equipHtml = '';
            if(sortedEquip.length > 0) {
                const thaiNums = ['๑', '๒', '๓', '๔', '๕'];
                sortedEquip.forEach((eq, index) => {
                    equipHtml += `<p>๒.${thaiNums[index]} ${eq.name} จำนวน <b>${thaiNum(eq.count)}</b> รายการ</p>`;
                });
            } else {
                equipHtml = `<p>- ไม่มีข้อมูลการแจ้งซ่อมในระบบ -</p>`;
            }
            document.getElementById('docTopEquip').innerHTML = equipHtml;
        }

        // ฟังก์ชันเปิด-ปิดตาอื่นๆ...
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function toggleCustomDept(selectElement, customInputId) {
            const customInput = document.getElementById(customInputId);
            if(selectElement.value === 'อื่นๆ') {
                customInput.classList.remove('hidden');
                customInput.required = true;
            } else {
                customInput.classList.add('hidden');
                customInput.required = false;
            }
        }

        function setDropdownOrCustom(selectId, customInputId, val) {
            const selectEl = document.getElementById(selectId);
            const customEl = document.getElementById(customInputId);
            
            if (!val || val === '-') {
                selectEl.value = '';
                customEl.classList.add('hidden');
                customEl.value = '';
                customEl.required = false;
                return;
            }

            const options = Array.from(selectEl.options).map(opt => opt.value);
            if (options.includes(val) && val !== 'อื่นๆ') {
                selectEl.value = val;
                customEl.classList.add('hidden');
                customEl.value = '';
                customEl.required = false;
            } else {
                selectEl.value = 'อื่นๆ';
                customEl.classList.remove('hidden');
                customEl.value = val;
                customEl.required = true;
            }
        }

        function openAddAssetModal() { 
            document.getElementById('assetModalTitle').innerHTML = '<i class="fas fa-plus-circle text-sky-500 mr-2"></i> เพิ่มอุปกรณ์ใหม่'; 
            document.getElementById('asset_id').value = ''; document.getElementById('asset_code').value = ''; document.getElementById('asset_name').value = ''; document.getElementById('asset_category').value = 'IT Support'; document.getElementById('asset_status').value = 'ใช้งานปกติ'; toggleModal('assetModal'); 
        }

        function openEditAssetModal(id, c, n, cat, s) { 
            document.getElementById('assetModalTitle').innerHTML = '<i class="fas fa-edit text-amber-500 mr-2"></i> แก้ไขอุปกรณ์'; 
            document.getElementById('asset_id').value = id; document.getElementById('asset_code').value = c; document.getElementById('asset_name').value = n; document.getElementById('asset_category').value = cat; document.getElementById('asset_status').value = s; toggleModal('assetModal'); 
        }

        function openTechAdminModal(role, id='', u='', f='', p='', d='') { 
            let isManagement = (role.toLowerCase() === 'admin' || role.toLowerCase() === 'executive');
            let baseRole = isManagement ? 'Admin' : 'Technician';
            let title = isManagement ? '<i class="fas fa-user-shield text-purple-500 mr-2"></i> จัดการผู้ดูแล/ผู้บริหาร' : '<i class="fas fa-hard-hat text-sky-500 mr-2"></i> จัดการช่างซ่อม';
            
            document.getElementById('techAdminModalTitle').innerHTML = title; 
            document.getElementById('techAdmin_role').value = baseRole; 
            
            const adminLevelDiv = document.getElementById('adminLevelDiv');
            const deptDiv = document.getElementById('deptDiv');
            
            if(isManagement) {
                adminLevelDiv.classList.remove('hidden');
                deptDiv.classList.add('hidden');
                document.getElementById('techAdmin_department_select').required = false;
                let exactRole = (role.toLowerCase() === 'executive') ? 'Executive' : 'Admin';
                document.getElementById('techAdmin_level').value = exactRole;
            } else {
                adminLevelDiv.classList.add('hidden');
                deptDiv.classList.remove('hidden');
                document.getElementById('techAdmin_department_select').required = true;
            }

            document.getElementById('techAdmin_id').value = id; 
            document.getElementById('techAdmin_username').value = u; 
            document.getElementById('techAdmin_fullname').value = f; 
            document.getElementById('techAdmin_phone').value = p; 
            
            const pwdInput = document.getElementById('techAdmin_password');
            const pwdHint = document.getElementById('pwdHint');
            const eyeIcon = document.getElementById('eyeIcon');
            pwdInput.value = ''; 
            pwdInput.type = 'password'; 
            if(eyeIcon) {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }

            if(id === '') {
                pwdInput.required = true;
                pwdHint.innerText = "(บังคับกรอก)";
            } else {
                pwdInput.required = false;
                pwdHint.innerText = "(ปล่อยว่างได้ถ้าไม่ต้องการเปลี่ยน)";
            }
            
            document.getElementById('techAdmin_department_select').name = "department_select";
            document.getElementById('techAdmin_department_custom').name = "department_custom";
            setDropdownOrCustom('techAdmin_department_select', 'techAdmin_department_custom', d);
            
            toggleModal('techAdminModal'); 
        }

        function openEditReporterModal(old_name, old_phone) {
            document.getElementById('edit_rep_old_name').value = old_name;
            document.getElementById('edit_rep_new_name').value = old_name;
            document.getElementById('edit_rep_new_phone').value = old_phone;
            toggleModal('editReporterModal');
        }

        function viewHistory(fullName, type) {
            const tbody = document.getElementById('historyTableBody');
            tbody.innerHTML = '';
            
            const userRepairs = allRepairs.filter(r => type === 'reporter' ? r.reporter_name === fullName : r.technician_name === fullName);
            
            if(userRepairs.length === 0) {
                let emptyMsg = type === 'reporter' ? 'ไม่พบประวัติการแจ้งซ่อม' : 'ยังไม่เคยรับงานซ่อมในระบบ';
                tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">${emptyMsg}</td></tr>`;
            } else {
                userRepairs.forEach(r => {
                    let statusClass = 'bg-slate-100 text-slate-600';
                    if(r.status === 'รอรับเรื่อง') statusClass = 'bg-amber-50 text-amber-600';
                    else if(r.status === 'กำลังดำเนินการ') statusClass = 'bg-sky-50 text-sky-600';
                    else if(r.status === 'ซ่อมเสร็จแล้ว') statusClass = 'bg-emerald-50 text-emerald-600';
                    
                    tbody.innerHTML += `<tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-3 md:px-4 py-3 font-bold text-sky-600">${r.ticket_no}</td>
                        <td class="px-3 md:px-4 py-3 text-slate-700 font-medium whitespace-normal min-w-[120px]">${r.equipment_type}</td>
                        <td class="px-3 md:px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-[10px] font-bold ${statusClass}">${r.status}</span></td>
                        <td class="px-3 md:px-4 py-3 text-slate-500">${r.created_at_fmt}</td>
                    </tr>`;
                });
            }
            
            let titlePrefix = type === 'reporter' ? 'ประวัติการแจ้งซ่อมของ:' : 'ประวัติรับงานของช่าง:';
            document.getElementById('historyModalTitle').innerHTML = `<i class="fas fa-history text-sky-500 mr-2"></i> ${titlePrefix} <span class="text-sky-700 ml-1 text-sm md:text-lg">${fullName}</span>`;
            toggleModal('historyModal');
        }

        function confirmDelete(type, id) { 
            Swal.fire({ title: 'ยืนยัน?', text: "ลบแล้วไม่สามารถกู้คืนได้!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ลบเลย!' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_'+type+'=' + id; }); 
        }

        function confirmDeleteReporter(name) { 
            Swal.fire({ title: 'ลบประวัติบุคคลนี้?', text: "จะทำให้ชื่อผู้แจ้งซ่อมในประวัติที่ผ่านมาถูกลบทั้งหมด!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'ยืนยันลบ' }).then((r) => { if(r.isConfirmed) window.location.href = 'dashboard.php?delete_reporter=' + encodeURIComponent(name); }); 
        }

    </script>
</body>
</html>
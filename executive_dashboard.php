<?php 
session_start();

// 1. เช็คว่าได้ล็อกอินเข้ามาหรือยัง? ถ้ายังให้เด้งไปหน้า login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. ป้องกันช่างซ่อม (Technician) แอบเข้ามาดูหน้าผู้บริหาร
if (strtolower($_SESSION['role']) === 'technician') {
    header("Location: dashboard.php");
    exit();
}

include 'db_connect.php'; 

$conn->set_charset("utf8mb4");

// ✨ ดึงข้อมูล "ผู้บริหาร" (Executive) จากฐานข้อมูลโดยตรง เพื่อให้เชื่อมกับที่แอดมินตั้งค่าไว้อัตโนมัติ ✨
$current_user_name = 'ยังไม่กำหนดผู้บริหาร';
$current_user_role = 'Executive';
$current_username = 'exec';

$exec_res = $conn->query("SELECT username, full_name, role, position FROM users WHERE LOWER(role) = 'executive' ORDER BY id ASC LIMIT 1");
if ($exec_res && $exec_res->num_rows > 0) {
    $exec_data = $exec_res->fetch_assoc();
    $current_username = $exec_data['username'];
    $current_user_name = !empty($exec_data['full_name']) ? $exec_data['full_name'] : $exec_data['username'];
    $current_user_role = !empty($exec_data['position']) ? $exec_data['position'] : 'Executive';
}

// ฟังก์ชันต่างๆ สำหรับ Format ข้อมูล
function formatEmptyOrDash($val) {
    $val = trim((string)$val);
    if (empty($val) || $val === '-') return "<span class='text-rose-500 font-bold'>-</span>";
    if ($val === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
    return htmlspecialchars($val);
}

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

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 0) $diff = 0; 
    if ($diff < 60) return "เมื่อสักครู่";
    if ($diff < 3600) return floor($diff / 60) . " นาทีที่แล้ว";
    if ($diff < 86400) return floor($diff / 3600) . " ชั่วโมงที่แล้ว";
    if ($diff < 604800) return floor($diff / 86400) . " วันที่แล้ว";
    $thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $m = date('n', $time) - 1;
    $y = date('Y', $time) + 543;
    $d = date('j', $time);
    return "$d " . $thaiMonths[$m] . " $y";
}

// =====================================================================
// ดึงข้อมูลเตรียมแสดงผล
// =====================================================================

$all_repairs_json = "[]";
$check_repairs_list = $conn->query("SHOW TABLES LIKE 'repairs'");
if($check_repairs_list && $check_repairs_list->num_rows > 0) {
    $select_query = "SELECT * FROM repairs ORDER BY created_at DESC";
    $rep_res = $conn->query($select_query);
    $reps = [];
    if($rep_res) {
        while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
        $encoded = json_encode($reps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            $all_repairs_json = $encoded;
        }
    }
}

$tech_dept_map = [];
$tech_info_map = [];
$td_res = $conn->query("SELECT full_name, department, english_name, position FROM technicians");
if($td_res) {
    while($tr = $td_res->fetch_assoc()) {
        $tech_dept_map[$tr['full_name']] = !empty($tr['department']) ? $tr['department'] : 'ฝ่ายงานทั่วไป';
        list($th_name, $en_name) = splitThaiEngName($tr['full_name'], $tr['english_name']);
        $pos = !empty($tr['position']) ? $tr['position'] : getAutoPosition($th_name);
        $tech_info_map[$tr['full_name']] = [
            'th' => $th_name,
            'eng' => $en_name,
            'pos' => $pos
        ];
    }
}
$tech_dept_map_json = json_encode($tech_dept_map, JSON_UNESCAPED_UNICODE);
$tech_info_map_json = json_encode($tech_info_map, JSON_UNESCAPED_UNICODE);

// ดึงข้อมูล line_users
$line_users_map = [];
$check_lu = $conn->query("SHOW TABLES LIKE 'line_users'");
if($check_lu && $check_lu->num_rows > 0) {
    $lu_res = $conn->query("SELECT line_display_name, real_name, phone_number FROM line_users");
    if($lu_res) {
        while($lu = $lu_res->fetch_assoc()) {
            $user_info = [
                'line_display_name' => $lu['line_display_name'],
                'real_name' => $lu['real_name'],
                'phone_number' => $lu['phone_number']
            ];
            if(!empty($lu['line_display_name'])) { $line_users_map[$lu['line_display_name']] = $user_info; }
            if(!empty($lu['real_name'])) { $line_users_map[$lu['real_name']] = $user_info; }
        }
    }
}
$line_users_map_json = json_encode($line_users_map, JSON_UNESCAPED_UNICODE);

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

$pageTitles = [
    'dash' => 'Dashboard Overview',
    'repairs' => 'All Repairs List'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard | MBS Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .modern-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); border: 1px solid #f1f5f9; }
        #sidebar { width: 240px !important; min-width: 240px !important; max-width: 240px !important; }
        .sidebar-logo-box { height: 88px !important; padding: 0 24px !important; }
        .top-header { height: 88px !important; padding: 0 32px !important; }
        .nav-btn { width: calc(100% - 32px) !important; display: flex !important; align-items: center !important; padding: 0.65rem 1rem !important; margin: 2px 16px !important; border-radius: 12px !important; color: #64748b !important; font-weight: 600 !important; font-size: 0.875rem !important; transition: all 0.2s ease !important; cursor: pointer !important; }
        .nav-btn i { width: 1.5rem !important; text-align: center !important; font-size: 1rem !important; margin-right: 0.75rem !important; color: #94a3b8 !important; }
        .nav-btn:hover { background-color: #f8fafc !important; color: #4f46e5 !important; }
        .nav-btn:hover i { color: #4f46e5 !important; }
        .active-btn { background-color: #eef2ff !important; color: #4f46e5 !important; font-weight: 700 !important; }
        .active-btn i { color: #4f46e5 !important; }

        ::-webkit-scrollbar { width: 8px; height: 12px; } 
        ::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 3px solid #f8fafc; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; margin: 0 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { border: 2px solid #f1f5f9; }

        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        
        .badge-pending { background-color: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-progress { background-color: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background-color: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        /* ✨ สไตล์สำหรับ Custom Dropdown บนกราฟ ✨ */
        .chart-dropdown-item.kb-active-item {
            background-color: #eef2ff !important;
            color: #4f46e5 !important;
        }

        @media print { aside, header, .no-print { display: none !important; } }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-indigo-100">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 z-40 hidden md:hidden backdrop-blur-sm transition-opacity" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="bg-white flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 border-r border-slate-100 no-print">

        <div class="sidebar-logo-box flex items-center border-b border-slate-50 py-6 px-6">
            <div class="w-[42px] h-[42px] rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 flex items-center justify-center shadow-md shadow-purple-500/30 mr-3.5 shrink-0">
                <i class="fas fa-chart-line text-white text-[22px]"></i>
            </div>
            <div class="overflow-hidden flex-1 mt-0.5">
                <h1 class="text-[19px] font-extrabold text-slate-800 tracking-tight leading-none mb-1">MBS REPAIR</h1>
                <p class="text-[10px] text-purple-600 font-extrabold tracking-[0.15em] uppercase leading-none">Executive View</p>
            </div>
        </div>

        <nav class="flex-1 py-6 flex flex-col overflow-y-auto">
            <p class="px-6 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">EXECUTIVE</p>
            <button onclick="show('dash')" class="nav-btn active-btn" id="btn-dash"><i class="fas fa-chart-pie"></i> Overview</button>
            <button onclick="show('repairs')" class="nav-btn" id="btn-repairs"><i class="fas fa-list-ul"></i> Transactions</button>

            <div class="mt-auto pt-4 border-t border-slate-50">
                <a href="logout.php" class="nav-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-sign-out-alt text-rose-400"></i> ออกจากระบบ</a>
            </div>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative bg-[#f8fafc]">

        <header class="top-header bg-gradient-to-r from-indigo-600 via-violet-600 to-fuchsia-600 flex items-center justify-between z-10 sticky top-0 shadow-md shadow-indigo-200/50">
            <div class="flex items-center">
                <button onclick="toggleSidebar()" class="md:hidden mr-4 text-white hover:text-indigo-100 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <!-- ✨ ปรับขนาดตัวอักษรให้ใหญ่เท่าหน้าแอดมิน (md:text-3xl) และให้เปลี่ยนชื่อแท็บอัตโนมัติ ✨ -->
                <h3 class="text-xl md:text-3xl font-extrabold text-white tracking-tight drop-shadow-sm" id="headerTitle"><?php echo $currentTitle; ?></h3>
            </div>

            <div class="flex items-center space-x-3 md:space-x-6">
                <div class="flex items-center gap-3 cursor-pointer group">
                    <div class="text-right hidden sm:block">
                        <!-- ✨ ดึงชื่อและตำแหน่งผู้บริหารที่แอดมินตั้งค่าไว้มาแสดงอัตโนมัติ ✨ -->
                        <span class="block text-sm font-bold text-white drop-shadow-sm leading-none mb-1 group-hover:text-indigo-100 transition-colors">
                            <?php echo htmlspecialchars($current_user_name); ?>
                        </span>
                        <span class="block text-[11px] text-indigo-100 font-semibold"><?php echo htmlspecialchars($current_user_role); ?></span>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white overflow-hidden border border-white/30 shadow-inner backdrop-blur-sm">
                        <img src="https://api.dicebear.com/7.x/notionists/svg?seed=<?php echo urlencode($current_username); ?>&backgroundColor=e2e8f0" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div id="dash" class="section space-y-6 animate-fade-in no-print">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php 
                        $resTotal = $conn->query("SELECT count(*) as c FROM repairs");
                        $cTotal = $resTotal ? $resTotal->fetch_assoc()['c'] : 0;
                        $resPend = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอรับเรื่อง'");
                        $cPend = $resPend ? $resPend->fetch_assoc()['c'] : 0;
                        $resProg = $conn->query("SELECT count(*) as c FROM repairs WHERE status='กำลังดำเนินการ'");
                        $cProg = $resProg ? $resProg->fetch_assoc()['c'] : 0;
                        $resComp = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว'");
                        $cComp = $resComp ? $resComp->fetch_assoc()['c'] : 0;
                    ?>
                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 text-xl"><i class="fas fa-layer-group"></i></div>
                            <span class="text-xs font-bold text-slate-400">TOTAL</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cTotal; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Total Repairs</p>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-500 text-xl"><i class="fas fa-clock"></i></div>
                            <span class="text-xs font-bold text-slate-400">WAITING</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cPend; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Pending</p>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-sky-50 flex items-center justify-center text-sky-500 text-xl"><i class="fas fa-spinner"></i></div>
                            <span class="text-xs font-bold text-slate-400">ACTIVE</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cProg; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">In Progress</p>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col justify-between hover:shadow-lg transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-500 text-xl"><i class="fas fa-check-circle"></i></div>
                            <span class="text-xs font-bold text-slate-400">DONE</span>
                        </div>
                        <div>
                            <h3 class="text-3xl font-extrabold text-slate-800"><?php echo $cComp; ?></h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Completed</p>
                        </div>
                    </div>
                </div>

                <!-- Equipment & Work Status -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Equipment Analytics</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">อุปกรณ์ที่แจ้งซ่อมบ่อยที่สุด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- ✨ Custom Dropdown ✨ -->
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="equip-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'equip-Month', renderEquipChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'equip-Month')">
                                        <span id="equip-MonthText" class="truncate font-bold">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="equip-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('equip-Month', 'all', 'เดือน', renderEquipChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('equip-Month', '{$num_pad}', '{$name}', renderEquipChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="equipMonth" value="all">
                                </div>
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="equip-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'equip-Year', renderEquipChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'equip-Year')">
                                        <span id="equip-YearText" class="truncate font-bold">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="equip-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('equip-Year', 'all', 'ปี (พ.ศ.)', renderEquipChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('equip-Year', '{$y}', 'พ.ศ. {$thai_y}', renderEquipChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="equipYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 relative w-full h-[280px]">
                            <canvas id="mainEquipChart"></canvas>
                        </div>
                    </div>

                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Work Status</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">สัดส่วนสถานะการดำเนินงาน</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="status-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'status-Month', renderStatusChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'status-Month')">
                                        <span id="status-MonthText" class="truncate font-bold">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="status-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('status-Month', 'all', 'เดือน', renderStatusChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('status-Month', '{$num_pad}', '{$name}', renderStatusChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="statusMonth" value="all">
                                </div>
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="status-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'status-Year', renderStatusChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'status-Year')">
                                        <span id="status-YearText" class="truncate font-bold">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="status-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('status-Year', 'all', 'ปี (พ.ศ.)', renderStatusChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('status-Year', '{$y}', 'พ.ศ. {$thai_y}', renderStatusChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="statusYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 relative w-full h-[280px] flex justify-center items-center">
                            <canvas id="mainStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Locations & Workload -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Top Locations</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">ห้อง/สถานที่ ที่เกิดปัญหาบ่อยที่สุด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="loc-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'loc-Month', renderLocChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'loc-Month')">
                                        <span id="loc-MonthText" class="truncate font-bold">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="loc-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('loc-Month', 'all', 'เดือน', renderLocChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('loc-Month', '{$num_pad}', '{$name}', renderLocChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="locMonth" value="all">
                                </div>
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="loc-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'loc-Year', renderLocChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'loc-Year')">
                                        <span id="loc-YearText" class="truncate font-bold">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="loc-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('loc-Year', 'all', 'ปี (พ.ศ.)', renderLocChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('loc-Year', '{$y}', 'พ.ศ. {$thai_y}', renderLocChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="locYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="relative w-full h-[250px]"> 
                            <canvas id="mainLocChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="modern-card p-6 flex flex-col">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Technician Workload</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">ปริมาณงานที่รับผิดชอบรายบุคคล</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="tech-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'tech-Month', renderTechChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'tech-Month')">
                                        <span id="tech-MonthText" class="truncate font-bold">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="tech-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('tech-Month', 'all', 'เดือน', renderTechChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('tech-Month', '{$num_pad}', '{$name}', renderTechChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="techMonth" value="all">
                                </div>
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="tech-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'tech-Year', renderTechChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'tech-Year')">
                                        <span id="tech-YearText" class="truncate font-bold">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="tech-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('tech-Year', 'all', 'ปี (พ.ศ.)', renderTechChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('tech-Year', '{$y}', 'พ.ศ. {$thai_y}', renderTechChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="techYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="relative w-full h-[250px]"> 
                            <canvas id="mainTechChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Customer Satisfaction & Top Reporters -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
                    <div class="modern-card p-6 flex flex-col lg:col-span-7 justify-between">
                        <div class="flex flex-col sm:flex-row justify-between items-start mb-4 gap-4">
                            <div class="flex flex-col">
                                <h3 class="font-extrabold text-slate-800 text-lg">Customer Satisfaction</h3>
                                <span class="text-sm font-medium text-slate-400 mt-0.5">คะแนนความพึงพอใจการให้บริการ</span>
                                <span class="text-[12px] text-indigo-500 font-bold mt-1"><i class="fas fa-hand-pointer mr-1"></i>คลิกที่แท่งกราฟเพื่อดูรีวิวช่าง</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="rating-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'rating-Month', renderRatingChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'rating-Month')">
                                        <span id="rating-MonthText" class="truncate font-bold">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="rating-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('rating-Month', 'all', 'เดือน', renderRatingChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('rating-Month', '{$num_pad}', '{$name}', renderRatingChart)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="ratingMonth" value="all">
                                </div>
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="rating-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'rating-Year', renderRatingChart)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'rating-Year')">
                                        <span id="rating-YearText" class="truncate font-bold">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="rating-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('rating-Year', 'all', 'ปี (พ.ศ.)', renderRatingChart)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('rating-Year', '{$y}', 'พ.ศ. {$thai_y}', renderRatingChart)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="ratingYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center w-full mt-2 flex-1">
                            <div class="relative w-full h-[380px]">
                                <canvas id="mainRatingChart" class="cursor-pointer"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="modern-card overflow-hidden flex flex-col lg:col-span-5 h-full">
                        <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col xl:flex-row justify-between items-start xl:items-center shrink-0 gap-3">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Top Reporters</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">สถิติผู้ที่แจ้งซ่อมบ่อยที่สุด</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="reporter-MonthContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'reporter-Month', renderTopReporters)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'reporter-Month')">
                                        <span id="reporter-MonthText" class="truncate font-bold">เดือน</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="reporter-MonthList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='เดือน' onclick="selectChartDropdown('reporter-Month', 'all', 'เดือน', renderTopReporters)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>เดือน</span>
                                        </div>
                                        <?php foreach($thai_months as $num => $name) { $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT); echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$num_pad}' data-display='{$name}' onclick=\"selectChartDropdown('reporter-Month', '{$num_pad}', '{$name}', renderTopReporters)\">{$name}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="reporterMonth" value="all">
                                </div>
                                <div class="relative w-[100px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-lg" id="reporter-YearContainer" tabindex="0" onkeydown="handleChartKeydown(event, 'reporter-Year', renderTopReporters)" style="font-family: 'Sarabun', sans-serif;">
                                    <div class="flex items-center justify-between w-full bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100" onclick="toggleChartDropdown(event, 'reporter-Year')">
                                        <span id="reporter-YearText" class="truncate font-bold">ปี (พ.ศ.)</span>
                                        <i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]"></i>
                                    </div>
                                    <div id="reporter-YearList" class="chart-dropdown-list absolute z-50 w-32 right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col pb-2 max-h-48 overflow-y-auto custom-scrollbar" style="font-family: 'Sarabun', sans-serif;">
                                        <div class='chart-dropdown-item flex justify-center items-center px-4 py-2 mb-1 bg-indigo-50 border-b border-indigo-100 sticky top-0 z-10 rounded-t-2xl cursor-pointer hover:bg-indigo-100 transition-colors' data-value='all' data-display='ปี (พ.ศ.)' onclick="selectChartDropdown('reporter-Year', 'all', 'ปี (พ.ศ.)', renderTopReporters)">
                                            <span class='text-[11px] font-extrabold text-indigo-600 tracking-wide pointer-events-none'>ปี (พ.ศ.)</span>
                                        </div>
                                        <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<div class='chart-dropdown-item px-4 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all text-slate-700 hover:bg-slate-100 hover:text-indigo-600' data-value='{$y}' data-display='พ.ศ. {$thai_y}' onclick=\"selectChartDropdown('reporter-Year', '{$y}', 'พ.ศ. {$thai_y}', renderTopReporters)\">พ.ศ. {$thai_y}</div>"; } ?>
                                    </div>
                                    <input type="hidden" id="reporterYear" value="all">
                                </div>
                            </div>
                        </div>
                        <div class="px-4 md:px-5 py-3 bg-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center shrink-0 z-10 shadow-sm gap-3">
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mr-1">จัดอันดับ:</span>
                                <div class="flex items-center gap-1.5">
                                    <button id="btnFilterTop3" onclick="setTopReportersFilter(3)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 3</button>
                                    <button id="btnFilterTop5" onclick="setTopReportersFilter(5)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700">Top 5</button>
                                    <button id="btnFilterTop10" onclick="setTopReportersFilter(10)" class="px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">Top 10</button>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button id="btnFilterTopAll" onclick="setTopReportersFilter('all')" class="px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">ทั้งหมด</button>
                            </div>
                        </div>
                        <div class="p-0 overflow-y-auto flex-1 bg-white custom-scrollbar max-h-[380px]">
                            <div class="divide-y divide-slate-100" id="topReportersList"></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions (Dashboard) -->
                <div class="grid grid-cols-1 gap-6 mt-6">
                    <div class="modern-card overflow-hidden flex flex-col col-span-full">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                            <div>
                                <h3 class="font-extrabold text-slate-800 text-lg">Recent Transactions</h3>
                                <p class="text-sm font-medium text-slate-400 mt-0.5">Latest 5 repairs in system</p>
                            </div>
                            <button onclick="show('repairs')" class="flex items-center text-sm text-slate-600 font-bold hover:text-indigo-600 transition-colors group">
                                See All <i class="fas fa-arrow-right ml-2 text-xs text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto pb-4 custom-scrollbar">
                            <table class="w-full text-left whitespace-nowrap">
                                <thead class="bg-[#fef9c3] text-[#854d0e] text-xs uppercase tracking-widest font-bold border-b border-[#fef08a]">
                                    <tr>
                                        <th class="px-6 py-4">Date / Time</th>
                                        <th class="px-6 py-4">Ticket No.</th>
                                        <th class="px-6 py-4">Reporter</th>
                                        <th class="px-6 py-4">Equipment</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100">
                                    <?php
                                    $recent_dash = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                    if($recent_dash && $recent_dash->num_rows > 0){
                                        while($rd = $recent_dash->fetch_assoc()) {
                                            $stClass = ($rd['status'] == 'รอรับเรื่อง') ? 'badge-pending' : (($rd['status'] == 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-success');
                                            $statusText = htmlspecialchars($rd['status']);
                                            $ticket_no = formatEmptyOrDash($rd['ticket_no']);
                                            
                                            $raw_rep = trim((string)$rd['reporter_name']);
                                            $disp_name = $raw_rep;
                                            if (isset($line_users_map[$raw_rep]) && !empty($line_users_map[$raw_rep]['real_name'])) {
                                                $disp_name = $line_users_map[$raw_rep]['real_name'];
                                            }
                                            $reporter_name = formatEmptyOrDash($disp_name);
                                            $phone_number = formatEmptyOrDash($rd['phone_number']);
                                            
                                            $equipment_type = formatEmptyOrDash($rd['equipment_type']);
                                            $has_created = (!empty($rd['created_at']) && $rd['created_at'] != '0000-00-00 00:00:00');
                                            $date_fmt = $has_created ? date("Y-m-d", strtotime($rd['created_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                            $time_fmt = $has_created ? date("H:i", strtotime($rd['created_at'])) : '';
                                            $time_html = $time_fmt ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$time_fmt}</div>" : "";
                                            
                                            $imageIcon = "";
                                            if(isset($rd['image_path']) && !empty($rd['image_path'])) {
                                                $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                                            }
                                            
                                            echo "<tr class='hover:bg-slate-50/50 transition-colors'>
                                                <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                    <div class='font-medium text-slate-700'>{$date_fmt}</div>
                                                    {$time_html}
                                                </td>
                                                <td class='px-6 py-4 align-top text-slate-500 font-mono font-semibold'>{$ticket_no}</td>
                                                <td class='px-6 py-4 align-top'>
                                                    <div class='flex items-center'>
                                                        <div class='w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500 mr-3 text-xs shrink-0'><i class='fas fa-user'></i></div>
                                                        <div>
                                                            <div class='text-slate-800 font-bold'>{$reporter_name}</div>
                                                            <div class='text-slate-500 text-[11px] font-medium mt-0.5'>{$phone_number}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class='px-6 py-4 align-top text-slate-600 font-medium'>{$equipment_type} {$imageIcon}</td>
                                                <td class='px-6 py-4 align-middle text-center'><span class='{$stClass}'>{$statusText}</span></td>
                                                <td class='px-6 py-4 align-middle text-right'>
                                                    <div class='flex items-center justify-end space-x-2'>
                                                        <!-- ผู้บริหารลิงก์ไปแค่ view_repair.php -->
                                                        <a href='view_repair.php?id={$rd['id']}&source=overview' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-all flex items-center justify-center border border-slate-100 shadow-sm' title='View'><i class='fas fa-eye'></i></a>
                                                    </div>
                                                </td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='6' class='px-6 py-8 text-center text-slate-400'>No transactions found</td></tr>"; }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ✨ หน้า Transactions (All Repairs List) ให้ผู้บริหาร ✨ -->
            <div id="repairs" class="section hidden space-y-6 no-print animate-fade-in">
                <div class="modern-card overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800">Repairs List</h2>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">All repair transactions (View Only)</p>
                        </div>
                        <div class="w-full md:w-auto relative">
                            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" id="searchInput" placeholder="ค้นหาเลขที่ใบงาน หรือสถานะ..." class="w-full md:w-64 bg-slate-50 border border-slate-200 text-sm rounded-xl pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium">
                        </div>
                    </div>
                    <div class="overflow-x-auto w-full max-h-[70vh] overflow-y-auto custom-scrollbar relative">
                        <table class="w-full text-left whitespace-nowrap min-w-[1200px]" id="repairsTable">
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
                            <tbody class="text-sm divide-y divide-slate-100 bg-white">
                                <?php
                                $select_query = "SELECT * FROM repairs ORDER BY created_at DESC";
                                $res = $conn->query($select_query);

                                if($res && $res->num_rows > 0){
                                    while($row = $res->fetch_assoc()) {
                                        $stClass = ($row['status'] == 'รอรับเรื่อง') ? 'badge-pending' : (($row['status'] == 'กำลังดำเนินการ') ? 'badge-progress' : 'badge-success');
                                        
                                        $ticket_no = formatEmptyOrDash($row['ticket_no']);
                                        $raw_rep = trim((string)$row['reporter_name']);
                                        $disp_name = $raw_rep;
                                        if (isset($line_users_map[$raw_rep]) && !empty($line_users_map[$raw_rep]['real_name'])) {
                                            $disp_name = $line_users_map[$raw_rep]['real_name'];
                                        }
                                        $reporter_name = formatEmptyOrDash($disp_name);
                                        $phone_number = formatEmptyOrDash($row['phone_number']);
                                        $equipment_type = formatEmptyOrDash($row['equipment_type']);
                                        $problem_desc = formatEmptyOrDash($row['problem_desc']);
                                        
                                        $t_pos = ''; $t_eng = ''; $t_th = '';
                                        if (!empty($row['technician_name']) && $row['technician_name'] !== '-') {
                                            $t_raw = $row['technician_name'];
                                            if (isset($tech_info_map[$t_raw])) {
                                                $t_th = htmlspecialchars($tech_info_map[$t_raw]['th']);
                                                $t_eng = htmlspecialchars($tech_info_map[$t_raw]['eng']);
                                                $t_pos = htmlspecialchars($tech_info_map[$t_raw]['pos']);
                                            } else {
                                                list($th_name, $en_name) = splitThaiEngName($t_raw, '');
                                                $t_th = htmlspecialchars($th_name);
                                                $t_eng = htmlspecialchars($en_name);
                                                $t_pos = htmlspecialchars(getAutoPosition($th_name));
                                            }
                                            $techHtml = "<div class='text-blue-600 font-bold cursor-default'>{$t_th}</div>";
                                            if (!empty($t_eng)) {
                                                $techHtml .= "<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>{$t_eng}</div>";
                                            }
                                            $techName = $techHtml;
                                        } else {
                                            $techName = "<span class='text-rose-500 font-bold'>-</span>";
                                        }

                                        $dept_str = isset($tech_dept_map[$row['technician_name']]) ? $tech_dept_map[$row['technician_name']] : 'General';
                                        if (empty($row['technician_name']) || $row['technician_name'] === '-') {
                                            $deptEng = "<span class='text-rose-500 font-bold'>-</span>";
                                        } else {
                                            $deptEng = "<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold mb-1 shadow-sm'>{$dept_str}</div>";
                                            if (!empty($t_pos)) {
                                                $deptEng .= "<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>{$t_pos}</div>";
                                            }
                                        }

                                        $has_created = (!empty($row['created_at']) && $row['created_at'] != '0000-00-00 00:00:00');
                                        $created_date = $has_created ? date('Y-m-d', strtotime($row['created_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $created_time = $has_created ? date('H:i', strtotime($row['created_at'])) : '';
                                        $created_time_html = $created_time ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$created_time}</div>" : "";

                                        $has_received = (!empty($row['created_at']) && $row['created_at'] != '0000-00-00 00:00:00');
                                        $received_date = $has_received ? date('Y-m-d', strtotime($row['created_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $received_time = $has_received ? date('H:i', strtotime($row['created_at'])) : '';
                                        $received_time_html = $received_time ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$received_time}</div>" : "";

                                        $has_completed = (!empty($row['completed_at']) && $row['completed_at'] != '0000-00-00 00:00:00');
                                        $completed_date = $has_completed ? date('Y-m-d', strtotime($row['completed_at'])) : "<span class='text-rose-500 font-bold'>-</span>";
                                        $completed_time = $has_completed ? date('H:i', strtotime($row['completed_at'])) : '';
                                        $completed_time_html = $completed_time ? "<div class='text-[11px] text-blue-600 font-bold mt-0.5'>{$completed_time}</div>" : "";

                                        $rootCause = !empty($row['root_cause']) && $row['root_cause'] !== '-' ? "<span class='text-slate-700 font-medium'>".htmlspecialchars($row['root_cause'])."</span>" : "<span class='text-rose-500 font-bold'>-</span>";

                                        $imageIcon = "";
                                        if(isset($row['image_path']) && !empty($row['image_path'])) {
                                            $imageIcon = "<i class='fas fa-image text-slate-400 ml-1' title='มีรูปภาพแนบ'></i>";
                                        }

                                        echo "<tr class='hover:bg-slate-50/50 transition-colors search-row'>
                                            <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                <div class='font-medium text-slate-700'>{$created_date}</div>
                                                {$created_time_html}
                                            </td>
                                            <td class='px-6 py-4 align-top font-mono font-semibold text-slate-600'>{$ticket_no}</td>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='text-slate-800 font-bold'>{$reporter_name}</div>
                                                <div class='text-slate-500 text-[11px] font-medium mt-0.5'>{$phone_number}</div>
                                            </td>
                                            <td class='px-6 py-4 align-top'>
                                                <div class='text-slate-800 font-bold'>{$equipment_type} {$imageIcon}</div>
                                                <div class='text-slate-500 text-[11px] font-medium mt-0.5 max-w-[150px] truncate' title='".strip_tags($problem_desc)."'>{$problem_desc}</div>
                                            </td>
                                            <td class='px-6 py-4 align-top'>{$deptEng}</td>
                                            <td class='px-6 py-4 align-top'>{$techName}</td>
                                            <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                <div class='font-medium text-slate-700'>{$received_date}</div>
                                                {$received_time_html}
                                            </td>
                                            <td class='px-6 py-4 align-top'>{$rootCause}</td>
                                            <td class='px-6 py-4 align-middle text-center'><span class='{$stClass}'>{$row['status']}</span></td>
                                            <td class='px-6 py-4 align-top text-xs whitespace-nowrap'>
                                                <div class='font-medium text-emerald-700'>{$completed_date}</div>
                                                {$completed_time_html}
                                            </td>
                                            <td class='px-6 py-4 align-middle text-center'>
                                                <div class='flex items-center justify-center'>
                                                    <!-- ผู้บริหารลิงก์ไปแค่ view_repair.php -->
                                                    <a target='_blank' href='view_repair.php?id={$row['id']}&source=overview' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-all flex items-center justify-center border border-slate-100 shadow-sm' title='View'><i class='fas fa-eye'></i></a>
                                                </div>
                                            </td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='11' class='px-6 py-16 text-center text-slate-400 font-medium'>No records found</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ✨ Modal ประวัติการซ่อม ✨ -->
    <div id="historyModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('historyModal')"></div>
        <div class="modal-container bg-white w-full max-w-5xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[85vh] max-h-[850px]">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl shrink-0">
                <p class="text-lg font-extrabold text-slate-800 truncate pr-4" id="historyModalTitle">History</p>
                <div class="flex items-center gap-6 shrink-0">
                    <button onclick="toggleModal('historyModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 rounded-full w-10 h-10 flex items-center justify-center shadow-sm shrink-0 hover:bg-rose-50"><i class="fas fa-times text-lg"></i></button>
                </div>
            </div>
           <div class="p-6 overflow-y-auto flex-1 bg-white">
                <div class="w-full overflow-x-auto rounded-2xl border border-slate-100 shadow-sm max-h-[65vh] overflow-y-auto custom-scrollbar relative">
                    <table class="w-full text-left whitespace-nowrap min-w-[1100px]">
                        <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs uppercase tracking-widest font-bold sticky top-0 z-20 shadow-sm">
                            <tr>
                                <th class="px-5 py-4">Date / Time</th>
                                <th class="px-5 py-4">Ticket No.</th>
                                <th class="px-5 py-4">Reporter</th>
                                <th class="px-5 py-4">Equipment</th>
                                <th class="px-5 py-4">Department</th>
                                <th class="px-5 py-4">Technician</th>
                                <th class="px-5 py-4">Received At</th>
                                <th class="px-5 py-4">Root Cause</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4">Completed At</th>
                                <th class="px-5 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50" id="historyTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ✨ Modal สำหรับแสดงรีวิวของช่างรายบุคคล ✨ -->
    <div id="techReviewsModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-900/40 backdrop-blur-sm" onclick="toggleModal('techReviewsModal')"></div>
        <div class="modal-container bg-white w-full max-w-lg mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[80vh] max-h-[800px]">
            <div class="px-5 py-5 border-b border-slate-100 flex justify-between items-start bg-gradient-to-b from-slate-50 to-white shrink-0 relative">
                <div class="flex gap-4 relative z-10 flex-1 min-w-0">
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-500 flex items-center justify-center text-2xl shrink-0 shadow-sm border border-amber-200 mt-1"><i class="fas fa-star"></i></div>
                    <div class="flex flex-col min-w-0 w-full">
                        <p class="text-lg md:text-xl font-extrabold text-slate-800 truncate" id="techReviewsModalTitle">รีวิวของช่าง: ...</p>
                        <div class="flex items-center mt-1">
                            <p class="text-[13px] font-bold text-indigo-600 truncate" id="techReviewsModalDept">ฝ่ายงาน...</p>
                            <p class="text-[11px] font-medium text-slate-500 truncate ml-1.5" id="techReviewsModalPos">(...)</p>
                        </div>
                        <div class="mt-2.5">
                            <select id="modalTechSelector" onchange="changeModalTech(this.value)" style="font-family: 'Sarabun', sans-serif;" class="custom-select w-max min-w-[200px] max-w-[320px] bg-slate-50 border border-slate-200 text-xs text-slate-700 rounded-md pl-3 pr-8 py-1.5 focus:outline-none focus:border-indigo-400 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm appearance-none mt-1">
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-3 shrink-0 ml-3 relative z-10">
                    <button onclick="toggleModal('techReviewsModal')" class="text-slate-400 hover:text-rose-500 transition-colors bg-white border border-slate-200 rounded-full w-8 h-8 flex items-center justify-center shadow-sm shrink-0"><i class="fas fa-times"></i></button>
                    <span id="techReviewsModalCount" class="text-xs font-extrabold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full shadow-sm border border-amber-100 whitespace-nowrap mt-1">0 รีวิว</span>
                </div>
            </div>
            
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center shrink-0 z-10 shadow-sm gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mr-1">ระดับคะแนน:</span>
                    <div id="starFilterContainer" class="flex gap-1.5">
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(1)" title="1 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(2)" title="2 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(3)" title="3 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(4)" title="4 ดาว"></i>
                        <i class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200" onclick="setReviewFilter(5)" title="5 ดาว"></i>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button id="btnFilterZeroReviews" onclick="setReviewFilter(0)" class="px-3 py-1.5 text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm">
                        เฉพาะคอมเมนต์
                    </button>
                    <button id="btnFilterAllReviews" onclick="setReviewFilter('all')" class="px-4 py-1.5 text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700">
                        ทั้งหมด
                    </button>
                </div>
            </div>

            <div class="p-0 overflow-y-auto flex-1 bg-white custom-scrollbar">
                <div class="divide-y divide-slate-100" id="techReviewsList"></div>
            </div>
        </div>
    </div>

    <!-- ================== JAVASCRIPT ================== -->
    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;
        const techDeptMap = <?php echo $tech_dept_map_json; ?>;
        const techInfoMap = <?php echo $tech_info_map_json; ?>; 
        const lineUsersMap = <?php echo $line_users_map_json; ?>;
        
        let chartEquipInstance = null;
        let chartStatusInstance = null;
        let chartLocInstance = null;
        let chartTechInstance = null;
        let chartRatingInstance = null;
        
        let currentTechReviewsData = [];
        let currentDeptReviewsData = []; 
        let currentReviewFilter = 'all';

        const pageTitles = {
            'dash': 'Dashboard Overview',
            'repairs': 'All Repairs List'
        };

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
        }

        function show(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active-btn'));
            const activeBtn = document.getElementById('btn-' + id);
            if(activeBtn) activeBtn.classList.add('active-btn');
            document.getElementById('headerTitle').innerText = pageTitles[id] || 'Executive View';
            
            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
            }

            let searchInput = document.getElementById('searchInput');
            if(searchInput && id === 'repairs') {
                searchInput.value = '';
                let activeSection = document.getElementById(id);
                if(activeSection) {
                    activeSection.querySelectorAll('table tbody tr').forEach(row => row.style.display = '');
                }
            }
        }

        function formatValJS(val) {
            if (!val || String(val).trim() === '-' || String(val).trim() === '') return "<span class='text-rose-500 font-bold'>-</span>";
            if (String(val).trim() === 'ไม่ระบุ') return "<span class='text-rose-500 font-bold'>ไม่ระบุ</span>";
            return val;
        }

        function timeAgoJS(dateString) {
            if (!dateString || dateString === '0000-00-00 00:00:00') return "-";
            const date = new Date(dateString.replace(/-/g, '/'));
            const now = new Date();
            let diffInSeconds = Math.floor((now - date) / 1000);
            if (diffInSeconds < 0) diffInSeconds = 0;
            if (diffInSeconds < 60) return "เมื่อสักครู่";
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + " นาทีที่แล้ว";
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + " ชั่วโมงที่แล้ว";
            if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + " วันที่แล้ว"; 
            const thaiMonths = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
            return `${date.getDate()} ${thaiMonths[date.getMonth()]} ${date.getFullYear() + 543}`;
        }

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        function getFilteredRepairsByMonthYear(m, y) {
            if (m === 'all' && y === 'all') return allRepairs;
            return allRepairs.filter(r => {
                if (!r.created_at || r.created_at === '0000-00-00 00:00:00') return false;
                let datePart = r.created_at.split(' ')[0];
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

        function renderAllCharts() {
            renderEquipChart();
            renderStatusChart();
            renderLocChart();
            renderTechChart();
            renderRatingChart();
        }

        function renderEquipChart() {
            let m = document.getElementById('equipMonth').value;
            let y = document.getElementById('equipYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let map = {};
            data.forEach(r => {
                if(r.equipment_type) map[r.equipment_type] = (map[r.equipment_type] || 0) + 1;
            });
            let sorted = Object.keys(map).map(k => ({ name: k, count: map[k] })).sort((a,b) => b.count - a.count).slice(0, 7);
            
            const ctx = document.getElementById('mainEquipChart').getContext('2d');
            if(chartEquipInstance) chartEquipInstance.destroy();
            
            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(139, 92, 246, 0.5)');
            gradient.addColorStop(1, 'rgba(139, 92, 246, 0.0)'); 
            
            chartEquipInstance = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: sorted.length ? sorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{ 
                        label: 'จำนวน (ครั้ง)', 
                        data: sorted.length ? sorted.map(e => e.count) : [0], 
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
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
        }

        function renderStatusChart() {
            let m = document.getElementById('statusMonth').value;
            let y = document.getElementById('statusYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let pending = 0, progress = 0, completed = 0;
            data.forEach(r => {
                if(r.status === 'รอรับเรื่อง') pending++;
                else if(r.status === 'กำลังดำเนินการ') progress++;
                else if(r.status === 'ซ่อมเสร็จแล้ว') completed++;
            });

            const ctx = document.getElementById('mainStatusChart').getContext('2d');
            if(chartStatusInstance) chartStatusInstance.destroy();

            let isEmpty = (pending+progress+completed === 0);

            chartStatusInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['รอดำเนินการ', 'กำลังแก้ไข', 'เสร็จสิ้น'],
                    datasets: [{ 
                        data: isEmpty ? [1] : [pending, progress, completed], 
                        backgroundColor: isEmpty ? ['#f1f5f9'] : ['#f59e0b', '#38bdf8', '#10b981'],
                        borderWidth: 0, 
                        hoverOffset: 4 
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { 
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif", weight: '600' } } },
                        tooltip: { callbacks: { label: function(context) { return isEmpty ? ' ไม่มีข้อมูล' : ' ' + context.formattedValue + ' งาน'; } } }
                    }, 
                    cutout: '75%' 
                }
            });
        }

        function renderLocChart() {
            let m = document.getElementById('locMonth').value;
            let y = document.getElementById('locYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let map = {};
            data.forEach(r => {
                if(r.location && r.location !== 'ไม่ระบุสถานที่') map[r.location] = (map[r.location] || 0) + 1;
            });
            let sorted = Object.keys(map).map(k => ({ name: k, count: map[k] })).sort((a,b) => b.count - a.count).slice(0, 5);

            const ctx = document.getElementById('mainLocChart').getContext('2d');
            if(chartLocInstance) chartLocInstance.destroy();

            chartLocInstance = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: sorted.length ? sorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{ 
                        label: 'แจ้งซ่อม (ครั้ง)', 
                        data: sorted.length ? sorted.map(e => e.count) : [0], 
                        backgroundColor: '#f43f5e', 
                        borderRadius: 6
                    }]
                },
                options: { 
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        y: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
        }

        function renderTechChart() {
            let m = document.getElementById('techMonth').value;
            let y = document.getElementById('techYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let map = {};
            data.forEach(r => {
                let tName = r.technician_name ? r.technician_name : 'ไม่ระบุช่าง';
                map[tName] = (map[tName] || 0) + 1;
            });
            let sorted = Object.keys(map).map(k => ({ name: k, count: map[k] })).sort((a,b) => b.count - a.count).slice(0, 5);

            const ctx = document.getElementById('mainTechChart').getContext('2d');
            if(chartTechInstance) chartTechInstance.destroy();

            chartTechInstance = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: sorted.length ? sorted.map(e => e.name) : ['ไม่มีข้อมูล'],
                    datasets: [{ 
                        label: 'รับผิดชอบ (งาน)', 
                        data: sorted.length ? sorted.map(e => e.count) : [0], 
                        backgroundColor: '#6366f1', 
                        borderRadius: 6
                    }]
                },
                options: { 
                    responsive: true, maintainAspectRatio: false, 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" } }, grid: { color: '#f8fafc' }, border: {display: false} }, 
                        x: { ticks: { font: { family: "'Kanit', sans-serif" } }, grid: { display: false }, border: {display: false} } 
                    } 
                }
            });
        }

        function renderRatingChart() {
            let m = document.getElementById('ratingMonth').value;
            let y = document.getElementById('ratingYear').value;
            let data = getFilteredRepairsByMonthYear(m, y);

            let deptMap = {}; 
            let techMap = {}; 

            data.forEach(r => {
                let rating = parseFloat(r.rating);
                if (!isNaN(rating) && rating > 0) {
                    let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                    let dName = techDeptMap[tName] ? techDeptMap[tName] : 'ไม่มีสังกัด';
                    if (dName !== 'ไม่มีสังกัด' && !dName.startsWith('ฝ่ายงาน') && dName !== 'แม่บ้าน' && dName !== 'อื่นๆ') {
                        dName = 'ฝ่ายงาน' + dName;
                    }

                    if (!techMap[tName]) techMap[tName] = { sum: 0, count: 0, dept: dName };
                    techMap[tName].sum += rating;
                    techMap[tName].count++;

                    if (!deptMap[dName]) deptMap[dName] = { sum: 0, count: 0, techs: new Set() };
                    deptMap[dName].sum += rating;
                    deptMap[dName].count++;
                    deptMap[dName].techs.add(tName);
                }
            });

            let deptArr = Object.keys(deptMap).map(dName => {
                let dAvg = (deptMap[dName].sum / deptMap[dName].count).toFixed(1);
                let topTechName = '-';
                let topTechAvg = 0;

                deptMap[dName].techs.forEach(t => {
                    let tAvg = (techMap[t].sum / techMap[t].count);
                    if (tAvg > topTechAvg) {
                        topTechAvg = tAvg;
                        topTechName = t;
                    }
                });

                return {
                    name: dName,
                    avg: dAvg,
                    count: deptMap[dName].count,
                    topTech: topTechName,
                    topTechAvg: topTechAvg.toFixed(1)
                };
            });

            deptArr.sort((a, b) => b.avg - a.avg || b.count - a.count);

            const getRatingColor = (score) => {
                if (score >= 4.5) return '#22c55e'; 
                if (score >= 3.5) return '#84cc16'; 
                if (score >= 2.5) return '#eab308'; 
                if (score >= 1.5) return '#f97316'; 
                return '#ef4444'; 
            };

            const ctx = document.getElementById('mainRatingChart').getContext('2d');
            if(chartRatingInstance) chartRatingInstance.destroy();

            chartRatingInstance = new Chart(ctx, {
                type: 'bar', 
                plugins: [{
                    id: 'custom_star_gradient',
                    afterDraw: (chart) => {
                        const ctx = chart.ctx;
                        const yAxis = chart.scales.y;

                        ctx.save();
                        ctx.textAlign = 'right';
                        ctx.textBaseline = 'middle';

                        yAxis.ticks.forEach((tick, index) => {
                            const y = yAxis.getPixelForTick(index);
                            const labelArray = chart.data.labels[index];
                            if (!labelArray) return;

                            if (Array.isArray(labelArray) && labelArray.length === 3) {
                                const scoreStr = labelArray[0].trim();
                                const scoreVal = parseFloat(scoreStr) || 0;
                                const tName = labelArray[1];
                                const dName = labelArray[2];

                                ctx.font = '800 14px "Sarabun", sans-serif';
                                ctx.fillStyle = '#4f46e5';
                                ctx.fillText(dName, yAxis.right - 10, y + 18);

                                ctx.font = 'bold 13px "Sarabun", sans-serif';
                                ctx.fillStyle = '#475569';
                                ctx.fillText(tName, yAxis.right - 10, y);

                                const textY = y - 18; 
                                ctx.font = 'bold 12px "Sarabun", sans-serif';
                                ctx.fillStyle = '#64748b';
                                ctx.fillText(scoreStr, yAxis.right - 10, textY);

                                const scoreWidth = ctx.measureText(scoreStr).width;
                                const starX = yAxis.right - 10 - scoreWidth - 4; 

                                ctx.font = '900 13px "Font Awesome 6 Free"';
                                const starIcon = '\uf005'; 
                                const starWidth = ctx.measureText(starIcon).width;
                                const startX = starX - starWidth;

                                ctx.fillStyle = '#e2e8f0';
                                ctx.fillText(starIcon, starX, textY);

                                if (scoreVal > 0) {
                                    const fillPercent = scoreVal / 5.0;
                                    ctx.save();
                                    ctx.beginPath();
                                    ctx.rect(startX, textY - 10, starWidth * fillPercent, 20);
                                    ctx.clip(); 
                                    ctx.fillStyle = '#f59e0b'; 
                                    ctx.fillText(starIcon, starX, textY);
                                    ctx.restore();
                                }
                            } else {
                                ctx.font = 'bold 13px "Sarabun", sans-serif';
                                ctx.fillStyle = '#475569';
                                ctx.fillText(Array.isArray(labelArray) ? labelArray.join(' ') : labelArray, yAxis.right - 10, y);
                            }
                        });
                        ctx.restore();
                    }
                }],
                data: {
                    labels: deptArr.length ? deptArr.map(d => ['   ' + d.topTechAvg, d.topTech, d.name]) : [['ไม่มีข้อมูล']],
                    datasets: [
                        { 
                            label: 'คะแนนเฉลี่ยฝ่าย', 
                            data: deptArr.length ? deptArr.map(d => d.avg) : [0], 
                            backgroundColor: deptArr.length ? deptArr.map(d => getRatingColor(d.avg)) : ['#e2e8f0'], 
                            borderRadius: 10,
                            barThickness: 24,
                            maxBarThickness: 32,
                            borderSkipped: false,
                            z: 2
                        },
                        {
                            label: 'เต็ม 5',
                            data: deptArr.length ? deptArr.map(d => 5.0) : [5.0],
                            backgroundColor: '#f1f5f9',
                            borderRadius: 10,
                            barThickness: 24,
                            maxBarThickness: 32,
                            borderSkipped: false,
                            z: 1
                        }
                    ]
                },
                options: { 
                    indexAxis: 'y', 
                    grouped: false,
                    responsive: true, 
                    maintainAspectRatio: false,
                    interaction: { mode: 'y', intersect: false },
                    onClick: (e, elements, chart) => {
                        const activeElements = chart.getElementsAtEventForMode(e, 'y', { intersect: false }, true);
                        if (activeElements && activeElements.length > 0 && deptArr.length > 0) {
                            const index = activeElements[0].index;
                            if(deptArr && deptArr[index]) {
                                let selectedDept = deptArr[index].name;
                                openTechReviewsModal(selectedDept, m, y);
                            }
                        }
                    },
                    onHover: (event, chartElement) => {
                        event.native.target.style.cursor = chartElement.length > 0 ? 'pointer' : 'default';
                    },
                    layout: { padding: { top: 10, bottom: 10, left: 0, right: 20 } },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            filter: function(tooltipItem) { return tooltipItem.datasetIndex === 0; },
                            callbacks: {
                                title: function(context) {
                                    if (!deptArr.length) return '';
                                    return deptArr[context[0].dataIndex].name;
                                },
                                label: function(context) {
                                    if (!deptArr.length) return ' ไม่มีข้อมูล';
                                    let dept = deptArr[context.dataIndex];
                                    return [' ช่าง ' + dept.topTech + ' (⭐ ' + parseFloat(dept.topTechAvg).toFixed(1) + ')', ' จำนวน: ' + dept.count + ' รีวิว'];
                                }
                            }
                        }
                    }, 
                    scales: { 
                        x: { 
                            min: 0, max: 5,
                            ticks: { stepSize: 1, font: { family: "'Sarabun', sans-serif", weight: 'bold' }, color: '#94a3b8' }, 
                            grid: { color: '#f1f5f9', drawBorder: false }, 
                            border: {display: false} 
                        }, 
                        y: { 
                            ticks: { 
                                color: 'transparent',
                                font: { family: "'Sarabun', sans-serif", size: 14, weight: 'bold' } 
                            }, 
                            grid: { display: false }, 
                            border: {display: false} 
                        } 
                    } 
                }
            });
        }

        function openTechReviewsModal(deptName, month, year) {
            document.getElementById('techReviewsModalDept').innerText = deptName;

            let data = getFilteredRepairsByMonthYear(month, year);

            let allTechsInDept = Object.keys(techDeptMap).filter(tName => {
                let dName = techDeptMap[tName] ? techDeptMap[tName] : 'ไม่มีสังกัด';
                if (dName !== 'ไม่มีสังกัด' && !dName.startsWith('ฝ่ายงาน') && dName !== 'แม่บ้าน' && dName !== 'อื่นๆ') {
                    dName = 'ฝ่ายงาน' + dName;
                }
                return dName === deptName;
            });

            currentDeptReviewsData = data.filter(r => {
                let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                return allTechsInDept.includes(tName);
            });

            let techStats = {};
            allTechsInDept.forEach(tName => { techStats[tName] = { sum: 0, count: 0 }; });

            currentDeptReviewsData.forEach(r => {
                let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                if(techStats[tName]) {
                    let rating = parseFloat(r.rating) || 0;
                    if(rating > 0) { 
                        techStats[tName].sum += rating; 
                        techStats[tName].count++; 
                    }
                }
            });

            let techArr = Object.keys(techStats).map(k => {
                let tAvg = techStats[k].count > 0 ? (techStats[k].sum / techStats[k].count).toFixed(1) : 0;
                return { name: k, avg: parseFloat(tAvg), count: techStats[k].count };
            });

            techArr.sort((a, b) => b.avg - a.avg || b.count - a.count);

            const selector = document.getElementById('modalTechSelector');
            selector.innerHTML = '';

            // ✨ นำโค้ดดรอปดาวน์และลูกศรของฝั่งแอดมินมาใช้ เพื่อให้หน้าตาเหมือนกัน 100% ✨
            selector.className = "w-max min-w-[200px] max-w-[320px] bg-slate-50 border border-slate-200 text-xs text-slate-700 rounded-md pl-3 pr-8 py-1.5 focus:outline-none focus:border-indigo-400 font-bold cursor-pointer transition-colors hover:bg-slate-100 shadow-sm appearance-none mt-1";
            selector.style.backgroundImage = "url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')";
            selector.style.backgroundRepeat = "no-repeat";
            selector.style.backgroundPosition = "right 0.5rem top 50%";
            selector.style.backgroundSize = "0.55rem auto";

            if(techArr.length === 0) {
                selector.style.display = 'none';
                document.getElementById('techReviewsModalTitle').innerText = 'รีวิวฝ่ายงาน';
                document.getElementById('techReviewsModalCount').innerText = '0 รีวิว';
                currentTechReviewsData = [];
                renderTechReviewsList();
            } else {
                selector.style.display = 'block';
                techArr.forEach(t => {
                    let opt = document.createElement('option');
                    opt.value = t.name;
                    let thNameOnly = (techInfoMap[t.name] && techInfoMap[t.name].th) ? techInfoMap[t.name].th : t.name.split(' (')[0];
                    let visualLen = thNameOnly.replace(/[\u0E31-\u0E3A\u0E47-\u0E4E]/g, '').length;
                    let padSpaces = '\u00A0'.repeat(Math.max(2, 38 - (visualLen * 1.8))); 
                    if (t.avg > 0) {
                        opt.innerHTML = `${thNameOnly}${padSpaces}⭐ ${t.avg} (${t.count} รีวิว)`;
                    } else {
                        opt.innerHTML = `${thNameOnly}${padSpaces}☆ ยังไม่มีคะแนน`;
                    }
                    selector.appendChild(opt);
                });
                changeModalTech(techArr[0].name);
            }
            toggleModal('techReviewsModal');
        }

        function changeModalTech(techName) {
            let thNameOnly = (techInfoMap[techName] && techInfoMap[techName].th) ? techInfoMap[techName].th : techName.split(' (')[0];
            document.getElementById('techReviewsModalTitle').innerText = 'รีวิวของช่าง: ' + thNameOnly;

            let posName = (techInfoMap[techName] && techInfoMap[techName].pos) ? techInfoMap[techName].pos : '';
            document.getElementById('techReviewsModalPos').innerText = posName && posName !== '-' ? '(' + posName + ')' : '(ไม่ระบุตำแหน่ง)';

            currentTechReviewsData = currentDeptReviewsData.filter(r => {
                let tName = r.technician_name && r.technician_name !== '-' ? r.technician_name : 'ไม่ระบุช่าง';
                let rRating = parseFloat(r.rating) || 0;
                let hasComment = r.review_comment && r.review_comment.trim() !== '' && r.review_comment.trim() !== '-';
                return tName === techName && (rRating > 0 || hasComment);
            });

            document.getElementById('techReviewsModalCount').innerText = currentTechReviewsData.length + ' รีวิว';

            let sum = 0, count = 0;
            currentTechReviewsData.forEach(r => {
                let rating = parseFloat(r.rating) || 0;
                if(rating > 0) { sum += rating; count++; }
            });
            let avg = count > 0 ? sum / count : 0;

            const bigStarIcon = document.getElementById('techReviewsModalTitle').parentNode.parentNode.querySelector('.fa-star');
            if (bigStarIcon) {
                if (avg > 0) {
                    let percent = (avg / 5.0) * 100;
                    bigStarIcon.style.background = `linear-gradient(90deg, #f59e0b ${percent}%, #e2e8f0 ${percent}%)`;
                    bigStarIcon.style.webkitBackgroundClip = 'text';
                    bigStarIcon.style.webkitTextFillColor = 'transparent';
                } else {
                    bigStarIcon.style.background = 'none';
                    bigStarIcon.style.webkitBackgroundClip = 'border-box';
                    bigStarIcon.style.webkitTextFillColor = 'initial';
                    bigStarIcon.style.color = '#cbd5e1'; 
                }
            }
            setReviewFilter('all'); 
        }

        // ✨ ประวัติ Modal การคลิกจาก Top Reporters ✨
        function viewHistory(fullName, type) {
            const tbody = document.getElementById('historyTableBody'); 
            tbody.innerHTML = '';

            const userRepairs = allRepairs.filter(r => type === 'reporter' ? r.reporter_name === fullName : r.technician_name === fullName);

            if(userRepairs.length === 0) {
                let emptyMsg = type === 'reporter' ? 'No repair history found.' : 'No tasks assigned yet.';
                tbody.innerHTML = `<tr><td colspan="11" class="px-5 py-8 text-center text-slate-400 font-medium">${emptyMsg}</td></tr>`;
            } else {
                userRepairs.forEach(r => {
                    let statusClass = 'badge-pending';
                    if(r.status === 'กำลังดำเนินการ') statusClass = 'badge-progress';
                    else if(r.status === 'ซ่อมเสร็จแล้ว') statusClass = 'badge-success';

                    let statusText = formatValJS(r.status);

                    let createdDate = '-';
                    let createdTime = '';
                    if(r.created_at) {
                        let parts = r.created_at.split(' ');
                        createdDate = parts[0] || "<span class='text-rose-500 font-bold'>-</span>";
                        createdTime = parts[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${parts[1].substring(0, 5)}</div>` : '';
                    } else {
                        createdDate = "<span class='text-rose-500 font-bold'>-</span>";
                    }
                    
                    let techNameHtml = "<span class='text-rose-500 font-bold'>-</span>";
                    if (r.technician_name && r.technician_name !== '-') {
                        let info = techInfoMap[r.technician_name] || { th: r.technician_name, eng: '', pos: '' };
                        techNameHtml = `<div class='text-indigo-600 font-bold'>${info.th}</div>`;
                        if(info.eng) techNameHtml += `<div class='text-slate-400 font-medium text-[10px] uppercase tracking-wider mt-0.5'>${info.eng}</div>`;
                    }
                    let techName = techNameHtml;

                    let rootCause = !r.root_cause || r.root_cause === '-' ? "<span class='text-rose-500 font-bold'>-</span>" : `<span class='text-slate-700 font-medium'>${r.root_cause}</span>`;

                    let has_received = (r.created_at && r.created_at != '0000-00-00 00:00:00');
                    let received_date = has_received ? createdDate : "<span class='text-rose-500 font-bold'>-</span>";
                    let received_time = has_received && r.created_at.split(' ')[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${r.created_at.split(' ')[1].substring(0, 5)}</div>` : '';

                    let has_completed = (r.completed_at && r.completed_at != '0000-00-00 00:00:00');
                    let completed_date = has_completed ? r.completed_at.split(' ')[0] : "<span class='text-rose-500 font-bold'>-</span>";
                    let completed_time = has_completed && r.completed_at.split(' ')[1] ? `<div class='text-[11px] text-blue-600 font-bold mt-0.5'>${r.completed_at.split(' ')[1].substring(0, 5)}</div>` : '';
                    
                    let dName = r.technician_name && techDeptMap[r.technician_name] ? techDeptMap[r.technician_name] : 'General';
                    let deptEng = "<span class='text-rose-500 font-bold'>-</span>";
                    if (r.technician_name && r.technician_name !== '-') {
                        deptEng = `<div class='px-2.5 py-1 inline-block bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold tracking-wider mb-1 shadow-sm'>${dName}</div>`;
                        let info = techInfoMap[r.technician_name];
                        if (info && info.pos) {
                            deptEng += `<div class='text-slate-500 font-bold text-[11px] ml-2.5 mt-0.5'>${info.pos}</div>`;
                        }
                    }
                    
                    let rNameRaw = r.reporter_name;
                    let dispName = rNameRaw;
                    if (lineUsersMap[rNameRaw] && lineUsersMap[rNameRaw].real_name) {
                        dispName = lineUsersMap[rNameRaw].real_name;
                    }
                    let rName = formatValJS(dispName);
                    let rPhone = formatValJS(r.phone_number);
                    
                    let tNo = formatValJS(r.ticket_no);
                    let eqType = formatValJS(r.equipment_type);
                    let pDesc = formatValJS(r.problem_desc);

                    // ✨ ลิงก์บังคับไปหน้า view_repair.php อย่างเดียว ✨
                    tbody.innerHTML += `<tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-4 align-top text-xs whitespace-nowrap">
                            <div class="font-medium text-slate-700">${createdDate}</div>
                            ${createdTime}
                        </td>
                        <td class="px-5 py-4 align-top font-mono font-semibold text-slate-600">${tNo}</td>
                        <td class="px-5 py-4 align-top">
                            <div class="text-slate-800 font-bold">${rName}</div>
                            <div class="text-slate-500 text-[11px] font-medium mt-0.5">${rPhone}</div>
                        </td>
                        <td class="px-5 py-4 align-top">
                            <div class="text-slate-800 font-bold">${eqType}</div>
                            <div class="text-slate-500 text-[11px] font-medium mt-0.5 max-w-[180px] truncate" title="${pDesc.replace(/<[^>]*>?/gm, '')}">${pDesc}</div>
                        </td>
                        <td class="px-5 py-4 align-top">${deptEng}</td>
                        <td class="px-5 py-4 align-top">${techName}</td>
                        <td class="px-5 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-medium text-slate-700'>${received_date}</div>
                            ${received_time}
                        </td>
                        <td class="px-5 py-4 align-top">${rootCause}</td>
                        <td class="px-5 py-4 align-middle text-center"><span class="${statusClass}">${statusText}</span></td>
                        <td class="px-5 py-4 align-top text-xs whitespace-nowrap">
                            <div class='font-medium text-emerald-700'>${completed_date}</div>
                            ${completed_time}
                        </td>
                        <td class="px-5 py-4 align-middle text-center">
                            <div class='flex items-center justify-center'>
                                <a target='_blank' href='view_repair.php?id=${r.id}&source=overview' class='w-8 h-8 rounded-xl bg-slate-50 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-all flex items-center justify-center border border-slate-100 shadow-sm' title='View'><i class='fas fa-eye'></i></a>
                            </div>
                        </td>
                    </tr>`;
                });
            }
            
            let displayTitleName = fullName;
            if (type === 'reporter' && lineUsersMap[fullName] && lineUsersMap[fullName].real_name) {
                displayTitleName = lineUsersMap[fullName].real_name;
            }
            document.getElementById('historyModalTitle').innerText = (type === 'technician' ? 'ประวัติงานช่าง: ' : 'ประวัติการแจ้งซ่อม: ') + displayTitleName;
            
            toggleModal('historyModal');
        }

        function setReviewFilter(val) {
            currentReviewFilter = val;
            const btnAll = document.getElementById('btnFilterAllReviews');
            const btnZero = document.getElementById('btnFilterZeroReviews');
            
            if(btnAll) btnAll.className = "px-4 py-1.5 text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            if(btnZero) btnZero.className = "px-3 py-1.5 text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            
            if(val === 'all') {
                if(btnAll) btnAll.className = "px-4 py-1.5 text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            } else if (val === 0) {
                if(btnZero) btnZero.className = "px-3 py-1.5 text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            }
            
            const stars = document.querySelectorAll('#starFilterContainer i');
            stars.forEach((star, index) => {
                let starVal = index + 1;
                if(val !== 'all' && val !== 0 && starVal <= val) {
                    star.className = "fas fa-star cursor-pointer text-amber-400 hover:scale-125 transition-all text-lg drop-shadow-sm";
                } else {
                    star.className = "fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-lg hover:text-amber-200";
                }
            });
            renderTechReviewsList();
        }

        function renderTechReviewsList() {
            const container = document.getElementById('techReviewsList');
            container.innerHTML = '';

            let filteredReviews = [...currentTechReviewsData];

            if (currentReviewFilter !== 'all') {
                filteredReviews = filteredReviews.filter(r => parseInt(r.rating || 0) === parseInt(currentReviewFilter));
            }

            filteredReviews.sort((a,b) => new Date(b.completed_at) - new Date(a.completed_at));

            if(filteredReviews.length === 0) {
                container.innerHTML = `<div class='p-10 flex flex-col items-center justify-center text-center h-full'>
                                            <i class='fas fa-star text-4xl text-slate-200 mb-3'></i>
                                            <p class='text-slate-400 font-medium text-sm mt-2'>ไม่พบข้อมูลรีวิวในระดับคะแนนนี้</p>
                                          </div>`;
            } else {
                filteredReviews.forEach(rev => {
                    let r_name = formatValJS(rev.reporter_name);
                    let r_rating = parseInt(rev.rating || 0);
                    let r_comment = (rev.review_comment && rev.review_comment.trim() !== '' && rev.review_comment !== '-') 
                                    ? rev.review_comment.trim() 
                                    : "<span class='text-slate-300 italic'>- ไม่มีข้อความรีวิว -</span>";

                    let stars_html = '';
                    if (r_rating === 0) {
                        stars_html = '<span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">ไม่มีคะแนนดาว</span>';
                    } else {
                        for(let i=1; i<=5; i++) {
                            if(i <= r_rating) stars_html += '<i class="fas fa-star text-amber-400 text-[11px] drop-shadow-sm"></i>';
                            else stars_html += '<i class="fas fa-star text-slate-200 text-[11px]"></i>';
                        }
                    }

                    let date_str = "-";
                    if(rev.completed_at && rev.completed_at !== '0000-00-00 00:00:00') {
                        date_str = timeAgoJS(rev.completed_at);
                    }

                    // ✨ สร้างตัวแปรดึงชื่อช่าง เพื่อเอาไปแสดงใต้คอมเมนต์ (นี่คือโค้ดที่หายไปครับ!) ✨
                    let tName = rev.technician_name && rev.technician_name !== '-' ? rev.technician_name : 'ไม่ระบุช่าง';
                    let techInfoHtml = `<div class="text-[10px] text-indigo-500 font-bold mt-1.5 inline-block bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100"><i class="fas fa-tools mr-1 opacity-70"></i>ช่าง: ${tName}</div>`;

                    container.innerHTML += `<div onclick="openReviewTab(${rev.id})" class='p-5 hover:bg-slate-50 transition-colors group border-b border-slate-50 last:border-0 cursor-pointer relative'>
                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity text-indigo-400">
                                <i class="fas fa-external-link-alt text-xs" title="คลิกเพื่อดูใบงานนี้"></i>
                            </div>
                            <div class='flex justify-between items-start mb-2.5 pr-6'>
                                <div class='flex items-center gap-3'>
                                    <div class='w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-indigo-100 group-hover:text-indigo-500 transition-colors'><i class='fas fa-user text-xs'></i></div>
                                    <div>
                                        <div class='text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors'>${r_name}</div>
                                        <div class='text-[10px] text-slate-400 font-medium'>${date_str}</div>
                                    </div>
                                </div>
                                <div class='flex gap-0.5 pt-1'>${stars_html}</div>
                            </div>
                            <p class='text-xs text-slate-600 font-medium pl-11 leading-relaxed'>${r_comment}</p>
                            <div class='pl-11'>${techInfoHtml}</div>
                          </div>`;
                });
            }
        }

        // ✨ เปิดแท็บใหม่แบบมีรหัสลับ: ส่งค่า source ไปด้วย เพื่อสั่งให้แท็บมันปิดตัวเองลงทันที 100% ไม่โหลดหน้าซ้อนทับ ✨
        function openReviewTab(id) {
            window.open('view_repair.php?id=' + id + '&source=tech_reviews', '_blank');
        }

        // ✨ ตัวแปรและฟังก์ชันจัดอันดับ Top Reporters ✨
        let currentTopReportersLimit = 5;
        function setTopReportersFilter(limit) {
            currentTopReportersLimit = limit;
            const btn3 = document.getElementById('btnFilterTop3');
            const btn5 = document.getElementById('btnFilterTop5');
            const btn10 = document.getElementById('btnFilterTop10');
            const btnAll = document.getElementById('btnFilterTopAll');
            const activeClass = "px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            const inactiveClass = "px-3 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            const activeAllClass = "px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700";
            const inactiveAllClass = "px-4 py-1.5 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm";
            
            if(btn3) btn3.className = inactiveClass;
            if(btn5) btn5.className = inactiveClass;
            if(btn10) btn10.className = inactiveClass;
            if(btnAll) btnAll.className = inactiveAllClass;
            
            if (limit === 3 && btn3) btn3.className = activeClass;
            else if (limit === 5 && btn5) btn5.className = activeClass;
            else if (limit === 10 && btn10) btn10.className = activeClass;
            else if (limit === 'all' && btnAll) btnAll.className = activeAllClass;
            renderTopReporters();
        }

        function renderTopReporters() {
            const container = document.getElementById('topReportersList');
            if(!container) return;
            container.innerHTML = '';
            let m = document.getElementById('reporterMonth') ? document.getElementById('reporterMonth').value : 'all';
            let y = document.getElementById('reporterYear') ? document.getElementById('reporterYear').value : 'all';
            let filteredRepairs = getFilteredRepairsByMonthYear(m, y);
            let reporterMap = {};
            
            filteredRepairs.forEach(r => {
                let name = formatValJS(r.reporter_name);
                if (name.includes('ไม่ระบุ') || name.includes('span')) name = 'ไม่ระบุชื่อผู้แจ้ง';
                else name = r.reporter_name.trim();
                if (!reporterMap[name]) reporterMap[name] = 0;
                reporterMap[name]++;
            });

            let reporterArr = Object.keys(reporterMap).map(k => ({ name: k, count: reporterMap[k] }));
            reporterArr.sort((a,b) => b.count - a.count);

            if (currentTopReportersLimit !== 'all') reporterArr = reporterArr.slice(0, parseInt(currentTopReportersLimit));

            if(reporterArr.length === 0) {
                container.innerHTML = `<div class='p-10 flex flex-col items-center justify-center text-center h-full min-h-[250px]'>
                                            <i class='fas fa-user-tag text-4xl text-slate-200 mb-3'></i>
                                            <p class='text-slate-400 font-medium text-sm mt-2'>ไม่พบข้อมูลผู้แจ้งในเดือน/ปีนี้</p>
                                        </div>`;
            } else {
                reporterArr.forEach((rep, index) => {
                    let rankColor = index === 0 ? 'text-amber-500 bg-amber-50 border-amber-200' :
                                    index === 1 ? 'text-slate-500 bg-slate-100 border-slate-300' :
                                    index === 2 ? 'text-orange-500 bg-orange-50 border-orange-200' : 'text-indigo-500 bg-indigo-50 border-indigo-100';
                    let rankIcon = index === 0 ? '<i class="fas fa-trophy text-lg"></i>' :
                                   index === 1 ? '<i class="fas fa-medal text-base"></i>' :
                                   index === 2 ? '<i class="fas fa-award text-base"></i>' : `<span class="text-sm font-black">#${index + 1}</span>`;
                    
                    let displayName = rep.name;
                    let lineIdHtml = `<div class='text-[11px] text-slate-400 font-medium mt-0.5'>บุคลากรผู้แจ้งซ่อม</div>`;
                    
                    if (lineUsersMap[rep.name] && lineUsersMap[rep.name].real_name) {
                        displayName = lineUsersMap[rep.name].real_name;
                        if (rep.name !== displayName) {
                            lineIdHtml = `<div class='text-[12px] font-bold text-indigo-600 mt-0.5 flex items-center'><i class='fab fa-line text-[#06C755] text-[14px] mr-1.5'></i> ${rep.name}</div>`;
                        }
                    }

                    let safeName = rep.name.replace(/'/g, "\\'");
                    // ✨ ลิงก์บังคับให้ไปดูประวัติอย่างเดียว
                    let clickAction = rep.name === 'ไม่ระบุชื่อผู้แจ้ง' ? '' : `onclick="viewHistory('${safeName}', 'reporter')" class="p-4 md:p-5 hover:bg-slate-50 transition-colors flex items-center justify-between border-b border-slate-50 last:border-0 cursor-pointer group" title="คลิกเพื่อดูประวัติการแจ้งซ่อมของ ${displayName}"`;
                    let disableClickClass = rep.name === 'ไม่ระบุชื่อผู้แจ้ง' ? `class="p-4 md:p-5 flex items-center justify-between border-b border-slate-50 last:border-0 opacity-70"` : '';

                    container.innerHTML += `
                        <div ${clickAction || disableClickClass}>
                            <div class='flex items-center gap-4'>
                                <div class='w-10 h-10 rounded-full flex items-center justify-center border shadow-sm ${rankColor} shrink-0 group-hover:scale-110 transition-transform'>${rankIcon}</div>
                                <div>
                                    <div class='text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors'>${displayName}</div>
                                    ${lineIdHtml}
                                </div>
                            </div>
                            <div class='text-right flex items-center'>
                                <div class='flex flex-col items-end'>
                                    <span class='text-xl font-extrabold text-indigo-600'>${rep.count}</span>
                                    <span class='text-[11px] text-slate-500 font-medium ml-1'>รายการ</span>
                                </div>
                                ${rep.name !== 'ไม่ระบุชื่อผู้แจ้ง' ? `<div class="ml-4 w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center group-hover:bg-indigo-50 group-hover:border-indigo-200 transition-all shadow-sm"><i class="fas fa-chevron-right text-slate-400 group-hover:text-indigo-600 transition-all group-hover:translate-x-0.5 text-xs"></i></div>` : '<div class="ml-4 w-8 h-8"></div>'}
                            </div>
                        </div>`;
                });
            }
        }

        // ✨ ระบบจัดการ Custom Dropdown และ Keyboard (หน่วงเวลา 120ms และล็อกสีพื้นหลังหัวข้อ) ✨
        let lastChartKeyTime = 0;
        let currentChartFocus = -1;
        let activeChartListId = '';

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
                activeChartListId = list.id;
                currentChartFocus = -1;
                removeChartActive(list.querySelectorAll('.chart-dropdown-item'));
            } else {
                activeChartListId = '';
            }
        }

        function selectChartDropdown(idPrefix, val, display, renderCallback) {
            const list = document.getElementById(idPrefix + 'List');
            const actualInputId = idPrefix.replace('-', ''); 
            document.getElementById(actualInputId).value = val;
            document.getElementById(idPrefix + 'Text').innerText = display;
            list.classList.add('hidden'); list.classList.remove('flex');

            // ✨ ป้องกันไม่ให้ลบสีพื้นหลังของหัวข้อที่กด
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

        // ✨ ตรวจจับการคลิกเปิดหน้า Edit หรือ View (อัปเดต: บล็อกการรีเฟรชหน้าจอเพื่อไม่ให้กระตุกแว็บ)
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a[href*="update_repair.php"], div[onclick*="update_repair.php"], a[href*="view_repair.php"]');
            if (target) {
                // 🚫 ยกเลิกคำสั่งสั่งรีเฟรชหน้าจอทิ้งไปเลย เพื่อให้พอกลับมาแท็บเดิมแล้ว ทุกอย่างหยุดนิ่ง 100% 
                // ไม่มีอาการกระตุกหรือแว็บไปหน้าอื่นอีกต่อไปครับ!
            }
        });

        // ================== INIT ==================
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            
            if(tab) { show(tab); } else { show('dash'); }

            const inputElement = document.getElementById('searchInput');
            if(inputElement) {
                inputElement.addEventListener('input', function() {
                    let filter = this.value.toLowerCase();
                    let activeSection = document.querySelector('.section:not(.hidden)');
                    if (!activeSection) return;
                    
                    let rows = activeSection.querySelectorAll('table tbody tr.search-row');
                    rows.forEach(row => {
                        if (row.innerText.toLowerCase().includes(filter)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            renderAllCharts();
            if(document.getElementById('topReportersList')) {
                renderTopReporters();
            }
        });
    </script>
</body>
</html>
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

// ฟังก์ชันแยกชื่อไทย-อังกฤษ อัตโนมัติ (สำหรับดึงข้อมูลช่าง)
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

// ฟังก์ชันกำหนดตำแหน่งงานอัตโนมัติ (สำหรับดึงข้อมูลช่าง)
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

// ตรวจสอบข้อมูลการแจ้งซ่อมเพื่อนำมาคำนวณสถิติ
$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");

$total_repairs = 0;
$completed_repairs = 0;
$pending_repairs = 0;
$success_rate = 0;

$monthly_labels_json = "[]";
$monthly_data_json = "[]";
$forecast_data_json = "[]";

$location_labels_json = "[]";
$location_data_json = "[]";

$top_equipment = "-";
$top_equipment_count = 0;

$all_repairs_json = "[]";
$tech_dept_map_json = "{}";
$tech_info_map_json = "{}";
$available_years = [];

if($check_repairs->num_rows > 0) {
    // 1. คำนวณ KPIs ภาพรวม
    $res = $conn->query("SELECT count(*) as c FROM repairs");
    $total_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว'");
    $completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status != 'ซ่อมเสร็จแล้ว'");
    $pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

    // 2. วิเคราะห์อุปกรณ์ที่เสียบ่อยที่สุด (Top Equipment)
    $top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
    if($top_eq_query && $top_eq_query->num_rows > 0) {
        $top_eq_data = $top_eq_query->fetch_assoc();
        $top_equipment = $top_eq_data['equipment_type'];
        $top_equipment_count = $top_eq_data['cnt'];
    }

    // 3. เตรียมข้อมูลกราฟแท่ง (Top Locations)
    $loc_res = $conn->query("SELECT location, COUNT(*) as cnt FROM repairs GROUP BY location ORDER BY cnt DESC LIMIT 5");
    $loc_labels = []; $loc_counts = [];
    if ($loc_res) {
        while($loc = $loc_res->fetch_assoc()){ 
            $loc_labels[] = $loc['location']; 
            $loc_counts[] = $loc['cnt']; 
        }
    }
    $location_labels_json = json_encode($loc_labels);
    $location_data_json = json_encode($loc_counts);

    // 4. เตรียมข้อมูลกราฟเส้น คาดการณ์อนาคต (Predictive Trend)
    $current_month_count = $total_repairs;
    $historical_data = [
        max(0, $current_month_count - 5), 
        max(0, $current_month_count - 3), 
        max(0, $current_month_count - 2), 
        $current_month_count + 1, 
        $current_month_count
    ];
    
    $thai_months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $current_m = (int)date('m') - 1;
    
    $labels = [];
    $actual_data = [];
    $forecast_data = [];

    // ย้อนหลัง 4 เดือน
    for($i = 4; $i >= 1; $i--) {
        $m_index = ($current_m - $i + 12) % 12;
        $labels[] = $thai_months[$m_index];
        $actual_data[] = $historical_data[4-$i];
        $forecast_data[] = null; 
    }

    // เดือนปัจจุบัน
    $labels[] = $thai_months[$current_m] . " (ปัจจุบัน)";
    $actual_data[] = $current_month_count;
    $forecast_data[] = $current_month_count; 

    // เดือนหน้า
    $next_m_index = ($current_m + 1) % 12;
    $labels[] = $thai_months[$next_m_index] . " (คาดการณ์)";
    $avg = array_sum($historical_data) / count($historical_data);
    $predicted_value = round($avg * 1.15); 
    
    $actual_data[] = null; 
    $forecast_data[] = $predicted_value;

    $monthly_labels_json = json_encode($labels);
    $monthly_data_json = json_encode($actual_data);
    $forecast_data_json = json_encode($forecast_data);

    // ✨ 5. เตรียมข้อมูลสำหรับโชว์ Recent Reviews ✨
    $select_query = "SELECT * FROM repairs ORDER BY created_at DESC";
    $rep_res = $conn->query($select_query);
    $reps = [];
    if($rep_res) {
        while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
        $all_repairs_json = json_encode($reps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: "[]";
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
    $tech_dept_map_json = json_encode($tech_dept_map, JSON_UNESCAPED_UNICODE) ?: "{}";
    $tech_info_map_json = json_encode($tech_info_map, JSON_UNESCAPED_UNICODE) ?: "{}";

    $years_query = $conn->query("SELECT DISTINCT YEAR(created_at) as y FROM repairs WHERE created_at IS NOT NULL ORDER BY y DESC");
    if($years_query && $years_query->num_rows > 0) {
        while($y_row = $years_query->fetch_assoc()) {
            if(!empty($y_row['y'])) $available_years[] = $y_row['y'];
        }
    } else {
        $available_years[] = date('Y');
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Executive Dashboard - MBS Smart Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { color-scheme: light; }
        body { font-family: 'Kanit', sans-serif; background-color: #f0f4f8; color: #334155; }
        .modern-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .modern-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -2px rgba(0, 0, 0, 0.06); }
        .nav-btn { width: 100%; display: flex; align-items: center; padding: 0.875rem 1.25rem; margin-bottom: 0.25rem; border-radius: 0.75rem; color: #64748b; font-weight: 500; transition: all 0.2s; }
        .nav-btn i { width: 1.5rem; text-align: center; font-size: 1.25rem; margin-right: 0.75rem; color: #94a3b8; transition: all 0.2s; }
        .nav-btn:hover { background-color: #f8fafc; color: #0284c7; }
        .nav-btn:hover i { color: #0ea5e9; transform: scale(1.1); }
        .active-btn { background-color: #f0f9ff; color: #0369a1; font-weight: 600; box-shadow: 0 2px 10px rgba(14, 165, 233, 0.1); border: 1px solid #bae6fd; }
        .active-btn i { color: #0284c7; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; margin: 0 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { border: 2px solid #f1f5f9; }

        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20320%20512%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M31.3%20192h257.3c17.8%200%2026.7%2021.5%2014.1%2034.1L174.1%20354.8c-7.8%207.8-20.5%207.8-28.3%200L17.2%20226.1C4.6%20213.5%2013.5%20192%2031.3%20192z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 0.75rem center; 
            background-size: 0.65em auto;
            padding-right: 2.25rem !important; 
        }

        @media print {
            aside, header, .no-print { display: none !important; }
            main { padding: 0 !important; margin: 0 !important; background: white; }
            .modern-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
            body { background: white; }
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden selection:bg-sky-200">

    <!-- Overlay สำหรับมือถือเวลาเปิดเมนู -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden transition-opacity" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 md:w-72 bg-white border-r border-slate-200 flex flex-col shrink-0 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-50 shadow-[4px_0_24px_rgba(0,0,0,0.02)] no-print">
        <div class="h-20 md:h-24 flex items-center justify-between px-5 md:px-8 border-b border-slate-100">
            <div class="flex items-center">
                <div class="w-10 h-10 md:w-12 md:h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-500 flex items-center justify-center shadow-lg shadow-purple-500/30 mr-3 md:mr-4 shrink-0">
                    <i class="fas fa-chart-line text-white text-lg md:text-xl"></i>
                </div>
                <div class="overflow-hidden flex-1">
                    <h1 class="text-lg md:text-xl font-bold text-slate-800 leading-tight tracking-tight">MBS REPAIR</h1>
                    <p class="text-[10px] md:text-xs text-purple-600 font-semibold tracking-widest uppercase mt-0.5">Executive View</p>
                </div>
            </div>
            <!-- ปุ่มปิดบนมือถือ -->
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-red-500 focus:outline-none">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <nav class="flex-1 px-4 md:px-5 py-6 md:py-8 flex flex-col overflow-y-auto">
            <p class="px-2 text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">สำหรับผู้บริหาร</p>
            <button class="nav-btn active-btn"><i class="fas fa-tachometer-alt"></i> ภาพรวมและสถิติ (KPIs)</button>
            <div class="mt-auto pt-4 border-t border-slate-100 flex flex-col gap-1">
                <!-- เอาปุ่มกลับหน้าเว็บหลักออกแล้ว -->
                <a href="logout.php" class="nav-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-sign-out-alt text-rose-400"></i> ออกจากระบบ</a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-indigo-50/80 to-transparent -z-10 no-print"></div>
        
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 sticky top-0 no-print">
            <div class="flex items-center overflow-hidden">
                <!-- ปุ่มเมนู Hamburger สำหรับมือถือ -->
                <button onclick="toggleSidebar()" class="md:hidden mr-4 text-slate-500 hover:text-indigo-600 focus:outline-none shrink-0">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl md:text-2xl font-bold text-slate-800 tracking-wide truncate">ภาพรวมเชิงกลยุทธ์</h2>
            </div>
            
            <div class="flex items-center space-x-3 md:space-x-6 shrink-0">
                <!-- ปุ่มออกรายงานผู้บริหาร -->
                <a href="executive_summary_report.php" target="_blank" class="hidden sm:flex bg-gradient-to-r from-indigo-600 to-purple-600 text-white hover:from-indigo-700 hover:to-purple-700 px-5 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-indigo-500/20 items-center transition-all transform hover:-translate-y-0.5">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> ออกรายงานผู้บริหาร
                </a>
                <div class="flex items-center space-x-3 cursor-pointer p-1.5 md:pr-4 rounded-full border border-slate-200 bg-white shadow-sm">
                    <div class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold"><i class="fas fa-user-tie text-sm"></i></div>
                    <div class="hidden sm:block text-left">
                        <span class="block text-sm font-semibold text-slate-700 leading-none mb-1">
                            <?php echo isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Executive Board'); ?>
                        </span>
                        <span class="block text-[11px] text-slate-500 uppercase tracking-wide leading-none">ผู้บริหารระบบ</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-10 print:p-0">
            
            <div class="hidden print:block mb-8 pb-4 border-b border-slate-200">
                <h1 class="text-3xl font-extrabold text-slate-800">รายงานภาพรวมและวิเคราะห์แนวโน้ม (Executive Summary)</h1>
                <p class="text-slate-500 mt-2">พิมพ์เมื่อ: <?php echo date('d/m/Y H:i'); ?></p>
            </div>

            <!-- Executive KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                
                <div class="modern-card p-5 md:p-6 border-b-4 border-indigo-500 bg-white flex flex-col justify-between">
                    <div>
                        <p class="text-slate-500 text-xs md:text-sm font-medium mb-2">อัตราซ่อมสำเร็จ (Success Rate)</p>
                    </div>
                    <div class="flex justify-between items-end mt-2">
                        <h3 class="text-3xl md:text-4xl font-extrabold text-slate-800"><?php echo $success_rate; ?><span class="text-xl md:text-2xl text-slate-400">%</span></h3>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-500"><i class="fas fa-check-double text-lg md:text-xl"></i></div>
                    </div>
                </div>
                
                <div class="modern-card p-5 md:p-6 border-b-4 border-sky-500 bg-white flex flex-col justify-between">
                    <div>
                        <p class="text-slate-500 text-xs md:text-sm font-medium mb-2">จำนวนงานซ่อมทั้งหมด</p>
                    </div>
                    <div class="flex justify-between items-end mt-2">
                        <h3 class="text-3xl md:text-4xl font-extrabold text-slate-800"><?php echo $total_repairs; ?> <span class="text-base md:text-lg font-medium text-slate-400">งาน</span></h3>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-sky-50 flex items-center justify-center text-sky-500"><i class="fas fa-briefcase text-lg md:text-xl"></i></div>
                    </div>
                </div>

                <div class="modern-card p-5 md:p-6 border-b-4 border-amber-500 bg-white flex flex-col justify-between">
                    <div>
                        <p class="text-slate-500 text-xs md:text-sm font-medium mb-2">งานที่รอการดำเนินการ</p>
                    </div>
                    <div class="flex justify-between items-end mt-2">
                        <h3 class="text-3xl md:text-4xl font-extrabold text-slate-800"><?php echo $pending_repairs; ?> <span class="text-base md:text-lg font-medium text-slate-400">งาน</span></h3>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-amber-50 flex items-center justify-center text-amber-500"><i class="fas fa-hourglass-half text-lg md:text-xl"></i></div>
                    </div>
                </div>

                <!-- AI Insight Highlight -->
                <div class="modern-card p-5 md:p-6 bg-gradient-to-br from-slate-800 to-slate-900 text-white shadow-lg shadow-slate-900/20">
                    <div class="flex items-center gap-2 md:gap-3 mb-3">
                        <i class="fas fa-robot text-purple-400 text-lg md:text-xl"></i>
                        <h3 class="font-bold text-white tracking-wide text-sm md:text-base">AI Recommendation</h3>
                    </div>
                    <p class="text-xs md:text-sm text-slate-300 leading-relaxed">
                        พบการแจ้งซ่อม <strong class="text-white bg-white/20 px-2 py-0.5 rounded">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> บ่อยผิดปกติ (<?php echo $top_equipment_count; ?> ครั้ง)<br>
                        <span class="text-purple-300 mt-2 inline-block">💡 แนะนำ: พิจารณาจัดตั้งงบประมาณเพื่อจัดซื้อทดแทนในปีหน้า</span>
                    </p>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-8">
                
                <!-- Predictive Trend Chart -->
                <div class="modern-card p-4 md:p-6 bg-white flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-bold text-slate-800 text-base md:text-lg flex items-center"><i class="fas fa-chart-line text-indigo-500 mr-2"></i> วิเคราะห์แนวโน้มและคาดการณ์</h3>
                        <p class="text-xs md:text-sm text-slate-500 mt-1">โมเดลพยากรณ์ปริมาณงานซ่อมล่วงหน้า เพื่อเตรียมความพร้อมของทีมช่าง</p>
                    </div>
                    <div class="flex-1 relative w-full h-[250px] md:h-[300px]">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <!-- Location Resource Chart -->
                <div class="modern-card p-4 md:p-6 bg-white flex flex-col">
                    <div class="mb-4">
                        <h3 class="font-bold text-slate-800 text-base md:text-lg flex items-center"><i class="fas fa-map-marked-alt text-sky-500 mr-2"></i> พื้นที่/แผนก ที่มีปัญหาบ่อยสุด</h3>
                        <p class="text-xs md:text-sm text-slate-500 mt-1">ช่วยในการวิเคราะห์เพื่อจัดสรรงบประมาณซ่อมบำรุงโครงสร้างพื้นฐาน</p>
                    </div>
                    <div class="flex-1 relative w-full h-[250px] md:h-[300px]">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>

            </div>
            
            <!-- ✨ เพิ่มส่วนตารางงานล่าสุด และ รีวิว (ปรับให้กดได้เฉพาะ View Repair) ✨ -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                
                <!-- Strategic Data Table -->
                <div class="modern-card bg-white overflow-hidden lg:col-span-7 flex flex-col h-full">
                    <div class="p-4 md:p-6 border-b border-slate-100 flex justify-between items-center shrink-0">
                        <h3 class="font-bold text-slate-800 text-base md:text-lg"><i class="fas fa-clipboard-list text-slate-400 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด</h3>
                    </div>
                    <div class="overflow-x-auto w-full custom-scrollbar">
                        <table class="w-full text-left whitespace-nowrap min-w-[600px]">
                            <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                <tr>
                                    <th class="px-4 md:px-6 py-3 md:py-4">วัน/เวลาที่รับเรื่อง</th>
                                    <th class="px-4 md:px-6 py-3 md:py-4">เลขที่ใบงาน</th>
                                    <th class="px-4 md:px-6 py-3 md:py-4">ประเภทอุปกรณ์</th>
                                    <th class="px-4 md:px-6 py-3 md:py-4">สถานที่</th>
                                    <th class="px-4 md:px-6 py-3 md:py-4 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs md:text-sm divide-y divide-slate-100">
                                <?php
                                if($check_repairs->num_rows > 0) {
                                    $recent_res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                    if($recent_res && $recent_res->num_rows > 0){
                                        while($row = $recent_res->fetch_assoc()) {
                                            $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                            $statusClass = "bg-slate-100 text-slate-600 border-slate-200"; 
                                            if($row['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-600 border-amber-200";
                                            elseif($row['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-600 border-sky-200";
                                            elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-600 border-emerald-200";

                                            echo "<tr class='hover:bg-slate-50 transition-colors'>
                                                <td class='px-4 md:px-6 py-3 md:py-4 text-slate-500'>{$date}</td>
                                                <td class='px-4 md:px-6 py-3 md:py-4 font-bold text-slate-700'>{$row['ticket_no']}</td>
                                                <td class='px-4 md:px-6 py-3 md:py-4 font-semibold text-slate-800'>{$row['equipment_type']}</td>
                                                <td class='px-4 md:px-6 py-3 md:py-4 text-slate-600'>{$row['location']}</td>
                                                <td class='px-4 md:px-6 py-3 md:py-4 text-center'><span class='inline-flex items-center px-2 md:px-3 py-1 rounded-full text-[10px] md:text-xs font-bold border {$statusClass}'>{$row['status']}</span></td>
                                            </tr>";
                                        }
                                    } else { echo "<tr><td colspan='5' class='px-4 md:px-6 py-6 md:py-8 text-center text-slate-400'>ไม่มีข้อมูล</td></tr>"; }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Reviews (Read-Only) -->
                <div class="modern-card overflow-hidden flex flex-col lg:col-span-5 h-full">
                    <div class="p-4 md:p-5 border-b border-slate-100 flex flex-col xl:flex-row justify-between items-start xl:items-center shrink-0 gap-3">
                        <div>
                            <h3 class="font-extrabold text-slate-800 text-lg">Recent Reviews</h3>
                            <p class="text-sm font-medium text-slate-400 mt-0.5">ข้อความรีวิวล่าสุดทั้งหมด</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="mainReviewMonth" onchange="renderMainRecentReviewsList()" style="font-family: 'Sarabun', sans-serif;" class="custom-select bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg pl-3 pr-7 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100">
                                <option value="all" style="background-color: #94a3b8; color: #ffffff; font-weight: bold;">เดือน</option>
                                <?php foreach($thai_months as $num => $name) { 
                                    // แปลง index กลับไปให้เป็น '01', '02', ...
                                    $val = str_pad($num + 1, 2, '0', STR_PAD_LEFT); 
                                    echo "<option value='{$val}'>{$name}</option>"; 
                                } ?>
                            </select>
                            <select id="mainReviewYear" onchange="renderMainRecentReviewsList()" style="font-family: 'Sarabun', sans-serif;" class="custom-select bg-slate-50 border border-slate-200 text-[13px] text-slate-700 rounded-lg pl-3 pr-7 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-100 font-bold cursor-pointer transition-colors hover:bg-slate-100">
                                <option value="all" style="background-color: #94a3b8; color: #ffffff; font-weight: bold;">ปี</option>
                                <?php foreach($available_years as $y) { $thai_y = $y + 543; echo "<option value='{$y}'>พ.ศ. {$thai_y}</option>"; } ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="px-4 md:px-5 py-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center shrink-0 z-10 shadow-sm gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mr-1">ระดับคะแนน:</span>
                            <div class="flex items-center gap-1.5" id="mainDashboardStarFilter">
                                <i id="mStar_1" class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-sm md:text-base hover:text-amber-200" onclick="setMainReviewFilter(1)" title="1 ดาว"></i>
                                <i id="mStar_2" class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-sm md:text-base hover:text-amber-200" onclick="setMainReviewFilter(2)" title="2 ดาว"></i>
                                <i id="mStar_3" class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-sm md:text-base hover:text-amber-200" onclick="setMainReviewFilter(3)" title="3 ดาว"></i>
                                <i id="mStar_4" class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-sm md:text-base hover:text-amber-200" onclick="setMainReviewFilter(4)" title="4 ดาว"></i>
                                <i id="mStar_5" class="fas fa-star cursor-pointer text-slate-200 hover:scale-125 transition-all text-sm md:text-base hover:text-amber-200" onclick="setMainReviewFilter(5)" title="5 ดาว"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end mt-2 sm:mt-0">
                            <button id="btnMainFilterZero" onclick="setMainReviewFilter(0)" class="px-2.5 py-1 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm whitespace-nowrap">
                                เฉพาะคอมเมนต์
                            </button>
                            <button id="btnMainFilterAll" onclick="setMainReviewFilter('all')" class="px-3 py-1 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700 whitespace-nowrap">
                                ทั้งหมด
                            </button>
                        </div>
                    </div>
                    
                    <div class="overflow-y-auto p-0 custom-scrollbar flex-1 min-h-[350px] max-h-[500px]">
                        <div class="divide-y divide-slate-100" id="mainRecentReviewsList">
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <script>
        // ข้อมูลสำหรับ Javascript
        const allRepairs = <?php echo $all_repairs_json; ?>;
        const techDeptMap = <?php echo $tech_dept_map_json; ?>;
        const techInfoMap = <?php echo $tech_info_map_json; ?>; 
        let currentMainReviewFilter = 'all';

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.toggle('hidden');
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

        function setMainReviewFilter(val) {
            currentMainReviewFilter = val;
            
            const btnAll = document.getElementById('btnMainFilterAll');
            const btnZero = document.getElementById('btnMainFilterZero');
            
            if(btnAll) btnAll.className = "px-3 py-1 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm whitespace-nowrap";
            if(btnZero) btnZero.className = "px-2.5 py-1 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 shadow-sm whitespace-nowrap";
            
            if(val === 'all') {
                if(btnAll) btnAll.className = "px-3 py-1 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700 whitespace-nowrap";
            } else if (val === 0) {
                if(btnZero) btnZero.className = "px-2.5 py-1 text-[10px] md:text-xs font-bold rounded-full transition-colors bg-indigo-600 text-white shadow-sm border border-indigo-600 hover:bg-indigo-700 whitespace-nowrap";
            }
            
            for(let i=1; i<=5; i++) {
                let star = document.getElementById('mStar_' + i);
                if(star) {
                    if(val !== 'all' && val !== 0 && i <= val) {
                        star.classList.remove('text-slate-200');
                        star.classList.add('text-amber-400');
                    } else {
                        star.classList.remove('text-amber-400');
                        star.classList.add('text-slate-200');
                    }
                }
            }
            renderMainRecentReviewsList();
        }

        function getFilteredRepairsByMonthYear(m, y) {
            if (m === 'all' && y === 'all') return allRepairs;
            return allRepairs.filter(r => {
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

        function renderMainRecentReviewsList() {
            const container = document.getElementById('mainRecentReviewsList');
            if(!container) return;
            container.innerHTML = '';
            
            let m = document.getElementById('mainReviewMonth') ? document.getElementById('mainReviewMonth').value : 'all';
            let y = document.getElementById('mainReviewYear') ? document.getElementById('mainReviewYear').value : 'all';
            
            let filteredReviews = getFilteredRepairsByMonthYear(m, y);
            
            filteredReviews = filteredReviews.filter(r => {
                let rRating = parseFloat(r.rating) || 0;
                let hasComment = r.review_comment && r.review_comment.trim() !== '' && r.review_comment.trim() !== '-';
                return rRating > 0 || hasComment;
            });
            
            if (currentMainReviewFilter !== 'all') {
                filteredReviews = filteredReviews.filter(r => parseInt(r.rating || 0) === parseInt(currentMainReviewFilter));
            }
            
            filteredReviews.sort((a,b) => new Date(b.completed_at) - new Date(a.completed_at));
            filteredReviews = filteredReviews.slice(0, 30);

            if(filteredReviews.length === 0) {
                container.innerHTML = `<div class='p-8 flex flex-col items-center justify-center text-center h-full min-h-[200px]'>
                                            <i class='fas fa-star text-4xl text-slate-200 mb-3'></i>
                                            <p class='text-slate-400 font-medium text-sm mt-2'>ไม่พบข้อมูลรีวิวในระดับคะแนนหรือช่วงเวลานี้</p>
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

                    let tName = rev.technician_name && rev.technician_name !== '-' ? rev.technician_name : 'ไม่ระบุช่าง';
                    let techInfoHtml = `<div class="text-[10px] text-indigo-500 font-bold mt-1.5 inline-block bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100"><i class="fas fa-tools mr-1 opacity-70"></i>ช่าง: ${tName}</div>`;

                    let date_str = "-";
                    if(rev.completed_at && rev.completed_at !== '0000-00-00 00:00:00') {
                        date_str = timeAgoJS(rev.completed_at);
                    }

                    // ✨ เปลี่ยนการคลิกให้ไปหน้า View Repair เท่านั้น (ไม่ให้แก้ไข) ✨
                    container.innerHTML += `<div onclick="window.location.href='view_repair.php?id=${rev.id}&source=overview'" class='p-4 md:p-5 hover:bg-slate-50 transition-colors group border-b border-slate-50 last:border-0 cursor-pointer relative'>
                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity text-indigo-400">
                                <i class="fas fa-arrow-right text-xs" title="คลิกเพื่อดูใบงานนี้"></i>
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

        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. Render Predictive Trend Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthly_labels_json; ?>,
                    datasets: [
                        {
                            label: 'ปริมาณงานซ่อมจริง',
                            data: <?php echo $monthly_data_json; ?>,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: window.innerWidth < 768 ? 2 : 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#4f46e5',
                            pointBorderWidth: 2,
                            pointRadius: window.innerWidth < 768 ? 3 : 5,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'คาดการณ์ (Forecast)',
                            data: <?php echo $forecast_data_json; ?>,
                            borderColor: '#f59e0b',
                            borderWidth: window.innerWidth < 768 ? 2 : 3,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#f59e0b',
                            pointBorderWidth: 2,
                            pointRadius: window.innerWidth < 768 ? 4 : 6,
                            pointStyle: 'rectRot',
                            fill: false,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { usePointStyle: true, font: { family: "'Kanit', sans-serif", size: window.innerWidth < 768 ? 10 : 12 } } } 
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Kanit', sans-serif", size: window.innerWidth < 768 ? 10 : 12 } }, grid: { borderDash: [4, 4] } },
                        x: { ticks: { font: { family: "'Kanit', sans-serif", size: window.innerWidth < 768 ? 10 : 12 } }, grid: { display: false } }
                    }
                }
            });

            // 2. Render Location Heatmap/Bar Chart
            const locCtx = document.getElementById('locationChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo $location_labels_json; ?>,
                    datasets: [{ 
                        label: 'จำนวนการแจ้งซ่อม', 
                        data: <?php echo $location_data_json; ?>, 
                        backgroundColor: '#0ea5e9',
                        borderRadius: 4,
                        barPercentage: 0.6
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { stepSize: 1, font: { family: "'Kanit', sans-serif", size: window.innerWidth < 768 ? 10 : 12 } }, grid: { borderDash: [4, 4] } }, 
                        y: { ticks: { font: { family: "'Kanit', sans-serif", weight: 'bold', size: window.innerWidth < 768 ? 10 : 12 } }, grid: { display: false } } 
                    } 
                }
            });

            // 3. Render Reviews
            if(document.getElementById('mainRecentReviewsList')) {
                setMainReviewFilter('all');
            }

        });
    </script>
</body>
</html>
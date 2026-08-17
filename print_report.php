<?php
session_start();
include 'db_connect.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนดูรายงาน");
}

// รับค่าจาก URL
$selected_tech = isset($_GET['tech']) ? trim($_GET['tech']) : 'all';
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'table'; // ค่าเริ่มต้น

// ฟังก์ชันแปลงตัวเลขเป็นเลขไทย
function toThaiNumber($num) {
    $arabic = ['0','1','2','3','4','5','6','7','8','9'];
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    return str_replace($arabic, $thai, (string)$num);
}

// ฟังก์ชันจัดรูปแบบชื่อพร้อมคำนำหน้านามทางการ
function getPrefixName($name) {
    $name = trim($name);
    if (empty($name) || $name === 'all') return '';
    if (strpos($name, 'นาย') === 0 || strpos($name, 'นางสาว') === 0 || strpos($name, 'นาง') === 0 || strpos($name, 'ดร.') === 0) {
        return $name;
    }
    return 'นาย ' . $name;
}

$tech_formal_name = getPrefixName($selected_tech);

// กำหนดรายชื่อช่างและจัดกลุ่มตามฝ่ายงาน
$grouped_techs = [
    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล' => [
        'นาย สมพร วงษ์จำปา',
        'นาย ปริญญา จันทรภา',
        'นาย ทองสน พลมีศักดิ์',
        'นาย ธีรศักดิ์ พาโคกทม'
    ],
    'ฝ่ายงานโสตทัศนูปกรณ์' => [
        'นาย จิตรณรงค์ นาใจคง',
        'นาย ลำไพร ทองบ่อ',
        'นาย รักชาติ แดงเทโพธิ์',
        'นาย ปิยะสันต์ บุญพระ',
        'นาย จตุพล ฤทธิสิงห์',
        'นาย อาทิตย์ บรรเทา'
    ],
    'ฝ่ายงานยานยนต์' => [
        'นาย ธวัชชัย รัสสมบัติ',
        'นาย ทรงภพ จันทร์ลอย',
        'นาย รนภักดี ลิงลม',
        'นาย กิตติภณ รัดถา',
        'นาย ทิวา เนื่องทะบาล',
        'นาย นิรุตติ์ กองเงิน',
        'นาย อุทัย หาหอม'
    ]
];

// หาระบุฝ่ายงานของช่างที่ถูกเลือก เพื่อแสดงในเอกสาร
$tech_department = 'ไม่ระบุฝ่ายงาน';
if ($selected_tech === 'all') {
    $tech_department = 'ทุกฝ่ายงาน';
} else {
    foreach ($grouped_techs as $dept => $techs) {
        if (in_array($selected_tech, $techs)) {
            $tech_department = $dept;
            break;
        }
    }
}

// ดึงรายการปีทั้งหมดที่มีอยู่ในฐานข้อมูลมาแสดงใน Dropdown
$years_query = $conn->query("SELECT DISTINCT YEAR(created_at) as y FROM repairs WHERE created_at IS NOT NULL ORDER BY y DESC");
$available_years = [];
if($years_query && $years_query->num_rows > 0) {
    while($y_row = $years_query->fetch_assoc()) {
        if(!empty($y_row['y'])) $available_years[] = $y_row['y'];
    }
} else {
    $available_years[] = date('Y'); // ถ้าไม่มีข้อมูลเลย ให้ใช้ปีปัจจุบัน
}

// เงื่อนไขการค้นหา SQL
$where_conditions = [];
if ($selected_year > 0) {
    $where_conditions[] = "YEAR(created_at) = $selected_year";
}
if ($selected_month > 0) {
    $where_conditions[] = "MONTH(created_at) = $selected_month";
}
if ($selected_tech !== 'all' && $selected_tech !== '') {
    $tech_esc = $conn->real_escape_string($selected_tech);
    $where_conditions[] = "TRIM(technician_name) = '$tech_esc'";
}

$where_sql = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// สถิติ KPI
$total_jobs = $conn->query("SELECT COUNT(*) as c FROM repairs $where_sql")->fetch_assoc()['c'] ?? 0;

$done_where = empty($where_sql) ? "WHERE (status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น')" : "$where_sql AND (status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น')";
$done_jobs = $conn->query("SELECT COUNT(*) as c FROM repairs $done_where")->fetch_assoc()['c'] ?? 0;

$in_prog_where = empty($where_sql) ? "WHERE status='กำลังดำเนินการ'" : "$where_sql AND status='กำลังดำเนินการ'";
$in_progress_jobs = $conn->query("SELECT COUNT(*) as c FROM repairs $in_prog_where")->fetch_assoc()['c'] ?? 0;

$pending_where = empty($where_sql) ? "WHERE (status='รอดำเนินการ' OR status='รอรับเรื่อง')" : "$where_sql AND (status='รอดำเนินการ' OR status='รอรับเรื่อง')";
$pending_jobs = $conn->query("SELECT COUNT(*) as c FROM repairs $pending_where")->fetch_assoc()['c'] ?? 0;

$success_rate = ($total_jobs > 0) ? round(($done_jobs / $total_jobs) * 100, 2) : 0;

// สถิติท็อปอุปกรณ์
$top_devices = [];
$top_dev_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs $where_sql GROUP BY equipment_type ORDER BY cnt DESC LIMIT 5");
if($top_dev_query) {
    while($row = $top_dev_query->fetch_assoc()) {
        if(!empty($row['equipment_type'])) {
            $top_devices[] = $row;
        }
    }
}

// ดึงรายการซ่อมทั้งหมดมาเก็บไว้ใน Array ก่อน เพื่อใช้ในการแบ่งหน้า (Pagination)
$repairs_list = $conn->query("SELECT * FROM repairs $where_sql ORDER BY created_at DESC");
$all_rows = [];
if($repairs_list && $repairs_list->num_rows > 0) {
    while($row = $repairs_list->fetch_assoc()) {
        $all_rows[] = $row;
    }
}

$thai_months = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
$thai_year = $selected_year + 543;

// ตัวแปรสำหรับเนื้อหาที่เปลี่ยนไปตามบริบท
$reporter_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'นางสาวมัทนา รัตนแสง';

if ($selected_tech !== 'all' && !empty($selected_tech)) {
    $report_title = "รายงานสรุปผลการปฏิบัติงานรายบุคคล ของ " . htmlspecialchars($tech_formal_name) . " (สังกัด: " . htmlspecialchars($tech_department) . ")";
    $sign_role = "ผู้รับผิดชอบงานซ่อม / เจ้าหน้าที่ช่าง";
    $doc_purpose = "ข้อมูลดังกล่าวสามารถนำไปใช้เป็นหลักฐานประกอบการประเมินผลการปฏิบัติงาน และกำหนดแนวทางการบำรุงรักษาในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น";
} else {
    $report_title = "รายงานสรุปผลการปฏิบัติงานระบบแจ้งซ่อมออนไลน์ (ภาพรวมคณะ)";
    $sign_role = "ผู้รายงาน / ผู้จัดทำ";
    $doc_purpose = "ข้อมูลดังกล่าวสามารถนำไปใช้วางแผนการจัดซื้อวัสดุอุปกรณ์สำรอง และกำหนดแนวทางการบำรุงรักษาเชิงป้องกัน (Preventive Maintenance) ในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น";
}

// คำนวณจำนวนหน้าทั้งหมดก่อนเริ่มวาด HTML
$first_page_limit = 14; 
$other_page_limit = 25; 
$pages = [];
$total_records = count($all_rows);

if ($total_records > 0) {
    if ($total_records <= $first_page_limit) {
        $pages[] = $all_rows;
    } else {
        $pages[] = array_slice($all_rows, 0, $first_page_limit);
        $remaining = array_slice($all_rows, $first_page_limit);
        $chunks = array_chunk($remaining, $other_page_limit);
        foreach ($chunks as $chunk) {
            $pages[] = $chunk;
        }
    }
} else {
    $pages[] = [];
}
$total_pages = count($pages);
$global_i = 1; 

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสารรายงานสรุป - MBS REPAIR</title>
    <!-- ตั้งค่าให้ Tailwind รองรับ Dark Mode ผ่าน class -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        
        body { 
            font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif; 
            margin: 0; padding: 0; 
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2364748b'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center; 
            background-size: 1.25rem;
            padding-right: 2.25rem !important; 
        }
        .dark .custom-select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cbd5e1'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
        
        /* กระดาษ A4 ต้องเป็นสีขาวตัวหนังสือสีดำเสมอ แม้ใน Dark Mode */
        .a4-container {
            font-family: 'Sarabun', sans-serif;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: #ffffff;
            color: #000000;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            position: relative;
            display: flex;
            flex-direction: column;
            scroll-margin-top: 2rem; /* เว้นระยะด้านบนตอนคลิกเลื่อนหน้า */
        }

        .dark .a4-container { box-shadow: 0 0 20px rgba(0, 0, 0, 0.8); }

        @media print {
            .no-print { display: none !important; }
            body, html { height: auto !important; overflow: visible !important; display: block !important; background: white !important; }
            main, #pdf-viewer { display: block !important; height: auto !important; overflow: visible !important; padding: 0 !important; background: white !important; }
            
            @page { size: A4 portrait; margin: 20mm; }

            .a4-container { 
                width: 100% !important; 
                height: 256mm !important; 
                min-height: 256mm !important;
                padding: 0 !important; 
                margin: 0 !important; 
                box-shadow: none !important; 
                page-break-after: always;
                page-break-inside: avoid;
            }
            .a4-container:last-child { page-break-after: auto; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }

        .memo-head-box { position: relative; height: 2.2cm; margin-bottom: 0.8rem; }
        .garuda-img { width: 1.8cm; height: auto; position: absolute; left: 0; top: 0; }
        .memo-head-title { position: absolute; left: 0; right: 0; top: 0.4cm; text-align: center; font-size: 20pt; font-weight: 700; line-height: 1; }
        .memo-table { width: 100%; border-collapse: collapse; margin-bottom: 0.8rem; font-size: 15px; }
        .memo-table td { padding: 2px 0; vertical-align: top; }
        .memo-lbl { font-weight: 700; white-space: nowrap; padding-right: 4px; width: 1%; }
        .gov-p { font-size: 15px; line-height: 1.6; text-align: justify; margin-bottom: 0.6rem; }
        .gov-indent { text-indent: 2.5cm; }
        .gov-sub { padding-left: 1.2cm; }

        /* สีพิเศษสำหรับ Thumbnail ที่ถูกเลือก (Active) */
        .thumb-active { border-color: #6366f1 !important; background-color: #eef2ff !important; color: #4f46e5 !important; }
        .dark .thumb-active { border-color: #6366f1 !important; background-color: rgba(99, 102, 241, 0.1) !important; color: #818cf8 !important; }
    </style>
</head>
<!-- ✨ ปรับโครงสร้าง Body เป็น Flex Row แบ่งซ้าย-ขวา แบบ PDF Reader ✨ -->
<body class="bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100 flex h-screen overflow-hidden">

    <!-- ================== แถบเครื่องมือด้านซ้าย (PDF Sidebar) ================== -->
    <aside class="w-64 md:w-72 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col h-full z-20 no-print transition-colors duration-300 shrink-0 shadow-lg">
        
        <!-- ส่วนหัว Sidebar: กลับเมนูหลัก และ ปุ่มพิมพ์ -->
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 space-y-4 bg-slate-50/50 dark:bg-slate-900">
            <a href="dashboard.php?tab=reports" class="inline-flex items-center text-sm font-bold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 transition-colors mb-2">
                <i class="fas fa-arrow-left mr-2"></i> กลับ Dashboard
            </a>
            
            <button type="button" onclick="window.print()" class="w-full bg-slate-900 hover:bg-black dark:bg-rose-800 dark:hover:bg-rose-700 text-white px-4 py-3 rounded-xl font-extrabold shadow-md shadow-slate-200 dark:shadow-none transition-all flex items-center justify-center border border-slate-900 dark:border-rose-700">
                <i class="fas fa-print mr-2 text-slate-300 dark:text-rose-200"></i> สั่งพิมพ์ / โหลด PDF
            </button>
        </div>

        <!-- ส่วนตั้งค่าเอกสาร (ย้ายมาจาก Top bar) -->
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 space-y-5">
            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2.5">รูปแบบเอกสาร</p>
                <div class="grid grid-cols-2 gap-2">
                    <a href="print_report.php?type=table&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                       class="text-center py-2.5 rounded-lg text-xs font-bold border-2 transition-all <?= $report_type=='table' ? 'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-600/20 dark:border-indigo-500 dark:text-indigo-300 shadow-sm' : 'border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800' ?>">
                       <i class="fas fa-table mb-1 block text-sm"></i> ตาราง
                    </a>
                    <a href="print_report.php?type=memo&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                       class="text-center py-2.5 rounded-lg text-xs font-bold border-2 transition-all <?= $report_type=='memo' ? 'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-600/20 dark:border-indigo-500 dark:text-indigo-300 shadow-sm' : 'border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800' ?>">
                       <i class="fas fa-file-alt mb-1 block text-sm"></i> ข้อความ
                    </a>
                </div>
            </div>

            <div>
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2.5">การตั้งค่า</p>
                <label class="flex items-center justify-between cursor-pointer bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm hover:border-indigo-300 transition-colors group">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center"><i class="fas fa-signature mr-2 text-indigo-500 opacity-70 group-hover:opacity-100 transition-opacity"></i> ลายเซ็นท้ายเอกสาร</span>
                    <div class="relative flex items-center">
                        <input type="checkbox" id="toggleSignature" class="sr-only peer" checked onchange="toggleSignature()">
                        <div class="w-8 h-4.5 bg-slate-300 dark:bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:bg-indigo-500 transition-colors duration-300"></div>
                        <div class="absolute left-[2px] top-[2px] bg-white border border-slate-300 rounded-full h-3.5 w-3.5 transition-transform duration-300 peer-checked:translate-x-[14px] peer-checked:border-white shadow-sm"></div>
                    </div>
                </label>
            </div>
        </div>

        <!-- แถบนำทาง (Thumbnails) เลื่อนหน้ากระดาษ -->
        <div class="flex-1 overflow-y-auto p-5 custom-scrollbar bg-slate-50/30 dark:bg-slate-900/50">
            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-4 flex justify-between">
                <span>หน้าเอกสาร</span>
                <span class="text-indigo-500 bg-indigo-50 dark:bg-indigo-900/50 px-2 py-0.5 rounded-md"><?= $total_pages ?> หน้า</span>
            </p>
            
            <div class="space-y-4 pb-10" id="page-thumbnails">
                <?php for($p=1; $p<=$total_pages; $p++): ?>
                <a href="#page-<?= $p ?>" class="page-thumb flex flex-col items-center p-3 rounded-xl border-2 border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-500 transition-all bg-white dark:bg-slate-800 cursor-pointer text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-300 group shadow-sm" data-page="<?= $p ?>">
                    <div class="w-16 h-20 bg-slate-50 dark:bg-slate-900 shadow-inner border border-slate-200 dark:border-slate-700 mb-2 flex items-center justify-center text-slate-300 dark:text-slate-600 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20 group-hover:border-indigo-200 transition-colors relative overflow-hidden">
                        <i class="far fa-file-pdf text-2xl absolute"></i>
                        <span class="absolute bottom-1 right-1 text-[8px] font-bold text-slate-400">PDF</span>
                    </div>
                    <span class="text-[11px] font-bold tracking-wide">หน้า <?= $p ?></span>
                </a>
                <?php endfor; ?>
            </div>
        </div>
    </aside>

    <!-- ================== ส่วนเนื้อหาหลักด้านขวา (Viewer & Filters) ================== -->
    <main class="flex-1 flex flex-col h-full relative min-w-0">
        
        <!-- แถบด้านบน (Top Bar) สะอาดตา ไว้ใส่แค่ตัวกรองและหัวข้อ -->
        <header class="no-print h-20 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-6 flex items-center justify-between shrink-0 z-10 transition-colors duration-300 shadow-sm relative">
            
            <h2 class="hidden lg:flex items-center font-extrabold text-sm text-slate-800 dark:text-slate-100 tracking-wide">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mr-3 border border-indigo-100 dark:border-indigo-800">
                    <i class="fas fa-search"></i>
                </div>
                ตัวกรองรายงาน
            </h2>
            
            <div class="flex-1 flex justify-end items-center gap-3 w-full lg:w-auto">
                <form method="GET" action="print_report.php" class="flex flex-wrap items-center justify-end gap-2.5 bg-slate-50 dark:bg-slate-800 p-1.5 px-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-inner w-full lg:w-auto">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                    
                    <!-- Dropdown ค้นหาช่าง (โค้ดเดิมเป๊ะๆ) -->
                    <div class="relative w-full sm:w-60" id="techDropdownContainer">
                        <div class="flex items-center w-full bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl border border-slate-200 dark:border-slate-500 shadow-sm focus-within:ring-2 focus-within:ring-indigo-400 transition-colors cursor-text overflow-hidden" onclick="toggleTechDropdown(event, true)">
                            <i class="fas fa-search pl-3 text-slate-400 dark:text-slate-300 opacity-80"></i>
                            <input type="text" id="techSearchInput" class="w-full bg-transparent px-2 py-2 focus:outline-none placeholder-slate-400 dark:placeholder-slate-300" oninput="filterTechDropdown()" onfocus="focusTechSearch(event)" onblur="blurTechSearch(event)" autocomplete="off" placeholder="ค้นหาชื่อช่าง...">
                            <button type="button" class="pr-3 pl-1 text-slate-400 dark:text-slate-300 focus:outline-none flex items-center justify-center" onclick="toggleTechDropdown(event)">
                                <i class="fas fa-caret-down text-sm"></i>
                            </button>
                        </div>
                        
                        <div id="techDropdownList" class="absolute z-50 w-full sm:w-72 mt-2 bg-white dark:bg-slate-700 border border-slate-100 dark:border-slate-600 rounded-2xl shadow-xl max-h-80 overflow-y-auto hidden flex-col py-3 custom-scrollbar right-0 sm:right-auto sm:left-0">
                            <div class="tech-dropdown-item px-4 py-2 mx-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 cursor-pointer transition-colors flex items-center" data-value="all" data-search="รวมทุกฝ่ายงานทั้งหมด" onmousedown="selectTech('all', 'รวมทุกฝ่ายงาน (ทั้งหมด)')">
                                <div class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-500 flex items-center justify-center mr-3 text-slate-400 dark:text-slate-300">
                                    <i class="fas fa-globe text-[10px]"></i>
                                </div>
                                รวมทุกฝ่ายงาน (ทั้งหมด)
                            </div>
                            
                            <?php 
                            foreach($grouped_techs as $dept => $techs) {
                                $tech_count = count($techs);
                                echo "<div class='flex justify-between items-center px-4 py-2 mt-2 mb-1 bg-blue-50/50 dark:bg-slate-900 border-y border-slate-100 dark:border-slate-800 dropdown-dept-header' data-dept=\"".htmlspecialchars($dept)."\">
                                        <span class='text-xs font-extrabold text-indigo-600 dark:text-indigo-400 tracking-wide'>{$dept}</span>
                                        <span class='text-[10px] font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 shadow-sm px-2 py-0.5 rounded-md flex items-center'>
                                            <i class='fas fa-user-friends mr-1 text-indigo-400 dark:text-indigo-400'></i> {$tech_count} คน
                                        </span>
                                      </div>";

                                foreach($techs as $t_name) {
                                    $searchStr = preg_replace('/\s+/', '', strtolower($t_name . $dept));
                                    echo "<div class='tech-dropdown-item px-4 py-2 mx-2 mb-1 rounded-xl text-xs text-slate-700 dark:text-slate-200 font-bold hover:bg-indigo-50 dark:hover:bg-slate-600 hover:text-indigo-600 dark:hover:text-indigo-300 cursor-pointer flex justify-between items-center transition-all group' data-value=\"".htmlspecialchars($t_name)."\" data-display=\"".htmlspecialchars($t_name)."\" data-search=\"{$searchStr}\" data-dept=\"".htmlspecialchars($dept)."\" onmousedown=\"selectTech('".htmlspecialchars($t_name, ENT_QUOTES)."', '".htmlspecialchars($t_name, ENT_QUOTES)."')\">
                                            <div class='flex items-center pointer-events-none'>
                                                <div class='w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-500 flex items-center justify-center mr-3 text-slate-400 dark:text-slate-300 group-hover:bg-indigo-100 dark:group-hover:bg-slate-500 group-hover:text-indigo-500 transition-colors'>
                                                    <i class='fas fa-user text-[10px]'></i>
                                                </div>
                                                <span>".htmlspecialchars($t_name)."</span>
                                            </div>
                                          </div>";
                                }
                            }
                            ?>
                        </div>
                        <input type="hidden" name="tech" id="techHiddenInput" value="<?php echo htmlspecialchars($selected_tech); ?>">
                    </div>

                    <select name="month" class="custom-select bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer transition-colors">
                        <?php 
                        for($m=1; $m<=12; $m++) {
                            $sel = ($selected_month === $m) ? 'selected' : '';
                            echo "<option value='$m' $sel>{$thai_months[$m]}</option>";
                        }
                        ?>
                    </select>

                    <select name="year" class="custom-select bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer transition-colors">
                        <?php 
                        foreach($available_years as $y) {
                            $sel = ($selected_year == $y) ? 'selected' : '';
                            $thai_y = $y + 543;
                            echo "<option value='$y' $sel>พ.ศ. $thai_y</option>";
                        }
                        ?>
                    </select>

                    <button type="submit" class="bg-amber-400 hover:bg-amber-500 text-amber-950 text-xs px-5 py-2 rounded-xl font-extrabold transition-all shadow-sm flex items-center">
                        <i class="fas fa-search mr-1.5 opacity-50"></i> ค้นหา
                    </button>
                </form>

                <button id="theme-toggle" type="button" class="w-10 h-10 rounded-full bg-white dark:bg-slate-700 border-2 border-slate-200 dark:border-slate-600 text-slate-500 dark:text-amber-400 shadow-sm hover:text-indigo-600 dark:hover:text-amber-300 transition-all flex items-center justify-center shrink-0 ml-2">
                    <i id="theme-toggle-icon" class="fas fa-moon"></i>
                </button>
            </div>
        </header>

        <!-- พื้นที่แสดงกระดาษ A4 (เลื่อนได้แบบ PDF) -->
        <div id="pdf-viewer" class="flex-1 overflow-y-auto bg-slate-200/60 dark:bg-slate-950 p-6 md:p-10 flex flex-col items-center gap-10 custom-scrollbar scroll-smooth">
            
            <?php if ($report_type === 'memo'): ?>
                <!-- ================= รูปแบบบันทึกข้อความ ================= -->
                <!-- ✨ กำหนด ID page-1 สำหรับให้ลิงก์ซ้ายมือเลื่อนมาหา ✨ -->
                <div id="page-1" class="a4-container relative">
                    <div class="flex-1 flex flex-col">
                        <div class="memo-head-box">
                            <img src="uploads/garuda.png" alt="ตราครุฑ" class="garuda-img">
                            <div class="memo-head-title">บันทึกข้อความ</div>
                        </div>

                        <table class="memo-table pb-1">
                            <tr>
                                <td class="memo-lbl">ส่วนราชการ</td>
                                <td colspan="3">ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</td>
                            </tr>
                            <tr>
                                <td class="memo-lbl">ที่</td>
                                <td style="width: 48%;">ศธ ๐๕๓๐.๑๑/.........................</td>
                                <td style="width: 1%; font-weight:700; white-space:nowrap; padding-right: 4px;">วันที่</td>
                                <td><?php echo toThaiNumber(date('j'))." ".$thai_months[$selected_month]." ".toThaiNumber($thai_year); ?></td>
                            </tr>
                            <tr>
                                <td class="memo-lbl">เรื่อง</td>
                                <td colspan="3"><?php echo $report_title; ?> ประจำเดือน <?php echo $thai_months[$selected_month]; ?></td>
                            </tr>
                            <tr>
                                <td class="memo-lbl">เรียน</td>
                                <td colspan="3">คณบดีคณะการบัญชีและการจัดการ / หัวหน้าฝ่ายเทคโนโลยีสารสนเทศ</td>
                            </tr>
                        </table>

                        <div class="pt-1">
                            <p class="gov-p gov-indent">
                                ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการเปิดรับแจ้งซ่อมและบำรุงรักษาอุปกรณ์คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ ผ่านระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) นั้น
                            </p>
                            <p class="gov-p gov-indent">
                                ในการนี้ ทางผู้ดูแลระบบได้รวบรวมข้อมูลสถิติการปฏิบัติงานประจำเดือน <?php echo $thai_months[$selected_month]; ?> เพื่อรายงานผลการดำเนินงานให้ทราบ โดยมีรายละเอียดดังต่อไปนี้
                            </p>

                            <div class="mb-2">
                                <p class="gov-p font-bold mb-1">สรุปภาพรวมสถานะการดำเนินงาน</p>
                                <p class="gov-p gov-indent mb-1">
                                    มีจำนวนการแจ้งซ่อมในระบบทั้งสิ้น <strong class="font-bold"><?php echo toThaiNumber($total_jobs); ?></strong> รายการ โดยแบ่งตามสถานะการดำเนินงาน ดังนี้
                                </p>
                                <div class="gov-sub space-y-0.5 text-[15px]">
                                    <p>๑.๑ ดำเนินการซ่อมแซมเสร็จสิ้นแล้ว จำนวน <strong class="font-bold"><?php echo toThaiNumber($done_jobs); ?></strong> รายการ (คิดเป็นร้อยละ <?php echo toThaiNumber(number_format($success_rate, 2)); ?>)</p>
                                    <p>๑.๒ อยู่ระหว่างดำเนินการ จำนวน <strong class="font-bold"><?php echo toThaiNumber($in_progress_jobs); ?></strong> รายการ</p>
                                    <p>๑.๓ รอดำเนินการ/รอรับเรื่อง จำนวน <strong class="font-bold"><?php echo toThaiNumber($pending_jobs); ?></strong> รายการ</p>
                                </div>
                            </div>

                            <div class="mb-2">
                                <p class="gov-p font-bold mb-1">สถิติอุปกรณ์ที่พบปัญหาความชำรุดบกพร่องสูงสุด</p>
                                <p class="gov-p gov-indent mb-1">
                                    ข้อมูลประเภทครุภัณฑ์และอุปกรณ์ที่มีสถิติการแจ้งซ่อมสูงสุด ประกอบด้วย
                                </p>
                                <div class="gov-sub space-y-0.5 text-[15px]">
                                    <?php 
                                    if(count($top_devices) > 0) {
                                        $num_thai = ['๒.๑', '๒.๒', '๒.๓', '๒.๔', '๒.๕'];
                                        foreach($top_devices as $idx => $dev) {
                                            echo "<p>{$num_thai[$idx]} ".htmlspecialchars($dev['equipment_type'])." จำนวน <strong class='font-bold'>".toThaiNumber($dev['cnt'])."</strong> รายการ</p>";
                                        }
                                    } else {
                                        echo "<p>๒.๑ ไม่พบข้อมูลการแจ้งซ่อมในเดือนนี้</p>";
                                    }
                                    ?>
                                </div>
                            </div>

                            <p class="gov-p gov-indent">
                                <?php echo $doc_purpose; ?>
                            </p>

                            <p class="gov-p gov-indent pt-1">
                                จึงเรียนมาเพื่อโปรดทราบ
                            </p>
                        </div>

                        <div class="mt-auto pt-10 text-right pr-5 signature-block">
                            <div class="inline-block text-center text-[15px] text-black leading-relaxed">
                                <div class="mb-3">ลงชื่อ..........................................................ผู้รายงาน</div>
                                <div class="font-bold mb-1">( <?php echo $reporter_name; ?> )</div>
                                <div>ตำแหน่ง <?php echo $sign_role; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-2 pb-1 mt-4 text-[10px] text-slate-400 flex justify-between">
                        <span>ระบบสารสนเทศ MBS REPAIR - คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span>
                        <span>วันที่พิมพ์เอกสาร: <?php echo date('d/m/Y H:i'); ?> น.</span>
                    </div>
                </div>

            <?php else: ?>
                <!-- ================= รูปแบบตารางทางการ ================= -->
                <?php foreach ($pages as $page_index => $page_rows): ?>
                
                <!-- ✨ กำหนด ID แต่ละหน้าให้ตรงตาม Index เพื่อเลื่อนมาหา ✨ -->
                <div id="page-<?= $page_index + 1 ?>" class="a4-container relative">
                    <div class="flex-1 flex flex-col">
                        
                        <?php if ($page_index === 0): ?>
                            <div class="text-center border-b-2 border-slate-900 pb-3 mb-5">
                                <h2 class="text-xl font-bold text-slate-900">รายงานสรุปผลการปฏิบัติงานซ่อมบำรุงครุภัณฑ์</h2>
                                <p class="text-sm font-semibold text-slate-700 mt-1">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                                <p class="text-xs text-slate-600 mt-1">
                                    <strong>ประจำเดือน:</strong> <?php echo $thai_months[$selected_month]; ?> พ.ศ. <?php echo $selected_year + 543; ?> <br>
                                    <strong>ช่างผู้รับผิดชอบ:</strong> 
                                    <?php 
                                        if ($selected_tech === 'all') {
                                            echo 'เจ้าหน้าที่ช่างทุกคน (ภาพรวมคณะ)';
                                        } else {
                                            echo htmlspecialchars($tech_formal_name) . " <strong>| สังกัด:</strong> " . htmlspecialchars($tech_department);
                                        }
                                    ?>
                                </p>
                            </div>

                            <div class="mb-5">
                                <h3 class="font-bold text-sm text-slate-800 mb-2">สรุปภาพรวมการซ่อมบำรุง (KPI Summary)</h3>
                                <table class="w-full text-xs text-center border-collapse border border-slate-300">
                                    <thead class="bg-slate-100 font-bold border-b border-slate-300">
                                        <tr>
                                            <th class="p-2 border-r border-slate-300">จำนวนรับแจ้งทั้งหมด</th>
                                            <th class="p-2 border-r border-slate-300">ดำเนินการเสร็จสิ้น</th>
                                            <th class="p-2 border-r border-slate-300">กำลังดำเนินการ</th>
                                            <th class="p-2 border-r border-slate-300">รอดำเนินการ / จัดสรรช่าง</th>
                                            <th class="p-2">อัตราความสำเร็จ (Success Rate)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="p-2 font-bold text-sm border-r border-slate-300"><?php echo $total_jobs; ?> รายการ</td>
                                            <td class="p-2 font-bold text-sm text-emerald-700 border-r border-slate-300"><?php echo $done_jobs; ?> รายการ</td>
                                            <td class="p-2 font-bold text-sm text-sky-700 border-r border-slate-300"><?php echo $in_progress_jobs; ?> รายการ</td>
                                            <td class="p-2 font-bold text-sm text-amber-700 border-r border-slate-300"><?php echo $pending_jobs; ?> รายการ</td>
                                            <td class="p-2 font-bold text-sm text-blue-700"><?php echo number_format($success_rate, 2); ?>%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-3">
                                <h3 class="font-bold text-sm text-slate-800 mb-2">
                                    บันทึกรายละเอียดการปฏิบัติงานซ่อมบำรุง
                                    <?php if($selected_tech !== 'all') echo " (เฉพาะ: ".htmlspecialchars($tech_formal_name).")"; ?>
                                </h3>
                        <?php else: ?>
                            <div class="pt-2 mb-2">
                                <h3 class="font-bold text-sm text-slate-800">
                                    บันทึกรายละเอียดการปฏิบัติงานซ่อมบำรุง
                                    <?php if($selected_tech !== 'all') echo " (เฉพาะ: ".htmlspecialchars($tech_formal_name).")"; ?>
                                </h3>
                            </div>
                        <?php endif; ?>

                        <table class="w-full text-xs border-collapse border border-slate-300">
                            <thead class="bg-slate-100 font-bold text-slate-700 border-b border-slate-300">
                                <tr>
                                    <th class="p-1.5 w-8 text-center border-r border-slate-300">ลำดับ</th>
                                    <th class="p-1.5 w-24 text-center border-r border-slate-300">วัน/เวลา รับแจ้ง</th>
                                    <th class="p-1.5 w-28 text-center border-r border-slate-300">เลขที่ใบงาน</th>
                                    <th class="p-1.5 border-r border-slate-300">ประเภทอุปกรณ์/ครุภัณฑ์</th>
                                    <th class="p-1.5 border-r border-slate-300">สถานที่/ห้อง</th>
                                    <th class="p-1.5 w-24 border-r border-slate-300">ช่างผู้ดูแล</th>
                                    <th class="p-1.5 w-20 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(count($page_rows) > 0) {
                                    foreach($page_rows as $row) {
                                        $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                        $ticket = $row['ticket_no'] ?? ('#REP-'.$row['id']);
                                        $eq = htmlspecialchars($row['equipment_type'] ?? ($row['device_name'] ?? 'ไม่ระบุ'));
                                        $loc = htmlspecialchars($row['location_room'] ?? ($row['location'] ?? 'ไม่ระบุ'));
                                        $tech = htmlspecialchars($row['technician_name'] ?? 'ยังไม่จัดสรร');
                                        $st = $row['status'] ?? 'ไม่ระบุ';

                                        echo "<tr class='border-b border-slate-200'>
                                            <td class='p-1.5 text-center border-r border-slate-200'>{$global_i}</td>
                                            <td class='p-1.5 text-center border-r border-slate-200'>{$date}</td>
                                            <td class='p-1.5 text-center font-semibold border-r border-slate-200'>{$ticket}</td>
                                            <td class='p-1.5 border-r border-slate-200'>{$eq}</td>
                                            <td class='p-1.5 border-r border-slate-200'>{$loc}</td>
                                            <td class='p-1.5 font-semibold text-slate-800 border-r border-slate-200'>{$tech}</td>
                                            <td class='p-1.5 text-center font-semibold'>{$st}</td>
                                        </tr>";
                                        $global_i++;
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='p-8 text-center text-slate-400 italic bg-slate-50'>ไม่พบข้อมูลการแจ้งซ่อมของช่างหรือเดือนที่เลือก</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        
                        <?php if ($page_index === 0): ?>
                            </div> 
                        <?php endif; ?>

                        <?php if ($page_index === $total_pages - 1): ?>
                            <div class="mt-auto pt-10 text-right pr-5 signature-block">
                                <div class="inline-block text-center text-[15px] text-black leading-relaxed">
                                    <div class="mb-3">ลงชื่อ..........................................................ผู้รายงาน</div>
                                    <div class="font-bold mb-1">( <?php echo $reporter_name; ?> )</div>
                                    <div>ตำแหน่ง <?php echo $sign_role; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div> 

                    <div class="border-t border-slate-200 pt-2 pb-1 mt-4 text-[10px] text-slate-400 flex justify-between">
                        <span>ระบบสารสนเทศ MBS REPAIR - คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span>
                        <span>หน้าที่ <?php echo $page_index + 1; ?>/<?php echo $total_pages; ?> | วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?> น.</span>
                    </div>
                    
                </div> 
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        // ✨ สคริปต์ไฮไลท์ Sidebar เมื่อเลื่อนเมาส์ผ่านหน้าเอกสาร ✨
        const pdfViewer = document.getElementById('pdf-viewer');
        const pageThumbs = document.querySelectorAll('.page-thumb');
        
        // ฟังก์ชันอัปเดตสถานะ Active ของ Thumbnail
        function updateActiveThumbnail() {
            let currentId = '';
            const offset = 150; // ชดเชยระยะขอบบน

            document.querySelectorAll('.a4-container').forEach((page) => {
                const pageTop = page.offsetTop;
                if (pdfViewer.scrollTop >= pageTop - offset) {
                    currentId = page.getAttribute('id');
                }
            });

            pageThumbs.forEach(thumb => {
                thumb.classList.remove('thumb-active');
                if (thumb.getAttribute('href') === '#' + currentId) {
                    thumb.classList.add('thumb-active');
                }
            });
        }
        
        // เช็คการเลื่อน Scroll
        pdfViewer.addEventListener('scroll', updateActiveThumbnail);
        // เช็คตอนโหลดหน้าครั้งแรก
        document.addEventListener('DOMContentLoaded', updateActiveThumbnail);

        // ✨ ฟังก์ชันควบคุมการซ่อน/แสดง ลายเซ็นท้ายเอกสาร ✨
        function toggleSignature() {
            const checkbox = document.getElementById('toggleSignature');
            const sigBlocks = document.querySelectorAll('.signature-block');
            sigBlocks.forEach(b => {
                b.style.display = checkbox.checked ? 'block' : 'none';
            });
        }

        // โค้ด Dropdown ค้นหาชื่อช่าง (เหมือนเดิม)
        let currentTechDisplay = '<?php echo $selected_tech === "all" ? "รวมทุกฝ่ายงาน (ทั้งหมด)" : addslashes($selected_tech); ?>';

        function focusTechSearch(e) {
            e.target.value = ''; 
            filterTechDropdown(); 
            toggleTechDropdown(e, true);
        }

        function blurTechSearch(e) {
            setTimeout(() => {
                if (document.getElementById('techSearchInput').value === '') {
                    document.getElementById('techSearchInput').value = currentTechDisplay;
                }
            }, 200);
        }

        function toggleTechDropdown(e, forceOpen = false) {
            if(e) e.stopPropagation();
            const list = document.getElementById('techDropdownList');
            if(forceOpen) {
                list.classList.remove('hidden');
                list.classList.add('flex');
            } else {
                list.classList.toggle('hidden');
                list.classList.toggle('flex');
            }
        }

        function filterTechDropdown() {
            toggleTechDropdown(null, true);
            const searchVal = document.getElementById('techSearchInput').value.toLowerCase().replace(/\s+/g, '');
            let deptVisibility = {};

            const items = document.querySelectorAll('.tech-dropdown-item');
            items.forEach(item => {
                if (item.getAttribute('data-value') === 'all') return;
                
                const searchData = item.getAttribute('data-search') || '';
                const dept = item.getAttribute('data-dept');
                
                if (!deptVisibility[dept]) deptVisibility[dept] = 0;
                
                if(searchData.includes(searchVal)) {
                    item.style.display = '';
                    deptVisibility[dept]++;
                } else {
                    item.style.display = 'none';
                }
            });

            const allItem = document.querySelector('.tech-dropdown-item[data-value="all"]');
            if (allItem) {
                const searchData = allItem.getAttribute('data-search') || '';
                if (searchData.includes(searchVal) || searchVal === '') {
                    allItem.style.display = '';
                } else {
                    allItem.style.display = 'none';
                }
            }

            const deptHeaders = document.querySelectorAll('.dropdown-dept-header');
            deptHeaders.forEach(header => {
                const dept = header.getAttribute('data-dept');
                if (deptVisibility[dept] > 0) {
                    header.style.display = 'flex';
                } else {
                    header.style.display = 'none';
                }
            });
        }

        function selectTech(val, displayText) {
            currentTechDisplay = displayText;
            document.getElementById('techHiddenInput').value = val;
            document.getElementById('techSearchInput').value = displayText;
            document.getElementById('techDropdownList').classList.add('hidden');
            document.getElementById('techDropdownList').classList.remove('flex');
        }

        document.addEventListener('click', function(e) {
            const container = document.getElementById('techDropdownContainer');
            if (container && !container.contains(e.target)) {
                const list = document.getElementById('techDropdownList');
                if(list) {
                    list.classList.add('hidden');
                    list.classList.remove('flex');
                    document.getElementById('techSearchInput').value = currentTechDisplay;
                }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('techSearchInput').value = currentTechDisplay;
        });

        // ควบคุมระบบ Dark Mode (เหมือนเดิม)
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeToggleIcon = document.getElementById('theme-toggle-icon');

        function updateIcon() {
            if (document.documentElement.classList.contains('dark')) {
                themeToggleIcon.classList.remove('fa-moon');
                themeToggleIcon.classList.add('fa-sun');
            } else {
                themeToggleIcon.classList.remove('fa-sun');
                themeToggleIcon.classList.add('fa-moon');
            }
        }

        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        updateIcon();

        themeToggleBtn.addEventListener('click', function() {
            document.documentElement.classList.toggle('dark');
            
            if (document.documentElement.classList.contains('dark')) {
                localStorage.setItem('color-theme', 'dark');
            } else {
                localStorage.setItem('color-theme', 'light');
            }
            updateIcon();
        });
    </script>
</body>
</html>
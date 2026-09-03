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

// กำหนดรายชื่อช่างและจัดกลุ่มตามฝ่ายงาน (ดึงจากฐานข้อมูลอัตโนมัติแบบ Real-time)
$grouped_techs = [];
$tech_res = $conn->query("SELECT full_name, department FROM technicians WHERE full_name IS NOT NULL AND full_name != '' ORDER BY department ASC, full_name ASC");

if ($tech_res && $tech_res->num_rows > 0) {
    while ($t = $tech_res->fetch_assoc()) {
        $dept = !empty($t['department']) ? $t['department'] : 'ฝ่ายงานทั่วไป';
        if (!isset($grouped_techs[$dept])) {
            $grouped_techs[$dept] = [];
        }
        $grouped_techs[$dept][] = trim($t['full_name']);
    }
}

// จัดเรียงลำดับฝ่ายงานให้อยู่ในตำแหน่งที่สวยงามเสมอ
$custom_dept_order = [
    'ฝ่ายงานบริการเทคโนโลยีดิจิทัล',
    'ฝ่ายงานโสตทัศนูปกรณ์',
    'ฝ่ายงานยานยนต์',
    'แม่บ้าน',
    'ฝ่ายงานทั่วไป',
    'อื่นๆ'
];

uksort($grouped_techs, function($a, $b) use ($custom_dept_order) {
    $pos_a = array_search($a, $custom_dept_order);
    $pos_b = array_search($b, $custom_dept_order);
    $pos_a = ($pos_a === false) ? 999 : $pos_a;
    $pos_b = ($pos_b === false) ? 999 : $pos_b;
    if ($pos_a == $pos_b) return strcmp($a, $b);
    return $pos_a - $pos_b;
});

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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสารรายงานสรุป - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
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
        .dark .custom-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cbd5e1'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
        
        .a4-container {
            font-family: 'Sarabun', sans-serif;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 30px auto 50px auto;
            background: #ffffff;
            color: #000000;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .dark .a4-container {
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6); 
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; font-size: 14px; color: black !important; min-height: auto !important; }
            
            @page { 
                size: A4 portrait; 
                margin: 15mm; /* ลดขอบลงนิดนึงกันเนื้อหาดันล้นกระดาษ */
            }

            .a4-container { 
                width: 100% !important; 
                height: auto !important; /* ปล่อยความสูงให้เป็นไปตามเนื้อหาจริง */
                min-height: auto !important;
                padding: 0 !important; 
                margin: 0 !important; 
                box-shadow: none !important; 
                page-break-after: always;
                page-break-inside: avoid;
            }
            
            /* บังคับให้กระดาษแผ่นสุดท้าย ห้ามเว้นแผ่นเปล่าเด็ดขาด */
            .a4-container:last-of-type {
                page-break-after: auto !important; 
            }
            
            /* ล้างระยะห่างขอบล่างของเว็บไซต์ตอนปริ้นท์ */
            .pb-10 { padding-bottom: 0 !important; }

            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }

        .memo-head-box { position: relative; height: 2.2cm; margin-bottom: 0.8rem; }
        .garuda-img { width: 1.8cm; height: auto; position: absolute; left: 0; top: 0; }
        .memo-head-title { 
            position: absolute; 
            left: 0; 
            right: 0; 
            top: 0.4cm; 
            text-align: center; 
            font-size: 20pt; 
            font-weight: 700; 
            line-height: 1; 
        }

        .memo-table { width: 100%; border-collapse: collapse; margin-bottom: 0.8rem; font-size: 15px; }
        .memo-table td { padding: 2px 0; vertical-align: top; }
        .memo-lbl { font-weight: 700; white-space: nowrap; padding-right: 4px; width: 1%; }

        .gov-p { font-size: 15px; line-height: 1.6; text-align: justify; margin-bottom: 0.6rem; }
        .gov-indent { text-indent: 2.5cm; }
        .gov-sub { padding-left: 1.2cm; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100 min-h-screen flex flex-col">

    <!-- แถบเมนูควบคุม -->
    <div class="no-print bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 py-4 px-6 sticky top-0 z-50 shadow-md transition-colors duration-300">
        <div class="max-w-7xl mx-auto flex flex-col gap-4">
            
            <div class="flex flex-col lg:flex-row justify-between items-start md:items-center gap-4">
                
                <div class="flex items-center space-x-4 w-full lg:w-auto justify-center lg:justify-start shrink-0">
                    <button type="button" onclick="window.close();" class="bg-violet-50 hover:bg-violet-100 text-violet-700 border-2 border-violet-200 dark:bg-violet-600 dark:hover:bg-violet-500 dark:border-violet-600 dark:text-white px-4 py-1.5 rounded-full text-xs font-bold transition-all shadow-sm flex items-center cursor-pointer">
                        <i class="fas fa-arrow-left mr-2"></i> Dashboard
                    </button>
                    <h1 class="font-extrabold text-sm border-l-2 border-slate-200 dark:border-slate-500 pl-4 text-slate-800 dark:text-slate-100 tracking-wide hidden sm:block">ระบบพิมพ์เอกสารรายงาน</h1>
                </div>
                
                <div class="flex flex-wrap items-center justify-center lg:justify-end gap-2.5 w-full lg:w-auto pb-0.5">
                    <form method="GET" action="print_report.php" class="flex flex-wrap items-center gap-2.5 bg-slate-50 dark:bg-slate-800 p-1.5 px-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-inner">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                        
                        <div class="relative w-full md:w-60" id="techDropdownContainer">
                            <div class="flex items-center w-full bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl border border-slate-200 dark:border-slate-500 shadow-sm focus-within:ring-2 focus-within:ring-indigo-400 transition-colors cursor-text overflow-hidden" onclick="toggleTechDropdown(event, true)">
                                <i class="fas fa-search pl-3 text-slate-400 dark:text-slate-300 opacity-80"></i>
                                <input type="text" id="techSearchInput" class="w-full bg-transparent px-2 py-2 focus:outline-none placeholder-slate-400 dark:placeholder-slate-300" oninput="filterTechDropdown()" onfocus="focusTechSearch(event)" onblur="blurTechSearch(event)" autocomplete="off" placeholder="ค้นหาชื่อช่าง...">
                                <button type="button" class="pr-3 pl-1 text-slate-400 dark:text-slate-300 focus:outline-none flex items-center justify-center" onclick="toggleTechDropdown(event)">
                                    <i class="fas fa-caret-down text-sm"></i>
                                </button>
                            </div>
                            
                            <div id="techDropdownList" class="absolute z-50 w-full md:w-72 mt-2 bg-white dark:bg-slate-700 border border-slate-100 dark:border-slate-600 rounded-2xl shadow-xl max-h-80 overflow-y-auto hidden flex-col py-3 custom-scrollbar right-0 md:right-auto md:left-0">
                                
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

                        <select name="month" class="custom-select bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-full px-3 py-2 border border-slate-200 dark:border-slate-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer transition-colors w-24 sm:w-auto">
                            <?php 
                            for($m=1; $m<=12; $m++) {
                                $sel = ($selected_month === $m) ? 'selected' : '';
                                echo "<option value='$m' $sel>{$thai_months[$m]}</option>";
                            }
                            ?>
                        </select>

                        <select name="year" class="custom-select bg-white dark:bg-slate-600 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-full px-3 py-2 border border-slate-200 dark:border-slate-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer transition-colors w-24 sm:w-auto">
                            <?php 
                            foreach($available_years as $y) {
                                $sel = ($selected_year == $y) ? 'selected' : '';
                                $thai_y = $y + 543;
                                echo "<option value='$y' $sel>พ.ศ. $thai_y</option>";
                            }
                            ?>
                        </select>

                        <button type="submit" class="bg-amber-400 hover:bg-amber-500 text-amber-950 text-xs px-4 py-1.5 rounded-full font-extrabold transition-all shadow-sm">
                            ค้นหา
                        </button>
                    </form>

                    <button id="theme-toggle" type="button" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-amber-400 shadow-sm flex items-center justify-center shrink-0 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors">
                        <i id="theme-toggle-icon" class="fas fa-moon"></i>
                    </button>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mt-2 md:mt-0">
                
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5 w-full sm:w-auto">
                    <a href="print_report.php?type=table&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                       class="px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center border-2 <?php echo $report_type === 'table' ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-600 dark:text-white dark:border-indigo-600 shadow-sm' : 'bg-transparent text-slate-500 border-transparent hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-600'; ?>">
                        <i class="fas fa-table mr-1.5 <?php echo $report_type === 'table' ? 'text-indigo-600 dark:text-indigo-200' : 'text-slate-400 dark:text-slate-400'; ?>"></i> ตารางรายงาน
                    </a>
                    
                    <a href="print_report.php?type=memo&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                       class="px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center border-2 <?php echo $report_type === 'memo' ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-600 dark:text-white dark:border-indigo-600 shadow-sm' : 'bg-transparent text-slate-500 border-transparent hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-600'; ?>">
                        <i class="fas fa-file-alt mr-1.5 <?php echo $report_type === 'memo' ? 'text-indigo-600 dark:text-indigo-200' : 'text-slate-400 dark:text-slate-400'; ?>"></i> บันทึกข้อความ
                    </a>
                    
                    <button type="button" onclick="window.print()" class="bg-slate-900 hover:bg-black dark:bg-rose-800 dark:hover:bg-rose-700 text-white text-xs px-5 py-2 rounded-full font-bold shadow-md transition-all flex items-center ml-1 border border-slate-900 dark:border-rose-800">
                        <i class="fas fa-print mr-1.5 text-slate-300 dark:text-rose-200"></i> พิมพ์ / โหลด PDF
                    </button>
                </div>

                <div class="flex items-center justify-center sm:justify-end w-full sm:w-auto pr-1">
                    <label for="toggleSignature" class="flex items-center cursor-pointer">
                        <span class="mr-3 text-[12px] font-bold text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">ลายเซ็นท้ายเอกสาร</span>
                        <div class="relative flex items-center">
                            <input type="checkbox" id="toggleSignature" class="sr-only peer" checked onchange="toggleSignature()">
                            <div class="w-9 h-5 bg-slate-300 dark:bg-slate-600 peer-focus:outline-none rounded-full peer peer-checked:bg-indigo-500 transition-colors duration-300 shadow-inner"></div>
                            <div class="absolute left-[2px] top-[2px] bg-white border border-slate-300 rounded-full h-4 w-4 transition-transform duration-300 peer-checked:translate-x-[16px] peer-checked:border-white shadow-sm"></div>
                        </div>
                    </label>
                </div>

            </div>

        </div>
    </div>


    <!-- ================== ส่วนแสดงผลรายงาน ================== -->
    
    <div class="flex-1 overflow-auto pb-10">

        <?php if ($report_type === 'memo'): ?>
            <!-- รูปแบบที่ 1: บันทึกข้อความ -->
            <div class="a4-container">
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

                    <div class="mt-12 pt-4 text-right pr-5 signature-block">
                        <div class="inline-block text-center text-[15px] text-black leading-relaxed">
                            <div class="mb-3">ลงชื่อ..........................................................ผู้รายงาน</div>
                            <div class="font-bold mb-1">( <?php echo $reporter_name; ?> )</div>
                            <div>ตำแหน่ง <?php echo $sign_role; ?></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ==========================================
                 รูปแบบที่ 2: ตารางรายงานทางการ
                 ========================================== -->
            <?php
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
            
            foreach ($pages as $page_index => $page_rows):
            ?>
            
            <div class="a4-container">
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
                        <div class="mt-12 pt-4 text-right pr-5 signature-block">
                            <div class="inline-block text-center text-[15px] text-black leading-relaxed">
                                <div class="mb-3">ลงชื่อ..........................................................ผู้รายงาน</div>
                                <div class="font-bold mb-1">( <?php echo $reporter_name; ?> )</div>
                                <div>ตำแหน่ง <?php echo $sign_role; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div> 
            </div> 
            
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script>
        // ✨ ฟังก์ชันควบคุมการซ่อน/แสดง ลายเซ็นท้ายเอกสาร ✨
        function toggleSignature() {
            const checkbox = document.getElementById('toggleSignature');
            const sigBlocks = document.querySelectorAll('.signature-block');
            sigBlocks.forEach(b => {
                b.style.display = checkbox.checked ? 'block' : 'none';
            });
        }

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

        // ควบคุมระบบ Dark Mode
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
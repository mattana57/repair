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

// เงื่อนไขการค้นหา SQL
$where_conditions = [];
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

// ดึงรายการซ่อมทั้งหมด
$repairs_list = $conn->query("SELECT * FROM repairs $where_sql ORDER BY created_at DESC");

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
    <!-- ตั้งค่าให้ Tailwind รองรับ Dark Mode ผ่าน class -->
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
        
        /* พื้นหลังหน้าเว็บเปลี่ยนสีตามโหมดมืด/สว่าง */
        body { 
            font-family: 'Plus Jakarta Sans', 'Sarabun', sans-serif; 
            margin: 0; padding: 0; 
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* กระดาษ A4 ต้องเป็นสีขาวตัวหนังสือสีดำเสมอ แม้ใน Dark Mode */
        .a4-container {
            font-family: 'Sarabun', sans-serif;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 20mm 20mm 25mm;
            margin: 30px auto 50px auto;
            background: #ffffff;
            color: #000000;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
        }

        .dark .a4-container {
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.5); /* ให้เงาเข้มขึ้นในโหมดมืดเพื่อให้กระดาษลอยเด่น */
        }

        /* เปลี่ยนสีลูกศรใน select เวลาเป็น Dark mode */
        .dark select {
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') !important;
        }

        .page-footer {
            position: absolute;
            bottom: 10mm;
            left: 25mm;
            right: 20mm;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; font-size: 15px; color: black !important; }
            .a4-container { 
                box-shadow: none !important; 
                border: none !important; 
                padding: 0 !important; 
                margin: 0 !important; 
                width: 100% !important; 
                min-height: auto !important;
            }
            @page { 
                size: A4 portrait; 
                margin: 20mm 20mm 20mm 25mm; 
            }
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
<body class="bg-slate-50 text-slate-800 dark:bg-slate-800 dark:text-slate-100">

    <!-- ✨ แถบเมนูควบคุม (ปรับการไล่สีให้ปุ่มในโหมดมืดเด่นชัดขึ้น) ✨ -->
    <div class="no-print bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 py-4 px-6 sticky top-0 z-50 shadow-sm transition-colors duration-300">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            
            <!-- ฝั่งซ้าย: แบ่ง 2 บรรทัดชัดเจน -->
            <div class="flex flex-col space-y-4">
                
                <!-- บรรทัดบน: ปุ่ม Dashboard + ชื่อระบบ -->
                <div class="flex items-center space-x-4">
                    <!-- ✨ เปลี่ยนปุ่ม Dashboard โหมดมืดเป็นสีทึบชัดเจน (Violet) ✨ -->
                    <a href="dashboard.php?tab=reports" class="bg-violet-50 hover:bg-violet-100 text-violet-700 border-2 border-violet-200 dark:bg-violet-600 dark:hover:bg-violet-500 dark:border-violet-500 dark:text-white px-4 py-1.5 rounded-full text-xs font-bold transition-all shadow-sm flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Dashboard
                    </a>
                    <h1 class="font-extrabold text-sm border-l-2 border-slate-200 dark:border-slate-600 pl-4 text-slate-800 dark:text-slate-200 tracking-wide">ระบบพิมพ์เอกสารรายงาน</h1>
                </div>
                
                <!-- บรรทัดล่าง: แท็บสลับหน้า + ปุ่มพิมพ์ PDF -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- ✨ แท็บโหมดมืด: ถ้าเปิดอยู่ให้เป็นสีทึบ (Indigo) ชัดเจน ✨ -->
                    <a href="print_report.php?type=table&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>" 
                       class="px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center border-2 <?php echo $report_type === 'table' ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-600 dark:text-white dark:border-indigo-500 shadow-sm' : 'bg-transparent text-slate-500 border-transparent hover:bg-slate-100 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600'; ?>">
                        <i class="fas fa-table mr-1.5 <?php echo $report_type === 'table' ? 'text-indigo-600 dark:text-indigo-200' : 'text-slate-400 dark:text-slate-400'; ?>"></i> ตารางรายงาน
                    </a>
                    
                    <a href="print_report.php?type=memo&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>" 
                       class="px-4 py-1.5 rounded-full text-xs font-bold transition-all flex items-center border-2 <?php echo $report_type === 'memo' ? 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-600 dark:text-white dark:border-indigo-500 shadow-sm' : 'bg-transparent text-slate-500 border-transparent hover:bg-slate-100 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600'; ?>">
                        <i class="fas fa-file-alt mr-1.5 <?php echo $report_type === 'memo' ? 'text-indigo-600 dark:text-indigo-200' : 'text-slate-400 dark:text-slate-400'; ?>"></i> บันทึกข้อความ
                    </a>
                    
                    <!-- ✨ ปุ่ม Print: โหมดสว่างเป็นสีดำ, โหมดมืดเป็นสีแดงไวน์ (red-800) ✨ -->
                    <button type="button" onclick="window.print()" class="bg-slate-900 hover:bg-black dark:bg-red-800 dark:hover:bg-red-900 text-white text-xs px-5 py-2 rounded-full font-bold shadow-md dark:shadow-sm transition-all flex items-center ml-1 border border-slate-900 dark:border-red-700">
                        <i class="fas fa-print mr-1.5 text-slate-300 dark:text-red-200"></i> พิมพ์ / โหลด PDF
                    </button>
                </div>

            </div>

            <!-- ฝั่งขวา: ฟอร์มค้นหา + สลับธีม -->
            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto pb-0.5">
                <form method="GET" action="print_report.php" class="flex flex-wrap items-center gap-2.5 bg-slate-50 dark:bg-slate-700/50 p-1.5 px-2.5 rounded-2xl border border-slate-200 dark:border-slate-600 shadow-sm">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                    
                    <select name="tech" class="bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 appearance-none pr-8 cursor-pointer transition-colors" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0.6rem top 50%; background-size: 0.65rem auto;">
                        <option value="all" <?php echo $selected_tech === 'all' ? 'selected' : ''; ?>>รวมทุกฝ่ายงาน (ทั้งหมด)</option>
                        
                        <?php 
                        foreach($grouped_techs as $dept => $techs) {
                            echo "<optgroup label='--- ".htmlspecialchars($dept)." ---' class='bg-slate-50 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold'>";
                            foreach($techs as $t_name) {
                                $selected = ($selected_tech === $t_name) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($t_name)."' $selected class='bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100'>".htmlspecialchars($t_name)."</option>";
                            }
                            echo "</optgroup>";
                        }
                        ?>
                    </select>

                    <select name="month" class="bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-100 font-bold text-xs rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 appearance-none pr-8 cursor-pointer transition-colors" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0.6rem top 50%; background-size: 0.65rem auto;">
                        <?php 
                        for($m=1; $m<=12; $m++) {
                            $sel = ($selected_month === $m) ? 'selected' : '';
                            echo "<option value='$m' $sel>{$thai_months[$m]}</option>";
                        }
                        ?>
                    </select>

                    <button type="submit" class="bg-amber-400 hover:bg-amber-500 text-amber-950 text-xs px-4 py-2 rounded-xl font-extrabold transition-all shadow-sm">
                        ค้นหา
                    </button>
                </form>

                <!-- ✨ ปุ่มสลับ Theme ถอยห่างออกมาด้วย ml-6 ✨ -->
                <button id="theme-toggle" type="button" class="w-9 h-9 rounded-full bg-white dark:bg-slate-700 border-2 border-slate-200 dark:border-slate-600 text-slate-500 dark:text-amber-400 shadow-sm hover:text-indigo-600 dark:hover:text-amber-300 transition-all flex items-center justify-center shrink-0 ml-6">
                    <i id="theme-toggle-icon" class="fas fa-moon"></i>
                </button>
            </div>

        </div>
    </div>

    <!-- ส่วนของกระดาษ A4 (จะคงเป็นสีขาวเสมอแม้เปิด Dark Mode) -->
    <div class="a4-container">

        <?php if ($report_type === 'memo'): ?>
        <!-- ==========================================
             รูปแบบที่ 1: บันทึกข้อความ
             ========================================== -->
        <div class="pb-10">
            
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
                    <p class="gov-p font-bold mb-1">๑. สรุปภาพรวมสถานะการดำเนินงาน</p>
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
                    <p class="gov-p font-bold mb-1">๒. สถิติอุปกรณ์ที่พบปัญหาความชำรุดบกพร่องสูงสุด</p>
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

            <!-- ส่วนลายเซ็น: บันทึกข้อความ -->
            <div style="margin-top: 60px; text-align: right; padding-right: 20px;">
                <div style="display: inline-block; text-align: center; font-size: 15px; color: #000; line-height: 1.8;">
                    <div style="margin-bottom: 10px;">(ลงชื่อ)........................................................................</div>
                    <div style="font-weight: bold; margin-bottom: 2px;">( <?php echo $reporter_name; ?> )</div>
                    <div>ตำแหน่ง <?php echo $sign_role; ?></div>
                </div>
            </div>

        </div>

        <?php else: ?>
        <!-- ==========================================
             รูปแบบที่ 2: ตารางรายงานทางการ
             ========================================== -->
        <div class="pb-10">
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
                <h3 class="font-bold text-sm text-slate-800 mb-2">1. สรุปภาพรวมการซ่อมบำรุง (KPI Summary)</h3>
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

            <div class="mb-5">
                <h3 class="font-bold text-sm text-slate-800 mb-2">
                    2. บันทึกรายละเอียดการปฏิบัติงานซ่อมบำรุง 
                    <?php if($selected_tech !== 'all') echo " (เฉพาะ: ".htmlspecialchars($tech_formal_name).")"; ?>
                </h3>
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
                        if($repairs_list && $repairs_list->num_rows > 0) {
                            $i = 1;
                            while($row = $repairs_list->fetch_assoc()) {
                                $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                $ticket = $row['ticket_no'] ?? ('#REP-'.$row['id']);
                                $eq = htmlspecialchars($row['equipment_type'] ?? ($row['device_name'] ?? 'ไม่ระบุ'));
                                $loc = htmlspecialchars($row['location_room'] ?? ($row['location'] ?? 'ไม่ระบุ'));
                                $tech = htmlspecialchars($row['technician_name'] ?? 'ยังไม่จัดสรร');
                                $st = $row['status'] ?? 'ไม่ระบุ';

                                echo "<tr class='border-b border-slate-200'>
                                    <td class='p-1.5 text-center border-r border-slate-200'>{$i}</td>
                                    <td class='p-1.5 text-center border-r border-slate-200'>{$date}</td>
                                    <td class='p-1.5 text-center font-semibold border-r border-slate-200'>{$ticket}</td>
                                    <td class='p-1.5 border-r border-slate-200'>{$eq}</td>
                                    <td class='p-1.5 border-r border-slate-200'>{$loc}</td>
                                    <td class='p-1.5 font-semibold text-slate-800 border-r border-slate-200'>{$tech}</td>
                                    <td class='p-1.5 text-center font-semibold'>{$st}</td>
                                </tr>";
                                $i++;
                            }
                        } else {
                            echo "<tr><td colspan='7' class='p-8 text-center text-slate-400 italic bg-slate-50'>ไม่พบข้อมูลการแจ้งซ่อมของช่างหรือเดือนที่เลือก</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- ส่วนลายเซ็น: ตารางรายงาน -->
            <div style="margin-top: 60px; text-align: right; padding-right: 20px;">
                <div style="display: inline-block; text-align: center; font-size: 15px; color: #000; line-height: 1.8;">
                    <div style="margin-bottom: 10px;">ลงชื่อ..........................................................ผู้รายงาน</div>
                    <div style="font-weight: bold; margin-bottom: 2px;">( <?php echo $reporter_name; ?> )</div>
                    <div>ตำแหน่ง <?php echo $sign_role; ?></div>
                </div>
            </div>

        </div>
        <?php endif; ?>

        <!-- ท้ายกระดาษ: ซ่อนเมื่อสั่งพิมพ์ -->
        <div class="page-footer no-print border-t border-slate-200 pt-2 text-[10px] text-slate-400 flex justify-between">
            <span>ระบบสารสนเทศ MBS REPAIR - คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</span>
            <span>วันที่พิมพ์เอกสาร: <?php echo date('d/m/Y H:i'); ?> น.</span>
        </div>
        
    </div>

    <!-- ✨ JavaScript สำหรับระบบ Dark/Light Mode ✨ -->
    <script>
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeToggleIcon = document.getElementById('theme-toggle-icon');

        // ฟังก์ชันอัปเดตไอคอน
        function updateIcon() {
            if (document.documentElement.classList.contains('dark')) {
                themeToggleIcon.classList.remove('fa-moon');
                themeToggleIcon.classList.add('fa-sun');
            } else {
                themeToggleIcon.classList.remove('fa-sun');
                themeToggleIcon.classList.add('fa-moon');
            }
        }

        // เช็คการตั้งค่าเดิมใน Local Storage หรือ System Preferences ตอนเปิดหน้าเว็บ
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        updateIcon();

        // เมื่อกดปุ่มสลับ Theme
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
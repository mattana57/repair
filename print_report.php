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
$report_type = isset($_GET['type']) ? $_GET['type'] : 'memo'; // หน้าพิมพ์ตั้งต้นเป็น memo

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
    return 'นาย' . $name;
}

$tech_formal_name = getPrefixName($selected_tech);

// ดึงรายชื่อช่างทั้งหมดจาก DB
$tech_list = $conn->query("SELECT DISTINCT TRIM(technician_name) as tech_name FROM repairs WHERE technician_name IS NOT NULL AND technician_name != ''");

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
    $report_title = "รายงานสรุปผลการปฏิบัติงานรายบุคคล ของ " . htmlspecialchars($tech_formal_name);
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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            src: url('https://cdn.jsdelivr.net/gh/lazywasabi/thai-web-fonts@7/fonts/THSarabunNew/THSarabunNew.woff2') format('woff2');
            font-weight: normal; font-style: normal;
        }
        @font-face {
            font-family: 'THSarabunNew';
            src: url('https://cdn.jsdelivr.net/gh/lazywasabi/thai-web-fonts@7/fonts/THSarabunNew/THSarabunNew%20Bold.woff2') format('woff2');
            font-weight: bold; font-style: normal;
        }

        * { box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f8ff; color: #000; margin: 0; padding: 0; }
        
        .a4-container {
            font-family: 'THSarabunNew', sans-serif;
            width: 210mm;
            min-height: 297mm;
            padding: 1.5cm 2cm 2cm 3cm; 
            margin: 20px auto;
            background: white;
            box-shadow: 0 4px 25px rgba(106, 156, 253, 0.15);
            position: relative;
            font-size: 16pt;
        }

        .page-footer {
            position: absolute;
            bottom: 10mm;
            left: 3cm;
            right: 2cm;
            font-family: 'Sarabun', sans-serif;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .a4-container { 
                box-shadow: none !important; 
                border: none !important; 
                padding: 1.5cm 2cm 2cm 3cm !important; 
                margin: 0 !important; 
                width: 100% !important; 
                min-height: auto !important;
            }
            @page { 
                size: A4 portrait; 
                margin: 0; 
            }
        }

        .memo-head-box { position: relative; height: 2.2cm; margin-bottom: 1rem; }
        .garuda-img { width: 1.5cm; height: auto; position: absolute; left: 0; top: 0; }
        .memo-head-title { 
            position: absolute; 
            left: 0; 
            right: 0; 
            top: 0.5cm; 
            text-align: center; 
            font-size: 29pt; 
            font-weight: bold; 
            line-height: 1; 
        }

        .memo-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 16pt; }
        .memo-table td { padding: 2px 0; vertical-align: top; line-height: 1.2; }
        .memo-lbl { font-weight: bold; white-space: nowrap; padding-right: 15px; width: 1%; }

        .gov-p { font-size: 16pt; line-height: 1.15; text-align: justify; margin-bottom: 5px; }
        .gov-indent { text-indent: 2.5cm; }
        .gov-sub { padding-left: 2.5cm; }

        .bg-palette-header { background: linear-gradient(135deg, #033495 0%, #6A9CFD 100%); font-family: 'Sarabun', sans-serif;}
        .btn-palette-active { background-color: #FFB8D0; color: #033495; }
        .btn-palette-inactive { background-color: rgba(255, 255, 255, 0.2); color: #ffffff; }
        .btn-palette-inactive:hover { background-color: rgba(255, 255, 255, 0.35); }
    </style>
</head>
<body>

    <!-- แถบเมนูควบคุม ด้านบน (ไม่แสดงตอนพิมพ์) -->
    <div class="no-print bg-palette-header text-white p-3.5 sticky top-0 z-50 shadow-md">
        <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-4">
            
            <div class="flex items-center space-x-3">
                <!-- 🟢 เปลี่ยนลิงก์กลับไปที่หน้า dashboard.php (แอดมิน) เสมอ -->
                <a href="dashboard.php" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold text-white transition-all backdrop-blur-sm">
                    ← Dashboard
                </a>
                <h1 class="font-bold text-sm border-l border-white/30 pl-3 text-white tracking-wide">พิมพ์เอกสารรายงาน (Print Report)</h1>
            </div>

            <!-- ฟอร์มเลือกกรองข้อมูล -->
            <form method="GET" action="" class="flex flex-wrap items-center gap-2.5">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                
                <div>
                    <select name="tech" class="bg-white text-[#033495] font-semibold text-xs rounded-xl px-3 py-1.5 border border-sky-200 shadow-sm focus:outline-none">
                        <option value="all" <?php echo $selected_tech === 'all' ? 'selected' : ''; ?>>-- ช่างทุกคน (ภาพรวมคณะ) --</option>
                        <?php 
                        if($tech_list) {
                            while($t = $tech_list->fetch_assoc()) {
                                $t_name = $t['tech_name'];
                                $selected = ($selected_tech === $t_name) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($t_name)."' $selected>".htmlspecialchars($t_name)."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <select name="month" class="bg-white text-[#033495] font-semibold text-xs rounded-xl px-3 py-1.5 border border-sky-200 shadow-sm focus:outline-none">
                        <?php 
                        for($m=1; $m<=12; $m++) {
                            $sel = ($selected_month === $m) ? 'selected' : '';
                            echo "<option value='$m' $sel>{$thai_months[$m]}</option>";
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" class="bg-[#033495] hover:bg-[#022578] text-white text-xs px-3.5 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                    ค้นหา
                </button>
            </form>

            <!-- ปุ่มสลับรูปแบบเอกสาร และปุ่มพิมพ์ -->
            <div class="flex items-center space-x-2">
                <a href="?type=table&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>" 
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shadow-sm <?php echo $report_type === 'table' ? 'btn-palette-active' : 'btn-palette-inactive'; ?>">
                    📊 ตารางรายงาน
                </a>
                <a href="?type=memo&tech=<?php echo urlencode($selected_tech); ?>&month=<?php echo $selected_month; ?>" 
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shadow-sm <?php echo $report_type === 'memo' ? 'btn-palette-active' : 'btn-palette-inactive'; ?>">
                    📜 บันทึกข้อความ
                </a>
                <button type="button" onclick="window.print()" class="bg-[#AEE4FF] hover:bg-[#8CD8FF] text-[#033495] text-xs px-3.5 py-1.5 rounded-xl font-bold shadow-md transition-all">
                    🖨️ พิมพ์ / โหลด PDF
                </button>
            </div>

        </div>
    </div>

    <!-- หน้ากระดาษเอกสาร A4 -->
    <div class="a4-container">

        <?php if ($report_type === 'memo'): ?>
        <!-- ==========================================
             รูปแบบที่ 1: บันทึกข้อความ
             ========================================== -->
        <div class="text-black pb-10">
            
            <div class="memo-head-box">
                <!-- ตราครุฑอ้างอิงจากโฟลเดอร์รูปภาพที่มีอยู่ -->
                <img src="ตราครุฑ.jpg" alt="ตราครุฑ" class="garuda-img" onerror="this.src='uploads/garuda.png'">
                <div class="memo-head-title">บันทึกข้อความ</div>
            </div>

            <table class="memo-table pb-2">
                <tr>
                    <td class="memo-lbl">ส่วนราชการ</td>
                    <td colspan="3">ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</td>
                </tr>
                <tr>
                    <td class="memo-lbl">ที่</td>
                    <td style="width: 50%;">ศธ ๐๕๓๐.๑๑/.........................</td>
                    <td class="memo-lbl">วันที่</td>
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

            <div class="pt-2">
                <p class="gov-p gov-indent">
                    ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการเปิดรับแจ้งซ่อมและบำรุงรักษาอุปกรณ์คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ ผ่านระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) นั้น
                </p>
                <p class="gov-p gov-indent">
                    ในการนี้ ทางผู้ดูแลระบบได้รวบรวมข้อมูลสถิติการปฏิบัติงาน ประจำเดือน <?php echo $thai_months[$selected_month]; ?> เพื่อรายงานผลการดำเนินงานให้ทราบ โดยมีรายละเอียดดังต่อไปนี้
                </p>

                <div class="mt-3 mb-2" style="page-break-inside: avoid;">
                    <p class="gov-p font-bold mb-1">๑. สรุปภาพรวมสถานะการดำเนินงาน</p>
                    <p class="gov-p gov-indent mb-1">
                        มีจำนวนการแจ้งซ่อมในระบบทั้งสิ้น <strong class="font-bold"><?php echo toThaiNumber($total_jobs); ?></strong> รายการ โดยแบ่งตามสถานะการดำเนินงาน ดังนี้
                    </p>
                    <div class="gov-sub space-y-0 text-[16pt]">
                        <p>๑.๑ ดำเนินการซ่อมแซมเสร็จสิ้นแล้ว จำนวน <strong class="font-bold"><?php echo toThaiNumber($done_jobs); ?></strong> รายการ (คิดเป็นร้อยละ <?php echo toThaiNumber(number_format($success_rate, 2)); ?>)</p>
                        <p>๑.๒ อยู่ระหว่างดำเนินการ จำนวน <strong class="font-bold"><?php echo toThaiNumber($in_progress_jobs); ?></strong> รายการ</p>
                        <p>๑.๓ รอดำเนินการ/รอรับเรื่อง จำนวน <strong class="font-bold"><?php echo toThaiNumber($pending_jobs); ?></strong> รายการ</p>
                    </div>
                </div>

                <div class="mt-3 mb-2" style="page-break-inside: avoid;">
                    <p class="gov-p font-bold mb-1">๒. สถิติอุปกรณ์ที่พบปัญหาความชำรุดบกพร่องสูงสุด</p>
                    <p class="gov-p gov-indent mb-1">
                        ข้อมูลประเภทครุภัณฑ์และอุปกรณ์ที่มีสถิติการแจ้งซ่อมสูงสุด ประกอบด้วย
                    </p>
                    <div class="gov-sub space-y-0 text-[16pt]">
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

                <p class="gov-p gov-indent mt-3" style="page-break-inside: avoid;">
                    <?php echo $doc_purpose; ?>
                </p>

                <p class="gov-p gov-indent mt-3" style="page-break-inside: avoid;">
                    จึงเรียนมาเพื่อโปรดทราบ
                </p>
            </div>

            <!-- ส่วนลงชื่อ (แสดงเสมอ และเปลี่ยนชื่อตาม Session อัตโนมัติ) -->
            <div class="mt-12 flex justify-end" style="page-break-inside: avoid;">
                <div class="w-80 text-center space-y-2 text-[16pt]">
                    <p>(ลงชื่อ).................................................................</p>
                    <p class="mt-2">( <?php echo $reporter_name; ?> )</p>
                    <p><?php echo $sign_role; ?></p>
                </div>
            </div>

        </div>

        <?php else: ?>
        <!-- ==========================================
             รูปแบบที่ 2: ตารางรายงานทางการ
             ========================================== -->
        <div class="pb-10 font-sans" style="font-family: 'Sarabun', sans-serif; font-size: 14px;">
            <div class="text-center border-b-2 border-slate-900 pb-3 mb-5 mt-5">
                <h2 class="text-xl font-bold text-slate-900">รายงานสรุปผลการปฏิบัติงานซ่อมบำรุงครุภัณฑ์</h2>
                <p class="text-sm font-semibold text-slate-700 mt-1">คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                <p class="text-xs text-slate-600 mt-1">
                    <strong>ประจำเดือน:</strong> <?php echo $thai_months[$selected_month]; ?> พ.ศ. <?php echo $selected_year + 543; ?> 
                    | <strong>ผู้รับผิดชอบ:</strong> <?php echo ($selected_tech === 'all') ? 'เจ้าหน้าที่ช่างทุกคน (ภาพรวมคณะ)' : htmlspecialchars($tech_formal_name); ?>
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
                                $loc = htmlspecialchars($row['location'] ?? 'ไม่ระบุ');
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

            <div class="mt-8 flex justify-end" style="page-break-inside: avoid;">
                <div class="w-72 text-center space-y-1.5 text-xs">
                    <p class="mb-8">ลงชื่อ..........................................................ผู้รายงาน</p>
                    <p class="font-bold text-slate-800">( <?php echo $reporter_name; ?> )</p>
                    <p class="text-slate-600">ตำแหน่ง <?php echo $sign_role; ?></p>
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

</body>
</html>
<?php
session_start();
include 'db_connect.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนดูรายงาน");
}

// รับค่าเดือนที่ต้องการดูรายงาน (ถ้าไม่ได้เลือกให้ใช้เดือนปัจจุบัน)
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// เงื่อนไขสำหรับค้นหาข้อมูลตามเดือนและปี
$where_sql = "WHERE MONTH(created_at) = $selected_month AND YEAR(created_at) = $selected_year";

// ฟังก์ชันแปลงตัวเลขเป็นเลขไทย
function toThaiNumber($num) {
    $arabic = ['0','1','2','3','4','5','6','7','8','9'];
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    return str_replace($arabic, $thai, (string)$num);
}

// ดึงข้อมูลสถิติ (กรองตามเดือน)
$total_repairs = 0; $completed_repairs = 0; $pending_repairs = 0; $success_rate = 0;
$top_equipment = "-"; $top_equipment_count = 0;

$res = $conn->query("SELECT count(*) as c FROM repairs $where_sql");
$total_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$res = $conn->query("SELECT count(*) as c FROM repairs $where_sql AND (status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น')");
$completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$res = $conn->query("SELECT count(*) as c FROM repairs $where_sql AND status != 'ซ่อมเสร็จแล้ว' AND status != 'เสร็จสิ้น'");
$pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100, 2) : 0;

// อุปกรณ์ที่เสียบ่อยสุด (กรองตามเดือน)
$top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs $where_sql GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
if($top_eq_query && $top_eq_query->num_rows > 0) {
    $top_eq_data = $top_eq_query->fetch_assoc();
    $top_equipment = $top_eq_data['equipment_type'];
    $top_equipment_count = $top_eq_data['cnt'];
}

// ข้อมูลวันที่
$thai_months = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
$report_month_name = $thai_months[$selected_month];
$thai_year_display = $selected_year + 543;

// ชื่อผู้รายงาน
$reporter_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'นางสาวมัทนา รัตนแสง';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุปผู้บริหาร - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f8ff; color: #000; margin: 0; padding: 0; }
        
        .a4-container {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 20mm 20mm 25mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 4px 25px rgba(106, 156, 253, 0.15);
            position: relative;
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

        .bg-palette-header {
            background: linear-gradient(135deg, #033495 0%, #6A9CFD 100%);
        }
    </style>
</head>
<body>

    <!-- แถบเมนูด้านบน -->
    <div class="no-print bg-palette-header text-white p-3.5 sticky top-0 z-50 shadow-md">
        <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <a href="executive_dashboard.php" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold text-white transition-all backdrop-blur-sm">
                    ← กลับหน้า Dashboard
                </a>
                <h1 class="font-bold text-sm border-l border-white/30 pl-3 text-white tracking-wide hidden sm:block">รายงานสรุปผู้บริหาร</h1>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- 🟢 เพิ่มฟอร์มเลือกเดือน -->
                <form method="GET" action="" class="flex items-center gap-2">
                    <select name="month" class="bg-white text-[#033495] font-semibold text-xs rounded-xl px-3 py-1.5 border border-sky-200 shadow-sm focus:outline-none cursor-pointer">
                        <?php 
                        for($m=1; $m<=12; $m++) {
                            $sel = ($selected_month === $m) ? 'selected' : '';
                            echo "<option value='$m' $sel>{$thai_months[$m]}</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" class="bg-[#033495] hover:bg-[#022578] text-white text-xs px-3.5 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                        ค้นหา
                    </button>
                </form>

                <button type="button" onclick="window.print()" class="bg-[#AEE4FF] hover:bg-[#8CD8FF] text-[#033495] text-xs px-3.5 py-1.5 rounded-xl font-bold shadow-md transition-all">
                    🖨️ พิมพ์ / โหลด PDF
                </button>
            </div>
        </div>
    </div>

    <!-- หน้ากระดาษเอกสาร A4 -->
    <div class="a4-container">
        <div class="text-black pb-10">
            
            <!-- หัวตราครุฑชิดซ้าย + บันทึกข้อความ -->
            <div class="memo-head-box">
                <img src="ตราครุฑ.jpg" alt="ตราครุฑ" class="garuda-img" onerror="this.src='uploads/garuda.png'">
                <div class="memo-head-title">บันทึกข้อความ</div>
            </div>

            <!-- รายละเอียดส่วนหัว -->
            <table class="memo-table pb-1">
                <tr>
                    <td class="memo-lbl">ส่วนราชการ</td>
                    <td colspan="3">ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</td>
                </tr>
                <tr>
                    <td class="memo-lbl">ที่</td>
                    <td style="width: 48%;">ศธ ๐๕๓๐.๑๑/.........................</td>
                    <td style="width: 1%; font-weight:700; white-space:nowrap; padding-right: 4px;">วันที่</td>
                    <td><?php echo toThaiNumber(date('j'))." ". $thai_months[(int)date('m')] ." ".toThaiNumber(date('Y') + 543); ?></td>
                </tr>
                <tr>
                    <td class="memo-lbl">เรื่อง</td>
                    <td colspan="3">รายงานสรุปผลการปฏิบัติงานเชิงกลยุทธ์ (Executive Summary) ประจำเดือน <?php echo $report_month_name; ?></td>
                </tr>
                <tr>
                    <td class="memo-lbl">เรียน</td>
                    <td colspan="3">คณบดีคณะการบัญชีและการจัดการ</td>
                </tr>
            </table>

            <!-- เนื้อหาหนังสือ -->
            <div class="pt-1">
                <p class="gov-p gov-indent">
                    ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการนำระบบสารสนเทศเพื่อการแจ้งซ่อมและบำรุงรักษา (MBS REPAIR) มาประยุกต์ใช้ในการบริหารจัดการทรัพยากร อาคารสถานที่ และระบบเทคโนโลยีสารสนเทศภายในคณะฯ นั้น
                </p>
                <p class="gov-p gov-indent">
                    ในการนี้ ทางผู้ดูแลระบบได้ทำการรวบรวมและวิเคราะห์ข้อมูลเชิงสถิติ (Data Analytics) ประจำเดือน <?php echo $report_month_name; ?> เพื่อประกอบการพิจารณาตัดสินใจเชิงนโยบายและการบริหารงบประมาณ โดยมีรายละเอียดสรุปผลการดำเนินงาน ดังต่อไปนี้
                </p>

                <!-- หัวข้อที่ 1: KPI -->
                <div class="mb-2">
                    <p class="gov-p font-bold mb-1">๑. สรุปตัวชี้วัดผลการดำเนินงาน (Key Performance Indicators)</p>
                    <div class="gov-sub space-y-0.5 text-[15px]">
                        <p>๑.๑ ปริมาณงานรับแจ้งซ่อมทั้งหมด จำนวน <strong class="font-bold"><?php echo toThaiNumber($total_repairs); ?></strong> รายการ</p>
                        <p>๑.๒ ดำเนินการแก้ไขเสร็จสิ้นแล้ว จำนวน <strong class="font-bold"><?php echo toThaiNumber($completed_repairs); ?></strong> รายการ (คิดเป็นอัตราความสำเร็จ ร้อยละ <?php echo toThaiNumber(number_format($success_rate, 2)); ?>)</p>
                        <p>๑.๓ งานที่อยู่ระหว่างดำเนินการและรอรับเรื่อง จำนวน <strong class="font-bold"><?php echo toThaiNumber($pending_repairs); ?></strong> รายการ</p>
                    </div>
                </div>

                <!-- หัวข้อที่ 2: AI Recommendation -->
                <div class="mb-2">
                    <p class="gov-p font-bold mb-1">๒. ประเด็นที่ต้องเฝ้าระวังและข้อเสนอแนะเชิงกลยุทธ์ (Strategic Recommendations)</p>
                    <?php if($total_repairs > 0 && $top_equipment !== "-"): ?>
                        <p class="gov-p gov-indent mb-1">
                            จากการวิเคราะห์ข้อมูลความถี่ในการชำรุด พบว่าอุปกรณ์ประเภท <strong class="font-bold">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> มีสถิติการแจ้งซ่อมสูงสุด จำนวน <strong class="font-bold"><?php echo toThaiNumber($top_equipment_count); ?></strong> ครั้ง ในรอบเดือนที่ผ่านมา ซึ่งสะท้อนให้เห็นถึงความจำเป็นในการวางแผนบำรุงรักษาเชิงป้องกัน
                        </p>
                        <p class="gov-p gov-indent mb-1">
                            <strong class="font-bold">ข้อเสนอแนะ:</strong> เพื่อลดภาระค่าใช้จ่ายในการบำรุงรักษาระยะยาวและเพิ่มประสิทธิภาพการสนับสนุนการเรียนการสอน จึงเห็นควรเสนอให้พิจารณาบรรจุแผนการตั้งงบประมาณ เพื่อดำเนินการ <strong class="font-bold">จัดซื้อ "<?php echo htmlspecialchars($top_equipment); ?>" ชุดใหม่ทดแทน</strong> ตามความเหมาะสมต่อไป
                        </p>
                    <?php else: ?>
                        <p class="gov-p gov-indent mb-1">
                            ในรอบเดือนนี้ ไม่พบสถิติการแจ้งซ่อม หรือไม่พบความผิดปกติของการชำรุดแบบซ้ำซ้อนที่มีนัยสำคัญต่อการปฏิบัติงาน
                        </p>
                    <?php endif; ?>
                </div>

                <p class="gov-p gov-indent pt-1">
                    จึงเรียนมาเพื่อโปรดพิจารณา
                </p>
            </div>

            <!-- ส่วนลงชื่อ -->
            <div class="mt-8 flex justify-end">
                <div class="w-72 text-center space-y-1.5 text-[15px]">
                    <p>(ลงชื่อ).................................................................</p>
                    <p class="font-bold mt-2">( <?php echo $reporter_name; ?> )</p>
                    <p class="text-slate-700 text-sm">ผู้ดูแลระบบสารสนเทศ</p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
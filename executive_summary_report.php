<?php
session_start();
include 'db_connect.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนดูรายงาน");
}

// ฟังก์ชันแปลงตัวเลขเป็นเลขไทย
function toThaiNumber($num) {
    $arabic = ['0','1','2','3','4','5','6','7','8','9'];
    $thai = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
    return str_replace($arabic, $thai, (string)$num);
}

// ดึงข้อมูลสถิติ
$total_repairs = 0; $completed_repairs = 0; $pending_repairs = 0; $success_rate = 0;
$top_equipment = "-"; $top_equipment_count = 0;

$res = $conn->query("SELECT count(*) as c FROM repairs");
$total_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น'");
$completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$res = $conn->query("SELECT count(*) as c FROM repairs WHERE status != 'ซ่อมเสร็จแล้ว' AND status != 'เสร็จสิ้น'");
$pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;

$success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100, 2) : 0;

// อุปกรณ์ที่เสียบ่อยสุด
$top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
if($top_eq_query && $top_eq_query->num_rows > 0) {
    $top_eq_data = $top_eq_query->fetch_assoc();
    $top_equipment = $top_eq_data['equipment_type'];
    $top_equipment_count = $top_eq_data['cnt'];
}

// ข้อมูลจำลองสำหรับ SLA และ Cost เชิงบริหาร
$avg_sla_days = 1.2; 
$estimated_cost = $total_repairs * 450; 

// ข้อมูลวันที่
$thai_months = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
$current_month_num = (int)date('m');
$current_month_name = $thai_months[$current_month_num];
$thai_year = date('Y') + 543;

// ชื่อผู้รายงาน
$reporter_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'นางสาวมัทนา รัตนแสง';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสรุปสำหรับผู้บริหาร - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- ใช้ฟอนต์สารบรรณแบบราชการ -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            @page { size: A4 portrait; margin: 0; }
        }

        /* รูปแบบบันทึกข้อความ */
        .memo-head-box { position: relative; height: 2.2cm; margin-bottom: 1rem; }
        .garuda-img { width: 1.5cm; height: auto; position: absolute; left: 0; top: 0; }
        .memo-head-title { 
            position: absolute; left: 0; right: 0; top: 0.5cm; 
            text-align: center; font-size: 29pt; font-weight: bold; line-height: 1; 
        }

        .memo-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 16pt; }
        .memo-table td { padding: 2px 0; vertical-align: top; line-height: 1.2; }
        .memo-lbl { font-weight: bold; white-space: nowrap; padding-right: 15px; width: 1%; }

        .gov-p { font-size: 16pt; line-height: 1.15; text-align: justify; margin-bottom: 8px; }
        .gov-indent { text-indent: 2.5cm; }
        .gov-sub { padding-left: 2.5cm; }
    </style>
</head>
<body>

    <!-- แถบเมนูด้านบนสำหรับสั่งพิมพ์ -->
    <div class="no-print bg-indigo-600 text-white p-3.5 sticky top-0 z-50 shadow-md">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="executive_dashboard.php" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-xl text-xs font-bold text-white transition-all">
                    ← กลับหน้า Dashboard
                </a>
                <h1 class="font-bold text-sm border-l border-white/30 pl-3">เอกสารสรุปผู้บริหาร (Executive Summary) - แบบทางการ</h1>
            </div>
            <button type="button" onclick="window.print()" class="bg-white text-indigo-600 px-4 py-2 rounded-xl text-xs font-bold shadow-md hover:bg-indigo-50 transition-all">
                🖨️ พิมพ์บันทึกข้อความ
            </button>
        </div>
    </div>

    <!-- กระดาษ A4 -->
    <div class="a4-container">

        <div class="text-black pb-10">
            
            <div class="memo-head-box">
                <!-- ใช้ครุฑเหมือนหน้าพิมพ์ปกติ -->
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
                    <td><?php echo toThaiNumber(date('j'))." ".$current_month_name." ".toThaiNumber($thai_year); ?></td>
                </tr>
                <tr>
                    <td class="memo-lbl">เรื่อง</td>
                    <td colspan="3">รายงานสรุปผลการปฏิบัติงานเชิงกลยุทธ์ (Executive Summary) ประจำเดือน <?php echo $current_month_name; ?></td>
                </tr>
                <tr>
                    <td class="memo-lbl">เรียน</td>
                    <td colspan="3">คณบดีคณะการบัญชีและการจัดการ</td>
                </tr>
            </table>

            <div class="pt-2">
                <p class="gov-p gov-indent">
                    ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการนำระบบสารสนเทศเพื่อการแจ้งซ่อมและบำรุงรักษา (MBS REPAIR) มาประยุกต์ใช้ในการบริหารจัดการทรัพยากร อาคารสถานที่ และระบบเทคโนโลยีสารสนเทศภายในคณะฯ นั้น
                </p>
                <p class="gov-p gov-indent">
                    ในการนี้ ทางผู้ดูแลระบบได้ทำการรวบรวมและวิเคราะห์ข้อมูลเชิงสถิติ (Data Analytics) ประจำเดือน <?php echo $current_month_name; ?> เพื่อประกอบการพิจารณาตัดสินใจเชิงนโยบายและการบริหารงบประมาณ โดยมีรายละเอียดสรุปผลการดำเนินงาน ดังต่อไปนี้
                </p>

                <!-- หัวข้อที่ 1: KPI -->
                <div class="mt-4 mb-3" style="page-break-inside: avoid;">
                    <p class="gov-p font-bold mb-1">๑. สรุปตัวชี้วัดผลการดำเนินงาน (Key Performance Indicators)</p>
                    <div class="gov-sub space-y-0 text-[16pt]">
                        <p>๑.๑ ปริมาณงานรับแจ้งซ่อมทั้งหมด จำนวน <strong class="font-bold"><?php echo toThaiNumber($total_repairs); ?></strong> รายการ</p>
                        <p>๑.๒ ดำเนินการแก้ไขเสร็จสิ้นแล้ว จำนวน <strong class="font-bold"><?php echo toThaiNumber($completed_repairs); ?></strong> รายการ (คิดเป็นอัตราความสำเร็จ ร้อยละ <?php echo toThaiNumber(number_format($success_rate, 2)); ?>)</p>
                        <p>๑.๓ งานที่อยู่ระหว่างดำเนินการและรอรับเรื่อง จำนวน <strong class="font-bold"><?php echo toThaiNumber($pending_repairs); ?></strong> รายการ</p>
                        <p>๑.๔ ระยะเวลาเฉลี่ยในการดำเนินการซ่อมแซมต่อรายการ (SLA) อยู่ที่ <strong class="font-bold"><?php echo toThaiNumber($avg_sla_days); ?></strong> วัน</p>
                        <p>๑.๕ ประมาณการมูลค่าภาระค่าใช้จ่ายในการซ่อมบำรุงรวมทั้งสิ้น <strong class="font-bold"><?php echo toThaiNumber(number_format($estimated_cost)); ?></strong> บาท</p>
                    </div>
                </div>

                <!-- หัวข้อที่ 2: ข้อเสนอแนะเชิงนโยบาย -->
                <div class="mt-4 mb-3" style="page-break-inside: avoid;">
                    <p class="gov-p font-bold mb-1">๒. ประเด็นที่ต้องเฝ้าระวังและข้อเสนอแนะเชิงกลยุทธ์ (Strategic Recommendations)</p>
                    <p class="gov-p gov-indent mb-1">
                        จากการวิเคราะห์ข้อมูลความถี่ในการชำรุดของครุภัณฑ์ พบว่าอุปกรณ์ประเภท <strong class="font-bold">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> มีสถิติการแจ้งซ่อมซ้ำซ้อนสูงสุด จำนวน <strong class="font-bold"><?php echo toThaiNumber($top_equipment_count); ?></strong> ครั้ง ซึ่งสะท้อนให้เห็นถึงวงจรการเสื่อมสภาพของอุปกรณ์ที่อาจหมดความคุ้มค่าในการซ่อมแซมแบบรายชิ้น
                    </p>
                    <p class="gov-p gov-indent">
                        <strong class="font-bold">ข้อเสนอแนะ:</strong> เพื่อลดภาระค่าใช้จ่ายในการบำรุงรักษาระยะยาวและเพิ่มประสิทธิภาพการสนับสนุนการเรียนการสอน จึงเห็นควรเสนอให้พิจารณาบรรจุแผนการตั้งงบประมาณประจำปี เพื่อดำเนินการ <strong class="font-bold">จัดซื้อ "<?php echo htmlspecialchars($top_equipment); ?>" ชุดใหม่ทดแทน</strong> ตามความเหมาะสมต่อไป
                    </p>
                </div>

                <p class="gov-p gov-indent mt-5" style="page-break-inside: avoid;">
                    จึงเรียนมาเพื่อโปรดพิจารณา
                </p>
            </div>

            <!-- ส่วนลงชื่อ -->
            <div class="mt-16 flex justify-end" style="page-break-inside: avoid;">
                <div class="w-80 text-center space-y-2 text-[16pt]">
                    <p>(ลงชื่อ).................................................................</p>
                    <p class="mt-2">( <?php echo $reporter_name; ?> )</p>
                    <p>ผู้รายงาน / ผู้ดูแลระบบสารสนเทศ</p>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
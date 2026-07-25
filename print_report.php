<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนดูรายงาน");
}

// ฟังก์ชันแปลงเลขเป็นเลขไทย
function thaiNum($num) {
    return str_replace(
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        array('๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'),
        $num
    );
}

// รับค่าตัวกรองช่าง
$filter_tech = isset($_GET['tech']) ? trim($_GET['tech']) : 'all';

// กำหนดเงื่อนไขดึงข้อมูล
if ($filter_tech !== 'all' && !empty($filter_tech)) {
    $stmt = $conn->prepare("SELECT status, equipment_type FROM repairs WHERE technician_name = ?");
    $stmt->bind_param("s", $filter_tech);
    $stmt->execute();
    $rep_res = $stmt->get_result();
    $report_title = "รายงานสรุปผลการปฏิบัติงานรายบุคคล (ช่าง: " . htmlspecialchars($filter_tech) . ")";
    $sign_role = "ผู้รับผิดชอบงานซ่อม";
    $doc_purpose = "ข้อมูลดังกล่าวสามารถนำไปใช้เป็นหลักฐานประกอบการประเมินผลการปฏิบัติงาน และกำหนดแนวทางการบำรุงรักษาในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น";
} else {
    $rep_res = $conn->query("SELECT status, equipment_type FROM repairs");
    $report_title = "รายงานสรุปผลการปฏิบัติงานระบบแจ้งซ่อมออนไลน์ (MBS REPAIR)";
    $sign_role = "ผู้รายงาน / ผู้จัดทำ";
    $doc_purpose = "ข้อมูลดังกล่าวสามารถนำไปใช้วางแผนการจัดซื้อวัสดุอุปกรณ์สำรอง และกำหนดแนวทางการบำรุงรักษาเชิงป้องกัน (Preventive Maintenance) ในภาคการศึกษาถัดไปให้มีประสิทธิภาพมากยิ่งขึ้น";
}

$pending = 0; $progress = 0; $completed = 0;
$equip_counts = [];

if ($rep_res) {
    while ($r = $rep_res->fetch_assoc()) {
        if ($r['status'] == 'รอรับเรื่อง') $pending++;
        elseif ($r['status'] == 'กำลังดำเนินการ') $progress++;
        elseif ($r['status'] == 'ซ่อมเสร็จแล้ว') $completed++;

        if (!empty($r['equipment_type'])) {
            if (isset($equip_counts[$r['equipment_type']])) {
                $equip_counts[$r['equipment_type']]++;
            } else {
                $equip_counts[$r['equipment_type']] = 1;
            }
        }
    }
}

$total_repairs = $pending + $progress + $completed;
$pct_completed = $total_repairs > 0 ? number_format(($completed / $total_repairs) * 100, 2) : 0;
$pct_progress = $total_repairs > 0 ? number_format(($progress / $total_repairs) * 100, 2) : 0;
$pct_pending = $total_repairs > 0 ? number_format(($pending / $total_repairs) * 100, 2) : 0;

arsort($equip_counts);
$top_equip = array_slice($equip_counts, 0, 5, true);

// ข้อมูลวันที่
$thai_months = [
    "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน",
    "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม",
    "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
];
$report_month = $thai_months[date('m')];
$current_date_thai = thaiNum(date('j')) . " " . $report_month . " " . thaiNum(date('Y') + 543);

// ชื่อผู้รายงาน (ถ้าไม่มีข้อมูลในระบบ จะใช้ชื่อนางสาวมัทนา รัตนแสง เป็นค่าเริ่มต้นตามบริบทผู้ทำโปรเจกต์)
$reporter_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'นางสาวมัทนา รัตนแสง';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>พิมพ์บันทึกข้อความ - MBS REPAIR</title>
    <style>
        /* 1. นำเข้าฟอนต์ราชการ TH Sarabun New */
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

        /* 2. ตั้งค่าพื้นฐานสำหรับหน้าจอและหน้ากระดาษ */
        @page {
            size: A4;
            margin: 0; /* กำหนด margin ในคลาส .a4-paper แทนเพื่อให้ขอบชัดเจน */
        }

        body {
            font-family: 'THSarabunNew', sans-serif;
            background-color: #cbd5e1; /* สีพื้นหลังตอนดูบนเว็บ */
            margin: 0;
            padding: 20px 0;
            display: flex;
            justify-content: center;
        }

        /* 3. โครงสร้างกระดาษ A4 มาตรฐาน */
        .a4-paper {
            background-color: white;
            width: 210mm;
            min-height: 297mm;
            box-sizing: border-box;
            /* ระยะกั้นหน้ามาตรฐาน: บน 1.5cm(เผื่อตราครุฑ) ขวา 2cm ล่าง 2cm ซ้าย 3cm */
            padding: 1.5cm 2cm 2cm 3cm; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
        }

        /* 4. ตราครุฑและหัวเรื่อง */
        .garuda {
            position: absolute;
            top: 1.5cm;
            left: 3cm;
            width: 1.5cm;
            height: auto;
            object-fit: contain;
        }

        .doc-title {
            font-size: 29pt;
            font-weight: bold;
            text-align: center;
            margin: 0;
            padding-top: 1cm; /* ดันคำว่าบันทึกข้อความให้ลงมาอยู่ระดับเดียวกับครุฑ */
            line-height: 1;
        }

        /* 5. การจัดเลย์เอาต์หัวเอกสารด้วย Table (ป้องกันข้อความหดเบียดกัน 100%) */
        table.header-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 16pt;
        }
        table.header-table td {
            vertical-align: top;
            line-height: 1.2;
            padding-bottom: 5px;
        }
        .col-label {
            font-weight: bold;
            white-space: nowrap;
            width: 1%; /* บังคับให้คอลัมน์ชื่อหัวข้อแคบที่สุด */
            padding-right: 15px;
        }

        /* 6. การจัดรูปแบบเนื้อหา */
        .content {
            margin-top: 15px;
            font-size: 16pt;
        }
        .content p {
            text-align: justify;
            text-justify: inter-word;
            margin: 0 0 5px 0;
            line-height: 1.15;
        }
        .indent {
            text-indent: 2.5cm; /* ย่อหน้ามาตรฐาน 2.5 เซนติเมตร */
        }
        .sub-indent {
            padding-left: 2.5cm; /* สำหรับหัวข้อย่อย */
        }
        .bold {
            font-weight: bold;
        }

        /* 7. ลายเซ็น */
        .signature-section {
            margin-top: 60px;
            margin-left: 50%; /* เริ่มต้นที่กึ่งกลางหน้ากระดาษ */
            text-align: center;
            page-break-inside: avoid;
        }
        .signature-section p {
            margin: 0;
            line-height: 1.2;
            font-size: 16pt;
        }

        /* 8. ซ่อนปุ่มต่างๆ ตอนปริ้นท์ */
        .print-toolbar {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .btn-print {
            background: #2563eb; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; font-family: sans-serif; font-weight: bold;
        }
        .btn-print:hover { background: #1d4ed8; }

        @media print {
            body { background: none; padding: 0; margin: 0; display: block; }
            .a4-paper { box-shadow: none; margin: 0; padding: 1.5cm 2cm 2cm 3cm; width: 100%; border: none; }
            .print-toolbar { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="print-toolbar no-print">
        <button class="btn-print" onclick="window.print();">🖨️ พิมพ์เอกสาร</button>
    </div>

    <!-- กระดาษ A4 -->
    <div class="a4-paper">
        
        <!-- รูปครุฑดึงจากโฟลเดอร์ uploads ที่ผู้ใช้เตรียมไว้ -->
        <img src="uploads/garuda.png" alt="ครุฑ" class="garuda">
        
        <div class="doc-title">บันทึกข้อความ</div>
        
        <!-- ส่วนหัวของบันทึกข้อความ ใช้ Table ป้องกันข้อความซ้อนทับ -->
        <table class="header-table">
            <tr>
                <td class="col-label">ส่วนราชการ</td>
                <td>ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</td>
            </tr>
        </table>
        
        <table class="header-table">
            <tr>
                <td class="col-label">ที่</td>
                <td style="width: 50%;">ศธ ๐๕๓๐.๑๑/......................</td>
                <td class="col-label">วันที่</td>
                <td><?php echo $current_date_thai; ?></td>
            </tr>
        </table>
        
        <table class="header-table">
            <tr>
                <td class="col-label">เรื่อง</td>
                <td><?php echo $report_title; ?> ประจำเดือน <?php echo $report_month; ?></td>
            </tr>
            <tr>
                <td class="col-label">เรียน</td>
                <td>คณบดีคณะการบัญชีและการจัดการ / หัวหน้าฝ่ายเทคโนโลยีสารสนเทศ</td>
            </tr>
        </table>

        <!-- เนื้อหาเอกสาร -->
        <div class="content">
            <p class="indent">ด้วย ฝ่ายเทคโนโลยีสารสนเทศ คณะการบัญชีและการจัดการ ได้ดำเนินการเปิดรับแจ้งซ่อมและบำรุงรักษาอุปกรณ์คอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า และอาคารสถานที่ ผ่านระบบแจ้งซ่อมออนไลน์ (MBS REPAIR) นั้น</p>
            <p class="indent">ในการนี้ ทางผู้ดูแลระบบได้รวบรวมข้อมูลสถิติการปฏิบัติงาน ประจำเดือน <?php echo $report_month; ?> เพื่อรายงานผลการดำเนินงานให้รับทราบ โดยมีรายละเอียดดังต่อไปนี้</p>
            
            <div style="page-break-inside: avoid; margin-top: 10px;">
                <p class="bold">๑. สรุปภาพรวมสถานะการดำเนินงาน</p>
                <p class="indent">มีจำนวนการแจ้งซ่อมในระบบทั้งสิ้น <span class="bold"><?php echo thaiNum($total_repairs); ?></span> รายการ โดยแบ่งตามสถานะการดำเนินงาน ดังนี้</p>
                <div class="sub-indent">
                    <p>๑.๑ ดำเนินการซ่อมแซมเสร็จสิ้นแล้ว จำนวน <span class="bold"><?php echo thaiNum($completed); ?></span> รายการ (คิดเป็นร้อยละ <?php echo thaiNum($pct_completed); ?>)</p>
                    <p>๑.๒ อยู่ระหว่างดำเนินการ จำนวน <span class="bold"><?php echo thaiNum($progress); ?></span> รายการ (คิดเป็นร้อยละ <?php echo thaiNum($pct_progress); ?>)</p>
                    <p>๑.๓ รอดำเนินการ/รอรับเรื่อง จำนวน <span class="bold"><?php echo thaiNum($pending); ?></span> รายการ (คิดเป็นร้อยละ <?php echo thaiNum($pct_pending); ?>)</p>
                </div>
            </div>

            <div style="page-break-inside: avoid; margin-top: 10px;">
                <p class="bold">๒. สถิติอุปกรณ์ที่พบปัญหาความชำรุดบกพร่องสูงสุด</p>
                <p class="indent">ข้อมูลประเภทครุภัณฑ์และอุปกรณ์ที่มีสถิติการแจ้งซ่อมสูงสุด ประกอบด้วย</p>
                <div class="sub-indent">
                    <?php 
                        if (!empty($top_equip)) {
                            $thai_nums = ['๑', '๒', '๓', '๔', '๕'];
                            $i = 0;
                            foreach ($top_equip as $eq_name => $count) {
                                echo "<p>๒." . $thai_nums[$i] . " " . htmlspecialchars($eq_name) . " จำนวน <span class='bold'>" . thaiNum($count) . "</span> รายการ</p>";
                                $i++;
                            }
                        } else {
                            echo "<p>- ไม่มีข้อมูลการแจ้งซ่อมในระบบ -</p>";
                        }
                    ?>
                </div>
            </div>

            <p class="indent" style="margin-top: 10px; page-break-inside: avoid;"><?php echo $doc_purpose; ?></p>
            <p class="indent" style="margin-top: 10px; page-break-inside: avoid;">จึงเรียนมาเพื่อโปรดทราบ</p>
        </div>

        <!-- ส่วนลายเซ็น (จัดชิดขวา เริ่มจากกึ่งกลางหน้ากระดาษ) -->
        <div class="signature-section">
            <p style="margin-bottom: 30px;">(ลงชื่อ)...........................................................</p>
            <p>( <?php echo $reporter_name; ?> )</p>
            <p><?php echo $sign_role; ?></p>
        </div>

    </div>
    
    <script>
        // ให้หน้าต่าง Print ทำงานอัตโนมัติเมื่อเอกสารและฟอนต์พร้อม
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
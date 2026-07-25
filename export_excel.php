<?php
session_start();
include 'db_connect.php'; // ตรวจสอบให้แน่ใจว่าชื่อไฟล์เชื่อมต่อฐานข้อมูลตรงกันนะคะ

// 1. รับค่าชื่อช่างที่ส่งมาจากการเลือก Dropdown ในหน้า Dashboard
$filter_tech = isset($_GET['tech']) ? trim($_GET['tech']) : 'all';

// 2. ตั้งชื่อไฟล์อิงตามข้อมูลที่ออกรายงาน
$filename_suffix = ($filter_tech === 'all' || empty($filter_tech)) ? "All_Overview" : "Tech_" . preg_replace('/[^a-zA-Z0-9ก-๙]/u', '', $filter_tech);
$filename = "MBSRepair_LogBook_" . $filename_suffix . "_" . date('Ymd') . ".xls";

// 3. ตั้งค่า Header เพื่อบังคับให้เบราว์เซอร์ดาวน์โหลดเป็นไฟล์ Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// พิมพ์ BOM (Byte Order Mark) เพื่อให้ Excel อ่านภาษาไทย (UTF-8) ได้ถูกต้องเป๊ะๆ
echo "\xEF\xBB\xBF";

// 4. สร้างคำสั่ง SQL ดึงข้อมูลตามเงื่อนไขการกรอง
if ($filter_tech !== 'all' && !empty($filter_tech)) {
    // กรณีดูแบบ "รายบุคคล"
    $stmt = $conn->prepare("SELECT * FROM repairs WHERE technician_name = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $filter_tech);
    $stmt->execute();
    $result = $stmt->get_result();
    $report_title_suffix = "(เฉพาะผลงานของ: " . htmlspecialchars($filter_tech) . ")";
} else {
    // กรณีดูแบบ "ภาพรวมทั้งหมด"
    $sql = "SELECT * FROM repairs ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $report_title_suffix = "(ภาพรวมระบบทั้งหมด)";
}

// 5. เตรียมข้อมูลส่วนหัวรายงาน
$thai_months = [
    "01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน",
    "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม",
    "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"
];
$current_month = $thai_months[date('m')] . " " . (date('Y') + 543);
$current_date = date('d/m/') . (date('Y') + 543);
$print_by = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : "ผู้ดูแลระบบ";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        /* สไตล์ตารางสำหรับ Excel ให้ดูเป็นระเบียบ */
        table { border-collapse: collapse; font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 12pt; }
        th, td { border: 1px solid #000000; padding: 5px 10px; vertical-align: middle; }
        .header-title { font-size: 16pt; font-weight: bold; background-color: #0f172a; color: #ffffff; text-align: center; }
        .sub-header { font-size: 12pt; font-weight: bold; background-color: #e2e8f0; text-align: left; }
        .table-head { background-color: #3b82f6; color: #ffffff; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        /* สีกำกับสถานะงาน (เพื่อให้ดูง่ายเวลาอยู่บน Excel) */
        .status-pending { color: #d97706; font-weight: bold; } 
        .status-progress { color: #2563eb; font-weight: bold; } 
        .status-success { color: #16a34a; font-weight: bold; } 
    </style>
</head>
<body>

    <table>
        <!-- ================= ส่วนหัวรายงาน (Header) ================= -->
        <tr>
            <td colspan="9" class="header-title">รายงานทะเบียนประวัติการแจ้งซ่อม (Log Book) ผ่านระบบ MBS REPAIR</td>
        </tr>
        <tr>
            <td colspan="9" class="sub-header">หน่วยงาน: คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</td>
        </tr>
        <tr>
            <td colspan="9" class="sub-header">ขอบเขตการรายงาน: <?php echo $report_title_suffix; ?></td>
        </tr>
        <tr>
            <td colspan="3"><b>ข้อมูลประจำเดือน:</b> <?php echo $current_month; ?></td>
            <td colspan="3"><b>วันที่พิมพ์รายงาน:</b> <?php echo $current_date; ?></td>
            <td colspan="3"><b>ผู้พิมพ์รายงาน:</b> <?php echo $print_by; ?></td>
        </tr>
        <tr>
            <td colspan="9"></td> <!-- แถวว่างเว้นระยะ -->
        </tr>

        <!-- ================= ส่วนหัวตารางข้อมูล (Table Head) ================= -->
        <tr>
            <th class="table-head">ลำดับ</th>
            <th class="table-head">เลขที่ใบงาน</th>
            <th class="table-head">วัน/เวลาที่แจ้ง</th>
            <th class="table-head">หมวดหมู่/อุปกรณ์</th>
            <th class="table-head">ผู้แจ้ง</th>
            <th class="table-head">สถานะงาน</th>
            <th class="table-head">ช่างผู้ดำเนินการ</th>
            <th class="table-head">วัน/เวลาที่อัปเดตล่าสุด</th>
            <th class="table-head">หมายเหตุจากช่าง</th>
        </tr>

        <!-- ================= ส่วนข้อมูล (Data Rows) ================= -->
        <?php 
        if ($result && $result->num_rows > 0) {
            $i = 1;
            while($row = $result->fetch_assoc()) { 
                
                // จัดรูปแบบวันที่
                $created_date = date("d/m/Y H:i", strtotime($row['created_at']));
                // สมมติว่ามีฟิลด์ updated_at ถ้าไม่มีให้ใช้ created_at แทน
                $updated_date = isset($row['updated_at']) ? date("d/m/Y H:i", strtotime($row['updated_at'])) : "-";
                
                // จัดคลาสสีตามสถานะ
                $status_class = "";
                if($row['status'] == 'รอรับเรื่อง') $status_class = "status-pending";
                elseif($row['status'] == 'กำลังดำเนินการ') $status_class = "status-progress";
                elseif($row['status'] == 'ซ่อมเสร็จแล้ว') $status_class = "status-success";

                // ตรวจสอบค่าว่างของช่างและหมายเหตุ
                $tech_name = !empty($row['technician_name']) ? $row['technician_name'] : "-";
                $note = !empty($row['repair_note']) ? $row['repair_note'] : "-";
        ?>
            <tr>
                <td class="text-center"><?php echo $i++; ?></td>
                <td class="text-center" style="mso-number-format:'\@';"><?php echo $row['ticket_no']; ?></td>
                <td class="text-center"><?php echo $created_date; ?></td>
                <td class="text-left"><?php echo $row['equipment_type']; ?></td>
                <td class="text-left"><?php echo $row['reporter_name']; ?></td>
                <td class="text-center <?php echo $status_class; ?>"><?php echo $row['status']; ?></td>
                <td class="text-center"><?php echo $tech_name; ?></td>
                <td class="text-center"><?php echo $updated_date; ?></td>
                <td class="text-left"><?php echo $note; ?></td>
            </tr>
        <?php 
            } 
        } else {
        ?>
            <tr>
                <td colspan="9" class="text-center" style="padding: 20px; color: #666; font-weight: bold;">
                    ไม่มีประวัติการแจ้งซ่อมในระบบ หรือไม่มีข้อมูลการซ่อมตามเงื่อนไขที่เลือก
                </td>
            </tr>
        <?php } ?>
    </table>

</body>
</html>
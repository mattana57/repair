<?php
session_start();
include 'db_connect.php'; // ตรวจสอบชื่อไฟล์เชื่อมต่อฐานข้อมูลให้ตรงกับของคุณน้ำฝนนะคะ

// =================================================================
// 1. ตั้งค่า Header เพื่อบังคับให้เบราว์เซอร์ดาวน์โหลดเป็นไฟล์ Excel
// =================================================================
$filename = "MBSRepair_LogBook_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// พิมพ์ BOM (Byte Order Mark) เพื่อให้ Excel อ่านภาษาไทย (UTF-8) ได้ถูกต้องเป๊ะๆ ไม่เป็นภาษาต่างดาว
echo "\xEF\xBB\xBF";

// =================================================================
// 2. ดึงข้อมูลการแจ้งซ่อมจากฐานข้อมูล
// =================================================================
$sql = "SELECT * FROM repairs ORDER BY created_at DESC";
$result = $conn->query($sql);

// เดือนภาษาไทยสำหรับแสดงในหัวรายงาน
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
        /* สไตล์สำหรับ Excel */
        table { border-collapse: collapse; font-family: 'Tahoma', sans-serif; font-size: 11pt; }
        th, td { border: 1px solid #dddddd; padding: 6px 10px; vertical-align: middle; }
        .header-title { font-size: 16pt; font-weight: bold; background-color: #0f172a; color: #ffffff; text-align: center; }
        .sub-header { font-size: 12pt; font-weight: bold; background-color: #f8fafc; text-align: left; }
        .table-head { background-color: #3b82f6; color: #ffffff; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        /* สีสถานะ */
        .status-pending { color: #d97706; font-weight: bold; } /* รอรับเรื่อง */
        .status-progress { color: #2563eb; font-weight: bold; } /* กำลังดำเนินการ */
        .status-success { color: #16a34a; font-weight: bold; } /* ซ่อมเสร็จแล้ว */
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
            <td colspan="3"><b>ข้อมูลประจำเดือน:</b> <?php echo $current_month; ?></td>
            <td colspan="3"><b>วันที่ออกรายงาน:</b> <?php echo $current_date; ?></td>
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
                // สมมติว่ามีฟิลด์ updated_at ถ้าไม่มีให้ใช้ created_at แทนได้ค่ะ
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
                <td colspan="9" class="text-center" style="padding: 20px; color: #94a3b8;">ไม่มีประวัติการแจ้งซ่อมในระบบ</td>
            </tr>
        <?php } ?>
    </table>

</body>
</html>
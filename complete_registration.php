<?php
// ยกเลิกหน้าขอ OTP สำหรับลงทะเบียน ไม่รับหรือบันทึกข้อมูลจากฟอร์มเดิม
header('Cache-Control: no-store');
header('Location: register.php', true, 303);
exit();

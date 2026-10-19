<?php
// ปิดหน้าที่เพิ่มเกินคำขอ ไม่รับหรือบันทึกข้อมูลบัญชีจาก URL เดิม
header('Cache-Control: no-store');
header('Location: login.php', true, 303);
exit();

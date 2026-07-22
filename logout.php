<?php
// 1. เริ่มต้นระบบ Session เพื่อให้ตัวหน้าเว็บรู้จัก Session ของผู้ใช้ที่กำลังล็อกอินอยู่
session_start();

// 2. ล้างค่าข้อมูลทั้งหมดที่เก็บอยู่ใน Session (เช่น user_id, role, full_name) ให้ว่างเปล่า
session_unset(); 

// 3. ทำลาย Session ทิ้งอย่างสมบูรณ์เพื่อตัดการเชื่อมต่อออกจากระบบ
session_destroy(); 

// 4. เด้งผู้ใช้งานกลับไปที่หน้าเข้าสู่ระบบ (หน้าแรก)
header("Location: index.php");
exit();
?>
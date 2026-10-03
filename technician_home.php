<?php
// เรียกใช้ไฟล์ตรวจสิทธิ์
require_once 'auth_guard.php';

// ป้องกัน Admin และ Executive เข้ามาหน้านี้ (เตะกลับไป Dashboard ที่ถูกต้อง)
$role_lower = strtolower($_SESSION['role']);
if ($role_lower === 'admin' || $role_lower === 'executive') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลักช่าง | MBS Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4 selection:bg-indigo-200">
    
    <div class="w-full max-w-md bg-white rounded-3xl p-8 shadow-xl text-center border border-slate-100">
        <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
            <i class="fas fa-tools text-3xl text-indigo-500"></i>
        </div>
        
        <h2 class="text-2xl font-bold text-slate-800 mb-2">ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
        
        <div class="bg-slate-50 border border-slate-100 p-4 rounded-xl mt-4 mb-8">
            <p class="text-slate-500 font-medium leading-relaxed text-sm">
                กรุณาเปิดหน้าจัดการใบงาน หรือแจ้งผลการซ่อม <br>
                <span class="text-indigo-600 font-bold mt-1 inline-block">จากปุ่มเมนูในแชท LINE (MBS Repair)</span>
            </p>
        </div>
        
        <div class="space-y-3">
            <a href="logout.php" class="block w-full bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 px-4 py-3.5 rounded-xl font-bold transition-all shadow-sm">
                <i class="fas fa-sign-out-alt mr-2"></i> ออกจากระบบ
            </a>
        </div>
    </div>

</body>
</html>
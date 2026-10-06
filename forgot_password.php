<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($username) || empty($phone) || empty($new_password)) {
        $error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } else {
        // ✨ ปิดช่องทางเปลี่ยนรหัสผ่านด้วย Username/เบอร์โทรทันที เพื่อความปลอดภัย ✨
        // ระบบจะถูกอัปเกรดไปใช้ระบบยืนยันตัวตนผ่าน OTP ทางอีเมลในอนาคต (ตามที่ฝั่งผู้พัฒนา Dashboard กำหนด)
        $error = "ระบบตั้งรหัสผ่านใหม่ด้วยเบอร์โทรถูกระงับเพื่อความปลอดภัย กรุณาติดต่อ Admin เพื่อรีเซ็ตรหัสผ่านครับ";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน | MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Kanit', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl p-8 shadow-xl border border-slate-100">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">ลืมรหัสผ่าน</h2>
            <p class="text-slate-500 text-sm mt-1">ยืนยันตัวตนเพื่อตั้งรหัสผ่านใหม่</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-500 p-3 rounded-xl text-sm font-bold mb-4 text-center border border-rose-100">
                <i class="fas fa-exclamation-circle mr-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl text-sm font-bold mb-4 text-center border border-emerald-100">
                <i class="fas fa-check-circle mr-1"></i> <?php echo $success; ?>
            </div>
            <a href="login.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center px-4 py-3 rounded-xl font-bold transition-all mt-4">กลับไปหน้าเข้าสู่ระบบ</a>
        <?php else: ?>
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ชื่อผู้ใช้งาน (Username) เดิม</label>
                    <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">เบอร์โทรศัพท์ที่ลงทะเบียนไว้</label>
                    <input type="text" name="phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">รหัสผ่านใหม่ (New Password)</label>
                    <div class="relative">
                        <input type="password" name="new_password" id="new_password" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                        <button type="button" onclick="togglePassword('new_password', 'eyeIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-amber-500 transition-colors">
                            <i id="eyeIcon" class="fas fa-eye-slash text-sm"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white px-4 py-3 rounded-xl font-bold transition-all shadow-md mt-6">ตั้งรหัสผ่านใหม่</button>
            </form>
            <div class="text-center mt-6">
                <a href="login.php" class="text-sm text-slate-500 hover:text-indigo-600 font-bold transition-colors">จำรหัสผ่านได้แล้ว? เข้าสู่ระบบ</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
        function togglePassword(inputId, iconId) {
            var x = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            if (x.type === "password") {
                x.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                x.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            }
        }
    </script>
</body>
</html>
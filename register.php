<?php
session_start();
require_once 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($password) || empty($full_name) || empty($phone)) {
        $error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    } else {
        // ตรวจสอบ Username ซ้ำ
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "ชื่อผู้ใช้งาน (Username) นี้มีผู้ใช้แล้ว กรุณาตั้งใหม่";
        } else {
            // สมัครสมาชิกเป็น Technician เบื้องต้น (แอดมินค่อยไปผูกชื่อทีหลัง)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Technician';
            $is_active = 1;
            $auth_version = 1;

            $stmt_insert = $conn->prepare("INSERT INTO users (username, password, full_name, phone, role, is_active, auth_version) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssssii", $username, $hashed_password, $full_name, $phone, $role, $is_active, $auth_version);
            if ($stmt_insert->execute()) {
                $success = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
            } else {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก | MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Kanit', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl p-8 shadow-xl border border-slate-100">
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800">สมัครสมาชิกเจ้าหน้าที่</h2>
            <p class="text-slate-500 text-sm mt-1">สร้างบัญชีเพื่อเข้าสู่ระบบแจ้งซ่อม</p>
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
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ชื่อผู้ใช้งาน</label>
                    <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">รหัสผ่าน</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-indigo-100 outline-none">
                        <button type="button" onclick="togglePassword('password', 'eyeIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-indigo-600 transition-colors">
                            <i id="eyeIcon" class="fas fa-eye-slash text-sm"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl font-bold transition-all shadow-md mt-6">ยืนยันการสมัครสมาชิก</button>
            </form>
            <div class="text-center mt-6">
                <a href="login.php" class="text-sm text-slate-500 hover:text-indigo-600 font-bold transition-colors">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
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
<?php
session_start();
require_once 'db_connect.php';

// 1. ตรวจสอบว่ามีการล็อกอินอยู่หรือไม่ (ถ้าไม่มี ให้เด้งไปหน้าล็อกอิน)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. ตรวจสอบสถานะบัญชีปัจจุบันจากฐานข้อมูล
$stmt = $conn->prepare("SELECT role, must_change_password, auth_version FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $res->fetch_assoc();
$stmt->close();

// 3. ป้องกันคนแอบพิมพ์ URL เข้ามา: ถ้าบัญชีนี้ไม่ต้องเปลี่ยนรหัสแล้ว ให้เตะกลับไปหน้า Dashboard ตามสิทธิ์ทันที
if ($user['must_change_password'] == 0) {
    if (strtolower($user['role']) === 'executive') {
        header("Location: executive_dashboard.php");
    } elseif (strtolower($user['role']) === 'technician') {
        header("Location: technician_home.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// 4. สร้าง CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// ส่วนประมวลผล Backend (AJAX API)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // ตรวจสอบ CSRF Token
    $csrf_token_post = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token_post)) {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือ Session หมดอายุ กรุณารีเฟรชหน้าเว็บ']);
        exit();
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจสอบความถูกต้องของรหัสผ่าน
    if (strlen($new_password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร']);
        exit();
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน']);
        exit();
    }

    // เข้ารหัสผ่านใหม่
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // เพิ่ม auth_version ขึ้นอีก 1 ขั้น เพื่อป้องกันช่องโหว่ Session เก่า
    $new_auth_version = $user['auth_version'] + 1;

    $conn->begin_transaction();
    try {
        // ✨ ข้อ 2.11: บันทึกรหัสผ่านใหม่, ปลดล็อกบัญชี (must_change_password = 0) และอัปเดตเวอร์ชันความปลอดภัย
        $upd = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0, auth_version = ? WHERE id = ?");
        $upd->bind_param("sii", $hashed_password, $new_auth_version, $user_id);
        $upd->execute();
        
        if ($upd->affected_rows === 0) {
            throw new Exception("ไม่สามารถอัปเดตข้อมูลได้");
        }
        $upd->close();
        
        $conn->commit();
        
        // ✨ สำคัญมาก: ต้องอัปเดต Session auth_version ให้ตรงกับฐานข้อมูลใหม่ ไม่เช่นนั้น auth_guard.php จะเตะผู้ใช้ออกทันทีที่เปลี่ยนหน้า
        $_SESSION['auth_version'] = $new_auth_version;

        // กำหนด URL หน้าแรกตามตำแหน่ง (Role)
        $redirect_url = 'dashboard.php';
        if (strtolower($user['role']) === 'executive') {
            $redirect_url = 'executive_dashboard.php';
        } else if (strtolower($user['role']) === 'technician') {
            $redirect_url = 'technician_home.php';
        }

        echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านส่วนตัวสำเร็จ!', 'redirect' => $redirect_url]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Force Change Password Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บังคับเปลี่ยนรหัสผ่าน - MBS Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-100 relative">
        <!-- ขอบสีตกแต่งด้านบน -->
        <div class="h-2 bg-gradient-to-r from-rose-500 to-indigo-500 w-full absolute top-0 left-0"></div>

        <div class="p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-rose-100 shadow-sm">
                    <i class="fas fa-lock text-rose-500 text-2xl"></i>
                </div>
                <h2 class="text-2xl font-extrabold text-slate-800">ตั้งรหัสผ่านใหม่</h2>
                <p class="text-sm font-medium text-slate-500 mt-2 leading-relaxed">
                    เพื่อความปลอดภัยของบัญชี กรุณาเปลี่ยนรหัสผ่านจากที่แอดมินตั้งให้ <br>เป็น <strong>"รหัสผ่านส่วนตัวของคุณ"</strong> ก่อนเข้าใช้งานระบบ
                </p>
            </div>

            <!-- Form -->
            <form id="forceChangeForm" onsubmit="handleForceChange(event)">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">รหัสผ่านใหม่ (New Password)</label>
                        <div class="relative">
                            <i class="fas fa-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password" id="new_password" required minlength="6" placeholder="รหัสผ่านใหม่อย่างน้อย 6 ตัวอักษร" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-12 py-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-colors font-medium">
                            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 focus:outline-none" onclick="togglePassword('new_password', 'eyeIcon1')">
                                <i id="eyeIcon1" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">ยืนยันรหัสผ่านใหม่ (Confirm Password)</label>
                        <div class="relative">
                            <i class="fas fa-check-circle absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password" id="confirm_password" required minlength="6" placeholder="กรอกรหัสผ่านใหม่อีกครั้งให้ตรงกัน" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-12 py-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-colors font-medium">
                            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 focus:outline-none" onclick="togglePassword('confirm_password', 'eyeIcon2')">
                                <i id="eyeIcon2" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <button type="submit" id="btnSubmit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 px-4 rounded-xl transition-colors shadow-md shadow-indigo-200/50 flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i> บันทึกและเข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center border-t border-slate-100 pt-4">
                <a href="logout.php" class="text-sm font-bold text-slate-400 hover:text-rose-500 transition-colors">
                    <i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ (Logout)
                </a>
            </div>

        </div>
    </div>

    <script>
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        function handleForceChange(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            const newPwd = document.getElementById('new_password').value;
            const confPwd = document.getElementById('confirm_password').value;

            if (newPwd.length < 6) {
                Swal.fire('ข้อผิดพลาด', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร', 'error');
                return;
            }

            if (newPwd !== confPwd) {
                Swal.fire('ข้อผิดพลาด', 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน', 'error');
                return;
            }

            // ล็อกปุ่มกันกดซ้ำ
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> กำลังบันทึกข้อมูล...';
            btn.classList.replace('bg-indigo-600', 'bg-indigo-400');

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('new_password', newPwd);
            formData.append('confirm_password', confPwd);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.replace(data.redirect); // พากลับไปหน้าหลักตามตำแหน่ง
                    });
                } else {
                    Swal.fire('ข้อผิดพลาด', data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    btn.classList.replace('bg-indigo-400', 'bg-indigo-600');
                }
            })
            .catch(error => {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
                btn.classList.replace('bg-indigo-400', 'bg-indigo-600');
            });
        }
    </script>
</body>
</html>
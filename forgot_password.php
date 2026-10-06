<?php
session_start();
require_once 'db_connect.php';

// นำเข้าระบบส่งอีเมลที่เตรียมไว้
require_once 'mailer.php';

// ==========================================
// ส่วนประมวลผล Backend (AJAX API)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // 1. ขอรับรหัส OTP
    if ($action === 'request_otp') {
        $username = trim($_POST['username'] ?? '');
        
        // สร้างโทเคนหลอกเพื่อตอบกลับเสมอ ป้องกันการสุ่มเดา Username (Security: No Enumeration)
        $fake_token = bin2hex(random_bytes(32));
        $neutral_message = "หากบัญชีนี้มีสิทธิ์ใช้งานและยืนยันอีเมลแล้ว ระบบจะส่งรหัส OTP ไปยังอีเมลดังกล่าว";

        if (empty($username)) {
            echo json_encode(['status' => 'success', 'token' => $fake_token, 'message' => $neutral_message]);
            exit();
        }

        // ค้นหาบัญชีโดยตรวจสอบอีเมลที่ยืนยันแล้ว
        $stmt = $conn->prepare("
            SELECT u.id, u.role, u.is_active, u.verified_email, u.email_verified_at, u.auth_version, t.email AS tech_email 
            FROM users u 
            LEFT JOIN technicians t ON u.technician_id = t.id 
            WHERE u.username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $is_valid = false;
        $target_email = null;

        // ตรวจสอบเงื่อนไขว่าบัญชีนี้พร้อมสำหรับรีเซ็ตรหัสผ่านหรือไม่
        if ($user && $user['is_active'] == 1 && !empty($user['email_verified_at'])) {
            if ($user['role'] === 'Technician') {
                if (!empty($user['tech_email']) && $user['tech_email'] === $user['verified_email']) {
                    $target_email = $user['tech_email'];
                    $is_valid = true;
                }
            } else {
                if (!empty($user['verified_email'])) {
                    $target_email = $user['verified_email'];
                    $is_valid = true;
                }
            }
        }

        // หากบัญชีถูกต้อง ให้สร้างคำขอจริงและส่งอีเมล
        if ($is_valid && $target_email) {
            // ยกเลิกคำขอรีเซ็ตรหัสผ่านเดิมทั้งหมด
            $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE user_id = {$user['id']} AND purpose = 'password_reset'");

            $otp_code = sprintf("%06d", mt_rand(100000, 999999));
            $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
            $real_token = bin2hex(random_bytes(32));

            $stmt_insert = $conn->prepare("
                INSERT INTO auth_requests (request_token, user_id, purpose, target_email, otp_hash, expires_at, auth_version) 
                VALUES (?, ?, 'password_reset', ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?)
            ");
            $stmt_insert->bind_param("sissi", $real_token, $user['id'], $target_email, $otp_hash, $user['auth_version']);
            
            if ($stmt_insert->execute()) {
                $request_id = $conn->insert_id;
                
                // ส่งอีเมล
                $is_sent = sendOtpEmail($target_email, $otp_code, "รีเซ็ตรหัสผ่าน");
                
                if (!$is_sent) {
                    // หากส่งไม่สำเร็จ ยกเลิกคำขอนี้ทันที และตอบกลับข้อความเดิมเพื่อไม่ให้หลุดข้อมูล
                    $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = $request_id");
                } else {
                    echo json_encode(['status' => 'success', 'token' => $real_token, 'message' => $neutral_message]);
                    exit();
                }
            }
        }

        // ตอบกลับแบบเป็นกลางเสมอ ไม่ว่าจะพบผู้ใช้ ส่งสำเร็จ หรือส่งล้มเหลว
        echo json_encode(['status' => 'success', 'token' => $fake_token, 'message' => $neutral_message]);
        exit();
    }
    
    // 2. ตรวจสอบ OTP และตั้งรหัสผ่านใหม่
    if ($action === 'verify_and_reset') {
        $token = $_POST['token'] ?? '';
        $otp = $_POST['otp'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        if (empty($token) || empty($otp) || empty($new_password)) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT a.id, a.user_id, a.otp_hash, a.failed_attempts, a.expires_at, a.auth_version, u.auth_version AS current_auth_version 
            FROM auth_requests a
            JOIN users u ON a.user_id = u.id
            WHERE a.request_token = ? AND a.purpose = 'password_reset' AND a.is_canceled = 0 AND a.used_at IS NULL
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            echo json_encode(['status' => 'error', 'message' => 'รหัสคำขอไม่ถูกต้องหรือหมดอายุแล้ว กรุณาทำรายการใหม่']);
            exit();
        }

        if (strtotime($request['expires_at']) < time()) {
            $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = {$request['id']}");
            echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณาขอใหม่']);
            exit();
        }

        if ($request['auth_version'] != $request['current_auth_version']) {
            $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = {$request['id']}");
            echo json_encode(['status' => 'error', 'message' => 'ข้อมูลบัญชีถูกเปลี่ยนแปลงระหว่างการทำรายการ กรุณาขอใหม่']);
            exit();
        }

        if ($request['failed_attempts'] >= 5) {
            $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = {$request['id']}");
            echo json_encode(['status' => 'error', 'message' => 'กรอกรหัสผิดเกินกำหนด คำขอนี้ถูกยกเลิกแล้ว']);
            exit();
        }

        if (password_verify($otp, $request['otp_hash'])) {
            // อัปเดตเฉพาะรหัสผ่านเท่านั้น
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?");
            $stmt_update->bind_param("si", $hashed_password, $request['user_id']);
            $stmt_update->execute();
            $stmt_update->close();

            $conn->query("UPDATE auth_requests SET verified_at = NOW(), used_at = NOW() WHERE id = {$request['id']}");

            echo json_encode(['status' => 'success', 'message' => 'ตั้งรหัสผ่านใหม่สำเร็จ! กรุณาเข้าสู่ระบบ']);
        } else {
            $conn->query("UPDATE auth_requests SET failed_attempts = failed_attempts + 1 WHERE id = {$request['id']}");
            $remain = 4 - $request['failed_attempts'];
            echo json_encode(['status' => 'error', 'message' => "รหัส OTP ไม่ถูกต้อง (เหลือโอกาส $remain ครั้ง)"]);
        }
        exit();
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - MBS Repair System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 p-8 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                <i class="fas fa-unlock-alt text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-white">กู้คืนรหัสผ่าน</h2>
            <p class="text-indigo-100 text-sm mt-2">ยืนยันตัวตนผ่านอีเมลของคุณ</p>
        </div>

        <div class="p-8">
            <!-- ฟอร์มขั้นตอนที่ 1: ขอ OTP -->
            <form id="requestForm" onsubmit="handleRequestOtp(event)">
                <div class="mb-5">
                    <label class="block text-sm font-bold text-slate-700 mb-2">ชื่อผู้ใช้งาน (Username)</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all" placeholder="กรอกชื่อผู้ใช้งานของคุณ">
                    </div>
                </div>
                <button type="submit" id="btnRequest" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-colors shadow-md">
                    ขอรหัส OTP ทางอีเมล
                </button>
            </form>

            <!-- ฟอร์มขั้นตอนที่ 2: กรอก OTP และรหัสผ่านใหม่ (ซ่อนไว้ก่อน) -->
            <form id="resetForm" class="hidden" onsubmit="handleResetPassword(event)">
                <input type="hidden" id="reset_token" value="">
                
                <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-5 text-center">
                    <i class="fas fa-envelope-open-text text-indigo-500 text-2xl mb-2"></i>
                    <p id="neutralMessage" class="text-xs text-indigo-700 font-medium leading-relaxed"></p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-slate-700 mb-2">รหัส OTP 6 หลัก</label>
                    <input type="text" id="otp_code" required maxlength="6" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-center text-xl tracking-widest text-slate-800 font-bold focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all" placeholder="------">
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-bold text-slate-700 mb-2">รหัสผ่านใหม่</label>
                    <input type="password" id="new_password" required minlength="6" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all" placeholder="รหัสผ่านใหม่อย่างน้อย 6 ตัวอักษร">
                </div>

                <button type="submit" id="btnReset" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-colors shadow-md">
                    บันทึกรหัสผ่านใหม่
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-sm font-bold text-slate-600 mb-2">เข้าถึงอีเมลไม่ได้ใช่หรือไม่?</p>
                <p class="text-xs text-slate-500 mb-4">หากคุณยังไม่เคยยืนยันอีเมล หรือไม่สามารถเข้าถึงอีเมลเดิมได้ กรุณาติดต่อแอดมินเพื่อขอรหัสผ่านชั่วคราว</p>
                <a href="login.php" class="text-indigo-600 font-bold hover:text-indigo-800 text-sm transition-colors"><i class="fas fa-arrow-left mr-1"></i> กลับไปหน้าเข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

    <script>
        function handleRequestOtp(e) {
            e.preventDefault();
            const btn = document.getElementById('btnRequest');
            const username = document.getElementById('username').value.trim();
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> กำลังตรวจสอบ...';

            const formData = new FormData();
            formData.append('action', 'request_otp');
            formData.append('username', username);

            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                // แสดงหน้าจอถัดไปเสมอ ไม่ว่าจะเจอ User หรือไม่ (ป้องกันการสุ่มเดาบัญชี)
                document.getElementById('requestForm').classList.add('hidden');
                document.getElementById('resetForm').classList.remove('hidden');
                document.getElementById('neutralMessage').innerText = data.message;
                document.getElementById('reset_token').value = data.token;
            })
            .catch(error => {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                btn.disabled = false;
                btn.innerHTML = 'ขอรหัส OTP ทางอีเมล';
            });
        }

        function handleResetPassword(e) {
            e.preventDefault();
            const btn = document.getElementById('btnReset');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> กำลังตรวจสอบ...';

            const formData = new FormData();
            formData.append('action', 'verify_and_reset');
            formData.append('token', document.getElementById('reset_token').value);
            formData.append('otp', document.getElementById('otp_code').value);
            formData.append('new_password', document.getElementById('new_password').value);

            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = 'บันทึกรหัสผ่านใหม่';
                
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: data.message,
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                } else {
                    Swal.fire('ข้อผิดพลาด', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                btn.disabled = false;
                btn.innerHTML = 'บันทึกรหัสผ่านใหม่';
            });
        }
    </script>
</body>
</html>
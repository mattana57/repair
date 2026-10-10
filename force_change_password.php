<?php
require_once 'auth_guard.php';
require_once __DIR__ . '/password_policy.php';

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
if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// ส่วนประมวลผล Backend (AJAX API)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // ตรวจสอบ CSRF Token
    $csrf_token_post = $_POST['csrf_token'] ?? '';
        if (!is_string($csrf_token_post)
        || !is_string($_SESSION['csrf_token'] ?? null)
        || !hash_equals($_SESSION['csrf_token'], $csrf_token_post)) {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือ Session หมดอายุ กรุณารีเฟรชหน้าเว็บ']);
        exit();
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจรหัสผ่านด้วยกติกาส่วนกลางก่อนเริ่มบันทึก
    $password_error = passwordPolicyError($new_password);
    if ($password_error !== null) {
        echo json_encode(['status' => 'error', 'message' => $password_error], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (!is_string($confirm_password)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกการยืนยันรหัสผ่านเป็นข้อความ'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน']);
        exit();
    }

        // เข้ารหัสก่อนล็อกแถว เพื่อลดเวลาที่คำขออื่นต้องรอ
    $transaction_started = false;
    $invalid_session = false;
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        if (!is_string($hashed_password)) {
            throw new RuntimeException('Password hashing failed');
        }

        // ต้องรองรับ Transaction ก่อนบันทึก ไม่สร้างหรือแก้โครงสร้างตาราง
        $engine_result = $conn->query(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('users', 'technicians', 'auth_requests')"
        );
        if (!$engine_result) {
            throw new RuntimeException('Password change table check failed');
        }
        $engines = [];
        while ($engine_row = $engine_result->fetch_assoc()) {
            $engines[$engine_row['TABLE_NAME']] = strtoupper((string) $engine_row['ENGINE']);
        }
        $engine_result->free();
        foreach (['users', 'technicians', 'auth_requests'] as $table_name) {
            if (($engines[$table_name] ?? '') !== 'INNODB') {
                throw new RuntimeException('Password change tables require InnoDB');
            }
        }

        if (!$conn->begin_transaction()) {
            throw new RuntimeException('Password change transaction failed');
        }
        $transaction_started = true;

        // อ่านบัญชีและช่างซ้ำภายใต้ล็อก ห้ามใช้ Session เก่ารับเวอร์ชันใหม่เอง
        $fresh_user = authGuardLoadAccount($conn, $user_id, true);
        if (!authGuardSessionMatches($fresh_user, $_SESSION, time())
            || (int) $fresh_user['must_change_password'] !== 1) {
            $invalid_session = true;
            throw new RuntimeException('Password change session invalid');
        }
        $old_auth_version = filter_var($fresh_user['auth_version'], FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483646]]);
        if ($old_auth_version === false) {
            throw new RuntimeException('Invalid account version');
        }
        $new_auth_version = $old_auth_version + 1;

        $upd = authGuardStatement($conn,
            'UPDATE users
             SET password = ?, must_change_password = 0, auth_version = auth_version + 1
             WHERE id = ? AND is_active = 1 AND auth_version = ? AND must_change_password = 1',
            'sii', [$hashed_password, $user_id, $old_auth_version]);
        if ($upd->affected_rows !== 1) {
            $upd->close();
            throw new RuntimeException('Password change update failed');
        }
        $upd->close();

        // ยกเลิกคำขอเก่าใน Transaction เดียวกับการเปลี่ยนรหัส
        $cancel = authGuardStatement($conn,
            "UPDATE auth_requests SET is_canceled = 1
             WHERE user_id = ? AND purpose IN ('email_verification', 'password_reset')
               AND is_canceled = 0", 'i', [$user_id]);
        $cancel->close();

        if (!$conn->commit()) {
            throw new RuntimeException('Password change commit failed');
        }
        $transaction_started = false;

        // คงพฤติกรรมเดิม: เบราว์เซอร์ที่เปลี่ยนรหัสสำเร็จใช้งานต่อได้
        // อุปกรณ์อื่นยังถือเวอร์ชันเก่า และถูก Guard ปฏิเสธในการร้องขอครั้งถัดไป
        $_SESSION['auth_version'] = $new_auth_version;
        $_SESSION['LAST_ACTIVITY'] = time();
        unset($_SESSION['password_reset_grant'], $_SESSION['otp_request_context']);

        $redirect_url = 'dashboard.php';
        if (strtolower($fresh_user['role']) === 'executive') {
            $redirect_url = 'executive_dashboard.php';
        } elseif (strtolower($fresh_user['role']) === 'technician') {
            $redirect_url = 'technician_home.php';
        }
        echo json_encode(['status' => 'success',
            'message' => 'เปลี่ยนรหัสผ่านส่วนตัวสำเร็จ!', 'redirect' => $redirect_url],
            JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($transaction_started) {
            try { $conn->rollback(); } catch (Throwable $rollback_error) {}
        }
        if ($invalid_session) {
            $_SESSION = [];
            session_destroy();
        }
        error_log('Force password change failed');
        echo json_encode(['status' => 'error',
            'message' => $invalid_session
                ? 'ข้อมูลบัญชีหรือ Session เปลี่ยนไป กรุณาเข้าสู่ระบบใหม่'
                : 'ไม่สามารถบันทึกรหัสผ่านได้ กรุณาลองใหม่ภายหลัง'], JSON_UNESCAPED_UNICODE);
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
                            <input type="password" id="new_password" required autocomplete="new-password" placeholder="กรอกรหัสผ่านตามเงื่อนไขด้านล่าง"
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
                            <input type="password" id="confirm_password" required autocomplete="new-password" placeholder="กรอกรหัสผ่านใหม่อีกครั้งให้ตรงกัน"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-12 py-3 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-colors font-medium">
                            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 focus:outline-none" onclick="togglePassword('confirm_password', 'eyeIcon2')">
                                <i id="eyeIcon2" class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-slate-500 mt-4"><?php echo htmlspecialchars(passwordPolicyHint(), ENT_QUOTES, 'UTF-8'); ?></p>

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

    <script src="password_policy.js"></script>
    <script>
        const sharedPasswordPolicy = <?php echo json_encode(passwordPolicyConfig(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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

            const passwordError = PasswordPolicy.error(newPwd, sharedPasswordPolicy);
            if (passwordError !== null) {
                Swal.fire('ข้อผิดพลาด', passwordError, 'error');
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
<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db_connect.php';
require_once __DIR__ . '/password_policy.php';

// เลือกอีเมลตามบทบาทจากข้อมูลฐานข้อมูลเท่านั้น
function getPasswordResetEmailSource($account) {
    if (!is_array($account)) {
        return null;
    }

    $role = strtolower((string) ($account['role'] ?? ''));

    if ($role === 'technician') {
        $technician_id = filter_var(
            $account['technician_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $found_technician_id = filter_var(
            $account['found_technician_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($technician_id === false
            || $found_technician_id === false
            || $technician_id !== $found_technician_id) {
            return null;
        }

        $email = $account['tech_email'] ?? null;

    } elseif (in_array($role, ['admin', 'executive'], true)) {
        // บัญชีผู้ดูแลต้องไม่ใช้ข้อมูลช่างเป็นช่องทางสำรอง
        if (($account['technician_id'] ?? null) !== null) {
            return null;
        }

        $email = $account['account_email'] ?? null;

    } else {
        return null;
    }

    if (!is_string($email)) {
        return null;
    }

    $email = trim($email);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return $email;
}

// นำเข้าระบบส่งอีเมลมาใช้งาน
require_once 'mailer.php';

// สร้าง CSRF Token สำหรับป้องกันการโจมตีข้ามไซต์
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// ส่วนประมวลผล Backend (AJAX API)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // ตรวจสอบ CSRF Token ทุกครั้งที่มีการส่ง POST (การป้องกันคำขอและการใช้ซ้ำ)
    $csrf_token_post = $_POST['csrf_token'] ?? '';
        if (!is_string($csrf_token_post)
        || !is_string($_SESSION['csrf_token'] ?? null)
        || !hash_equals($_SESSION['csrf_token'], $csrf_token_post)) {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้องหรือ Session หมดอายุ กรุณารีเฟรชหน้าเว็บ']);
        exit();
    }

    $action = $_POST['action'] ?? '';

        // 1. ขอรับรหัส OTP
        if ($action === 'request_otp') {
        unset($_SESSION['password_reset_grant']);
        header('Cache-Control: no-store');

        $fake_token = bin2hex(random_bytes(32));
        $neutral_message = 'หากบัญชีนี้มีสิทธิ์ใช้งานและยืนยันอีเมลแล้ว ระบบจะส่งรหัส OTP ไปยังอีเมลดังกล่าว';

        $response_token = $fake_token;

        // ใช้เวลาแสดงผลแบบเดียวกันทุกบัญชี
        // ไม่เปิดเผยว่าพบบัญชีหรือส่งอีเมลสำเร็จหรือไม่
        $countdown_started_at = microtime(true);
        $countdown_lifetime = 300;

        try {
            require_once __DIR__ . '/otp_request_service.php';

            $countdown_config = otpRequestConfig();
            $countdown_lifetime = (int) $countdown_config['otp_lifetime_seconds'];

            // ส่งค่าดิบให้ Service ตรวจประเภทข้อมูล
            // และนับ IP ก่อนตรวจว่าบัญชีมีอยู่หรือไม่
            $result = otpRequestCreateAndSend(
                $conn,
                'password_reset',
                $_POST['username'] ?? null
            );

            if (($result['status'] ?? '') === 'sent'
                && isset($result['token'])
                && is_string($result['token'])
                && preg_match('/\A[a-f0-9]{64}\z/', $result['token'])) {
                $response_token = $result['token'];
            }

        } catch (Throwable $e) {
            // ไม่บันทึกข้อมูลบัญชี OTP หรือโทเคนลง Log
            error_log('Password reset OTP request failed');
        }

        // หักเวลาที่ใช้ประมวลผลและส่งอีเมลออกแล้ว
        // ตัวนับนี้ไม่ต่ออายุ OTP และเซิร์ฟเวอร์ยังตรวจวันหมดอายุจริง
        $countdown_elapsed = max(
            0,
            (int) ceil(microtime(true) - $countdown_started_at)
        );

        $countdown_remaining = max(
            0,
            $countdown_lifetime - $countdown_elapsed
        );

        // คงคำตอบกลางทุกกรณี รวมถึงเกินโควตาและส่งเมลล้มเหลว
        echo json_encode([
            'status' => 'success',
            'token' => $response_token,
            'message' => $neutral_message,
            'expires_in' => $countdown_remaining,
        ], JSON_UNESCAPED_UNICODE);

        exit();
    }
    
        // ตรวจ OTP และออกสิทธิ์รีเซ็ตเท่านั้น
    if ($action === 'verify_otp') {
        header('Cache-Control: no-store');
        try {
            require_once __DIR__ . '/otp_verification_service.php';
            $result = otpVerificationCheck(
                $conn, 'password_reset',
                $_POST['token'] ?? null, $_POST['otp'] ?? null
            );
        } catch (Throwable $e) {
            error_log('Password reset OTP verification failed');
            $result = ['status' => 'error', 'message' => 'ไม่สามารถตรวจ OTP ได้ กรุณาลองใหม่ภายหลัง'];
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ตั้งรหัสผ่านได้เฉพาะสิทธิ์ที่ออกหลังตรวจ OTP ใน Session เดียวกัน
    if ($action === 'reset_password') {
        header('Cache-Control: no-store');
        try {
            require_once __DIR__ . '/password_reset_service.php';
            $result = passwordResetSave(
                $conn,
                $_POST['reset_grant'] ?? null,
                $_POST['new_password'] ?? null,
                $_POST['confirm_password'] ?? null
            );
        } catch (Throwable $e) {
            error_log('Password reset endpoint failed');
            $result = ['status' => 'error', 'message' => 'ไม่สามารถบันทึกรหัสผ่านได้ กรุณาลองใหม่ภายหลัง'];
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ปิดเส้นทางเดิมที่รวมการตรวจ OTP และตั้งรหัสผ่าน
    if ($action === 'verify_and_reset') {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณาขอ OTP และตรวจรหัสก่อนตั้งรหัสผ่านใหม่'
        ], JSON_UNESCAPED_UNICODE);
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

        #otpRequestAgain[hidden] {
            display: none !important;
        }
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
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <label for="otp_code" class="block text-sm font-bold text-slate-700">
                            รหัส OTP 6 หลัก
                        </label>
                        <span id="otpCountdown" class="text-sm font-bold" style="color: #dc2626;" hidden></span>
                    </div>
                    <input type="text" id="otp_code" required maxlength="6" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-center text-xl tracking-widest text-slate-800 font-bold focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all" placeholder="------">
                    <a id="otpRequestAgain" href="forgot_password.php" class="block mt-3 text-center text-sm font-bold text-indigo-600" hidden>
                        ขอรหัส OTP ใหม่
                    </a>
                </div>

                                <input type="hidden" id="reset_grant" value="">
                <button type="submit" id="btnReset" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-colors shadow-md">
                    ตรวจสอบ OTP
                </button>

            </form>

            <!-- แสดงหลังตรวจ OTP ผ่าน; Endpoint ตรวจสิทธิ์ซ้ำทุกครั้ง -->
            <form id="newPasswordForm" class="hidden" onsubmit="handleSavePassword(event)">
                <p class="text-sm text-indigo-700 mb-2">ตรวจ OTP สำเร็จ กรุณาตั้งรหัสผ่านใหม่ก่อนสิทธิ์หมดอายุ</p>
                <p id="grantCountdown" class="text-sm font-bold mb-5" style="color: #dc2626;" hidden></p>
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-bold text-slate-700 mb-2">รหัสผ่านใหม่</label>
                    <div class="relative">
                        <input type="password" id="new_password" required autocomplete="new-password"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-12 py-3 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <button type="button" onclick="toggleResetPasswordVisibility('new_password', this)"
                            class="absolute right-2 top-0 h-full w-10 flex items-center justify-center rounded-lg text-slate-500 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            aria-controls="new_password" aria-pressed="false" aria-label="แสดงรหัสผ่านใหม่" title="แสดงรหัสผ่านใหม่">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars(passwordPolicyHint(), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="mb-5">
                    <label for="confirm_password" class="block text-sm font-bold text-slate-700 mb-2">ยืนยันรหัสผ่านใหม่</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" required autocomplete="new-password"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-12 py-3 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <button type="button" onclick="toggleResetPasswordVisibility('confirm_password', this)"
                            class="absolute right-2 top-0 h-full w-10 flex items-center justify-center rounded-lg text-slate-500 hover:text-indigo-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            aria-controls="confirm_password" aria-pressed="false" aria-label="แสดงยืนยันรหัสผ่านใหม่" title="แสดงยืนยันรหัสผ่านใหม่">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" id="btnSavePassword" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-colors shadow-md">บันทึกรหัสผ่านใหม่</button>
                <a href="forgot_password.php" class="block mt-4 text-center text-sm font-bold text-indigo-600">ขอ OTP ใหม่</a>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-sm font-bold text-slate-600 mb-2">เข้าถึงอีเมลไม่ได้ใช่หรือไม่?</p>
                <p class="text-xs text-slate-500 mb-4">หากคุณยังไม่เคยยืนยันอีเมล หรือไม่สามารถเข้าถึงอีเมลเดิมได้ กรุณาติดต่อแอดมินเพื่อขอรหัสผ่านชั่วคราว</p>
                <a href="login.php" class="text-indigo-600 font-bold hover:text-indigo-800 text-sm transition-colors"><i class="fas fa-arrow-left mr-1"></i> กลับไปหน้าเข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

    <script src="password_policy.js"></script>
    <script>
        const sharedPasswordPolicy = <?php echo json_encode(passwordPolicyConfig(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // กำหนดตัวแปร CSRF Token สำหรับส่งไปกับ API
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        let countdownStage = '';
        let countdownDeadline = 0;
        let countdownTimer = null;

        function countdownRemaining() {
            return Math.max(
                0,
                Math.ceil((countdownDeadline - Date.now()) / 1000)
            );
        }

        function countdownExpired(stage) {
            return countdownStage === stage
                && countdownRemaining() === 0;
        }

        function stopResetCountdown() {
            if (countdownTimer !== null) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }

            countdownStage = '';
            countdownDeadline = 0;
        }

        function renderResetCountdown() {
            if (countdownStage === '') {
                return;
            }

            const seconds = countdownRemaining();
            const minutesText = String(
                Math.floor(seconds / 60)
            ).padStart(2, '0');
            const secondsText = String(seconds % 60).padStart(2, '0');

            if (countdownStage === 'otp') {
                const label = document.getElementById('otpCountdown');
                label.hidden = false;

                if (seconds > 0) {
                    label.textContent =
                        `เหลือเวลา ${minutesText}:${secondsText}`;
                } else {
                    label.textContent =
                        'รหัส OTP หมดอายุ กรุณาขอรหัสใหม่';

                    document.getElementById('otp_code').disabled = true;
                    document.getElementById('btnReset').disabled = true;
                    document.getElementById('otpRequestAgain').hidden = false;
                }
            }

            if (countdownStage === 'grant') {
                const label = document.getElementById('grantCountdown');
                label.hidden = false;

                if (seconds > 0) {
                    label.textContent =
                        `เหลือเวลาตั้งรหัสผ่าน ${minutesText}:${secondsText}`;
                } else {
                    label.textContent =
                        'สิทธิ์ตั้งรหัสผ่านหมดอายุ กรุณาขอรหัส OTP ใหม่';

                    document.getElementById('new_password').disabled = true;
                    document.getElementById('confirm_password').disabled = true;
                    document.getElementById('btnSavePassword').disabled = true;
                }
            }

            if (seconds === 0 && countdownTimer !== null) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }

        function startResetCountdown(stage, seconds) {
            if (!Number.isSafeInteger(seconds) || seconds < 0) {
                throw new Error('Invalid countdown duration');
            }

            stopResetCountdown();

            countdownStage = stage;
            countdownDeadline = Date.now() + seconds * 1000;
            countdownTimer = setInterval(renderResetCountdown, 1000);

            renderResetCountdown();
        }

        document.addEventListener(
            'visibilitychange',
            renderResetCountdown
        );

        function handleRequestOtp(e) {
            e.preventDefault();
            const btn = document.getElementById('btnRequest');
            const username = document.getElementById('username').value.trim();
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> กำลังตรวจสอบ...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'request_otp');
            formData.append('username', username);

            // ... (โค้ด fetch ปล่อยไว้ตามเดิม)
            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success'
                    || typeof data.token !== 'string'
                    || !Number.isSafeInteger(data.expires_in)
                    || data.expires_in < 0) {
                    throw new Error('request failed');
                }

                document.getElementById('reset_grant').value = '';
                document.getElementById('requestForm').classList.add('hidden');
                document.getElementById('resetForm').classList.remove('hidden');
                document.getElementById('neutralMessage').innerText = data.message;
                document.getElementById('reset_token').value = data.token;

                document.getElementById('otp_code').disabled = false;
                document.getElementById('btnReset').disabled = false;
                document.getElementById('otpRequestAgain').hidden = true;

                startResetCountdown('otp', data.expires_in);
            })
            .catch(error => {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                btn.disabled = false;
                btn.innerHTML = 'ขอรหัส OTP ทางอีเมล';
            });
        }

        function handleResetPassword(e) {
            e.preventDefault();

            if (countdownExpired('otp')) {
                renderResetCountdown();
                Swal.fire(
                    'รหัส OTP หมดอายุ',
                    'กรุณาขอรหัส OTP ใหม่',
                    'error'
                );
                return;
            }

            const btn = document.getElementById('btnReset');

            if (btn.disabled) {
                return;
            }

            btn.disabled = true;
            btn.innerText = 'กำลังตรวจสอบ...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'verify_otp');
            formData.append(
                'token',
                document.getElementById('reset_token').value
            );
            formData.append(
                'otp',
                document.getElementById('otp_code').value
            );

            fetch('', {
                method: 'POST',
                body: formData,
                cache: 'no-store'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('verification failed');
                }

                return response.json();
            })
            .then(data => {
                if (data.status === 'success'
                    && typeof data.reset_grant === 'string'
                    && Number.isSafeInteger(data.expires_in)
                    && data.expires_in >= 0) {
                    document.getElementById('reset_grant').value =
                        data.reset_grant;

                    document.getElementById('otp_code').value = '';
                    document.getElementById('otp_code').disabled = true;
                    btn.innerText = 'ตรวจ OTP สำเร็จ';

                    document.getElementById('resetForm')
                        .classList.add('hidden');
                    document.getElementById('newPasswordForm')
                        .classList.remove('hidden');

                    startResetCountdown('grant', data.expires_in);

                    if (!countdownExpired('grant')) {
                        document.getElementById('new_password').focus();
                    }
                } else {
                    btn.disabled = countdownExpired('otp');
                    btn.innerText = 'ตรวจสอบ OTP';
                    renderResetCountdown();

                    Swal.fire(
                        'ตรวจ OTP ไม่สำเร็จ',
                        data.message || 'กรุณาลองใหม่',
                        'error'
                    );
                }
            })
            .catch(() => {
                btn.disabled = countdownExpired('otp');
                btn.innerText = 'ตรวจสอบ OTP';
                renderResetCountdown();

                Swal.fire(
                    'ข้อผิดพลาด',
                    'ไม่สามารถตรวจ OTP ได้ กรุณาลองใหม่',
                    'error'
                );
            });
        }

        function handleSavePassword(e) {
            e.preventDefault();

            if (countdownExpired('grant')) {
                renderResetCountdown();
                Swal.fire(
                    'สิทธิ์หมดอายุ',
                    'กรุณาขอรหัส OTP ใหม่ก่อนตั้งรหัสผ่าน',
                    'error'
                );
                return;
            }

            if (document.getElementById('btnSavePassword').disabled) {
                return;
            }

            const password = document.getElementById('new_password').value;
            const confirmation = document.getElementById('confirm_password').value;
            const grant = document.getElementById('reset_grant').value;
                        const passwordError = PasswordPolicy.error(password, sharedPasswordPolicy);
            if (passwordError !== null) {
                Swal.fire('ข้อผิดพลาด', passwordError, 'error');
                return;
            }

            if (password !== confirmation) {
                Swal.fire('ข้อผิดพลาด', 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน', 'error');
                return;
            }
            if (!/^[a-f0-9]{64}$/.test(grant)) {
                Swal.fire('ข้อผิดพลาด', 'กรุณาขอ OTP และตรวจรหัสใหม่', 'error');
                return;
            }
            const btn = document.getElementById('btnSavePassword');
            btn.disabled = true;
            btn.innerText = 'กำลังบันทึก...';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('action', 'reset_password');
            formData.append('reset_grant', grant);
            formData.append('new_password', password);
            formData.append('confirm_password', confirmation);
            fetch('', { method: 'POST', body: formData, cache: 'no-store' })
            .then(response => {
                if (!response.ok) throw new Error('reset failed');
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    stopResetCountdown();
                    document.getElementById('grantCountdown').hidden = true;
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                    document.getElementById('reset_grant').value = '';
                    document.getElementById('reset_token').value = '';
                    document.getElementById('new_password').disabled = true;
                    document.getElementById('confirm_password').disabled = true;
                    Swal.fire('สำเร็จ', data.message, 'success')
                    .then(() => window.location.replace('login.php'));
                } else {
                    btn.disabled = countdownExpired('grant');
                    btn.innerText = 'บันทึกรหัสผ่านใหม่';
                    renderResetCountdown();

                    Swal.fire(
                        'ตั้งรหัสผ่านไม่สำเร็จ',
                        data.message || 'กรุณาลองใหม่',
                        'error'
                    );
                }
            })
            .catch(() => {
                btn.disabled = countdownExpired('grant');
                btn.innerText = 'บันทึกรหัสผ่านใหม่';
                renderResetCountdown();

                Swal.fire(
                    'ข้อผิดพลาด',
                    'ไม่สามารถยืนยันผลการบันทึกได้ กรุณาลองเข้าสู่ระบบด้วยรหัสผ่านใหม่ หากยังไม่ได้ให้ขอ OTP ใหม่',
                    'error'
                );
            });
        }
    </script>
    <script>
        // รูปตาแสดงสถานะปัจจุบัน: ตาเปิด = เห็นรหัส, ตาปิด = ซ่อนรหัส
        function toggleResetPasswordVisibility(inputId, button) {
            if (!['new_password', 'confirm_password'].includes(inputId)) return;
            const input = document.getElementById(inputId);
            if (!input) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            const icon = button.querySelector('i');
            icon.classList.toggle('fa-eye', visible);
            icon.classList.toggle('fa-eye-slash', !visible);
            const fieldLabel = inputId === 'new_password' ? 'รหัสผ่านใหม่' : 'ยืนยันรหัสผ่านใหม่';
            const actionLabel = (visible ? 'ซ่อน' : 'แสดง') + fieldLabel;
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', actionLabel);
            button.title = actionLabel;
        }
    </script>
</body>
</html>
<?php
require_once 'auth_guard.php';

header('Cache-Control: no-store');

$page_role = strtolower((string) ($_SESSION['role'] ?? ''));

if (!in_array($page_role, ['technician', 'admin', 'executive'], true)) {
    http_response_code(403);
    exit('บัญชีนี้ไม่มีสิทธิ์ยืนยันอีเมล');
}

$page_user_id = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($page_user_id === false) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])
    || !is_string($_SESSION['csrf_token'])
    || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_email = '';
$page_verified = false;
$page_error = '';

try {
    $page_stmt = $conn->prepare(
        "SELECT u.role, u.technician_id,
                u.email AS account_email,
                u.verified_email, u.email_verified_at,
                t.id AS found_technician_id, t.email AS tech_email
         FROM users u
         LEFT JOIN technicians t ON t.id = u.technician_id
         WHERE u.id = ?"
    );

    if (!$page_stmt) {
        throw new Exception('ไม่สามารถตรวจข้อมูลอีเมลได้');
    }

    $page_stmt->bind_param('i', $page_user_id);

    if (!$page_stmt->execute()) {
        throw new Exception('ไม่สามารถตรวจข้อมูลอีเมลได้');
    }

    $page_result = $page_stmt->get_result();

    if (!$page_result || $page_result->num_rows !== 1) {
        throw new Exception('ไม่พบข้อมูลบัญชี');
    }

    $page_account = $page_result->fetch_assoc();
    $page_stmt->close();

    if (strtolower((string) $page_account['role']) !== $page_role) {
        throw new Exception('ข้อมูลบัญชีเปลี่ยนไป กรุณาเข้าสู่ระบบใหม่');
    }

    if ($page_role === 'technician') {
        $page_tech_id = filter_var(
            $page_account['technician_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $page_found_id = filter_var(
            $page_account['found_technician_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($page_tech_id === false
            || $page_found_id === false
            || $page_tech_id !== $page_found_id) {
            throw new Exception('ข้อมูลการเชื่อมช่างไม่ถูกต้อง กรุณาติดต่อแอดมิน');
        }

        $page_email_source = $page_account['tech_email'];

    } else {
        if ($page_account['technician_id'] !== null) {
            throw new Exception('ข้อมูลการเชื่อมบัญชีไม่ถูกต้อง กรุณาติดต่อแอดมิน');
        }

        $page_email_source = $page_account['account_email'];
    }

    $page_email = is_string($page_email_source)
        ? trim($page_email_source)
        : '';

    if ($page_email === ''
        || !filter_var($page_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('ยังไม่มีอีเมลที่ถูกต้อง กรุณาติดต่อแอดมินเพื่อแก้ไขข้อมูล');
    }

    $page_verified = $page_account['verified_email'] === $page_email
        && !empty($page_account['email_verified_at']);

} catch (Throwable $e) {
    $page_error = 'ไม่สามารถเปิดการยืนยันอีเมลได้ กรุณาติดต่อแอดมินเพื่อตรวจอีเมลและข้อมูลบัญชี';
}

$page_back_links = [
    'technician' => 'technician_home.php',
    'admin' => 'dashboard.php',
    'executive' => 'executive_dashboard.php'
];

$page_back_url = $page_back_links[$page_role];

function verificationPageEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ยืนยันอีเมล</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        main {
            max-width: 540px;
            margin: 40px auto;
            padding: 28px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 8px 30px #0f172a10;
        }
        h1 { font-size: 26px; margin-top: 0; }
        p { line-height: 1.7; overflow-wrap: anywhere; }
        label { display: block; margin-bottom: 8px; }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 22px;
            letter-spacing: 6px;
        }
        button {
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            background: #4f46e5;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        button:disabled { opacity: .5; cursor: wait; }
        form { margin-top: 24px; }
        form button { margin-top: 12px; }
        a { color: #4338ca; }
        .email {
            padding: 12px;
            border-radius: 10px;
            background: #f8fafc;
        }
        .message { min-height: 28px; }
        .success { color: #15803d; }
        .error { color: #b91c1c; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<main>
    <h1>ยืนยันอีเมล</h1>
    <p>
        ยืนยันอีเมลของบัญชีนี้ก่อนใช้การรีเซ็ตรหัสผ่าน
        ระบบจะส่งรหัสไปยังอีเมลที่บันทึกไว้เท่านั้น
    </p>

    <?php if ($page_error !== ''): ?>
        <p class="error">
            <?= verificationPageEscape($page_error) ?>
        </p>
    <?php else: ?>
        <p class="email">
            <?= verificationPageEscape($page_email) ?>
        </p>

        <?php if ($page_verified): ?>
            <p class="success">อีเมลนี้ได้รับการยืนยันแล้ว</p>
        <?php else: ?>
            <div id="verification-controls">
                <p>
                    หากเข้าอีเมลนี้ไม่ได้ ให้ติดต่อแอดมินตรวจสอบตัวตน
                    และแก้ไขอีเมลก่อน
                </p>

                <button type="button" id="request-button">
                    ส่งรหัสยืนยันอีเมล
                </button>

                <form id="otp-form" hidden>
                    <label for="otp">รหัส OTP จากอีเมล มีอายุ 5 นาที</label>
                    <input
                        id="otp"
                        name="otp"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]{6}"
                        minlength="6"
                        maxlength="6"
                        required
                    >
                    <button type="submit" id="verify-button">
                        ยืนยันอีเมล
                    </button>
                </form>
            </div>

            <p id="message" class="message" role="status" aria-live="polite"></p>
        <?php endif; ?>
    <?php endif; ?>

    <p>
        <a href="<?= verificationPageEscape($page_back_url) ?>">
            กลับหน้าหลัก
        </a>
    </p>
</main>

<?php if ($page_error === '' && !$page_verified): ?>
<script>
'use strict';

const csrfToken = <?= json_encode(
    $_SESSION['csrf_token'],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const requestButton = document.getElementById('request-button');
const verifyButton = document.getElementById('verify-button');
const otpForm = document.getElementById('otp-form');
const otpInput = document.getElementById('otp');
const message = document.getElementById('message');

let requestToken = '';
let busy = false;

function showMessage(text, success = false) {
    message.textContent = text;
    message.className = 'message ' + (success ? 'success' : 'error');
}

function setBusy(value) {
    busy = value;
    requestButton.disabled = value;
    verifyButton.disabled = value;
}

async function callVerification(action, extra = {}) {
    const body = new URLSearchParams({
        action,
        csrf_token: csrfToken,
        ...extra
    });

    const response = await fetch('verify_email.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept': 'application/json'
        },
        body
    });

    if (response.redirected) {
        window.location.assign(response.url);
        return null;
    }

    const contentType = response.headers.get('Content-Type') || '';

    if (!contentType.toLowerCase().includes('application/json')) {
        throw new Error('ไม่สามารถทำรายการได้ กรุณารีเฟรชหน้าและเข้าสู่ระบบใหม่');
    }

    const data = await response.json();

    if (!data || typeof data.status !== 'string') {
        throw new Error('ไม่สามารถอ่านผลการทำรายการได้ กรุณาลองใหม่');
    }

    return data;
}

requestButton.addEventListener('click', async () => {
    if (busy) return;

    setBusy(true);
    requestToken = '';
    otpForm.hidden = true;
    otpInput.value = '';
    showMessage('กำลังส่งรหัสยืนยันอีเมล…');

    try {
        const data = await callVerification('request_otp');

        if (!data) return;

        if (data.status !== 'success'
            || typeof data.token !== 'string'
            || !/^[a-f0-9]{64}$/.test(data.token)) {
            throw new Error(data.message || 'ไม่สามารถส่งรหัสได้');
        }

        requestToken = data.token;
        otpForm.hidden = false;
        showMessage(data.message || 'ส่งรหัสไปยังอีเมลแล้ว', true);
        otpInput.focus();

    } catch (error) {
        showMessage(error.message || 'ไม่สามารถส่งรหัสได้ กรุณาลองใหม่');
    } finally {
        setBusy(false);
    }
});

otpForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (busy) return;

    if (!requestToken || !/^[0-9]{6}$/.test(otpInput.value)) {
        showMessage('กรุณาขอรหัสและกรอก OTP เป็นตัวเลข 6 หลัก');
        return;
    }

    setBusy(true);

    try {
        const data = await callVerification('verify_otp', {
            token: requestToken,
            otp: otpInput.value
        });

        if (!data) return;

        if (data.status !== 'success') {
            throw new Error(data.message || 'ยืนยันอีเมลไม่สำเร็จ');
        }

        requestToken = '';
        otpInput.value = '';
        document.getElementById('verification-controls').hidden = true;
        showMessage(data.message || 'ยืนยันอีเมลสำเร็จแล้ว', true);

    } catch (error) {
        showMessage(error.message || 'ยืนยันอีเมลไม่สำเร็จ กรุณาลองใหม่');
    } finally {
        setBusy(false);
    }
});
</script>
<?php endif; ?>
</body>
</html>
<?php
// ตั้งค่า Session สำหรับ HTTPS (ngrok) และป้องกันข้ามโดเมน แต่รองรับการเปิดผ่าน LINE
$session_params = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 28800, // กำหนดอายุ Session 8 ชั่วโมง
    'path' => '/',
    'domain' => '', // ปล่อยว่างเพื่อรองรับโดเมนปัจจุบันที่เปลี่ยนไปมาของ ngrok
    'secure' => true, // บังคับส่ง Cookie ผ่าน HTTPS เท่านั้น
    'httponly' => true, // ป้องกันการดึง Cookie ผ่าน JavaScript (XSS)
    'samesite' => 'None' // จำเป็นต้องใช้ None เพื่อให้เปิดหน้าเว็บซ้อนใน LINE (LIFF/In-App Browser) ได้
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจ Session เดิม ห้ามเติมหรือเขียนทับตัวตนด้วยข้อมูลบัญชีปัจจุบัน
function authGuardPositiveInt($value)
{
    if (!is_int($value)
        && (!is_string($value) || !preg_match('/\A[1-9][0-9]*\z/', $value))) {
        return false;
    }
    return filter_var($value, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);
}

function authGuardSessionIsWellFormed($session, $now)
{
    if (!is_array($session)
        || authGuardPositiveInt($session['user_id'] ?? null) === false
        || authGuardPositiveInt($session['auth_version'] ?? null) === false
        || !is_string($session['username'] ?? null)
        || trim($session['username']) === ''
        || !is_string($session['full_name'] ?? null)
        || !is_string($session['role'] ?? null)
        || !in_array(strtolower($session['role']), ['admin', 'executive', 'technician'], true)
        || !is_int($session['LAST_ACTIVITY'] ?? null)
        || $session['LAST_ACTIVITY'] < 1
        || $session['LAST_ACTIVITY'] > $now
        || $now - $session['LAST_ACTIVITY'] > 28800) {
        return false;
    }
    if (strtolower($session['role']) === 'technician') {
        return authGuardPositiveInt($session['technician_id'] ?? null) !== false;
    }
    // auth.php เดิมไม่เก็บรหัสช่างสำหรับ Admin/Executive
    return !array_key_exists('technician_id', $session)
        || $session['technician_id'] === null;
}

function authGuardSessionMatches($account, $session, $now)
{
    if (!authGuardSessionIsWellFormed($session, $now)
        || !is_array($account)
        || authGuardPositiveInt($account['id'] ?? null) === false
        || authGuardPositiveInt($account['auth_version'] ?? null) === false
        || !in_array($account['is_active'] ?? null, [1, '1'], true)
        || !in_array($account['must_change_password'] ?? null, [0, '0', 1, '1'], true)
        || !is_string($account['role'] ?? null)
        || !array_key_exists('technician_id', $account)
        || (string) $account['id'] !== (string) $session['user_id']
        || (string) $account['auth_version'] !== (string) $session['auth_version']
        || $account['role'] !== $session['role']) {
        return false;
    }
    if (strtolower($account['role']) === 'technician') {
        return authGuardPositiveInt($account['technician_id']) !== false
            && (string) $account['technician_id'] === (string) $session['technician_id']
            && (string) ($account['linked_technician_id'] ?? '')
                === (string) $account['technician_id'];
    }
    return $account['technician_id'] === null;
}

function authGuardStatement($conn, $sql, $types, $values)
{
    $statement = $conn->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('Account check failed');
    }
    try {
        if (!$statement->bind_param($types, ...$values) || !$statement->execute()) {
            throw new RuntimeException('Account check failed');
        }
        return $statement;
    } catch (Throwable $e) {
        $statement->close();
        throw $e;
    }
}

function authGuardReadRow($statement)
{
    try {
        $result = $statement->get_result();
        if (!$result) {
            throw new RuntimeException('Account check failed');
        }
        $row = $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $result->free();
        return $row;
    } finally {
        $statement->close();
    }
}

function authGuardLoadAccount($conn, $user_id, $for_update = false)
{
    $suffix = $for_update ? ' FOR UPDATE' : '';
    $account = authGuardReadRow(authGuardStatement($conn,
        'SELECT id, role, technician_id, is_active, auth_version, must_change_password
         FROM users WHERE id = ?' . $suffix, 'i', [$user_id]));
    if ($account === null) {
        return null;
    }
    $account['linked_technician_id'] = null;
    if (strtolower((string) $account['role']) === 'technician') {
        $tech_id = authGuardPositiveInt($account['technician_id']);
        if ($tech_id !== false) {
            // ลำดับล็อกบัญชี -> ช่าง ตรงกับระบบ OTP
            $technician = authGuardReadRow(authGuardStatement($conn,
                'SELECT id FROM technicians WHERE id = ?' . $suffix, 'i', [$tech_id]));
            $account['linked_technician_id'] = $technician['id'] ?? null;
        }
    }
    return $account;
}

function authGuardRejectSession()
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Cache-Control: no-store');
    header('Location: login.php');
    exit();
}

// คงการฝากปลายทางของผู้ที่ยังไม่ได้ล็อกอินตามรูปแบบเดิม
if (!array_key_exists('user_id', $_SESSION)) {
    $_SESSION['redirect_url'] = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    header('Cache-Control: no-store');
    header('Location: login.php');
    exit();
}

$guard_now = time();
if (!authGuardSessionIsWellFormed($_SESSION, $guard_now)) {
    authGuardRejectSession();
}

require_once 'db_connect.php';
$user_id = authGuardPositiveInt($_SESSION['user_id']);
try {
    $user = authGuardLoadAccount($conn, $user_id);
} catch (Throwable $e) {
    // ปฏิเสธการเข้าถึงเมื่อยืนยันบัญชีไม่ได้ ไม่เปิดเผยรายละเอียดฐานข้อมูล
    error_log('Session account check failed');
    authGuardRejectSession();
}

if (!authGuardSessionMatches($user, $_SESSION, $guard_now)) {
    authGuardRejectSession();
}

header('Cache-Control: no-store');
// อัปเดตเฉพาะเวลา หลังผ่านการตรวจตัวตนและรุ่นสิทธิ์แล้ว
$_SESSION['LAST_ACTIVITY'] = $guard_now;

if ((int) $user['must_change_password'] === 1) {
    $current_page = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($current_page !== 'force_change_password.php' && $current_page !== 'logout.php') {
        header('Location: force_change_password.php');
        exit();
    }
}
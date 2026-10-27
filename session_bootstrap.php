<?php
// ใช้ร่วมกันก่อนเริ่ม Session; ไฟล์นี้ไม่บังคับล็อกอิน
const MBS_SESSION_IDLE_TIMEOUT = 28800; // 8 ชั่วโมง
// คุกกี้ช่างตั้งวันไกล แต่เบราว์เซอร์อาจจำกัดอายุเอง
const MBS_TECHNICIAN_COOKIE_EXPIRES = 2145916800; // 1 ม.ค. 2038 UTC

(static function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // HTTP ของ IP เดิมใช้ Secure=false
    // โดเมน HTTPS ใช้ Secure=true แม้ Proxy ส่งเข้า Apache ผ่าน HTTP
    // ใช้ SameSite=Lax และทดสอบความต่อเนื่องจาก LINE ในเบราว์เซอร์เดียวกัน
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $direct_https = ($https !== '' && $https !== 'off' && $https !== '0')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $plain_http_ip = !$direct_https
        && in_array($host, ['103.99.11.147', '103.99.11.147:80'], true);

    $cookie_params = [
        'lifetime' => MBS_SESSION_IDLE_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => !$plain_http_ip,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    // เก็บ Session แยกและไม่ลบทิ้งตามระยะเวลา; Guard ยังคงตรวจบัญชีทุกครั้ง
    if (ini_set('session.save_path', '/var/lib/php/repair-sessions') === false
        || ini_set('session.gc_probability', '0') === false
        || ini_set('session.gc_divisor', '100') === false
        || !session_set_cookie_params($cookie_params)
        || !session_start()) {
        error_log('Session initialization failed');
        http_response_code(503);
        exit('ไม่สามารถเริ่มการใช้งานได้ กรุณาลองใหม่ภายหลัง');
    }

    // ต่ออายุ Cookie เมื่อมีคำขอ; สิทธิ์และเวลาใช้งานยังตรวจโดย Guard
    if (ini_get('session.use_cookies')) {
        $cookie_options = $cookie_params;
        unset($cookie_options['lifetime']);
        $cookie_options['expires'] = strtolower((string) ($_SESSION['role'] ?? '')) === 'technician'
            ? MBS_TECHNICIAN_COOKIE_EXPIRES : time() + MBS_SESSION_IDLE_TIMEOUT;

        if (!setcookie(session_name(), session_id(), $cookie_options)) {
            error_log('Session cookie update failed');
            http_response_code(503);
            exit('ไม่สามารถเริ่มการใช้งานได้ กรุณาลองใหม่ภายหลัง');
        }
    }
})();
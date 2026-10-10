<?php
// กติกาตั้งรหัสผ่านส่วนกลาง ไม่มีการเชื่อมฐานข้อมูลหรือเปลี่ยน Session
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit();
}

function passwordPolicyConfig()
{
    return [
        'min_length' => 15,
        'max_bytes' => 72,
        'common_passwords' => [
            'password123456!', 'password123456789!', 'password123456789.',
            'password123456789_', 'qwerty123456789!', 'abcdef123456789!',
            'welcome123456789!', 'administrator123!',
        ],
    ];
}

function passwordPolicyRules($password)
{
    $policy = passwordPolicyConfig();
    $valid_utf8 = is_string($password) && preg_match('//u', $password) === 1;
    $length = $valid_utf8 ? preg_match_all('/./us', $password, $characters) : 0;
    return [
        'length' => $valid_utf8 && $length >= $policy['min_length'],
        'uppercase' => $valid_utf8 && preg_match('/[A-Z]/', $password) === 1,
        'lowercase' => $valid_utf8 && preg_match('/[a-z]/', $password) === 1,
        'number' => $valid_utf8 && preg_match('/[0-9]/', $password) === 1,
        'special' => $valid_utf8
            && preg_match('/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/', $password) === 1,
        'bytes' => $valid_utf8 && strlen($password) <= $policy['max_bytes'],
        'printable' => $valid_utf8 && preg_match('/[\x00-\x1F\x7F]/', $password) === 0,
        'common' => $valid_utf8 && $password !== ''
            && !in_array(strtolower($password), $policy['common_passwords'], true),
    ];
}

function passwordPolicyError($password)
{
    $policy = passwordPolicyConfig();
    if (!is_string($password) || $password === '' || preg_match('//u', $password) !== 1) {
        return 'กรุณากรอกรหัสผ่านเป็นข้อความที่ถูกต้อง';
    }
    $rules = passwordPolicyRules($password);
    $messages = [
        'length' => 'รหัสผ่านต้องมีอย่างน้อย ' . $policy['min_length'] . ' ตัวอักษร',
        'uppercase' => 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่ A–Z อย่างน้อย 1 ตัว',
        'lowercase' => 'รหัสผ่านต้องมีตัวพิมพ์เล็ก a–z อย่างน้อย 1 ตัว',
        'number' => 'รหัสผ่านต้องมีตัวเลข 0–9 อย่างน้อย 1 ตัว',
        'special' => 'รหัสผ่านต้องมีอักขระพิเศษ เช่น _ . ! @ # $ % & * อย่างน้อย 1 ตัว',
        'bytes' => 'รหัสผ่านต้องไม่เกิน ' . $policy['max_bytes'] . ' ไบต์',
        'printable' => 'รหัสผ่านต้องไม่มีอักขระควบคุม เช่น ขึ้นบรรทัดใหม่',
        'common' => 'กรุณาใช้รหัสผ่านอื่นที่ไม่ใช่รหัสตัวอย่างที่เดาง่าย',
    ];
    foreach ($messages as $key => $message) {
        if (!$rules[$key]) { return $message; }
    }
    return null;
}

function passwordPolicyHint()
{
    $policy = passwordPolicyConfig();
    return 'อย่างน้อย ' . $policy['min_length']
        . ' ตัวอักษร มี A–Z, a–z, 0–9 และอักขระพิเศษ เช่น _ . ! @ #'
        . ' ไม่เกิน ' . $policy['max_bytes']
        . ' ไบต์ ไม่มีอักขระควบคุม และไม่ใช่รหัสตัวอย่างที่เดาง่ายในรายการของระบบ';
}

<?php
require_once __DIR__ . '/auth_guard.php';

// Guard ตรวจบัญชีปัจจุบันแล้ว จากนั้นจำกัดเฉพาะ Admin
if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}

header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('{"error":"GET only"}');
}

// อ่านเฉพาะค่าที่จำเป็น ไม่อ่าน Cookie หรือข้อมูลลับ
$keys = [
    'HTTP_HOST',
    'REMOTE_ADDR',
    'HTTP_X_FORWARDED_FOR',
    'HTTP_X_FORWARDED_PROTO',
    'HTTPS',
    'SERVER_PORT',
];

$output = [];

foreach ($keys as $key) {
    $value = $_SERVER[$key] ?? null;

    $output[$key] = is_string($value)
        ? substr($value, 0, 512)
        : null;
}

session_write_close();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_INVALID_UTF8_SUBSTITUTE
);
<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/env.php';
header('Cache-Control: no-store');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(400); exit('ไม่พบเลขรายการค่ะ'); }
if (empty($lineLoginChannelId) || empty($lineLoginChannelSecret) || empty($lineLoginCallbackUrl)) { http_response_code(500); exit('ยังไม่ได้ตั้งค่า LINE Login ใน env.php ค่ะ'); }
$_SESSION['line_login_pending'] = ['state'=>bin2hex(random_bytes(32)), 'nonce'=>bin2hex(random_bytes(32)), 'id'=>$id, 'time'=>time()];
$query = http_build_query(['response_type'=>'code', 'client_id'=>$lineLoginChannelId, 'redirect_uri'=>$lineLoginCallbackUrl, 'state'=>$_SESSION['line_login_pending']['state'], 'scope'=>'openid profile', 'nonce'=>$_SESSION['line_login_pending']['nonce']], '', '&', PHP_QUERY_RFC3986);
header('Location: https://access.line.me/oauth2/v2.1/authorize?' . $query); exit;

<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/env.php';
header('Cache-Control: no-store');
function lineLoginFail($message, $status = 403) { http_response_code($status); header('Content-Type: text/plain; charset=utf-8'); exit($message); }
function lineLoginPost($url, $params) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($params, '', '&', PHP_QUERY_RFC3986), CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>20, CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
    $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    if ($body === false || $status !== 200) { throw new RuntimeException('LINE request failed'); }
    $data = json_decode($body, true); if (!is_array($data)) throw new RuntimeException('Invalid LINE response'); return $data;
}
$pending = $_SESSION['line_login_pending'] ?? null; unset($_SESSION['line_login_pending']);
if (!is_array($pending) || time() - ($pending['time'] ?? 0) > 600 || !is_string($_GET['state'] ?? null) || !hash_equals($pending['state'], $_GET['state']) || !is_string($_GET['code'] ?? null) || $_GET['code'] === '') lineLoginFail('เข้าสู่ระบบไม่สำเร็จ กรุณากดปุ่มจัดการจาก LINE อีกครั้งค่ะ');
try {
    if (empty($lineLoginChannelId) || empty($lineLoginChannelSecret) || empty($lineLoginCallbackUrl)) throw new RuntimeException('Missing configuration');
    $tokens = lineLoginPost('https://api.line.me/oauth2/v2.1/token', ['grant_type'=>'authorization_code','code'=>$_GET['code'],'redirect_uri'=>$lineLoginCallbackUrl,'client_id'=>$lineLoginChannelId,'client_secret'=>$lineLoginChannelSecret]);
    if (empty($tokens['id_token'])) throw new RuntimeException('Missing ID token');
    $identity = lineLoginPost('https://api.line.me/oauth2/v2.1/verify', ['id_token'=>$tokens['id_token'],'client_id'=>$lineLoginChannelId,'nonce'=>$pending['nonce']]);
    if (!isset($identity['sub'], $identity['nonce']) || !is_string($identity['sub']) || !hash_equals($pending['nonce'], $identity['nonce'])) throw new RuntimeException('Invalid identity');
    require_once __DIR__ . '/db_connect.php';
    $sql = "SELECT u.id, u.username, u.full_name, u.role, u.technician_id, u.auth_version FROM users u JOIN technicians t ON t.id=u.technician_id JOIN repairs r ON r.technician_id=t.id WHERE BINARY t.line_user_id=BINARY ? AND t.deleted_at IS NULL AND t.approval_status='อนุมัติแล้ว' AND LOWER(u.role)='technician' AND u.is_active=1 AND u.deleted_at IS NULL AND u.must_change_password=0 AND r.id=? AND r.deleted_at IS NULL LIMIT 2";
    $stmt = $conn->prepare($sql); if (!$stmt) throw new RuntimeException('Database prepare failed');
    $stmt->bind_param('si', $identity['sub'], $pending['id']); if (!$stmt->execute()) throw new RuntimeException('Database query failed');
    $result=$stmt->get_result(); if (!$result || $result->num_rows !== 1) lineLoginFail('ไม่สามารถเปิดใบงานได้ กรุณาตรวจว่าบัญชี LINE นี้ผูกกับเจ้าหน้าที่ผู้รับงานและบัญชีเว็บใช้งานได้ค่ะ');
    $user=$result->fetch_assoc(); $stmt->close();
    session_regenerate_id(true);
    $_SESSION['user_id']=(int)$user['id']; $_SESSION['username']=(string)$user['username']; $_SESSION['full_name']=(string)$user['full_name']; $_SESSION['role']=(string)$user['role']; $_SESSION['technician_id']=(int)$user['technician_id']; $_SESSION['auth_version']=(int)$user['auth_version']; $_SESSION['LAST_ACTIVITY']=time();
    unset($_SESSION['repair_csrf']);
    header('Location: update_repair.php?id='.(int)$pending['id']); exit;
} catch (Throwable $e) { error_log('LINE Login failed: '.$e->getMessage()); lineLoginFail('เข้าสู่ระบบ LINE ไม่สำเร็จ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบค่ะ', 500); }

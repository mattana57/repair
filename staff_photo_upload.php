<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/repair_media.php';
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
function staffPhotoExit($message, $id = 0, $code = 400) {
    http_response_code($code);
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="th"><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>รูปผลการดำเนินงาน</title><body style="font:18px sans-serif;padding:24px;line-height:1.6"><p>'.$safe.'</p>';
    if ($id > 0) echo '<a href="update_repair.php?id='.(int)$id.'">กลับไปหน้าใบงาน</a>';
    echo '</body></html>'; exit;
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) staffPhotoExit('คำขอไม่ถูกต้องค่ะ', 0, 400);
if (strtolower((string)($_SESSION['role'] ?? '')) !== 'technician') staffPhotoExit('เฉพาะเจ้าหน้าที่ผู้รับงานเท่านั้นที่เพิ่มรูปได้ค่ะ', $id, 403);
$csrf = $_POST['repair_csrf'] ?? null;
if (!is_string($csrf) || !is_string($_SESSION['repair_csrf'] ?? null) || !hash_equals($_SESSION['repair_csrf'], $csrf)) staffPhotoExit('คำขอหมดอายุ กรุณาเปิดใบงานใหม่ค่ะ', $id, 403);
$tech = (int)($_SESSION['technician_id'] ?? 0);
if ($tech < 1) staffPhotoExit('ไม่พบข้อมูลเจ้าหน้าที่ค่ะ', $id, 403);
$f = $_FILES['photo'] ?? null;
if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) staffPhotoExit('รับรูปไม่สำเร็จ กรุณาเลือกรูปและลองใหม่ค่ะ', $id);
$size = filesize($f['tmp_name']);
if (!$size || $size > 10485760) staffPhotoExit('กรุณาเลือกรูปขนาดไม่เกิน 10 MB ค่ะ', $id);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true) || @getimagesize($f['tmp_name']) === false) staffPhotoExit('กรุณาเลือกไฟล์รูป JPEG, PNG หรือ WebP ค่ะ', $id);
$path = null;
try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT technician_id,status,deleted_at FROM repairs WHERE id=? FOR UPDATE');
    $stmt->bind_param('i',$id); $stmt->execute(); $job = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$job || (int)$job['technician_id'] !== $tech || $job['deleted_at'] !== null || in_array($job['status'], ['ซ่อมเสร็จแล้ว','เสร็จสิ้น','ยกเลิก'], true)) {
        $conn->rollback(); staffPhotoExit('ใบงานนี้ไม่พร้อมให้คุณเพิ่มรูปค่ะ', $id, 403);
    }
    $dir = rmDirectory(); $storage = bin2hex(random_bytes(24)); $path = $dir.'/'.$storage;
    if (!move_uploaded_file($f['tmp_name'],$path)) throw new RuntimeException('file move failed');
    chmod($path, 0600);
    $uid = 'staff:'.$tech; $message = 'staff-'.bin2hex(random_bytes(24)); $kind='image'; $sent=time(); $state='ready';
    $stmt = $conn->prepare('INSERT INTO repair_attachments (repair_id,line_user_id,line_message_id,media_type,sent_epoch,state,storage_name,mime_type,size_bytes) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('isssisssi',$id,$uid,$message,$kind,$sent,$state,$storage,$mime,$size);
    if (!$stmt->execute()) throw new RuntimeException('insert failed');
    $stmt->close(); if (!$conn->commit()) throw new RuntimeException('commit failed');
    staffPhotoExit('บันทึกรูปแล้วค่ะ กดกลับไปหน้าใบงานแล้วเลือกดูไฟล์แนบทั้งหมดเพื่อดูรูป', $id, 200);
} catch (Throwable $e) {
    $conn->rollback(); if ($path && is_file($path)) @unlink($path);
    error_log('Staff photo upload failed: '.get_class($e));
    staffPhotoExit('บันทึกรูปไม่สำเร็จ กรุณาติดต่อผู้ดูแลระบบค่ะ', $id, 500);
}

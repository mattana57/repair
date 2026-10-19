<?php
require_once __DIR__.'/auth_guard.php';
require_once __DIR__.'/self_profile_service.php';
require_once __DIR__.'/activity_log.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    if (!in_array(strtolower((string)($_SESSION['role']??'')),['executive','technician'],true)) {
        http_response_code(403);throw new DomainException('ไม่อนุญาตให้แก้ไขข้อมูลผ่านหน้าต่างนี้');
    }
    if (($_SERVER['REQUEST_METHOD']??'')==='GET') {
        $a=spLoad($conn,$_SESSION);
        echo json_encode(['ok'=>true,'profile'=>spProfile($a),'revision'=>spRevision($a)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    } elseif (($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $saved=spSave($conn,$_SESSION,$_POST);
        foreach (['username','full_name','english_name','auth_version'] as $key) $_SESSION[$key]=$saved[$key];
        mbsActivityLog($conn,'SAVE_ACCOUNT','user',(int)$_SESSION['user_id'],'เจ้าของบัญชีแก้ไขข้อมูลส่วนตัว');
        echo json_encode(['ok'=>true,'message'=>'บันทึกข้อมูลสำเร็จ','email_changed'=>$saved['email_changed']],JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(405);header('Allow: GET, POST');
        echo json_encode(['ok'=>false,'message'=>'วิธีส่งคำขอไม่ถูกต้อง'],JSON_UNESCAPED_UNICODE);
    }
} catch (DomainException $e) {
    if (http_response_code()<400) http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Own profile request failed');
    http_response_code((int)$e->getCode()===1062 ? 409 : 500);
    echo json_encode(['ok'=>false,'message'=>(int)$e->getCode()===1062 ? 'ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว กรุณากรอกชื่อผู้ใช้ใหม่แล้วลองอีกครั้ง' : 'ไม่สามารถบันทึกหรือโหลดข้อมูลได้ กรุณาลองใหม่ภายหลัง'],JSON_UNESCAPED_UNICODE);
}

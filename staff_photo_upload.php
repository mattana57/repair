<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/repair_media.php';
require_once __DIR__ . '/line_job_sync.php';
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
function staffMediaExit($message, $id=0, $code=400) {
    http_response_code($code);
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>$code===200,'message'=>$message], JSON_UNESCAPED_UNICODE); exit; }
    echo '<!doctype html><html lang="th"><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>ไฟล์ผลการดำเนินงาน</title><body style="margin:0;background:#f1f5fb;font:17px sans-serif;padding:24px;line-height:1.6;color:#1e3a5f"><main style="max-width:480px;margin:40px auto;background:#fff;border-radius:20px;padding:24px;box-shadow:0 10px 30px #1e3a5f18"><h2>ไฟล์ผลการดำเนินงาน</h2><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>';
    if ($id>0) echo '<a href="update_repair.php?id='.(int)$id.'">กลับไปหน้าใบงาน</a>';
    echo '</main></body></html>'; exit;
}
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if ($_SERVER['REQUEST_METHOD']!=='POST' || !$id) staffMediaExit('คำขอไม่ถูกต้อง หรือไฟล์รวมเกินขนาดที่เซิร์ฟเวอร์กำหนดค่ะ',0,400);
$csrf=$_POST['repair_csrf']??null;
if (strtolower((string)($_SESSION['role']??''))!=='technician' || !is_string($csrf) || !is_string($_SESSION['repair_csrf']??null) || !hash_equals($_SESSION['repair_csrf'],$csrf)) staffMediaExit('กรุณาเปิดใบงานใหม่ด้วยบัญชีเจ้าหน้าที่ผู้รับงานค่ะ',$id,403);
$tech=(int)($_SESSION['technician_id']??0); $fs=$_FILES['media']??null;
if ($tech<1 || !is_array($fs) || !isset($fs['error']) || !is_array($fs['error']) || count($fs['error'])<1 || count($fs['error'])>12) staffMediaExit('กรุณาเลือกไฟล์ 1–12 ไฟล์ต่อครั้งค่ะ',$id);
$allowed=['image/jpeg'=>'image','image/png'=>'image','image/webp'=>'image','video/mp4'=>'video','video/quicktime'=>'video','video/webm'=>'video'];
$files=[]; $images=0; $videos=0;
foreach ($fs['error'] as $i=>$err) {
    $tmp=$fs['tmp_name'][$i]??'';
    if ($err!==UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp)) staffMediaExit('รับบางไฟล์ไม่สำเร็จ กรุณาตรวจขนาดไฟล์และลองใหม่ค่ะ ยังไม่มีไฟล์ใดในชุดนี้ถูกบันทึก',$id);
    $size=filesize($tmp);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$kind=$allowed[$mime]??null;
    if (!$kind || !$size || ($kind==='image' && ($size>10485760 || @getimagesize($tmp)===false)) || ($kind==='video' && $size>52428800)) staffMediaExit('ชนิดไฟล์ไม่รองรับ หรือไฟล์มีขนาดเกินที่กำหนดค่ะ ยังไม่มีไฟล์ใดในชุดนี้ถูกบันทึก',$id);
    if($kind==='image')$images++;else $videos++;
    $files[]=['tmp'=>$tmp,'size'=>$size,'mime'=>$mime,'kind'=>$kind];
}
if ($images>10 || $videos>2) staffMediaExit('เลือกได้ครั้งละไม่เกิน 10 รูป และ 2 วิดีโอค่ะ',$id);
$paths=[];
try {
    $conn->begin_transaction();
    $stmt=$conn->prepare('SELECT * FROM repairs WHERE id=? FOR UPDATE');
    if(!$stmt) throw new RuntimeException('prepare');$stmt->bind_param('i',$id);$stmt->execute();$job=$stmt->get_result()->fetch_assoc();$stmt->close();
    if (!$job || (int)$job['technician_id']!==$tech || $job['deleted_at']!==null) {$conn->rollback();staffMediaExit('คุณไม่มีสิทธิ์เพิ่มไฟล์ในใบงานนี้ค่ะ',$id,403);}
    $dir=rmDirectory();$uid='staff:'.$tech;$sent=time();$state='ready';
    $firstFileId = 0;
    foreach($files as $f){
        $storage=bin2hex(random_bytes(24));$path=$dir.'/'.$storage;
        if(!move_uploaded_file($f['tmp'],$path))throw new RuntimeException('move');
        $paths[]=$path;chmod($path,0600);$message='staff-'.bin2hex(random_bytes(24));$kind=$f['kind'];$mime=$f['mime'];$size=$f['size'];
        $stmt=$conn->prepare('INSERT INTO repair_attachments (repair_id,line_user_id,line_message_id,media_type,sent_epoch,state,storage_name,mime_type,size_bytes) VALUES (?,?,?,?,?,?,?,?,?)');
        if(!$stmt) throw new RuntimeException('prepare');$stmt->bind_param('isssisssi',$id,$uid,$message,$kind,$sent,$state,$storage,$mime,$size);
        if(!$stmt->execute())throw new RuntimeException('insert'); if (!$firstFileId) $firstFileId=(int)$conn->insert_id; $stmt->close();
    }
    if (!empty($job['line_user_id'])) {
        $label=mbsSyncLabel($conn,$job);
        $viewToken=bin2hex(random_bytes(32)); $viewHash=hash('sha256',$viewToken); $viewUser=(string)$job['line_user_id'];
        $grant=$conn->prepare("INSERT INTO staff_media_view_grants (token_hash,repair_id,line_user_id,expires_at) VALUES (?,?,?,'9999-12-31 23:59:59')");
        if (!$grant) throw new RuntimeException('grant prepare');
        $grant->bind_param('sis',$viewHash,$id,$viewUser); if (!$grant->execute()) throw new RuntimeException('grant save'); $grant->close();
        $viewUrl=mbsSyncConfig()['base'].'/requester_staff_media.php?token='.$viewToken;
        $notice=['type'=>'flex','altText'=>'เจ้าหน้าที่เพิ่มไฟล์ผลการดำเนินงาน','contents'=>['type'=>'bubble','body'=>['type'=>'box','layout'=>'vertical','spacing'=>'sm','contents'=>[['type'=>'text','text'=>'📎 เจ้าหน้าที่เพิ่มไฟล์ผลการดำเนินงาน','weight'=>'bold','wrap'=>true],['type'=>'text','text'=>$label,'wrap'=>true],['type'=>'text','text'=>'สถานที่: '.(string)$job['location'],'wrap'=>true],['type'=>'text','text'=>'เจ้าหน้าที่: '.(string)$job['technician_name'],'wrap'=>true],['type'=>'text','text'=>'เพิ่ม '.$images.' รูป และ '.$videos.' วิดีโอ','wrap'=>true]]], 'footer'=>['type'=>'box','layout'=>'vertical','contents'=>[['type'=>'button','style'=>'primary','action'=>['type'=>'uri','label'=>'ดูไฟล์ผลการดำเนินงาน','uri'=>$viewUrl]]]]]];
        mbsSyncEnqueue($conn,$id,'media'.base_convert((string)$firstFileId,10,36),(string)$job['line_user_id'],[$notice]);
        $stmtF=$conn->prepare('SELECT line_user_id FROM repair_followers WHERE repair_id=?');
        if (!$stmtF) throw new RuntimeException('follower prepare');
        $stmtF->bind_param('i',$id); if (!$stmtF->execute()) throw new RuntimeException('follower read');
        $fr=$stmtF->get_result();
        while($f=$fr->fetch_assoc()) {
            $recipient=(string)$f['line_user_id']; if($recipient===(string)$job['line_user_id']) continue;
            $ftoken=bin2hex(random_bytes(32));$fhash=hash('sha256',$ftoken);
            $fg=$conn->prepare("INSERT INTO staff_media_view_grants(token_hash,repair_id,line_user_id,expires_at) VALUES(?,?,?,'9999-12-31 23:59:59')");
            if(!$fg)throw new RuntimeException('follower grant');$fg->bind_param('sis',$fhash,$id,$recipient);if(!$fg->execute())throw new RuntimeException('follower grant save');$fg->close();
            $fnotice=$notice;$fnotice['contents']['footer']['contents'][0]['action']['uri']=mbsSyncConfig()['base'].'/requester_staff_media.php?token='.$ftoken;
            mbsSyncEnqueue($conn,$id,'media'.base_convert((string)$firstFileId,10,36),$recipient,[$fnotice]);
        }
        $stmtF->close();
    }
    if(!$conn->commit())throw new RuntimeException('commit');
    mbsSyncDispatchSafe($conn,$id);
    staffMediaExit('บันทึก '.$images.' รูป และ '.$videos.' วิดีโอแล้วค่ะ กลับไปหน้าใบงานเพื่อดูไฟล์แนบ',$id,200);
} catch(Throwable $e) {
    $conn->rollback();foreach($paths as $path){if(is_file($path))@unlink($path);}error_log('Staff media upload failed: '.get_class($e));
    staffMediaExit('บันทึกไฟล์ไม่สำเร็จ กรุณาติดต่อผู้ดูแลค่ะ',$id,500);
}

<?php
require_once __DIR__.'/auth_guard.php';
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/repair_reassignment.php';
function mbsReassignFail($message,$code=400) {
    http_response_code($code);header('Content-Type: text/html; charset=UTF-8');header('Cache-Control: no-store');
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>เปลี่ยนผู้รับผิดชอบ</title><body style="background:#f1f5f9;font:18px Tahoma,sans-serif;padding:24px;line-height:1.7"><main style="max-width:560px;margin:40px auto;background:white;padding:24px;border-radius:16px"><h2>ยังเปลี่ยนเจ้าหน้าที่ไม่ได้</h2><p>'.mbsAssignmentEscape($message).'</p><a href="javascript:history.back()">กลับหน้าใบงาน</a></main></body></html>';exit;
}
if ($_SERVER['REQUEST_METHOD']!=='POST') mbsReassignFail('กรุณาเปิดใบงานเพื่อทำรายการ',405);
if (strtolower((string)($_SESSION['role']??''))!=='admin') mbsReassignFail('เฉพาะแอดมินเท่านั้นที่เปลี่ยนผู้รับผิดชอบได้',403);
$csrf=$_POST['repair_csrf']??null;
if (!is_string($csrf) || !isset($_SESSION['repair_csrf']) || !hash_equals($_SESSION['repair_csrf'],$csrf)) mbsReassignFail('คำขอไม่ถูกต้อง กรุณาเปิดใบงานใหม่',403);
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$newId=filter_var($_POST['new_technician_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$expected=filter_var($_POST['expected_technician_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$reason=$_POST['reason']??null;$key=$_POST['request_key']??null;
if (!$id || !$newId || !$expected || !is_string($reason) || !is_string($key) || !preg_match('/^[a-f0-9]{32}$/D',$key)) mbsReassignFail('ข้อมูลการเปลี่ยนเจ้าหน้าที่ไม่ครบ');
$reason=trim($reason);
if (!preg_match('/[^\s\p{Z}\x{200B}\x{FEFF}]/u',$reason) || mb_strlen($reason)>1000) mbsReassignFail('กรุณาระบุเหตุผลไม่เกิน 1,000 ตัวอักษร');
if (empty($_SESSION['reassign_keys'][$id]) || !hash_equals($_SESSION['reassign_keys'][$id],$key)) mbsReassignFail('รายการนี้ถูกส่งไปแล้วหรือหมดอายุ กรุณาเปิดใบงานใหม่');
if (!mbsAssignmentReady($conn)) mbsReassignFail('ยังไม่ได้ติดตั้งตารางและ Trigger กรุณาติดตั้ง install_reassignment.sql ก่อน',503);
require_once __DIR__.'/line_job_sync.php';
$started=false;
try {
    if (!$conn->begin_transaction()) throw new RuntimeException('Cannot start transfer');$started=true;
    $actor=mbsSyncRow($conn,"SELECT id,auth_version FROM users WHERE id=? AND LOWER(role)='admin' AND is_active=1 AND deleted_at IS NULL FOR UPDATE",'i',(int)$_SESSION['user_id']);
    if (!$actor || (int)$actor['auth_version']!==(int)($_SESSION['auth_version']??0)) throw new DomainException('บัญชีแอดมินเปลี่ยนแปลง กรุณาเข้าสู่ระบบใหม่');
    $job=mbsSyncRow($conn,'SELECT * FROM repairs WHERE id=? AND deleted_at IS NULL FOR UPDATE','i',$id);
    if (!$job || !in_array($job['status'],['กำลังดำเนินการ','ช่างรับเรื่องแจ้งซ่อมแล้ว'],true) || (int)$job['technician_id']<1) throw new DomainException('เปลี่ยนได้เฉพาะงานที่มีผู้รับผิดชอบและยังไม่ปิด');
    if ((int)$job['technician_id']!==$expected) throw new DomainException('ผู้รับผิดชอบเปลี่ยนไปแล้ว กรุณาเปิดใบงานล่าสุด');
    if ($newId===$expected) throw new DomainException('กรุณาเลือกเจ้าหน้าที่คนใหม่');
    $new=mbsSyncRow($conn,"SELECT t.* FROM technicians t JOIN users u ON u.technician_id=t.id WHERE t.id=? AND t.deleted_at IS NULL AND t.approval_status='อนุมัติแล้ว' AND LOWER(u.role)='technician' AND u.is_active=1 AND u.deleted_at IS NULL FOR UPDATE",'i',$newId);
    if (!$new) throw new DomainException('บัญชีเจ้าหน้าที่ใหม่ยังไม่พร้อมใช้งานหรือถูกระงับ');
    $old=mbsSyncRow($conn,'SELECT id,full_name,line_user_id FROM technicians WHERE id=?','i',$expected);
    $oldName=(string)($old['full_name']??$job['technician_name']??'');
    $now=mbsSyncRow($conn,'SELECT DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 HOUR) AS changed_at')['changed_at'];
    mbsSyncStmt($conn,'INSERT INTO repair_reassignments(repair_id,old_technician_id,new_technician_id,old_name,new_name,admin_user_id,reason,changed_at,request_key) VALUES(?,?,?,?,?,?,?,?,?)','iiississs',$id,$expected,$newId,$oldName,$new['full_name'],(int)$actor['id'],$reason,$now,$key)->close();
    $transferId=$conn->insert_id;
    mbsSyncStmt($conn,'UPDATE repairs SET technician_id=?,technician_name=? WHERE id=? AND technician_id=?','isii',$newId,$new['full_name'],$id,$expected)->close();
    $job['technician_id']=$newId;$job['technician_name']=$new['full_name'];
    $label=mbsSyncLabel($conn,$job);$config=mbsSyncConfig();
    $kind='tr_'.base_convert((string)$transferId,10,36);
    $notice=mbsRequesterCard('เปลี่ยนเจ้าหน้าที่ผู้รับผิดชอบ',$label,[
        'เดิม: '.$oldName,'ผู้รับผิดชอบใหม่: '.$new['full_name'],
        'เบอร์ติดต่อ: '.($new['phone']?:'-'),'สถานที่: '.$job['location'],
        'เรื่อง: '.$job['equipment_type'],'เหตุผล: '.$reason
    ],'สถานะงานยังอยู่ระหว่างดำเนินการค่ะ');
    $buckets=[];
    $add=static function($target,$messages) use (&$buckets) { $target=trim((string)$target);if ($target!=='') $buckets[$target]=array_merge($buckets[$target]??[],$messages); };
    $add($job['line_user_id']??null,[$notice]);
    $card=mbsSyncAcceptedCard($job,$new,$label);
    $add($config['group'],[$notice,$card]);
    $add($new['line_user_id']??null,[$notice,$card]);
    if (!empty($old['line_user_id'])) $add($old['line_user_id'],[mbsRequesterCard('ส่งต่องานแล้ว',$label,['ผู้รับผิดชอบใหม่: '.$new['full_name'],'เหตุผล: '.$reason],'คุณไม่มีสิทธิ์แก้ไขหรือปิดใบงานนี้แล้วค่ะ')]);
    foreach ($buckets as $target=>$messages) mbsSyncEnqueue($conn,$id,$kind,$target,array_slice($messages,0,5));
    if (!$conn->commit()) throw new RuntimeException('Cannot commit transfer');$started=false;
} catch (DomainException $e) {
    if ($started) $conn->rollback();mbsReassignFail($e->getMessage(),409);
} catch (Throwable $e) {
    if ($started) $conn->rollback();error_log('Repair reassignment failed: '.get_class($e));mbsReassignFail('บันทึกไม่สำเร็จ กรุณาตรวจตารางและระบบแจ้งเตือน แล้วเปิดใบงานล่าสุดก่อนลองอีกครั้ง',500);
}
unset($_SESSION['reassign_keys'][$id]);
$_SESSION['reassign_notice'][$id]='เปลี่ยนผู้รับผิดชอบเป็น '.$new['full_name'].' แล้ว และบันทึกคิวแจ้งเตือนเรียบร้อย';
session_write_close();
mbsSyncDispatchSafe($conn,$id);
header('Location: update_repair.php?id='.$id, true,303);exit;

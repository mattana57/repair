<?php
// เรียกผ่าน PHP CLI โดย cron เท่านั้น
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
chdir(__DIR__);
require __DIR__.'/db_connect.php';
require __DIR__.'/env.php';
require_once __DIR__.'/repair_media.php';
$lock=rmQuery($conn,"SELECT GET_LOCK('repair_media_worker_v1',0) AS acquired")->get_result()->fetch_assoc();
if(!$lock || (int)$lock['acquired']!==1){exit;}
try{
    // คืนรายการที่ process เดิมค้างให้ลองใหม่; timeout ดาวน์โหลดสูงสุด 60 วินาที
    rmQuery($conn,"UPDATE repair_attachments SET state='queued' WHERE state='fetching' AND touched_at < NOW() - INTERVAL 15 MINUTE");
    // พักไฟล์ก่อนแจ้งงานได้ 24 ชั่วโมง เมื่อหมดอายุให้คืนโควตาและลบไฟล์พัก
    $expired=rmQuery($conn,"SELECT id,storage_name FROM repair_attachments WHERE repair_id IS NULL AND sent_epoch<? AND state<>'fetching'",'i',time()-RM_PENDING_SECONDS)->get_result();
    while($r=$expired->fetch_assoc()){
        if($r['storage_name'] && preg_match('/^[a-f0-9]{48}$/D',$r['storage_name'])){@unlink(rmDirectory().'/'.$r['storage_name']);}
        rmQuery($conn,"UPDATE repair_attachments SET state='expired',storage_name=NULL WHERE id=?",'i',(int)$r['id']);
    }
    // กู้การผูกไฟล์พัก หากข้อความสร้างงานสำเร็จแต่ process หยุดก่อนผูกครบ
    $pending=rmQuery($conn,"SELECT DISTINCT line_user_id FROM repair_attachments WHERE repair_id IS NULL AND state NOT IN ('failed','expired') LIMIT 100")->get_result();
    while($p=$pending->fetch_assoc()){
        $job=rmLatest($conn,$p['line_user_id']);
        if($job){rmClaimPending($conn,$p['line_user_id'],(int)$job['id']);}
    }
    $rows=rmQuery($conn,"SELECT * FROM repair_attachments WHERE state='queued' AND attempts<30 ORDER BY id LIMIT 10")->get_result();
    while($row=$rows->fetch_assoc()){
        $result=rmDownload($conn,$row,$channelAccessToken,60);
        if(in_array($result,['too_large','failed'],true)){
            $message=$result==='too_large'?'ไฟล์แนบหนึ่งรายการเกินขนาดที่กำหนดค่ะ รูปไม่เกิน 10 MB และวิดีโอไม่เกิน 50 MB ไฟล์อื่นยังอยู่ครบ':'รับไฟล์แนบหนึ่งรายการไม่สำเร็จค่ะ กรุณาส่งไฟล์ใหม่ ไฟล์อื่นยังอยู่ครบ';
            $ch=curl_init('https://api.line.me/v2/bot/message/push');
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$channelAccessToken],CURLOPT_POSTFIELDS=>json_encode(['to'=>$row['line_user_id'],'messages'=>[['type'=>'text','text'=>$message]]],JSON_UNESCAPED_UNICODE),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);curl_exec($ch);curl_close($ch);
        }
    }
}finally{rmQuery($conn,"SELECT RELEASE_LOCK('repair_media_worker_v1')");}

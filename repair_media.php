<?php
// ใช้ร่วมกับ webhook และตัวรับไฟล์เบื้องหลัง ไม่แสดงไฟล์โดยตรงจากโฟลเดอร์
const RM_IMAGE_LIMIT = 10;
const RM_VIDEO_LIMIT = 2;
const RM_IMAGE_BYTES = 10485760;
const RM_VIDEO_BYTES = 52428800;
const RM_PENDING_SECONDS = 86400; // ไฟล์ก่อนข้อความพักได้ 24 ชั่วโมง
function rmQuery($db, $sql, $types = '', ...$args) {
    $s = $db->prepare($sql);
    if ($types !== '') { $s->bind_param($types, ...$args); }
    $s->execute();
    return $s;
}
function rmLock($db, $user) {
    $key = 'repair_media_' . substr(hash('sha256', $user), 0, 40);
    $r = rmQuery($db, 'SELECT GET_LOCK(?, 10) AS acquired', 's', $key)->get_result()->fetch_assoc();
    if (!$r || (int)$r['acquired'] !== 1) { throw new RuntimeException('media lock unavailable'); }
    return $key;
}
function rmUnlock($db, $key) { rmQuery($db, 'SELECT RELEASE_LOCK(?)', 's', $key); }
function rmLatest($db, $user) {
    return rmQuery($db, 'SELECT id,ticket_no,status,UNIX_TIMESTAMP(created_at) AS created_epoch FROM repairs WHERE line_user_id=? ORDER BY id DESC LIMIT 1', 's', $user)->get_result()->fetch_assoc();
}
function rmEligible($job, $sent) {
    return $job && !in_array($job['status'], ['ซ่อมเสร็จแล้ว','เสร็จสิ้น'], true)
        && $sent >= (int)$job['created_epoch'] && $sent <= (int)$job['created_epoch'] + 300;
}
function rmRegister($db, $user, $message, $kind, $sent) {
    $key = rmLock($db, $user);
    try {
        $old = rmQuery($db,'SELECT * FROM repair_attachments WHERE line_message_id=?','s',$message)->get_result()->fetch_assoc();
        if ($old) { return ['duplicate'=>true,'row'=>$old]; }
        $job = rmLatest($db,$user);
        $repair = rmEligible($job,$sent) ? (int)$job['id'] : null;
        $cutoff = time() - RM_PENDING_SECONDS;
        // แถวรอดาวน์โหลดนับรวมด้วย เพื่อไม่ให้ส่งพร้อมกันแล้วเกินจำนวน
        $count = rmQuery($db,"SELECT COUNT(*) AS n FROM repair_attachments WHERE line_user_id=? AND media_type=? AND state NOT IN ('failed','expired') AND ((? IS NOT NULL AND repair_id=?) OR (? IS NULL AND repair_id IS NULL AND sent_epoch>=?))",'ssiiii',$user,$kind,$repair,$repair,$repair,$cutoff)->get_result()->fetch_assoc();
        $limit = $kind === 'image' ? RM_IMAGE_LIMIT : RM_VIDEO_LIMIT;
        $legacy = $repair && $kind === 'image' ? rmQuery($db,"SELECT image_path FROM repairs WHERE id=?",'i',$repair)->get_result()->fetch_assoc() : null;
        if ((int)$count['n'] + (!empty($legacy['image_path']) ? 1 : 0) >= $limit) {
            return ['error'=>$kind === 'image' ? 'รับรูปได้ไม่เกิน 10 รูปต่อใบงานค่ะ ไฟล์ก่อนหน้ายังอยู่ครบ' : 'รับวิดีโอได้ไม่เกิน 2 คลิปต่อใบงานค่ะ ไฟล์ก่อนหน้ายังอยู่ครบ'];
        }
        rmQuery($db,"INSERT INTO repair_attachments(repair_id,line_user_id,line_message_id,media_type,sent_epoch,state) VALUES(?,?,?,?,?,'queued')",'isssi',$repair,$user,$message,$kind,$sent);
        $row = rmQuery($db,'SELECT * FROM repair_attachments WHERE id=?','i',(int)$db->insert_id)->get_result()->fetch_assoc();
        return ['row'=>$row,'ticket'=>$repair ? $job['ticket_no'] : null];
    } finally { rmUnlock($db,$key); }
}
function rmClaimPending($db,$user,$repair) {
    $key=rmLock($db,$user);
    try {
        $job=rmQuery($db,'SELECT id,line_user_id,image_path,UNIX_TIMESTAMP(created_at) AS created_epoch FROM repairs WHERE id=?','i',$repair)->get_result()->fetch_assoc();
        if (!$job || $job['line_user_id'] !== $user) { return 0; }
        $cutoff=time()-RM_PENDING_SECONDS;
        $rows=rmQuery($db,"SELECT id,media_type FROM repair_attachments WHERE line_user_id=? AND repair_id IS NULL AND state NOT IN ('failed','expired') AND sent_epoch>=? AND sent_epoch<=? ORDER BY id",'sii',$user,$cutoff,(int)$job['created_epoch'])->get_result();
        $counts=['image'=>!empty($job['image_path']) ? 1 : 0,'video'=>0];
        $existing=rmQuery($db,"SELECT media_type,COUNT(*) AS n FROM repair_attachments WHERE repair_id=? AND line_user_id NOT LIKE 'staff:%' AND state NOT IN ('failed','expired') GROUP BY media_type",'i',$repair)->get_result();
        while($x=$existing->fetch_assoc()){$counts[$x['media_type']]+=(int)$x['n'];}
        $n=0;
        while($r=$rows->fetch_assoc()){
            $kind=$r['media_type'];$limit=$kind==='image'?RM_IMAGE_LIMIT:RM_VIDEO_LIMIT;
            if($counts[$kind]>=$limit){rmQuery($db,"UPDATE repair_attachments SET state='expired' WHERE id=?",'i',(int)$r['id']);continue;}
            rmQuery($db,'UPDATE repair_attachments SET repair_id=? WHERE id=? AND repair_id IS NULL','ii',$repair,(int)$r['id']);$counts[$kind]++;$n++;
        }
        return $n;
    } finally {rmUnlock($db,$key);}
}
function rmDirectory() {
    // ไฟล์เก่าและใหม่อยู่ uploads โดยตรง ไม่สร้างโฟลเดอร์ย่อย
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) || !is_writable($dir)) { throw new RuntimeException('uploads unavailable'); }
    // ตัวติดตั้งสร้างเครื่องหมายนี้หลัง Apache ป้องกันชื่อไฟล์ใหม่แล้ว
    if (!is_file($dir . '/.mbs_media_guard_ready')) { throw new RuntimeException('media access protection not installed'); }
    return $dir;
}
function rmDownload($db,$row,$token,$timeout=8) {
    // หลาย worker เรียกพร้อมกันได้ แต่โหลดไฟล์เดียวกันเพียงครั้งเดียว
    $s=rmQuery($db,"UPDATE repair_attachments SET state='fetching',attempts=attempts+1,touched_at=NOW() WHERE id=? AND state='queued'",'i',(int)$row['id']);
    if($s->affected_rows!==1){return 'queued';}
    $path=null;
    try {
        $dir=rmDirectory();$path=$dir.'/'.bin2hex(random_bytes(24));$file=fopen($path,'xb');
        if(!$file){throw new RuntimeException('cannot create media');}
        chmod($path,0600);$bytes=0;$oversize=false;
        $limit=$row['media_type']==='image'?RM_IMAGE_BYTES:RM_VIDEO_BYTES;
        $ch=curl_init('https://api-data.line.me/v2/bot/message/'.rawurlencode($row['line_message_id']).'/content');
        curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token],CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>function($ch,$data)use($file,&$bytes,&$oversize,$limit){$length=strlen($data);if($bytes+$length>$limit){$oversize=true;return 0;}$written=fwrite($file,$data);$bytes+=$written===false?0:$written;return $written===false?0:$written;}]);
        $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);fclose($file);
        if($oversize){@unlink($path);rmQuery($db,"UPDATE repair_attachments SET state='failed',failure_code='too_large' WHERE id=?",'i',(int)$row['id']);return 'too_large';}
        if($http===202 || $http===429 || $http>=500 || $ok===false){
            @unlink($path);$state=(int)$row['attempts']>=29?'failed':'queued';
            rmQuery($db,'UPDATE repair_attachments SET state=?,failure_code=? WHERE id=?','ssi',$state,'retry',(int)$row['id']);return $state;
        }
        if($http!==200 || $bytes===0){throw new RuntimeException('content unavailable');}
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed=$row['media_type']==='image'?['image/jpeg','image/png','image/gif','image/webp']:['video/mp4','video/quicktime','video/webm'];
        if(!in_array($mime,$allowed,true)){throw new RuntimeException('unsupported media');}
        rmQuery($db,"UPDATE repair_attachments SET state='ready',storage_name=?,mime_type=?,size_bytes=?,failure_code=NULL WHERE id=?",'ssii',basename($path),$mime,$bytes,(int)$row['id']);
        return 'ready';
    } catch(Throwable $e) {
        if($path){@unlink($path);}error_log('LINE media download failed for attachment '.(int)$row['id']);
        rmQuery($db,"UPDATE repair_attachments SET state='failed',failure_code='unavailable' WHERE id=?",'i',(int)$row['id']);return 'failed';
    }
}
function rmCanReadAttachments($role,$sessionTechnician,$owner) {
    $role=strtolower((string)$role);
    return in_array($role,['admin','executive'],true)
        || ($role==='technician' && (int)$sessionTechnician>0 && (int)$owner===(int)$sessionTechnician);
}
function rmPanel($db,$repair) {
    $r=rmQuery($db,"SELECT media_type,COUNT(*) AS n FROM repair_attachments WHERE repair_id=? AND state NOT IN ('failed','expired') GROUP BY media_type",'i',(int)$repair)->get_result();
    $counts=['image'=>0,'video'=>0];while($x=$r->fetch_assoc()){$counts[$x['media_type']]=(int)$x['n'];}
    if(array_sum($counts)===0){return;}
    echo '<p style="margin:12px 0"><a style="display:inline-block;padding:10px 14px;background:#1e3a5f;color:#fff;border-radius:8px" href="repair_attachments.php?id='.(int)$repair.'">ดูไฟล์แนบทั้งหมด · รูป '.$counts['image'].' รูป / วิดีโอ '.$counts['video'].' คลิป</a></p>';
}

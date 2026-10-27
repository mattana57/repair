<?php
require_once __DIR__.'/auth_guard.php';
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/repair_media.php';
function rmDeny($code=403){http_response_code($code);header('Content-Type: text/plain; charset=UTF-8');exit('ไม่สามารถเปิดไฟล์แนบนี้ได้ค่ะ');}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id){rmDeny(400);}
$job=rmQuery($conn,'SELECT id,ticket_no,technician_id FROM repairs WHERE id=?','i',$id)->get_result()->fetch_assoc();
if(!$job){rmDeny(404);}
$role=strtolower((string)($_SESSION['role']??''));
$allowed=rmCanReadAttachments($role,$_SESSION['technician_id']??0,$job['technician_id']);
if(!$allowed){rmDeny();}
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
if(isset($_GET['file'])){
    $fileId=filter_var($_GET['file'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$fileId){rmDeny(400);}
    $file=rmQuery($conn,"SELECT * FROM repair_attachments WHERE id=? AND repair_id=? AND state='ready'",'ii',$fileId,$id)->get_result()->fetch_assoc();
    if(!$file || !preg_match('/^[a-f0-9]{48}$/D',$file['storage_name'])){rmDeny(404);}
    $path=rmDirectory().'/'.$file['storage_name'];if(!is_file($path)){rmDeny(404);}
    session_write_close();$size=filesize($path);$start=0;$end=$size-1;
    if(isset($_SERVER['HTTP_RANGE'])){
        $range=$_SERVER['HTTP_RANGE'];
        if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$m) || ($m[1]==='' && $m[2]==='')){header('Content-Range: bytes */'.$size);rmDeny(416);}
        if($m[1]===''){$length=(int)$m[2];$start=max(0,$size-$length);if($length<=0){rmDeny(416);}}
        else{$start=(int)$m[1];if($m[2]!==''){$end=min($end,(int)$m[2]);}}
        if($start>$end || $start>=$size){header('Content-Range: bytes */'.$size);rmDeny(416);}
        http_response_code(206);header("Content-Range: bytes $start-$end/$size");
    }
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm'][$file['mime_type']]??'bin';
    header('Content-Type: '.$file['mime_type']);header('Content-Disposition: inline; filename="attachment-'.$fileId.'.'.$ext.'"');header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));
    $handle=fopen($path,'rb');fseek($handle,$start);$left=$end-$start+1;
    while($left>0 && !feof($handle) && !connection_aborted()){$chunk=fread($handle,min(65536,$left));if($chunk===false || $chunk===''){break;}echo $chunk;$left-=strlen($chunk);}
    fclose($handle);exit;
}
$staffOnly = ($_GET['scope'] ?? '') === 'staff';
$filter = $staffOnly ? "line_user_id LIKE 'staff:%'" : "line_user_id NOT LIKE 'staff:%'";
$rows=rmQuery($conn,"SELECT id,media_type,state,failure_code FROM repair_attachments WHERE repair_id=? AND state<>'expired' AND $filter ORDER BY id",'i',$id)->get_result();
function rmEscape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ไฟล์แนบใบงาน</title><link rel="stylesheet" href="repair-detail-ui.css?v=20261019-2"></head><body class="attachment-page"><main><p class="attachment-back"><a href="javascript:history.back()">กลับหน้าก่อนหน้า</a></p><header class="attachment-header"><p class="attachment-eyebrow"><?php echo $staffOnly ? 'ไฟล์ผลการดำเนินงาน' : 'ไฟล์ที่ผู้แจ้งส่ง'; ?></p><h1>ไฟล์แนบใบงาน<br><span class="attachment-ticket"><?php echo rmEscape($job['ticket_no']);?></span></h1><p class="attachment-caption"><?php echo $staffOnly ? 'รูปและวิดีโอที่เจ้าหน้าที่เพิ่ม' : 'รูปและวิดีโอที่ผู้แจ้งส่ง'; ?></p></header><div class="attachment-grid">
<?php while($file=$rows->fetch_assoc()):$url='repair_attachments.php?id='.(int)$id.'&amp;file='.(int)$file['id'];?><article class="attachment-card">
<?php if($file['state']==='ready'):if($file['media_type']==='image'):?><a href="<?php echo $url;?>" target="_blank" rel="noopener"><img loading="lazy" src="<?php echo $url;?>" alt="รูปประกอบใบงาน"></a><?php else:?><video controls preload="metadata" playsinline src="<?php echo $url;?>"></video><?php endif;?>
<p><a href="<?php echo $url;?>" target="_blank" rel="noopener">เปิด<?php echo $file['media_type']==='image'?'รูป':'วิดีโอ';?>เต็ม</a></p>
<?php elseif($file['state']==='failed'):?><p>รับไฟล์นี้ไม่สำเร็จ<?php echo $file['failure_code']==='too_large'?' เนื่องจากเกินขนาดที่กำหนด':'';?>ค่ะ</p>
<?php else:?><p>กำลังรับ<?php echo $file['media_type']==='image'?'รูป':'วิดีโอ';?>ค่ะ กรุณาเปิดหน้านี้ใหม่ภายหลัง</p><?php endif;?></article><?php endwhile;?>
</div></main></body></html>

<?php
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/repair_media.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
function staffViewFail($code=403) {http_response_code($code);header('Content-Type: text/plain; charset=utf-8');exit('ไม่สามารถเปิดไฟล์ได้ค่ะ กรุณากดปุ่มดูไฟล์ใน LINE อีกครั้ง');}
$token=$_GET['token']??'';
if(!is_string($token)||!preg_match('/\A[a-f0-9]{64}\z/D',$token))staffViewFail();
$hash=hash('sha256',$token);
$stmt=$conn->prepare('SELECT g.repair_id FROM staff_media_view_grants g JOIN repairs r ON r.id=g.repair_id AND (BINARY r.line_user_id=BINARY g.line_user_id OR EXISTS (SELECT 1 FROM repair_followers f WHERE f.repair_id=r.id AND BINARY f.line_user_id=BINARY g.line_user_id)) WHERE g.token_hash=? AND g.expires_at>UTC_TIMESTAMP() AND r.deleted_at IS NULL');
if(!$stmt)staffViewFail(500);$stmt->bind_param('s',$hash);$stmt->execute();$grant=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$grant)staffViewFail();
$id=(int)$grant['repair_id'];
if(isset($_GET['file'])) {
    $fileId=filter_var($_GET['file'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$fileId)staffViewFail(404);
    $stmt=$conn->prepare("SELECT storage_name,mime_type FROM repair_attachments WHERE id=? AND repair_id=? AND state='ready' AND line_user_id LIKE 'staff:%'");
    if(!$stmt)staffViewFail(500);$stmt->bind_param('ii',$fileId,$id);$stmt->execute();$f=$stmt->get_result()->fetch_assoc();$stmt->close();
    $mime=$f['mime_type']??'';$allowed=['image/jpeg','image/png','image/webp','video/mp4','video/quicktime','video/webm'];
    if(!$f||!in_array($mime,$allowed,true)||!preg_match('/\A[a-f0-9]{48}\z/D',(string)$f['storage_name']))staffViewFail(404);
    try{$path=rmDirectory().'/'.$f['storage_name'];}catch(Throwable $e){staffViewFail(500);}if(!is_file($path))staffViewFail(404);
    $size=filesize($path);$start=0;$end=$size-1;
    if(isset($_SERVER['HTTP_RANGE'])){$range=$_SERVER['HTTP_RANGE'];if(!preg_match('/\Abytes=(\d*)-(\d*)\z/D',$range,$m)||($m[1]===''&&$m[2]===''))staffViewFail(416);if($m[1]===''){$length=(int)$m[2];if($length<=0)staffViewFail(416);$start=max(0,$size-$length);}else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}if($start>$end||$start>=$size)staffViewFail(416);http_response_code(206);header("Content-Range: bytes $start-$end/$size");}
    header('Content-Type: '.$mime);header('Content-Disposition: inline');header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));$fh=fopen($path,'rb');if(!$fh)exit;fseek($fh,$start);$left=$end-$start+1;while($left>0&&!feof($fh)&&!connection_aborted()){$chunk=fread($fh,min(65536,$left));if($chunk===false||$chunk==='')break;echo $chunk;$left-=strlen($chunk);}fclose($fh);exit;
}
$stmt=$conn->prepare("SELECT id,media_type FROM repair_attachments WHERE repair_id=? AND state='ready' AND line_user_id LIKE 'staff:%' ORDER BY id DESC");
if(!$stmt)staffViewFail(500);$stmt->bind_param('i',$id);$stmt->execute();$rows=$stmt->get_result();
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ไฟล์ผลการดำเนินงาน</title><body style="font:17px sans-serif;line-height:1.5;padding:18px;color:#1e3a5f"><h2>ไฟล์ผลการดำเนินงาน</h2>';
if($rows->num_rows===0)echo '<p>ยังไม่มีไฟล์ค่ะ</p>';
while($row=$rows->fetch_assoc()){$url='?token='.$token.'&amp;file='.(int)$row['id'];if($row['media_type']==='image')echo '<p><a href="'.$url.'"><img src="'.$url.'" alt="รูปผลการดำเนินงาน" style="max-width:100%;max-height:400px"></a></p>';else echo '<p><video controls preload="metadata" playsinline src="'.$url.'" style="max-width:100%"></video></p>';}
echo '</body></html>';$stmt->close();

<?php
// Required by dashboard_features.php after authentication. No standalone endpoint.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbs_h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function mbs_period_rows(array $rows,$key,$dateColumn='created_at') {
    $range=mbs_range($key);$get=static function($part)use($key){$v=$_GET['mbs_'.$key.'_'.$part]??'all';return is_string($v)?$v:'all';};$d=$get('day');$m=$get('month');$y=$get('year');
    return array_values(array_filter($rows,static function($r)use($range,$d,$m,$y,$dateColumn){
        $date=(string)($r[$dateColumn]??'');
        if($range)return $date >= $range[0].' 00:00:00' && $date < $range[1].' 00:00:00';
        foreach([[$d,8,2],[$m,5,2],[$y,0,4]] as [$v,$offset,$len])if(ctype_digit((string)$v)&&(int)$v>0&&(int)substr($date,$offset,$len)!==(int)$v)return false;
        return true;
    }));
}
function mbs_drill_dispatch($db) {
    if(!isset($_GET['mbs_drill']))return;
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
    try{
        $data=mbs_feature_payload($db);$kind=$_GET['mbs_drill'];$value=$_GET['mbs_value']??'';
        if(!is_string($kind)||!is_string($value)||!in_array($kind,['reporter','equipment','location','technician','reviews'],true))throw new RuntimeException('คำขอไม่ถูกต้อง');
        $rows=mbs_period_rows($data['repairs'],'overview');
        if($kind==='reviews'){
            $reviewPeriod=mbs_range('reviews')!==null;foreach(['day','month','year'] as $part){$v=$_GET['mbs_reviews_'.$part]??'all';if(is_string($v)&&ctype_digit($v)&&(int)$v>0)$reviewPeriod=true;}if($reviewPeriod)$rows=$data['repairs'];
            if($data['role']==='technician')throw new RuntimeException('ไม่อนุญาต');
            $noReview=($_GET['mbs_no_review']??'')==='1';
            $rows=array_values(array_filter($rows,static function($r)use($value,$noReview){
                $rated=(int)($r['rating']??0)>=1&&(int)($r['rating']??0)<=5;
                $comment=trim((string)($r['review_comment']??''));$reviewed=$rated||($comment!==''&&$comment!=='-');
                return (!$value||(int)$r['technician_id']===(int)$value)&&($noReview?(!$reviewed&&($r['status']??'')==='ซ่อมเสร็จแล้ว'):$reviewed);
            }));
            foreach($rows as &$reviewRow)$reviewRow['mbs_review_at']=$noReview?($reviewRow['completed_at']??null):mbs_review_time($reviewRow);unset($reviewRow);
            $rows=mbs_period_rows($rows,'reviews','mbs_review_at');
            if(($_GET['mbs_comments']??'')==='1')$rows=array_values(array_filter($rows,static function($r){$c=trim((string)($r['review_comment']??''));return $c!==''&&$c!=='-';}));
            $star=filter_var($_GET['mbs_star']??null,FILTER_VALIDATE_INT);if($star>=1&&$star<=5)$rows=array_values(array_filter($rows,static function($r)use($star){return (int)$r['rating']===$star;}));
            usort($rows,static function($a,$b){return strcmp((string)($b['mbs_review_at']??''),(string)($a['mbs_review_at']??''))?:((int)$b['id']-(int)$a['id']);});
        }else{
            $column=['reporter'=>'line_user_id','equipment'=>'equipment_type','location'=>'location','technician'=>'technician_id'][$kind];
            $rows=array_values(array_filter($rows,static function($r)use($column,$value,$kind){return $kind==='technician'?(int)$r[$column]===(int)$value:trim((string)($r[$column]??''))===trim($value);}));
            foreach(['equipment_type','location','technician_id','problem_desc','status'] as $column){$v=$_GET['mbs_match_'.$column]??null;if(is_string($v)&&$v!=='')$rows=array_values(array_filter($rows,static function($r)use($column,$v){return trim((string)($r[$column]??''))===trim($v);}));}
        }
        $total=count($rows);$pages=max(1,(int)ceil($total/50));$page=max(1,min($pages,(int)($_GET['mbs_page']??1)));$slice=array_slice($rows,($page-1)*50,50);
        echo '<p class="mbs-result-count">'.number_format($total).($kind==='reviews'?(!empty($noReview)?' รายการที่ยังไม่มีรีวิว':' รีวิว'):' รายการแจ้งซ่อม').'</p>';
        if($kind==='reviews'){
            $reviewPeriod=mbs_range('reviews')!==null;foreach(['day','month','year'] as $part){$v=$_GET['mbs_reviews_'.$part]??'all';if(is_string($v)&&ctype_digit($v)&&(int)$v>0)$reviewPeriod=true;}if($reviewPeriod)$rows=$data['repairs'];
            if(!$total)echo '<p class="mbs-empty">'.($noReview?'ไม่พบงานที่ยังไม่มีรีวิวในช่วงเวลานี้':'ยังไม่มีรีวิว').'</p>';
            foreach($slice as $r){
                $stars=(int)($r['rating']??0);$validStars=$stars>=1&&$stars<=5;
                $starHtml=$validStars?str_repeat('★',$stars).'<span class="mbs-unfilled-stars">'.str_repeat('★',5-$stars).'</span>':($noReview?'<small>ยังไม่มีรีวิว</small>':'<span class="mbs-unfilled-stars">★★★★★</span><small>ยังไม่ได้ให้คะแนน</small>');
                $comment=trim((string)($r['review_comment']??''));$hasComment=$comment!==''&&$comment!=='-';
                $media=function_exists('dm_icons')?dm_icons($db,$r):'';
                $repairUrl=($data['role']==='executive'?'view_repair.php':'update_repair.php').'?id='.(int)$r['id'].'&source=tech_reviews';
                $openLink='<a class="mbs-review-open" href="'.mbs_h($repairUrl).'" target="_blank" rel="noopener" title="เปิดใบงานในแท็บใหม่" aria-label="เปิดใบงาน '.mbs_h($r['ticket_no']).' ในแท็บใหม่"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 4h6v6m0-6L10 14M10 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-4"/></svg></a>';
                echo '<article class="mbs-review" role="link" tabindex="0" data-mbs-review-id="'.(int)$r['id'].'" aria-label="เปิดใบงาน '.mbs_h($r['ticket_no']).' ในแท็บใหม่"><div class="mbs-classic-review-avatar"><i class="fas fa-user" aria-hidden="true"></i></div><div class="mbs-classic-review-content"><div class="mbs-classic-review-row-head"><div><div class="mbs-classic-review-name-line"><strong class="mbs-classic-review-name">'.mbs_h($r['reporter_label']).'</strong><span class="mbs-stars" aria-label="'.($validStars?$stars.' จาก 5 ดาว':'ยังไม่มีรีวิว').'">'.$starHtml.'</span>'.$media.'</div><time>'.mbs_h(mbs_date_display(mbs_review_time($r))).'</time></div><div class="mbs-classic-review-link-tools">'.$openLink.'</div></div><p'.($hasComment?'':' class="mbs-no-review-text"').'>'.($hasComment?nl2br(mbs_h($comment)):'- ไม่มีข้อความรีวิว -').'</p><span class="mbs-classic-tech-badge"><i class="fas fa-tools" aria-hidden="true"></i>เจ้าหน้าที่: '.mbs_h($r['technician_name']).'</span></div></article>';
            }
        }else{
            echo '<div class="mbs-table-scroll"><table class="mbs-table"><thead><tr><th>เลขที่งาน</th><th>อุปกรณ์ / ปัญหา</th><th>สถานที่</th><th>ผู้แจ้ง</th><th>เจ้าหน้าที่</th><th>สถานะ</th><th>รับงาน</th><th>ปิดงาน</th><th>ระยะเวลาทำงาน</th></tr></thead><tbody>';
            foreach($slice as $r){echo '<tr><td>'.mbs_h($r['ticket_no']).'</td><td>'.mbs_h($r['equipment_type']).'<small>'.mbs_h($r['problem_desc']).'</small></td><td>'.mbs_h($r['location']).'</td><td>'.mbs_h($r['reporter_label']).(!empty($r['line_user_id'])?'<br><button type="button" class="mbs-history-link" data-mbs-reporter="'.mbs_h($r['line_user_id']).'">ดูประวัติผู้แจ้ง</button>':'').'</td><td>'.mbs_h($r['technician_name']).'</td><td>'.mbs_h($r['status']).'</td><td>'.mbs_h(mbs_date_display($r['received_at'])).'</td><td>'.mbs_h($r['completed_at']?mbs_date_display($r['completed_at']):'ยังไม่ปิดงาน').'</td><td>'.mbs_h(mbs_duration($r['received_at'],$r['completed_at'])).'</td></tr>';}
            if(!$total)echo '<tr><td colspan="9">ไม่พบรายการในช่วงเวลานี้</td></tr>';
            echo '</tbody></table></div>';
        }
        echo '<nav class="mbs-pages" aria-label="แบ่งหน้ารายการ">';
        foreach(array_unique([1,max(1,$page-1),$page,min($pages,$page+1),$pages]) as $p){$q=$_GET;$q['mbs_page']=$p;echo '<a '.($p===$page?'aria-current="page" ':'').'href="?'.mbs_h(http_build_query($q)).'">'.$p.'</a>';}
        echo '<span>หน้า '.$page.' / '.$pages.' · หน้าละ 50 รายการ</span></nav>';
    }catch(Throwable $e){http_response_code(400);echo '<p role="alert">โหลดข้อมูลไม่สำเร็จ กรุณาลองใหม่</p>';error_log('Dashboard drill: '.$e->getMessage());}exit;
}

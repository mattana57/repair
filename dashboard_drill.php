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
        if(!is_string($kind)||!is_string($value)||!in_array($kind,['reporter','equipment','location','technician','reviews','review_jobs'],true))throw new RuntimeException('คำขอไม่ถูกต้อง');
        $cardHistory=$data['role']==='admin' && $kind==='technician' && ($_GET['mbs_card_history']??'')==='1';
        $rows=$cardHistory?$data['repairs']:mbs_period_rows($data['repairs'],'overview');
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
        }elseif($kind==='review_jobs'){
            if(!in_array($data['role'],['admin','executive'],true))throw new RuntimeException('ไม่อนุญาต');
            $reviewPeriod=mbs_range('reviews')!==null;
            foreach(['day','month','year'] as $part){$v=$_GET['mbs_reviews_'.$part]??'all';if(is_string($v)&&ctype_digit($v)&&(int)$v>0)$reviewPeriod=true;}
            if($reviewPeriod)$rows=$data['repairs'];
            $rows=mbs_period_rows($rows,'reviews','created_at');
            $department=$_GET['mbs_jobs_department']??'';
            if(!is_string($department))throw new RuntimeException('ข้อมูลฝ่ายงานไม่ถูกต้อง');
            $ids=[];foreach($data['technicians'] as $t)if($department===''||(trim((string)($t['department']??''))?:'ฝ่ายงานทั่วไป')===$department)$ids[]=(int)$t['id'];
            $rows=array_values(array_filter($rows,static function($r)use($ids,$value){$id=(int)($r['technician_id']??0);return in_array($id,$ids,true)&&($value===''||$id===(int)$value);}));
            $reporter=$_GET['mbs_jobs_reporter']??'';$repair=$_GET['mbs_jobs_repair_id']??'';
            if(!is_string($reporter)||!is_string($repair))throw new RuntimeException('ข้อมูลผู้แจ้งไม่ถูกต้อง');
            if($reporter!=='')$rows=array_values(array_filter($rows,static function($r)use($reporter){return trim((string)($r['line_user_id']??''))===$reporter;}));
            elseif($repair!=='')$rows=array_values(array_filter($rows,static function($r)use($repair){return (int)$r['id']===(int)$repair;}));
        }else{
            $column=['reporter'=>'line_user_id','equipment'=>'equipment_type','location'=>'location','technician'=>'technician_id'][$kind];
            $rows=array_values(array_filter($rows,static function($r)use($column,$value,$kind){return $kind==='technician'?(int)$r[$column]===(int)$value:trim((string)($r[$column]??''))===trim($value);}));
            foreach(['equipment_type','location','technician_id','line_user_id','problem_desc','status'] as $column){$v=$_GET['mbs_match_'.$column]??null;if(is_string($v)&&$v!=='')$rows=array_values(array_filter($rows,static function($r)use($column,$v){return trim((string)($r[$column]??''))===trim($v);}));}
        }
        if (in_array($kind,['reporter','equipment','location','technician'],true)) {
            $query=$_GET['mbs_q']??'';
            if(!is_string($query)||strlen($query)>1000)throw new RuntimeException('คำค้นไม่ถูกต้อง');
            require_once __DIR__.'/dashboard_analytics_search.php';
            $rows=mbs_analytics_search_rows($rows,$data['technicians'],$query);
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
        }elseif($kind==='review_jobs'){
            if(!$total)echo '<p class="mbs-empty">ไม่พบงานของผู้แจ้งในช่วงเวลาที่เลือก</p>';
            echo '<div class="mbs-job-list">';
            foreach($slice as $r){
                $url=($data['role']==='executive'?'view_repair.php':'update_repair.php').'?id='.(int)$r['id'].'&source=tech_reviews';
                $status=trim((string)($r['status']??''));
                $tone=['รอรับเรื่อง'=>'pending','กำลังดำเนินการ'=>'progress','ซ่อมเสร็จแล้ว'=>'complete'][$status]??'other';
                echo '<article class="mbs-job-card"><header><a href="'.mbs_h($url).'" target="_blank" rel="noopener">'.mbs_h($r['ticket_no']).'</a><span class="mbs-job-status mbs-job-'.$tone.'">'.mbs_h($status).'</span></header><h3>'.mbs_h($r['equipment_type']).'</h3><p class="mbs-job-problem">'.nl2br(mbs_h($r['problem_desc'])).'</p><dl class="mbs-job-facts"><div><dt>ผู้แจ้งซ่อม</dt><dd>'.mbs_h($r['reporter_label']).'</dd></div><div><dt>สถานที่</dt><dd>'.mbs_h($r['location']).'</dd></div><div><dt>เจ้าหน้าที่</dt><dd>'.mbs_h($r['technician_name']).'</dd></div></dl><dl class="mbs-job-times"><div><dt>รับงาน</dt><dd>'.mbs_h(!empty($r['received_at'])?mbs_date_display($r['received_at']):'ยังไม่รับงาน').'</dd></div><div><dt>ปิดงาน</dt><dd>'.mbs_h(!empty($r['completed_at'])?mbs_date_display($r['completed_at']):'ยังไม่ปิดงาน').'</dd></div><div><dt>ระยะเวลาทำงาน</dt><dd>'.mbs_h(mbs_duration($r['received_at']??null,$r['completed_at']??null)).'</dd></div></dl><footer>'.(!empty($r['line_user_id'])?'<button type="button" class="mbs-history-link" data-mbs-repair-id="'.(int)$r['id'].'" data-mbs-reporter="'.mbs_h($r['line_user_id']).'">ดูประวัติผู้แจ้ง</button>':'').'<a class="mbs-job-detail" href="'.mbs_h($url).'" target="_blank" rel="noopener">ดูรายละเอียดงาน ↗</a></footer></article>';
            }
            echo '</div>';
        }elseif(in_array($kind,['equipment','location','technician','reporter'],true)){
            require_once __DIR__.'/dashboard_analytics_view.php';
            mbs_render_analytics_table($slice,$total,$data['role'],$kind);
        }else{
            echo '<div class="mbs-table-scroll"><table class="mbs-table"><thead><tr><th>เลขที่งาน</th><th>อุปกรณ์ / ปัญหา</th><th>สถานที่</th><th>ผู้แจ้ง</th><th>เจ้าหน้าที่</th><th>สถานะ</th><th>รับงาน</th><th>ปิดงาน</th><th>ระยะเวลาทำงาน</th></tr></thead><tbody>';
            foreach($slice as $r){echo '<tr><td>'.($kind==='review_jobs'?'<a class="mbs-history-link" href="'.($data['role']==='executive'?'view_repair.php':'update_repair.php').'?id='.(int)$r['id'].'&amp;source=tech_reviews" target="_blank" rel="noopener">'.mbs_h($r['ticket_no']).'</a>':mbs_h($r['ticket_no'])).'</td><td>'.mbs_h($r['equipment_type']).'<small>'.mbs_h($r['problem_desc']).'</small></td><td>'.mbs_h($r['location']).'</td><td>'.mbs_h($r['reporter_label']).(!empty($r['line_user_id'])?'<br><button type="button" class="mbs-history-link" data-mbs-repair-id="'.(int)$r['id'].'" data-mbs-reporter="'.mbs_h($r['line_user_id']).'">ดูประวัติผู้แจ้ง</button>':'').'</td><td>'.mbs_h($r['technician_name']).'</td><td>'.mbs_h($r['status']).'</td><td>'.mbs_h(mbs_date_display($r['received_at'])).'</td><td>'.mbs_h($r['completed_at']?mbs_date_display($r['completed_at']):'ยังไม่ปิดงาน').'</td><td>'.mbs_h(mbs_duration($r['received_at'],$r['completed_at'])).'</td></tr>';}
            if(!$total)echo '<tr><td colspan="9">ไม่พบรายการในช่วงเวลานี้</td></tr>';
            echo '</tbody></table></div>';
        }
        if(in_array($kind,['reporter','equipment','location','technician'],true)){
            require_once __DIR__.'/dashboard_analytics_pagination.php';
            mbs_render_analytics_pagination($total,$page,$pages);
        }else{
        echo '<nav class="mbs-pages" aria-label="แบ่งหน้ารายการ">';
        foreach(array_unique([1,max(1,$page-1),$page,min($pages,$page+1),$pages]) as $p){$q=$_GET;$q['mbs_page']=$p;echo '<a '.($p===$page?'aria-current="page" ':'').'href="?'.mbs_h(http_build_query($q)).'">'.$p.'</a>';}
        echo '<span>หน้า '.$page.' / '.$pages.' · หน้าละ 50 รายการ</span></nav>';
        }
    }catch(Throwable $e){http_response_code(400);echo '<p role="alert">โหลดข้อมูลไม่สำเร็จ กรุณาลองใหม่</p>';error_log('Dashboard drill: '.$e->getMessage());}exit;
}

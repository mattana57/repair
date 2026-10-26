<?php
// Shared dashboard features. Include only after auth_guard and role checks.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbs_event_date($value) {
    if (!is_string($value) || !preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/D', $value)) return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Bangkok'));
    return $d && $d->format('Y-m-d') === $value ? $d : null;
}
function mbs_range($key) {
    $a = mbs_event_date($_GET['mbs_'.$key.'_start'] ?? null);
    $b = mbs_event_date($_GET['mbs_'.$key.'_end'] ?? null);
    if (!$a || !$b || $a > $b) return null;
    return [$a->format('Y-m-d'), $b->modify('+1 day')->format('Y-m-d')];
}
function mbs_range_clause($key, $column, &$types, array &$values) {
    if (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_.]*\z/D',$column)) throw new InvalidArgumentException('Invalid date column');
    $r = mbs_range($key); if (!$r) return '';
    $types .= 'ss'; $values[]=$r[0].' 00:00:00'; $values[]=$r[1].' 00:00:00';
    return " AND $column >= ? AND $column < ?";
}
function mbs_range_literal($key, $column) {
    if (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_.]*\z/D',$column)) throw new InvalidArgumentException('Invalid date column');
    $r=mbs_range($key); if(!$r)return '';
    // Both strings are strict validated calendar dates, never raw SQL input.
    return " AND $column >= '{$r[0]} 00:00:00' AND $column < '{$r[1]} 00:00:00'";
}
function mbs_overview_sql($column='created_at') {
    $sql=mbs_range_literal('overview',$column);if($sql!=='')return $sql;
    foreach(['day'=>['DAY',1,31],'month'=>['MONTH',1,12],'year'=>['YEAR',1000,9999]] as $part=>$rule){$v=$_GET['mbs_overview_'.$part]??'all';if(is_string($v)&&ctype_digit($v)&&(int)$v>=$rule[1]&&(int)$v<=$rule[2])$sql.=' AND '.$rule[0].'('.$column.')='.(int)$v;}
    return $sql;
}
function mbs_range_label($key) {
    $r=mbs_range($key);if(!$r)return '';
    return mbs_event_date($r[0])->format('d/m/Y').' – '.mbs_event_date($r[1])->modify('-1 day')->format('d/m/Y');
}
function mbs_active_repair_sql($alias='r') {
    if(!in_array($alias,['r','repairs'],true))throw new InvalidArgumentException('Invalid repair alias');
    return "$alias.deleted_at IS NULL AND ($alias.technician_id IS NULL OR $alias.technician_id=0 OR EXISTS (SELECT 1 FROM technicians mbs_live_tech WHERE mbs_live_tech.id=$alias.technician_id AND mbs_live_tech.deleted_at IS NULL))";
}
function mbs_duration($start,$end=null) {
    try {
        if (!$start || substr((string)$start,0,4)==='0000') return $end?'ไม่ทราบเวลารับงานเดิม':'ยังไม่รับงาน';
        $tz=new DateTimeZone('Asia/Bangkok');$a=new DateTimeImmutable($start,$tz);
        $hasEnd=$end && substr((string)$end,0,4)!=='0000';$b=$hasEnd?new DateTimeImmutable($end,$tz):new DateTimeImmutable('now',$tz);
        $seconds=$b->getTimestamp()-$a->getTimestamp(); if($seconds<0)return 'ตรวจสอบข้อมูลเวลา';
        $m=(int)floor($seconds/60);$d=intdiv($m,1440);$h=intdiv($m%1440,60);
        return ($hasEnd?'':'กำลังดำเนินการ · ').($d?$d.' วัน ':'').($h?$h.' ชม. ':'').($m%60).' นาที';
    } catch(Throwable $e){return 'ไม่ทราบระยะเวลา';}
}
function mbs_date_display($raw) {
    if(!$raw || substr((string)$raw,0,4)==='0000')return 'ไม่ทราบวันเวลาเดิม';
    try{return (new DateTimeImmutable($raw,new DateTimeZone('Asia/Bangkok')))->format('d/m/Y H:i');}catch(Throwable $e){return 'ไม่ทราบวันเวลาเดิม';}
}
function mbs_review_time(array $row) {
    foreach (['rated_at','completed_at'] as $column) {
        $value=$row[$column]??null;
        if(is_string($value)&&$value!==''&&substr($value,0,4)!=='0000')return $value;
    }
    return null;
}
function mbs_prepare_range_dates() {
    $groups=[
        'table'=>['pg_repairs_day','pg_repairs_month','pg_repairs_year','er_day','er_month','er_year'],
        'history'=>['pg_history_day','pg_history_month','pg_history_year','eh_day','eh_month','eh_year'],
        'technician-history'=>['hd','hm','hy'],
        'accountActivity'=>['lh_day','lh_month','lh_year'],
        'login'=>['pg_logs_day','pg_logs_month','pg_logs_year'],
        'trash'=>['trash_day','trash_month','trash_year'],
        'report'=>['day','month','year']
    ];
    foreach($groups as $key=>$fields)if(mbs_range($key))foreach($fields as $field)$_GET[$field]=$key==='report'?'0':'all';
}
mbs_prepare_range_dates();
function mbs_feature_rows($db,$sql,$types='',array $args=[]) {
    $s=$db->prepare($sql);if(!$s)throw new RuntimeException('ไม่สามารถเตรียมข้อมูลได้');
    try{if($types!==''&&!$s->bind_param($types,...$args))throw new RuntimeException('ไม่สามารถเตรียมข้อมูลได้');if(!$s->execute())throw new RuntimeException('ไม่สามารถอ่านข้อมูลได้');$r=$s->get_result();if(!$r)throw new RuntimeException('ไม่สามารถอ่านข้อมูลได้');return $r->fetch_all(MYSQLI_ASSOC);}finally{$s->close();}
}
function mbs_feature_payload($db) {
    $role=strtolower((string)($_SESSION['role']??''));
    if(!in_array($role,['admin','executive','technician'],true))throw new RuntimeException('ไม่อนุญาต');
    $where=mbs_active_repair_sql('r');$types='';$args=[];
    if($role==='technician'){
        $id=(int)($_SESSION['technician_id']??0);if($id<1)throw new RuntimeException('ไม่พบเจ้าหน้าที่');
        $where.=" AND (r.technician_id=? OR (COALESCE(r.technician_id,0)=0 AND TRIM(r.status)='รอรับเรื่อง'))";$types='i';$args=[$id];
    }
    $rows=mbs_feature_rows($db,"SELECT r.* FROM repairs r WHERE $where ORDER BY r.created_at DESC,r.id DESC",$types,$args);
    $techSql="SELECT t.*,u.id AS web_user_id,u.username AS web_username,u.created_at AS registered_at,u.is_active AS web_active,
        (SELECT lp.line_display_name FROM line_technician lp WHERE lp.technician_id=t.id AND BINARY lp.line_user_id=BINARY t.line_user_id LIMIT 1) AS linked_line_name
        FROM technicians t LEFT JOIN users u ON u.technician_id=t.id AND LOWER(u.role)='technician' AND u.deleted_at IS NULL
        WHERE t.deleted_at IS NULL";
    $techs=mbs_feature_rows($db,$techSql.($role==='technician'?' AND t.id=?':''),$role==='technician'?'i':'',$role==='technician'?[(int)$_SESSION['technician_id']]:[]);
    $techMap=[];foreach($techs as $t)$techMap[(string)$t['id']]=$t;
    $lineMap=[];foreach(mbs_feature_rows($db,'SELECT line_user_id,line_display_name,real_name FROM line_users') as $l)$lineMap[$l['line_user_id']]=$l;
    foreach($rows as &$row){
        if(function_exists('dm_enrich'))$row=dm_enrich($db,$row);
        $l=$lineMap[(string)($row['line_user_id']??'')]??[];
        $row['reporter_label']=($l['real_name']??'')?:(($row['reporter_name']??'')?:($l['line_display_name']??'ไม่ระบุชื่อผู้แจ้ง'));
        $row['reporter_line_name']=$l['line_display_name']??($row['line_display_name']??'');
        $t=$techMap[(string)($row['technician_id']??'')]??[];
        if($t)$row['technician_name']=$t['full_name'];
    }unset($row);
    if($role!=='admin')foreach($techs as &$t){$t=array_intersect_key($t,array_flip(['id','full_name','department','position','english_name','phone','email','avatar_url']));}unset($t);
    $accounts=$role==='admin'?mbs_feature_rows($db,"SELECT id,role,created_at AS registered_at FROM users WHERE deleted_at IS NULL AND LOWER(role) IN ('admin','executive')"):[];
    return ['role'=>$role,'technicianId'=>(int)($_SESSION['technician_id']??0),'repairs'=>$rows,'technicians'=>$techs,'accounts'=>$accounts,'now'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok')))->format(DateTimeInterface::ATOM)];
}
require_once __DIR__.'/dashboard_drill.php';
function mbs_feature_dispatch($db) {
    if(isset($_GET['mbs_line_profile_data'])) {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        if(strtolower((string)($_SESSION['role']??''))!=='admin'){http_response_code(403);echo '{"error":"ไม่อนุญาต"}';exit;}
        $id=filter_var($_GET['mbs_technician_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id){http_response_code(400);echo '{"error":"ข้อมูลเจ้าหน้าที่ไม่ถูกต้อง"}';exit;}
        try {
            require_once __DIR__.'/technician_line_profile.php';
            $t=mbs_technician_line_profile($db,$id);
            if(!$t){http_response_code(404);echo '{"error":"ไม่พบเจ้าหน้าที่"}';exit;}
            echo json_encode(['technician'=>$t],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        }catch(Throwable $e){http_response_code(503);echo '{"error":"โหลดชื่อ LINE ไม่สำเร็จ"}';error_log('LINE profile read failed: '.get_class($e));}
        exit;
    }
    if(isset($_GET['mbs_line_link_data'])) {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        if(strtolower((string)($_SESSION['role']??''))!=='admin'){http_response_code(403);echo '{"error":"ไม่อนุญาต"}';exit;}
        $id=filter_var($_GET['mbs_technician_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$id){http_response_code(400);echo '{"error":"ข้อมูลเจ้าหน้าที่ไม่ถูกต้อง"}';exit;}
        try {
            $rows=mbs_feature_rows($db,"SELECT t.id,t.line_linked_at FROM technicians t WHERE t.id=? AND t.deleted_at IS NULL LIMIT 1",'i',[$id]);
            if(!$rows){http_response_code(404);echo '{"error":"ไม่พบเจ้าหน้าที่"}';exit;}
            echo json_encode(['technician'=>$rows[0]],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        }catch(Throwable $e){http_response_code(503);echo '{"error":"โหลดข้อมูลไม่สำเร็จ"}';error_log('LINE link time read failed: '.get_class($e));}
        exit;
    }
    mbs_drill_dispatch($db);
    if(isset($_GET['mbs_features_data'])){
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        try{echo json_encode(mbs_feature_payload($db),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable $e){http_response_code(503);echo '{"error":"โหลดข้อมูลไม่สำเร็จ"}';error_log('Dashboard features: '.$e->getMessage());}exit;
    }
    // Admin changes other people's account status only. Never trust read-only HTML.
    if(strtolower((string)($_SESSION['role']??''))!=='admin'||!isset($_POST['save_user']))return;
    $csrf=$_POST['csrf_token']??null;$expected=$_SESSION['csrf_token']??null;
    if(!is_string($csrf)||!is_string($expected)||$expected===''||$csrf===''||!hash_equals($expected,$csrf)){http_response_code(403);exit('คำขอไม่ถูกต้อง');}
    $role=$_POST['role']??'';$id=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!is_string($role)||!$id){http_response_code(400);exit('ข้อมูลบัญชีไม่ถูกต้อง');}
    $role=strtolower($role);
    if($role==='admin'&&$id===(int)($_SESSION['user_id']??0)){ $_POST['role']='Admin'; $_POST['admin_level']='Admin'; return; }
    $v=$_POST['is_active']??null;if(!is_string($v)||!in_array($v,['0','1'],true)){http_response_code(400);exit('สถานะบัญชีไม่ถูกต้อง');}
    $db->begin_transaction();
    try{
        if($role==='technician')$rows=mbs_feature_rows($db,"SELECT u.id,u.role FROM users u JOIN technicians t ON t.id=u.technician_id WHERE t.id=? AND t.deleted_at IS NULL AND u.deleted_at IS NULL AND LOWER(u.role)='technician' FOR UPDATE",'i',[$id]);
        elseif(in_array($role,['executive','admin'],true))$rows=mbs_feature_rows($db,'SELECT id,role FROM users WHERE id=? AND deleted_at IS NULL FOR UPDATE','i',[$id]);
        else throw new RuntimeException('ไม่อนุญาต');
        if(count($rows)!==1||strtolower($rows[0]['role'])!==$role)throw new RuntimeException('ไม่พบบัญชีที่ตรงกัน');
        $uid=(int)$rows[0]['id'];
        if($role==='admin'&&$v==='1')throw new RuntimeException('ระบบกำหนดให้มีบัญชีแอดมินหลักเพียงบัญชีเดียว');
        $s=$db->prepare('UPDATE users SET auth_version=auth_version+IF(is_active<>?,1,0),is_active=? WHERE id=?');$active=(int)$v;$s->bind_param('iii',$active,$active,$uid);if(!$s->execute())throw new RuntimeException('บันทึกสถานะไม่สำเร็จ');$s->close();$db->commit();
        if(function_exists('mbsActivityLog')){try{mbsActivityLog($db,'ACCOUNT_STATUS','user',$uid,'เปลี่ยนสถานะบัญชี '.($v==='1'?'เปิดใช้งาน':'ระงับบัญชี'));}catch(Throwable $logError){error_log('Account status activity log failed');}}
        header('Location: dashboard.php?tab='.((($_GET['tab']??'')==='team_cards')?'team_cards':'technicians'));exit;
    }catch(Throwable $e){$db->rollback();http_response_code(400);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
}
function mbs_feature_assets($db) {
    try{$data=mbs_feature_payload($db);}catch(Throwable $e){error_log('Dashboard features load failed: '.$e->getMessage());$data=['role'=>strtolower((string)($_SESSION['role']??'')),'repairs'=>[],'technicians'=>[],'accounts'=>[],'error'=>'โหลดข้อมูลไม่สำเร็จ'];}
    echo '<link rel="stylesheet" href="dashboard_reviews.css?v=20261026-3">';
    echo '<link rel="stylesheet" href="dashboard_analytics.css?v=20261026-5">';
    echo '<link rel="stylesheet" href="technician_cards.css?v=20261026-1">';
    echo '<link rel="stylesheet" href="bulk_edit_buttons.css?v=20261026-2">';
    echo '<script src="bulk_edit_menu.js?v=20261026-1" defer></script>';
    echo '<link rel="stylesheet" href="technician_manage_layout.css?v=20261026-1">';
    echo '<link rel="stylesheet" href="profile_menu_actions.css?v=20261025-1">';
    echo '<script type="application/json" id="mbsFeaturesData">'.json_encode($data,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE).'</script>';
    echo '<script src="dashboard_chart_appearance.js?v=20261025-4"></script>';
    echo '<script src="dashboard_features.js?v=20261026-20"></script>';
}

<?php
if (!defined('MBS_ADMIN_TRASH') || strtolower((string)($_SESSION['role']??''))!=='admin') {http_response_code(403);exit('ไม่อนุญาตให้เข้าถึงหน้านี้');}
function lb_active($conn,$table) {return at_has_log_archive($conn,$table)?'deleted_at IS NULL':'1=1';}
function lb_login_filters() {
    global $conn;
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok'));
    $where=' WHERE '.lb_active($conn,'login_logs');$types='';$values=[];
    $mode=tp_get('pg_logs_status','');if($mode==='all')$mode='failed';
    if($mode==='failed')$where.=" AND COALESCE(status,'') <> 'สำเร็จ'";
    elseif($mode==='success')$where.=" AND status='สำเร็จ' AND LOWER(role) IN ('admin','executive','technician')";
    elseif(in_array($mode,['admin','executive','technician'],true)){$where.=" AND status='สำเร็จ' AND LOWER(role)=?";$types.='s';$values[]=$mode;}
    foreach(['day'=>[1,31,'j'],'month'=>[1,12,'n'],'year'=>[1000,9999,'Y']] as $part=>$rule){
        $value=tp_get('pg_logs_'.$part,$now->format($rule[2]));
        if($value==='all')continue;
        if(!ctype_digit($value)||(int)$value<$rule[0]||(int)$value>$rule[1])$value=$now->format($rule[2]);
        $where.=' AND '.strtoupper($part).'(login_time)=?';$types.='i';$values[]=(int)$value;
    }
    $role=tp_get('pg_logs_role','all');if(in_array($role,['admin','executive','technician'],true)){$where.=' AND LOWER(role)=?';$types.='s';$values[]=$role;}
    $q=tp_get('pg_logs_q');if($q!==''){$where.=" AND CONCAT_WS(' ', username, role, ip_address, status, login_time, CONCAT(DATE_FORMAT(login_time, '%d/%m/'), YEAR(login_time) + 543)) LIKE ? ESCAPE '!'";$types.='s';$values[]=tp_like($q);}
    return [$where,$types,$values];
}
function lb_activity_filters($includeUser=false) {
    global $conn;
    $where=" WHERE role IN ('admin','executive','technician') AND ".lb_active($conn,'user_activity_logs');$types='';$values=[];
    $role=tp_get('pg_logs_status','');if(in_array($role,['admin','executive','technician'],true)){$where.=' AND role=?';$types.='s';$values[]=$role;}
    $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok'));
    foreach(['day'=>[1,31,'j'],'month'=>[1,12,'n'],'year'=>[1000,9999,'Y']] as $part=>$rule){
        $value=tp_get('lh_'.$part,$now->format($rule[2]));
        if($value!=='all'&&ctype_digit($value)&&(int)$value>=$rule[0]&&(int)$value<=$rule[1]){$where.=' AND '.strtoupper($part).'(created_at)=?';$types.='i';$values[]=(int)$value;}
    }
    $q=mb_substr(tp_get('lh_q',''),0,200,'UTF-8');if($q!==''){$where.=" AND CONCAT_WS(' ', username, full_name, role, summary, action_code, created_at) LIKE ? ESCAPE '!'";$types.='s';$values[]=tp_like($q);}
    $user=filter_var(tp_get('pg_logs_q','0'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($includeUser&&$user!==false){$where.=' AND user_id=?';$types.='i';$values[]=$user;}
    return [$where,$types,$values];
}
function lb_query($kind) {return $kind==='loginlog'?lb_login_filters():lb_activity_filters($kind==='activityevent');}
function lb_items($conn,$kind){
    list($where,$types,$values)=lb_query($kind);$table=$kind==='loginlog'?'login_logs':'user_activity_logs';
    $select=$kind==='activityperson'?'SELECT user_id,role,MAX(id) AS upto_id':'SELECT id';
    $end=$kind==='activityperson'?' GROUP BY user_id,role ORDER BY user_id,role':' ORDER BY id';
    $stmt=at_stmt($conn,$select.' FROM '.$table.$where.$end,$types,$values);$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    foreach($rows as &$row){if($kind==='activityperson'){$row['user_id']=(string)$row['user_id'];$row['upto_id']=(string)$row['upto_id'];$row['id']=$row['user_id'].':'.$row['role'];}else$row['id']=(string)$row['id'];}unset($row);
    return $rows;
}
function lb_archive($conn,$kind,array $items){
    if(!in_array($kind,['loginlog','activityperson','activityevent'],true)||!$items||array_keys($items)!==range(0,count($items)-1))throw new RuntimeException('กรุณาเลือกรายการให้ถูกต้อง');
    list($where,$types,$values)=lb_query($kind);$table=$kind==='loginlog'?'login_logs':'user_activity_logs';$seen=[];$targets=[];
    foreach($items as $item){
        if(!is_array($item))throw new RuntimeException('ข้อมูลที่เลือกไม่ถูกต้อง');
        $targetWhere=$where;$targetTypes=$types;$targetValues=$values;
        if($kind==='activityperson'){
            $user=at_positive_id($item['user_id']??null);$upto=at_positive_id($item['upto_id']??null);$role=$item['role']??null;
            if($user===false||$upto===false||!in_array($role,['admin','executive','technician'],true))throw new RuntimeException('ข้อมูลบัญชีที่เลือกไม่ถูกต้อง');
            $key=$user.':'.$role;$targetWhere.=' AND user_id=? AND role=? AND id<=?';$targetTypes.='isi';$targetValues=array_merge($targetValues,[$user,$role,$upto]);
            $marker=at_row($conn,'SELECT id FROM '.$table.$where.' AND user_id=? AND role=? AND id=? FOR UPDATE',$types.'isi',array_merge($values,[$user,$role,$upto]));
            if(!$marker)throw new RuntimeException('ประวัติที่เลือกเปลี่ยนแล้ว กรุณาเลือกใหม่');
        }else{
            $id=at_positive_id($item['id']??null);if($id===false)throw new RuntimeException('รหัสประวัติไม่ถูกต้อง');
            $key=(string)$id;$targetWhere.=' AND id=?';$targetTypes.='i';$targetValues[]=$id;
        }
        if(isset($seen[$key]))throw new RuntimeException('เลือกประวัติซ้ำ กรุณาเลือกใหม่');$seen[$key]=true;
        $stmt=at_stmt($conn,'SELECT id FROM '.$table.$targetWhere.' ORDER BY id FOR UPDATE',$targetTypes,$targetValues);$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
        if(!$rows)throw new RuntimeException('ประวัติที่เลือกไม่อยู่ในเงื่อนไขแล้ว กรุณาเลือกใหม่');
        foreach($rows as $row)$targets[(int)$row['id']]=true;
    }
    ksort($targets);foreach(array_keys($targets) as $id)at_stmt($conn,'UPDATE '.$table.' SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL','i',[$id])->close();
    return count($targets);
}
function lb_handle($conn){
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!isset($_POST['log_bulk_action']))return;
    $select=($_POST['log_bulk_action']??null)==='select_all';$started=false;
    try{
        $token=$_POST['csrf_token']??null;
        if(!is_string($token)||!is_string($_SESSION['csrf_token']??null)||!hash_equals($_SESSION['csrf_token'],$token))throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้า');
        $kind=$_POST['log_bulk_kind']??null;$action=$_POST['log_bulk_action'];
        if(!in_array($action,['select_all','move'],true)||!in_array($kind,['loginlog','activityperson','activityevent'],true))throw new RuntimeException('คำขอไม่ถูกต้อง');
        $table=$kind==='loginlog'?'login_logs':'user_activity_logs';
        if(!at_has_log_archive($conn,$table))throw new RuntimeException('กรุณารันไฟล์ SQL เพิ่มสถานะถังขยะของประวัติก่อนใช้งาน');
        if(!$select&&($_POST['log_bulk_confirm']??null)!=='1')throw new RuntimeException('กรุณายืนยันการย้ายประวัติเข้าถังขยะ');
        $engine=at_row($conn,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?','s',[$table]);
        if(!$engine||strtoupper((string)$engine['ENGINE'])!=='INNODB')throw new RuntimeException('ตารางประวัติต้องรองรับ Transaction');
        if(!$conn->begin_transaction())throw new RuntimeException('เริ่มดำเนินการไม่สำเร็จ');$started=true;at_check_admin($conn);
        if($select){$items=lb_items($conn,$kind);if(!$conn->commit())throw new RuntimeException('อ่านประวัติไม่สำเร็จ');$started=false;header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');echo json_encode(['items'=>$items],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
        if(!is_string($_POST['log_bulk_items']??null))throw new RuntimeException('รายการประวัติไม่ถูกต้อง');$items=json_decode($_POST['log_bulk_items'],true,16,JSON_THROW_ON_ERROR);if(!is_array($items))throw new RuntimeException('รายการประวัติไม่ถูกต้อง');
        $count=lb_archive($conn,$kind,$items);if(!$conn->commit())throw new RuntimeException('ยืนยันผลไม่สำเร็จ กรุณาตรวจรายการก่อนลองใหม่');$started=false;
        if(function_exists('mbsActivityLog'))mbsActivityLog($conn,'TRASH_MOVE',$table,null,'ย้ายประวัติที่เลือกเข้าถังขยะ '.$count.' รายการ');
        $_SESSION['admin_trash_notice']=['type'=>'success','message'=>'ย้ายประวัติเข้าถังขยะแล้ว '.$count.' รายการ โดยไม่ลบบัญชีผู้ใช้'];
    }catch(Throwable $error){if($started){try{$conn->rollback();}catch(Throwable $ignored){}}$message=$error instanceof RuntimeException&&!($error instanceof mysqli_sql_exception)?$error->getMessage():'ดำเนินการไม่สำเร็จ กรุณาลองใหม่';if($select){http_response_code(400);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);exit;}$_SESSION['admin_trash_notice']=['type'=>'error','message'=>$message];}
    $query=$_GET;foreach($query as $key=>$value)if(!is_string($value)||!preg_match('/^(pg_logs_|lh_)/',$key))unset($query[$key]);$query['tab']='login_logs';
    header('Location: dashboard.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),true,303);exit;
}
lb_handle($conn);
function lb_edit_menu($kind){
    echo '<div class="ab-edit-wrap"><button type="button" class="ab-edit-button" data-lb-edit="'.$kind.'" aria-expanded="false" aria-controls="lb-menu-'.$kind.'">แก้ไข</button><div id="lb-menu-'.$kind.'" class="ab-menu hidden" role="group" aria-label="จัดการประวัติที่เลือก">';
    foreach(['choose'=>'เลือก','all'=>'เลือกทั้งหมด','none'=>'ไม่เลือก','delete'=>'ลบ'] as $action=>$label)echo '<button type="button" data-lb-action="'.$action.'" data-lb-kind="'.$kind.'"'.($action==='delete'?' disabled':'').'><i class="fas '.(['choose'=>'fa-check-square','all'=>'fa-check-double','none'=>'fa-square','delete'=>'fa-trash-alt'][$action]).'" aria-hidden="true"></i>'.$label.'</button>';
    echo '</div></div>';
}

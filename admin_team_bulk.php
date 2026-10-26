<?php
// ใช้จาก Dashboard หลัง auth_guard และ admin_trash เท่านั้น
if (!defined('MBS_ADMIN_TRASH') || strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403); exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}
function tb_selected_items($conn) {
    $items = [];
    $result = at_stmt($conn, "SELECT id, LOWER(role) AS role FROM users WHERE deleted_at IS NULL AND LOWER(role) IN ('admin','executive') AND id <> ? ORDER BY id", 'i', [(int)$_SESSION['user_id']]);
    $rows = $result->get_result();
    while ($row = $rows->fetch_assoc()) $items[] = ['kind'=>'user','id'=>(string)$row['id'],'role'=>$row['role']];
    $result->close();
    $result = at_stmt($conn, 'SELECT id FROM technicians WHERE deleted_at IS NULL ORDER BY id');
    $rows = $result->get_result();
    while ($row = $rows->fetch_assoc()) $items[] = ['kind'=>'tech','id'=>(string)$row['id'],'role'=>'technician'];
    $result->close();
    return $items;
}
function tb_move_selected($conn, array $items) {
    if (!$items || array_keys($items) !== range(0, count($items)-1)) throw new RuntimeException('กรุณาเลือกรายการให้ถูกต้อง');
    $seen=[];
    foreach ($items as &$item) {
        if (!is_array($item) || !in_array($item['kind'] ?? null, ['user','tech'], true)
            || ($id=at_positive_id($item['id'] ?? null)) === false) throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง กรุณาเลือกใหม่');
        $key=$item['kind'].':'.$id;
        if (isset($seen[$key])) throw new RuntimeException('มีรายการที่เลือกซ้ำ');
        $seen[$key]=true; $item=['kind'=>$item['kind'],'id'=>$id];
    }
    unset($item);
    usort($items, static function($a,$b){$rank=($a['kind']==='user'?0:1)<=>($b['kind']==='user'?0:1);return $rank!==0?$rank:$a['id']<=>$b['id'];});
    // ตรวจและล็อกทุกรายการก่อนเปลี่ยนข้อมูล ถ้ามีข้อผิดพลาดให้ caller rollback ทั้งชุด
    foreach ($items as $item) {
        $id=$item['id'];
        if ($item['kind']==='user') {
            if ($id===(int)$_SESSION['user_id']) throw new RuntimeException('ไม่สามารถย้ายบัญชีที่กำลังใช้งานอยู่เข้าถังขยะได้');
            $row=at_row($conn,'SELECT id, role, deleted_at FROM users WHERE id = ? FOR UPDATE','i',[$id]);
            if (!$row || $row['deleted_at']!==null || !in_array(strtolower((string)$row['role']),['admin','executive'],true)) throw new RuntimeException('สถานะบัญชีเปลี่ยนแล้ว กรุณารีเฟรชและเลือกใหม่');
        } else {
            $row=at_row($conn,'SELECT id, deleted_at FROM technicians WHERE id = ? FOR UPDATE','i',[$id]);
            if (!$row || $row['deleted_at']!==null) throw new RuntimeException('สถานะเจ้าหน้าที่เปลี่ยนแล้ว กรุณารีเฟรชและเลือกใหม่');
            $linked=at_stmt($conn,'SELECT id, role FROM users WHERE technician_id = ? FOR UPDATE','i',[$id]);
            $rows=$linked->get_result();
            while($account=$rows->fetch_assoc()) {
                if (strtolower((string)$account['role'])!=='technician' || (int)$account['id']===(int)$_SESSION['user_id']) throw new RuntimeException('บัญชีที่ผูกเจ้าหน้าที่มีประเภทไม่ถูกต้อง กรุณาตรวจสอบก่อน');
            }
            $linked->close();
        }
    }
    // ใช้การย้าย/ระงับแบบเดียวกับการลบทีละรายการเดิม ไม่ลบจริงและไม่แก้รหัสที่ผูก LINE
    foreach ($items as $item) {
        $id=$item['id'];
        if ($item['kind']==='user') {
            at_stmt($conn,'UPDATE users SET deleted_at = NOW(), is_active = 0, auth_version = auth_version + 1 WHERE id = ? AND deleted_at IS NULL','i',[$id])->close();
        } else {
            at_stmt($conn,"UPDATE users SET is_active = 0, auth_version = auth_version + 1 WHERE technician_id = ? AND LOWER(role) = 'technician'",'i',[$id])->close();
            at_stmt($conn,'UPDATE technicians SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL','i',[$id])->close();
        }
    }
    return $items;
}
function tb_handle_request($conn) {
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST' || !isset($_POST['team_bulk_action'])) return;
    $select=($_POST['team_bulk_action'] ?? null)==='select_all'; $started=false;
    try {
        $token=$_POST['csrf_token'] ?? null;
        if (!is_string($token) || $token === '' || !is_string($_SESSION['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'],$token)) throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
        if (!in_array($_POST['team_bulk_action'],['select_all','move'],true)) throw new RuntimeException('ประเภทคำขอไม่ถูกต้อง');
        if (!$select && ($_POST['team_bulk_confirm'] ?? null)!=='1') throw new RuntimeException('กรุณายืนยันการย้ายเข้าถังขยะ');
        $engines=at_stmt($conn,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','technicians')");
        $rows=$engines->get_result();
        if($rows->num_rows!==2)throw new RuntimeException('ตรวจตารางข้อมูลไม่สำเร็จ');
        while($row=$rows->fetch_assoc())if(strtoupper((string)$row['ENGINE'])!=='INNODB')throw new RuntimeException('ตารางต้องรองรับ Transaction');
        $engines->close();
        if(!$conn->begin_transaction())throw new RuntimeException('เริ่มบันทึกข้อมูลไม่ได้');
        $started=true;at_check_admin($conn);
        if($select){
            $items=tb_selected_items($conn);
            if(!$conn->commit())throw new RuntimeException('อ่านรายการไม่สำเร็จ');
            $started=false;header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
            echo json_encode(['items'=>$items,'count'=>count($items)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
        }
        if(!is_string($_POST['team_bulk_items'] ?? null))throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง');
        $items=json_decode($_POST['team_bulk_items'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($items))throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง');
        $moved=tb_move_selected($conn,$items);
        if(!$conn->commit())throw new RuntimeException('ยืนยันการบันทึกไม่สำเร็จ กรุณาตรวจรายการก่อนลองใหม่');
        $started=false;
        try{foreach($moved as $item)if(function_exists('mbsActivityLog'))mbsActivityLog($conn,'TRASH_MOVE',$item['kind']==='user'?'users':'technicians',$item['id'],'ย้ายเข้าถังขยะจากรายการที่เลือกในหน้า Team');}catch(Throwable $ignored){error_log('Selected team activity log failed');}
        $_SESSION['team_bulk_notice']=['type'=>'success','message'=>'ย้ายเข้าถังขยะแล้ว '.count($moved).' รายการ'];
    } catch(Throwable $error) {
        if($started){try{$conn->rollback();}catch(Throwable $ignored){}}
        $message=$error instanceof RuntimeException && !($error instanceof mysqli_sql_exception)?$error->getMessage():'ดำเนินการไม่สำเร็จ กรุณาตรวจสอบแล้วลองใหม่';
        if($select){http_response_code(400);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
        $_SESSION['team_bulk_notice']=['type'=>'error','message'=>$message];
    }
    $query=['tab'=>(($_POST['team_bulk_return'] ?? '')==='team_cards' ? 'team_cards' : 'technicians')];
    if(in_array($_GET['team_account_role']??null,['admin','executive'],true))$query['team_account_role']=$_GET['team_account_role'];
    header('Location: dashboard.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),true,303);exit;
}
tb_handle_request($conn);
function tb_edit_menu() {
    echo '<div class="tb-edit-wrap"><button type="button" id="tb-edit" class="tb-edit" aria-expanded="false" aria-controls="tb-menu">แก้ไข</button><div id="tb-menu" hidden role="group" aria-label="จัดการบัญชีที่เลือก">';
    foreach(['choose'=>'เลือก','all'=>'เลือกทั้งหมด','none'=>'ยกเลิก','delete'=>'ลบ'] as $action=>$label)echo '<button type="button" data-tb-action="'.$action.'"'.($action==='delete'?' disabled':'').'>'.$label.'</button>';
    echo '</div></div>';
}

function tb_notice() {
    $notice=$_SESSION['team_bulk_notice'] ?? null; unset($_SESSION['team_bulk_notice']);
    if (!is_array($notice)) return;
    $kind=($notice['type'] ?? '')==='success'?'success':'error';
    echo '<p class="tb-notice tb-notice--'.$kind.'" role="status">'.htmlspecialchars((string)($notice['message']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
}

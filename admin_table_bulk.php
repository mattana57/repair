<?php
// โหลดหลัง Guard และ contact_history_pagination.php เฉพาะ Dashboard แอดมิน
if (!defined('MBS_ADMIN_TRASH') || strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403); exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}
if (!defined('MBS_ADMIN_TABLE_BULK')) define('MBS_ADMIN_TABLE_BULK', true);

function ab_selection_rows($conn, $kind) {
    if ($kind === 'asset') return tp_query($conn, 'SELECT id FROM assets WHERE deleted_at IS NULL ORDER BY id');
    return ch_contacts_page($conn, [], true);
}
function ab_move_selected($conn, $kind, array $items) {
    if (!in_array($kind, ['asset','reporter'], true) || !$items
        || array_keys($items) !== range(0, count($items)-1)) throw new RuntimeException('กรุณาเลือกรายการให้ถูกต้อง');
    $seen=[];
    foreach ($items as &$item) {
        if (!is_array($item) || ($id=at_positive_id($item['id'] ?? null)) === false) throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง กรุณาเลือกใหม่');
        if (isset($seen[$id])) throw new RuntimeException('มีรายการที่เลือกซ้ำ');
        $seen[$id]=true; $item['id']=$id;
        if ($kind==='reporter' && (!is_string($item['line_user_id'] ?? null) || strlen($item['line_user_id'])>255)) throw new RuntimeException('รหัสบัญชี LINE ไม่ถูกต้อง');
    }
    unset($item);
    usort($items, static function($a,$b){return $a['id'] <=> $b['id'];});
    $rows=[]; $groups=[];
    foreach ($items as $item) {
        $table=$kind==='asset'?'assets':'repairs';
        $row=at_row($conn,"SELECT * FROM $table WHERE id = ? FOR UPDATE",'i',[$item['id']]);
        if (!$row || $row['deleted_at']!==null) throw new RuntimeException('สถานะข้อมูลเปลี่ยนแล้ว กรุณารีเฟรชและเลือกใหม่');
        if ($kind==='reporter') {
            $line=(string)($row['line_user_id']??'');
            if ($line!==$item['line_user_id']) throw new RuntimeException('บัญชีผู้แจ้งเปลี่ยนแล้ว กรุณาเลือกใหม่');
            $key=trim($line)!==''?'line:'.$line:'repair:'.$item['id'];
            if (isset($groups[$key])) throw new RuntimeException('เลือกประวัติของบัญชีเดียวกันซ้ำ กรุณาเลือกใหม่');
            $groups[$key]=true;
        }
        $rows[]=$row;
    }
    foreach ($rows as $row) {
        $id=(int)$row['id'];
        if ($kind==='reporter' && trim((string)($row['line_user_id']??''))!=='') {
            at_stmt($conn,'UPDATE repairs SET deleted_at = NOW(), updated_at = updated_at WHERE BINARY line_user_id = BINARY ? AND deleted_at IS NULL','s',[(string)$row['line_user_id']])->close();
        } elseif ($kind==='reporter') {
            at_stmt($conn,'UPDATE repairs SET deleted_at = NOW(), updated_at = updated_at WHERE id = ? AND deleted_at IS NULL','i',[$id])->close();
        } else {
            at_stmt($conn,'UPDATE assets SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL','i',[$id])->close();
        }
    }
    return count($rows);
}
function ab_handle_request($conn) {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !isset($_POST['table_bulk_action'])) return;
    $select=($_POST['table_bulk_action']??null)==='select_all'; $started=false;
    $kind=$_POST['table_bulk_kind']??null;
    $tab=$kind==='asset'?'assets':'users';
    try {
        $token=$_POST['csrf_token']??null;
        if (!is_string($token) || !is_string($_SESSION['csrf_token']??null) || !hash_equals($_SESSION['csrf_token'],$token)) throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
        if (!in_array($_POST['table_bulk_action'],['select_all','move'],true) || !in_array($kind,['asset','reporter'],true)) throw new RuntimeException('ประเภทคำขอไม่ถูกต้อง');
        if (!$select && ($_POST['table_bulk_confirm']??null)!=='1') throw new RuntimeException('กรุณายืนยันการย้ายเข้าถังขยะ');
        if (!$select) {
            $engines=at_stmt($conn,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','assets','repairs')");
            $result=$engines->get_result();
            if ($result->num_rows!==3) throw new RuntimeException('ตรวจตารางข้อมูลไม่สำเร็จ');
            while($engine=$result->fetch_assoc())if(strtoupper((string)$engine['ENGINE'])!=='INNODB')throw new RuntimeException('ตารางต้องรองรับ Transaction');
            $engines->close();
        }
        if (!$conn->begin_transaction()) throw new RuntimeException('เริ่มดำเนินการไม่สำเร็จ');
        $started=true; at_check_admin($conn);
        if ($select) {
            $result=ab_selection_rows($conn,$kind); $items=[];
            while($row=$result->fetch_assoc()) {
                if ((int)$row['id']<1) continue; // คงข้อจำกัดเดิม: ไม่มีใบงานก็ไม่มีประวัติให้ย้าย
                $items[]=['id'=>(string)$row['id'],'line_user_id'=>$kind==='reporter'?(string)($row['line_user_id']??''):''];
            }
            $result->free();
            if (!$conn->commit()) throw new RuntimeException('อ่านรายการไม่สำเร็จ');
            $started=false;
            header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
            echo json_encode(['items'=>$items,'count'=>count($items)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
        }
        if (!is_string($_POST['table_bulk_items']??null)) throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง');
        $items=json_decode($_POST['table_bulk_items'],true,16,JSON_THROW_ON_ERROR);
        if (!is_array($items)) throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง');
        $count=ab_move_selected($conn,$kind,$items);
        if (!$conn->commit()) throw new RuntimeException('ยืนยันการบันทึกไม่สำเร็จ กรุณาตรวจรายการก่อนลองใหม่');
        $started=false;
        try { foreach($items as $item) if(function_exists('mbsActivityLog'))mbsActivityLog($conn,'TRASH_MOVE',$kind==='asset'?'assets':'repairs',(int)$item['id'],'ย้ายเข้าถังขยะจากรายการที่เลือก'); }
        catch(Throwable $ignored){error_log('Selected table activity log failed');}
        $_SESSION['admin_trash_notice']=['type'=>'success','message'=>'ย้ายเข้าถังขยะแล้ว '.$count.' รายการ'];
    } catch (Throwable $error) {
        if($started){try{$conn->rollback();}catch(Throwable $ignored){}}
        $message=$error instanceof RuntimeException && !($error instanceof mysqli_sql_exception)?$error->getMessage():'ดำเนินการไม่สำเร็จ กรุณาตรวจสอบแล้วลองใหม่';
        if($select){http_response_code(400);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);exit;}
        $_SESSION['admin_trash_notice']=['type'=>'error','message'=>$message];
    }
    $query=['tab'=>$tab];
    foreach($tab==='users'?['pg_reporters_q','pg_reporters_page']:['pg_assets_page'] as $key)if(isset($_GET[$key])&&is_string($_GET[$key]))$query[$key]=$_GET[$key];
    header('Location: dashboard.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),true,303);exit;
}
ab_handle_request($conn);

function ab_edit_menu($kind) {
    $kind=htmlspecialchars($kind,ENT_QUOTES,'UTF-8');
    echo '<div class="ab-edit-wrap"><button type="button" class="ab-edit-button" data-ab-edit="'.$kind.'" aria-expanded="false" aria-controls="ab-menu-'.$kind.'">แก้ไข</button><div id="ab-menu-'.$kind.'" class="ab-menu hidden" role="group" aria-label="จัดการรายการที่เลือก">';
    foreach(['choose'=>'เลือก','all'=>'เลือกทั้งหมด','none'=>'ไม่เลือก','delete'=>'ลบ'] as $action=>$label)echo '<button type="button" data-ab-action="'.$action.'" data-ab-kind="'.$kind.'"'.($action==='delete'?' disabled':'').'><i class="fas '.(['choose'=>'fa-check-square','all'=>'fa-check-double','none'=>'fa-square','delete'=>'fa-trash-alt'][$action]).'" aria-hidden="true"></i>'.$label.'</button>';
    echo '</div></div>';
}

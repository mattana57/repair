<?php
// Loaded only after dashboard authentication and the existing Trash helpers.
if (!defined('MBS_ADMIN_TRASH') || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403); exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง');
}

function mbs_transactions_move($conn, array $post) {
    if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
        throw new RuntimeException('ไม่อนุญาตให้ดำเนินการ');
    }
    $token = $post['csrf_token'] ?? null;
    if (!is_string($token) || !is_string($_SESSION['csrf_token'] ?? null)
        || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
    if (($post['transactions_bulk_action'] ?? null) !== 'move' || ($post['transactions_bulk_confirm'] ?? null) !== '1') {
        throw new RuntimeException('กรุณายืนยันรายการที่ต้องการลบ');
    }
    $raw = $post['transactions_bulk_ids'] ?? null;
    if (!is_string($raw) || strlen($raw) > 100000) throw new RuntimeException('ข้อมูลรายการไม่ถูกต้อง');
    $ids = json_decode($raw, true);
    if (!is_array($ids) || !$ids || count($ids) > 5000 || array_values($ids) !== $ids) throw new RuntimeException('กรุณาเลือกรายการที่ต้องการลบ');
    foreach ($ids as &$id) {
        $id = at_positive_id(is_int($id) ? (string)$id : $id);
        if ($id === false) throw new RuntimeException('รหัสรายการไม่ถูกต้อง');
    }
    unset($id);
    $ids = array_values(array_unique($ids)); sort($ids, SORT_NUMERIC);
    $engine = at_row($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='repairs'");
    if (!$engine || strtoupper((string)$engine['ENGINE']) !== 'INNODB') throw new RuntimeException('ตารางใบงานต้องรองรับธุรกรรมก่อน');
    if (!$conn->begin_transaction()) throw new RuntimeException('เริ่มบันทึกข้อมูลไม่ได้');
    try {
        at_check_admin($conn);
        foreach ($ids as $id) {
            $row = at_row($conn, 'SELECT id FROM repairs WHERE id=? AND deleted_at IS NULL FOR UPDATE', 'i', [$id]);
            if (!$row) throw new RuntimeException('บางรายการเปลี่ยนแปลงแล้ว กรุณารีเฟรชหน้าแล้วเลือกใหม่');
            $stmt = at_stmt($conn, 'UPDATE repairs SET deleted_at=NOW(), updated_at=updated_at WHERE id=? AND deleted_at IS NULL', 'i', [$id]);
            $affected = $stmt->affected_rows; $stmt->close();
            if ($affected !== 1) throw new RuntimeException('ไม่สามารถย้ายรายการเข้าถังขยะได้');
        }
        if (!$conn->commit()) throw new RuntimeException('ยืนยันการบันทึกไม่สำเร็จ');
    } catch (Throwable $error) { $conn->rollback(); throw $error; }
    foreach ($ids as $id) {
        try { if (function_exists('mbsActivityLog')) mbsActivityLog($conn, 'TRASH_MOVE', 'repairs', $id, 'ย้ายประวัติผู้แจ้งซ่อมจาก Transactions เข้าถังขยะ'); }
        catch (Throwable $error) { error_log('Transactions Trash audit failed'); }
    }
    return count($ids);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['transactions_bulk_action'])) {
    try {
        $count = mbs_transactions_move($conn, $_POST);
        $_SESSION['transactions_bulk_notice'] = ['type'=>'success', 'message'=>'ย้ายประวัติผู้แจ้งซ่อม '.$count.' รายการเข้า Trash แล้ว สามารถกู้คืนได้'];
    } catch (Throwable $error) {
        error_log('Transactions Trash move failed: '.$error->getMessage());
        $_SESSION['transactions_bulk_notice'] = ['type'=>'error', 'message'=>$error instanceof RuntimeException && !($error instanceof mysqli_sql_exception) ? $error->getMessage() : 'บันทึกไม่สำเร็จ กรุณารีเฟรชหน้าแล้วตรวจสอบรายการ'];
    }
    $query = ['tab'=>'repairs'];
    foreach ($_GET as $key=>$value) {
        if (is_string($value) && (preg_match('/^pg_repairs_(page|q|day|month|year|status)$/D', $key) || preg_match('/^mbs_table_(start|end|day|month|year)$/D', $key))) $query[$key] = $value;
    }
    header('Location: dashboard.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986), true, 303); exit;
}

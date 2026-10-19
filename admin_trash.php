<?php
// เรียกจาก dashboard.php หลังผ่าน Guard และตรวจบทบาทผู้ดูแลแล้วเท่านั้น
if (!defined('MBS_ADMIN_TRASH')) {
    http_response_code(403);
    exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง');
}

function at_stmt($conn, $sql, $types = '', array $values = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('ไม่สามารถเตรียมคำสั่งได้');
    if ($types !== '' && !$stmt->bind_param($types, ...$values)) {
        $stmt->close();
        throw new RuntimeException('ข้อมูลคำขอไม่ถูกต้อง');
    }
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('ไม่สามารถบันทึกรายการได้');
    }
    return $stmt;
}

function at_row($conn, $sql, $types = '', array $values = []) {
    $stmt = at_stmt($conn, $sql, $types, $values);
    try { return $stmt->get_result()->fetch_assoc(); }
    finally { $stmt->close(); }
}

function at_has_log_archive($conn, $table) {
    static $cache=[];
    if (!in_array($table,['login_logs','user_activity_logs'],true)) return false;
    $key=spl_object_hash($conn).':'.$table;
    if (array_key_exists($key,$cache)) return $cache[$key];
    try {$result=$conn->query("SHOW COLUMNS FROM `$table` LIKE 'deleted_at'");$ok=$result&&$result->num_rows===1;if($result)$result->free();return $cache[$key]=$ok;}
    catch(Throwable $error){return $cache[$key]=false;}
}
function at_log_engine($conn,$table) {
    if (!in_array($table,['login_logs','user_activity_logs'],true)) return;
    $row=at_row($conn,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?','s',[$table]);
    if (!$row||strtoupper((string)$row['ENGINE'])!=='INNODB') throw new RuntimeException('ตารางประวัติต้องรองรับ Transaction');
}

function at_positive_id($value) {
    if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)) return false;
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function at_redirect($type, $message) {
    $_SESSION['admin_trash_notice'] = ['type' => $type, 'message' => $message];
    $destination = 'dashboard.php?tab=trash';
    // เฉพาะย้ายประวัติจาก Contacts ให้กลับมารายการเดิม ไม่รับ URL ปลายทางจากผู้ใช้
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($_POST['trash_action'] ?? null) === 'move'
        && ($_POST['trash_kind'] ?? null) === 'reporter') {
        $returnQuery = ['tab' => 'users'];
        if (isset($_GET['pg_reporters_q']) && is_string($_GET['pg_reporters_q'])) {
            $returnQuery['pg_reporters_q'] = $_GET['pg_reporters_q'];
        }
        $returnPage = at_positive_id($_GET['pg_reporters_page'] ?? null);
        if ($returnPage !== false) $returnQuery['pg_reporters_page'] = $returnPage;
        $destination = 'dashboard.php?' . http_build_query($returnQuery, '', '&', PHP_QUERY_RFC3986);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($_POST['trash_action'] ?? null) === 'move'
        && ($_POST['trash_kind'] ?? null) === 'tech'
        && ($_GET['tab'] ?? null) === 'team_cards') {
        $destination = 'dashboard.php?tab=team_cards';
    }
    header('Location: ' . $destination, true, 303);
    exit;
}

function at_check_admin($conn) {
    $admin = authGuardLoadAccount($conn, (int)$_SESSION['user_id'], true);
    if (!authGuardSessionMatches($admin, $_SESSION, time())
        || strtolower((string)$admin['role']) !== 'admin') {
        throw new RuntimeException('สิทธิ์ผู้ดูแลเปลี่ยนไป กรุณาเข้าสู่ระบบใหม่');
    }
    $row = at_row($conn, 'SELECT deleted_at FROM users WHERE id = ? FOR UPDATE', 'i', [(int)$_SESSION['user_id']]);
    if (!$row || $row['deleted_at'] !== null) throw new RuntimeException('บัญชีผู้ดูแลไม่พร้อมใช้งาน');
}

// กันการลบแบบ CASCADE และการอ้างอิงค้าง โดยไม่แก้รหัสหรือข้อมูลในตารางอื่น
function at_check_foreign_references($conn, $table, $id) {
    $stmt = at_stmt($conn, 'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?', 's', [$table]);
    $refs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($refs as $ref) {
        foreach ($ref as $identifier) {
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $identifier)) {
                throw new RuntimeException('ไม่สามารถตรวจความสัมพันธ์ของข้อมูลได้');
            }
        }
        $target = at_row($conn, 'SELECT `' . $ref['REFERENCED_COLUMN_NAME'] . '` AS value FROM `' . $table . '` WHERE id = ?', 'i', [$id]);
        $related = at_row($conn, 'SELECT 1 AS found FROM `' . $ref['TABLE_NAME'] . '` WHERE `' . $ref['COLUMN_NAME'] . '` = ? LIMIT 1', 's', [(string)$target['value']]);
        if ($related) throw new RuntimeException('ยังลบถาวรไม่ได้ เนื่องจากมีข้อมูลอื่นอ้างอิงรายการนี้อยู่');
    }
}

function at_check_references($conn, $table, $id) {
    at_check_foreign_references($conn, $table, $id);
    if ($table === 'technicians') {
        foreach (['repairs', 'users'] as $dependent) {
            if (at_row($conn, "SELECT id FROM $dependent WHERE technician_id = ? LIMIT 1", 'i', [$id])) {
                throw new RuntimeException('ยังลบเจ้าหน้าที่ถาวรไม่ได้ เนื่องจากมีใบงานหรือบัญชีที่ผูกกับรหัสนี้อยู่');
            }
        }
    }
    if ($table === 'users') {
        // Dashboard เดิมสร้างบัญชีผู้แจ้งจากใบงานอัตโนมัติ ป้องกันลบแล้วถูกสร้างซ้ำทันที
        $user = at_row($conn, 'SELECT full_name FROM users WHERE id = ?', 'i', [$id]);
        if ($user && trim((string)$user['full_name']) !== ''
            && at_row($conn, 'SELECT id FROM repairs WHERE BINARY reporter_name = BINARY ? LIMIT 1', 's', [(string)$user['full_name']])) {
            throw new RuntimeException('ยังลบบัญชีถาวรไม่ได้ เนื่องจากยังมีใบงานที่ระบบเดิมใช้สร้างบัญชีผู้แจ้งอัตโนมัติ กรุณาจัดการใบงานที่เกี่ยวข้องก่อน');
        }
    }
}

// ลบบัญชีผู้ดูแล/ผู้บริหารพร้อมคำขอ OTP ของบัญชีนี้ ภายในธุรกรรมเดิมเท่านั้น
function at_purge_management_account($conn, $id) {
    $account = at_row($conn, 'SELECT id, role, is_active, deleted_at FROM users WHERE id = ? FOR UPDATE', 'i', [$id]);
    if (!$account || $account['deleted_at'] === null || (int)$account['is_active'] !== 0
        || !in_array(strtolower((string)$account['role']), ['admin', 'executive'], true)
        || $id === (int)$_SESSION['user_id']) {
        throw new RuntimeException('บัญชีไม่พร้อมสำหรับการลบถาวร กรุณาตรวจสอบสถานะอีกครั้ง');
    }
    $engine = at_row($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_requests'");
    if (!$engine || strtoupper((string)$engine['ENGINE']) !== 'INNODB') {
        throw new RuntimeException('ตารางคำขอ OTP ต้องรองรับธุรกรรมก่อนลบบัญชีถาวร');
    }
    // ใช้ user_id ตามความสัมพันธ์จริง ไม่จับคู่ด้วยชื่อหรืออีเมล
    at_stmt($conn, 'DELETE FROM auth_requests WHERE user_id = ?', 'i', [$id])->close();
    // ยังป้องกันการลบเมื่อมีข้อมูลอ้างอิงประเภทอื่น ไม่ปล่อยให้ CASCADE ลบส่วนอื่น
    at_check_foreign_references($conn, 'users', $id);
    $delete = at_stmt($conn, "DELETE FROM users WHERE id = ? AND deleted_at IS NOT NULL AND is_active = 0 AND LOWER(role) IN ('admin', 'executive')", 'i', [$id]);
    $affected = $delete->affected_rows; $delete->close();
    if ($affected !== 1) throw new RuntimeException('สถานะบัญชีเปลี่ยนแล้ว กรุณาตรวจสอบก่อนลองใหม่');
}

// เรียกภายในธุรกรรมและหลังล็อกแถวเจ้าหน้าที่ที่อยู่ในถังขยะแล้วเท่านั้น
function at_purge_technician_with_accounts($conn, $id) {
    if (at_row($conn, 'SELECT id FROM repairs WHERE technician_id = ? LIMIT 1 FOR UPDATE', 'i', [$id])) {
        throw new RuntimeException('ยังลบเจ้าหน้าที่ถาวรไม่ได้ เนื่องจากมีใบงานอ้างอิงอยู่ รวมใบงานในถังขยะ');
    }
    $stmt = at_stmt($conn, 'SELECT id, role, is_active FROM users WHERE technician_id = ? FOR UPDATE', 'i', [$id]);
    $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $deletedAccounts = [];
    // ตรวจทุกบัญชีก่อนลบ ไม่จับคู่ด้วยชื่อ ไม่เปลี่ยน technician_id
    foreach ($accounts as $account) {
        $accountId = (int)$account['id'];
        if ($accountId === (int)$_SESSION['user_id'] || strtolower((string)$account['role']) !== 'technician') {
            throw new RuntimeException('บัญชีที่ผูกเจ้าหน้าที่มีประเภทไม่ถูกต้อง ไม่สามารถลบทั้งชุดได้');
        }
        if ((int)$account['is_active'] !== 0) {
            throw new RuntimeException('บัญชีเว็บที่ผูกอยู่ยังเปิดใช้งาน กรุณาระงับบัญชีก่อนลบถาวร');
        }
        // ใช้ความสัมพันธ์ฐานข้อมูลจริง ไม่ใช้ชื่อผู้แจ้งไปจับคู่บัญชีเจ้าหน้าที่
        try { at_check_foreign_references($conn, 'users', $accountId); }
        catch (RuntimeException $referenceError) {
            throw new RuntimeException('บัญชีเว็บที่ผูกอยู่ยังมีข้อมูลอ้างอิง จึงยังลบเจ้าหน้าที่พร้อมบัญชีไม่ได้');
        }
    }
    foreach ($accounts as $account) {
        $accountId = (int)$account['id'];
        $delete = at_stmt($conn, "DELETE FROM users WHERE id = ? AND technician_id = ? AND LOWER(role) = 'technician' AND is_active = 0", 'ii', [$accountId, $id]);
        $affected = $delete->affected_rows;
        $delete->close();
        if ($affected !== 1) throw new RuntimeException('สถานะบัญชีเปลี่ยนแล้ว กรุณาตรวจสอบก่อนลองใหม่');
        $deletedAccounts[] = $accountId;
    }
    // users ถูกลบในธุรกรรมนี้แล้ว จึงตรวจความสัมพันธ์ที่เหลือของเจ้าหน้าที่
    // หากยังติดข้อมูลอื่น จะ rollback บัญชีทุกบัญชีกลับด้วย
    at_check_references($conn, 'technicians', $id);
    $delete = at_stmt($conn, 'DELETE FROM technicians WHERE id = ? AND deleted_at IS NOT NULL', 'i', [$id]);
    $affected = $delete->affected_rows;
    $delete->close();
    if ($affected !== 1) throw new RuntimeException('สถานะเจ้าหน้าที่เปลี่ยนแล้ว กรุณาตรวจสอบก่อนลองใหม่');
    return $deletedAccounts;
}

// กู้คืนด้วยกติกาเดียวกันทั้งปุ่มรายรายการและรายการที่เลือก
function at_restore_row($conn, $kind, $table, $id) {
    $restoredTechAccountCount = 0;
    if ($kind === 'tech') {
        // ผูกบัญชีด้วย technician_id เท่านั้น คงรหัสและการผูก LINE เดิม
        $linked = at_stmt($conn, 'SELECT id, role, deleted_at FROM users WHERE technician_id = ? FOR UPDATE', 'i', [$id]);
        $linkedRows = $linked->get_result()->fetch_all(MYSQLI_ASSOC); $linked->close();
        foreach ($linkedRows as $linkedRow) {
            if (strtolower((string)$linkedRow['role']) !== 'technician') {
                throw new RuntimeException('บัญชีที่ผูกเจ้าหน้าที่มีประเภทไม่ถูกต้อง กรุณาตรวจสอบก่อน');
            }
        }
        at_stmt($conn, 'UPDATE technicians SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL', 'i', [$id])->close();
        // ไม่กู้คืนบัญชีที่ถูกย้ายเข้าถังขยะแยกไว้ และไม่สร้างบัญชีหรือผูก LINE ใหม่
        $activate = at_stmt($conn, "UPDATE users SET is_active = 1, auth_version = auth_version + 1 WHERE technician_id = ? AND LOWER(role) = 'technician' AND deleted_at IS NULL", 'i', [$id]);
        $restoredTechAccountCount = $activate->affected_rows;
        $activate->close();
    } else {
        $keepUpdatedAt = $table === 'repairs' ? ', updated_at = updated_at' : '';
        at_stmt($conn, "UPDATE $table SET deleted_at = NULL$keepUpdatedAt WHERE id = ? AND deleted_at IS NOT NULL", 'i', [$id])->close();
    }
    return $restoredTechAccountCount;
}

function at_handle_request($conn) {
    // ปิดช่องทาง DELETE ผ่าน GET เก่า ไม่แปลงคำขอเก่าเป็นการลบถาวร
    foreach (['delete_asset', 'delete_user', 'delete_tech', 'delete_reporter'] as $legacy) {
        if (isset($_GET[$legacy])) {
            at_redirect('error', 'กรุณาใช้ปุ่มย้ายเข้าถังขยะจากหน้ารายการ');
        }
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['trash_action'])) {
        // ห้ามใช้ฟอร์มเก่าบันทึกรายการที่ถูกย้ายเข้าถังขยะแล้ว
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $table = null; $target = false;
            if (isset($_POST['save_asset'])) { $table = 'assets'; $target = at_positive_id($_POST['asset_id'] ?? null); }
            elseif (isset($_POST['save_user'])) {
                $table = ($_POST['role'] ?? null) === 'Technician' ? 'technicians' : 'users';
                $target = at_positive_id($_POST['user_id'] ?? null);
            }
            if ($table !== null && $target !== false) {
                $archived = at_row($conn, "SELECT deleted_at FROM $table WHERE id = ?", 'i', [$target]);
                if ($archived && $archived['deleted_at'] !== null) at_redirect('error', 'รายการนี้อยู่ในถังขยะ กรุณากู้คืนข้อมูลก่อนแก้ไข');
                if ($table === 'technicians' && at_row($conn, 'SELECT id FROM users WHERE technician_id = ? AND deleted_at IS NOT NULL LIMIT 1', 'i', [$target])) {
                    at_redirect('error', 'บัญชีเว็บที่ผูกเจ้าหน้าที่อยู่ในถังขยะ กรุณากู้คืนบัญชีก่อนแก้ไข');
                }
            }
        }
        return;
    }
    $started = false;
    $purgedLinkedAccounts = [];
    $restoredTechAccountCount = 0;
    try {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !is_string($_SESSION['csrf_token'] ?? null)
            || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
        }
        $action = $_POST['trash_action'];
        $kind = $_POST['trash_kind'] ?? null;
        $tables = ['asset' => 'assets', 'user' => 'users', 'tech' => 'technicians', 'repair' => 'repairs', 'reporter' => 'repairs', 'loginlog'=>'login_logs', 'activitylog'=>'user_activity_logs'];
        if (!is_string($action) || !in_array($action, ['move', 'restore', 'purge'], true)
            || !is_string($kind) || !isset($tables[$kind])) {
            throw new RuntimeException('ประเภทคำขอไม่ถูกต้อง');
        }
        $id = at_positive_id($_POST['trash_id'] ?? null);
        if ($id === false) throw new RuntimeException('รหัสรายการไม่ถูกต้อง');
        if ($kind === 'reporter' && $action !== 'move') throw new RuntimeException('คำขอไม่ถูกต้อง');
        if ($action === 'purge' && ($_POST['trash_confirm'] ?? null) !== '1') {
            throw new RuntimeException('กรุณายืนยันการลบถาวรก่อน');
        }
        if ($action === 'purge' && $kind === 'tech'
            && ($_POST['trash_confirm_linked_account'] ?? null) !== '1') {
            throw new RuntimeException('กรุณายืนยันว่าจะลบเจ้าหน้าที่พร้อมบัญชีเว็บที่ผูกอยู่ด้วย');
        }
        // การทำงานหลายตารางต้องรองรับ rollback; ไม่เปลี่ยน Engine อัตโนมัติ
        $engines = at_stmt($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','technicians','repairs','assets')");
        $result = $engines->get_result();
        if ($result->num_rows !== 4) throw new RuntimeException('ตารางข้อมูลไม่ครบ');
        while ($engine = $result->fetch_assoc()) {
            if (strtoupper((string)$engine['ENGINE']) !== 'INNODB') throw new RuntimeException('ตารางต้องรองรับธุรกรรมก่อนใช้งานถังขยะ');
        }
        $engines->close();
        if (!$conn->begin_transaction()) throw new RuntimeException('เริ่มบันทึกข้อมูลไม่ได้');
        $started = true;
        at_check_admin($conn);
        $table = $tables[$kind];
        at_log_engine($conn,$table);
        $row = at_row($conn, "SELECT * FROM $table WHERE id = ? FOR UPDATE", 'i', [$id]);
        if (!$row) throw new RuntimeException('ไม่พบรายการนี้');
        if (($action === 'move') !== ($row['deleted_at'] === null)) {
            throw new RuntimeException('สถานะรายการเปลี่ยนแล้ว กรุณาตรวจรายการอีกครั้ง');
        }
        if ($table === 'users' && $id === (int)$_SESSION['user_id']) {
            throw new RuntimeException('ไม่สามารถย้ายหรือลบบัญชีที่กำลังใช้งานอยู่ได้');
        }
        if ($action === 'move') {
            if ($table === 'users') {
                at_stmt($conn, 'UPDATE users SET deleted_at = NOW(), is_active = 0, auth_version = auth_version + 1 WHERE id = ? AND deleted_at IS NULL', 'i', [$id])->close();
            } elseif ($kind === 'tech') {
                $linked = at_stmt($conn, 'SELECT id, role FROM users WHERE technician_id = ? FOR UPDATE', 'i', [$id]);
                $linkedRows = $linked->get_result()->fetch_all(MYSQLI_ASSOC); $linked->close();
                foreach ($linkedRows as $linkedRow) {
                    if (strtolower((string)$linkedRow['role']) !== 'technician') {
                        throw new RuntimeException('บัญชีที่ผูกเจ้าหน้าที่มีประเภทไม่ถูกต้อง กรุณาตรวจสอบก่อน');
                    }
                }
                at_stmt($conn, "UPDATE users SET is_active = 0, auth_version = auth_version + 1 WHERE technician_id = ? AND LOWER(role) = 'technician'", 'i', [$id])->close();
                at_stmt($conn, 'UPDATE technicians SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL', 'i', [$id])->close();
            } elseif ($kind === 'reporter' && trim((string)($row['line_user_id'] ?? '')) !== '') {
                at_stmt($conn, 'UPDATE repairs SET deleted_at = NOW(), updated_at = updated_at WHERE BINARY line_user_id = BINARY ? AND deleted_at IS NULL', 's', [(string)$row['line_user_id']])->close();
            } else {
                // รายการเก่าที่ไม่มี LINE ID ย้ายเฉพาะเลขใบงาน ไม่จับคู่ด้วยชื่อ
                $keepUpdatedAt = $table === 'repairs' ? ', updated_at = updated_at' : '';
                at_stmt($conn, "UPDATE $table SET deleted_at = NOW()$keepUpdatedAt WHERE id = ? AND deleted_at IS NULL", 'i', [$id])->close();
            }
        } elseif ($action === 'restore') {
            $restoredTechAccountCount = at_restore_row($conn, $kind, $table, $id);
        } elseif ($kind === 'tech') {
            $purgedLinkedAccounts = at_purge_technician_with_accounts($conn, $id);
        } elseif ($table === 'users' && in_array(strtolower((string)$row['role']), ['admin', 'executive'], true)) {
            at_purge_management_account($conn, $id);
        } else {
            at_check_references($conn, $table, $id);
            at_stmt($conn, "DELETE FROM $table WHERE id = ? AND deleted_at IS NOT NULL", 'i', [$id])->close();
        }
        if (!$conn->commit()) throw new RuntimeException('ไม่สามารถยืนยันการบันทึกได้ กรุณาตรวจรายการก่อนลองซ้ำ');
        $started = false;
        try {
            if (function_exists('mbsActivityLog')) mbsActivityLog($conn, 'TRASH_' . strtoupper($action), $table, $id, 'จัดการรายการผ่านถังขยะ');
            foreach ($purgedLinkedAccounts as $purgedAccountId) {
                if (function_exists('mbsActivityLog')) mbsActivityLog($conn, 'TRASH_PURGE', 'users', $purgedAccountId, 'ลบบัญชีเว็บพร้อมเจ้าหน้าที่รหัส ' . $id);
            }
        } catch (Throwable $logError) { error_log('Trash activity log failed'); }
        $message = ['move' => 'ย้ายข้อมูลเข้าถังขยะแล้ว', 'restore' => 'กู้คืนข้อมูลแล้ว', 'purge' => 'ลบข้อมูลถาวรแล้ว'][$action];
        if ($action === 'purge' && $kind === 'tech') {
            $message = 'ลบข้อมูลเจ้าหน้าที่ถาวรแล้ว พร้อมบัญชีเว็บที่ผูกอยู่ ' . count($purgedLinkedAccounts) . ' บัญชี';
        }
        if ($action === 'restore' && $kind === 'tech') {
            $message .= $restoredTechAccountCount > 0
                ? ' เปิดใช้งานบัญชีเว็บที่ผูกกับเจ้าหน้าที่แล้ว ' . $restoredTechAccountCount . ' บัญชี'
                : ' ไม่พบบัญชีเว็บที่พร้อมเปิดใช้งานซึ่งผูกกับเจ้าหน้าที่นี้';
        } elseif ($action === 'restore' && $kind === 'user') {
            $message .= ' บัญชีเว็บยังคงปิดใช้งาน กรุณาตรวจสอบก่อนเปิดใช้งานจากหน้าจัดการบัญชี';
        }
        at_redirect('success', $message);
    } catch (Throwable $error) {
        if ($started) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
        $safe = $error instanceof RuntimeException && !($error instanceof mysqli_sql_exception)
            ? $error->getMessage() : 'ไม่สามารถบันทึกรายการได้ อาจมีข้อมูลอื่นอ้างอิงอยู่ กรุณาตรวจสอบก่อนลองใหม่';
        at_redirect('error', $safe);
    }
}

// Bulk requests use the same guards and restore/purge rules as single-item Trash actions.
function at_handle_bulk_request($conn) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !in_array($_POST['trash_action'] ?? null, ['bulk_select', 'bulk_purge', 'bulk_restore'], true)) return;
    $selectOnly = $_POST['trash_action'] === 'bulk_select';
    $restoreSelected = $_POST['trash_action'] === 'bulk_restore';
    $started = false;
    $audit = [];
    try {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !is_string($_SESSION['csrf_token'] ?? null)
            || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
        }
        if (!$selectOnly && ($_POST['trash_confirm'] ?? null) !== '1') {
            throw new RuntimeException($restoreSelected ? 'กรุณายืนยันการกู้คืนก่อน' : 'กรุณายืนยันการลบถาวรก่อน');
        }
        if (!$selectOnly) {
            $engines = at_stmt($conn, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','technicians','repairs','assets')");
            $result = $engines->get_result();
            if ($result->num_rows !== 4) throw new RuntimeException('ตารางข้อมูลไม่ครบ');
            while ($engine = $result->fetch_assoc()) {
                if (strtoupper((string)$engine['ENGINE']) !== 'INNODB') throw new RuntimeException('ตารางต้องรองรับธุรกรรมก่อนใช้งานถังขยะ');
            }
            $engines->close();
        }
        if (!$conn->begin_transaction()) throw new RuntimeException('เริ่มบันทึกข้อมูลไม่ได้');
        $started = true;
        at_check_admin($conn);
        if ($selectOnly) {
            // Capture a fixed selection. New arrivals after this response are not selected.
            extract(at_trash_query($conn), EXTR_SKIP);
            $stmt = at_stmt($conn, "SELECT kind, id, deleted_at, category FROM ($union) trash_rows WHERE $where ORDER BY kind ASC, id ASC", $types, $values);
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            foreach ($items as &$item) $item['id'] = (string)$item['id'];
            unset($item);
            if (!$conn->commit()) throw new RuntimeException('ไม่สามารถอ่านรายการได้');
            $started = false;
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode(['items'=>$items, 'count'=>count($items)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            exit;
        }
        $rawItems = $_POST['trash_items'] ?? null;
        if (!is_string($rawItems)) throw new RuntimeException('รายการที่เลือกไม่ถูกต้อง');
        $items = json_decode($rawItems, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !$items || array_keys($items) !== range(0, count($items)-1)) {
            throw new RuntimeException($restoreSelected ? 'กรุณาเลือกรายการก่อนกู้คืน' : 'กรุณาเลือกรายการก่อนลบ');
        }
        $tables = ['asset'=>'assets', 'user'=>'users', 'tech'=>'technicians', 'repair'=>'repairs', 'loginlog'=>'login_logs', 'activitylog'=>'user_activity_logs'];
        $seen = [];
        foreach ($items as &$item) {
            if (!is_array($item) || !is_string($item['kind'] ?? null) || !isset($tables[$item['kind']])) {
                throw new RuntimeException('ประเภทข้อมูลที่เลือกไม่ถูกต้อง');
            }
            $id = at_positive_id($item['id'] ?? null);
            $deletedAt = $item['deleted_at'] ?? null;
            if ($id === false || !is_string($deletedAt) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $deletedAt)) {
                throw new RuntimeException('ข้อมูลรายการที่เลือกไม่ถูกต้อง กรุณาเลือกใหม่');
            }
            if (!$restoreSelected && $item['kind'] === 'tech' && ($_POST['trash_confirm_linked_account'] ?? null) !== '1') {
                throw new RuntimeException('กรุณายืนยันการลบเจ้าหน้าที่พร้อมบัญชีเว็บที่ผูกอยู่');
            }
            $key = $item['kind'] . ':' . $id;
            if (isset($seen[$key])) throw new RuntimeException('รายการที่เลือกซ้ำ กรุณาเลือกใหม่');
            $seen[$key] = true; $item['id'] = $id;
        }
        unset($item);
        // จัดการบัญชีที่เลือกก่อนเจ้าหน้าที่ เพื่อให้กติกาการกู้คืนและลบทำงานตามลำดับเดิม
        $order = ['loginlog'=>0, 'activitylog'=>0, 'repair'=>1, 'user'=>2, 'asset'=>3, 'tech'=>4];
        usort($items, static function ($a, $b) use ($order) {
            $rank = $order[$a['kind']] <=> $order[$b['kind']];
            return $rank !== 0 ? $rank : $a['id'] <=> $b['id'];
        });
        foreach ($items as $item) {
            $kind = $item['kind']; $id = $item['id']; $table = $tables[$kind];
            at_log_engine($conn,$table);
            $row = at_row($conn, "SELECT * FROM $table WHERE id = ? FOR UPDATE", 'i', [$id]);
            if (!$row || $row['deleted_at'] === null || (string)$row['deleted_at'] !== $item['deleted_at']) {
                throw new RuntimeException('สถานะรายการรหัส ' . $id . ' เปลี่ยนแล้ว กรุณารีเฟรชและเลือกใหม่');
            }
            if ($table === 'users' && $id === (int)$_SESSION['user_id']) {
                throw new RuntimeException('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
            }
            if ($restoreSelected) {
                at_restore_row($conn, $kind, $table, $id);
                $audit[] = [$table, $id];
                continue;
            }
            if ($kind === 'tech') {
                $linkedAccounts = at_purge_technician_with_accounts($conn, $id);
                foreach ($linkedAccounts as $accountId) $audit[] = ['users', $accountId];
            } elseif ($table === 'users' && in_array(strtolower((string)$row['role']), ['admin', 'executive'], true)) {
                at_purge_management_account($conn, $id);
            } else {
                at_check_references($conn, $table, $id);
                $delete = at_stmt($conn, "DELETE FROM $table WHERE id = ? AND deleted_at IS NOT NULL", 'i', [$id]);
                $affected = $delete->affected_rows; $delete->close();
                if ($affected !== 1) throw new RuntimeException('สถานะรายการเปลี่ยนแล้ว กรุณาเลือกใหม่');
            }
            $audit[] = [$table, $id];
        }
        if (!$conn->commit()) throw new RuntimeException($restoreSelected ? 'ไม่สามารถยืนยันการกู้คืนได้ กรุณาตรวจรายการก่อนลองใหม่' : 'ไม่สามารถยืนยันการลบได้ กรุณาตรวจรายการก่อนลองใหม่');
        $started = false;
        try {
            foreach ($audit as $entry) {
                if (function_exists('mbsActivityLog')) mbsActivityLog($conn, $restoreSelected ? 'TRASH_RESTORE' : 'TRASH_PURGE', $entry[0], $entry[1], $restoreSelected ? 'กู้คืนจากรายการที่เลือกในถังขยะ' : 'ลบถาวรจากรายการที่เลือกในถังขยะ');
            }
        } catch (Throwable $logError) { error_log('Bulk Trash activity log failed'); }
        at_redirect('success', ($restoreSelected ? 'กู้คืนข้อมูลที่เลือกแล้ว ' : 'ลบรายการที่เลือกถาวรแล้ว ') . count($items) . ' รายการ');
    } catch (Throwable $error) {
        if ($started) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
        $message = $error instanceof RuntimeException && !($error instanceof mysqli_sql_exception)
            ? $error->getMessage() : 'ไม่สามารถดำเนินการกับรายการที่เลือกได้ กรุณาตรวจสอบแล้วลองใหม่';
        if ($selectOnly) {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode(['error'=>$message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        at_redirect('error', $message);
    }
}

at_handle_bulk_request($conn);
at_handle_request($conn);

function at_period_filters() {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
    $read = static function ($key, $min, $max, $fallback) {
        $raw = $_GET[$key] ?? $fallback;
        if ($raw === 'all') return 'all';
        if (!is_string($raw) || !preg_match('/^[0-9]{1,4}$/D', $raw)
            || (int)$raw < $min || (int)$raw > $max) return $fallback;
        return (string)(int)$raw;
    };
    $day = $read('trash_day', 1, 31, $now->format('j'));
    $month = $read('trash_month', 1, 12, $now->format('n'));
    $year = $read('trash_year', 1000, 9999, $now->format('Y'));
    return compact('day', 'month', 'year');
}

function at_trash_query($conn = null) {
    if ($conn === null) $conn = $GLOBALS['conn'];
    $union = "SELECT 'asset' AS kind, id, CONVERT(asset_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS label, CONVERT(asset_code USING utf8mb4) COLLATE utf8mb4_unicode_ci AS detail, deleted_at, 'asset' AS category FROM assets WHERE deleted_at IS NOT NULL
        UNION ALL SELECT 'user', id, COALESCE(NULLIF(full_name,''),username), username, deleted_at, CASE LOWER(role) WHEN 'admin' THEN 'admin' WHEN 'executive' THEN 'executive' ELSE 'user' END FROM users WHERE deleted_at IS NOT NULL
        UNION ALL SELECT 'tech', id, full_name, department, deleted_at, 'tech' FROM technicians WHERE deleted_at IS NOT NULL
        UNION ALL SELECT 'repair', id, ticket_no, reporter_name, deleted_at, 'user' FROM repairs WHERE deleted_at IS NOT NULL";
    if (at_has_log_archive($conn,'login_logs')) $union .= " UNION ALL SELECT 'loginlog',id,username,CONCAT_WS(' · ',role,status,DATE_FORMAT(login_time,'%d/%m/%Y %H:%i')),deleted_at,'loginlog' FROM login_logs WHERE deleted_at IS NOT NULL";
    if (at_has_log_archive($conn,'user_activity_logs')) $union .= " UNION ALL SELECT 'activitylog',id,COALESCE(NULLIF(full_name,''),username),CONCAT_WS(' · ',role,summary),deleted_at,'activitylog' FROM user_activity_logs WHERE deleted_at IS NOT NULL";
    // category ใช้แสดง/กรองเท่านั้น kind ยังคงอ้างอิงตารางจริงสำหรับ POST
    $q = isset($_GET['trash_q']) && is_string($_GET['trash_q']) ? trim($_GET['trash_q']) : '';
    $q = mb_substr($q, 0, 200, 'UTF-8');
    $kind = isset($_GET['trash_type']) && is_string($_GET['trash_type']) ? $_GET['trash_type'] : 'all';
    if ($kind === 'repair') $kind = 'user';
    $where = '1=1'; $values = []; $types = '';
    if (in_array($kind, ['asset','user','tech','admin','executive','loginlog','activitylog'], true)) { $where .= ' AND category = ?'; $values[] = $kind; $types .= 's'; }
    else { $kind = 'all'; }
    $period = at_period_filters();
    $day = $period['day']; $month = $period['month']; $year = $period['year'];
    foreach (['day'=>'DAY', 'month'=>'MONTH', 'year'=>'YEAR'] as $key => $sqlFunction) {
        if ($period[$key] !== 'all') {
            $where .= " AND $sqlFunction(deleted_at) = ?";
            $values[] = (int)$period[$key]; $types .= 'i';
        }
    }
    if ($q !== '') {
        $where .= " AND CONCAT_WS(' ',label,detail,id) LIKE ? ESCAPE '!'";
        $values[] = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%'; $types .= 's';
    }
    return compact('union','where','values','types','q','kind','day','month','year');
}

function at_page($conn) {
    extract(at_trash_query($conn), EXTR_SKIP);
    $years = [(int)(new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y')];
    if ($year !== 'all') $years[] = (int)$year;
    $yearStmt = at_stmt($conn, "SELECT DISTINCT YEAR(deleted_at) AS y FROM ($union) trash_years WHERE YEAR(deleted_at) BETWEEN 1000 AND 9999");
    $yearResult = $yearStmt->get_result();
    while ($yearRow = $yearResult->fetch_assoc()) {
        $years[] = (int)$yearRow['y'];
    }
    $yearStmt->close(); $years = array_values(array_unique($years)); rsort($years, SORT_NUMERIC);
    $total = (int)at_row($conn, "SELECT COUNT(*) AS total FROM ($union) trash_rows WHERE $where", $types, $values)['total'];
    $pages = max(1, (int)ceil($total / 50));
    $rawPage = at_positive_id($_GET['trash_page'] ?? '1');
    $page = max(1, min($pages, $rawPage === false ? 1 : $rawPage));
    $offset = ($page - 1) * 50;
    $stmt = at_stmt($conn, "SELECT * FROM ($union) trash_rows WHERE $where ORDER BY deleted_at DESC, kind ASC, id DESC LIMIT 50 OFFSET $offset", $types, $values);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return compact('rows','page','pages','total','offset','q','kind','day','month','year','years');
}

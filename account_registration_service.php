<?php
// ใช้เฉพาะบัญชีเว็บ: ไม่เขียนรหัสช่าง สถานะอนุมัติ หรือข้อมูล LINE

function arDuplicateMessage()
{
    return 'ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว กรุณากรอกชื่อผู้ใช้ใหม่แล้วลองอีกครั้ง';
}

function arStatement($db, $sql, $types = '', $values = [])
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Account database operation failed');
    try {
        if ($types !== '' && !$stmt->bind_param($types, ...$values)) throw new RuntimeException('Account database operation failed');
        if (!$stmt->execute()) throw new RuntimeException('Account database operation failed', (int)$stmt->errno);
        return $stmt;
    } catch (Throwable $e) { $stmt->close(); throw $e; }
}

function arRow($db, $sql, $types = '', $values = [])
{
    $stmt = arStatement($db, $sql, $types, $values);
    try {
        $result = $stmt->get_result();
        if (!$result) throw new RuntimeException('Account database operation failed');
        return $result->num_rows === 1 ? $result->fetch_assoc() : null;
    } finally { $stmt->close(); }
}

function arExecute($db, $sql, $types = '', $values = [])
{
    $s = arStatement($db, $sql, $types, $values);
    $n = $s->affected_rows;
    $s->close();
    return $n;
}

function arAccount($db, $id, $lock = false)
{
    // อ่านตามรหัสบัญชีและรหัสเจ้าหน้าที่เดิม
    $u = arRow($db, 'SELECT * FROM users WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), 'i', [$id]);
    if (!$u || $u['deleted_at'] !== null) return null;
    $role = strtolower((string)$u['role']);
    if (!in_array($role, ['admin', 'executive', 'technician'], true)) return null;
    if ($role === 'technician') {
        $t = arRow($db, 'SELECT * FROM technicians WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), 'i', [(int)$u['technician_id']]);
        if (!$t || $t['deleted_at'] !== null) return null;
        $u['profile'] = $t;
        $u['linked_technician_id'] = $t['id'];
    } else {
        if ($u['technician_id'] !== null) return null;
        $u['profile'] = $u;
    }
    return $u;
}

function arPendingCandidate($db, $role, $data)
{
    // ค้นได้เฉพาะรายการที่แอดมินเตรียมไว้และยังไม่มี Username
    $tech = strtolower($role) === 'technician';
    $sql = $tech
        ? "SELECT u.id FROM users u JOIN technicians p ON p.id=u.technician_id WHERE u.deleted_at IS NULL AND p.deleted_at IS NULL"
        : "SELECT u.id FROM users u JOIN users p ON p.id=u.id WHERE u.deleted_at IS NULL";
    $sql .= ' AND u.registration_pending=1 AND u.username IS NULL AND LOWER(u.role)=?';
    $v = [strtolower($role)];
    foreach (['full_name','phone','email'] as $field) {
        $sql .= ' AND BINARY TRIM(p.' . $field . ')=BINARY ?';
        $v[] = trim($data[$field]);
    }
    if ($tech) {
        foreach (['position','department'] as $field) {
            $sql .= ' AND BINARY TRIM(p.' . $field . ')=BINARY ?';
            $v[] = trim($data[$field]);
        }
    }
    $s = arStatement($db, $sql . ' LIMIT 2', str_repeat('s', count($v)), $v);
    try {
        $r = $s->get_result();
        if (!$r) throw new RuntimeException('Candidate lookup failed');
        if ($r->num_rows > 1) throw new DomainException('พบรายการรอลงทะเบียนที่มีข้อมูลตรงกันมากกว่าหนึ่งรายการ กรุณาติดต่อแอดมินเพื่อตรวจสอบก่อน');
        $row = $r->fetch_assoc();
        return $row ? (int)$row['id'] : null;
    } finally { $s->close(); }
}

function arCompletePreparedRegistration($db, $id, $role, $data, $password)
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) throw new RuntimeException('Password hash failed');
    if (!$db->begin_transaction()) throw new RuntimeException('Registration transaction failed');
    try {
        $u = arAccount($db, $id, true);
        if (!$u || (int)$u['is_active'] !== 1 || (int)$u['registration_pending'] !== 1
            || $u['username'] !== null || strtolower($u['role']) !== strtolower($role)) {
            throw new DomainException('รายการนี้ไม่พร้อมลงทะเบียน กรุณาตรวจสอบข้อมูลกับแอดมิน');
        }
        $fields = ['full_name','phone','email'];
        if (strtolower($role)==='technician') $fields = array_merge($fields,['position','department']);
        foreach ($fields as $field) {
            if (trim((string)$u['profile'][$field]) !== trim($data[$field])) throw new DomainException('ข้อมูลไม่ตรงกับรายการที่แอดมินเตรียมไว้ กรุณาตรวจสอบแล้วลองอีกครั้ง');
        }
        if (arRow($db,'SELECT id FROM users WHERE username=? AND id<>? LIMIT 1','si',[$data['username'],(int)$u['id']])) throw new DomainException(arDuplicateMessage());
        // กำหนดเฉพาะชื่อผู้ใช้และรหัสผ่านของรายการรอลงทะเบียน ไม่เขียนทับบัญชีที่ลงทะเบียนแล้ว
        // ไม่ยืนยันอีเมลอัตโนมัติ และไม่แก้ id, technician_id, ข้อมูลช่าง หรือการผูก LINE
        $n=arExecute($db,'UPDATE users SET username=?,password=?,registration_pending=0,initial_password_pending=0,must_change_password=0,verified_email=NULL,email_verified_at=NULL,auth_version=auth_version+1 WHERE id=? AND registration_pending=1 AND username IS NULL AND is_active=1 AND deleted_at IS NULL','ssi',[$data['username'],$hash,(int)$u['id']]);
        if ($n!==1) throw new RuntimeException('Prepared registration update failed');
        if (!$db->commit()) throw new RuntimeException('Registration commit failed');
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

function arGateRegistration($db, $role, $data, $password, &$error, &$success)
{
    if (strtolower((string)$role)==='admin') { $error='ระบบใช้บัญชีแอดมินหลักเพียงบัญชีเดียว ไม่เปิดรับสมัครแอดมินเพิ่มเติม'; return true; }
    try {
        if (arRow($db,'SELECT id FROM users WHERE username=? LIMIT 1','s',[$data['username']])) {
            $error=arDuplicateMessage();
            return true;
        }
        $id=arPendingCandidate($db,$role,$data);
        if (!$id) return false;
        arCompletePreparedRegistration($db,$id,$role,$data,$password);
        unset($_SESSION['account_registration_claim']);
        $success = strtolower($role)==='technician'
            ? 'สมัครสมาชิกสำเร็จ! ชื่อผู้ใช้บันทึกในรายการเจ้าหน้าที่เดิมแล้ว กรุณาผูกบัญชี LINE ตามขั้นตอนเดิมก่อนเข้าสู่ Dashboard เจ้าหน้าที่'
            : 'สมัครสมาชิกสำเร็จ! ชื่อผู้ใช้บันทึกในรายการเดิมแล้ว สามารถเข้าสู่ระบบด้วยบัญชีที่สมัครได้';
    } catch (DomainException $e) { $error=$e->getMessage(); }
    catch (Throwable $e) {
        error_log('Prepared registration failed');
        $error=(int)$e->getCode()===1062 ? arDuplicateMessage() : 'ลงทะเบียนไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
    }
    return true;
}

function arAdminUsername($db, $role, $targetId)
{
    if ((int)$targetId === 0) return null;
    $u = strtolower($role)==='technician'
        ? arRow($db, "SELECT username FROM users WHERE technician_id=? AND LOWER(role)='technician'", 'i', [(int)$targetId])
        : arRow($db, 'SELECT username FROM users WHERE id=?', 'i', [(int)$targetId]);
    return $u['username'] ?? null;
}

function arMarkAdminDraft($db, $id, $adminId)
{
    if (arExecute($db, 'UPDATE users SET registration_pending=1, registration_created_by=?, initial_password_pending=0, verified_email=NULL, email_verified_at=NULL WHERE id=? AND username IS NULL', 'ii', [$adminId,$id]) !== 1) throw new RuntimeException('Draft marking failed');
}

function arDraftBadge($role)
{
    $labels = ['admin'=>'บัญชีแอดมิน','executive'=>'บัญชีผู้บริหาร','technician'=>'บัญชีเจ้าหน้าที่'];
    $label = $labels[strtolower($role)] ?? 'บัญชี';
    return '<div class="ar-draft-badge">' . $label . ' · เพิ่มโดยแอดมิน</div><div class="ar-draft-caption">รอเจ้าของบัญชีลงทะเบียน</div>';
}

function arModalArgs($values)
{
    // ส่งข้อความไป JavaScript อย่างถูกต้อง แม้ Username หรือชื่อมีเครื่องหมายคำพูด
    $parts = [];
    foreach ($values as $value) {
        $parts[] = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    return htmlspecialchars(implode(',', $parts), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

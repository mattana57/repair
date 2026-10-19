<?php
// ใช้เฉพาะบัญชีเว็บ: ไม่เขียนรหัสช่าง สถานะอนุมัติ หรือข้อมูล LINE
require_once __DIR__ . '/otp_request_service.php';

function arDuplicateMessage()
{
    return 'ชื่อผู้ใช้นี้มีผู้ใช้งานแล้ว กรุณากรอกชื่อผู้ใช้ใหม่แล้วลองอีกครั้ง';
}

function arStatement($db, $sql, $types = '', $values = [])
{
    return otpRequestStatement($db, $sql, $types, $values);
}

function arRow($db, $sql, $types = '', $values = [])
{
    return otpRequestRow(arStatement($db, $sql, $types, $values));
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
    // ล็อก user แล้ว technician ให้ตรงกับระบบ OTP เดิม
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

function arSnapshot($u)
{
    $p = $u['profile'];
    $data = [];
    foreach (['id','username','password','role','technician_id','is_active','auth_version','registration_pending','registration_created_by','verified_email','email_verified_at'] as $key) {
        $data['account_' . $key] = $u[$key] ?? null;
    }
    foreach (['id','full_name','english_name','phone','email','position','department','approval_status','line_user_id','secret_code'] as $key) {
        $data['profile_' . $key] = $p[$key] ?? null;
    }
    return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
}

function arPendingCandidate($db, $role, $data)
{
    // ข้อมูลตรงกันใช้ค้นหารายการที่รอเท่านั้น การยืนยันสิทธิ์ต้องผ่าน OTP อีกครั้ง
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

function arStartClaim($db, $id, $data, $password)
{
    $config = otpRequestConfig();
    otpRequestCheckEngines($db);
    $engine = arRow($db, "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='account_registration_requests'");
    if (!$engine || strtoupper($engine['ENGINE'])!=='INNODB') throw new RuntimeException('Registration requests require InnoDB');
    if (!otpRequestAdmitSource($db, $config)) throw new DomainException('ขอรหัสบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่');
    $payload = $data;
    $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($payload['password_hash'])) throw new RuntimeException('Hash failed');
    $requestId = bin2hex(random_bytes(16));
    $otp = (string)random_int(100000, 999999);
    if (!$db->begin_transaction()) throw new RuntimeException('Registration transaction failed');
    try {
        $u = arAccount($db, $id, true);
        if (!$u || (int)$u['is_active'] !== 1 || (int)$u['registration_pending'] !== 1 || $u['username'] !== null) {
            throw new DomainException('รายการนี้ไม่พร้อมลงทะเบียน กรุณาติดต่อแอดมิน');
        }
        // เช็กข้อมูลอีกครั้งหลังล็อก ป้องกันข้อมูลถูกแก้ระหว่างค้นหา
        foreach (['full_name','phone','email'] as $field) {
            if (trim((string)$u['profile'][$field]) !== trim($payload[$field])) throw new DomainException('ข้อมูลถูกเปลี่ยน กรุณาเริ่มลงทะเบียนอีกครั้ง');
        }
        if (strtolower($u['role']) === 'technician') {
            foreach (['position','department'] as $field) {
                if (trim((string)$u['profile'][$field]) !== trim($payload[$field])) throw new DomainException('ข้อมูลถูกเปลี่ยน กรุณาเริ่มลงทะเบียนอีกครั้ง');
            }
        }
        if (strtolower($u['role']) !== strtolower($payload['role'])) throw new DomainException('ประเภทบัญชีไม่ตรงกัน');
        $email = trim((string)$u['profile']['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('อีเมลของรายการนี้ไม่ถูกต้อง กรุณาติดต่อแอดมิน');
        $rate = arRow($db, 'SELECT COUNT(*) AS total, MAX(created_at) AS latest FROM account_registration_requests WHERE user_id=? AND created_at>=DATE_SUB(NOW(), INTERVAL ? SECOND)', 'ii', [$id, $config['account_window_seconds']]);
        // ใช้เวลาเซิร์ฟเวอร์ฐานข้อมูลเพื่อไม่ขึ้นกับ timezone ของ PHP
        $cooldown = arRow($db, 'SELECT id FROM account_registration_requests WHERE user_id=? AND created_at>DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1', 'ii', [$id, $config['account_cooldown_seconds']]);
        if ((int)$rate['total'] >= $config['account_request_limit'] || $cooldown) throw new DomainException('ขอรหัสแล้ว กรุณารออย่างน้อย 60 วินาที และไม่เกินจำนวนคำขอที่ระบบกำหนด');
        $snapshot = arSnapshot($u);
        $sessionHash = hash('sha256', session_id());
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        arExecute($db, 'UPDATE account_registration_requests SET canceled_at=NOW() WHERE user_id=? AND used_at IS NULL AND canceled_at IS NULL', 'i', [$id]);
        arExecute($db, 'INSERT INTO account_registration_requests (id,user_id,session_hash,snapshot_hash,otp_hash,created_at,expires_at) VALUES (?,?,?,?,?,NOW(),DATE_ADD(NOW(), INTERVAL ? SECOND))', 'sisssi', [$requestId,$id,$sessionHash,$snapshot,$otpHash,$config['otp_lifetime_seconds']]);
        if (!$db->commit()) throw new RuntimeException('Registration commit failed');
    } catch (Throwable $e) { $db->rollback(); throw $e; }
    try {
        require_once __DIR__ . '/mailer.php';
        $sent = sendOtpEmail($email, $otp, 'ยืนยันการลงทะเบียนบัญชีที่แอดมินเตรียมไว้');
    } catch (Throwable $e) { $sent = false; }
    unset($otp);
    if (!$sent) {
        arExecute($db, 'UPDATE account_registration_requests SET canceled_at=NOW() WHERE id=?', 's', [$requestId]);
        throw new DomainException('ส่ง OTP ไม่สำเร็จ กรุณาลองอีกครั้งภายหลัง');
    }
    $_SESSION['account_registration_claim'] = ['id'=>$requestId,'user_id'=>$id,'snapshot'=>$snapshot,'payload'=>$payload,'email'=>$email,'expires'=>time()+$config['otp_lifetime_seconds']];
}

function arFinishClaim($db, $claim, $otp)
{
    if (!is_array($claim) || !is_string($otp) || !preg_match('/\A[0-9]{6}\z/', $otp)) throw new DomainException('กรุณากรอก OTP ให้ครบ 6 หลัก');
    if (!$db->begin_transaction()) throw new RuntimeException('Registration transaction failed');
    try {
        $u = arAccount($db, (int)$claim['user_id'], true);
        $r = arRow($db, 'SELECT *, expires_at>NOW() AS unexpired FROM account_registration_requests WHERE id=? FOR UPDATE', 's', [$claim['id']]);
        $config = otpRequestConfig();
        if (!$u || !$r || (int)$r['user_id'] !== (int)$claim['user_id'] || (int)$u['is_active'] !== 1
            || (int)$u['registration_pending'] !== 1 || $u['username'] !== null
            || !hash_equals($r['session_hash'], hash('sha256', session_id()))
            || !hash_equals($r['snapshot_hash'], arSnapshot($u))
            || !hash_equals($r['snapshot_hash'], $claim['snapshot'])
            || !$r['unexpired'] || $r['used_at'] !== null || $r['canceled_at'] !== null
            || (int)$r['failed_attempts'] >= ($config['otp_max_failed_attempts'] ?? 5)) {
            throw new DomainException('คำขอยืนยันใช้ไม่ได้หรือหมดอายุ กรุณาเริ่มลงทะเบียนอีกครั้ง');
        }
        if (!password_verify($otp, $r['otp_hash'])) {
            arExecute($db, 'UPDATE account_registration_requests SET failed_attempts=failed_attempts+1 WHERE id=?', 's', [$claim['id']]);
            if (!$db->commit()) throw new RuntimeException('Registration commit failed');
            return false;
        }
        $p = $claim['payload'];
        if (arRow($db, 'SELECT id FROM users WHERE username=? AND id<>? LIMIT 1', 'si', [$p['username'],(int)$u['id']])) throw new DomainException(arDuplicateMessage());
        // คง id, technician_id, LINE และข้อมูลช่างเดิมทุกคอลัมน์
        $n = arExecute($db, 'UPDATE users SET username=?, password=?, registration_pending=0, initial_password_pending=0, must_change_password=0, verified_email=?, email_verified_at=NOW(), auth_version=auth_version+1 WHERE id=? AND registration_pending=1 AND username IS NULL', 'sssi', [$p['username'],$p['password_hash'],$claim['email'],(int)$u['id']]);
        if ($n !== 1) throw new RuntimeException('Claim update failed');
        arExecute($db, 'UPDATE account_registration_requests SET used_at=NOW() WHERE id=?', 's', [$claim['id']]);
        arExecute($db, "UPDATE auth_requests SET is_canceled=1 WHERE user_id=? AND is_canceled=0 AND purpose IN ('email_verification','password_reset')", 'i', [(int)$u['id']]);
        if (!$db->commit()) throw new RuntimeException('Registration commit failed');
        unset($_SESSION['account_registration_claim']);
        return true;
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

function arGateRegistration($db, $role, $data, $password, &$error)
{
    // บัญชีเดิมที่มี Username แล้วใช้หน้าแก้ไขของเจ้าของบัญชี ไม่เขียนทับจากการสมัคร
    $data['role'] = $role;
    try {
        if (arRow($db, 'SELECT id FROM users WHERE username=? LIMIT 1', 's', [$data['username']])) {
            $error = arDuplicateMessage();
            return true;
        }
        $id = arPendingCandidate($db, $role, $data);
        if (!$id) return false;
        arStartClaim($db, $id, $data, $password);
        header('Location: complete_registration.php');
        exit();
    } catch (DomainException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Pending registration failed'); $error = 'ไม่สามารถตรวจรายการรอลงทะเบียนได้ กรุณาลองใหม่ภายหลัง'; }
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

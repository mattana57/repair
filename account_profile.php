<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/account_registration_service.php';
require_once __DIR__ . '/activity_log.php';
header('Cache-Control: no-store');
$error='';
$saved=false;
$ownId=(int)$_SESSION['user_id'];
$account=arAccount($conn,$ownId);
if (!$account || (int)$account['registration_pending']===1) authGuardRejectSession();
$tech=strtolower($account['role'])==='technician';
$profile=$account['profile'];
$values=[];
foreach (['username','full_name','phone','email','position','department'] as $key) $values[$key]=(string)($key==='username' ? $account['username'] : ($profile[$key] ?? ''));
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'],$_POST['csrf_token'])) throw new DomainException('คำขอไม่ถูกต้อง กรุณารีเฟรชแล้วลองใหม่');
        $fields=$tech ? ['username','full_name','phone','email','position','department'] : ['username','full_name','phone','email'];
        foreach ($fields as $key) {
            if (!is_string($_POST[$key] ?? null)) throw new DomainException('กรุณากรอกข้อมูลให้ครบ');
            $values[$key]=trim($_POST[$key]);
            if ($values[$key]==='' || preg_match('/[\x00-\x1F\x7F]/u',$values[$key])) throw new DomainException('กรุณากรอกข้อมูลที่ถูกต้องให้ครบทุกช่อง');
        }
        if (preg_match('/[\s\p{Z}\p{C}]/u',$values['username'])) throw new DomainException('ชื่อผู้ใช้ต้องไม่มีช่องว่างหรืออักขระควบคุม');
        foreach (['username'=>50,'full_name'=>255,'phone'=>20,'email'=>255,'position'=>255,'department'=>255] as $key=>$limit) if (mb_strlen($values[$key],'UTF-8')>$limit) throw new DomainException('ข้อมูลยาวเกินกำหนด กรุณาตรวจสอบอีกครั้ง');
        if (!filter_var($values['email'],FILTER_VALIDATE_EMAIL) || strlen($values['email'])>255) throw new DomainException('กรุณากรอกอีเมลที่ถูกต้อง');
        if (!otpRequestAdmitSource($conn,otpRequestConfig())) throw new DomainException('ทำรายการบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่');
        if (!is_string($_POST['current_password'] ?? null) || !password_verify($_POST['current_password'],$account['password'])) throw new DomainException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        // ตรวจบัญชีจาก session และล็อกซ้ำ ไม่รับ user_id หรือ technician_id จากฟอร์ม
        if (!$conn->begin_transaction()) throw new RuntimeException('Profile transaction failed');
        try {
            $live=arAccount($conn,$ownId,true);
            if (!$live || !authGuardSessionMatches($live,$_SESSION,time()) || !hash_equals(arSnapshot($account),arSnapshot($live))) throw new DomainException('ข้อมูลหรือสิทธิ์บัญชีเปลี่ยน กรุณาเข้าสู่ระบบใหม่');
            if (arRow($conn,'SELECT id FROM users WHERE username=? AND id<>? LIMIT 1','si',[$values['username'],$ownId])) throw new DomainException(arDuplicateMessage());
            $emailChanged=trim((string)$live['profile']['email'])!==$values['email'];
            // เก็บตำแหน่งและฝ่ายเดิมสำหรับ Admin/Executive ที่ไม่มีช่องเหล่านี้ให้แก้
            $n=arExecute($conn,'UPDATE users SET username=?,full_name=?,phone=?,email=?,position=?,department=?,verified_email=IF(?=1,NULL,verified_email),email_verified_at=IF(?=1,NULL,email_verified_at),auth_version=auth_version+1 WHERE id=? AND is_active=1 AND deleted_at IS NULL',
                'ssssssiii',[$values['username'],$values['full_name'],$values['phone'],$values['email'],$tech ? $values['position'] : $live['position'],$tech ? $values['department'] : $live['department'],(int)$emailChanged,(int)$emailChanged,$ownId]);
            if ($n!==1) throw new RuntimeException('Own account update failed');
            if ($tech) arExecute($conn,'UPDATE technicians SET full_name=?,phone=?,email=?,position=?,department=? WHERE id=? AND deleted_at IS NULL','sssssi',[$values['full_name'],$values['phone'],$values['email'],$values['position'],$values['department'],(int)$live['technician_id']]);
            arExecute($conn,"UPDATE auth_requests SET is_canceled=1 WHERE user_id=? AND is_canceled=0 AND purpose IN ('email_verification','password_reset')",'i',[$ownId]);
            if (!$conn->commit()) throw new RuntimeException('Profile commit failed');
            mbsActivityLog($conn,'SAVE_ACCOUNT','user',$ownId,'เจ้าของบัญชีแก้ไขข้อมูลบัญชีของตนเอง');
            $saved=true;
            $_SESSION=[];
            session_regenerate_id(true);
        } catch (Throwable $e) { $conn->rollback(); throw $e; }
    } catch (DomainException $e) { $error=$e->getMessage(); }
    catch (Throwable $e) { error_log('Own account profile update failed'); $error=(int)$e->getCode()===1062 ? arDuplicateMessage() : 'บันทึกไม่สำเร็จ กรุณาลองใหม่ภายหลัง'; }
}
function apEscape($v) { return htmlspecialchars((string)$v,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); }
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>แก้ไขข้อมูลบัญชีของฉัน</title><link rel="stylesheet" href="account_registration.css?v=20261019-1"><link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600&display=swap" rel="stylesheet"></head>
<body class="ar-page"><main class="ar-card"><h1>แก้ไขข้อมูลบัญชีของฉัน</h1>
<?php if ($saved): ?><p class="ar-success">บันทึกข้อมูลในบัญชีเดิมแล้ว ประวัติงานและการผูก LINE ยังคงเดิม กรุณาเข้าสู่ระบบอีกครั้ง</p><a class="ar-link" href="login.php">เข้าสู่ระบบ</a>
<?php else: ?>
<p class="ar-muted">เปลี่ยนชื่อผู้ใช้หรือข้อมูลติดต่อได้โดยไม่ต้องสมัครบัญชีใหม่ หากเปลี่ยนอีเมล ต้องยืนยันอีเมลใหม่ก่อนใช้กู้คืนรหัสผ่าน</p>
<?php if ($error): ?><p class="ar-error" role="alert"><?= apEscape($error) ?></p><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= apEscape($_SESSION['csrf_token']) ?>"><div class="ar-grid">
<?php $labels=['username'=>'ชื่อผู้ใช้','full_name'=>'ชื่อ–นามสกุล','phone'=>'เบอร์โทรศัพท์','email'=>'อีเมล']; if ($tech) $labels+=['position'=>'ตำแหน่งงาน','department'=>'ฝ่ายงาน']; foreach ($labels as $key=>$label): ?>
<label class="ar-field"><?= $label ?> *<input type="<?= $key==='email' ? 'email' : ($key==='phone' ? 'tel' : 'text') ?>" name="<?= $key ?>" maxlength="<?= $key==='username' ? 50 : ($key==='phone' ? 20 : 255) ?>" value="<?= apEscape($values[$key]) ?>" required></label>
<?php endforeach; ?></div>
<label class="ar-field">รหัสผ่านปัจจุบันเพื่อยืนยันการแก้ไข *<input id="currentPassword" type="password" name="current_password" autocomplete="current-password" required></label>
<label class="ar-muted"><input id="showPassword" type="checkbox"> แสดงรหัสผ่าน</label><br><br>
<button type="submit" class="ar-button">บันทึกข้อมูล</button></form>
<a class="ar-link" href="<?= $tech ? 'technician_home.php' : (strtolower($account['role'])==='admin' ? 'dashboard.php' : 'executive_dashboard.php') ?>">กลับแดชบอร์ด</a>
<script>document.getElementById('showPassword').addEventListener('change',function(){document.getElementById('currentPassword').type=this.checked?'text':'password';});</script>
<?php endif; ?></main></body></html>

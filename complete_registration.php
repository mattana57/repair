<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/account_registration_service.php';
header('Cache-Control: no-store');
$error = '';
$success = false;
$claim = $_SESSION['account_registration_claim'] ?? null;
if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token']==='') $_SESSION['csrf_token']=bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error='คำขอไม่ถูกต้อง กรุณารีเฟรชแล้วลองใหม่';
    } elseif (isset($_POST['cancel'])) {
        if (is_array($claim)) {
            try { arExecute($conn, 'UPDATE account_registration_requests SET canceled_at=NOW() WHERE id=? AND session_hash=? AND used_at IS NULL', 'ss', [$claim['id'],hash('sha256',session_id())]); }
            catch (Throwable $e) { error_log('Registration cancellation failed'); }
        }
        unset($_SESSION['account_registration_claim']);
        header('Location: register.php'); exit();
    } else {
        try {
            $success=arFinishClaim($conn,$claim,$_POST['otp'] ?? null);
            if (!$success) $error='OTP ไม่ถูกต้อง กรุณาตรวจสอบแล้วลองอีกครั้ง หากกรอกผิดครบจำนวนที่กำหนดต้องเริ่มลงทะเบียนใหม่';
        } catch (DomainException $e) { $error=$e->getMessage(); }
        catch (Throwable $e) { error_log('Registration confirmation failed'); $error=(int)$e->getCode()===1062 ? arDuplicateMessage() : 'ยืนยันไม่สำเร็จ กรุณาลองใหม่ภายหลัง'; }
    }
}
function arEscape($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ยืนยันการลงทะเบียน</title><link rel="stylesheet" href="account_registration.css?v=20261019-1"><link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600&display=swap" rel="stylesheet"></head>
<body class="ar-page"><main class="ar-card">
<h1>ยืนยันการลงทะเบียน</h1>
<?php if ($error): ?><p class="ar-error" role="alert"><?= arEscape($error) ?></p><?php endif; ?>
<?php if ($success): ?>
<p class="ar-success">ลงทะเบียนกับรายการเดิมสำเร็จ ข้อมูลและประวัติเดิมยังอยู่ครบ</p>
<p>สามารถเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่านที่เพิ่งตั้งได้ สำหรับเจ้าหน้าที่ต้องผูกบัญชี LINE ตามขั้นตอนเดิมก่อนเข้าใช้งานแดชบอร์ด</p>
<a class="ar-link" href="login.php">ไปหน้าเข้าสู่ระบบ</a>
<?php elseif (is_array($claim)): ?>
<p>พบรายการที่แอดมินเตรียมไว้ กรุณากรอก OTP ที่ส่งไปยังอีเมลของรายการนั้น เพื่อยืนยันก่อนเชื่อมกับข้อมูลเดิม</p>
<p class="ar-muted">OTP มีอายุ 5 นาที ใช้ได้ครั้งเดียว กรุณาเปิดอีเมลในอีกแท็บและคงหน้านี้ไว้</p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= arEscape($_SESSION['csrf_token']) ?>">
<label class="ar-field">รหัส OTP<input name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required></label>
<button class="ar-button" type="submit">ยืนยันและลงทะเบียน</button>
<button class="ar-button" style="background:#64748b" type="submit" name="cancel" value="1" formnovalidate>ยกเลิก</button>
</form>
<?php else: ?><p>ไม่พบคำขอลงทะเบียนที่รอยืนยัน กรุณาเริ่มลงทะเบียนอีกครั้ง</p><a class="ar-link" href="register.php">กลับหน้าสมัครสมาชิก</a><?php endif; ?>
</main></body></html>

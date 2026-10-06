<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// 1. ตรวจสอบว่าล็อกอินอยู่หรือไม่ (แอดมินเท่านั้นที่ช่วยคนที่ล็อกอินไม่ได้)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ดึงข้อมูลบัญชีและอีเมลปัจจุบันของช่าง
$stmt = $conn->prepare("
    SELECT u.id, u.role, u.technician_id, u.verified_email, u.email_verified_at, t.email AS tech_email 
    FROM users u 
    LEFT JOIN technicians t ON u.technician_id = t.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || $user['role'] !== 'Technician' || empty($user['technician_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'บัญชีของคุณยังไม่ได้เชื่อมโยงกับข้อมูลช่าง กรุณาติดต่อแอดมิน']);
    exit();
}

$target_email = trim($user['tech_email'] ?? '');
if (empty($target_email)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลในระบบ กรุณาติดต่อแอดมินเพื่อเพิ่มอีเมลก่อนทำรายการ']);
    exit();
}

// ---------------------------------------------------------
// Action 1: ขอรับรหัส OTP (Request OTP)
// ---------------------------------------------------------
if ($action === 'request_otp') {
    // เช็คว่าอีเมลนี้ยืนยันไปแล้วหรือยัง (เช็คว่าอีเมลตรงกันและมีเวลายืนยัน)
    if ($user['verified_email'] === $target_email && !empty($user['email_verified_at'])) {
        echo json_encode(['status' => 'error', 'message' => 'อีเมลนี้ได้รับการยืนยันเรียบร้อยแล้ว']);
        exit();
    }

    // ตรวจสอบการขอซ้ำซ้อน (Cooldown 60 วินาที)
    $stmt_check_delay = $conn->prepare("SELECT last_sent_at FROM auth_requests WHERE user_id = ? AND purpose = 'email_verification' AND is_canceled = 0 ORDER BY created_at DESC LIMIT 1");
    $stmt_check_delay->bind_param("i", $user_id);
    $stmt_check_delay->execute();
    $res_delay = $stmt_check_delay->get_result();
    if ($res_delay->num_rows > 0) {
        $last_sent = strtotime($res_delay->fetch_assoc()['last_sent_at']);
        if ((time() - $last_sent) < 60) {
            echo json_encode(['status' => 'error', 'message' => 'กรุณารอ 60 วินาทีก่อนขอรหัสใหม่']);
            exit();
        }
    }
    $stmt_check_delay->close();

    // ยกเลิกคำขอยืนยันอีเมลเดิมทั้งหมดที่ยังไม่หมดอายุ
    $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE user_id = $user_id AND purpose = 'email_verification'");

    // สร้าง OTP 6 หลัก และ Token สุ่ม
    $otp_code = sprintf("%06d", mt_rand(100000, 999999));
    $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
    $request_token = bin2hex(random_bytes(32)); // ไม่ใช้เลขบัญชีเป็นโทเคน
    
    // บันทึกคำขอลงฐานข้อมูล (หมดอายุใน 5 นาที)
    $stmt_insert = $conn->prepare("
        INSERT INTO auth_requests (request_token, user_id, purpose, target_email, otp_hash, expires_at, auth_version) 
        VALUES (?, ?, 'email_verification', ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), (SELECT auth_version FROM users WHERE id = ?))
    ");
    $stmt_insert->bind_param("sissi", $request_token, $user_id, $target_email, $otp_hash, $user_id);
    
    // โหลดฟังก์ชันส่งเมล์
    require_once 'mailer.php'; 

    if ($stmt_insert->execute()) {
        $request_id = $conn->insert_id; // เก็บ ID คำขอไว้ เผื่อต้องยกเลิกถ้าส่งอีเมลพัง
        
        // ✨ เรียกใช้ฟังก์ชันส่งอีเมลผ่าน SMTP ✨
        $is_sent = sendOtpEmail($target_email, $otp_code, "ยืนยันบัญชีอีเมล");

        if ($is_sent) {
            // ✅ กฎ: ห้ามแสดงรหัส OTP บน API หรือหน้าเว็บเด็ดขาด!
            echo json_encode([
                'status' => 'success', 
                'message' => 'ระบบได้ส่งรหัส OTP ไปยังอีเมล ' . substr($target_email, 0, 3) . '***@*** แล้ว (รหัสมีอายุ 5 นาที)',
                'token' => $request_token
            ]);
        } else {
            // ✅ กฎ: หากส่งไม่สำเร็จ ต้องจัดการข้อผิดพลาดและยกเลิกคำขอทันที ห้ามถือว่าสำเร็จ!
            $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = $request_id");
            echo json_encode(['status' => 'error', 'message' => 'ระบบส่งอีเมลขัดข้อง ไม่สามารถส่ง OTP ได้ กรุณาลองใหม่อีกครั้ง']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างคำขอได้ กรุณาลองใหม่']);
    }
    $stmt_insert->close();
}

// ---------------------------------------------------------
// Action 2: ตรวจสอบ OTP (Verify OTP)
// ---------------------------------------------------------
elseif ($action === 'verify_otp') {
    $token = $_POST['token'] ?? '';
    $otp_input = $_POST['otp'] ?? '';

    if (empty($token) || empty($otp_input)) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit();
    }

    // ค้นหาคำขอที่ยังไม่ถูกยกเลิก ยังไม่หมดอายุ และจุดประสงค์ถูกต้อง
    $stmt_req = $conn->prepare("
        SELECT id, otp_hash, target_email, failed_attempts, auth_version, expires_at 
        FROM auth_requests 
        WHERE request_token = ? AND user_id = ? AND purpose = 'email_verification' AND is_canceled = 0 AND used_at IS NULL
    ");
    $stmt_req->bind_param("si", $token, $user_id);
    $stmt_req->execute();
    $request = $stmt_req->get_result()->fetch_assoc();
    $stmt_req->close();

    if (!$request) {
        echo json_encode(['status' => 'error', 'message' => 'คำขอไม่ถูกต้อง ถูกยกเลิก หรือถูกใช้งานไปแล้ว']);
        exit();
    }

    // ตรวจสอบวันหมดอายุ (Double check ฝั่ง PHP)
    if (strtotime($request['expires_at']) < time()) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'รหัส OTP หมดอายุแล้ว กรุณาขอใหม่']);
        exit();
    }

    // ตรวจสอบจำนวนครั้งที่กรอกผิด
    if ($request['failed_attempts'] >= 5) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'คุณกรอกรหัสผิดเกินจำนวนที่กำหนด คำขอนี้ถูกยกเลิกแล้ว']);
        exit();
    }

    // ตรวจสอบว่าอีเมลยังตรงกับตอนที่ขอ OTP ไหม (เผื่อแอดมินเปลี่ยนอีเมลช่างระหว่างที่กำลังกรอก OTP)
    if ($request['target_email'] !== $target_email) {
        $conn->query("UPDATE auth_requests SET is_canceled = 1 WHERE id = " . $request['id']);
        echo json_encode(['status' => 'error', 'message' => 'อีเมลของคุณถูกเปลี่ยนแปลงระหว่างดำเนินการ กรุณาขอรหัสใหม่']);
        exit();
    }

    // ตรวจ OTP
    if (password_verify($otp_input, $request['otp_hash'])) {
        // อัปเดตตาราง Users ให้ผูกกับอีเมลที่ยืนยัน
        $stmt_update_user = $conn->prepare("UPDATE users SET verified_email = ?, email_verified_at = NOW() WHERE id = ?");
        $stmt_update_user->bind_param("si", $request['target_email'], $user_id);
        $stmt_update_user->execute();
        $stmt_update_user->close();

        // อัปเดตตารางคำขอ ว่าถูกใช้งานและยืนยันแล้ว
        $conn->query("UPDATE auth_requests SET verified_at = NOW(), used_at = NOW() WHERE id = " . $request['id']);

        echo json_encode(['status' => 'success', 'message' => 'ยืนยันอีเมลสำเร็จ!']);
    } else {
        // บันทึกการกรอกผิด
        $conn->query("UPDATE auth_requests SET failed_attempts = failed_attempts + 1 WHERE id = " . $request['id']);
        $remain = 4 - $request['failed_attempts'];
        echo json_encode(['status' => 'error', 'message' => "รหัส OTP ไม่ถูกต้อง (เหลือโอกาส $remain ครั้ง)"]);
    }
}
?>
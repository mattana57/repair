<?php
session_start();
require_once 'db_connect.php'; 

// ฟังก์ชันช่วยบันทึกการเข้าระบบผิดพลาด (Rate Limiting)
function record_failed_attempt($file) {
    $data = ['attempts' => 1, 'last_time' => time()];
    if (file_exists($file)) {
        $existing = json_decode(file_get_contents($file), true);
        $data['attempts'] = $existing['attempts'] + 1;
    }
    file_put_contents($file, json_encode($data));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // จำกัดความถี่การล็อกอิน (Rate Limiting) ป้องกันการเดารหัสผ่านโดยอ้างอิงจาก IP Address
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $attempt_file = 'uploads/login_attempts_' . md5($ip_address) . '.txt';
    $max_attempts = 5;
    $lockout_time = 15 * 60; // ระงับ 15 นาที
    
    if (file_exists($attempt_file)) {
        $attempts_data = json_decode(file_get_contents($attempt_file), true);
        if ($attempts_data['attempts'] >= $max_attempts) {
            if (time() - $attempts_data['last_time'] < $lockout_time) {
                $_SESSION['login_error'] = "พยายามเข้าสู่ระบบผิดพลาดหลายครั้งเกินไป กรุณารอ 15 นาทีแล้วลองใหม่";
                header("Location: login.php");
                exit();
            } else {
                // ปลดล็อกเมื่อครบเวลาที่กำหนด
                unlink($attempt_file);
            }
        }
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจสอบค่าว่างและบันทึกการผิดพลาด
    if (empty($username) || empty($password)) {
        record_failed_attempt($attempt_file);
        $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        header("Location: login.php");
        exit();
    }

    $sql = "SELECT id, username, password, full_name, role, technician_id, is_active, auth_version FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // ✨ ระบบตรวจสอบรหัสผ่าน และ Auto-Migration (ย้ายรหัสผ่านเดิมให้เป็น Hash) ✨
        $is_password_correct = false;
        
        // 1. ตรวจสอบรหัสผ่านแบบ Hash ตามมาตรฐานความปลอดภัยใหม่ (ข้อ 7.1, 7.2)
        if (password_verify($password, $user['password'])) {
            $is_password_correct = true;
        } 
        // 2. ตรวจสอบรหัสผ่านบัญชีเก่าที่เป็น Plain text และแปลงเป็น Hash ให้อัตโนมัติ (ข้อ 7.4)
        elseif ($password === $user['password']) {
            $is_password_correct = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update_pwd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt_update_pwd) {
                $stmt_update_pwd->bind_param("si", $new_hash, $user['id']);
                $stmt_update_pwd->execute();
                $stmt_update_pwd->close();
            }
        }

        if ($is_password_correct) {
            
            // ล้างประวัติการล็อกอินผิดพลาดเมื่อเข้าสู่ระบบสำเร็จ
            if (file_exists($attempt_file)) {
                unlink($attempt_file);
            }
            if (file_exists($attempt_file)) {
                unlink($attempt_file);
            }

            if ($user['is_active'] != 1) {
                $_SESSION['login_error'] = "บัญชีนี้ถูกระงับการใช้งาน";
                header("Location: login.php");
                exit();
            }
        
        $role_lower = strtolower($user['role']);

            if ($role_lower === 'technician') {
                if (empty($user['technician_id'])) {
                    $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                    header("Location: login.php");
                    exit();
                }

                $tech_check_sql = "SELECT id FROM technicians WHERE id = ?";
                $tech_stmt = $conn->prepare($tech_check_sql);
                $tech_stmt->bind_param("i", $user['technician_id']);
                $tech_stmt->execute();
                if ($tech_stmt->get_result()->num_rows === 0) {
                    $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
                    header("Location: login.php");
                    exit();
                }
                $tech_stmt->close();
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id']; 
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['auth_version'] = $user['auth_version'];
            
            if ($role_lower === 'technician') {
                $_SESSION['technician_id'] = $user['technician_id'];
            } else {
                unset($_SESSION['technician_id']);
            }
        
        // ==========================================
            // ตรวจสอบว่าระบบมีการฝากจำ URL ไว้ก่อนล็อกอินหรือไม่
            // ==========================================
            $redirect = "";
            if (isset($_SESSION['redirect_url']) && !empty($_SESSION['redirect_url'])) {
                $saved_url = $_SESSION['redirect_url'];
                
                if (strpos($saved_url, 'update_repair.php') !== false) {
                    $url_parts = parse_url($saved_url);
                    parse_str($url_parts['query'] ?? '', $query_params);
                    if (isset($query_params['id']) && is_numeric($query_params['id'])) {
                        $redirect = "update_repair.php?id=" . (int)$query_params['id'];
                    }
                }
                unset($_SESSION['redirect_url']);
            }

            // ==========================================
            // กรณีไม่มีปลายทางที่ถูกต้อง หรือเข้าผ่านหน้าเว็บตรงๆ
            // ==========================================
            if (empty($redirect)) {
                if ($role_lower === 'admin') {
                    $redirect = "dashboard.php";
                } elseif ($role_lower === 'executive') {
                    $redirect = "executive_dashboard.php";
                } elseif ($role_lower === 'technician') {
                    $redirect = "technician_home.php";
                } else {
                    session_unset();
                    session_destroy();
                    $_SESSION['login_error'] = "ไม่อนุญาตให้เข้าสู่ระบบ";
                    header("Location: login.php");
                    exit();
                }
            }

            if ($role_lower === 'technician' && strpos($redirect, 'dashboard.php') !== false) {
                $redirect = "technician_home.php";
            }

            header("Location: " . $redirect);
            exit();
            
        } else {
            // บันทึกการเข้าระบบผิดพลาด (รหัสผ่านผิด)
            record_failed_attempt($attempt_file);
            $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit();
        }
    } else {
        // บันทึกการเข้าระบบผิดพลาด (ไม่พบชื่อผู้ใช้)
        record_failed_attempt($attempt_file);
        $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        header("Location: login.php");
        exit();
    }
}
?>
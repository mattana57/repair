<?php
session_start();
require_once 'db_connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
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

        if (password_verify($password, $user['password'])) {
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
            $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        header("Location: login.php");
        exit();
    }
}
?>
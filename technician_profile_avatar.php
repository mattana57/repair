<?php
// เรียกหลัง auth_guard และตรวจการผูกบัญชีแล้วเท่านั้น
if (!defined('MBS_TECH_PERSONAL_PROFILE') || strtolower((string)($_SESSION['role'] ?? '')) !== 'technician') {
    http_response_code(403); exit('ไม่อนุญาตให้เข้าถึง');
}
function tpa_stmt($conn, $sql, $types, array $values) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('เตรียมข้อมูลโปรไฟล์ไม่สำเร็จ');
    try {
        if (!$stmt->bind_param($types, ...$values) || !$stmt->execute()) throw new RuntimeException('บันทึกข้อมูลโปรไฟล์ไม่สำเร็จ');
        return $stmt;
    } catch (Throwable $e) { $stmt->close(); throw $e; }
}
function tpa_cleanup($conn, $path, $uid) {
    // ลบได้เฉพาะไฟล์ส่วนตัวที่สร้างให้บัญชีนี้ และไม่มีตารางใดอ้างอิงแล้ว
    if (!is_string($path) || !preg_match('~\Auploads/tech_self_' . (int)$uid . '_[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $path)) return;
    $stmt = tpa_stmt($conn, 'SELECT (SELECT COUNT(*) FROM users WHERE avatar_url = ?) + (SELECT COUNT(*) FROM technicians WHERE avatar_url = ?) AS refs', 'ss', [$path, $path]);
    try { $row = $stmt->get_result()->fetch_assoc(); } finally { $stmt->close(); }
    if ($row && (int)$row['refs'] === 0 && !is_link(__DIR__ . '/' . $path) && is_file(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
}
function tpa_upload($file, $uid) {
    if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK
        || !isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('กรุณาเลือกไฟล์รูปภาพใหม่');
    $bytes = filesize($file['tmp_name']);
    if ($bytes === false || $bytes < 1 || $bytes > 5 * 1024 * 1024) throw new RuntimeException('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_WEBP => ['image/webp', 'webp'], IMAGETYPE_GIF => ['image/gif', 'gif']];
    if (!$info || !isset($types[$info[2]]) || $mime !== $types[$info[2]][0]
        || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000) throw new RuntimeException('กรุณาใช้ไฟล์ JPG, PNG, WEBP หรือ GIF ที่เป็นรูปภาพจริง');
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('เตรียมพื้นที่เก็บรูปไม่สำเร็จ');
    $path = 'uploads/tech_self_' . (int)$uid . '_' . bin2hex(random_bytes(16)) . '.' . $types[$info[2]][1];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $path)) throw new RuntimeException('บันทึกไฟล์รูปไม่สำเร็จ');
    return $path;
}
function tpa_change($conn, $uid, $techId, $action, $file) {
    $started = false; $new = null; $old = null; $commitAttempted = false;
    try {
        if (!$conn->begin_transaction()) throw new RuntimeException('เริ่มบันทึกไม่ได้');
        $started = true;
        $stmt = tpa_stmt($conn, 'SELECT u.id, u.role, u.technician_id, u.is_active, u.auth_version, u.deleted_at, u.avatar_url, t.approval_status, t.deleted_at AS tech_deleted_at FROM users u JOIN technicians t ON t.id = u.technician_id WHERE u.id = ? FOR UPDATE', 'i', [$uid]);
        try { $row = $stmt->get_result()->fetch_assoc(); } finally { $stmt->close(); }
        if (!$row || strtolower((string)$row['role']) !== 'technician' || (int)$row['technician_id'] !== (int)$techId
            || (int)$row['is_active'] !== 1 || $row['deleted_at'] !== null || $row['tech_deleted_at'] !== null
            || $row['approval_status'] !== 'อนุมัติแล้ว'
            || (isset($_SESSION['auth_version']) && (int)$row['auth_version'] !== (int)$_SESSION['auth_version'])) throw new RuntimeException('สิทธิ์บัญชีเปลี่ยนแล้ว กรุณาเข้าสู่ระบบใหม่');
        if (!in_array($action, ['upload', 'delete'], true)) throw new RuntimeException('คำขอไม่ถูกต้อง');
        $old = $row['avatar_url'];
        if ($action === 'upload') $new = tpa_upload($file, $uid);
        $stmt = tpa_stmt($conn, "UPDATE users SET avatar_url = ? WHERE id = ? AND LOWER(role) = 'technician' AND technician_id = ? AND deleted_at IS NULL", 'sii', [$new, $uid, $techId]);
        $stmt->close();
        $commitAttempted = true;
        if (!$conn->commit()) throw new RuntimeException('ยืนยันการบันทึกไม่ได้ กรุณาตรวจรูปปัจจุบันก่อนทำรายการซ้ำ');
        $started = false;
    } catch (Throwable $e) {
        $rolledBack = !$started;
        if ($started) { try { $rolledBack = $conn->rollback(); } catch (Throwable $ignored) {} }
        if (!$commitAttempted && $rolledBack && $new !== null) { try { tpa_cleanup($conn, $new, $uid); } catch (Throwable $ignored) {} }
        throw $e;
    }
    try { tpa_cleanup($conn, $old, $uid); } catch (Throwable $ignored) { error_log('Technician personal avatar cleanup skipped'); }
    try { if (function_exists('mbsActivityLog')) mbsActivityLog($conn, 'PROFILE_PHOTO', 'user', $uid, $action === 'delete' ? 'ลบรูปโปรไฟล์ส่วนตัวเจ้าหน้าที่' : 'เปลี่ยนรูปโปรไฟล์ส่วนตัวเจ้าหน้าที่'); } catch (Throwable $ignored) { error_log('Technician personal avatar log skipped'); }
}
if (!is_string($_SESSION['csrf_token'] ?? null) || $_SESSION['csrf_token'] === '') $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['tech_profile_action'])) {
    try {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) throw new RuntimeException('คำขอหมดอายุ กรุณาโหลดหน้าใหม่');
        $action = $_POST['tech_profile_action'];
        if (!is_string($action) || !in_array($action, ['upload', 'delete'], true)) throw new RuntimeException('คำขอไม่ถูกต้อง');
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid < 1 || (int)$tech_id_session < 1) throw new RuntimeException('กรุณาเข้าสู่ระบบใหม่');
        tpa_change($conn, $uid, (int)$tech_id_session, $action, $_FILES['profile_avatar'] ?? null);
        $_SESSION['tech_profile_notice'] = ['ok' => true, 'text' => $action === 'delete' ? 'ลบรูปโปรไฟล์แล้ว' : 'เปลี่ยนรูปโปรไฟล์แล้ว'];
    } catch (Throwable $e) {
        error_log('Technician personal avatar request failed');
        $_SESSION['tech_profile_notice'] = ['ok' => false, 'text' => $e instanceof RuntimeException && !($e instanceof mysqli_sql_exception) ? $e->getMessage() : 'บันทึกรูปไม่สำเร็จ กรุณาตรวจรูปปัจจุบันแล้วลองใหม่'];
    }
    header('Location: technician_home.php?tab=dash', true, 303); exit;
}
$tech_profile_notice = $_SESSION['tech_profile_notice'] ?? null;
unset($_SESSION['tech_profile_notice']);
$stmt = tpa_stmt($conn, "SELECT avatar_url FROM users WHERE id = ? AND LOWER(role) = 'technician' AND technician_id = ? AND deleted_at IS NULL", 'ii', [(int)$_SESSION['user_id'], (int)$tech_id_session]);
try { $tech_personal_account = $stmt->get_result()->fetch_assoc(); } finally { $stmt->close(); }
$tech_has_personal_avatar = false;
$tech_profile_image = 'https://api.dicebear.com/7.x/notionists/svg?seed=' . urlencode((string)($_SESSION['username'] ?? $_SESSION['user_id']) . '_web_profile') . '&backgroundColor=e2e8f0';
$tech_personal_path = (string)($tech_personal_account['avatar_url'] ?? '');
if (preg_match('~\Auploads/[A-Za-z0-9_.-]+\.(?:jpg|jpeg|png|webp|gif)\z~i', $tech_personal_path) && is_file(__DIR__ . '/' . $tech_personal_path)) {
    $tech_profile_image = $tech_personal_path . '?v=' . filemtime(__DIR__ . '/' . $tech_personal_path);
    $tech_has_personal_avatar = true;
}

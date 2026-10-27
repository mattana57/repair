<?php
// Read-only review polling. Reuse the web authentication guard; never update jobs.
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function mbs_live_review_fail(int $status): void {
    http_response_code($status);
    echo json_encode(['error' => 'ไม่สามารถอ่านรีวิวได้'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') mbs_live_review_fail(405);
$role = strtolower((string) ($_SESSION['role'] ?? ''));
$layout = $_GET['layout'] ?? null;
$allowed = ['update' => ['admin', 'technician'], 'view' => ['admin', 'executive']];
if (!is_string($layout) || !isset($allowed[$layout]) || !in_array($role, $allowed[$layout], true)) {
    mbs_live_review_fail(403);
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) mbs_live_review_fail(400);
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/repair_review_fragment.php';
try {
    $sql = 'SELECT r.rating, r.review_comment, r.rated_at, r.completed_at,
                   r.reporter_name, r.technician_name
            FROM repairs r WHERE r.id = ? AND r.deleted_at IS NULL';
    $types = 'i';
    $params = [(int) $id];
    if ($role === 'technician') {
        $techId = filter_var($_SESSION['technician_id'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if (!$techId || !$userId) mbs_live_review_fail(403);
        $sql .= " AND r.technician_id = ? AND EXISTS (
            SELECT 1 FROM technicians t JOIN users u ON u.technician_id = t.id
            WHERE t.id = r.technician_id AND t.deleted_at IS NULL
              AND t.approval_status = 'อนุมัติแล้ว'
              AND u.id = ? AND LOWER(u.role) = 'technician'
              AND u.is_active = 1 AND u.deleted_at IS NULL)";
        $types .= 'ii';
        $params[] = (int) $techId;
        $params[] = (int) $userId;
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt || !$stmt->bind_param($types, ...$params) || !$stmt->execute()) {
        throw new RuntimeException('Cannot read repair review');
    }
    $result = $stmt->get_result();
    if (!$result) throw new RuntimeException('Cannot read repair review');
    $repair = $result->fetch_assoc();
    $stmt->close();
    if (!$repair) mbs_live_review_fail(404);
    echo json_encode(['id' => (int) $id,
        'html' => mbs_repair_review_fragment($repair, $layout, $role === 'executive')],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Review polling failed: ' . get_class($e));
    mbs_live_review_fail(503);
}

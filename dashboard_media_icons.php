<?php
// อ่านเฉพาะจำนวนและประเภทไฟล์แนบ หลัง Dashboard ตรวจสิทธิ์แล้ว
if (!defined('MBS_DASHBOARD_MEDIA')) { http_response_code(403); exit('ไม่อนุญาตให้เข้าถึงหน้านี้'); }
function dm_media_map($conn) {
    static $cache = [];
    $key = spl_object_hash($conn);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $map = [];
    try {
        $table = $conn->query("SHOW TABLES LIKE 'repair_attachments'");
        if ($table && $table->num_rows > 0) {
            $table->free();
            $result = $conn->query("SELECT repair_id, media_type, COUNT(*) AS total FROM repair_attachments WHERE state = 'ready' AND media_type IN ('image','video') GROUP BY repair_id, media_type");
            if (!$result) throw new RuntimeException('อ่านประเภทไฟล์แนบไม่สำเร็จ');
            while ($row = $result->fetch_assoc()) {
                $id = (int)$row['repair_id'];
                if ($id > 0 && in_array($row['media_type'], ['image','video'], true)) $map[$id][$row['media_type']] = (int)$row['total'];
            }
            $result->free();
        } elseif ($table) $table->free();
    } catch (Throwable $error) { error_log('Dashboard attachment counts unavailable'); }
    return $cache[$key] = $map;
}
function dm_legacy_kind($value) {
    $value = trim((string)$value);
    if ($value === '' || $value === '-' || strtolower($value) === 'null') return '';
    $path = parse_url($value, PHP_URL_PATH);
    $ext = strtolower(pathinfo(is_string($path) ? $path : $value, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4','mov','webm','m4v','avi','3gp'], true) ? 'video' : 'image';
}
function dm_enrich($conn, array $row) {
    $map = dm_media_map($conn);
    $counts = $map[(int)($row['id'] ?? 0)] ?? [];
    foreach (['image_path','image_before'] as $column) {
        $kind = dm_legacy_kind($row[$column] ?? '');
        if ($kind !== '') $counts[$kind] = max(1, (int)($counts[$kind] ?? 0));
    }
    $row['attachment_image_count'] = (int)($counts['image'] ?? 0);
    $row['attachment_video_count'] = (int)($counts['video'] ?? 0);
    return $row;
}
function dm_icons($conn, array $row) {
    $row = dm_enrich($conn, $row); $html = '';
    if ($row['attachment_image_count'] > 0) $html .= '<i class="fas fa-image text-slate-400 ml-1" title="มีรูปภาพแนบ" role="img" aria-label="มีรูปภาพแนบ"></i>';
    if ($row['attachment_video_count'] > 0) $html .= '<i class="fas fa-video text-slate-400 ml-1" title="มีวิดีโอแนบ" role="img" aria-label="มีวิดีโอแนบ"></i>';
    return $html;
}

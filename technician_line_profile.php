<?php
// LINE profile cache for technicians. Included only by the authenticated dashboard dispatcher.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbs_line_profile_token() {
    $channelAccessToken = '';
    $path = __DIR__.'/env.php';
    if (is_file($path)) {
        ob_start();
        try { require $path; } finally { ob_end_clean(); }
    }
    return is_string($channelAccessToken) ? trim($channelAccessToken) : '';
}
function mbs_line_profile_request($lineId) {
    if (!preg_match('/\AU[0-9a-f]{32}\z/D', $lineId) || !function_exists('curl_init')) return '';
    $token = mbs_line_profile_token();
    if ($token === '' || preg_match('/[\r\n]/', $token)) return '';
    $ch = curl_init('https://api.line.me/v2/bot/profile/'.rawurlencode($lineId));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>5, CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token]]);
    $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($status !== 200 || !is_string($raw)) return '';
    $profile = json_decode($raw, true);
    if (!is_array($profile) || ($profile['userId'] ?? '') !== $lineId || !is_string($profile['displayName'] ?? null)) return '';
    return trim($profile['displayName']);
}
function mbs_technician_line_profile($db, $id) {
    $rows = mbs_feature_rows($db, "SELECT t.id,t.line_user_id,t.approval_status,
        (SELECT lp.line_display_name FROM line_technician lp WHERE lp.technician_id=t.id AND BINARY lp.line_user_id=BINARY t.line_user_id LIMIT 1) AS linked_line_name
        FROM technicians t WHERE t.id=? AND t.deleted_at IS NULL LIMIT 1", 'i', [$id]);
    if (!$rows) return null;
    $t = $rows[0]; $lineId = trim((string)($t['line_user_id'] ?? ''));
    $name = trim((string)($t['linked_line_name'] ?? ''));
    if ($lineId === '' || ($t['approval_status'] ?? '') !== 'อนุมัติแล้ว') $name = '';
    elseif ($name === '') {
        $cache = $_SESSION['mbs_line_profile_cache'][$lineId] ?? null;
        if (is_array($cache) && ($cache['expires'] ?? 0) > time()) $name = $cache['name'];
        else {
            $name = mbs_line_profile_request($lineId);
            $_SESSION['mbs_line_profile_cache'][$lineId] = ['name'=>$name, 'expires'=>time()+($name !== '' ? 900 : 60)];
        }
        if ($name !== '') {
            // Recheck the current link in the write itself; an unlink/relink during the API request must not save a stale identity.
            $stmt = $db->prepare("INSERT INTO line_technician (technician_id,line_user_id,line_display_name,full_name,fetched_at)
                SELECT t.id,t.line_user_id,?,t.full_name,? FROM technicians t
                WHERE t.id=? AND BINARY t.line_user_id=BINARY ? AND t.approval_status='อนุมัติแล้ว' AND t.deleted_at IS NULL
                ON DUPLICATE KEY UPDATE line_user_id=VALUES(line_user_id),line_display_name=VALUES(line_display_name),full_name=VALUES(full_name),fetched_at=VALUES(fetched_at)");
            if (!$stmt) throw new RuntimeException('Cannot prepare technician LINE profile cache');
            $fetchedAt = (new DateTimeImmutable('now',new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
            $stmt->bind_param('ssis',$name,$fetchedAt,$id,$lineId);
            if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Cannot save technician LINE profile cache'); }
            $saved = $stmt->affected_rows; $stmt->close();
            if ($saved === 0) {
                $current=mbs_feature_rows($db,"SELECT t.id FROM technicians t WHERE t.id=? AND BINARY t.line_user_id=BINARY ? AND t.approval_status='อนุมัติแล้ว' AND t.deleted_at IS NULL LIMIT 1",'is',[$id,$lineId]);
                if (!$current) $name='';
            }
        }
    }
    return ['id'=>(int)$t['id'], 'line_user_id'=>$lineId, 'linked_line_name'=>$name];
}

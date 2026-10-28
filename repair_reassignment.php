<?php
// Shared rendering helpers. This file performs no updates and grants no permissions.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbsAssignmentQuery($db,$sql,$types='',...$values) {
    $stmt=$db->prepare($sql);
    if (!$stmt || ($types!=='' && !$stmt->bind_param($types,...$values)) || !$stmt->execute()) throw new RuntimeException('Assignment database operation failed');
    return $stmt;
}
function mbsAssignmentReady($db) {
    try {
        foreach (['repair_assignment_periods','repair_reassignments'] as $table) {
            $s=mbsAssignmentQuery($db,'SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?','s',$table);
            $r=$s->get_result()->fetch_assoc();$s->close(); if ((int)$r['n']!==1) return false;
        }
        $s=mbsAssignmentQuery($db,"SELECT COUNT(*) AS n FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name='mbs_assignment_after_update'");
        $r=$s->get_result()->fetch_assoc();$s->close();return (int)$r['n']===1;
    } catch(Throwable $e) { return false; }
}
function mbsAssignmentEscape($s) { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function mbsAssignmentPanel($db,$job,$isAdmin,$csrf) {
    if (!mbsAssignmentReady($db)) {
        if ($isAdmin) echo '<p class="text-sm text-amber-700 mt-4">กรุณาติดตั้ง install_reassignment.sql ก่อนใช้การเปลี่ยนผู้รับผิดชอบ</p>';
        return;
    }
    $jobId=(int)$job['id'];
    if (!isset($_SESSION['reassign_keys'])) $_SESSION['reassign_keys']=[];
    if (empty($_SESSION['reassign_keys'][$jobId])) $_SESSION['reassign_keys'][$jobId]=bin2hex(random_bytes(16));
    $open=in_array($job['status'],['กำลังดำเนินการ','ช่างรับเรื่องแจ้งซ่อมแล้ว'],true) && (int)$job['technician_id']>0;
    echo '<section class="mt-6 p-4 border border-slate-200 rounded-xl"><h3 class="font-bold text-slate-800 mb-3">ผู้รับผิดชอบและประวัติการเปลี่ยนเจ้าหน้าที่</h3>';
    if (!empty($_SESSION['reassign_notice'][$jobId])) {
        echo '<p class="mb-3 text-sm text-sky-800">'.mbsAssignmentEscape($_SESSION['reassign_notice'][$jobId]).'</p>';unset($_SESSION['reassign_notice'][$jobId]);
    }
    if ($isAdmin && $open) {
        $s=mbsAssignmentQuery($db,"SELECT DISTINCT t.id,t.full_name FROM technicians t JOIN users u ON u.technician_id=t.id WHERE t.deleted_at IS NULL AND t.approval_status='อนุมัติแล้ว' AND LOWER(u.role)='technician' AND u.is_active=1 AND u.deleted_at IS NULL AND t.id<>? ORDER BY t.full_name,t.id",'i',(int)$job['technician_id']);
        $list=$s->get_result();
        echo '<form method="post" action="reassign_repair.php" class="space-y-3"><input type="hidden" name="id" value="'.$jobId.'"><input type="hidden" name="expected_technician_id" value="'.(int)$job['technician_id'].'"><input type="hidden" name="repair_csrf" value="'.mbsAssignmentEscape($csrf).'"><input type="hidden" name="request_key" value="'.mbsAssignmentEscape($_SESSION['reassign_keys'][$jobId]).'">';
        echo '<label class="block text-sm font-semibold">เปลี่ยนผู้รับผิดชอบ <select name="new_technician_id" required class="block w-full p-3 border rounded-xl mt-2"><option value="">เลือกเจ้าหน้าที่คนใหม่</option>';
        while ($t=$list->fetch_assoc()) echo '<option value="'.(int)$t['id'].'">'.mbsAssignmentEscape($t['full_name']).'</option>';
        $s->close();
        echo '</select></label><label class="block text-sm font-semibold">เหตุผลการเปลี่ยน<textarea name="reason" required maxlength="1000" rows="2" class="block w-full p-3 border rounded-xl mt-2" placeholder="ระบุเหตุผล"></textarea></label><button type="submit" class="bg-sky-700 text-white rounded-xl px-4 py-3 font-bold">ยืนยันเปลี่ยนเจ้าหน้าที่และแจ้งเตือน</button><p class="text-xs text-slate-500">ปุ่มนี้เปลี่ยนเฉพาะผู้รับผิดชอบ กรุณาบันทึกการแก้ไขอื่นก่อน</p></form>';
    }
    $s=mbsAssignmentQuery($db,'SELECT * FROM repair_reassignments WHERE repair_id=? ORDER BY id DESC','i',$jobId);$rows=$s->get_result();
    while ($r=$rows->fetch_assoc()) echo '<div class="mt-3 p-3 bg-slate-50 rounded-xl text-sm"><strong>'.mbsAssignmentEscape($r['old_name']).' → '.mbsAssignmentEscape($r['new_name']).'</strong><p>'.mbsAssignmentEscape($r['changed_at']).' · แอดมิน #'.(int)$r['admin_user_id'].'</p><p>เหตุผล: '.mbsAssignmentEscape($r['reason']).'</p></div>';
    $s->close();
    echo '<h4 class="font-semibold mt-4 mb-2">ระยะเวลารับผิดชอบแต่ละช่วง</h4>';
    $s=mbsAssignmentQuery($db,"SELECT technician_name,started_at,ended_at,CASE WHEN started_at IS NULL THEN NULL ELSE GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,COALESCE(ended_at,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 HOUR)))) END AS seconds FROM repair_assignment_periods WHERE repair_id=? ORDER BY id",'i',$jobId);$rows=$s->get_result();
    while ($r=$rows->fetch_assoc()) {
        $seconds=$r['seconds'];$duration=$seconds===null?'ไม่ทราบเวลาเริ่ม':floor($seconds/3600).' ชม. '.floor(($seconds%3600)/60).' นาที';
        echo '<p class="text-sm mb-2">'.mbsAssignmentEscape($r['technician_name']).' · '.mbsAssignmentEscape($r['started_at']??'ไม่ทราบเวลาเริ่ม').' ถึง '.mbsAssignmentEscape($r['ended_at']??'ปัจจุบัน').' · '.mbsAssignmentEscape($duration).'</p>';
    }
    $s->close();echo '<p class="text-xs text-slate-500 mt-2">ระยะเวลาตั้งแต่เริ่มรับผิดชอบถึงส่งต่อหรือปิดงาน ไม่ใช่เวลาลงมือซ่อมจริง เวลารวมใบงานยังคงเดิม</p></section>';
}

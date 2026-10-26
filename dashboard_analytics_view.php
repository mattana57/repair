<?php
// Presentation only; filtering, role scope and PHP pagination remain in dashboard_drill.php.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbs_render_analytics_table(array $slice, $total, $role) {
    if (!$total) { echo '<p class="mbs-analytics-empty">ไม่พบงานตามเงื่อนไขที่เลือก ลองเลือก “แสดงงานทั้งหมด”</p>'; return; }
    echo '<div class="mbs-analytics-table-wrap"><table class="mbs-analytics-table"><thead><tr><th>เลขที่งาน</th><th>อุปกรณ์ / ปัญหา</th><th>สถานที่</th><th>ผู้แจ้ง</th><th>เจ้าหน้าที่</th><th>สถานะ</th><th>เวลาการทำงาน</th></tr></thead><tbody>';
    foreach ($slice as $r) {
        $url=($role==='executive'?'view_repair.php':'update_repair.php').'?id='.(int)$r['id'];
        $status=trim((string)($r['status']??''));
        $tone=['รอรับเรื่อง'=>'pending','กำลังดำเนินการ'=>'progress','ซ่อมเสร็จแล้ว'=>'complete'][$status]??'other';
        echo '<tr><td data-label="เลขที่งาน"><a class="mbs-analytics-ticket" href="'.mbs_h($url).'" target="_blank" rel="noopener">'.mbs_h($r['ticket_no']).'<span>ดูรายละเอียด ↗</span></a></td>';
        echo '<td data-label="อุปกรณ์ / ปัญหา"><strong>'.mbs_h($r['equipment_type']??'ไม่ระบุอุปกรณ์').'</strong><p class="mbs-analytics-problem">'.nl2br(mbs_h($r['problem_desc']??'')).'</p></td>';
        echo '<td data-label="สถานที่">'.mbs_h(($r['location']??'')?:'ไม่ระบุสถานที่').'</td>';
        echo '<td data-label="ผู้แจ้ง"><strong>'.mbs_h($r['reporter_label']).'</strong>'.(!empty($r['line_user_id'])?'<button type="button" class="mbs-history-link" data-mbs-reporter="'.mbs_h($r['line_user_id']).'">ดูประวัติผู้แจ้ง</button>':'').'</td>';
        echo '<td data-label="เจ้าหน้าที่">'.mbs_h($r['technician_name']??'ยังไม่มอบหมาย').'</td>';
        echo '<td data-label="สถานะ"><span class="mbs-analytics-status mbs-analytics-status-'.$tone.'">'.mbs_h($status?:'ไม่ระบุสถานะ').'</span></td>';
        echo '<td data-label="เวลาการทำงาน"><dl class="mbs-analytics-times"><div><dt>รับงาน</dt><dd>'.mbs_h(!empty($r['received_at'])?mbs_date_display($r['received_at']):'ยังไม่รับงาน').'</dd></div><div><dt>ปิดงาน</dt><dd>'.mbs_h(!empty($r['completed_at'])?mbs_date_display($r['completed_at']):'ยังไม่ปิดงาน').'</dd></div><div><dt>ระยะเวลา</dt><dd>'.mbs_h(mbs_duration($r['received_at']??null,$r['completed_at']??null)).'</dd></div></dl></td></tr>';
    }
    echo '</tbody></table></div>';
}

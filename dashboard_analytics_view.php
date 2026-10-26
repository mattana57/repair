<?php
// Presentation only; filtering, role scope and PHP pagination remain in dashboard_drill.php.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
function mbs_render_analytics_table(array $slice, $total, $role, $kind = '') {
    if (!$total) { echo '<p class="mbs-analytics-empty">ไม่พบงานตามเงื่อนไขที่เลือก ลองเลือก “แสดงงานทั้งหมด”</p>'; return; }
    echo '<div class="mbs-analytics-work-list">';
    foreach ($slice as $r) {
        $url=($role==='executive'?'view_repair.php':'update_repair.php').'?id='.(int)$r['id'];
        $status=trim((string)($r['status']??''));
        $tone=['รอรับเรื่อง'=>'pending','กำลังดำเนินการ'=>'progress','ซ่อมเสร็จแล้ว'=>'complete'][$status]??'other';
        echo '<article class="mbs-analytics-work" data-status-tone="'.$tone.'"><header><div><span class="mbs-analytics-ticket">เลขที่งาน '.mbs_h($r['ticket_no']).'</span><h4>'.mbs_h($r['equipment_type']??'ไม่ระบุอุปกรณ์').'</h4></div><span class="mbs-analytics-status mbs-analytics-status-'.$tone.'">'.mbs_h($status?:'ไม่ระบุสถานะ').'</span></header>';
        echo '<p class="mbs-analytics-work-problem"><span>ปัญหา: </span>'.nl2br(mbs_h($r['problem_desc']??'')).'</p>';
        echo '<dl class="mbs-analytics-work-people"><div><dt>สถานที่</dt><dd>'.mbs_h(($r['location']??'')?:'ไม่ระบุสถานที่').'</dd></div><div><dt>ผู้แจ้งซ่อม</dt><dd>'.mbs_h($r['reporter_label']).'</dd></div><div><dt>เจ้าหน้าที่ผู้รับผิดชอบ</dt><dd>'.mbs_h(($r['technician_name']??'')?:'ยังไม่มอบหมาย').'</dd></div></dl>';
        echo '<dl class="mbs-analytics-work-times"><div><dt>รับงาน</dt><dd>'.mbs_h(!empty($r['received_at'])?mbs_date_display($r['received_at']):'ยังไม่รับงาน').'</dd></div><div><dt>ปิดงาน</dt><dd>'.mbs_h(!empty($r['completed_at'])?mbs_date_display($r['completed_at']):'ยังไม่ปิดงาน').'</dd></div><div><dt>ระยะเวลาทำงาน</dt><dd>'.mbs_h(mbs_duration($r['received_at']??null,$r['completed_at']??null)).'</dd></div></dl>';
        echo '<footer>'.(!empty($r['line_user_id'])?'<button type="button" class="mbs-history-link" data-mbs-reporter="'.mbs_h($r['line_user_id']).'">ดูประวัติผู้แจ้ง</button>':'').'<a class="mbs-analytics-work-open" href="'.mbs_h($url).'" target="_blank" rel="noopener">ดูรายละเอียดงาน ↗</a></footer></article>';
    }
    echo '</div>';
}

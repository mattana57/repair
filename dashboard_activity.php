<?php
// โหลดหลัง auth_guard และเงื่อนไขสิทธิ์ของแต่ละ Dashboard เท่านั้น
if (!defined('MBS_DASHBOARD_ACTIVITY')) {
    http_response_code(403);
    exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง');
}
require_once __DIR__ . '/activity_log.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['mbs_activity_event'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !is_string($_SESSION['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403); echo '{"ok":false}'; exit;
    }
    $event = $_POST['mbs_activity_event'];
    $events = ['VIEW_DASHBOARD', 'UI_CLICK', 'UI_FILTER', 'UI_SEARCH', 'UI_SUBMIT'];
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if (!is_string($event) || !in_array($event, $events, true)
        || !in_array($role, ['admin', 'executive', 'technician'], true)) {
        http_response_code(400); echo '{"ok":false}'; exit;
    }
    // รับเฉพาะชื่อส่วนควบคุม ไม่รับค่าฟอร์ม/ชื่อบุคคล/URL/รหัสผ่าน/OTP
    $control = $_POST['control'] ?? '';
    if (!is_string($control) || !preg_match('/\A[A-Za-z0-9_.:-]{0,100}\z/D', $control)) {
        http_response_code(400); echo '{"ok":false}'; exit;
    }
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $pages = ['dashboard.php' => 'Dashboard แอดมิน', 'technician_home.php' => 'Dashboard เจ้าหน้าที่', 'executive_dashboard.php' => 'Dashboard ผู้บริหาร'];
    if (!isset($pages[$page])) { http_response_code(400); echo '{"ok":false}'; exit; }
    $descriptions = [
        'show' => 'เลือกเมนู', 'viewHistory' => 'ดูประวัติ', 'toggleModal' => 'เปิด/ปิดหน้าต่าง',
        'toggleChartDropdown' => 'เปิดตัวกรอง', 'selectChartDropdown' => 'เลือกตัวกรอง',
        'selectLoginLogRole' => 'เลือกบทบาท', 'confirmDelete' => 'ขอย้ายข้อมูลเข้าถังขยะ',
        'confirmDeleteReporter' => 'ขอย้ายประวัติผู้แจ้งเข้าถังขยะ', 'submitTrashAction' => 'ส่งคำขอจัดการถังขยะ',
        'filterRepairsTable' => 'กรองรายการใบงาน', 'searchHistoryModalTable' => 'กรองประวัติใบงาน',
        'toggleMaximizeHistoryModal' => 'ขยาย/ย่อประวัติ', 'toggleMaximizeLoginLogs' => 'ขยาย/ย่อประวัติเข้าสู่ระบบ',
        'toggleMaximizeRepairs' => 'ขยาย/ย่อรายการใบงาน', 'confirmUnlink' => 'ขอยกเลิกผูกบัญชีเจ้าหน้าที่',
        'confirm' => 'กดยืนยัน', 'cancel' => 'กดยกเลิก',
    ];
    $control_parts = explode(':', $control);
    $control_label = $descriptions[end($control_parts)] ?? $control;
    $summary = (mbsActivityLabels()[$event] ?? $event) . ' · ' . $pages[$page];
    if ($control !== '') $summary .= ' · ' . $control_label;
    $ok = mbsActivityLog($conn, $event, 'dashboard', null, $summary);
    if (!$ok) http_response_code(503);
    echo $ok ? '{"ok":true}' : '{"ok":false}';
    exit;
}

function mbsDashboardActivityScript() {
    $activity_token_json = json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script>
(() => {
    const token = <?= $activity_token_json ?>;
    const endpoint = window.location.pathname;
    const timers = new WeakMap();
    const safe = value => /^[A-Za-z0-9_.:-]{1,100}$/.test(value || '') ? value : '';
    const control = el => {
        if (el.classList.contains('swal2-confirm')) return 'confirm';
        if (el.classList.contains('swal2-cancel')) return 'cancel';
        const named = safe(el.id) || safe(el.name);
        if (named) return named;
        // เก็บชื่อฟังก์ชันที่กด โดยไม่เก็บ arguments ซึ่งอาจเป็นชื่อ/ข้อมูลส่วนตัว
        const handler = el.getAttribute('onclick') || el.getAttribute('onchange') || '';
        const fn = handler.match(/\b([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/);
        if (fn && safe(fn[1])) {
            const container = el.parentElement && el.parentElement.closest('[id]');
            const context = container ? safe(container.id) : '';
            return safe(context ? context + ':' + fn[1] : fn[1]) || fn[1];
        }
        if (el.tagName === 'A') {
            const href = el.getAttribute('href') || '';
            try {
                const url = new URL(href, location.href);
                if (url.origin === location.origin) return safe(url.pathname.split('/').pop());
            } catch (_) {}
        }
        return safe(el.tagName.toLowerCase()) || 'control';
    };
    const send = (event, element) => {
        const data = new URLSearchParams({mbs_activity_event:event, csrf_token:token, control:typeof element === 'string' ? safe(element) : control(element)});
        // ไม่ขัดขวางรายการจริง; keepalive รองรับการเปลี่ยนหน้า/ส่งฟอร์ม
        fetch(endpoint, {method:'POST', body:data, credentials:'same-origin', keepalive:true}).then(r => {
            if (!r.ok) console.warn('บันทึกประวัติกิจกรรมไม่สำเร็จ');
        }).catch(() => console.warn('บันทึกประวัติกิจกรรมไม่สำเร็จ'));
    };
    document.addEventListener('click', event => {
        const el = event.target.closest('button,a,canvas,[onclick],[role="button"]');
        if (!el) return;
        send('UI_CLICK', el);
    }, true);
    document.addEventListener('change', event => {
        const el = event.target;
        if (!el.matches('input,select,textarea') || el.type === 'password' || el.type === 'hidden') return;
        send('UI_FILTER', el);
    }, true);
    document.addEventListener('input', event => {
        const el = event.target;
        if (!el.matches('input') || !/search/i.test(el.id + ' ' + el.name + ' ' + el.type)) return;
        if (el.type === 'password' || el.type === 'hidden') return;
        clearTimeout(timers.get(el));
        timers.set(el, setTimeout(() => send('UI_SEARCH', el), 250));
    }, true);
    document.addEventListener('submit', event => send('UI_SUBMIT', event.target), true);
    // form.submit() ไม่ส่ง DOM submit event: เก็บเฉพาะเหตุการณ์ ไม่อ่านเนื้อหาแบบฟอร์ม
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { send('UI_SUBMIT', this); return nativeSubmit.call(this); };
    document.addEventListener('DOMContentLoaded', () => {
        const tab = new URL(location.href).searchParams.get('tab');
        send('VIEW_DASHBOARD', safe(tab) || 'dash');
    });
    // เมนู/Modal ของระบบใช้ pushState/replaceState โดยไม่โหลดหน้าใหม่
    for (const method of ['pushState', 'replaceState']) {
        const original = history[method];
        history[method] = function (...args) {
            const before = location.href;
            const result = original.apply(this, args);
            if (location.href !== before) send('VIEW_DASHBOARD', safe(new URL(location.href).searchParams.get('tab')) || 'dash');
            return result;
        };
    }
    window.addEventListener('popstate', () => send('VIEW_DASHBOARD', safe(new URL(location.href).searchParams.get('tab')) || 'dash'));
})();
</script>
<?php
}

<?php
if (!defined('MBS_ADMIN_TRASH')) { http_response_code(403); exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง'); }
$atEscape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$atError = false;
$atData = ['rows'=>[], 'page'=>1, 'pages'=>1, 'total'=>0, 'offset'=>0, 'q'=>'', 'kind'=>'all'];
if ($active_tab === 'trash') {
    try {
        $atData = at_page($conn);
    } catch (Throwable $trashError) {
        $atError = true;
        error_log('Admin Trash load failed: ' . $trashError->getMessage());
    }
}
$atLabels = ['asset' => 'ครุภัณฑ์', 'user' => 'บัญชีผู้ใช้', 'tech' => 'เจ้าหน้าที่'];
$atNotice = $_SESSION['admin_trash_notice'] ?? null;
unset($_SESSION['admin_trash_notice']);
?>
<?php if ($active_tab === 'users' && is_array($atNotice)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: <?= json_encode(($atNotice['type'] ?? '') === 'success' ? 'success' : 'error') ?>,
        title: <?= json_encode(($atNotice['type'] ?? '') === 'success' ? 'ดำเนินการสำเร็จ' : 'ดำเนินการไม่สำเร็จ', JSON_UNESCAPED_UNICODE) ?>,
        text: <?= json_encode((string)($atNotice['message'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#4f46e5'
    });
});
</script>
<?php endif; ?>
<div id="trash" class="section <?= $active_tab === 'trash' ? '' : 'hidden' ?> space-y-6 no-print">
    <div class="modern-card bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 space-y-4">
            <h2 class="text-xl font-extrabold text-slate-800"><i class="fas fa-trash-alt text-indigo-500 mr-2" aria-hidden="true"></i>ถังขยะ</h2>
            <p class="text-sm text-slate-500">รายการที่ย้ายเข้าถังขยะยังเก็บอยู่ในฐานข้อมูล สามารถกู้คืนข้อมูลหรือลบถาวรได้</p>
            <p class="text-xs text-slate-500">การกู้คืนบัญชีหรือเจ้าหน้าที่จะไม่เปิดบัญชีเว็บอัตโนมัติ กรุณาตรวจสอบสถานะบัญชีก่อนเปิดใช้งาน</p>
            <?php if (is_array($atNotice)): ?>
            <div role="status" class="p-4 rounded-xl text-sm font-semibold <?= ($atNotice['type'] ?? '') === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>"><?= $atEscape($atNotice['message'] ?? '') ?></div>
            <?php endif; ?>
            <?php if ($atError): ?>
            <div role="alert" class="p-4 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold">โหลดถังขยะไม่สำเร็จ กรุณาตรวจสอบบันทึกข้อผิดพลาดของ PHP แล้วลองใหม่ ข้อความนี้ไม่ได้หมายความว่าข้อมูลถูกลบ</div>
            <?php endif; ?>
            <form id="trashFilterForm" action="dashboard.php" method="get" class="flex flex-wrap gap-3 items-center">
                <input type="hidden" name="tab" value="trash">
                <div class="relative mr-auto flex-1 min-w-[180px] xl:flex-none xl:w-[380px] 2xl:w-[440px]">
                    <label class="sr-only" for="trashSearchInput">ค้นหาข้อมูลในถังขยะ</label>
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <input type="text" id="trashSearchInput" name="trash_q" value="<?= $atEscape($atData['q']) ?>" maxlength="200" placeholder="ค้นหาข้อมูลในถังขยะ..." oninput="scheduleTrashSearch(event)" class="w-full bg-white border border-slate-200 text-sm rounded-xl pl-10 pr-[105px] py-2 focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium h-[42px]">
                    <button type="button" id="clearTrashSearchBtn" onclick="clearTrashSearch()" class="absolute right-0 top-0 h-full px-4 text-sm font-medium text-rose-500 bg-rose-50 border-l border-slate-200 <?= $atData['q'] === '' ? 'hidden' : 'flex' ?> items-center justify-center transition-colors rounded-r-xl"><i class="fas fa-times mr-1.5 text-sm" aria-hidden="true"></i>ล้างค่า</button>
                </div>
                <label><span class="sr-only">ประเภทข้อมูล</span><select name="trash_type" onchange="submitTrashFilters()" class="border border-slate-200 bg-white rounded-xl px-4 py-2.5 text-sm"><option value="all">ทุกประเภท</option><?php foreach ($atLabels as $key => $label): ?><option value="<?= $key ?>" <?= $atData['kind'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            </form>
            <p id="trashSearchError" class="hidden text-sm text-rose-600" role="alert"></p>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left min-w-[760px] text-sm">
                <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs font-bold"><tr><th class="px-6 py-4">ประเภทข้อมูล</th><th class="px-6 py-4">รายการ</th><th class="px-6 py-4">รายละเอียด</th><th class="px-6 py-4">วันที่ย้ายเข้าถังขยะ</th><th class="px-6 py-4 text-center w-[220px]">จัดการ</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($atData['rows'] as $atItem):
                    $atDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $atItem['deleted_at'], new DateTimeZone('Asia/Bangkok'));
                ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-6 py-4 font-semibold"><?= $atLabels[$atItem['category']] ?></td>
                        <td class="px-6 py-4"><div class="font-bold text-slate-800 break-words"><?= $atEscape($atItem['label'] ?: '-') ?></div><div class="text-xs text-slate-400 mt-1">รหัส <?= (int)$atItem['id'] ?></div></td>
                        <td class="px-6 py-4 text-slate-500 break-words"><?= $atEscape($atItem['detail'] ?: '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php if ($atDate): ?><?= $atDate->format('d/m/') . ((int)$atDate->format('Y') + 543) ?><div class="text-xs text-blue-600 font-bold mt-1"><?= $atDate->format('H:i') ?></div><?php else: ?>-<?php endif; ?></td>
                        <td class="px-6 py-4 w-[220px]"><div class="flex justify-center gap-2">
                            <?php foreach (['restore' => 'กู้คืนข้อมูล', 'purge' => 'ลบถาวร'] as $atAction => $atLabel): ?>
                            <form method="post" action="dashboard.php?tab=trash" onsubmit="return confirmTrashForm(event, this)">
                                <input type="hidden" name="csrf_token" value="<?= $atEscape($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="trash_action" value="<?= $atAction ?>">
                                <input type="hidden" name="trash_kind" value="<?= $atItem['kind'] ?>">
                                <input type="hidden" name="trash_id" value="<?= (int)$atItem['id'] ?>">
                                <input type="hidden" name="trash_confirm" value="0">
                                <button type="submit" class="px-3 py-2 rounded-xl text-xs font-bold border <?= $atAction === 'restore' ? 'bg-indigo-50 border-indigo-100 text-indigo-600' : 'bg-rose-50 border-rose-100 text-rose-600' ?>"><?= $atLabel ?></button>
                            </form>
                            <?php endforeach; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$atError && !$atData['rows']): ?><tr><td colspan="5" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในถังขยะ</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$atError): ?>
        <div class="tp-footer">
            <p class="tp-summary">แสดงรายการ <strong><?= $atData['total'] ? number_format($atData['offset'] + 1) : '0' ?>–<?= number_format(min($atData['offset'] + 50, $atData['total'])) ?></strong> จากทั้งหมด <strong><?= number_format($atData['total']) ?></strong> รายการ · หน้า <?= number_format($atData['page']) ?> / <?= number_format($atData['pages']) ?></p>
            <nav class="tp-nav" aria-label="แบ่งหน้าถังขยะ">
            <?php
            $atLink = static function ($page, $label) use ($atData, $atEscape) {
                if ($page < 1 || $page > $atData['pages']) return '<span class="tp-disabled" aria-disabled="true">' . $label . '</span>';
                if ($page === $atData['page']) return '<span aria-current="page" class="tp-link tp-current">' . $label . '</span>';
                $url = 'dashboard.php?' . http_build_query(['tab'=>'trash','trash_q'=>$atData['q'],'trash_type'=>$atData['kind'],'trash_page'=>$page]);
                return '<a class="tp-link" href="' . $atEscape($url) . '">' . $label . '</a>';
            };
            echo $atLink($atData['page'] - 1, '‹ ก่อนหน้า');
            $atNumbers = array_unique(array_merge([1], range(max(1,$atData['page']-1), min($atData['pages'],$atData['page']+1)), [$atData['pages']])); sort($atNumbers); $atPrevious = 0;
            foreach ($atNumbers as $atNumber) { if ($atNumber > $atPrevious + 1) echo '<span class="tp-dots">…</span>'; echo $atLink($atNumber, (string)$atNumber); $atPrevious = $atNumber; }
            echo $atLink($atData['page'] + 1, 'ถัดไป ›');
            ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
let trashSearchTimer = null;
let trashSearchController = null;
let trashSearchVersion = 0;
let trashSearchComposing = false;
const trashSearchForm = document.getElementById('trashFilterForm');
function cancelTrashSearch() {
    clearTimeout(trashSearchTimer);
    trashSearchVersion++;
    if (trashSearchController) trashSearchController.abort();
    document.querySelector('#trash tbody')?.removeAttribute('aria-busy');
}
function scheduleTrashSearch(event) {
    cancelTrashSearch();
    const input = document.getElementById('trashSearchInput');
    const button = document.getElementById('clearTrashSearchBtn');
    button.classList.toggle('hidden', input.value === '');
    button.classList.toggle('flex', input.value !== '');
    if (trashSearchComposing || (event && event.isComposing)) return;
    trashSearchTimer = setTimeout(submitTrashFilters, 350);
}
function clearTrashSearch() {
    document.getElementById('trashSearchInput').value = '';
    document.getElementById('clearTrashSearchBtn').classList.add('hidden');
    document.getElementById('clearTrashSearchBtn').classList.remove('flex');
    submitTrashFilters();
}
async function submitTrashFilters() {
    cancelTrashSearch();
    if (trashSearchComposing) return;
    const version = trashSearchVersion;
    const controller = new AbortController();
    trashSearchController = controller;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'trash');
    url.searchParams.set('trash_q', document.getElementById('trashSearchInput').value);
    url.searchParams.set('trash_type', trashSearchForm.elements.trash_type.value);
    url.searchParams.delete('trash_page');
    const body = document.querySelector('#trash tbody');
    const footer = document.querySelector('#trash .tp-footer');
    const errorText = document.getElementById('trashSearchError');
    errorText.classList.add('hidden');
    body.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(url.href, {signal:controller.signal, credentials:'same-origin', cache:'no-store'});
        if (version !== trashSearchVersion) return;
        if (response.redirected) {window.location.assign(response.url); return;}
        if (!response.ok) throw new Error('request');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (version !== trashSearchVersion || document.getElementById('trash').classList.contains('hidden')) return;
        const nextBody = doc.querySelector('#trash tbody');
        const nextFooter = doc.querySelector('#trash .tp-footer');
        if (!nextBody || !nextFooter || !footer) throw new Error('response');
        body.replaceChildren(...Array.from(nextBody.childNodes, node => document.importNode(node, true)));
        footer.replaceChildren(...Array.from(nextFooter.childNodes, node => document.importNode(node, true)));
        // Keep the original input, selection and focus. PHP still searches and paginates.
        window.history.replaceState(window.history.state, '', url.href);
    } catch (error) {
        if (error.name !== 'AbortError' && version === trashSearchVersion) {
            errorText.textContent = 'ค้นหาไม่สำเร็จ กรุณากด Enter หรือเลือกประเภทเพื่อลองใหม่';
            errorText.classList.remove('hidden');
        }
    } finally {
        if (version === trashSearchVersion) body.removeAttribute('aria-busy');
    }
}
trashSearchForm.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!trashSearchComposing) submitTrashFilters();
});
document.getElementById('trashSearchInput').addEventListener('compositionstart', function () {
    trashSearchComposing = true;
    cancelTrashSearch();
});
document.getElementById('trashSearchInput').addEventListener('compositionend', function () {
    trashSearchComposing = false;
    scheduleTrashSearch();
});
function submitTrashAction(action, kind, id, confirmed = false) {
    const form = document.createElement('form');
    form.method = 'post'; form.action = 'dashboard.php?tab=trash';
    if (action === 'move' && kind === 'reporter') {
        const current = new URL(window.location.href);
        const query = new URLSearchParams({tab:'users'});
        for (const key of ['pg_reporters_q', 'pg_reporters_page']) {
            if (current.searchParams.has(key)) query.set(key, current.searchParams.get(key));
        }
        form.action = 'dashboard.php?' + query.toString();
    }
    const fields = {trash_action:action, trash_kind:kind, trash_id:String(id), trash_confirm:confirmed?'1':'0', csrf_token:<?= json_encode($_SESSION['csrf_token']) ?>};
    for (const [name,value] of Object.entries(fields)) { const input=document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; form.appendChild(input); }
    document.body.appendChild(form); form.submit();
}
function confirmTrashForm(event, form) {
    event.preventDefault();
    if (form.dataset.busy === '1') return false;
    const purge = form.elements.trash_action.value === 'purge';
    form.dataset.busy = '1';
    Swal.fire({title:purge?'ยืนยันการลบถาวร?':'กู้คืนข้อมูลนี้?', text:purge?'เมื่อลบถาวรแล้วจะไม่สามารถกู้คืนข้อมูลได้ ต้องการดำเนินการต่อหรือไม่?':'ข้อมูลจะกลับไปอยู่ในหน้ารายการเดิม บัญชีเว็บจะไม่เปิดใช้งานอัตโนมัติ', icon:purge?'warning':'question', showCancelButton:true, confirmButtonText:purge?'ลบถาวร':'กู้คืนข้อมูล', cancelButtonText:'ยกเลิก', confirmButtonColor:purge?'#e11d48':'#4f46e5'}).then(result=>{
        if (result.isConfirmed) { form.elements.trash_confirm.value=purge?'1':'0'; form.submit(); }
        else delete form.dataset.busy;
    });
    return false;
}
</script>

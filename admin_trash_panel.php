<?php
if (!defined('MBS_ADMIN_TRASH')) { http_response_code(403); exit('ไม่อนุญาตให้เปิดไฟล์นี้โดยตรง'); }
$atEscape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$atError = false;
$atData = array_merge(['rows'=>[], 'page'=>1, 'pages'=>1, 'total'=>0, 'offset'=>0, 'q'=>'', 'kind'=>'all', 'years'=>[(int)(new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y')]], at_period_filters());
if ($active_tab === 'trash') {
    try {
        $atData = at_page($conn);
    } catch (Throwable $trashError) {
        $atError = true;
        error_log('Admin Trash load failed: ' . $trashError->getMessage());
    }
}
$atLabels = ['repair' => 'ประวัติผู้แจ้งซ่อม', 'asset' => 'ครุภัณฑ์', 'user' => 'บัญชีผู้ใช้', 'tech' => 'เจ้าหน้าที่', 'admin' => 'บัญชีแอดมิน', 'executive' => 'บัญชีผู้บริหาร', 'loginlog'=>'ประวัติการเข้าสู่ระบบ', 'activitylog'=>'ประวัติกิจกรรม'];
$atNotice = $_SESSION['admin_trash_notice'] ?? null;
unset($_SESSION['admin_trash_notice']);
?>
<?php if (in_array($active_tab, ['users', 'team_cards', 'assets', 'login_logs'], true) && is_array($atNotice)): ?>
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
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-extrabold text-slate-800"><i class="fas fa-trash-alt text-indigo-500 mr-2" aria-hidden="true"></i>ถังขยะ</h2>
                <div class="trash-edit-actions">
                    <span id="trashSelectedCount" class="text-xs font-semibold text-slate-500" role="status" aria-live="polite">ยังไม่ได้เลือกรายการ</span>
                    <div class="trash-edit-dropdown">
                <button type="button" id="trashEditButton" onclick="toggleTrashEdit()" aria-expanded="false" aria-controls="trashBulkToolbar" class="text-sm font-bold text-blue-600 hover:text-blue-700 px-3 py-2 rounded-xl hover:bg-blue-50 transition-colors">แก้ไข</button>
            <div id="trashBulkToolbar" class="hidden flex-wrap items-center gap-2 p-2 rounded-2xl bg-slate-50 border border-slate-200" aria-label="จัดการรายการที่เลือก">
                <button type="button" id="trashChooseButton" onclick="selectTrashItems()" class="px-4 py-2 rounded-xl text-sm font-bold bg-white border border-slate-200 text-slate-700 hover:bg-blue-50">เลือก</button>
                <button type="button" id="trashChooseAllButton" onclick="selectAllTrashItems()" class="px-4 py-2 rounded-xl text-sm font-bold bg-white border border-slate-200 text-slate-700 hover:bg-blue-50 disabled:opacity-50">เลือกทั้งหมด</button>
                <button type="button" id="trashRestoreSelectedButton" onclick="restoreSelectedTrashItems()" disabled class="px-4 py-2 rounded-xl text-sm font-bold bg-blue-50 border border-blue-100 text-blue-600 hover:bg-blue-100 disabled:opacity-50">กู้คืน</button>
                <button type="button" onclick="deselectTrashItems()" class="px-4 py-2 rounded-xl text-sm font-bold bg-white border border-slate-200 text-slate-700 hover:bg-blue-50">ยกเลิก</button>
                <button type="button" id="trashDeleteSelectedButton" onclick="deleteSelectedTrashItems()" disabled class="px-4 py-2 rounded-xl text-sm font-bold bg-rose-50 border border-rose-100 text-rose-600 hover:bg-rose-100 disabled:opacity-50 disabled:cursor-not-allowed">ลบ</button>

            </div>
                    </div>
                </div>
            </div>
            <p class="text-sm text-slate-500">รายการในถังขยะยังไม่ถูกลบจริง ข้อมูลยังอยู่ในฐานข้อมูลและสามารถกู้คืนได้ หากกดลบถาวร ข้อมูลจะถูกลบออกจากฐานข้อมูลและหน้าแดชบอร์ด และไม่สามารถกู้คืนได้</p>
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
                <div class="flex flex-wrap items-center gap-2 shrink-0 max-w-full relative z-40">
                <?php
                $atMonths = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
                $atDayOptions = ['all'=>'ทุกวัน'];
                for ($atDayNumber=1; $atDayNumber<=31; $atDayNumber++) $atDayOptions[(string)$atDayNumber] = 'วันที่ ' . $atDayNumber;
                $atMonthOptions = ['all'=>'ทุกเดือน'] + $atMonths;
                $atYearOptions = ['all'=>'ทุกปี'];
                foreach ($atData['years'] as $atYearNumber) $atYearOptions[(string)$atYearNumber] = (string)($atYearNumber + 543);
                foreach (['Day'=>['day', $atDayOptions, 'วัน'], 'Month'=>['month', $atMonthOptions, 'เดือน'], 'Year'=>['year', $atYearOptions, 'ปี']] as $atPart=>$atFilter):
                    $atKey=$atFilter[0]; $atOptions=$atFilter[1]; $atPrefix='trash-' . $atPart; $atSelected=$atData[$atKey];
                ?>
                    <div class="relative w-[110px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="<?= $atPrefix ?>Container" tabindex="0" role="group" aria-label="<?= $atFilter[2] ?>ที่ย้ายเข้าถังขยะ" onkeydown="handleChartKeydown(event, '<?= $atPrefix ?>', submitTrashFilters)">
                        <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm text-slate-700 rounded-xl px-4 py-2.5 h-[42px] font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleChartDropdown(event, '<?= $atPrefix ?>')">
                            <span id="<?= $atPrefix ?>Text" class="truncate"><?= $atEscape($atOptions[$atSelected]) ?></span><i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]" aria-hidden="true"></i>
                        </div>
                        <div id="<?= $atPrefix ?>List" class="chart-dropdown-list absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                        <?php foreach ($atOptions as $atValue=>$atDisplay):
                            $atOptionClass = (string)$atValue === 'all' || (string)$atValue === $atSelected ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700 hover:bg-slate-100 hover:text-indigo-600';
                            $atClick = 'selectChartDropdown(' . json_encode($atPrefix) . ', ' . json_encode((string)$atValue) . ', ' . json_encode($atDisplay, JSON_UNESCAPED_UNICODE) . ', submitTrashFilters)';
                        ?>
                            <div class="chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all <?= $atOptionClass ?>" data-value="<?= $atEscape($atValue) ?>" data-display="<?= $atEscape($atDisplay) ?>" onclick="<?= $atEscape($atClick) ?>"><?= $atEscape($atDisplay) ?></div>
                        <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="<?= str_replace('-', '', $atPrefix) ?>" name="trash_<?= $atKey ?>" value="<?= $atEscape($atSelected) ?>">
                    </div>
                <?php endforeach; ?>
                <label><span class="sr-only">ประเภทข้อมูล</span><select name="trash_type" onchange="submitTrashFilters()" class="border border-slate-200 bg-white rounded-xl px-4 py-2.5 text-sm"><option value="all">ทุกประเภท</option><?php foreach ($atLabels as $key => $label): ?><option value="<?= $key ?>" <?= $atData['kind'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                </div>
            </form>
            <p id="trashSearchError" class="hidden text-sm text-rose-600" role="alert"></p>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left min-w-[760px] text-sm">
                <thead class="bg-[#fef9c3] border-b border-[#fef08a] text-[#854d0e] text-xs font-bold"><tr><th class="trash-select-column hidden px-4 py-4 w-12 text-center"><input type="checkbox" id="trashSelectPage" onchange="selectTrashPage(this.checked)" aria-label="เลือกทั้งหมดในหน้านี้" title="เลือกทั้งหมดในหน้านี้" class="w-4 h-4 accent-blue-600 cursor-pointer"></th><th class="px-6 py-4">ประเภทข้อมูล</th><th class="px-6 py-4">รายการ</th><th class="px-6 py-4">รายละเอียด</th><th class="px-6 py-4">วันที่ย้ายเข้าถังขยะ</th><th class="px-6 py-4 text-center w-[220px]">จัดการ</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($atData['rows'] as $atItem):
                    $atDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $atItem['deleted_at'], new DateTimeZone('Asia/Bangkok'));
                    if ($atDate && $atDate->format('Y-m-d H:i:s') !== $atItem['deleted_at']) $atDate = false;
                ?>
                    <tr class="hover:bg-slate-50/50">
                        <td class="trash-select-column hidden px-4 py-4 text-center"><input type="checkbox" class="trash-row-select w-4 h-4 accent-blue-600 cursor-pointer" data-kind="<?= $atEscape($atItem['kind']) ?>" data-id="<?= (int)$atItem['id'] ?>" data-deleted-at="<?= $atEscape($atItem['deleted_at']) ?>" data-category="<?= $atEscape($atItem['category']) ?>" aria-label="เลือก <?= $atEscape($atItem['label'] ?: 'รายการรหัส ' . $atItem['id']) ?>" onchange="toggleTrashItem(this)"></td>
                        <td class="px-6 py-4 font-semibold"><?= $atLabels[$atItem['category']] ?></td>
                        <td class="px-6 py-4"><div class="font-bold text-slate-800 break-words"><?= $atEscape($atItem['label'] ?: '-') ?></div></td>
                        <td class="px-6 py-4 text-slate-500 break-words"><?= $atEscape($atItem['category'] === 'loginlog' ? mbsThaiDisplayDateInText($atItem['detail'] ?: '-') : ($atItem['detail'] ?: '-')) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php if ($atDate): ?><?= mbsThaiDisplayDateFull($atItem['deleted_at'], false) ?><div class="text-xs text-blue-600 font-bold mt-1"><?= mbsThaiDisplayTime($atItem['deleted_at']) ?></div><?php else: ?>-<?php endif; ?></td>
                        <td class="px-6 py-4 w-[220px]"><div class="flex justify-center gap-2">
                            <?php foreach (['restore' => 'กู้คืนข้อมูล', 'purge' => 'ลบถาวร'] as $atAction => $atLabel): ?>
                            <form method="post" action="dashboard.php?tab=trash" data-management="<?= in_array($atItem['category'], ['admin', 'executive'], true) ? '1' : '0' ?>" onsubmit="return confirmTrashForm(event, this)">
                                <input type="hidden" name="csrf_token" value="<?= $atEscape($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="trash_action" value="<?= $atAction ?>">
                                <input type="hidden" name="trash_kind" value="<?= $atItem['kind'] ?>">
                                <input type="hidden" name="trash_id" value="<?= (int)$atItem['id'] ?>">
                                <input type="hidden" name="trash_confirm" value="0">
                                <input type="hidden" name="trash_confirm_linked_account" value="0">
                                <button type="submit" class="px-3 py-2 rounded-xl text-xs font-bold border <?= $atAction === 'restore' ? 'bg-indigo-50 border-indigo-100 text-indigo-600' : 'bg-rose-50 border-rose-100 text-rose-600' ?>"><?= $atLabel ?></button>
                            </form>
                            <?php endforeach; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$atError && !$atData['rows']): ?><tr><td colspan="5" data-trash-empty class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในถังขยะ</td></tr><?php endif; ?>
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
                $url = 'dashboard.php?' . http_build_query(['tab'=>'trash','trash_q'=>$atData['q'],'trash_type'=>$atData['kind'],'trash_day'=>$atData['day'],'trash_month'=>$atData['month'],'trash_year'=>$atData['year'],'trash_page'=>$page]);
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
    clearTrashBulkSelection();
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
    clearTrashBulkSelection();
    cancelTrashSearch();
    if (trashSearchComposing) return;
    const version = trashSearchVersion;
    const controller = new AbortController();
    trashSearchController = controller;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'trash');
    url.searchParams.set('trash_q', document.getElementById('trashSearchInput').value);
    url.searchParams.set('trash_type', trashSearchForm.elements.trash_type.value);
    for (const key of ['trash_day', 'trash_month', 'trash_year']) url.searchParams.set(key, trashSearchForm.elements[key].value);
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
        refreshTrashSelectionUI();
        return true;
    } catch (error) {
        if (error.name !== 'AbortError' && version === trashSearchVersion) {
            errorText.textContent = 'ค้นหาไม่สำเร็จ กรุณากด Enter หรือเลือกตัวกรองเพื่อลองใหม่';
            errorText.classList.remove('hidden');
        }
        return false;
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
    clearTrashBulkSelection();
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
    } else if (action === 'move' && kind === 'tech'
        && new URL(window.location.href).searchParams.get('tab') === 'team_cards') {
        form.action = 'dashboard.php?tab=team_cards';
    }
    const fields = {trash_action:action, trash_kind:kind, trash_id:String(id), trash_confirm:confirmed?'1':'0', csrf_token:<?= json_encode($_SESSION['csrf_token']) ?>};
    for (const [name,value] of Object.entries(fields)) { const input=document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; form.appendChild(input); }
    document.body.appendChild(form); form.submit();
}
function confirmTrashForm(event, form) {
    event.preventDefault();
    if (form.dataset.busy === '1') return false;
    const purge = form.elements.trash_action.value === 'purge';
    const purgeTechnician = purge && form.elements.trash_kind.value === 'tech';
    const purgeManagement = purge && form.dataset.management === '1';
    const restoreText = form.elements.trash_kind.value === 'tech'
        ? 'ข้อมูลเจ้าหน้าที่จะกลับไปอยู่ในหน้ารายการเดิม และเปิดใช้งานบัญชีเว็บที่ผูกกับเจ้าหน้าที่โดยอัตโนมัติ โดยไม่กู้คืนบัญชีที่อยู่ในถังขยะแยกไว้'
        : form.elements.trash_kind.value === 'user'
            ? 'ข้อมูลจะกลับไปอยู่ในหน้ารายการเดิม บัญชีเว็บจะไม่เปิดใช้งานอัตโนมัติ'
            : 'ข้อมูลจะกลับไปอยู่ในหน้ารายการเดิม';
    const purgeText = purgeTechnician
        ? 'จะลบข้อมูลเจ้าหน้าที่และบัญชีเว็บที่ผูกกับรหัสเจ้าหน้าที่นี้ทั้งหมดอย่างถาวร กู้คืนไม่ได้ ระบบจะดำเนินการเฉพาะเมื่อไม่มีใบงานอ้างอิง บัญชีถูกระงับแล้ว และไม่มีข้อมูลอื่นอ้างอิง ต้องการลบทั้งสองส่วนหรือไม่?'
        : purgeManagement
            ? 'จะลบบัญชีนี้พร้อมคำขอยืนยันอีเมลและรีเซ็ตรหัสผ่านของบัญชีนี้ออกจากฐานข้อมูลอย่างถาวร และไม่สามารถกู้คืนได้ ต้องการดำเนินการต่อหรือไม่?'
            : 'เมื่อลบถาวรแล้วจะไม่สามารถกู้คืนข้อมูลได้ ต้องการดำเนินการต่อหรือไม่?';
    form.dataset.busy = '1';
    Swal.fire({title:purge?'ยืนยันการลบถาวร?':'กู้คืนข้อมูลนี้?', text:purge?purgeText:restoreText, icon:purge?'warning':'question', showCancelButton:true, confirmButtonText:purge?'ลบถาวร':'กู้คืนข้อมูล', cancelButtonText:'ยกเลิก', confirmButtonColor:purge?'#e11d48':'#4f46e5'}).then(result=>{
        if (result.isConfirmed) {
            form.elements.trash_confirm.value=purge?'1':'0';
            form.elements.trash_confirm_linked_account.value=purgeTechnician?'1':'0';
            form.submit();
        }
        else delete form.dataset.busy;
    });
    return false;
}

let trashEditOpen = false;
let trashSelectionVisible = false;
let trashBulkSelected = new Map();
let trashBulkVersion = 0;
let trashBulkController = null;
let trashBulkBusy = false;
let trashBulkDeleting = false;
function trashRowKey(item) { return item.kind + ':' + String(item.id); }
function trashCheckboxItem(box) {
    return {kind:box.dataset.kind, id:box.dataset.id, deleted_at:box.dataset.deletedAt, category:box.dataset.category};
}
function clearTrashBulkSelection() {
    trashBulkVersion++;
    if (trashBulkController) trashBulkController.abort();
    trashBulkController = null; trashBulkBusy = false;
    trashBulkSelected.clear();
    refreshTrashSelectionUI();
}
function refreshTrashSelectionUI() {
    const toolbar = document.getElementById('trashBulkToolbar');
    toolbar.classList.toggle('hidden', !trashEditOpen);
    toolbar.classList.toggle('flex', trashEditOpen);
    document.getElementById('trashEditButton').setAttribute('aria-expanded', String(trashEditOpen));
    const visible = trashEditOpen && trashSelectionVisible;
    document.querySelectorAll('#trash .trash-select-column').forEach(cell=>cell.classList.toggle('hidden', !visible));
    const boxes = Array.from(document.querySelectorAll('#trash .trash-row-select'));
    boxes.forEach(box=>{ box.checked = trashBulkSelected.has(trashRowKey(trashCheckboxItem(box))); });
    const page = document.getElementById('trashSelectPage');
    const checked = boxes.filter(box=>box.checked).length;
    page.checked = boxes.length > 0 && checked === boxes.length;
    page.indeterminate = checked > 0 && checked < boxes.length;
    page.disabled = !boxes.length || trashBulkBusy || trashBulkDeleting;
    boxes.forEach(box=>{box.disabled = trashBulkBusy || trashBulkDeleting;});
    document.querySelectorAll('#trash [data-trash-empty]').forEach(cell=>{cell.colSpan = visible ? 6 : 5;});
    document.getElementById('trashSelectedCount').textContent = trashBulkBusy ? 'กำลังเลือกรายการ…'
        : trashBulkSelected.size ? 'เลือกแล้ว ' + trashBulkSelected.size.toLocaleString('th-TH') + ' รายการ' : 'ยังไม่ได้เลือกรายการ';
    document.getElementById('trashChooseAllButton').disabled = trashBulkBusy || trashBulkDeleting;
    document.getElementById('trashDeleteSelectedButton').disabled = !trashBulkSelected.size || trashBulkBusy || trashBulkDeleting;
    document.getElementById('trashRestoreSelectedButton').disabled = !trashBulkSelected.size || trashBulkBusy || trashBulkDeleting;
    document.getElementById('trashChooseButton').classList.toggle('text-blue-600', visible);
    document.getElementById('trashChooseButton').classList.toggle('bg-blue-50', visible);
}
function toggleTrashEdit() {
    trashEditOpen = !trashEditOpen;
    trashSelectionVisible = trashEditOpen;
    if (!trashEditOpen) clearTrashBulkSelection();
    refreshTrashSelectionUI();
}
function selectTrashItems() {
    trashEditOpen = true; trashSelectionVisible = true;
    refreshTrashSelectionUI();
}
function deselectTrashItems() {
    trashSelectionVisible = false;
    clearTrashBulkSelection();
}
function toggleTrashItem(box) {
    if (trashBulkBusy || trashBulkDeleting) return;
    const item = trashCheckboxItem(box); const key = trashRowKey(item);
    if (box.checked) trashBulkSelected.set(key, item); else trashBulkSelected.delete(key);
    trashBulkVersion++; refreshTrashSelectionUI();
}
function selectTrashPage(checked) {
    if (trashBulkBusy || trashBulkDeleting) return;
    document.querySelectorAll('#trash .trash-row-select').forEach(box=>{
        const item = trashCheckboxItem(box); const key = trashRowKey(item);
        if (checked) trashBulkSelected.set(key, item); else trashBulkSelected.delete(key);
    });
    trashBulkVersion++; refreshTrashSelectionUI();
}
async function selectAllTrashItems() {
    if (trashBulkBusy || trashBulkDeleting) return;
    selectTrashItems();
    const beforeFilterVersion = trashBulkVersion;
    if (await submitTrashFilters() !== true || trashBulkVersion !== beforeFilterVersion + 1 || !trashEditOpen || !trashSelectionVisible) return;
    const version = trashBulkVersion;
    const controller = new AbortController();
    trashBulkController = controller; trashBulkBusy = true; refreshTrashSelectionUI();
    try {
        const data = new FormData();
        data.set('csrf_token', <?= json_encode($_SESSION['csrf_token']) ?>);
        data.set('trash_action', 'bulk_select');
        const response = await fetch(window.location.href, {method:'POST', body:data, signal:controller.signal, credentials:'same-origin', cache:'no-store'});
        if (response.redirected) {window.location.assign(response.url); return;}
        const result = await response.json();
        if (version !== trashBulkVersion) return;
        if (!response.ok || !Array.isArray(result.items) || result.count !== result.items.length) throw new Error(result.error || 'เลือกรายการไม่สำเร็จ');
        const selected = new Map();
        for (const item of result.items) {
            if (!['asset','user','tech','repair','loginlog','activitylog'].includes(item.kind) || !/^[1-9][0-9]*$/.test(String(item.id)) || typeof item.deleted_at !== 'string') throw new Error('ข้อมูลรายการไม่ถูกต้อง กรุณาลองใหม่');
            item.id = String(item.id); selected.set(trashRowKey(item), item);
        }
        trashBulkSelected = selected;
    } catch (error) {
        if (error.name !== 'AbortError' && version === trashBulkVersion) Swal.fire({icon:'error',title:'เลือกทั้งหมดไม่สำเร็จ',text:error.message,confirmButtonText:'ตกลง'});
    } finally {
        if (version === trashBulkVersion) {trashBulkBusy = false; trashBulkController = null; refreshTrashSelectionUI();}
    }
}
async function deleteSelectedTrashItems() {
    if (!trashBulkSelected.size || trashBulkBusy || trashBulkDeleting) return;
    const items = Array.from(trashBulkSelected.values()); const version = trashBulkVersion;
    let text = 'จะลบรายการที่เลือก ' + items.length.toLocaleString('th-TH') + ' รายการออกจากฐานข้อมูลอย่างถาวร และไม่สามารถกู้คืนได้';
    if (items.some(item=>item.kind === 'tech')) text += ' รวมบัญชีเว็บที่ผูกกับเจ้าหน้าที่ที่เลือก ตามเงื่อนไขการลบเดิม';
    if (items.some(item=>['admin','executive'].includes(item.category))) text += ' รวมคำขอยืนยันอีเมลและรีเซ็ตรหัสผ่านของบัญชีแอดมิน/ผู้บริหารที่เลือก';
    trashBulkDeleting = true; refreshTrashSelectionUI();
    let submitted = false;
    try {
        const result = await Swal.fire({title:'ยืนยันลบรายการที่เลือก?',text,icon:'warning',showCancelButton:true,confirmButtonText:'ลบถาวร',cancelButtonText:'ยกเลิก',confirmButtonColor:'#e11d48'});
        if (!result.isConfirmed || version !== trashBulkVersion) return;
        cancelTrashSearch();
        const form = document.createElement('form'); form.method = 'post'; form.action = window.location.href;
        const fields = {trash_action:'bulk_purge',trash_items:JSON.stringify(items),trash_confirm:'1',trash_confirm_linked_account:items.some(item=>item.kind === 'tech')?'1':'0',csrf_token:<?= json_encode($_SESSION['csrf_token']) ?>};
        for (const [name,value] of Object.entries(fields)) {const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input);}
        document.body.appendChild(form); form.submit(); submitted = true;
    } finally {if (!submitted) {trashBulkDeleting = false; refreshTrashSelectionUI();}}
}
async function restoreSelectedTrashItems() {
    if (!trashBulkSelected.size || trashBulkBusy || trashBulkDeleting) return;
    const items = Array.from(trashBulkSelected.values()); const version = trashBulkVersion;
    let text = 'จะกู้คืนรายการที่เลือก ' + items.length.toLocaleString('th-TH') + ' รายการกลับไปยังหน้ารายการเดิม';
    if (items.some(item=>item.kind === 'tech')) text += ' เจ้าหน้าที่จะเปิดใช้บัญชีเว็บที่ผูกตามกติกาเดิม โดยไม่กู้คืนบัญชีเว็บที่อยู่ใน Trash แยกไว้หากไม่ได้เลือกบัญชีนั้น';
    if (items.some(item=>item.kind === 'user')) text += ' บัญชีเว็บที่ไม่ได้เปิดใช้จากการกู้คืนเจ้าหน้าที่จะยังคงปิดใช้งานตามเดิม';
    trashBulkDeleting = true; refreshTrashSelectionUI();
    let submitted = false;
    try {
        const result = await Swal.fire({title:'กู้คืนรายการที่เลือก?',text,icon:'question',showCancelButton:true,confirmButtonText:'กู้คืนข้อมูล',cancelButtonText:'ยกเลิก',confirmButtonColor:'#2563eb'});
        if (!result.isConfirmed || version !== trashBulkVersion) return;
        cancelTrashSearch();
        const form = document.createElement('form'); form.method = 'post'; form.action = window.location.href;
        const fields = {trash_action:'bulk_restore',trash_items:JSON.stringify(items),trash_confirm:'1',csrf_token:<?= json_encode($_SESSION['csrf_token']) ?>};
        for (const [name,value] of Object.entries(fields)) {const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input);}
        document.body.appendChild(form); form.submit(); submitted = true;
    } finally {if (!submitted) {trashBulkDeleting = false; refreshTrashSelectionUI();}}
}
refreshTrashSelectionUI();
</script>

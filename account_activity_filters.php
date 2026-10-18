<?php
if (!defined('MBS_ACTIVITY_PANEL') || strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403); exit('ไม่อนุญาตให้เข้าถึงหน้านี้');
}
$lh_months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$lh_days = ['all'=>'ทุกวัน']; for($lh_d=1;$lh_d<=31;$lh_d++)$lh_days[(string)$lh_d]='วันที่ '.$lh_d;
$lh_year_options = ['all'=>'ทุกปี'];foreach($lh_years as $lh_y)$lh_year_options[(string)$lh_y]=(string)($lh_y+543);
?>
<div class="modern-card p-6">
    <form id="accountActivityFilters" action="dashboard.php" method="get" class="flex flex-wrap gap-3 items-center">
        <input type="hidden" name="tab" value="login_logs">
        <input type="hidden" name="pg_logs_status" value="<?= tp_text($lh_role) ?>">
        <?php if($lh_user>0): ?><input type="hidden" name="pg_logs_q" value="<?= (int)$lh_user ?>"><?php endif; ?>
        <div class="relative mr-auto flex-1 min-w-[180px] xl:flex-none xl:w-[440px]">
            <label for="accountActivitySearch" class="sr-only">ค้นหาข้อมูลการใช้งานรายบัญชี</label>
            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true"></i>
            <input type="text" data-activity-search id="accountActivitySearch" name="lh_q" value="<?= tp_text($lh_q) ?>" maxlength="200" placeholder="ค้นหาข้อมูลในตาราง..." class="w-full bg-white border border-slate-200 text-sm rounded-xl pl-10 pr-[95px] py-2.5 h-[42px] focus:outline-none focus:ring-2 focus:ring-indigo-100 transition-all font-medium shadow-sm">
            <button id="accountActivityClear" type="button" class="absolute right-0 top-0 h-full px-4 text-sm font-medium text-slate-400 hover:text-rose-500 hover:bg-rose-50 border-l border-slate-200 <?= $lh_q===''?'hidden':'' ?> items-center justify-center transition-colors rounded-r-xl"><i class="fas fa-times mr-1.5 text-sm" aria-hidden="true"></i>ล้างค่า</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0 max-w-full relative z-40">
        <?php foreach(['Day'=>['day',$lh_days,'วัน'],'Month'=>['month',['all'=>'ทุกเดือน']+$lh_months,'เดือน'],'Year'=>['year',$lh_year_options,'ปี']] as $lh_part=>$lh_spec):
            $lh_prefix='accountActivity-'.$lh_part;$lh_key=$lh_spec[0];$lh_options=$lh_spec[1];$lh_selected=$lh_period[$lh_key];
        ?>
            <div class="relative w-[110px] outline-none focus:ring-2 focus:ring-indigo-400 rounded-xl" id="<?= $lh_prefix ?>Container" tabindex="0" role="group" aria-label="<?= $lh_spec[2] ?>ของกิจกรรม" onkeydown="handleChartKeydown(event, '<?= $lh_prefix ?>', submitAccountActivityFilters)">
                <div class="flex items-center justify-between w-full bg-white border border-slate-200 text-sm text-slate-700 rounded-xl px-4 py-2.5 h-[42px] font-bold cursor-pointer transition-colors hover:bg-slate-50 shadow-sm" onclick="toggleChartDropdown(event, '<?= $lh_prefix ?>')">
                    <span id="<?= $lh_prefix ?>Text" class="truncate"><?= tp_text($lh_options[$lh_selected]) ?></span><i class="fas fa-caret-down text-slate-400 ml-1.5 text-[10px]" aria-hidden="true"></i>
                </div>
                <div id="<?= $lh_prefix ?>List" class="chart-dropdown-list absolute z-50 w-full right-0 mt-1 bg-white border border-slate-100 rounded-2xl shadow-xl hidden flex-col py-2 max-h-48 overflow-y-auto custom-scrollbar">
                <?php foreach($lh_options as $lh_value=>$lh_display):
                    $lh_option_class=(string)$lh_value==='all'||(string)$lh_value===$lh_selected?'bg-indigo-50 text-indigo-600':'text-slate-700 hover:bg-slate-100 hover:text-indigo-600';
                    $lh_click='selectChartDropdown('.json_encode($lh_prefix).', '.json_encode((string)$lh_value).', '.json_encode($lh_display,JSON_UNESCAPED_UNICODE).', submitAccountActivityFilters)';
                ?>
                    <div class="chart-dropdown-item px-3 py-1.5 mx-2 mb-0.5 rounded-xl text-xs font-bold cursor-pointer transition-all <?= $lh_option_class ?>" data-value="<?= tp_text($lh_value) ?>" data-display="<?= tp_text($lh_display) ?>" onclick="<?= tp_text($lh_click) ?>"><?= tp_text($lh_display) ?></div>
                <?php endforeach; ?>
                </div>
                <input type="hidden" id="<?= str_replace('-','',$lh_prefix) ?>" name="lh_<?= $lh_key ?>" value="<?= tp_text($lh_selected) ?>">
            </div>
        <?php endforeach; ?>
        </div>
    </form>
    <p id="accountActivityError" role="alert" class="hidden text-sm text-rose-600 mt-3"></p>
</div>

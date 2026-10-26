(() => {
    'use strict';
    const mount = config => {
        const controls = config.controls;
        const fields = config.fields;
        if (controls.some(node => !node) || Object.values(fields).some(node => !node) || typeof HTMLDialogElement === 'undefined' || !HTMLDialogElement.prototype.showModal) return null;
        const rangeKey = 'mbs_' + config.key;
        const rangeFields = {};
        const rangeHost = controls[0].closest('form') || controls[0].parentNode;
        for (const part of ['start','end']) {
            let field=rangeHost.querySelector('input[name="'+rangeKey+'_'+part+'"]');
            if(!field){field=document.createElement('input');field.type='hidden';field.name=rangeKey+'_'+part;field.value=new URLSearchParams(location.search).get(field.name)||'';rangeHost.appendChild(field);}
            rangeFields[part]=field;
        }
        const rangeText = () => rangeFields.start.value && rangeFields.end.value ? rangeFields.start.value.split('-').reverse().join('/')+' – '+rangeFields.end.value.split('-').reverse().join('/') : '';
        const saveRange = () => {window.mbsRanges=window.mbsRanges||{};window.mbsRanges[config.key]={start:rangeFields.start,end:rangeFields.end};const u=new URL(location.href);for(const part of ['start','end']){const v=rangeFields[part].value;if(v)u.searchParams.set(rangeKey+'_'+part,v);else u.searchParams.delete(rangeKey+'_'+part);}history.replaceState(history.state,'',u);};
        saveRange();
        const suffix = config.key === 'table' ? '' : '-' + config.key;
        const months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        const weekdays = ['อา.','จ.','อ.','พ.','พฤ.','ศ.','ส.'];
        const todayParts = Object.fromEntries(new Intl.DateTimeFormat('en-US', {timeZone:'Asia/Bangkok', year:'numeric', month:'numeric', day:'numeric'}).formatToParts(new Date()).map(p => [p.type,p.value]));
        const today = {year:Number(todayParts.year), month:Number(todayParts.month), day:Number(todayParts.day)};
        const availableYears = new Set(Array.from(controls.flatMap(node => Array.from(node.querySelectorAll('[data-value]'))), node => Number(node.dataset.value)).filter(n => Number.isInteger(n) && n >= 1000 && n <= 9999));
        for (let year = Math.max(1000, today.year - 100); year <= Math.min(9999, today.year + 20); year++) availableYears.add(year);
        let mode = 'date', viewYear = today.year, viewMonth = today.month, selectedDate = null, period = {month:'all', year:'all'};
        const read = () => Object.fromEntries(Object.entries(fields).map(([key,node]) => [key,(node.value === '0' || node.value === 'all' ? 'all' : key === 'month' ? String(Number(node.value)).padStart(2,'0') : node.value)]));
        const monthValue = month => String(month).padStart(2,'0');
        const validDate = value => /^\d{4}$/.test(value.year) && /^\d{1,2}$/.test(value.month) && /^\d{1,2}$/.test(value.day) && Number(value.day)>=1 && Number(value.month)>=1 && Number(value.month)<=12 && Number(value.day)<=new Date(Number(value.year),Number(value.month),0).getDate();
        const describe = value => {
            const parts = [];
            if (value.day !== 'all') parts.push('วันที่ ' + Number(value.day));
            parts.push(value.month === 'all' ? 'ทุกเดือน' : months[Number(value.month)-1]);
            parts.push(value.year === 'all' ? 'ทุกปี' : String(Number(value.year)+543));
            return parts.join(' · ');
        };
        const trigger = document.createElement('button');
        trigger.type = 'button'; trigger.className = 'rc-trigger'; trigger.id = 'repairsCalendarTrigger' + suffix;
        trigger.setAttribute('aria-haspopup','dialog'); trigger.setAttribute('aria-controls','repairsCalendarDialog' + suffix); trigger.setAttribute('aria-expanded','false');
        trigger.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v5m10-5v5M3 11h18"/></svg><span></span><svg class="rc-caret" viewBox="0 0 14 14" aria-hidden="true"><path d="M2 4.5h10L7 9.5z"/></svg>';
        const dialog = document.createElement('dialog'); dialog.id = 'repairsCalendarDialog' + suffix; dialog.className = 'rc-dialog'; dialog.setAttribute('aria-labelledby','rc-title' + suffix);if(config.chart){trigger.classList.add('rc-chart');dialog.classList.add('rc-chart');}
        dialog.innerHTML = `
            <header class="rc-header"><div><p>ตัวกรองวันที่แจ้งซ่อม</p><h3 id="rc-title">เลือกช่วงเวลาที่ต้องการดู</h3></div><button type="button" data-rc="close" class="rc-close" aria-label="ปิดปฏิทิน">×</button></header>
            <div class="rc-tabs" role="tablist" aria-label="รูปแบบการเลือกช่วงเวลา">
                <button type="button" id="rc-tab-date" role="tab" aria-controls="rc-date" data-rc-mode="date">เลือกวันที่</button>
                <button type="button" id="rc-tab-range" role="tab" aria-controls="rc-range" data-rc-mode="range">เริ่ม–สิ้นสุด</button>
                <button type="button" id="rc-tab-period" role="tab" aria-controls="rc-period" data-rc-mode="period">เลือกเดือน/ปี</button>
            </div>
            <section id="rc-date" class="rc-pane" role="tabpanel" aria-labelledby="rc-tab-date">
                <div class="rc-nav"><button type="button" data-rc="prev" aria-label="เดือนก่อนหน้า"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button><label class="rc-sr" for="rc-view-month">เดือนในปฏิทิน</label><select id="rc-view-month"></select><label class="rc-sr" for="rc-view-year">ปีในปฏิทิน พ.ศ.</label><select id="rc-view-year"></select><button type="button" data-rc="next" aria-label="เดือนถัดไป"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg></button></div>
                <div class="rc-weekdays" aria-hidden="true">${weekdays.map(day=>'<span>'+day+'</span>').join('')}</div>
                <div class="rc-days" role="group" aria-label="วันในปฏิทิน"></div>
                <button type="button" data-rc="today" class="rc-today">เลือกวันนี้</button>
            </section>
            <section id="rc-period" class="rc-pane" role="tabpanel" aria-labelledby="rc-tab-period" hidden>
                <label class="rc-year-label" for="rc-period-year">เลือกปี <span>พ.ศ.</span></label><select id="rc-period-year"></select>
                <p class="rc-month-label">เลือกเดือน</p><div class="rc-months">${months.map((month,index)=>'<button type="button" data-rc-month="'+monthValue(index+1)+'">'+month+'</button>').join('')}</div>
                <p class="rc-help">โหมดนี้แสดงทุกวันในเดือนหรือปีที่เลือก</p>
            </section>
            <section id="rc-range" class="rc-pane" role="tabpanel" aria-labelledby="rc-tab-range" hidden>
                <label>วันที่เริ่มต้น (Start)<input type="date" data-rc-start required hidden><button type="button" class="rc-range-date-button" data-rc-range-open="start" aria-expanded="false"><span>เลือกวันที่เริ่มต้น</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v5m10-5v5M3 11h18"/></svg></button></label>
                <label>วันที่สิ้นสุด (End)<input type="date" data-rc-end required hidden><button type="button" class="rc-range-date-button" data-rc-range-open="end" aria-expanded="false"><span>เลือกวันที่สิ้นสุด</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v5m10-5v5M3 11h18"/></svg></button></label>
                <div class="rc-range-picker" popover="manual" hidden aria-label="ปฏิทินเลือกวันที่">
                    <div class="rc-range-popup-header"><div class="rc-range-popup-heading"><select data-rc-range-month aria-label="เลือกเดือน" title="เลือกเดือน"></select><select data-rc-range-year aria-label="เลือกปี พ.ศ." title="เลือกปี พ.ศ."></select></div><div class="rc-range-popup-arrows"><button type="button" data-rc-range-nav="-1" aria-label="เดือนก่อนหน้า"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5-7 7 7 7"/></svg></button><button type="button" data-rc-range-nav="1" aria-label="เดือนถัดไป"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 5 7 7-7 7"/></svg></button></div></div>
                    <div class="rc-weekdays" aria-hidden="true">${['อา','จ','อ','พ','พฤ','ศ','ส'].map(day=>'<span>'+day+'</span>').join('')}</div>
                    <div class="rc-days rc-range-days" role="group" aria-label="เลือกวันเริ่มต้นหรือสิ้นสุด"></div>
                    <div class="rc-range-popup-footer"><button type="button" data-rc-range-clear>ล้าง</button><button type="button" data-rc-range-today>วันนี้</button></div>
                </div>
                <p data-rc-range-error role="alert" class="rc-range-error"></p>
            </section>
            <footer class="rc-footer"><p id="rc-selection" aria-live="polite"></p><div><button type="button" data-rc="close" class="rc-cancel">ยกเลิก</button><button type="button" data-rc="apply" class="rc-apply">ใช้ตัวกรอง</button></div></footer>`;
        if (suffix) {
            dialog.querySelectorAll('[id]').forEach(node => node.id += suffix);
            for (const attr of ['for','aria-controls','aria-labelledby']) dialog.querySelectorAll('['+attr+']').forEach(node => node.setAttribute(attr,node.getAttribute(attr)+suffix));
        }
        const get = selector => dialog.querySelector(selector.startsWith('#') ? selector + suffix : selector);
        let rangeTarget=null, rangeYear=today.year, rangeMonth=today.month;
        const rangeInput = part => get(part==='start'?'[data-rc-start]':'[data-rc-end]');
        function renderRangePicker() {
            for(const part of ['start','end']) {
                const button=get('[data-rc-range-open="'+part+'"]'), value=rangeInput(part).value;
                button.querySelector('span').textContent=value?value.split('-').reverse().join('/'):part==='start'?'เลือกวันที่เริ่มต้น':'เลือกวันที่สิ้นสุด';
                button.setAttribute('aria-expanded',String(mode==='range'&&rangeTarget===part));
            }
            const panel=get('.rc-range-picker'),visible=mode==='range'&&Boolean(rangeTarget);
            if(!visible){if(typeof panel.hidePopover==='function'&&panel.matches(':popover-open'))panel.hidePopover();panel.hidden=true;return;}
            panel.hidden=false;
            if(dialog.open&&typeof panel.showPopover==='function'&&!panel.matches(':popover-open'))panel.showPopover();
            const monthSelect=get('[data-rc-range-month]');
            monthSelect.replaceChildren(...months.map((text,i)=>{const option=document.createElement('option');option.value=i+1;option.textContent=text;return option;}));monthSelect.value=rangeMonth;
            fillYears(get('[data-rc-range-year]'),String(rangeYear),false);for(const option of get('[data-rc-range-year]').options)option.textContent=Number(option.value)+543;
            const first=new Date(rangeYear,rangeMonth-1,1).getDay(),nodes=[];
            for(let index=0;index<42;index++){
                const date=new Date(rangeYear,rangeMonth-1,index-first+1),day=date.getDate();
                const value=String(date.getFullYear())+'-'+monthValue(date.getMonth()+1)+'-'+String(day).padStart(2,'0');
                const button=document.createElement('button');button.type='button';button.dataset.rcRangeDay=value;button.textContent=day;
                if(date.getMonth()+1!==rangeMonth)button.className='rc-range-adjacent';
                button.setAttribute('aria-label',day+' '+months[date.getMonth()]+' '+date.getFullYear());button.setAttribute('aria-pressed',String(value===rangeInput(rangeTarget).value));
                if(date.getFullYear()===today.year&&date.getMonth()+1===today.month&&day===today.day)button.setAttribute('aria-current','date');nodes.push(button);
            }
            get('.rc-range-days').replaceChildren(...nodes);positionRangePicker();
        }
        get('.rc-header p').textContent = config.chart ? 'ตัวกรองช่วงเวลาของกราฟ' : 'ตัวกรองช่วงเวลา';

        get('#rc-view-month').replaceChildren(...months.map((month,index)=>{const option=document.createElement('option');option.value=index+1;option.textContent=month;return option;}));
        function fillYears(select, value, all) {
            const years = new Set(availableYears); if (value !== 'all') years.add(Number(value)); years.add(viewYear);
            const options = Array.from(years).sort((a,b)=>b-a).map(year=>{const option=document.createElement('option');option.value=year;option.textContent=year+543;return option;});
            if (all) {const option=document.createElement('option');option.value='all';option.textContent='ทุกปี';options.unshift(option);}
            select.replaceChildren(...options);select.value=value;
        }
        function render() {
            renderRangePicker();
            for (const tab of dialog.querySelectorAll('[data-rc-mode]')) {const active=tab.dataset.rcMode===mode;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;}
            get('#rc-date').hidden=mode!=='date';get('#rc-period').hidden=mode!=='period';get('#rc-range').hidden=mode!=='range';
            if (mode === 'date') {
                get('#rc-view-month').value=viewMonth;fillYears(get('#rc-view-year'),String(viewYear),false);
                const first=new Date(viewYear,viewMonth-1,1).getDay(), count=new Date(viewYear,viewMonth,0).getDate(),nodes=[];
                for(let i=0;i<first;i++){const empty=document.createElement('span');empty.className='rc-empty';empty.setAttribute('aria-hidden','true');nodes.push(empty);}
                for(let day=1;day<=count;day++){
                    const button=document.createElement('button');button.type='button';button.dataset.rcDay=day;button.textContent=day;
                    const chosen=selectedDate&&Number(selectedDate.year)===viewYear&&Number(selectedDate.month)===viewMonth&&Number(selectedDate.day)===day;
                    button.setAttribute('aria-pressed',String(Boolean(chosen)));button.setAttribute('aria-label',day+' '+months[viewMonth-1]+' '+(viewYear+543));
                    if(today.year===viewYear&&today.month===viewMonth&&today.day===day)button.setAttribute('aria-current','date');
                    nodes.push(button);
                }
                get('.rc-days').replaceChildren(...nodes);
            } else if(mode === 'period') {
                fillYears(get('#rc-period-year'),period.year,true);
                if(period.year==='all')period.month='all';
                for(const button of dialog.querySelectorAll('[data-rc-month]')) {button.setAttribute('aria-pressed',String(button.dataset.rcMonth===period.month));button.disabled=period.year==='all';}
                get('.rc-help').textContent=period.year==='all'?'ทุกปีแสดงทุกเดือนและทุกปี เลือกปีเจาะจงหากต้องการเลือกเดือน':'แสดงทั้งปีที่เลือก หรือเลือกเดือนเพื่อดูเฉพาะเดือนนั้น';
            }
            const value=mode==='date'?selectedDate:{day:'all',...period};
            if(mode==='range'){const a=get('[data-rc-start]').value,b=get('[data-rc-end]').value;const valid=Boolean(a&&b&&a<=b&&get('[data-rc-start]').validity.valid&&get('[data-rc-end]').validity.valid);get('[data-rc-range-error]').textContent=a&&b&&a>b?'วันเริ่มต้นต้องไม่เกินวันสิ้นสุด':'';get('#rc-selection').textContent=valid?a.split('-').reverse().join('/')+' – '+b.split('-').reverse().join('/'):'เลือกวันที่เริ่มต้นและสิ้นสุด';get('[data-rc="apply"]').disabled=!valid;return;}
            get('#rc-selection').textContent=value?describe(value):'เลือกวันที่จากปฏิทิน';get('[data-rc="apply"]').disabled=!value;
        }
        function positionRangePicker() {
            const panel=get('.rc-range-picker');if(panel.hidden||!rangeTarget||!dialog.open)return;
            const rect=get('[data-rc-range-open="'+rangeTarget+'"]').getBoundingClientRect();
            const width=panel.getBoundingClientRect().width||272,height=panel.getBoundingClientRect().height||350;
            const top=rect.top>=height+8?rect.top-height-4:rect.bottom+4;
            panel.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-width-8))+'px';
            panel.style.top=Math.max(8,Math.min(top,window.innerHeight-height-8))+'px';
        }
        function position() {
            if(!dialog.open)return;
            const rect=trigger.getBoundingClientRect(),width=dialog.getBoundingClientRect().width,height=dialog.getBoundingClientRect().height;
            dialog.style.left=Math.max(12,Math.min(rect.right-width,window.innerWidth-width-12))+'px';
            dialog.style.top=Math.max(12,Math.min(rect.bottom+8,window.innerHeight-height-12))+'px';positionRangePicker();
        }
        function sync() {const label=rangeText()||describe(read());trigger.querySelector('span').textContent=label;trigger.setAttribute('aria-label','ตัวกรองวันที่แจ้งซ่อม: '+label);}
        function open() {
            rangeTarget=null;
            const value=read();selectedDate=validDate(value)?{...value}:null;period={month:value.month,year:value.year};
            get('[data-rc-start]').value=rangeFields.start.value;get('[data-rc-end]').value=rangeFields.end.value;
            mode=rangeText()?'range':value.day==='all'?'period':'date';viewYear=value.year==='all'?today.year:Number(value.year);viewMonth=value.month==='all'?today.month:Number(value.month);
            render();dialog.showModal();trigger.setAttribute('aria-expanded','true');position();
        }
        function moveMonth(delta) {const date=new Date(viewYear,viewMonth-1+delta,1);if(date.getFullYear()<1000||date.getFullYear()>9999)return;viewYear=date.getFullYear();viewMonth=date.getMonth()+1;render();position();}
        dialog.addEventListener('click',event=>{
            if(rangeTarget&&(event.target.closest('[data-rc-range-clear]')||event.target.closest('[data-rc-range-today]'))){const part=rangeTarget;rangeInput(part).value=event.target.closest('[data-rc-range-today]')?String(today.year)+'-'+monthValue(today.month)+'-'+String(today.day).padStart(2,'0'):'';rangeTarget=null;render();position();get('[data-rc-range-open="'+part+'"]').focus();return;}
            const rangeOpen=event.target.closest('[data-rc-range-open]');
            if(rangeOpen){rangeTarget=rangeTarget===rangeOpen.dataset.rcRangeOpen?null:rangeOpen.dataset.rcRangeOpen;if(rangeTarget){const value=rangeInput(rangeTarget).value||rangeInput('start').value;if(value){rangeYear=Number(value.slice(0,4));rangeMonth=Number(value.slice(5,7));}else{rangeYear=today.year;rangeMonth=today.month;}}render();position();return;}
            const rangeNav=event.target.closest('[data-rc-range-nav]');
            if(rangeNav){const date=new Date(rangeYear,rangeMonth-1+Number(rangeNav.dataset.rcRangeNav),1);if(date.getFullYear()>=1000&&date.getFullYear()<=9999){rangeYear=date.getFullYear();rangeMonth=date.getMonth()+1;}render();position();return;}
            const rangeDay=event.target.closest('[data-rc-range-day]');
            if(rangeDay&&rangeTarget){const part=rangeTarget;rangeInput(part).value=rangeDay.dataset.rcRangeDay;rangeTarget=null;render();position();get('[data-rc-range-open="'+part+'"]').focus();return;}
            const tab=event.target.closest('[data-rc-mode]');if(tab){mode=tab.dataset.rcMode;render();position();get('#rc-tab-'+mode).focus();return;}
            const month=event.target.closest('[data-rc-month]');if(month&&!month.disabled){period.month=month.dataset.rcMonth;render();get('[data-rc-month="'+period.month+'"]').focus();return;}
            const day=event.target.closest('[data-rc-day]');if(day){selectedDate={day:day.dataset.rcDay,month:monthValue(viewMonth),year:String(viewYear)};render();get('[data-rc-day="'+selectedDate.day+'"]').focus();return;}
            const action=event.target.closest('[data-rc]')?.dataset.rc;
            if(action==='close')dialog.close();
            if(action==='prev'||action==='next')moveMonth(action==='prev'?-1:1);
            if(action==='today'){viewYear=today.year;viewMonth=today.month;selectedDate={day:String(today.day),month:monthValue(today.month),year:String(today.year)};render();}
            if(action==='apply'){
                const value=mode==='range'?{day:'all',month:'all',year:'all'}:mode==='date'?selectedDate:{day:'all',...period};if(!value)return;
                rangeFields.start.value=mode==='range'?get('[data-rc-start]').value:'';rangeFields.end.value=mode==='range'?get('[data-rc-end]').value:'';saveRange();
                for(const [key,valuePart] of Object.entries(value))fields[key].value=config.zeroAll && valuePart === 'all' ? '0' : valuePart;
                // ใช้เส้นทางกรอง/โหลดตารางและ PHP แบ่งหน้าเดิม ไม่กรองแถวด้วย JavaScript
                sync();dialog.close();config.apply();
            }
            if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}
        });
        dialog.addEventListener('change',event=>{
            if(event.target===get('[data-rc-range-month]'))rangeMonth=Number(event.target.value);
            if(event.target===get('[data-rc-range-year]'))rangeYear=Number(event.target.value);
            if(event.target===get('#rc-view-month'))viewMonth=Number(event.target.value);
            if(event.target===get('#rc-view-year'))viewYear=Number(event.target.value);
            if(event.target===get('#rc-period-year')){period.year=event.target.value;period.month='all';}
            render();position();
        });
        dialog.addEventListener('keydown',event=>{
            const tab=event.target.closest('[data-rc-mode]');
            if(tab&&['ArrowLeft','ArrowRight'].includes(event.key)){event.preventDefault();mode=['date','range','period'][(['date','range','period'].indexOf(mode)+(event.key==='ArrowRight'?1:2))%3];render();position();get('#rc-tab-'+mode).focus();return;}
            const button=event.target.closest('[data-rc-day]');if(!button)return;
            const day=Number(button.dataset.rcDay),date=new Date(viewYear,viewMonth-1,day),offset={ArrowLeft:-1,ArrowRight:1,ArrowUp:-7,ArrowDown:7,Home:-date.getDay(),End:6-date.getDay()}[event.key];
            if(offset===undefined)return;event.preventDefault();date.setDate(day+offset);viewYear=date.getFullYear();viewMonth=date.getMonth()+1;render();get('[data-rc-day="'+date.getDate()+'"]').focus();
        });
        dialog.addEventListener('close',()=>{rangeTarget=null;renderRangePicker();trigger.setAttribute('aria-expanded','false');});
        dialog.addEventListener('scroll',positionRangePicker);
        const outsideRange=event=>{if(rangeTarget&&!get('.rc-range-picker').contains(event.target)&&!event.target.closest('[data-rc-range-open]')){rangeTarget=null;renderRangePicker();}};
        document.addEventListener('pointerdown',outsideRange,true);
        trigger.addEventListener('click',open);window.addEventListener('resize',position);
        document.body.appendChild(dialog);controls[0].before(trigger);controls.forEach(node=>node.classList.add('rc-legacy-hidden'));
        sync();
        return {trigger,dialog,fields,sync,destroy(){document.removeEventListener('pointerdown',outsideRange,true);window.removeEventListener('resize',position);if(dialog.open)dialog.close();dialog.remove();}};
    };
    window.RepairCalendar = {mount};
})();

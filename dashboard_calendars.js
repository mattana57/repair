(() => {
    'use strict';
    // กราฟยังใช้ตัวกรองเดือน/ปีเดิม เพิ่มเฉพาะวันที่เมื่อผู้ใช้เลือกวันเจาะจง
    window.dashboardCalendarMatches = (row, prefix) => {
        const day = document.getElementById(prefix + 'Day')?.value || 'all';
        if (day === 'all' || day === '0') return true;
        const match = /^\d{4}-\d{2}-(\d{2})(?:[ T]|$)/.exec(String(row.created_at || ''));
        return Boolean(match) && Number(match[1]) === Number(day);
    };
    const init = () => {
        if (!window.RepairCalendar) return;
        const mounted = new WeakMap(), instances = new Set();
        const charts = new Set(['equip','status','loc','tech','rating','reporter','rep']);
        const monthNames = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        function add(config) {
            const anchor = config.controls[0]; if (mounted.has(anchor)) return;
            const instance = window.RepairCalendar.mount(config); if (!instance) return;
            mounted.set(anchor, instance); instances.add(instance);
        }
        function discover() {
            for (const instance of instances) if (!instance.trigger.isConnected) {instance.destroy(); instances.delete(instance);}
            for (const monthControl of document.querySelectorAll('[id$="-MonthContainer"]')) {
                const prefix = monthControl.id.slice(0,-'-MonthContainer'.length), yearControl = document.getElementById(prefix+'-YearContainer'), dayControl = document.getElementById(prefix+'-DayContainer');
                if (!yearControl || charts.has(prefix)) continue;
                const controls = [dayControl, monthControl, yearControl].filter(Boolean); if (mounted.has(controls[0])) continue;
                const callbackName = /,\s*([A-Za-z_$][\w$]*)\s*\)\s*$/.exec(monthControl.getAttribute('onkeydown') || '')?.[1];
                if (!callbackName || typeof window[callbackName] !== 'function') continue;
                const fields = {month:monthControl.querySelector('input[type=hidden]'),year:yearControl.querySelector('input[type=hidden]')};
                fields.day = dayControl?.querySelector('input[type=hidden]');
                if (!fields.day && charts.has(prefix)) {fields.day=document.createElement('input');fields.day.type='hidden';fields.day.id=prefix+'Day';fields.day.value='all';monthControl.appendChild(fields.day);}
                if (!fields.day || !fields.month || !fields.year) continue;
                add({key:prefix,controls,fields,chart:charts.has(prefix),apply(){
                    // ทำให้ค่าที่แสดงในตัวกรองเดิมตรงกันด้วย โดยเรียกตัวโหลดข้อมูลเพียงครั้งเดียว
                    if(typeof window.selectChartDropdown==='function')for(const [part,field] of Object.entries(fields)){
                        const cap=part[0].toUpperCase()+part.slice(1),container=document.getElementById(prefix+'-'+cap+'Container');if(!container)continue;
                        const value=field.value;const display=part==='day'?(value==='all'?'ทุกวัน':'วันที่ '+Number(value)):part==='month'?(value==='all'?'ทุกเดือน':monthNames[Number(value)-1]):value==='all'?'ทุกปี':String(Number(value)+543);
                        window.selectChartDropdown(prefix+'-'+cap,value,display,null);
                    }
                    window[callbackName]();sync();
                }});
            }
            // ตารางประวัติเจ้าหน้าที่ใช้ลิงก์ PHP + ฟอร์มเดิม ไม่ใช่ชุด Container
            const group=document.querySelector('#repairsMainCard .th-filter-group'),form=document.querySelector('#repairsMainCard .th-search');
            if(group&&form){const controls=Array.from(group.querySelectorAll(':scope > details')).slice(0,3);const fields={day:form.elements.namedItem('hd'),month:form.elements.namedItem('hm'),year:form.elements.namedItem('hy')};if(controls.length===3&&Object.values(fields).every(Boolean))add({key:'technician-history',controls,fields,apply(){form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}});}
            // หน้ารายงานและเอกสารพิมพ์: 0 หมายถึงทุกช่วงเวลาในระบบรายงานเดิม
            const reportMonth=document.getElementById('monthDropdownContainer'),reportYear=document.getElementById('yearDropdownContainer'),reportForm=document.getElementById('reportFilterForm');
            if(reportMonth&&reportYear&&reportForm&&!mounted.has(reportMonth)){
                let day=reportForm.elements.namedItem('day');if(!day){day=document.createElement('input');day.type='hidden';day.name='day';day.value=new URLSearchParams(location.search).get('day')||'0';reportForm.appendChild(day);}
                add({key:'report',controls:[reportMonth,reportYear],zeroAll:true,fields:{day,month:document.getElementById('monthHiddenInput'),year:document.getElementById('yearHiddenInput')},apply(){reportForm.requestSubmit();}});
            }
        }
        function sync(){for(const instance of instances)if(instance.trigger.isConnected)instance.sync();}
        let scheduled=false;
        const schedule=()=>{if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;discover();sync();});};
        discover();
        // รองรับส่วนกรองที่สร้างใหม่หลังค้นหา/แบ่งหน้า และหน้าต่างประวัติที่เปิดภายหลัง
        new MutationObserver(records=>{if(records.some(record=>Array.from(record.addedNodes).some(node=>node.nodeType===1&&!node.closest?.('.rc-dialog'))))schedule();}).observe(document.body,{childList:true,subtree:true});
        const original=window.selectChartDropdown;
        if(typeof original==='function')window.selectChartDropdown=function(...args){const result=original.apply(this,args);sync();return result;};
        document.addEventListener('click',()=>queueMicrotask(sync));window.addEventListener('popstate',schedule);
    };
    // These scripts run after the filter markup; do not wait behind chart/load callbacks.
    if(document.body)init();else document.addEventListener('DOMContentLoaded',init,{once:true});
})();

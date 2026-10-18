(() => {
    'use strict';
    const settings = document.getElementById('ab-settings');
    if (!settings) return;
    const state = {
        asset: {visible:false, menu:false, selected:new Map(), busy:false, version:0},
        reporter: {visible:false, menu:false, selected:new Map(), busy:false, version:0}
    };
    const root = kind => document.getElementById(kind === 'asset' ? 'assets' : 'users');
    const boxes = kind => Array.from(root(kind).querySelectorAll('[data-ab-row]'));
    const item = box => ({id:box.dataset.id, line_user_id:box.dataset.lineId || ''});
    function refresh(kind) {
        const s=state[kind], r=root(kind);
        r.querySelectorAll('[data-ab-cell]').forEach(cell=>cell.classList.toggle('hidden',!s.visible));
        r.querySelectorAll('[data-ab-row]').forEach(box=>{box.checked=s.selected.has(box.dataset.id);box.disabled=box.dataset.eligible!=='1'||s.busy;});
        r.querySelectorAll('[data-ab-empty]').forEach(cell=>{cell.colSpan=Number(cell.dataset.abEmpty)+(s.visible?1:0);});
        const enabled=boxes(kind).filter(box=>box.dataset.eligible==='1');
        const page=r.querySelector('[data-ab-page]');
        const n=enabled.filter(box=>box.checked).length;
        if(page){page.checked=enabled.length>0&&n===enabled.length;page.indeterminate=n>0&&n<enabled.length;page.disabled=s.busy||!enabled.length;}
        document.getElementById('ab-menu-'+kind).classList.toggle('hidden',!s.menu);
        r.querySelector('[data-ab-edit]').setAttribute('aria-expanded',String(s.menu));
        r.querySelectorAll('[data-ab-action]').forEach(button=>{button.disabled=s.busy||(button.dataset.abAction==='delete'&&!s.selected.size);});
        const count=document.getElementById('ab-count-'+kind);
        count.textContent=s.busy?'กำลังเลือกรายการ…':'เลือกแล้ว '+s.selected.size.toLocaleString('th-TH')+' รายการ';
        count.classList.toggle('hidden',!s.visible&&!s.selected.size&&!s.busy);
    }
    function clear(kind, hide=false){const s=state[kind];s.version++;if(s.controller)s.controller.abort();s.busy=false;s.selected.clear();if(hide)s.visible=false;refresh(kind);}
    async function selectAll(kind) {
        const s=state[kind], version=++s.version;
        if(s.controller)s.controller.abort();
        const controller=new AbortController();s.controller=controller;s.visible=true;s.busy=true;s.menu=false;refresh(kind);
        const body=new URLSearchParams({table_bulk_action:'select_all',table_bulk_kind:kind,csrf_token:settings.dataset.token});
        try {
            const response=await fetch(location.href.split('#')[0],{method:'POST',body,signal:controller.signal,credentials:'same-origin',cache:'no-store'});
            const data=await response.json();
            if(version!==s.version)return;
            if(!response.ok||!Array.isArray(data.items))throw new Error(data.error||'เลือกรายการไม่สำเร็จ');
            s.selected=new Map(data.items.map(row=>[String(row.id),row]));
        } catch(error) {if(error.name!=='AbortError'&&version===s.version)Swal.fire({icon:'error',text:error.message});}
        finally {if(version===s.version){s.busy=false;refresh(kind);}}
    }
    async function remove(kind) {
        const s=state[kind];if(s.busy||!s.selected.size)return;
        const version=s.version, items=Array.from(s.selected.values());s.menu=false;refresh(kind);
        const answer=await Swal.fire({title:'ย้ายรายการที่เลือกเข้าถังขยะ?',text:'เลือกแล้ว '+items.length.toLocaleString('th-TH')+' รายการ ข้อมูลยังอยู่ในฐานข้อมูลและกู้คืนได้'+(kind==='reporter'?' โดยย้ายประวัติใบงานของบัญชี LINE ที่เลือกตามเดิม':''),icon:'warning',showCancelButton:true,confirmButtonText:'ย้ายเข้าถังขยะ',cancelButtonText:'ยกเลิก',confirmButtonColor:'#2563eb'});
        if(!answer.isConfirmed||version!==s.version)return;
        s.busy=true;refresh(kind);
        const url=new URL(location.href);url.searchParams.set('tab',kind==='asset'?'assets':'users');
        const form=document.createElement('form');form.method='POST';form.action=url.pathname+url.search;
        const fields={table_bulk_action:'move',table_bulk_kind:kind,table_bulk_items:JSON.stringify(items),table_bulk_confirm:'1',csrf_token:settings.dataset.token};
        Object.entries(fields).forEach(([name,value])=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input);});
        document.body.appendChild(form);form.submit();
    }
    document.addEventListener('click', event=>{
        const edit=event.target.closest('[data-ab-edit]');
        if(edit){const kind=edit.dataset.abEdit,s=state[kind];s.menu=!s.menu;if(s.menu)s.visible=true;refresh(kind);return;}
        const action=event.target.closest('[data-ab-action]');
        if(action){const kind=action.dataset.abKind,s=state[kind];if(s.busy)return;
            if(action.dataset.abAction==='choose'){s.visible=true;s.menu=false;refresh(kind);}
            if(action.dataset.abAction==='none'){s.menu=false;clear(kind,true);}
            if(action.dataset.abAction==='all')selectAll(kind);
            if(action.dataset.abAction==='delete')remove(kind);
            return;
        }
        for(const kind of Object.keys(state))if(state[kind].menu&&!event.target.closest('#ab-menu-'+kind)){state[kind].menu=false;document.getElementById('ab-menu-'+kind).classList.add('hidden');root(kind).querySelector('[data-ab-edit]').setAttribute('aria-expanded','false');}
    });
    document.addEventListener('keydown',event=>{if(event.key==='Escape')for(const kind of Object.keys(state)){state[kind].menu=false;refresh(kind);}});
    document.addEventListener('change',event=>{
        const box=event.target.closest('[data-ab-row]');
        if(box){const kind=box.dataset.abRow,s=state[kind];if(box.disabled)return;s.version++;if(box.checked)s.selected.set(box.dataset.id,item(box));else s.selected.delete(box.dataset.id);refresh(kind);}
        const page=event.target.closest('[data-ab-page]');
        if(page){const kind=page.dataset.abPage,s=state[kind];if(s.busy)return;s.version++;boxes(kind).filter(box=>box.dataset.eligible==='1').forEach(box=>{if(page.checked)s.selected.set(box.dataset.id,item(box));else s.selected.delete(box.dataset.id);});refresh(kind);}
    });
    document.getElementById('searchHistoryInput').addEventListener('input',()=>clear('reporter'));
    // Preserve selections when PHP pagination or live updates replace table rows.
    for(const kind of Object.keys(state)){
        const tbody=root(kind).querySelector('tbody');
        new MutationObserver(()=>refresh(kind)).observe(tbody,{childList:true});
        refresh(kind);
    }
    // Keep checked items while moving between PHP pages in selection mode.
    async function navigateSelectedTable(kind, href) {
        const s=state[kind];if(s.busy)return;
        const version=++s.version;s.busy=true;refresh(kind);
        try {
            const response=await fetch(href,{credentials:'same-origin',cache:'no-store'});
            if(!response.ok)throw new Error('โหลดหน้าไม่สำเร็จ');
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');
            if(version!==s.version)return;
            const id=kind==='asset'?'assets':'users';
            const nextBody=doc.querySelector('#'+id+' tbody'),nextFooter=doc.querySelector('#'+id+' .tp-footer');
            const currentBody=root(kind).querySelector('tbody'),currentFooter=root(kind).querySelector('.tp-footer');
            if(!nextBody||!nextFooter||!currentFooter)throw new Error('โหลดหน้าไม่สำเร็จ');
            currentBody.replaceChildren(...Array.from(nextBody.childNodes,node=>document.importNode(node,true)));
            currentFooter.replaceWith(document.importNode(nextFooter,true));
            const url=new URL(href,location.href);history.replaceState(history.state,'',url.pathname+url.search+url.hash);
        } catch(error){if(version===s.version)Swal.fire({icon:'error',text:error.message});}
        finally{if(version===s.version){s.busy=false;refresh(kind);}}
    }
    document.addEventListener('click',event=>{
        const link=event.target.closest('#assets .tp-nav a, #users .tp-nav a');
        if(!link||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
        const kind=link.closest('#assets')?'asset':'reporter';
        if(!state[kind].visible&&!state[kind].selected.size)return;
        event.preventDefault();navigateSelectedTable(kind,link.href);
    });
    window.abTablesBusy=()=>Object.values(state).some(s=>s.busy);
    window.abClearReporterSelection=()=>clear('reporter');
    window.abRefreshSelections=()=>{for(const kind of Object.keys(state))refresh(kind);};

    let activityTimer, activityVersion=0, activityController, composing=false;
    async function filterActivity(resetPage=true, targetUrl=null) {
        clearTimeout(activityTimer);
        const form=document.getElementById('accountActivityFilters');if(!form)return;
        const version=++activityVersion;
        if(activityController)activityController.abort();
        const controller=new AbortController();activityController=controller;
        const url=targetUrl?new URL(targetUrl,location.href):new URL(form.action);
        if(!targetUrl){for(const [key,value] of new FormData(form))url.searchParams.set(key,value);}
        if(resetPage){url.searchParams.delete('lh_people_page');url.searchParams.delete('lh_activity_page');}
        const region=document.getElementById('accountActivityResults');
        region.setAttribute('aria-busy','true');
        const error=document.getElementById('accountActivityError');error.classList.add('hidden');
        try {
            const response=await fetch(url.href,{signal:controller.signal,credentials:'same-origin',cache:'no-store'});
            if(!response.ok)throw new Error('request');
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');
            if(version!==activityVersion)return;
            const next=doc.getElementById('accountActivityResults');if(!next)throw new Error('response');
            region.replaceChildren(...Array.from(next.childNodes,node=>document.importNode(node,true)));
            history.replaceState(history.state,'',url.pathname+url.search+url.hash);
        } catch(errorValue){if(errorValue.name!=='AbortError'&&version===activityVersion){error.textContent='ค้นหาไม่สำเร็จ กรุณากด Enter หรือเลือกตัวกรองเพื่อลองใหม่';error.classList.remove('hidden');}}
        finally{if(version===activityVersion)region.removeAttribute('aria-busy');}
    }
    window.submitAccountActivityFilters=()=>filterActivity();
    document.addEventListener('compositionstart',e=>{if(e.target.matches('[data-activity-search]')){composing=true;clearTimeout(activityTimer);}});
    document.addEventListener('compositionend',e=>{if(e.target.matches('[data-activity-search]')){composing=false;e.target.dispatchEvent(new Event('input',{bubbles:true}));}});
    document.addEventListener('input',event=>{
        if(!event.target.matches('[data-activity-search]'))return;
        clearTimeout(activityTimer);activityVersion++;if(activityController)activityController.abort();
        document.getElementById('accountActivityClear').classList.toggle('hidden',event.target.value==='');
        if(!composing&&!event.isComposing)activityTimer=setTimeout(()=>filterActivity(),350);
    });
    document.addEventListener('submit',event=>{if(event.target.id==='accountActivityFilters'){event.preventDefault();if(!composing)filterActivity();}});
    document.addEventListener('click',event=>{
        if(event.target.closest('#accountActivityClear')){document.getElementById('accountActivitySearch').value='';document.getElementById('accountActivityClear').classList.add('hidden');filterActivity();}
        const link=event.target.closest('#accountActivityResults .tp-nav a');
        if(link&&!event.ctrlKey&&!event.metaKey&&!event.shiftKey&&!event.altKey){event.preventDefault();filterActivity(false,link.href);}
    });
})();

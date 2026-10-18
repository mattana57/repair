(() => {
    const settings=document.getElementById('ab-settings');if(!settings)return;
    const ids={loginlog:'login-history-table',activityperson:'account-summary-table',activityevent:'account-event-table'};
    const state=Object.fromEntries(Object.keys(ids).map(kind=>[kind,{active:false,busy:false,version:0,selected:new Map()}]));
    const root=kind=>document.getElementById(ids[kind]);
    const boxes=kind=>Array.from(root(kind)?.querySelectorAll('[data-lb-row]')||[]);
    const rowItem=box=>box.dataset.lbRow==='activityperson'?{id:box.dataset.id,user_id:box.dataset.userId,role:box.dataset.role,upto_id:box.dataset.uptoId}:{id:box.dataset.id};
    function refresh(kind){
        const s=state[kind],r=root(kind);if(!r)return;
        r.querySelectorAll('[data-lb-cell]').forEach(cell=>cell.classList.toggle('hidden',!s.active));
        const rowBoxes=boxes(kind);rowBoxes.forEach(box=>{box.checked=s.selected.has(box.dataset.id);box.disabled=s.busy;});
        const page=r.querySelector('[data-lb-page]'),n=rowBoxes.filter(box=>box.checked).length;
        if(page){page.checked=rowBoxes.length>0&&n===rowBoxes.length;page.indeterminate=n>0&&n<rowBoxes.length;page.disabled=s.busy||!rowBoxes.length;}
        r.querySelectorAll('[data-lb-empty]').forEach(cell=>{cell.colSpan=Number(cell.dataset.lbEmpty)+(s.active?1:0);});
        const menu=document.getElementById('lb-menu-'+kind);menu?.classList.toggle('hidden',!s.active);
        document.querySelector('[data-lb-edit="'+kind+'"]').setAttribute('aria-expanded',String(s.active));
        document.querySelectorAll('[data-lb-kind="'+kind+'"]').forEach(button=>{button.disabled=s.busy||(button.dataset.lbAction==='delete'&&!s.selected.size);});
        const count=document.getElementById('lb-count-'+kind);if(count){count.textContent=s.busy?'กำลังเลือกรายการ…':'เลือกแล้ว '+s.selected.size.toLocaleString('th-TH')+' รายการ';count.classList.toggle('hidden',!s.active);}
    }
    function clear(kind,close=false){const s=state[kind];s.version++;s.controller?.abort();s.busy=false;s.selected.clear();if(close)s.active=false;refresh(kind);}
    function context(kind){const url=new URL(location.href);const form=document.getElementById(kind==='loginlog'?'loginLogsFilterForm':'accountActivityFilters');if(form)for(const [key,value] of new FormData(form))url.searchParams.set(key,value);url.searchParams.delete('_');return url;}
    async function all(kind){const s=state[kind];if(s.busy)return;const version=++s.version,controller=new AbortController();s.controller=controller;s.busy=true;refresh(kind);
        try{const response=await fetch(context(kind).href,{method:'POST',body:new URLSearchParams({log_bulk_action:'select_all',log_bulk_kind:kind,csrf_token:settings.dataset.token}),signal:controller.signal,credentials:'same-origin',cache:'no-store'});const data=await response.json();if(version!==s.version)return;if(!response.ok||!Array.isArray(data.items))throw new Error(data.error||'เลือกประวัติไม่สำเร็จ');s.selected=new Map(data.items.map(item=>[String(item.id),item]));}
        catch(error){if(error.name!=='AbortError'&&version===s.version)Swal.fire({icon:'error',text:error.message});}
        finally{if(version===s.version){s.busy=false;refresh(kind);}}
    }
    async function remove(kind){const s=state[kind];if(s.busy||!s.selected.size)return;const version=s.version,items=Array.from(s.selected.values());
        const answer=await Swal.fire({title:'ย้ายประวัติที่เลือกเข้าถังขยะ?',text:kind==='activityperson'?'จะย้ายประวัติกิจกรรมเดิมที่ตรงตัวกรองของ '+items.length.toLocaleString('th-TH')+' บัญชีที่เลือกเข้า Trash โดยไม่ลบบัญชีผู้ใช้ กิจกรรมใหม่ยังบันทึกตามปกติ':'จะย้ายประวัติที่เลือก '+items.length.toLocaleString('th-TH')+' รายการเข้า Trash และสามารถกู้คืนได้ โดยไม่ลบบัญชีผู้ใช้',icon:'warning',showCancelButton:true,confirmButtonText:'ย้ายเข้าถังขยะ',cancelButtonText:'ยกเลิก',confirmButtonColor:'#2563eb'});
        if(!answer.isConfirmed||version!==s.version)return;s.busy=true;refresh(kind);
        const url=context(kind),form=document.createElement('form');form.method='POST';form.action=url.pathname+url.search;
        Object.entries({log_bulk_action:'move',log_bulk_kind:kind,log_bulk_items:JSON.stringify(items),log_bulk_confirm:'1',csrf_token:settings.dataset.token}).forEach(([name,value])=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input);});document.body.appendChild(form);form.submit();
    }
    document.addEventListener('click',event=>{
        const edit=event.target.closest('[data-lb-edit]');if(edit){const kind=edit.dataset.lbEdit,s=state[kind];if(!s||!root(kind))return;
            if(s.active){clear(kind,true);return;}if(root(kind).dataset.lbReady!=='1'){Swal.fire({icon:'info',text:'กรุณารันไฟล์ 01_log_trash_columns.sql ใน phpMyAdmin ก่อนใช้งานแก้ไขประวัติ'});return;}s.active=true;refresh(kind);return;}
        const action=event.target.closest('[data-lb-action]');if(action){const kind=action.dataset.lbKind,s=state[kind];if(!s||s.busy)return;
            if(action.dataset.lbAction==='none')clear(kind,true);if(action.dataset.lbAction==='choose'){s.active=true;refresh(kind);}if(action.dataset.lbAction==='all')all(kind);if(action.dataset.lbAction==='delete')remove(kind);}
    });
    document.addEventListener('change',event=>{const box=event.target.closest('[data-lb-row]');if(box){const kind=box.dataset.lbRow,s=state[kind];if(s.busy)return;s.version++;if(box.checked)s.selected.set(box.dataset.id,rowItem(box));else s.selected.delete(box.dataset.id);refresh(kind);}
        const page=event.target.closest('[data-lb-page]');if(page){const kind=page.dataset.lbPage,s=state[kind];if(s.busy)return;s.version++;boxes(kind).forEach(box=>{if(page.checked)s.selected.set(box.dataset.id,rowItem(box));else s.selected.delete(box.dataset.id);});refresh(kind);}});
    document.addEventListener('keydown',event=>{if(event.key==='Escape')for(const kind of Object.keys(state))clear(kind,true);});
    // Drop selections immediately when filter values change, including while a search is loading.
    document.addEventListener('input',event=>{if(event.target.matches('#loginLogSearch,[data-activity-search]'))for(const kind of Object.keys(state))clear(kind);});
    for(const name of ['submitAccountActivityFilters','filterLoginLogs'])if(typeof window[name]==='function'){const original=window[name];window[name]=function(...args){for(const kind of Object.keys(state))clear(kind);return original.apply(this,args);};}
    const bodyObservers=new Map();
    function bind(){for(const kind of Object.keys(state)){const body=root(kind)?.querySelector('tbody');if(body&&bodyObservers.get(kind)?.body!==body){bodyObservers.get(kind)?.observer.disconnect();const observer=new MutationObserver(()=>refresh(kind));observer.observe(body,{childList:true});bodyObservers.set(kind,{body,observer});}refresh(kind);}}
    const region=document.getElementById('accountActivityResults');if(region)new MutationObserver(bind).observe(region,{childList:true});bind();
    const originalBusy=window.abTablesBusy;window.abTablesBusy=()=>Object.values(state).some(s=>s.busy)||(originalBusy?originalBusy():false);
    async function navigate(kind,href){const s=state[kind];if(s.busy)return;const version=++s.version;s.busy=true;refresh(kind);
        try{const response=await fetch(href,{credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error('โหลดหน้าไม่สำเร็จ');const doc=new DOMParser().parseFromString(await response.text(),'text/html');if(version!==s.version)return;const next=doc.getElementById(ids[kind]),r=root(kind);const tbody=next?.querySelector('tbody'),footer=next?.querySelector('.tp-footer');if(!tbody||!footer||!r)throw new Error('โหลดหน้าไม่สำเร็จ');r.querySelector('tbody').replaceChildren(...Array.from(tbody.childNodes,node=>document.importNode(node,true)));r.querySelector('.tp-footer').replaceWith(document.importNode(footer,true));const url=new URL(href,location.href);history.replaceState(history.state,'',url.pathname+url.search+url.hash);}
        catch(error){if(version===s.version)Swal.fire({icon:'error',text:error.message});}finally{if(version===s.version){s.busy=false;refresh(kind);}}}
    document.addEventListener('click',event=>{const link=event.target.closest('[data-lb-root] .tp-nav a');if(!link||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;const kind=link.closest('[data-lb-root]').dataset.lbRoot;if(!state[kind]?.active)return;event.preventDefault();event.stopImmediatePropagation();navigate(kind,link.href);},true);
})();

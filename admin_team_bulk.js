(() => {
    'use strict';
    const panel=document.getElementById('technicians'),settings=document.getElementById('tb-settings');
    const edit=document.getElementById('tb-edit'),menu=document.getElementById('tb-menu'),count=document.getElementById('tb-count');
    if(!panel||!settings||!edit||!menu||!count)return;
    const state={visible:false,menu:false,busy:false,selected:new Map(),version:0,controller:null};
    const key=item=>item.kind+':'+item.id;
    const boxes=()=>Array.from(panel.querySelectorAll('[data-tb-row]'));
    const item=box=>({kind:box.dataset.kind,id:box.dataset.id,role:box.dataset.role});
    const shown=box=>box.closest('tr').getClientRects().length>0;
    function inject(){
        for(const row of panel.querySelectorAll('tr[data-admin-row-uid],tr[data-tb-tech-id]')){
            if(row.querySelector('[data-tb-row]'))continue;
            const tech=row.hasAttribute('data-tb-tech-id'),id=tech?row.dataset.tbTechId:row.dataset.adminRowUid;
            const cell=document.createElement('td');cell.className='tb-cell';cell.dataset.tbCell='';cell.hidden=!state.visible;
            const box=document.createElement('input');box.type='checkbox';box.className='tb-check';box.dataset.tbRow='';box.dataset.kind=tech?'tech':'user';box.dataset.id=id;
            box.dataset.role=tech?'technician':row.querySelector('td:nth-child(4)')?.textContent.trim().toLowerCase()||'account';
            box.dataset.eligible=tech||id!==settings.dataset.user?'1':'0';
            box.setAttribute('aria-label','เลือก '+(tech?'เจ้าหน้าที่':'บัญชี')+' '+(row.querySelector(tech?'.text-slate-800':'td:nth-child(2) .text-slate-800')?.textContent.trim()||''));
            if(box.dataset.eligible==='0')box.title='ไม่สามารถย้ายบัญชีที่กำลังใช้งานอยู่เข้าถังขยะได้';
            cell.appendChild(box);row.prepend(cell);
        }
        for(const row of panel.querySelectorAll('#managementAccounts thead tr,#techniciansTableBody tr.tech-col-header')){
            if(row.querySelector('[data-tb-page]'))continue;
            const cell=document.createElement('th');cell.className='tb-cell';cell.dataset.tbCell='';cell.hidden=!state.visible;cell.scope='col';
            const box=document.createElement('input');box.type='checkbox';box.className='tb-check';box.dataset.tbPage=row.closest('#managementAccounts')?'management':'tech';box.dataset.dept=row.dataset.dept||'';box.setAttribute('aria-label','เลือกหรือยกเลิกรายการในตารางนี้');cell.appendChild(box);row.prepend(cell);
        }
    }
    function pageBoxes(page){return boxes().filter(box=>box.dataset.eligible==='1'&&shown(box)&&(page.dataset.tbPage==='management'?box.dataset.kind==='user':box.dataset.kind==='tech'&&(!page.dataset.dept||box.closest('tr').dataset.dept===page.dataset.dept)));}
    function refresh(){
        inject();
        panel.querySelectorAll('[data-tb-cell]').forEach(cell=>cell.hidden=!state.visible);
        for(const box of boxes()){box.checked=state.selected.has(key(item(box)));box.disabled=box.dataset.eligible!=='1'||state.busy;}
        for(const cell of panel.querySelectorAll('#techniciansTableBody td[colspan],#managementAccountsBody td[colspan]')){
            if(!cell.dataset.tbOriginalSpan)cell.dataset.tbOriginalSpan=cell.colSpan;
            cell.colSpan=Number(cell.dataset.tbOriginalSpan)+(state.visible?1:0);
        }
        for(const page of panel.querySelectorAll('[data-tb-page]')){const visible=pageBoxes(page),n=visible.filter(box=>box.checked).length;page.checked=visible.length>0&&n===visible.length;page.indeterminate=n>0&&n<visible.length;page.disabled=state.busy||!visible.length;}
        menu.hidden=!state.menu;edit.setAttribute('aria-expanded',String(state.menu));
        menu.querySelectorAll('button').forEach(button=>button.disabled=state.busy||(button.dataset.tbAction==='delete'&&!state.selected.size));
        const label=state.busy?'กำลังดำเนินการ…':'เลือกแล้ว '+state.selected.size.toLocaleString('th-TH')+' รายการ';
        if(count.textContent!==label)count.textContent=label;
        count.hidden=!state.visible;
    }
    function reset(){state.version++;state.controller?.abort();state.busy=false;state.visible=false;state.menu=false;state.selected.clear();refresh();}
    async function selectAll(){
        const version=++state.version;state.controller?.abort();const controller=new AbortController();state.controller=controller;state.busy=true;state.visible=true;refresh();
        try{
            const body=new URLSearchParams({team_bulk_action:'select_all',csrf_token:settings.dataset.token});
            const response=await fetch(location.href.split('#')[0],{method:'POST',body,signal:controller.signal,credentials:'same-origin',cache:'no-store'});
            const data=await response.json();if(version!==state.version)return;
            if(!response.ok||!Array.isArray(data.items))throw new Error(data.error||'เลือกรายการไม่สำเร็จ');
            state.selected=new Map(data.items.map(row=>[key(row),row]));
        }catch(error){if(error.name!=='AbortError'&&version===state.version)Swal.fire({icon:'error',text:error.message});}
        finally{if(version===state.version){state.busy=false;refresh();}}
    }
    async function remove(){
        if(state.busy||!state.selected.size)return;
        const version=state.version,items=Array.from(state.selected.values());state.menu=false;refresh();
        const answer=await Swal.fire({title:'ย้ายรายการที่เลือกเข้าถังขยะ?',text:'เลือกแล้ว '+items.length.toLocaleString('th-TH')+' รายการ รวมรายการที่เลือกไว้ในแท็บ Admin / Executive และตารางเจ้าหน้าที่ บัญชีที่เกี่ยวข้องจะถูกระงับ ข้อมูลยังกู้คืนได้จาก Trash',icon:'warning',showCancelButton:true,confirmButtonText:'ย้ายเข้าถังขยะ',cancelButtonText:'ยกเลิก',confirmButtonColor:'#2563eb'});
        if(!answer.isConfirmed||version!==state.version)return;
        state.busy=true;refresh();
        const url=new URL(location.href);url.searchParams.set('tab','technicians');url.hash='';
        const form=document.createElement('form');form.method='POST';form.action=url.pathname+url.search;
        const fields={team_bulk_action:'move',team_bulk_items:JSON.stringify(items.map(({kind,id})=>({kind,id}))),team_bulk_confirm:'1',csrf_token:settings.dataset.token};
        for(const [name,value] of Object.entries(fields)){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input);}
        document.body.appendChild(form);form.submit();
    }
    edit.addEventListener('click',()=>{state.visible=true;state.menu=!state.menu;refresh();});
    menu.addEventListener('click',e=>{const action=e.target.closest('[data-tb-action]');if(!action||action.disabled)return;
        if(action.dataset.tbAction==='choose'){state.visible=true;refresh();}
        if(action.dataset.tbAction==='all')selectAll();
        if(action.dataset.tbAction==='none')reset();
        if(action.dataset.tbAction==='delete')remove();
    });
    document.addEventListener('click',e=>{if(!e.target.closest('.tb-edit-wrap')&&state.menu){state.menu=false;menu.hidden=true;edit.setAttribute('aria-expanded','false');}});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){state.menu=false;refresh();}});
    panel.addEventListener('change',e=>{
        const box=e.target.closest('[data-tb-row]');if(box){if(box.disabled||state.busy)return;state.version++;if(box.checked)state.selected.set(key(item(box)),item(box));else state.selected.delete(key(item(box)));refresh();}
        const page=e.target.closest('[data-tb-page]');if(page){if(page.disabled||state.busy)return;state.version++;for(const box of pageBoxes(page)){if(page.checked)state.selected.set(key(item(box)),item(box));else state.selected.delete(key(item(box)));}refresh();}
    });
    let scheduled=false;
    new MutationObserver(records=>{if(!records.some(record=>record.type==='attributes'||Array.from(record.addedNodes).some(node=>node.nodeType===1)))return;if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;refresh();});}).observe(panel,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style']});
    window.tbTeamBusy=()=>state.busy;refresh();
})();

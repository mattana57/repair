(() => {
    'use strict';
    const root=document.getElementById('transactionsBulk'),table=document.getElementById('repairsTable');
    if(!root||!table)return;
    const edit=document.getElementById('transactionsBulkEdit'),menu=document.getElementById('transactionsBulkMenu'),count=document.getElementById('transactionsBulkCount');
    const selected=new Set();let visible=false,busy=false;
    function refresh(){
        for(const row of table.querySelectorAll('tbody tr[data-tx-id]')){
            let box=row.querySelector('.tx-check');
            if(!box){box=document.createElement('input');box.type='checkbox';box.className='tx-check';box.setAttribute('aria-label','เลือกรายการ '+row.dataset.txId);row.cells[0].prepend(box);}
            box.hidden=!visible;box.checked=selected.has(row.dataset.txId);box.disabled=busy;row.classList.toggle('tx-selected',box.checked);
        }
        count.textContent='เลือกแล้ว '+selected.size.toLocaleString('th-TH')+' รายการ';
        count.hidden=!visible;
        menu.querySelector('[data-tx-action=delete]').disabled=busy||!selected.size;
    }
    function close(){menu.hidden=true;edit.setAttribute('aria-expanded','false');}
    edit.addEventListener('click',()=>{visible=true;menu.hidden=!menu.hidden;edit.setAttribute('aria-expanded',String(!menu.hidden));refresh();});
    table.addEventListener('change',event=>{const box=event.target.closest('.tx-check');if(!box||busy)return;const id=box.closest('tr').dataset.txId;if(box.checked)selected.add(id);else selected.delete(id);refresh();});
    menu.addEventListener('click',async event=>{
        const button=event.target.closest('[data-tx-action]');if(!button||busy)return;
        if(button.dataset.txAction==='all'){for(const row of table.querySelectorAll('tbody tr[data-tx-id]'))selected.add(row.dataset.txId);refresh();return;}
        if(button.dataset.txAction==='cancel'){selected.clear();visible=false;close();refresh();return;}
        if(button.dataset.txAction!=='delete'||!selected.size)return;
        close();busy=true;refresh();
        const ids=Array.from(selected);
        const answer=await Swal.fire({title:'ย้ายประวัติผู้แจ้งซ่อมเข้า Trash?',text:'เลือกแล้ว '+ids.length+' รายการ สามารถกู้คืนได้จาก Trash หมวดประวัติผู้แจ้งซ่อม',icon:'warning',showCancelButton:true,confirmButtonText:'ย้ายเข้า Trash',cancelButtonText:'ยกเลิก',confirmButtonColor:'#e11d48'});
        if(!answer.isConfirmed){busy=false;refresh();return;}
        const url=new URL(location.href);url.searchParams.set('tab','repairs');url.searchParams.delete('pg_repairs_fragment');url.searchParams.delete('_');
        const form=document.createElement('form');form.method='POST';form.action=url.pathname+url.search;
        for(const [name,value] of Object.entries({transactions_bulk_action:'move',transactions_bulk_confirm:'1',transactions_bulk_ids:JSON.stringify(ids),csrf_token:root.dataset.token})){
            const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.append(input);
        }
        document.body.append(form);form.submit();
    });
    document.addEventListener('click',event=>{if(!root.contains(event.target))close();});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){close();edit.focus();}});
    // Keep selections when the existing PHP table fragment/live refresh replaces rows.
    new MutationObserver(refresh).observe(table.tBodies[0],{childList:true});refresh();
})();

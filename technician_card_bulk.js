(() => {
  const panel = document.getElementById('team_cards');
  const edit = document.getElementById('mbsCardEdit');
  if (!panel || !edit) return;
  const menu = document.getElementById('mbsCardMenu');
  const count = document.getElementById('mbsCardSelected');
  const boxes = () => [...panel.querySelectorAll('.mbs-card-select input')];
  let selecting = false, busy = false;
  function refresh() {
    panel.querySelectorAll('.mbs-card-select').forEach(el => el.hidden = !selecting);
    const n = boxes().filter(el => el.checked).length;
    count.hidden = !selecting;
    count.textContent = 'เลือกแล้ว ' + n + ' รายการ';
    menu.querySelector('[data-card-action="delete"]').disabled = !n || busy;
  }
  edit.onclick = () => {
    menu.hidden = !menu.hidden;
    if (!menu.hidden) { selecting = true; refresh(); }
    edit.setAttribute('aria-expanded', String(!menu.hidden));
  };
  panel.addEventListener('change', e => { if (e.target.matches('.mbs-card-select input')) refresh(); });
  menu.addEventListener('click', async e => {
    const action = e.target.closest('[data-card-action]')?.dataset.cardAction;
    if (!action || busy) return;
    if (action === 'choose') selecting = true;
    if (action === 'all') {
      selecting = true;
      boxes().forEach(el => { if (el.closest('.tech-card-item').getClientRects().length) el.checked = true; });
    }
    if (action === 'cancel') { selecting = false; boxes().forEach(el => el.checked = false); closeMenu(); }
    if (action === 'delete') {
      const selected = boxes().filter(el => el.checked);
      if (!selected.length) return;
      busy = true; refresh();
      const result = await Swal.fire({ title: 'ลบเจ้าหน้าที่ ' + selected.length + ' รายการ?', text: 'ข้อมูลจะถูกย้ายไปที่ Trash และสามารถกู้คืนได้', icon: 'warning', showCancelButton: true, confirmButtonText: 'ย้ายเข้าถังขยะ', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#f43f5e' });
      if (result.isConfirmed) {
        const form = document.getElementById('mbsCardBulkForm');
        form.elements.team_bulk_items.value = JSON.stringify(selected.map(el => ({kind:'tech', id:el.value})));
        form.submit();
        return;
      }
      busy = false;
    }
    refresh();
  });
  function closeMenu() {
    menu.hidden = true;
    edit.setAttribute('aria-expanded', 'false');
  }
  document.addEventListener('click', e => {
    if (!menu.hidden && !menu.contains(e.target) && !edit.contains(e.target)) closeMenu();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
  refresh();
})();

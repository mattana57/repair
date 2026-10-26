(() => {
  const selector = '.ab-menu, #tb-menu, #mbsCardMenu, #trashBulkToolbar';
  function positionCounts() {
    const failed = new URL(location.href).searchParams.get('pg_logs_status') === 'failed';
    for (const [id, editSelector, after] of [
      ['tb-count', '#tb-edit', false],
      ['ab-count-asset', '[data-ab-edit="asset"]', false],
      ['lb-count-loginlog', '[data-lb-edit="loginlog"]', !failed],
      ['lb-count-activityperson', '[data-lb-edit="activityperson"]', false],
      ['lb-count-activityevent', '[data-lb-edit="activityevent"]', false],
    ]) {
      const count = document.getElementById(id), edit = document.querySelector(editSelector);
      if (!count || !edit) continue;
      const wrap = edit.closest('.ab-edit-wrap, .tb-edit-wrap');
      if (!wrap) continue;
      let group = wrap.parentElement;
      if (!group.classList.contains('mbs-edit-count-group')) {
        group = document.createElement('div');
        group.className = 'mbs-edit-count-group';
        wrap.before(group);
        group.append(wrap);
      }
      if (count.parentElement === group) continue;
      if (after) group.append(count);
      else group.prepend(count);
    }
    const reporterCount = document.getElementById('ab-count-reporter');
    const subtitle = [...document.querySelectorAll('#users p')].find(el =>
      el.textContent.trim() === 'Database of personnel who reported issues');
    if (reporterCount && subtitle && subtitle.nextElementSibling !== reporterCount) {
      subtitle.after(reporterCount);
    }
  }
  function labels() {
    document.querySelectorAll(selector).forEach(menu => {
      const walker = document.createTreeWalker(menu, NodeFilter.SHOW_TEXT);
      let node;
      while ((node = walker.nextNode())) {
        if (node.nodeValue.trim() === 'ไม่เลือก') node.nodeValue = node.nodeValue.replace('ไม่เลือก', 'ยกเลิก');
      }
    });
  }
  function init() {
    labels();
    positionCounts();
    new MutationObserver(records => {
      if (records.some(r => [...r.addedNodes].some(n => n.nodeType === 1))) { labels(); positionCounts(); }
    }).observe(document.body, {childList:true, subtree:true});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
})();

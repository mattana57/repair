(() => {
  const selector = '.ab-menu, #tb-menu, #mbsCardMenu, #trashBulkToolbar';
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
    new MutationObserver(records => {
      if (records.some(r => [...r.addedNodes].some(n => n.nodeType === 1))) labels();
    }).observe(document.body, {childList:true, subtree:true});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
})();

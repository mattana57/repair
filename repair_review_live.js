/* Update only the review panel; never reload the page or touch the job form. */
(() => {
  'use strict';
  const card = document.getElementById('mbsRepairReview');
  const body = card?.querySelector('.p-5');
  const id = card?.dataset.mbsRepairId;
  const layout = card?.dataset.mbsReviewLayout;
  if (!body || !/^[1-9]\d*$/.test(id || '') || !['update', 'view'].includes(layout)) return;
  const url = new URL('repair_review_live.php', location.href);
  url.searchParams.set('id', id);
  url.searchParams.set('layout', layout);
  let stopped = false;
  let pending = false;
  let timer;
  let lastHtml;
  let controller;
  async function refresh() {
    clearTimeout(timer);
    if (stopped || pending || document.hidden) return;
    pending = true;
    controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);
    try {
      const response = await fetch(url, {method: 'GET', credentials: 'same-origin',
        cache: 'no-store', headers: {'Accept': 'application/json'}, signal: controller.signal});
      if (response.redirected || [401, 403, 404].includes(response.status)) {
        stopped = true;
        return;
      }
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return;
      const review = await response.json();
      if (String(review.id) !== id || typeof review.html !== 'string') return;
      if (review.html !== lastHtml) {
        // HTML is rendered by the authenticated, same-origin PHP template with escaped values.
        body.innerHTML = review.html;
        lastHtml = review.html;
      }
    } catch (_) {
      // Keep the current review during a transient network failure; retry later.
    } finally {
      clearTimeout(timeout);
      pending = false;
      if (!stopped && !document.hidden) timer = setTimeout(refresh, 3000);
    }
  }
  document.addEventListener('visibilitychange', () => {
    clearTimeout(timer);
    if (!document.hidden) refresh();
  });
  window.addEventListener('pagehide', () => { clearTimeout(timer); controller?.abort(); });
  window.addEventListener('pageshow', () => { if (!document.hidden) refresh(); });
  refresh();
})();

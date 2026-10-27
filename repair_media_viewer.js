/* View existing authorized attachment URLs only. Never upload or change a job. */
(() => {
  'use strict';
  const roots = [...document.querySelectorAll('[data-mbs-repair-media]')];
  if (!roots.length) return;
  let dialog, stage, title, previousFocus;
  function safeUrl(raw) {
    try {
      const u = new URL(raw, location.href);
      return ['http:', 'https:'].includes(u.protocol) && u.origin === location.origin ? u.href : null;
    } catch (_) { return null; }
  }
  function createViewer() {
    dialog = document.createElement('dialog');
    dialog.id = 'mbsRepairMediaViewer';
    dialog.setAttribute('aria-labelledby', 'mbsRepairMediaTitle');
    const toolbar = document.createElement('div'); toolbar.className = 'mbs-media-toolbar';
    title = document.createElement('h2'); title.id = 'mbsRepairMediaTitle'; title.className = 'mbs-media-title';
    const close = document.createElement('button'); close.type = 'button'; close.className = 'mbs-media-close';
    close.setAttribute('aria-label', 'ปิดรูปภาพหรือวิดีโอ'); close.textContent = '×';
    close.addEventListener('click', () => dialog.close());
    toolbar.append(title, close);
    stage = document.createElement('div'); stage.className = 'mbs-media-stage';
    dialog.append(toolbar, stage); document.body.append(dialog);
    dialog.addEventListener('click', e => {
      if (e.target !== dialog) return;
      const b = dialog.getBoundingClientRect();
      if (e.clientX < b.left || e.clientX > b.right || e.clientY < b.top || e.clientY > b.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
      const video = stage.querySelector('video');
      if (video) { video.pause(); video.removeAttribute('src'); video.load(); }
      stage.replaceChildren(); previousFocus?.focus();
    });
  }
  function open(raw, type, opener) {
    const url = safeUrl(raw);
    if (!url) return false;
    if (!dialog) createViewer();
    previousFocus = opener;
    const media = document.createElement(type === 'video' ? 'video' : 'img');
    media.src = url;
    if (type === 'video') { media.controls = true; media.playsInline = true; media.preload = 'metadata'; }
    else media.alt = opener?.alt || 'รูปภาพแนบใบงาน';
    title.textContent = type === 'video' ? 'วิดีโอแนบใบงาน' : 'รูปภาพแนบใบงาน';
    stage.replaceChildren(media);
    if (!dialog.open) dialog.showModal();
    return true;
  }
  for (const root of roots) {
    const types = new Map();
    for (const holder of root.querySelectorAll('[data-mbs-media-files]')) {
      try {
        const files = JSON.parse(holder.dataset.mbsMediaFiles);
        if (Array.isArray(files)) for (const f of files)
          if (f.state === 'ready' && ['image', 'video'].includes(f.media_type)) types.set(String(f.id), f.media_type);
      } catch (_) {}
    }
    for (const img of root.querySelectorAll('img')) {
      if (!img.closest('a,button')) { img.tabIndex = 0; img.setAttribute('role', 'button'); img.setAttribute('aria-label', 'ดูรูปภาพเต็ม'); }
    }
    root.addEventListener('click', e => {
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
      const anchor = e.target.closest('a');
      const url = anchor && safeUrl(anchor.href);
      const type = anchor && (anchor.dataset.mbsMediaType || (url && types.get(new URL(url).searchParams.get('file'))));
      if (type === 'video' && open(url, 'video', anchor)) { e.preventDefault(); return; }
      const img = e.target.closest('img') || anchor?.querySelector('img');
      if (img && open(anchor?.href || img.currentSrc || img.src, 'image', anchor || img)) { e.preventDefault(); return; }
      if (!anchor) return;
      if (['image', 'video'].includes(type) && open(url, type, anchor)) e.preventDefault();
    });
    root.addEventListener('keydown', e => {
      if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('img[role="button"]')) {
        if (open(e.target.currentSrc || e.target.src, 'image', e.target)) e.preventDefault();
      }
    });
    for (const video of root.querySelectorAll('video')) {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'mbs-media-open';
      button.textContent = 'ดูวิดีโอเต็ม';
      button.addEventListener('click', () => {
        const url = video.currentSrc || video.src || video.querySelector('source')?.src;
        if (safeUrl(url)) { video.pause(); open(url, 'video', button); }
      });
      const item = document.createElement('div'); item.className = 'mbs-media-video-item';
      video.before(item); item.append(video, button);
    }
  }
})();

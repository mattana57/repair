(() => {
    'use strict';
    const modal = document.getElementById('selfProfileModal');
    if (!modal) return;
    const form = document.getElementById('selfProfileForm');
    const body = modal.querySelector('.sp-body');
    const message = document.getElementById('selfProfileMessage');
    const save = document.getElementById('selfProfileSave');
    let controller = null, version = 0, saving = false, loaded = false, closeTimer = null;
    const field = name => form.elements.namedItem(name);
    function showMessage(text, success = false) {
        message.textContent = text;
        message.classList.toggle('sp-success', success);
        message.hidden = !text;
        if (text) body.scrollTo({top: 0, behavior: 'smooth'});
    }
    function busy(value) {
        form.querySelectorAll('input, .sp-select').forEach(el => { el.disabled = value; });
        modal.querySelectorAll('[data-sp-close]').forEach(button => { button.disabled = saving; });
        save.disabled = value || !loaded;
        save.textContent = saving ? 'กำลังบันทึก...' : 'Save Data';
    }
    function closeChoices(except = null) {
        form.querySelectorAll('[data-sp-choice]').forEach(group => {
            if (group === except) return;
            group.querySelector('.sp-options').hidden = true;
            group.querySelector('.sp-select').setAttribute('aria-expanded', 'false');
        });
    }
    function setChoice(group, value, custom = null) {
        const options = [...group.querySelectorAll('.sp-option')];
        const known = options.find(option => option.dataset.value === value && value !== 'อื่นๆ');
        const selected = known ? value : (value ? 'อื่นๆ' : '');
        group.querySelector('input[type=hidden]').value = selected;
        const label = group.querySelector('[data-sp-value]');
        label.textContent = selected === 'อื่นๆ' ? 'อื่นๆ (Custom)' : (selected || label.dataset.placeholder);
        const input = group.querySelector('.sp-custom');
        input.hidden = selected !== 'อื่นๆ';
        input.required = selected === 'อื่นๆ';
        input.value = selected === 'อื่นๆ' ? (custom !== null ? custom : (value === 'อื่นๆ' ? '' : value)) : '';
        options.forEach(option => option.setAttribute('aria-selected', String(option.dataset.value === selected)));
        group.querySelector('.sp-select').classList.remove('sp-invalid');
    }
    function openChoice(group, focus = false) {
        const trigger = group.querySelector('.sp-select');
        const menu = group.querySelector('.sp-options');
        const wasOpen = !menu.hidden;
        closeChoices();
        if (wasOpen) return;
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        const area = body.getBoundingClientRect(), rect = trigger.getBoundingClientRect();
        const above = rect.top - area.top - 10, below = area.bottom - rect.bottom - 10;
        const useAbove = below < Math.min(menu.scrollHeight, 250) && above > below;
        menu.style.top = useAbove ? 'auto' : 'calc(100% + 8px)';
        menu.style.bottom = useAbove ? 'calc(100% + 8px)' : 'auto';
        menu.style.maxHeight = Math.max(70, Math.min(260, useAbove ? above : below)) + 'px';
        if (focus) (menu.querySelector('[aria-selected=true]') || menu.querySelector('.sp-option')).focus({preventScroll: true});
    }
    async function request(options) {
        const response = await fetch('self_profile_endpoint.php', {credentials: 'same-origin', cache: 'no-store', ...options});
        if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('เซสชันหมดอายุหรือไม่สามารถตรวจบัญชีได้ กรุณาเข้าสู่ระบบใหม่');
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'ไม่สามารถทำรายการได้');
        return data;
    }
    function close() {
        if (saving) return;
        version++;
        if (controller) controller.abort();
        closeChoices();
        modal.classList.remove('sp-open');
        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => { if (modal.open) modal.close(); }, 180);
    }
    async function open(event) {
        if (event) {
            event.preventDefault();
            const details = event.target.closest('details');
            if (details) details.open = false;
        }
        if (typeof window.closeProfileDropdown === 'function') window.closeProfileDropdown();
        if (typeof window.closeAvatarMenu === 'function') window.closeAvatarMenu();
        clearTimeout(closeTimer);
        if (controller) controller.abort();
        const ticket = ++version;
        controller = new AbortController();
        form.reset(); loaded = false; saving = false;
        closeChoices(); showMessage(''); busy(true);
        if (!modal.open) modal.showModal();
        body.scrollTop = 0;
        requestAnimationFrame(() => modal.classList.add('sp-open'));
        const timer = setTimeout(() => controller.abort(), 15000);
        try {
            const data = await request({signal: controller.signal});
            if (ticket !== version || !modal.open) return;
            if (data.profile.role !== modal.dataset.role) throw new Error('บัญชีที่เข้าสู่ระบบเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่');
            for (const name of ['username', 'full_name', 'english_name', 'phone', 'email']) field(name).value = data.profile[name] || '';
            field('revision').value = data.revision;
            form.querySelectorAll('[data-sp-choice]').forEach(group => setChoice(group, data.profile[group.dataset.spChoice] || ''));
            loaded = true;
        } catch (error) {
            if (ticket === version) showMessage(error.name === 'AbortError' ? 'โหลดข้อมูลนานเกินไป กรุณาปิดแล้วเปิดหน้าต่างใหม่' : error.message);
        } finally {
            clearTimeout(timer);
            if (ticket === version) busy(false);
        }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-self-profile-open]');
        if (button) open(event);
    });
    modal.querySelectorAll('[data-sp-close]').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('cancel', event => { event.preventDefault(); close(); });
    modal.addEventListener('click', event => {
        if (event.target === modal) {
            const r = modal.getBoundingClientRect();
            if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) close();
        }
        const group = event.target.closest('[data-sp-choice]');
        const option = event.target.closest('.sp-option');
        if (option && group) {
            setChoice(group, option.dataset.value);
            closeChoices();
            const custom = group.querySelector('.sp-custom');
            if (!custom.hidden) { custom.scrollIntoView({block: 'nearest', behavior: 'smooth'}); custom.focus({preventScroll: true}); }
            else group.querySelector('.sp-select').focus({preventScroll: true});
        } else if (event.target.closest('.sp-select') && group) openChoice(group);
        else closeChoices();
    });
    modal.addEventListener('keydown', event => {
        const group = event.target.closest('[data-sp-choice]');
        if (!group) return;
        const menu = group.querySelector('.sp-options');
        const options = [...menu.querySelectorAll('.sp-option')];
        if (event.key === 'Escape' && !menu.hidden) { event.preventDefault(); event.stopPropagation(); closeChoices(); group.querySelector('.sp-select').focus(); return; }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) && !event.target.classList.contains('sp-custom')) {
            event.preventDefault();
            if (menu.hidden) { openChoice(group, true); return; }
            let index = options.indexOf(document.activeElement);
            if (event.key === 'Home') index = 0;
            else if (event.key === 'End') index = options.length - 1;
            else index = (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options[index].focus();
        }
    });
    modal.addEventListener('focusin', event => { if (!event.target.closest('.sp-combo')) closeChoices(); });
    form.addEventListener('input', event => { event.target.setCustomValidity(''); event.target.classList.remove('sp-invalid'); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!loaded || saving) return;
        closeChoices(); showMessage('');
        let invalid = null;
        for (const name of ['username', 'full_name', 'phone', 'email']) {
            const input = field(name);
            input.setCustomValidity(input.value.trim() ? '' : 'กรุณากรอกข้อมูลช่องนี้');
        }
        form.querySelectorAll('[data-sp-choice]').forEach(group => {
            const select = group.querySelector('input[type=hidden]');
            const trigger = group.querySelector('.sp-select');
            trigger.classList.toggle('sp-invalid', !select.value);
            if (!select.value && !invalid) invalid = trigger;
        });
        if (!form.reportValidity()) return;
        if (invalid) { invalid.focus(); showMessage('กรุณาเลือกตำแหน่งงานและฝ่ายงานให้ครบ'); return; }
        const payload = new FormData(form);
        saving = true; busy(true);
        controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);
        try {
            const data = await request({method: 'POST', body: payload, signal: controller.signal});
            showMessage(data.message, true);
            // โหลดข้อมูลล่าสุดจากฐานข้อมูลเดิม เพื่อให้ชื่อในหัวเว็บ/เมนูตรงกันทุกจุด
            setTimeout(() => window.location.reload(), 650);
        } catch (error) {
            showMessage(error.name === 'AbortError' ? 'ยังยืนยันผลการบันทึกไม่ได้ กรุณาตรวจสอบข้อมูลปัจจุบันก่อนบันทึกซ้ำ' : error.message);
            saving = false; busy(false);
        } finally { clearTimeout(timer); }
    });
})();

/* Display only: never use this text as a filter value, sort key or stored timestamp. */
(() => {
    'use strict';
    const months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    window.mbsThaiDisplayDateFull = (value, withTime = true, fallback = '-') => {
        if (typeof value !== 'string' || !value) return fallback;
        const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/.exec(value);
        if (!match) return fallback;
        const year = Number(match[1]), month = Number(match[2]), day = Number(match[3]);
        const hour = Number(match[4] || 0), minute = Number(match[5] || 0), second = Number(match[6] || 0);
        const check = new Date(Date.UTC(year, month - 1, day));
        if (year < 1000 || check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day
            || hour > 23 || minute > 59 || second > 59) return fallback;
        const text = day + ' ' + months[month - 1] + ' ' + (year + 543);
        return text + (withTime && match[4] !== undefined ? ' ' + match[4] + ':' + match[5] : '');
    };
})();

<?php
// Display formatting only. Database values and application timezone are unchanged.
function mbsThaiDisplayDate($value, $withTime = true, $fallback = '-') {
    if (!is_string($value) || $value === '') return $fallback;
    $format = strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s';
    $date = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('Asia/Bangkok'));
    if (!$date || $date->format($format) !== $value || (int)$date->format('Y') < 1000) return $fallback;
    $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $year = str_pad((string)(((int)$date->format('Y') + 543) % 100), 2, '0', STR_PAD_LEFT);
    $text = $date->format('j').' '.$months[(int)$date->format('n')-1].' '.$year;
    return $text.($withTime && $format === 'Y-m-d H:i:s' ? ' '.$date->format('H:i') : '');
}

// Dashboard/report display: abbreviated Thai month and full Buddhist year.
// The existing helper above retains its original behaviour for update/view.
function mbsThaiDisplayDateFull($value, $withTime = true, $fallback = '-') {
    if (!is_string($value) || $value === '') return $fallback;
    $formats = [10=>'Y-m-d', 16=>'Y-m-d H:i', 19=>'Y-m-d H:i:s'];
    $format = $formats[strlen($value)] ?? null;
    if ($format === null) return $fallback;
    $date = DateTimeImmutable::createFromFormat('!'.$format, $value, new DateTimeZone('Asia/Bangkok'));
    if (!$date || $date->format($format) !== $value || (int)$date->format('Y') < 1000) return $fallback;
    $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $text = $date->format('j').' '.$months[(int)$date->format('n')-1].' '.((int)$date->format('Y')+543);
    return $text.($withTime && $format !== 'Y-m-d' ? ' '.$date->format('H:i') : '');
}

// Only for the visible Trash description of a login log; query/search data stays unchanged.
function mbsThaiDisplayDateInText($text) {
    return preg_replace_callback('/\b(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})\b/', static function ($m) {
        $raw=$m[3].'-'.$m[2].'-'.$m[1].' '.$m[4].':'.$m[5].':00';
        return mbsThaiDisplayDateFull($raw);
    }, (string)$text);
}

function mbsThaiDisplayTime($value) {
    return is_string($value) && strlen($value) >= 16 && mbsThaiDisplayDateFull($value) !== '-'
        ? substr($value, 11, 5) : '';
}

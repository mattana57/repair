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

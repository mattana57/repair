<?php
require_once __DIR__ . '/thai_display_date.php';
// Display only: never updates repair records or sends LINE messages.
function mbs_repair_review_datetime(array $repair): string {
    $timezone = new DateTimeZone('Asia/Bangkok');
    foreach (['rated_at', 'completed_at'] as $column) {
        $raw = $repair[$column] ?? null;
        if (!is_string($raw) || $raw === '' || substr($raw, 0, 4) === '0000') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw, $timezone);
        if ($date && $date->format('Y-m-d H:i:s') === $raw) {
            return mbsThaiDisplayDate($raw, true, 'ไม่ระบุเวลา');
        }
    }
    return 'ไม่ระบุเวลา';
}

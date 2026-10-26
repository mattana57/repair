<?php
// Search only the already authorised and filtered repair rows, before pagination.
function mbs_analytics_search_rows(array $rows, array $technicians, string $query): array {
    $query = preg_replace('/\s+/u', '', trim($query));
    if ($query === '') return $rows;
    $staff = [];
    foreach ($technicians as $technician) $staff[(string)$technician['id']] = $technician;
    return array_values(array_filter($rows, static function ($row) use ($query, $staff) {
        $parts = [];
        foreach ($row as $value) if (is_scalar($value)) $parts[] = (string)$value;
        $technician = $staff[(string)($row['technician_id'] ?? '')] ?? [];
        foreach (['full_name','english_name','department','position','phone','email'] as $key) $parts[] = (string)($technician[$key] ?? '');
        foreach (['created_at','received_at','completed_at','rated_at'] as $key) {
            $raw = (string)($row[$key] ?? '');
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/', $raw, $date)) {
                $parts[] = $date[3].'/'.$date[2].'/'.$date[1].' '.($date[4] ?? '');
                $parts[] = $date[3].'/'.$date[2].'/'.((int)$date[1]+543).' '.($date[4] ?? '');
            }
        }
        $parts[] = mbs_duration($row['received_at'] ?? null, $row['completed_at'] ?? null);
        $text = preg_replace('/\s+/u', '', implode(' ', $parts));
        return preg_match('/'.preg_quote($query, '/').'/iu', $text) === 1;
    }));
}

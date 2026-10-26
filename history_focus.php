<?php
// Find the PHP page containing a selected job inside the already authorised,
// filtered history. A focus ID never widens the history or selects one row only.
function mbs_history_focus_page(callable $run, string $scope, array $values, int $id): int {
    if ($id <= 0) return 1;
    $result = $run("SELECT id, created_at FROM ($scope) AS focus_scope WHERE id = ?", array_merge($values, [$id]));
    $target = $result->fetch_assoc();
    $result->free();
    if (!$target || !$target['created_at']) return 1;
    $result = $run("SELECT COUNT(*) AS ahead FROM ($scope) AS focus_scope WHERE created_at > ? OR (created_at = ? AND id > ?)", array_merge($values, [$target['created_at'], $target['created_at'], $id]));
    $ahead = (int)$result->fetch_assoc()['ahead'];
    $result->free();
    return intdiv($ahead, 50) + 1;
}

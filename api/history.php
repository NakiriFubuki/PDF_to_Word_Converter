<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

ensure_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'GET requests only'], 405);
}

$sid = session_token();
$rows = list_conversions($sid, 12);

$items = array_map(static function (array $row): array {
    $direction = $row['direction'] ?? 'pdf_to_word';
    return [
        'id' => (int) $row['id'],
        'original_name' => $row['original_name'],
        'direction' => $direction,
        'direction_label' => $direction === 'word_to_pdf' ? 'Word → PDF' : 'PDF → Word',
        'file_size_label' => format_bytes((int) $row['file_size']),
        'page_count' => $row['page_count'] !== null ? (int) $row['page_count'] : null,
        'status' => $row['status'],
        'error_message' => $row['error_message'],
        'created_at' => $row['created_at'],
        'download_url' => $row['status'] === 'completed'
            ? 'api/download.php?id=' . (int) $row['id']
            : null,
    ];
}, $rows);

json_response(['ok' => true, 'items' => $items]);

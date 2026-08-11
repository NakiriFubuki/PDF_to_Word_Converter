<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

ensure_schema();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid download request';
    exit;
}

$sid = session_token();
$row = get_conversion_for_session($id, $sid);

if (!$row || $row['status'] !== 'completed') {
    http_response_code(404);
    echo 'File not found or conversion is not complete';
    exit;
}

$direction = $row['direction'] ?? 'pdf_to_word';

if ($direction === 'word_to_pdf') {
    if (empty($row['stored_pdf'])) {
        http_response_code(404);
        echo 'File not found or conversion is not complete';
        exit;
    }
    $path = OUTPUT_DIR . DIRECTORY_SEPARATOR . $row['stored_pdf'];
    $contentType = 'application/pdf';
} else {
    if (empty($row['stored_docx'])) {
        http_response_code(404);
        echo 'File not found or conversion is not complete';
        exit;
    }
    $path = OUTPUT_DIR . DIRECTORY_SEPARATOR . $row['stored_docx'];
    $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

if (!is_file($path)) {
    http_response_code(404);
    echo 'Output file has expired or been cleaned up';
    exit;
}

$downloadName = output_download_name($row);

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');

readfile($path);
exit;

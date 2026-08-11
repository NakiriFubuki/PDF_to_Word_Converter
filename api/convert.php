<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

try {
    ensure_schema();
    cleanup_expired_files();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'POST requests only'], 405);
    }

    $direction = $_POST['direction'] ?? 'pdf_to_word';
    if (!in_array($direction, ['pdf_to_word', 'word_to_pdf'], true)) {
        json_response(['ok' => false, 'error' => 'Invalid conversion direction'], 400);
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        json_response(['ok' => false, 'error' => 'Please upload a file'], 400);
    }

    $file = $_FILES['file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File is too large',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
            UPLOAD_ERR_NO_FILE => 'No file was selected',
        ];
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        json_response(['ok' => false, 'error' => $messages[$code] ?? 'Upload failed'], 400);
    }

    if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
        json_response(['ok' => false, 'error' => 'File must be 20 MB or smaller'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($direction === 'pdf_to_word') {
        $allowedMimes = ['application/pdf', 'application/x-pdf'];
        if (!in_array($mime, $allowedMimes, true) && $ext !== 'pdf') {
            json_response(['ok' => false, 'error' => 'PDF files only for this mode'], 400);
        }
        $header = file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($header !== '%PDF-') {
            json_response(['ok' => false, 'error' => 'File is not a valid PDF'], 400);
        }
    } else {
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/zip', // some servers detect docx as zip
            'application/octet-stream',
        ];
        if (!in_array($mime, $allowedMimes, true) && !in_array($ext, ['docx', 'doc'], true)) {
            json_response(['ok' => false, 'error' => 'Word files (.docx) only for this mode'], 400);
        }
        if ($ext === 'doc') {
            json_response(['ok' => false, 'error' => 'Legacy .doc is not supported. Please use .docx'], 400);
        }
        // DOCX is a ZIP package
        $zipCheck = @file_get_contents($file['tmp_name'], false, null, 0, 2);
        if ($zipCheck !== 'PK') {
            json_response(['ok' => false, 'error' => 'File is not a valid .docx document'], 400);
        }
    }

    $sid = session_token();
    $original = sanitize_filename($file['name']);
    $token = bin2hex(random_bytes(8));
    $pdfName = $token . '.pdf';
    $docxName = $token . '.docx';

    $inputPath = '';
    $outputPath = '';

    if ($direction === 'pdf_to_word') {
        $inputPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $pdfName;
        $outputPath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $docxName;
    } else {
        $inputPath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $docxName;
        $outputPath = OUTPUT_DIR . DIRECTORY_SEPARATOR . $pdfName;
    }

    if (!move_uploaded_file($file['tmp_name'], $inputPath)) {
        json_response(['ok' => false, 'error' => 'Could not save the uploaded file'], 500);
    }

    $stmt = db()->prepare(
        'INSERT INTO conversions (session_id, original_name, direction, stored_pdf, stored_docx, file_size, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $sid,
        $original,
        $direction,
        $pdfName,
        $docxName,
        (int) $file['size'],
        'processing',
    ]);
    $conversionId = (int) db()->lastInsertId();

    try {
        if ($direction === 'pdf_to_word') {
            $result = convert_pdf_to_docx($inputPath, $outputPath);
            $outputLabel = pathinfo($original, PATHINFO_FILENAME) . '.docx';
        } else {
            $result = convert_docx_to_pdf($inputPath, $outputPath);
            $outputLabel = pathinfo($original, PATHINFO_FILENAME) . '.pdf';
        }

        $update = db()->prepare(
            'UPDATE conversions
             SET page_count = ?, status = ?, completed_at = NOW()
             WHERE id = ?'
        );
        $update->execute([$result['page_count'], 'completed', $conversionId]);

        json_response([
            'ok' => true,
            'conversion' => [
                'id' => $conversionId,
                'direction' => $direction,
                'original_name' => $original,
                'output_name' => $outputLabel,
                'file_size' => (int) $file['size'],
                'file_size_label' => format_bytes((int) $file['size']),
                'page_count' => $result['page_count'],
                'download_url' => 'api/download.php?id=' . $conversionId,
            ],
        ]);
    } catch (Throwable $e) {
        @unlink($inputPath);
        @unlink($outputPath);

        $fail = db()->prepare(
            'UPDATE conversions SET status = ?, error_message = ?, completed_at = NOW() WHERE id = ?'
        );
        $fail->execute(['failed', mb_substr($e->getMessage(), 0, 500), $conversionId]);

        json_response([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 422);
    }
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => 'Service temporarily unavailable. Make sure Apache and MySQL are running, then try again.',
    ], 500);
}

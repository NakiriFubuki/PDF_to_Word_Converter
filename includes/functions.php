<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once APP_ROOT . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Image;
use Smalot\PdfParser\Parser;

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function session_token(): string
{
    if (empty($_SESSION['converter_sid'])) {
        $_SESSION['converter_sid'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['converter_sid'];
}

function sanitize_filename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^\w\s\.\-\(\)\[\]]+/u', '_', $name) ?? 'document';
    return trim($name) !== '' ? $name : 'document';
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 2) . ' MB';
}

function cleanup_expired_files(): void
{
    $cutoff = time() - (FILE_TTL_HOURS * 3600);

    foreach ([UPLOAD_DIR, OUTPUT_DIR] as $dir) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $base = basename($file);
            if ($base === '.gitkeep' || $base === '.htaccess') {
                continue;
            }
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    try {
        $stmt = db()->prepare('DELETE FROM conversions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)');
        $stmt->execute([FILE_TTL_HOURS]);
    } catch (Throwable $e) {
        // Schema may not exist yet during first boot
    }
}

/**
 * Convert a PDF file to DOCX, preserving text spacing, code line breaks, and images.
 */
function convert_pdf_to_docx(string $pdfPath, string $docxPath): array
{
    // Required so text like "<?php" does not break the DOCX XML
    PhpWordSettings::setOutputEscapingEnabled(true);

    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);
    $pages = $pdf->getPages();
    $pageCount = count($pages);

    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName('Calibri');
    $phpWord->setDefaultFontSize(11);

    $sectionStyle = [
        'marginTop' => 720,
        'marginBottom' => 720,
        'marginLeft' => 720,
        'marginRight' => 720,
    ];

    $tempImages = [];

    try {
        if ($pageCount === 0) {
            $section = $phpWord->addSection($sectionStyle);
            $text = trim($pdf->getText());
            if ($text === '') {
                throw new RuntimeException('Could not extract text from this PDF. It may be a scan or a protected file.');
            }
            add_pdf_text_structure($section, $text);
        } else {
            foreach ($pages as $index => $page) {
                $section = $phpWord->addSection($sectionStyle);
                $items = extract_pdf_page_items($page, $tempImages);

                if ($items === []) {
                    $fallback = trim($page->getText());
                    if ($fallback === '') {
                        $section->addText(
                            '(Page ' . ($index + 1) . ': no extractable text)',
                            ['italic' => true, 'color' => '888888', 'size' => 10]
                        );
                        continue;
                    }
                    add_pdf_text_structure($section, $fallback);
                    continue;
                }

                render_pdf_items_to_section($section, $items);
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($docxPath);

        return ['page_count' => max(1, $pageCount)];
    } finally {
        foreach ($tempImages as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}

/**
 * Walk PDF page operators and return ordered text/image items.
 *
 * @param list<string> $tempImages
 * @return list<array{type:string,text?:string,path?:string,width?:float,height?:float}>
 */
function extract_pdf_page_items($page, array &$tempImages): array
{
    $items = [];
    $seenImages = [];

    try {
        $commands = $page->extractDecodedRawData();
    } catch (Throwable $e) {
        $commands = [];
    }

    if (!is_array($commands) || $commands === []) {
        return [];
    }

    $xobjects = [];
    try {
        $xobjects = $page->getXObjects();
    } catch (Throwable $e) {
        $xobjects = [];
    }

    foreach ($commands as $cmd) {
        $op = $cmd['o'] ?? '';

        if ($op === 'Tj' || $op === '\'') {
            $text = pdf_command_text($cmd['c'] ?? '');
            if ($text !== '') {
                $items[] = ['type' => 'text', 'text' => $text];
            }
            continue;
        }

        if ($op === 'TJ') {
            $text = pdf_tj_text($cmd['c'] ?? null);
            if ($text !== '') {
                $items[] = ['type' => 'text', 'text' => $text];
            }
            continue;
        }

        if ($op === 'Do') {
            $name = trim((string) ($cmd['c'] ?? ''));
            $name = ltrim($name, '/');
            if ($name === '' || isset($seenImages[$name])) {
                continue;
            }

            // Skip numeric aliases that duplicate I0/I1 style names when both exist
            if (ctype_digit($name) && isset($xobjects['I' . $name])) {
                continue;
            }

            $obj = $xobjects[$name] ?? null;
            if (!$obj instanceof \Smalot\PdfParser\XObject\Image) {
                // Try alternate alias
                $alt = ctype_digit($name) ? ('I' . $name) : preg_replace('/^I/', '', $name);
                $obj = $xobjects[$alt] ?? null;
            }
            if (!$obj instanceof \Smalot\PdfParser\XObject\Image) {
                continue;
            }

            $path = materialize_pdf_image($obj, $tempImages);
            if ($path === null) {
                continue;
            }

            $seenImages[$name] = true;
            $header = $obj->getHeader();
            $items[] = [
                'type' => 'image',
                'path' => $path,
                'width' => $header && $header->get('Width') ? (float) $header->get('Width')->getContent() : 0,
                'height' => $header && $header->get('Height') ? (float) $header->get('Height')->getContent() : 0,
            ];
        }
    }

    return $items;
}

function pdf_command_text($content): string
{
    if (is_array($content)) {
        return pdf_tj_text($content);
    }
    $text = (string) $content;
    // Strip PDF literal string parentheses if present
    if (strlen($text) >= 2 && $text[0] === '(' && substr($text, -1) === ')') {
        $text = substr($text, 1, -1);
    }
    // Only unescape PDF string escapes — do NOT use stripcslashes (it destroys Windows paths like App\Http)
    $text = preg_replace_callback('/\\\\([nrtbf()\\\\])/', static function (array $m): string {
        return match ($m[1]) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'b' => "\x08",
            'f' => "\x0C",
            '(', ')', '\\' => $m[1],
            default => $m[0],
        };
    }, $text) ?? $text;

    return decode_docx_text(trim($text));
}

/**
 * Reconstruct TJ operator text WITHOUT inserting spaces between glyph chunks.
 */
function pdf_tj_text($content): string
{
    if (!is_array($content)) {
        return pdf_command_text($content);
    }

    $out = '';
    foreach ($content as $part) {
        if (is_array($part)) {
            // Nested TJ piece: ['t'=>'(','o'=>'TJ','c'=>'text'] or number kerning
            if (isset($part['c']) && is_string($part['c'])) {
                $out .= pdf_command_text($part['c']);
            } elseif (isset($part['c']) && is_numeric($part['c'])) {
                // Kerning adjustment — large negative often implies a space in some PDFs
                if ((float) $part['c'] < -100) {
                    $out .= ' ';
                }
            }
            continue;
        }
        if (is_string($part)) {
            $out .= pdf_command_text($part);
        } elseif (is_numeric($part) && (float) $part < -100) {
            $out .= ' ';
        }
    }

    return decode_docx_text($out);
}

/**
 * Render ordered PDF items into a Word section.
 */
function render_pdf_items_to_section($section, array $items): void
{
    $textBuffer = [];

    $flushText = static function () use ($section, &$textBuffer): void {
        if ($textBuffer === []) {
            return;
        }

        $segment = [];
        $segmentIsCode = false;

        $flushSegment = static function () use ($section, &$segment, &$segmentIsCode): void {
            if ($segment === []) {
                return;
            }
            add_pdf_lines_to_section($section, $segment, $segmentIsCode);
            $segment = [];
        };

        foreach ($textBuffer as $line) {
            $isCode = pdf_line_looks_like_code($line);
            $isContinuation = $segmentIsCode && pdf_line_is_code_continuation($line);

            if ($segment === []) {
                $segmentIsCode = $isCode;
                $segment[] = $line;
                continue;
            }

            if ($segmentIsCode) {
                if ($isCode || $isContinuation) {
                    $segment[] = $line;
                } else {
                    $flushSegment();
                    $segmentIsCode = $isCode;
                    $segment[] = $line;
                }
                continue;
            }

            // Currently in prose segment
            if ($isCode) {
                $flushSegment();
                $segmentIsCode = true;
                $segment[] = $line;
            } else {
                $segment[] = $line;
            }
        }
        $flushSegment();
        $textBuffer = [];
    };

    foreach ($items as $item) {
        if (($item['type'] ?? '') === 'image') {
            $flushText();
            add_pdf_image_to_section($section, $item);
            continue;
        }

        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $textBuffer[] = $text;
    }

    $flushText();
}

function pdf_line_looks_like_code(string $line): bool
{
    $t = trim($line);
    if ($t === '') {
        return false;
    }
    if (preg_match('/<\?php|namespace\s+\w|function\s+\w|Route::|return\s+|public function|protected function|private function/', $t)) {
        return true;
    }
    if (preg_match('/^(use\s+[\w\\\\]+|class\s+\w|if\s*\(|\}\s*$|\{\s*$|\$\w+)/', $t)) {
        return true;
    }
    if (preg_match('/[;{}]$/', $t) && preg_match('/[\$\(\)]/', $t)) {
        return true;
    }
    return false;
}

function pdf_line_is_code_continuation(string $line): bool
{
    $t = trim($line);
    if ($t === '') {
        return true;
    }
    // Comments, braces, JSON/code fragments, indented lines
    if (preg_match('/^(\/\/|\/\*|\*|#|\{\s*$|\}\s*$|\]|\[|\'|\"|\))/', $t)) {
        return true;
    }
    if (preg_match('/^[\$\}\{\]\[\'\"]/', $t)) {
        return true;
    }
    if (preg_match('/=>|->|::/', $t)) {
        return true;
    }
    // Still looks technical
    if (preg_match('/[;{}]$/', $t)) {
        return true;
    }
    return false;
}

/**
 * Add lines as either a monospace code table or normal paragraphs.
 */
function add_pdf_lines_to_section($section, array $lines, ?bool $forceCode = null): void
{
    if ($lines === []) {
        return;
    }

    $joined = implode("\n", $lines);
    $looksLikeCode = $forceCode;
    if ($looksLikeCode === null) {
        $codeHits = 0;
        foreach ($lines as $line) {
            if (pdf_line_looks_like_code($line)) {
                $codeHits++;
            }
        }
        $looksLikeCode = $codeHits >= max(1, (int) floor(count($lines) * 0.4))
            || preg_match('/<\?php|namespace\s+|public function|Route::/', $joined) === 1;
    }

    if ($looksLikeCode) {
        $tableStyle = [
            'borderSize' => 4,
            'borderColor' => 'C8CDD2',
            'cellMargin' => 80,
        ];
        $table = $section->addTable($tableStyle);
        $table->addRow();
        $cell = $table->addCell(9500, ['bgColor' => 'F5F6F8']);
        foreach ($lines as $i => $line) {
            $cell->addText(
                decode_docx_text($line),
                ['name' => 'Courier New', 'size' => 8]
            );
            if ($i < count($lines) - 1) {
                $cell->addTextBreak();
            }
        }
        $section->addTextBreak(1);
        return;
    }

    foreach ($lines as $line) {
        $line = decode_docx_text($line);
        if ($line === '') {
            continue;
        }
        $isHeading = mb_strlen($line) <= 80
            && !preg_match('/[.!?]$/', $line)
            && preg_match('/^[A-Z0-9]/', $line)
            && !pdf_line_looks_like_code($line);
        $section->addText(
            $line,
            $isHeading ? ['bold' => true, 'size' => 14] : ['size' => 11],
            ['spaceAfter' => 120]
        );
    }
}

function add_pdf_image_to_section($section, array $item): void
{
    $path = $item['path'] ?? '';
    if ($path === '' || !is_file($path)) {
        return;
    }

    $pxW = (float) ($item['width'] ?? 0);
    $pxH = (float) ($item['height'] ?? 0);
    if ($pxW <= 0 || $pxH <= 0) {
        $info = @getimagesize($path);
        if ($info) {
            $pxW = (float) $info[0];
            $pxH = (float) $info[1];
        }
    }

    // Fit within ~6 inch content width (approx 550px display width in Word)
    $maxW = 520;
    $width = $pxW > 0 ? min($pxW, $maxW) : $maxW;
    $height = ($pxW > 0 && $pxH > 0) ? ($pxH * ($width / $pxW)) : 280;

    try {
        // Own paragraph + inline wrap so text never sits beside the photo
        $section->addTextBreak(1);
        $run = $section->addTextRun([
            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            'spaceBefore' => 160,
            'spaceAfter' => 160,
        ]);
        $run->addImage($path, [
            'width' => (int) round($width),
            'height' => (int) round($height),
            'ratio' => true,
            'wrappingStyle' => \PhpOffice\PhpWord\Style\Image::WRAPPING_STYLE_INLINE,
        ]);
        $section->addTextBreak(1);
    } catch (Throwable $e) {
        // Skip broken images rather than failing the whole conversion
    }
}

/**
 * Fallback when operator stream is unavailable.
 */
function add_pdf_text_structure($section, string $text): void
{
    $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
    $lines = array_map(static fn($l) => trim($l), $lines);
    // Keep empty lines as separators but collapse runs of empties
    $clean = [];
    $prevEmpty = false;
    foreach ($lines as $line) {
        if ($line === '') {
            if (!$prevEmpty) {
                $clean[] = '';
            }
            $prevEmpty = true;
            continue;
        }
        $clean[] = $line;
        $prevEmpty = false;
    }

    $buffer = [];
    foreach ($clean as $line) {
        if ($line === '') {
            if ($buffer !== []) {
                add_pdf_lines_to_section($section, $buffer);
                $buffer = [];
            }
            continue;
        }
        $buffer[] = $line;
    }
    if ($buffer !== []) {
        add_pdf_lines_to_section($section, $buffer);
    }
}

/**
 * Save a PDF XObject image to a temp file PHPWord can embed.
 */
function materialize_pdf_image(\Smalot\PdfParser\XObject\Image $image, array &$tempImages): ?string
{
    $header = $image->getHeader();
    $filter = '';
    if ($header && $header->get('Filter')) {
        $filterObj = $header->get('Filter');
        $filter = method_exists($filterObj, 'getContent') ? (string) $filterObj->getContent() : '';
        // Filter can be an array in some PDFs
        if ($filter === '' && method_exists($filterObj, 'getContent')) {
            $filter = (string) $filterObj;
        }
    }

    $data = $image->getContent();
    if ($data === null || $data === '') {
        return null;
    }

    // JPEG
    if ($filter === 'DCTDecode' || strncmp($data, "\xFF\xD8\xFF", 3) === 0) {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pageshift_pdf_' . bin2hex(random_bytes(8)) . '.jpg';
        if (@file_put_contents($tmp, $data) === false) {
            return null;
        }
        $tempImages[] = $tmp;
        return $tmp;
    }

    // Raw FlateDecode raster → PNG via GD
    if (($filter === 'FlateDecode' || $filter === '') && extension_loaded('gd')) {
        $width = $header && $header->get('Width') ? (int) $header->get('Width')->getContent() : 0;
        $height = $header && $header->get('Height') ? (int) $header->get('Height')->getContent() : 0;
        $bpc = $header && $header->get('BitsPerComponent') ? (int) $header->get('BitsPerComponent')->getContent() : 8;
        if ($width > 0 && $height > 0 && $bpc === 8) {
            $colorSpace = 'DeviceRGB';
            if ($header && $header->get('ColorSpace')) {
                $cs = $header->get('ColorSpace');
                $colorSpace = method_exists($cs, 'getContent') ? (string) $cs->getContent() : 'DeviceRGB';
            }
            $channels = str_contains($colorSpace, 'RGB') ? 3 : (str_contains($colorSpace, 'Gray') ? 1 : 3);
            $expected = $width * $height * $channels;
            if (strlen($data) >= $expected) {
                $img = imagecreatetruecolor($width, $height);
                $i = 0;
                for ($y = 0; $y < $height; $y++) {
                    for ($x = 0; $x < $width; $x++) {
                        if ($channels === 1) {
                            $g = ord($data[$i++]);
                            $color = imagecolorallocate($img, $g, $g, $g);
                        } else {
                            $r = ord($data[$i++]);
                            $g = ord($data[$i++]);
                            $b = ord($data[$i++]);
                            $color = imagecolorallocate($img, $r, $g, $b);
                        }
                        imagesetpixel($img, $x, $y, $color);
                    }
                }
                $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pageshift_pdf_' . bin2hex(random_bytes(8)) . '.png';
                if (@imagepng($img, $tmp)) {
                    imagedestroy($img);
                    $tempImages[] = $tmp;
                    return $tmp;
                }
                imagedestroy($img);
            }
        }
    }

    // Already a PNG?
    if (strncmp($data, "\x89PNG", 4) === 0) {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pageshift_pdf_' . bin2hex(random_bytes(8)) . '.png';
        if (@file_put_contents($tmp, $data) === false) {
            return null;
        }
        $tempImages[] = $tmp;
        return $tmp;
    }

    return null;
}

function add_text_blocks($section, string $text): void
{
    add_pdf_text_structure($section, $text);
}

/**
 * Convert a DOCX file to PDF, preserving text spacing, line breaks, code blocks, and images.
 */
function convert_docx_to_pdf(string $docxPath, string $pdfPath): array
{
    $ext = strtolower(pathinfo($docxPath, PATHINFO_EXTENSION));
    if ($ext === 'doc') {
        throw new RuntimeException('Legacy .doc files are not supported. Please save as .docx and try again.');
    }

    PhpWordSettings::setOutputEscapingEnabled(true);

    $phpWord = IOFactory::load($docxPath);
    $blocks = [];
    $tempImages = [];

    try {
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                foreach (collect_docx_blocks($element, $tempImages) as $block) {
                    $blocks[] = $block;
                }
            }
            $blocks[] = ['type' => 'spacer', 'height' => 4];
        }

        // Drop trailing spacers
        while ($blocks !== [] && ($blocks[array_key_last($blocks)]['type'] ?? '') === 'spacer') {
            array_pop($blocks);
        }

        if ($blocks === []) {
            throw new RuntimeException('Could not extract content from this Word document.');
        }

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor(APP_NAME);
        $pdf->SetTitle(pathinfo($docxPath, PATHINFO_FILENAME));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(16, 16, 16);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();

        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

        foreach ($blocks as $block) {
            $type = $block['type'];

            if ($type === 'spacer') {
                $pdf->Ln((float) ($block['height'] ?? 4));
                continue;
            }

            if ($type === 'text') {
                $text = decode_docx_text($block['text'] ?? '');
                if ($text === '') {
                    continue;
                }
                $size = (float) ($block['size'] ?? 11);
                $style = !empty($block['bold']) ? 'B' : '';
                $pdf->SetFont('dejavusans', $style, $size);
                $pdf->SetX((float) $pdf->getMargins()['left']);
                $pdf->MultiCell(0, max(5, $size * 0.55), $text, 0, 'L', false, 1);
                $pdf->Ln(1.5);
                continue;
            }

            if ($type === 'code') {
                $code = decode_docx_text($block['text'] ?? '');
                if ($code === '') {
                    continue;
                }
                $pdf->SetFillColor(245, 246, 248);
                $pdf->SetDrawColor(200, 205, 210);
                $pdf->SetFont('courier', '', 8.5);
                $pdf->SetX((float) $pdf->getMargins()['left']);
                $pdf->MultiCell(0, 4.4, $code, 1, 'L', true, 1, '', '', true, 0, false, true, 0, 'T', false);
                $pdf->Ln(3);
                continue;
            }

            if ($type === 'image') {
                $imgPath = $block['path'] ?? '';
                if ($imgPath === '' || !is_file($imgPath)) {
                    continue;
                }

                $left = (float) $pdf->getMargins()['left'];
                $maxW = $pageWidth;
                $maxH = 130; // mm
                $pxW = (float) ($block['width'] ?? 0);
                $pxH = (float) ($block['height'] ?? 0);

                // Word stores many widths in pixels/EMU-ish display units; convert roughly to mm
                if ($pxW > 0 && $pxH > 0) {
                    $wMm = $pxW * 0.264583; // px @96dpi → mm
                    $hMm = $pxH * 0.264583;
                } else {
                    $info = @getimagesize($imgPath);
                    $wMm = $info ? ($info[0] * 0.264583) : $maxW;
                    $hMm = $info ? ($info[1] * 0.264583) : 60;
                }

                $scale = min($maxW / max($wMm, 0.1), $maxH / max($hMm, 0.1), 1);
                $drawW = $wMm * $scale;
                $drawH = $hMm * $scale;

                // Always start the image on its own line at the left margin
                $pdf->Ln(3);
                $pdf->SetX($left);

                $remaining = $pdf->getPageHeight() - $pdf->GetY() - $pdf->getMargins()['bottom'];
                if ($drawH > $remaining && $pdf->GetY() > $pdf->getMargins()['top'] + 10) {
                    $pdf->AddPage();
                    $pdf->SetX($left);
                }

                $pdf->Image($imgPath, $left, $pdf->GetY(), $drawW, $drawH, '', '', '', false, 300, '', false, false, 0, false, false, false);
                // Reset X to the left so following text cannot wrap beside the photo
                $pdf->SetXY($left, $pdf->GetY() + $drawH + 5);
                continue;
            }
        }

        $pageCount = max(1, (int) $pdf->getNumPages());
        $pdf->Output($pdfPath, 'F');

        return ['page_count' => $pageCount];
    } finally {
        foreach ($tempImages as $tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}

/**
 * Walk a PHPWord element and return render blocks.
 *
 * @param list<string> $tempImages
 * @return list<array<string, mixed>>
 */
function collect_docx_blocks($element, array &$tempImages): array
{
    if ($element instanceof TextBreak) {
        return [['type' => 'spacer', 'height' => 3]];
    }

    if ($element instanceof Text) {
        $text = decode_docx_text((string) $element->getText());
        if ($text === '') {
            return [];
        }
        $font = $element->getFontStyle();
        $bold = is_object($font) && method_exists($font, 'isBold') ? (bool) $font->isBold() : false;
        $size = 11;
        if (is_object($font) && method_exists($font, 'getSize') && $font->getSize()) {
            $size = (float) $font->getSize();
        }
        return [['type' => 'text', 'text' => $text, 'bold' => $bold, 'size' => $size]];
    }

    if ($element instanceof Image) {
        $path = materialize_docx_image($element, $tempImages);
        if ($path === null) {
            return [];
        }
        $style = $element->getStyle();
        return [[
            'type' => 'image',
            'path' => $path,
            'width' => is_object($style) ? (float) $style->getWidth() : 0,
            'height' => is_object($style) ? (float) $style->getHeight() : 0,
        ]];
    }

    if ($element instanceof Title) {
        $text = decode_docx_text(textrun_plain_text($element));
        if ($text === '') {
            return [];
        }
        return [['type' => 'text', 'text' => $text, 'bold' => true, 'size' => 14]];
    }

    if ($element instanceof TextRun) {
        $blocks = [];
        $buffer = '';
        $bold = false;
        $size = 11;

        foreach ($element->getElements() as $child) {
            if ($child instanceof Image) {
                if ($buffer !== '') {
                    $blocks[] = ['type' => 'text', 'text' => decode_docx_text($buffer), 'bold' => $bold, 'size' => $size];
                    $buffer = '';
                }
                foreach (collect_docx_blocks($child, $tempImages) as $b) {
                    $blocks[] = $b;
                }
                continue;
            }

            if ($child instanceof TextBreak) {
                if ($buffer !== '') {
                    $blocks[] = ['type' => 'text', 'text' => decode_docx_text($buffer), 'bold' => $bold, 'size' => $size];
                    $buffer = '';
                }
                $blocks[] = ['type' => 'spacer', 'height' => 3];
                continue;
            }

            if ($child instanceof Text) {
                $font = $child->getFontStyle();
                if (is_object($font) && method_exists($font, 'isBold')) {
                    $bold = $bold || (bool) $font->isBold();
                }
                if (is_object($font) && method_exists($font, 'getSize') && $font->getSize()) {
                    $size = (float) $font->getSize();
                }
                // IMPORTANT: concatenate runs without inserting spaces
                $buffer .= (string) $child->getText();
                continue;
            }

            $nested = collect_docx_blocks($child, $tempImages);
            if ($nested !== []) {
                if ($buffer !== '') {
                    $blocks[] = ['type' => 'text', 'text' => decode_docx_text($buffer), 'bold' => $bold, 'size' => $size];
                    $buffer = '';
                }
                foreach ($nested as $b) {
                    $blocks[] = $b;
                }
            }
        }

        if ($buffer !== '') {
            $blocks[] = ['type' => 'text', 'text' => decode_docx_text($buffer), 'bold' => $bold, 'size' => $size];
        }

        return $blocks;
    }

    if ($element instanceof ListItem) {
        $text = decode_docx_text(textrun_plain_text($element));
        if ($text === '') {
            return [];
        }
        return [['type' => 'text', 'text' => '• ' . $text, 'bold' => false, 'size' => 11]];
    }

    if ($element instanceof Table) {
        return [build_table_block($element)];
    }

    if (method_exists($element, 'getElements')) {
        $blocks = [];
        foreach ($element->getElements() as $child) {
            foreach (collect_docx_blocks($child, $tempImages) as $b) {
                $blocks[] = $b;
            }
        }
        return $blocks;
    }

    return [];
}

/**
 * Build a code/table block, preserving line breaks inside cells.
 */
function build_table_block(Table $table): array
{
    $lines = [];

    foreach ($table->getRows() as $row) {
        if (!($row instanceof Row)) {
            continue;
        }
        foreach ($row->getCells() as $cell) {
            if (!($cell instanceof Cell)) {
                continue;
            }

            $line = '';
            foreach ($cell->getElements() as $child) {
                if ($child instanceof TextBreak) {
                    $lines[] = rtrim($line);
                    $line = '';
                    continue;
                }

                if ($child instanceof Image) {
                    continue;
                }

                // Each TextRun in code-like tables is typically its own visual line
                if ($child instanceof TextRun) {
                    if ($line !== '') {
                        $lines[] = rtrim($line);
                        $line = '';
                    }
                    $runText = textrun_plain_text($child);
                    // Runs may contain embedded newlines already
                    $parts = preg_split("/\r\n|\n|\r/", $runText) ?: [$runText];
                    foreach ($parts as $idx => $part) {
                        if ($idx < count($parts) - 1) {
                            $lines[] = rtrim($part);
                        } else {
                            $line = $part;
                        }
                    }
                    // Flush completed run as its own line (Word paragraphs → TextRuns)
                    $lines[] = rtrim($line);
                    $line = '';
                    continue;
                }

                if ($child instanceof Text) {
                    $line .= (string) $child->getText();
                    continue;
                }

                $line .= textrun_plain_text($child);
            }

            if ($line !== '') {
                $lines[] = rtrim($line);
            }
        }
    }

    while ($lines !== [] && end($lines) === '') {
        array_pop($lines);
    }

    $text = implode("\n", $lines);
    $looksLikeCode = preg_match('/<\?php|function\s+|Route::|namespace\s+|return\s+|\{|\}/', $text) === 1;

    return [
        'type' => $looksLikeCode ? 'code' : 'text',
        'text' => $text,
        'bold' => false,
        'size' => $looksLikeCode ? 8.5 : 11,
    ];
}

/**
 * Concatenate text inside a run/element WITHOUT inserting spaces between children.
 */
function textrun_plain_text($element): string
{
    if ($element instanceof Text) {
        return (string) $element->getText();
    }

    if ($element instanceof Image) {
        return '';
    }

    if (method_exists($element, 'getElements')) {
        $out = '';
        foreach ($element->getElements() as $child) {
            if ($child instanceof TextBreak) {
                $out .= "\n";
                continue;
            }
            if ($child instanceof Image) {
                continue;
            }
            $out .= textrun_plain_text($child);
        }
        return $out;
    }

    if (method_exists($element, 'getText')) {
        $text = $element->getText();
        return is_string($text) ? $text : '';
    }

    return '';
}

function decode_docx_text(string $text): string
{
    // PHPWord often stores entities like &lt; &gt; &#039; &quot;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Normalize odd spacing artifacts
    $text = str_replace("\xc2\xa0", ' ', $text);
    return $text;
}

/**
 * Copy zip:// image streams to a real temp file TCPDF can read.
 * PNG files with alpha are converted to JPEG when GD is available,
 * because TCPDF requires GD/Imagick for alpha PNGs.
 */
function materialize_docx_image(Image $image, array &$tempImages): ?string
{
    $source = $image->getSource();
    if ($source === '') {
        return null;
    }

    $data = @file_get_contents($source);
    if ($data === false || $data === '') {
        return null;
    }

    $ext = 'png';
    if (preg_match('/\.(jpe?g|gif|png|webp)$/i', $source, $m)) {
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
    } elseif (strncmp($data, "\xFF\xD8\xFF", 3) === 0) {
        $ext = 'jpg';
    } elseif (strncmp($data, 'GIF', 3) === 0) {
        $ext = 'gif';
    }

    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pageshift_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (@file_put_contents($tmp, $data) === false) {
        return null;
    }
    $tempImages[] = $tmp;

    // Convert alpha PNG → JPEG for TCPDF compatibility
    if ($ext === 'png' && extension_loaded('gd')) {
        $img = @imagecreatefrompng($tmp);
        if ($img !== false) {
            $w = imagesx($img);
            $h = imagesy($img);
            $bg = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefilledrectangle($bg, 0, 0, $w, $h, $white);
            imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);
            $jpg = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pageshift_' . bin2hex(random_bytes(8)) . '.jpg';
            if (@imagejpeg($bg, $jpg, 90)) {
                $tempImages[] = $jpg;
                imagedestroy($img);
                imagedestroy($bg);
                return $jpg;
            }
            imagedestroy($img);
            imagedestroy($bg);
        }
    }

    return $tmp;
}

/**
 * @deprecated Kept for any leftover callers; prefer textrun_plain_text / collect_docx_blocks.
 */
function extract_element_text($element): string
{
    return decode_docx_text(textrun_plain_text($element));
}

function get_conversion_for_session(int $id, string $sid): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM conversions WHERE id = ? AND session_id = ? LIMIT 1'
    );
    $stmt->execute([$id, $sid]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_conversions(string $sid, int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT id, original_name, direction, file_size, page_count, status, error_message, created_at, completed_at
         FROM conversions
         WHERE session_id = ?
         ORDER BY created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $sid, PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function output_download_name(array $row): string
{
    $base = pathinfo($row['original_name'], PATHINFO_FILENAME);
    $direction = $row['direction'] ?? 'pdf_to_word';
    $ext = $direction === 'word_to_pdf' ? 'pdf' : 'docx';
    $name = $base . '.' . $ext;
    return preg_replace('/[^\w\s\.\-\(\)\[\]]+/u', '_', $name) ?: ('converted.' . $ext);
}

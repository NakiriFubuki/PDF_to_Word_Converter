<?php
require_once __DIR__ . '/includes/config.php';
$copyrightNotice = APP_COPYRIGHT_NOTICE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PageShift — PDF ↔ Word Converter</title>
  <meta name="description" content="PageShift converts between PDF and Word locally — PDF to Word and Word to PDF.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="noise" aria-hidden="true"></div>

  <header class="site-header">
    <a class="brand" href="index.php" aria-label="PageShift home">
      <span class="brand-mark" aria-hidden="true"></span>
      <span class="brand-text">PageShift</span>
    </a>
    <nav class="site-nav">
      <button type="button" class="nav-link-btn" id="open-manual" aria-haspopup="dialog" aria-controls="manual-modal">
        User Manual
      </button>
      <a href="#how">How It Works</a>
      <a href="#history">History</a>
    </nav>
  </header>

  <div class="manual-modal hidden" id="manual-modal" role="dialog" aria-modal="true" aria-labelledby="manual-title" hidden>
    <div class="manual-backdrop" data-close-manual tabindex="-1"></div>
    <div class="manual-panel" role="document">
      <div class="manual-toolbar">
        <h2 id="manual-title">PageShift User Manual</h2>
        <button type="button" class="manual-close" id="close-manual" aria-label="Close user manual">×</button>
      </div>
      <div class="manual-body">
        <p class="manual-intro">
          PageShift converts files in both directions: <strong>PDF → Word</strong> and <strong>Word → PDF</strong>.
          Processing happens on your local server — files are not sent to a third-party cloud.
        </p>

        <ol class="manual-steps">
          <li>
            <h3>Open the app</h3>
            <p>Go to <code>http://localhost/PDF_to_Word_Converter/</code> (or your server URL). Make sure Apache and MySQL are running in XAMPP.</p>
          </li>
          <li>
            <h3>Choose a direction</h3>
            <p>Use the toggle to select <strong>PDF → Word</strong> or <strong>Word → PDF</strong>.</p>
          </li>
          <li>
            <h3>Select your file</h3>
            <p>Click <strong>Select File</strong>, or drag and drop into the upload area. Max size: <strong>20 MB</strong>. Use <code>.pdf</code> for PDF → Word, or <code>.docx</code> for Word → PDF.</p>
          </li>
          <li>
            <h3>Confirm the file</h3>
            <p>Check the file name and size. Click <strong>×</strong> to clear and choose another file if needed.</p>
          </li>
          <li>
            <h3>Start conversion</h3>
            <p>Click <strong>Convert</strong> and wait while PageShift processes the document.</p>
          </li>
          <li>
            <h3>Download the result</h3>
            <p>Click <strong>Download</strong> to save the converted file (<code>.docx</code> or <code>.pdf</code>).</p>
          </li>
          <li>
            <h3>Review history</h3>
            <p>Scroll to <strong>History</strong> for files from this browser session. Items are cleaned up after about <strong>24 hours</strong>.</p>
          </li>
        </ol>

        <h3 class="manual-section-title">Tips for best results</h3>
        <ul class="manual-tips">
          <li>Text-based PDFs convert best to Word (selectable text in the original PDF).</li>
          <li>Scanned or image-only PDFs usually cannot be converted (no OCR).</li>
          <li>Use modern <code>.docx</code> files for Word → PDF. Legacy <code>.doc</code> is not supported.</li>
          <li>Complex layouts may not match the original exactly; conversion focuses on readable text.</li>
        </ul>

        <h3 class="manual-section-title">Troubleshooting</h3>
        <ul class="manual-tips">
          <li><strong>Upload fails:</strong> Confirm file type matches the selected mode and size is under 20 MB.</li>
          <li><strong>Conversion error:</strong> Try a text-based PDF or a simpler .docx file.</li>
          <li><strong>Download missing:</strong> The file may have expired or belong to another session. Convert again.</li>
        </ul>

        <div class="manual-copyright">
          <p><?= htmlspecialchars($copyrightNotice, ENT_QUOTES, 'UTF-8') ?></p>
          <p>PageShift / PDF_to_Word_Converter. Unauthorized copying or redistribution of this project without permission is prohibited.</p>
        </div>
      </div>
    </div>
  </div>

  <main>
    <section class="hero" id="convert">
      <div class="hero-copy">
        <p class="brand-hero">PageShift</p>
        <h1 id="hero-title">Convert PDF ↔ Word both ways</h1>
        <p class="lede" id="hero-lede">Pick a direction, drop a file, and convert in seconds — processed locally on your server.</p>

        <div class="mode-toggle" role="group" aria-label="Conversion direction">
          <button type="button" class="mode-btn is-active" data-mode="pdf_to_word" id="mode-pdf-word">
            PDF → Word
          </button>
          <button type="button" class="mode-btn" data-mode="word_to_pdf" id="mode-word-pdf">
            Word → PDF
          </button>
        </div>

        <div class="cta-group">
          <label class="btn btn-primary" for="file-input">
            <span id="select-label">Select PDF</span>
            <input type="file" id="file-input" accept="application/pdf,.pdf" hidden>
          </label>
          <button type="button" class="btn btn-ghost" id="browse-hint" tabindex="-1">Or drop it in the area below</button>
        </div>
      </div>

      <div class="hero-stage" aria-hidden="true">
        <div class="doc-plane" id="doc-plane">
          <div class="doc doc-pdf" id="doc-left">
            <span class="doc-badge" id="badge-left">PDF</span>
            <div class="doc-lines">
              <span></span><span></span><span></span><span></span>
            </div>
          </div>
          <div class="doc doc-mid">
            <div class="doc-lines">
              <span></span><span></span><span></span>
            </div>
          </div>
          <div class="doc doc-word" id="doc-right">
            <span class="doc-badge" id="badge-right">DOCX</span>
            <div class="doc-lines">
              <span></span><span></span><span></span><span></span>
            </div>
          </div>
          <div class="shift-arrow">
            <svg viewBox="0 0 64 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M2 12h52M42 4l12 8-12 8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>
      </div>
    </section>

    <section class="drop-section">
      <form id="upload-form" class="dropzone" aria-label="Upload file">
        <input type="hidden" name="direction" id="direction-input" value="pdf_to_word">
        <div class="dropzone-inner" id="drop-zone">
          <div class="drop-icon" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none">
              <rect x="10" y="6" width="28" height="36" rx="3" stroke="currentColor" stroke-width="2"/>
              <path d="M18 18h12M18 24h12M18 30h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <circle cx="34" cy="34" r="10" fill="var(--ink)" stroke="var(--paper)"/>
              <path d="M34 29v7M30.5 33.5 34 37l3.5-3.5" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <p class="drop-title" id="drop-title">Drop your PDF here</p>
          <p class="drop-sub" id="drop-sub">Max 20 MB · Best results with text-based PDFs</p>

          <div class="file-chip hidden" id="file-chip">
            <span class="file-chip-name" id="file-name"></span>
            <span class="file-chip-size" id="file-size"></span>
            <button type="button" class="file-chip-remove" id="clear-file" aria-label="Clear file">×</button>
          </div>

          <button type="submit" class="btn btn-convert hidden" id="convert-btn" disabled>
            <span class="btn-label">Convert</span>
            <span class="btn-spinner" aria-hidden="true"></span>
          </button>
        </div>

        <div class="progress-panel hidden" id="progress-panel">
          <div class="progress-track">
            <div class="progress-bar" id="progress-bar"></div>
          </div>
          <p class="progress-text" id="progress-text">Converting…</p>
        </div>

        <div class="result-panel hidden" id="result-panel">
          <div class="result-check" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none">
              <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2"/>
              <path d="M14 24.5 21 31.5 34 17" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h2>Conversion complete</h2>
          <p id="result-meta"></p>
          <div class="result-actions">
            <a class="btn btn-primary" id="download-link" href="#">Download</a>
            <button type="button" class="btn btn-ghost" id="convert-another">Convert Another</button>
          </div>
        </div>

        <div class="error-panel hidden" id="error-panel">
          <p id="error-text"></p>
          <button type="button" class="btn btn-ghost" id="error-retry">Try Again</button>
        </div>
      </form>
    </section>

    <section class="how" id="how">
      <h2>Three simple steps</h2>
      <p class="section-lede">No account required — convert in either direction.</p>
      <ol class="steps">
        <li>
          <span class="step-num">01</span>
          <h3>Choose direction</h3>
          <p>Switch between PDF → Word and Word → PDF.</p>
        </li>
        <li>
          <span class="step-num">02</span>
          <h3>Upload &amp; convert</h3>
          <p>Drop your file and let PageShift extract and rebuild the document.</p>
        </li>
        <li>
          <span class="step-num">03</span>
          <h3>Download</h3>
          <p>Save the result and open it in your preferred editor or reader.</p>
        </li>
      </ol>
    </section>

    <section class="history" id="history">
      <div class="history-head">
        <h2>History</h2>
        <p class="section-lede">Shows files from this browser session only. Files are cleaned up after 24 hours.</p>
      </div>
      <div class="history-list" id="history-list">
        <p class="history-empty" id="history-empty">No conversions yet. Your first file will appear here.</p>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="footer-left">
      <span class="brand-text">PageShift</span>
      <span>Local PDF ↔ Word converter</span>
    </div>
    <p class="footer-copyright"><?= htmlspecialchars($copyrightNotice, ENT_QUOTES, 'UTF-8') ?></p>
  </footer>

  <script src="assets/js/app.js" defer></script>
</body>
</html>

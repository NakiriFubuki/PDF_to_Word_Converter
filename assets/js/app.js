(() => {
  const form = document.getElementById("upload-form");
  const dropZone = document.getElementById("drop-zone");
  const fileInput = document.getElementById("file-input");
  const fileChip = document.getElementById("file-chip");
  const fileName = document.getElementById("file-name");
  const fileSize = document.getElementById("file-size");
  const clearFile = document.getElementById("clear-file");
  const convertBtn = document.getElementById("convert-btn");
  const progressPanel = document.getElementById("progress-panel");
  const progressText = document.getElementById("progress-text");
  const resultPanel = document.getElementById("result-panel");
  const resultMeta = document.getElementById("result-meta");
  const downloadLink = document.getElementById("download-link");
  const convertAnother = document.getElementById("convert-another");
  const errorPanel = document.getElementById("error-panel");
  const errorText = document.getElementById("error-text");
  const errorRetry = document.getElementById("error-retry");
  const historyList = document.getElementById("history-list");
  const historyEmpty = document.getElementById("history-empty");
  const browseHint = document.getElementById("browse-hint");
  const directionInput = document.getElementById("direction-input");
  const selectLabel = document.getElementById("select-label");
  const dropTitle = document.getElementById("drop-title");
  const dropSub = document.getElementById("drop-sub");
  const heroTitle = document.getElementById("hero-title");
  const heroLede = document.getElementById("hero-lede");
  const badgeLeft = document.getElementById("badge-left");
  const badgeRight = document.getElementById("badge-right");
  const docLeft = document.getElementById("doc-left");
  const docRight = document.getElementById("doc-right");
  const modeButtons = document.querySelectorAll(".mode-btn");

  let selectedFile = null;
  let direction = "pdf_to_word";
  const MAX_SIZE = 20 * 1024 * 1024;

  const modeCopy = {
    pdf_to_word: {
      select: "Select PDF",
      accept: "application/pdf,.pdf",
      dropTitle: "Drop your PDF here",
      dropSub: "Max 20 MB · Best results with text-based PDFs",
      heroTitle: "Turn PDFs into editable Word files",
      heroLede:
        "Drop a PDF and convert in seconds. Text is processed locally — never sent to a third-party cloud.",
      left: "PDF",
      right: "DOCX",
      leftClass: "doc-pdf",
      rightClass: "doc-word",
    },
    word_to_pdf: {
      select: "Select Word",
      accept:
        ".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      dropTitle: "Drop your Word file here",
      dropSub: "Max 20 MB · Use .docx (modern Word format)",
      heroTitle: "Turn Word documents into PDF",
      heroLede:
        "Drop a .docx file and convert in seconds. Processing stays on your local server.",
      left: "DOCX",
      right: "PDF",
      leftClass: "doc-word",
      rightClass: "doc-pdf",
    },
  };

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / 1048576).toFixed(2) + " MB";
  }

  function show(el) {
    el.classList.remove("hidden");
  }

  function hide(el) {
    el.classList.add("hidden");
  }

  function resetPanels() {
    hide(progressPanel);
    hide(resultPanel);
    hide(errorPanel);
    show(dropZone);
  }

  function setMode(next) {
    if (!modeCopy[next]) return;
    direction = next;
    directionInput.value = next;

    modeButtons.forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.mode === next);
    });

    const copy = modeCopy[next];
    selectLabel.textContent = copy.select;
    fileInput.accept = copy.accept;
    dropTitle.textContent = copy.dropTitle;
    dropSub.textContent = copy.dropSub;
    heroTitle.textContent = copy.heroTitle;
    heroLede.textContent = copy.heroLede;
    badgeLeft.textContent = copy.left;
    badgeRight.textContent = copy.right;
    docLeft.classList.remove("doc-pdf", "doc-word");
    docRight.classList.remove("doc-pdf", "doc-word");
    docLeft.classList.add(copy.leftClass);
    docRight.classList.add(copy.rightClass);

    clearSelection();
  }

  function isAllowedFile(file) {
    const name = file.name || "";
    if (direction === "pdf_to_word") {
      return file.type === "application/pdf" || /\.pdf$/i.test(name);
    }
    return (
      file.type ===
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document" ||
      /\.docx$/i.test(name)
    );
  }

  function setFile(file) {
    if (!file) return;

    if (!isAllowedFile(file)) {
      showError(
        direction === "pdf_to_word"
          ? "Please select a PDF file."
          : "Please select a Word (.docx) file."
      );
      return;
    }

    if (file.size > MAX_SIZE) {
      showError("File must be 20 MB or smaller.");
      return;
    }

    selectedFile = file;
    fileName.textContent = file.name;
    fileSize.textContent = formatBytes(file.size);
    show(fileChip);
    show(convertBtn);
    convertBtn.disabled = false;
    resetPanels();
    hide(errorPanel);
  }

  function clearSelection() {
    selectedFile = null;
    fileInput.value = "";
    hide(fileChip);
    hide(convertBtn);
    convertBtn.disabled = true;
    resetPanels();
  }

  function showError(message) {
    hide(progressPanel);
    hide(resultPanel);
    show(dropZone);
    errorText.textContent = message;
    show(errorPanel);
  }

  function showResult(data) {
    hide(dropZone);
    hide(progressPanel);
    hide(errorPanel);
    const pages = data.page_count ? ` · ${data.page_count} page(s)` : "";
    const out = data.output_name ? ` → ${data.output_name}` : "";
    resultMeta.textContent = `${data.original_name}${out}${pages} · ${data.file_size_label}`;
    downloadLink.href = data.download_url;
    downloadLink.textContent =
      data.direction === "word_to_pdf" ? "Download PDF" : "Download Word";
    show(resultPanel);
  }

  async function convert() {
    if (!selectedFile) return;

    const fd = new FormData();
    fd.append("file", selectedFile);
    fd.append("direction", direction);

    hide(resultPanel);
    hide(errorPanel);
    show(dropZone);
    show(progressPanel);
    progressText.textContent = "Uploading and converting…";
    convertBtn.classList.add("is-loading");
    convertBtn.disabled = true;

    try {
      const res = await fetch("api/convert.php", {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });

      const raw = await res.text();
      let payload;
      try {
        payload = JSON.parse(raw);
      } catch (_) {
        throw new Error("Unexpected server response. Please try again.");
      }

      if (!payload.ok) {
        throw new Error(payload.error || "Conversion failed");
      }

      showResult(payload.conversion);
      loadHistory();
    } catch (err) {
      showError(err.message || "Network error. Please try again.");
    } finally {
      convertBtn.classList.remove("is-loading");
      convertBtn.disabled = !selectedFile;
    }
  }

  function statusLabel(status) {
    const map = {
      completed: "Completed",
      failed: "Failed",
      processing: "Processing",
      pending: "Pending",
    };
    return map[status] || status;
  }

  async function loadHistory() {
    try {
      const res = await fetch("api/history.php", { credentials: "same-origin" });
      const payload = await res.json();
      if (!payload.ok) return;

      const items = payload.items || [];
      historyList.querySelectorAll(".history-item").forEach((n) => n.remove());

      if (items.length === 0) {
        show(historyEmpty);
        return;
      }

      hide(historyEmpty);

      items.forEach((item) => {
        const row = document.createElement("div");
        row.className = "history-item";

        const name = document.createElement("div");
        name.className = "history-name";
        name.textContent = item.original_name;

        const meta = document.createElement("div");
        meta.className = "history-meta";
        const pages = item.page_count ? `${item.page_count} page(s) · ` : "";
        const dir = item.direction_label ? `${item.direction_label} · ` : "";
        meta.textContent = `${dir}${pages}${item.file_size_label} · ${item.created_at}`;

        const action = document.createElement("div");
        if (item.download_url) {
          const a = document.createElement("a");
          a.className = "history-dl";
          a.href = item.download_url;
          a.textContent = "Download";
          action.appendChild(a);
        } else {
          const s = document.createElement("span");
          s.className = `history-status ${item.status}`;
          s.textContent = statusLabel(item.status);
          action.appendChild(s);
        }

        row.append(name, meta, action);
        historyList.appendChild(row);
      });
    } catch (_) {
      // Silent — history is secondary
    }
  }

  modeButtons.forEach((btn) => {
    btn.addEventListener("click", () => setMode(btn.dataset.mode));
  });

  ["dragenter", "dragover"].forEach((evt) => {
    dropZone.addEventListener(evt, (e) => {
      e.preventDefault();
      e.stopPropagation();
      form.classList.add("is-dragover");
    });
  });

  ["dragleave", "drop"].forEach((evt) => {
    dropZone.addEventListener(evt, (e) => {
      e.preventDefault();
      e.stopPropagation();
      form.classList.remove("is-dragover");
    });
  });

  dropZone.addEventListener("drop", (e) => {
    const file = e.dataTransfer?.files?.[0];
    if (file) setFile(file);
  });

  form.addEventListener("dragover", (e) => e.preventDefault());
  form.addEventListener("drop", (e) => {
    e.preventDefault();
    const file = e.dataTransfer?.files?.[0];
    if (file) setFile(file);
  });

  fileInput.addEventListener("change", () => {
    if (fileInput.files?.[0]) setFile(fileInput.files[0]);
  });

  browseHint?.addEventListener("click", () => fileInput.click());
  clearFile.addEventListener("click", clearSelection);
  convertAnother.addEventListener("click", clearSelection);
  errorRetry.addEventListener("click", () => {
    hide(errorPanel);
    if (selectedFile) convert();
  });

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    convert();
  });

  const manualModal = document.getElementById("manual-modal");
  const openManual = document.getElementById("open-manual");
  const closeManual = document.getElementById("close-manual");

  function openManualModal() {
    if (!manualModal) return;
    manualModal.hidden = false;
    manualModal.classList.remove("hidden");
    document.body.classList.add("manual-open");
    closeManual?.focus();
  }

  function closeManualModal() {
    if (!manualModal) return;
    manualModal.hidden = true;
    manualModal.classList.add("hidden");
    document.body.classList.remove("manual-open");
    openManual?.focus();
  }

  openManual?.addEventListener("click", openManualModal);
  closeManual?.addEventListener("click", closeManualModal);
  manualModal?.querySelectorAll("[data-close-manual]").forEach((el) => {
    el.addEventListener("click", closeManualModal);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && manualModal && !manualModal.hidden) {
      closeManualModal();
    }
  });

  setMode("pdf_to_word");
  loadHistory();

  const shot = new URLSearchParams(window.location.search).get("shot");
  if (shot === "word") {
    setMode("word_to_pdf");
  } else if (shot === "manual") {
    openManualModal();
  } else if (shot === "history") {
    document.getElementById("history")?.scrollIntoView();
  }
})();

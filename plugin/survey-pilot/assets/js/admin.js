(() => {
  function initHardCharacterMax() {
    document.querySelectorAll("[data-sp-maxlength]").forEach((el) => {
      const max = parseInt(el.getAttribute("data-sp-maxlength") || "", 10);
      if (!Number.isFinite(max) || max <= 0) return;
      const tag = el.tagName.toLowerCase();
      const type = (el.getAttribute("type") || "").toLowerCase();
      const supportsMaxlength =
        tag === "textarea" ||
        ["text", "search", "password", "email", "url", "tel"].includes(type);
      if (supportsMaxlength) {
        el.setAttribute("maxlength", String(max));
      }
    });

    document.addEventListener("input", (e) => {
      const target = e.target;
      if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement)) return;
      const max = parseInt(target.getAttribute("data-sp-maxlength") || "", 10);
      if (!Number.isFinite(max) || max <= 0) return;
      if (target.value.length > max) {
        target.value = target.value.slice(0, max);
      }
    });
  }

  // Export modal
  const exportModal      = document.getElementById("sp-export-modal");
  const exportBtns       = document.querySelectorAll("[data-sp-export-survey-id]");

  if (exportModal && exportBtns.length > 0) {
    const exportOverlay      = exportModal.querySelector(".sp-modal-overlay");
    const exportDialog       = exportModal.querySelector(".sp-modal-dialog");
    const exportCancelBtn    = exportModal.querySelector("[data-sp-export-cancel]");
    const exportDownloadBtn  = document.getElementById("sp-export-download-btn");
    const exportSurveyName   = document.getElementById("sp-export-survey-name");
    const exportNoResponses  = document.getElementById("sp-export-no-responses");

    const downloadIconHtml = exportDownloadBtn?.querySelector("img")?.outerHTML ?? "";
    let activeSurveyId = null;

    function openExportModal(surveyId, surveyTitle, responseCount) {
      activeSurveyId = surveyId;
      if (exportSurveyName) exportSurveyName.textContent = surveyTitle || "this survey";

      const hasResponses = responseCount > 0;
      if (exportNoResponses) exportNoResponses.style.display = hasResponses ? "none" : "block";
      if (exportDownloadBtn) {
        exportDownloadBtn.disabled = !hasResponses;
      }

      exportModal.classList.add("is-open");
      exportModal.setAttribute("aria-hidden", "false");
      exportDialog?.focus?.();
    }

    function closeExportModal() {
      exportModal.classList.remove("is-open");
      exportModal.setAttribute("aria-hidden", "true");
      activeSurveyId = null;
    }

    exportBtns.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const id    = btn.getAttribute("data-sp-export-survey-id");
        const title = btn.getAttribute("data-sp-survey-title") || "";
        const count = parseInt(btn.getAttribute("data-sp-response-count") || "0", 10);
        openExportModal(id, title, count);
      });
    });

    let activeAbortController = null;
    let exportCancelled = false;

    function cancelExport() {
      exportCancelled = true;
      activeAbortController?.abort();
      activeAbortController = null;
      if (exportDownloadBtn) {
        exportDownloadBtn.innerHTML = downloadIconHtml + " Download CSV";
        exportDownloadBtn.disabled = false;
        exportDownloadBtn.classList.remove("sp-btn-loading");
      }
    }

    exportOverlay?.addEventListener("click", () => {
      cancelExport();
      closeExportModal();
    });
    exportCancelBtn?.addEventListener("click", (e) => {
      e.preventDefault();
      cancelExport();
      closeExportModal();
    });

    exportDownloadBtn?.addEventListener("click", () => {
      if (!activeSurveyId) return;
      const resetBtn = () => {
        exportDownloadBtn.innerHTML = downloadIconHtml + " Download CSV";
        exportDownloadBtn.disabled = false;
        exportDownloadBtn.classList.remove("sp-btn-loading");
        activeAbortController = null;
      };

      exportCancelled = false;
      activeAbortController = new AbortController();

      exportDownloadBtn.disabled = true;
      exportDownloadBtn.classList.add("sp-btn-loading");
      exportDownloadBtn.innerHTML = "Preparing…";

      const data = new FormData();
      data.append("action",    "sp_export_survey_csv");
      data.append("nonce",     spAdmin.exportNonce);
      data.append("survey_id", activeSurveyId);

      fetch(spAdmin.ajaxUrl, { method: "POST", body: data, signal: activeAbortController.signal })
        .then((r) => r.json())
        .then((res) => {
          if (exportCancelled) return;
          resetBtn();

          if (!res.success) {
            alert("Export failed: " + (res.data || "Unknown error"));
            return;
          }

          const blob = new Blob([res.data.csv], { type: "text/csv;charset=utf-8;" });
          const url  = URL.createObjectURL(blob);
          const a    = document.createElement("a");
          a.href     = url;
          a.download = res.data.filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
          closeExportModal();
        })
        .catch((err) => {
          if (err.name === "AbortError" || exportCancelled) return;
          resetBtn();
          alert("Export failed. Please try again.");
        });
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && exportModal.classList.contains("is-open")) {
        cancelExport();
        closeExportModal();
      }
    });
  }

  // Duplicate blocked modal
  const dupBlockedModal   = document.getElementById("sp-duplicate-blocked-modal");
  const dupBlockedOkBtn   = document.getElementById("sp-dup-blocked-ok");
  const dupBlockedNameEl  = document.getElementById("sp-dup-blocked-name");
  const dupBtns           = document.querySelectorAll(".sp-duplicate-btn");

  if (dupBlockedModal && dupBtns.length > 0) {
    const dupOverlay = dupBlockedModal.querySelector(".sp-modal-overlay");
    const dupDialog  = dupBlockedModal.querySelector(".sp-modal-dialog");
    const titles     = (spAdmin.surveyTitles || []).map((t) => t.toLowerCase());

    function openDupBlockedModal(copyTitle) {
      if (dupBlockedNameEl) dupBlockedNameEl.textContent = copyTitle;
      dupBlockedModal.classList.add("is-open");
      dupBlockedModal.setAttribute("aria-hidden", "false");
      dupDialog?.focus?.();
    }

    function closeDupBlockedModal() {
      dupBlockedModal.classList.remove("is-open");
      dupBlockedModal.setAttribute("aria-hidden", "true");
    }

    dupBtns.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const title     = btn.getAttribute("data-survey-title") || "";
        const copyTitle = title + " (Copy)";
        if (titles.includes(copyTitle.toLowerCase())) {
          e.preventDefault();
          e.stopPropagation();
          openDupBlockedModal(copyTitle);
        }
      });
    });

    dupOverlay?.addEventListener("click", closeDupBlockedModal);
    dupBlockedOkBtn?.addEventListener("click", (e) => {
      e.preventDefault();
      closeDupBlockedModal();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && dupBlockedModal.classList.contains("is-open")) closeDupBlockedModal();
    });
  }

  // Delete confirmation modal
  const openButtons = document.querySelectorAll("[data-sp-delete-url]");
  const modal = document.getElementById("sp-delete-modal");

  if (modal && openButtons.length > 0) {
    const overlay = modal.querySelector(".sp-modal-overlay");
    const dialog = modal.querySelector(".sp-modal-dialog");
    const cancelBtn = modal.querySelector("[data-sp-cancel]");
    const confirmBtn = modal.querySelector("[data-sp-confirm]");

    let deleteUrl = null;
    const surveyNameEl = document.getElementById("sp-delete-survey-name");

    function openModal(url, surveyTitle) {
      deleteUrl = url;
      if (surveyNameEl) surveyNameEl.textContent = surveyTitle || "this survey";
      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      dialog?.focus?.();
    }

    function closeModal() {
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      deleteUrl = null;
    }

    openButtons.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const url = btn.getAttribute("data-sp-delete-url");
        const title = btn.getAttribute("data-sp-survey-title") || "";
        if (!url) return;
        openModal(url, title);
      });
    });

    overlay?.addEventListener("click", closeModal);
    cancelBtn?.addEventListener("click", (e) => {
      e.preventDefault();
      closeModal();
    });

    confirmBtn?.addEventListener("click", (e) => {
      e.preventDefault();
      if (deleteUrl) window.location.href = deleteUrl;
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeModal();
    });
  }

  // Make entire survey cards clickable on the dashboard (except buttons/links)
  function initDashboardCards() {
    const cards = document.querySelectorAll(".sp-survey-card[data-edit-url]");
    if (!cards.length) return;

    const openMenus = [];

    function closeAllMenus(except) {
      openMenus.forEach((menu) => {
        if (menu !== except) {
          menu.classList.remove("is-open");
          const toggleEl = menu.closest(".sp-survey-card")?.querySelector(
            ".sp-survey-menu-toggle"
          );
          if (toggleEl) toggleEl.setAttribute("aria-expanded", "false");
        }
      });
    }

    cards.forEach((card) => {
      const url = card.getAttribute("data-edit-url");
      if (!url) return;

      card.addEventListener("click", (e) => {
        const target = e.target;
        if (
          target.closest("a") ||
          target.closest("button") ||
          target.closest(".sp-survey-desc") ||
          target.closest(".sp-survey-response-count") ||
          target.closest(".sp-survey-shortcode-box")
        ) {
          return;
        }
        window.location.href = url;
      });

      [
        card.querySelector(".sp-survey-desc"),
        card.querySelector(".sp-survey-response-count"),
        card.querySelector(".sp-survey-shortcode-box"),
      ].forEach((el) => {
        if (!el) return;
        el.addEventListener("mouseenter", () => card.classList.add("sp-nohover"));
        el.addEventListener("mouseleave", () => card.classList.remove("sp-nohover"));
      });

      const reorder = card.querySelector(".sp-survey-reorder-actions");
      if (reorder) {
        reorder.querySelectorAll(".sp-survey-move-btn").forEach((moveBtn) => {
          moveBtn.addEventListener("mouseenter", () => {
            if (!moveBtn.disabled) card.classList.add("sp-nohover");
          });
          moveBtn.addEventListener("mouseleave", () => {
            card.classList.remove("sp-nohover");
          });
        });
      }

      const actions = card.querySelector(".sp-survey-actions");
      if (actions) {
        actions.addEventListener("mouseenter", () => {
          card.classList.add("sp-nohover");
        });
        actions.addEventListener("mouseleave", () => {
          card.classList.remove("sp-nohover");
        });

        const toggle = actions.querySelector(".sp-survey-menu-toggle");
        const menu = actions.querySelector(".sp-survey-menu");

        if (menu) {
          openMenus.push(menu);
        }

        if (toggle && menu) {
          toggle.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = menu.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            if (isOpen) {
              closeAllMenus(menu);
            }
          });
        }
      }
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest(".sp-survey-card")) {
        closeAllMenus(null);
      }
    });

    document.querySelectorAll(".sp-copy-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const shortcode = btn.getAttribute("data-shortcode");
        if (!shortcode) return;
        btn.classList.add("sp-copied");
        navigator.clipboard.writeText(shortcode);
      });

      btn.addEventListener("mouseleave", () => {
        btn.classList.remove("sp-copied");
      });
    });
  }

  // Auto-expanding textareas
  function initAutoExpand(scope) {
    const textareas =
      scope instanceof NodeList || Array.isArray(scope)
        ? scope
        : document.querySelectorAll(".sp-auto-expand");

    textareas.forEach((ta) => {
      const el = ta;
      const resize = () => {
        el.style.height = "auto";
        el.style.height = `${el.scrollHeight}px`;
      };
      el.addEventListener("input", resize);
      resize();
    });
  }

  // Question builder for create/edit survey page
  function initQuestionBuilder() {
    const builder = document.getElementById("sp-question-builder");
    if (!builder) return;

    const structureLocked = builder.getAttribute("data-sp-structure-locked") === "1";

    const questionsList = document.getElementById("sp-questions-list");
    const addQuestionBtn = document.getElementById("sp-add-question");
    const addTextBtn = document.getElementById("sp-add-text");
    const addPageBreakBtn = document.getElementById("sp-add-page-break");
    const templateEl = document.getElementById("sp-question-template");
    const textTemplateEl = document.getElementById("sp-text-card-template");
    const layoutInput = document.getElementById("sp_survey_layout");

    if (!questionsList || !addQuestionBtn || !templateEl) return;

    let nextIndex = parseInt(builder.getAttribute("data-next-index") || "0", 10);
    if (Number.isNaN(nextIndex)) nextIndex = 0;

    function mergeAdjacentPageBreaks() {
      let el = questionsList.firstElementChild;
      while (el) {
        const next = el.nextElementSibling;
        if (
          el.classList.contains("sp-page-break") &&
          next &&
          next.classList.contains("sp-page-break")
        ) {
          const hiKeep = el.querySelector(".sp-page-header-input");
          const hiDrop = next.querySelector(".sp-page-header-input");
          if (hiKeep && hiDrop) hiKeep.value = hiDrop.value;
          next.remove();
          continue;
        }
        el = next;
      }
    }

    function mergeLeadingPageBreaksIntoPage1() {
      const p1Input = document.querySelector("#sp-page-1-header .sp-page-header-input");
      let first = questionsList.firstElementChild;
      while (first && first.classList.contains("sp-page-break")) {
        const hi = first.querySelector(".sp-page-header-input");
        if (p1Input && hi) p1Input.value = hi.value;
        first.remove();
        first = questionsList.firstElementChild;
      }
    }

    function cleanupPageBreaks() {
      const hasQuestions = !!questionsList.querySelector(".sp-question-card");
      if (!hasQuestions) {
        questionsList.querySelectorAll(".sp-page-break").forEach((pb) => pb.remove());
        return;
      }

      mergeAdjacentPageBreaks();
      mergeLeadingPageBreaksIntoPage1();
    }

    function followMovedBlock(block, beforeTop) {
      if (!block || !block.isConnected) return;
      requestAnimationFrame(() => {
        const afterTop = block.getBoundingClientRect().top;
        if (typeof beforeTop === "number") {
          const delta = afterTop - beforeTop;
          if (Math.abs(delta) > 1) {
            window.scrollBy({ top: delta, behavior: "smooth" });
            return;
          }
        }
        block.scrollIntoView({
          block: "nearest",
          behavior: "smooth",
          inline: "nearest",
        });
      });
    }

    function attachReorderHandlers(block) {
      if (block.dataset.spReorderBound === "1") return;
      block.dataset.spReorderBound = "1";
      const up = block.querySelector(".sp-move-up");
      const down = block.querySelector(".sp-move-down");
      if (up) {
        up.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          moveBlockUp(block);
        });
      }
      if (down) {
        down.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          moveBlockDown(block);
        });
      }
    }

    function updateReorderButtonStates() {
      const items = Array.from(questionsList.children).filter(
        (n) =>
          n.classList.contains("sp-question-card") ||
          n.classList.contains("sp-page-break") ||
          n.classList.contains("sp-text-card")
      );
      items.forEach((block, i) => {
        const up = block.querySelector(".sp-move-up");
        const down = block.querySelector(".sp-move-down");
        if (!up || !down) return;
        if (
          structureLocked &&
          (block.classList.contains("sp-question-card") || block.classList.contains("sp-page-break"))
        ) {
          up.disabled = true;
          down.disabled = true;
          return;
        }
        const isFirst = i === 0;
        const isLast = i === items.length - 1;
        up.disabled = isFirst && !block.classList.contains("sp-page-break");
        down.disabled = isLast;
      });
    }

    function moveBlockUp(block) {
      if (block.parentNode !== questionsList) return;
      if (
        structureLocked &&
        (block.classList.contains("sp-question-card") || block.classList.contains("sp-page-break"))
      ) {
        return;
      }
      if (questionsList.firstElementChild === block) {
        if (block.classList.contains("sp-page-break")) {
          const p1Input = document.querySelector("#sp-page-1-header .sp-page-header-input");
          const hi = block.querySelector(".sp-page-header-input");
          if (p1Input && hi) p1Input.value = hi.value;
          block.remove();
          refreshQuestionNumbers();
        }
        return;
      }
      const beforeTop = block.getBoundingClientRect().top;
      const prev = block.previousElementSibling;
      questionsList.insertBefore(block, prev);
      refreshQuestionNumbers();
      followMovedBlock(block, beforeTop);
    }

    function moveBlockDown(block) {
      if (block.parentNode !== questionsList) return;
      if (
        structureLocked &&
        (block.classList.contains("sp-question-card") || block.classList.contains("sp-page-break"))
      ) {
        return;
      }
      const beforeTop = block.getBoundingClientRect().top;
      const next = block.nextElementSibling;
      if (!next) return;
      questionsList.insertBefore(next, block);
      refreshQuestionNumbers();
      followMovedBlock(block, beforeTop);
    }

    function refreshQuestionNumbers() {
      cleanupPageBreaks();
      const cards = Array.from(questionsList.querySelectorAll(".sp-question-card"));
      cards.forEach((card, idx) => {
        const numEl = card.querySelector(".sp-question-label .sp-question-number");
        if (numEl) numEl.textContent = String(idx + 1);
      });
      refreshPageNumbers();
      updateAddPageBreakBtn();
      // Hide the "at least one question" error once a question exists
      if (cards.length > 0) {
        const questionsError = document.getElementById("sp-questions-error");
        if (questionsError) questionsError.style.display = "none";
      }
      renumberQuestionCardsInListOrder();
      updateReorderButtonStates();
    }

    function refreshPageNumbers() {
      let currentPage = 1;
      Array.from(questionsList.children).forEach((el) => {
        if (el.classList.contains("sp-page-break")) {
          currentPage++;
          const headerInput = el.querySelector(".sp-page-header-input");
          if (headerInput) headerInput.name = `sp_page_headers[${currentPage}]`;
          const pageNumDisplay = el.querySelector(".sp-page-number-display");
          if (pageNumDisplay) pageNumDisplay.textContent = String(currentPage);
        }
      });
    }

    function buildLayoutFromDom() {
      const out = [];
      const p1Input = document.querySelector("#sp-page-1-header .sp-page-header-input");
      out.push({
        type: "page_header",
        page: 1,
        header: p1Input ? p1Input.value : "",
      });
      Array.from(questionsList.children).forEach((el) => {
        if (el.classList.contains("sp-page-break")) {
          const hi = el.querySelector(".sp-page-header-input");
          out.push({ type: "page_break", header: hi ? hi.value : "" });
        } else if (el.classList.contains("sp-question-card")) {
          out.push({ type: "question" });
        } else if (el.classList.contains("sp-text-card")) {
          const ta = el.querySelector(".sp-text-block-textarea");
          out.push({ type: "text", content: ta ? ta.value : "" });
        }
      });
      return out;
    }

    function renumberQuestionCardsInListOrder() {
      const cards = questionsList.querySelectorAll(".sp-question-card");
      cards.forEach((card, i) => {
        const oldIdx = card.getAttribute("data-question-index");
        if (oldIdx === String(i)) {
          return;
        }
        card.setAttribute("data-question-index", String(i));
        card.querySelectorAll("[name]").forEach((input) => {
          const n = input.getAttribute("name");
          if (n && n.includes("sp_questions[")) {
            input.setAttribute("name", n.replace(/sp_questions\[\d+]/, `sp_questions[${i}]`));
          }
        });
      });
    }

    function updateAddPageBreakBtn() {
      if (!addPageBreakBtn) return;
      if (structureLocked) {
        addPageBreakBtn.disabled = true;
        return;
      }
      const lastChild = questionsList.lastElementChild;
      const isEmpty = !lastChild;
      const isLastAPageBreak = lastChild && lastChild.classList.contains("sp-page-break");
      addPageBreakBtn.disabled = isEmpty || isLastAPageBreak;
    }

    const trashIconUrl = document.getElementById("sp-trash-icon-url")?.getAttribute("data-src") || "";
    const arrowUrlsEl = document.getElementById("sp-arrow-icon-urls");
    const upArrowUrl = arrowUrlsEl?.getAttribute("data-up") || "";
    const downArrowUrl = arrowUrlsEl?.getAttribute("data-down") || "";

    function renumberScaleRows(card) {
      const scaleContainer = card.querySelector(".sp-scale-rows");
      if (!scaleContainer) return;
      const questionIndex = card.getAttribute("data-question-index");
      const rows = scaleContainer.querySelectorAll(".sp-scale-row");
      rows.forEach((row, i) => {
        const newVal = i + 1;
        row.setAttribute("data-scale-value", String(newVal));
        const hiddenInput = row.querySelector("input[type=hidden]");
        if (hiddenInput) {
          hiddenInput.value = newVal;
          hiddenInput.name = `sp_questions[${questionIndex}][scale][${i}][value]`;
        }
        const numberInput = row.querySelector("input[type=number]");
        if (numberInput) numberInput.value = newVal;
        const textInput = row.querySelector('input[type="text"]');
        if (textInput) {
          textInput.name = `sp_questions[${questionIndex}][scale][${i}][label]`;
          textInput.placeholder = `Label for ${newVal}`;
        }
      });
    }

    function updateScaleRowTrash(card) {
      const scaleContainer = card.querySelector(".sp-scale-rows");
      if (!scaleContainer) return;
      const rows = scaleContainer.querySelectorAll(".sp-scale-row");
      rows.forEach((row) => {
        const existing = row.querySelector(".sp-scale-row-remove");
        if (existing) existing.remove();
      });
      const addScaleBtn = card.querySelector(".sp-add-scale");
      if (structureLocked) {
        if (addScaleBtn) addScaleBtn.disabled = true;
        return;
      }
      if (addScaleBtn) addScaleBtn.disabled = rows.length >= SP_SCALE_MAX;
      if (rows.length <= 1) return;
      rows.forEach((row) => {
        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "button-link sp-scale-row-remove";
        removeBtn.setAttribute("aria-label", "Delete Option");
        removeBtn.setAttribute("title", "Delete Option");
        removeBtn.innerHTML = trashIconUrl
          ? `<img src="${trashIconUrl}" alt="" class="sp-trash-icon" width="20" height="20">`
          : `<span class="dashicons dashicons-trash"></span>`;
        removeBtn.addEventListener("click", (e) => {
          e.preventDefault();
          row.remove();
          renumberScaleRows(card);
          updateScaleRowTrash(card);
        });
        row.appendChild(removeBtn);
      });
    }

    const SP_SCALE_MAX = 20;

    function addScaleRow(card) {
      if (structureLocked) return;
      const scaleContainer = card.querySelector(".sp-scale-rows");
      if (!scaleContainer) return;
      const rows = scaleContainer.querySelectorAll(".sp-scale-row");
      if (rows.length >= SP_SCALE_MAX) return;
      const lastRow = rows[rows.length - 1];
      const lastVal =
        lastRow &&
        parseInt(
          lastRow.getAttribute("data-scale-value") ||
            lastRow.querySelector("input[type=number]")?.value ||
            "0",
          10
        );
      const nextVal = (lastVal || 0) + 1;
      if (nextVal > SP_SCALE_MAX) return;
      const nextScaleIndex = rows.length;
      const questionIndex = card.getAttribute("data-question-index");

      const row = document.createElement("div");
      row.className = "sp-scale-row";
      row.setAttribute("data-scale-value", String(nextVal));
      row.innerHTML = `
        <input type="hidden" name="sp_questions[${questionIndex}][scale][${nextScaleIndex}][value]" value="${nextVal}" />
        <input type="number" class="small-text" value="${nextVal}" readonly />
        <input type="text" class="regular-text" name="sp_questions[${questionIndex}][scale][${nextScaleIndex}][label]" placeholder="Label for ${nextVal}" maxlength="120" data-sp-maxlength="120" />
      `;
      scaleContainer.appendChild(row);
      updateScaleRowTrash(card);
    }

    function attachPageBreakHandlers(pb) {
      const removeBtn = pb.querySelector(".sp-page-break-remove");
      if (removeBtn) {
        removeBtn.addEventListener("click", (e) => {
          e.preventDefault();
          if (structureLocked) return;
          pb.remove();
          refreshQuestionNumbers();
        });
      }
      attachReorderHandlers(pb);
    }

    function addPageBreak() {
      if (structureLocked) return;
      const lastChild = questionsList.lastElementChild;
      if (!lastChild || lastChild.classList.contains("sp-page-break")) return;

      const pb = document.createElement("div");
      pb.className = "sp-page-break";
      pb.innerHTML = `
        <div class="sp-page-break-bar">
          <div class="sp-page-break-line"></div>
          <span class="sp-page-break-label">Page Break</span>
          <span class="sp-block-move-actions" role="group" aria-label="Reorder">
            <button type="button" class="button-link sp-move-btn sp-move-up" aria-label="Move Element Up" title="Move Element Up">
              ${upArrowUrl ? `<img src="${upArrowUrl}" alt="" class="sp-move-icon" width="24" height="24" />` : ""}
            </button>
            <button type="button" class="button-link sp-move-btn sp-move-down" aria-label="Move Element Down" title="Move Element Down">
              ${downArrowUrl ? `<img src="${downArrowUrl}" alt="" class="sp-move-icon" width="24" height="24" />` : ""}
            </button>
          </span>
          <button type="button" class="button-link sp-page-break-remove" aria-label="Delete Page Break" title="Delete Page Break">
            ${trashIconUrl
              ? `<img src="${trashIconUrl}" alt="" class="sp-trash-icon" width="22" height="22">`
              : `<span class="dashicons dashicons-trash"></span>`}
          </button>
          <div class="sp-page-break-line"></div>
        </div>
        <div class="sp-page-header-field">
          <label class="sp-page-header-label">Page <span class="sp-page-number-display"></span> Header</label>
          <input type="text" class="regular-text sp-page-header-input" name="" placeholder="Optional page header\u2026" maxlength="120" data-sp-maxlength="120">
        </div>
      `;
      questionsList.appendChild(pb);
      attachPageBreakHandlers(pb);
      refreshQuestionNumbers();
    }

    if (addPageBreakBtn) {
      addPageBreakBtn.addEventListener("click", (e) => {
        e.preventDefault();
        if (structureLocked) return;
        addPageBreak();
      });
    }

    if (addTextBtn) {
      addTextBtn.addEventListener("click", (e) => {
        e.preventDefault();
        addTextCard();
      });
    }

    function attachTextBlockHandlers(card) {
      const removeBtn = card.querySelector(".sp-text-remove");
      if (removeBtn) {
        removeBtn.addEventListener("click", (e) => {
          e.preventDefault();
          card.remove();
          refreshQuestionNumbers();
        });
      }
      const ta = card.querySelector(".sp-text-block-textarea");
      const err = card.querySelector(".sp-text-block-error");
      if (ta && err) {
        ta.addEventListener("input", () => {
          if (ta.value.trim()) {
            err.style.display = "none";
            ta.classList.remove("sp-input-error");
          }
        });
      }
      attachReorderHandlers(card);
    }

    function addTextCard() {
      if (!textTemplateEl) return;
      const html = textTemplateEl.innerHTML.trim();
      if (!html) return;
      const wrapper = document.createElement("div");
      wrapper.innerHTML = html;
      const card = wrapper.firstElementChild;
      if (!card) return;
      card.querySelectorAll("input, textarea, select, button").forEach((el) => {
        el.removeAttribute("disabled");
      });
      questionsList.appendChild(card);
      attachTextBlockHandlers(card);
      initAutoExpand(card.querySelectorAll(".sp-auto-expand"));
      refreshQuestionNumbers();
    }

    function attachQuestionHandlers(card) {
      const removeBtn = card.querySelector(".sp-question-remove");
      if (removeBtn) {
        removeBtn.addEventListener("click", (e) => {
          e.preventDefault();
          if (structureLocked) return;
          card.remove();
          refreshQuestionNumbers();
        });
      }

      const addScaleBtn = card.querySelector(".sp-add-scale");
      if (addScaleBtn) {
        addScaleBtn.addEventListener("click", (e) => {
          e.preventDefault();
          if (structureLocked) return;
          addScaleRow(card);
        });
      }

      const qTextarea = card.querySelector(".sp-question-textarea");
      const qTextError = card.querySelector(".sp-qtext-error");
      if (qTextarea && qTextError) {
        qTextarea.addEventListener("input", () => {
          if (qTextarea.value.trim()) {
            qTextError.style.display = "none";
            qTextarea.classList.remove("sp-input-error");
          }
        });
      }

      updateScaleRowTrash(card);
      attachReorderHandlers(card);
    }

    function getScaleRowsLabelSetup(fromCard) {
      const scaleContainer = fromCard.querySelector(".sp-scale-rows");
      if (!scaleContainer) return [];
      const rows = scaleContainer.querySelectorAll(".sp-scale-row");
      return Array.from(rows).map((row) => {
        const valueInput = row.querySelector("input[type=number]");
        const labelInput = row.querySelector('input[type="text"]');
        return {
          value: parseInt(valueInput?.value || "0", 10),
          label: (labelInput && labelInput.value) || "",
        };
      });
    }

    function setScaleRowsFromLabelSetup(card, questionIndex, labelSetup) {
      const scaleContainer = card.querySelector(".sp-scale-rows");
      if (!scaleContainer || !labelSetup.length) return;
      scaleContainer.innerHTML = "";
      labelSetup.forEach((item, scaleIndex) => {
        const row = document.createElement("div");
        row.className = "sp-scale-row";
        row.setAttribute("data-scale-value", String(item.value));
        row.innerHTML = `
          <input type="hidden" name="sp_questions[${questionIndex}][scale][${scaleIndex}][value]" value="${item.value}" />
          <input type="number" class="small-text" value="${item.value}" readonly />
          <input type="text" class="regular-text" name="sp_questions[${questionIndex}][scale][${scaleIndex}][label]" placeholder="Label for ${item.value}" maxlength="120" data-sp-maxlength="120" />
        `;
        const labelInput = row.querySelector('input[type="text"]');
        if (labelInput && item.label) labelInput.value = item.label;
        scaleContainer.appendChild(row);
      });
    }

    function addQuestion() {
      if (structureLocked) return;
      let html = templateEl.innerHTML;
      if (!html) return;
      const index = nextIndex++;
      const existingCards = questionsList.querySelectorAll(".sp-question-card");
      const lastCard = existingCards.length > 0 ? existingCards[existingCards.length - 1] : null;
      const labelSetup = lastCard ? getScaleRowsLabelSetup(lastCard) : null;

      html = html.replace(/__INDEX__/g, String(index));

      const wrapper = document.createElement("div");
      wrapper.innerHTML = html.trim();
      const card = wrapper.firstElementChild;
      if (!card) return;
      card.setAttribute("data-question-index", String(index));

      if (labelSetup && labelSetup.length > 0) {
        setScaleRowsFromLabelSetup(card, String(index), labelSetup);
      }

      card.querySelectorAll("input, textarea, select, button").forEach((el) => {
        el.removeAttribute("disabled");
      });

      questionsList.appendChild(card);
      attachQuestionHandlers(card);
      updateScaleRowTrash(card);
      initAutoExpand(card.querySelectorAll(".sp-auto-expand"));
      refreshQuestionNumbers();
    }

    if (structureLocked && addQuestionBtn) {
      addQuestionBtn.disabled = true;
    }

    addQuestionBtn.addEventListener("click", (e) => {
      e.preventDefault();
      if (structureLocked) return;
      addQuestion();
    });

    // Existing questions and page breaks rendered by PHP
    questionsList.querySelectorAll(".sp-question-card").forEach((card) => {
      if (!card.getAttribute("data-question-index")) {
        card.setAttribute("data-question-index", String(nextIndex++));
      }
      attachQuestionHandlers(card);
    });

    questionsList.querySelectorAll(".sp-page-break").forEach((pb) => {
      attachPageBreakHandlers(pb);
    });

    questionsList.querySelectorAll(".sp-text-card").forEach((card) => {
      attachTextBlockHandlers(card);
    });

    initAutoExpand(questionsList.querySelectorAll(".sp-auto-expand"));
    refreshQuestionNumbers();

    // Require at least one question before the form can be submitted.
    const form = builder.closest("form");
    const questionsError   = document.getElementById("sp-questions-error");
    const titleInput       = document.getElementById("sp_survey_title");
    const titleError       = document.getElementById("sp-title-error");
    const excludeIdInput   = document.getElementById("sp_survey_exclude_id");
    const originalTitleInput = document.getElementById("sp_survey_original_title");

    let titleIsDuplicate = false;
    let titleIsVerified  = false;

    function showTitleError(msg) {
      if (titleError) {
        titleError.textContent = msg;
        titleError.style.display = "";
      }
      titleInput?.classList.add("sp-input-error");
    }

    function clearTitleError() {
      if (titleError) titleError.style.display = "none";
      titleInput?.classList.remove("sp-input-error");
      titleIsDuplicate = false;
    }

    const originalTitle = originalTitleInput?.value ?? "";
    const existingTitles = (spAdmin.surveyTitles || []).map((t) => t.toLowerCase());

    function checkDuplicateTitle(title) {
      if (title.toLowerCase() === originalTitle.toLowerCase()) {
        clearTitleError();
        titleIsVerified = true;
        return false;
      }
      return existingTitles.includes(title.toLowerCase());
    }

    if (titleInput && titleError) {
      // Any change invalidates the previous verification result.
      titleInput.addEventListener("input", () => {
        titleIsVerified = false;
        if (titleInput.value.trim()) {
          clearTitleError();
        } else {
          titleIsDuplicate = false;
        }
      });

      titleInput.addEventListener("blur", () => {
        const val = titleInput.value.trim();
        if (!val) return;
        const isDup = checkDuplicateTitle(val);
        if (isDup) {
          titleIsDuplicate = true;
          titleIsVerified  = false;
          showTitleError("A survey with this name already exists.");
        } else {
          titleIsDuplicate = false;
          titleIsVerified  = true;
          clearTitleError();
        }
      });
    }

    // --- Email messaging checkbox toggle ---
    const emailMessagingCheckbox = document.getElementById("sp_email_messaging");
    const emailMessageRow        = document.getElementById("sp-email-message-row");
    const emailMessageTextarea   = document.getElementById("sp_email_message");
    const emailMessageError      = document.getElementById("sp-email-message-error");
    const emailPdfColumn         = document.getElementById("sp-email-pdf-column");
    const sendPdfCheckbox        = document.getElementById("sp_send_pdf_report");
    const pdfLogoRow             = document.getElementById("sp-pdf-logo-row");

    const syncPdfLogoVisibility = () => {
      if (!pdfLogoRow) return;
      const show =
        Boolean(emailMessagingCheckbox?.checked) && Boolean(sendPdfCheckbox?.checked);
      pdfLogoRow.style.display = show ? "" : "none";
    };

    if (emailMessagingCheckbox) {
      emailMessagingCheckbox.addEventListener("change", () => {
        const checked = emailMessagingCheckbox.checked;
        if (emailMessageRow) emailMessageRow.style.display = checked ? "" : "none";
        if (emailPdfColumn) emailPdfColumn.style.display = checked ? "" : "none";
        if (!checked) {
          if (emailMessageError) emailMessageError.style.display = "none";
          emailMessageTextarea?.classList.remove("sp-input-error");
          if (sendPdfCheckbox) sendPdfCheckbox.checked = false;
        }
        syncPdfLogoVisibility();
      });
    }

    if (sendPdfCheckbox) {
      sendPdfCheckbox.addEventListener("change", syncPdfLogoVisibility);
    }

    syncPdfLogoVisibility();

    // PDF logo: show local preview as soon as a file is chosen (before save).
    const pdfLogoFileInput = document.getElementById("sp_pdf_report_logo");
    const pdfLogoPreviewLive = document.getElementById("sp-pdf-logo-preview-live");
    const pdfLogoPreviewLiveImg = document.getElementById("sp-pdf-logo-preview-live-img");
    const pdfLogoPreviewSaved = document.getElementById("sp-pdf-logo-preview-saved");
    const pdfLogoRemoveHidden = document.getElementById("sp_remove_pdf_report_logo");
    const pdfLogoRemoveLiveBtn = document.getElementById("sp-pdf-logo-remove-live-btn");
    const pdfLogoRemoveSavedBtn = document.getElementById("sp-pdf-logo-remove-saved-btn");
    const pdfLogoChooseBtn = document.getElementById("sp-pdf-logo-choose-btn");
    const pdfLogoFilenameEl = document.getElementById("sp-pdf-logo-filename");

    function spPdfLogoSyncFilenameLabel() {
      if (!pdfLogoFilenameEl || !pdfLogoFileInput) return;
      const emptyText = pdfLogoFilenameEl.getAttribute("data-empty-text") || "";
      const f = pdfLogoFileInput.files && pdfLogoFileInput.files[0];
      pdfLogoFilenameEl.textContent = f ? f.name : emptyText;
    }

    function spPdfLogoHideLivePreview() {
      if (pdfLogoPreviewLive) {
        pdfLogoPreviewLive.style.display = "none";
        pdfLogoPreviewLive.setAttribute("hidden", "");
      }
      if (pdfLogoPreviewLiveImg) {
        pdfLogoPreviewLiveImg.src = "";
      }
      if (pdfLogoPreviewSaved) {
        const hideSaved =
          pdfLogoRemoveHidden && String(pdfLogoRemoveHidden.value || "") === "1";
        pdfLogoPreviewSaved.style.display = hideSaved ? "none" : "";
      }
    }

    function spPdfLogoShowLivePreview(dataUrl) {
      if (!pdfLogoPreviewLive || !pdfLogoPreviewLiveImg || !dataUrl) return;
      pdfLogoPreviewLiveImg.src = dataUrl;
      pdfLogoPreviewLive.style.display = "";
      pdfLogoPreviewLive.removeAttribute("hidden");
      if (pdfLogoPreviewSaved) {
        pdfLogoPreviewSaved.style.display = "none";
      }
    }

    function spPdfLogoFileIsAllowedImage(file) {
      if (!file || !file.type) return false;
      if (file.type === "image/jpeg" || file.type === "image/png") return true;
      const name = (file.name || "").toLowerCase();
      return /\.(jpe?g|png)$/.test(name);
    }

    /** Last good local file so Cancel in the file picker does not clear the selection. */
    let spPdfLogoLastValidFile = null;

    function spPdfLogoRestoreFileInput(file) {
      if (!pdfLogoFileInput || !file) return false;
      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        pdfLogoFileInput.files = dt.files;
        return true;
      } catch (e) {
        return false;
      }
    }

    if (pdfLogoFileInput && pdfLogoPreviewLive && pdfLogoPreviewLiveImg) {
      pdfLogoFileInput.addEventListener("change", () => {
        try {
          const file = pdfLogoFileInput.files && pdfLogoFileInput.files[0];

          if (!file) {
            if (spPdfLogoLastValidFile && spPdfLogoRestoreFileInput(spPdfLogoLastValidFile)) {
              return;
            }
            spPdfLogoLastValidFile = null;
            spPdfLogoHideLivePreview();
            return;
          }

          if (!spPdfLogoFileIsAllowedImage(file)) {
            spPdfLogoHideLivePreview();
            if (spPdfLogoLastValidFile && spPdfLogoRestoreFileInput(spPdfLogoLastValidFile)) {
              return;
            }
            pdfLogoFileInput.value = "";
            spPdfLogoLastValidFile = null;
            return;
          }

          spPdfLogoLastValidFile = file;

          const reader = new FileReader();
          reader.onload = () => {
            const result = typeof reader.result === "string" ? reader.result : "";
            if (result) {
              if (pdfLogoRemoveHidden) {
                pdfLogoRemoveHidden.value = "";
              }
              spPdfLogoShowLivePreview(result);
            }
          };
          reader.onerror = () => {
            spPdfLogoHideLivePreview();
          };
          reader.readAsDataURL(file);
        } finally {
          spPdfLogoSyncFilenameLabel();
        }
      });
    }

    if (pdfLogoChooseBtn && pdfLogoFileInput) {
      pdfLogoChooseBtn.addEventListener("click", () => {
        pdfLogoFileInput.click();
      });
    }

    /** Clear pending file only (create / new selection); do not remove saved attachment on save. */
    if (pdfLogoRemoveLiveBtn && pdfLogoFileInput) {
      pdfLogoRemoveLiveBtn.addEventListener("click", () => {
        spPdfLogoLastValidFile = null;
        pdfLogoFileInput.value = "";
        spPdfLogoSyncFilenameLabel();
        if (pdfLogoPreviewLive) {
          pdfLogoPreviewLive.style.display = "none";
          pdfLogoPreviewLive.setAttribute("hidden", "");
        }
        if (pdfLogoPreviewLiveImg) {
          pdfLogoPreviewLiveImg.src = "";
        }
        if (
          pdfLogoPreviewSaved &&
          pdfLogoRemoveHidden &&
          String(pdfLogoRemoveHidden.value || "") !== "1"
        ) {
          pdfLogoPreviewSaved.style.display = "";
        }
      });
    }

    /** Mark saved logo for removal on save (edit only). */
    if (pdfLogoRemoveSavedBtn && pdfLogoRemoveHidden && pdfLogoFileInput) {
      pdfLogoRemoveSavedBtn.addEventListener("click", () => {
        spPdfLogoLastValidFile = null;
        pdfLogoFileInput.value = "";
        pdfLogoRemoveHidden.value = "1";
        spPdfLogoSyncFilenameLabel();
        if (pdfLogoPreviewLive) {
          pdfLogoPreviewLive.style.display = "none";
          pdfLogoPreviewLive.setAttribute("hidden", "");
        }
        if (pdfLogoPreviewLiveImg) {
          pdfLogoPreviewLiveImg.src = "";
        }
        if (pdfLogoPreviewSaved) {
          pdfLogoPreviewSaved.style.display = "none";
        }
      });
    }

    if (emailMessageTextarea && emailMessageError) {
      emailMessageTextarea.addEventListener("input", () => {
        if (emailMessageTextarea.value.trim()) {
          emailMessageError.style.display = "none";
          emailMessageTextarea.classList.remove("sp-input-error");
        }
      });
    }

    if (form && questionsError) {
      form.addEventListener("submit", (e) => {

        const titleVal = titleInput?.value.trim() ?? "";

        // --- Synchronous checks (no async needed) ---
        let syncBlocked = false;

        if (!titleVal) {
          syncBlocked = true;
          showTitleError("Survey Title is required.");
          titleInput?.scrollIntoView({ behavior: "smooth", block: "center" });
        } else if (titleIsDuplicate) {
          syncBlocked = true;
          showTitleError("A survey with this name already exists.");
          titleInput?.scrollIntoView({ behavior: "smooth", block: "center" });
        }

        if (emailMessagingCheckbox?.checked && emailMessageTextarea && !emailMessageTextarea.value.trim()) {
          syncBlocked = true;
          if (emailMessageError) emailMessageError.style.display = "";
          emailMessageTextarea.classList.add("sp-input-error");
          if (titleVal && !titleIsDuplicate) {
            emailMessageTextarea.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }

        if (!questionsList.querySelector(".sp-question-card")) {
          syncBlocked = true;
          questionsError.style.display = "";
          if (titleVal && !titleIsDuplicate) {
            questionsError.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        } else {
          questionsError.style.display = "none";
          let firstListFieldError = null;
          Array.from(questionsList.children).forEach((el) => {
            if (el.classList.contains("sp-question-card")) {
              const ta = el.querySelector(".sp-question-textarea");
              const err = el.querySelector(".sp-qtext-error");
              if (ta && !ta.value.trim()) {
                syncBlocked = true;
                if (err) err.style.display = "";
                ta.classList.add("sp-input-error");
                if (!firstListFieldError) firstListFieldError = ta;
              }
            } else if (el.classList.contains("sp-text-card")) {
              const ta = el.querySelector(".sp-text-block-textarea");
              const err = el.querySelector(".sp-text-block-error");
              if (ta && !ta.value.trim()) {
                syncBlocked = true;
                if (err) err.style.display = "";
                ta.classList.add("sp-input-error");
                if (!firstListFieldError) firstListFieldError = ta;
              }
            }
          });
          if (
            firstListFieldError &&
            titleVal &&
            !titleIsDuplicate &&
            !(emailMessagingCheckbox?.checked && emailMessageTextarea && !emailMessageTextarea.value.trim())
          ) {
            firstListFieldError.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }

        if (syncBlocked) {
          e.preventDefault();
          return;
        }

        renumberQuestionCardsInListOrder();
        if (layoutInput) {
          layoutInput.value = JSON.stringify(buildLayoutFromDom());
        }

        // --- Check title uniqueness synchronously ---
        if (!titleIsVerified) {
          const isDup = checkDuplicateTitle(titleVal);
          if (isDup) {
            e.preventDefault();
            titleIsDuplicate = true;
            showTitleError("A survey with this name already exists.");
            titleInput?.scrollIntoView({ behavior: "smooth", block: "center" });
            return;
          }
        }
      });
    }
  }

  function initSurveyListSort() {
    const list = document.querySelector(".sp-survey-list");
    const select = document.getElementById("sp-sort-select");
    if (!list || !select) return;

    const STORAGE_KEY = "sp_sort_order";
    const saved = localStorage.getItem(STORAGE_KEY) || "updated_desc";
    select.value = saved;

    function getSurveyCards() {
      return Array.from(list.querySelectorAll(":scope > .sp-survey-card"));
    }

    function saveCustomOrder() {
      if (typeof spAdmin === "undefined") return;
      const cards = getSurveyCards();
      cards.forEach((c, i) => {
        c.dataset.sortOrder = i + 1;
      });
      const params = new URLSearchParams({
        action: "sp_save_survey_order",
        nonce: spAdmin.nonce,
      });
      cards.forEach((c) => params.append("order[]", c.dataset.surveyId));
      fetch(spAdmin.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params,
      });
    }

    function updateReorderButtonStates() {
      if (!list.classList.contains("sp-custom-order-mode")) return;
      const cards = getSurveyCards();
      cards.forEach((card, index) => {
        const up = card.querySelector(".sp-survey-move-up");
        const down = card.querySelector(".sp-survey-move-down");
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === cards.length - 1;
      });
    }

    function sortList(key) {
      const cards = getSurveyCards();

      if (key === "custom") {
        list.classList.add("sp-custom-order-mode");
        const hasOrder = cards.some((c) => parseInt(c.dataset.sortOrder, 10) > 0);
        if (hasOrder) {
          cards.sort(
            (a, b) =>
              parseInt(a.dataset.sortOrder, 10) - parseInt(b.dataset.sortOrder, 10)
          );
          cards.forEach((c) => list.appendChild(c));
        }
        updateReorderButtonStates();
        return;
      }

      list.classList.remove("sp-custom-order-mode");

      cards.sort((a, b) => {
        switch (key) {
          case "updated_desc":
            return new Date(b.dataset.updated) - new Date(a.dataset.updated);
          case "updated_asc":
            return new Date(a.dataset.updated) - new Date(b.dataset.updated);
          case "created_desc":
            return new Date(b.dataset.created) - new Date(a.dataset.created);
          case "created_asc":
            return new Date(a.dataset.created) - new Date(b.dataset.created);
          case "alpha_asc":
            return a.dataset.title.localeCompare(b.dataset.title);
          case "alpha_desc":
            return b.dataset.title.localeCompare(a.dataset.title);
          default:
            return 0;
        }
      });
      cards.forEach((c) => list.appendChild(c));
    }

    sortList(saved);
    list.style.visibility = "";

    select.addEventListener("change", () => {
      const key = select.value;
      localStorage.setItem(STORAGE_KEY, key);
      sortList(key);
    });

    list.addEventListener("click", (e) => {
      const btn = e.target.closest(".sp-survey-move-btn");
      if (!btn || !list.contains(btn)) return;
      if (!list.classList.contains("sp-custom-order-mode")) return;
      e.stopPropagation();
      const card = btn.closest(".sp-survey-card");
      if (!card) return;
      const cards = getSurveyCards();
      const index = cards.indexOf(card);
      if (index < 0) return;
      if (btn.classList.contains("sp-survey-move-up")) {
        if (index === 0) return;
        list.insertBefore(card, cards[index - 1]);
      } else if (btn.classList.contains("sp-survey-move-down")) {
        if (index >= cards.length - 1) return;
        const next = cards[index + 1];
        list.insertBefore(card, next.nextSibling);
      } else {
        return;
      }
      saveCustomOrder();
      updateReorderButtonStates();
    });
  }

  function initEmailSettings() {
    const modeSelect   = document.getElementById("sp_email_mode");
    const configForm   = document.getElementById("sp-email-config-form");
    const testForm     = document.getElementById("sp-test-email-form");
    if (!modeSelect && !testForm) return;

    const smtpRows = document.querySelectorAll(".sp-smtp-row");

    function toggleSmtpRows() {
      const show = modeSelect && modeSelect.value === "smtp";
      smtpRows.forEach((row) => {
        row.style.display = show ? "" : "none";
      });
    }

    if (modeSelect) {
      toggleSmtpRows();
      modeSelect.addEventListener("change", toggleSmtpRows);
    }

    if (configForm) {
      configForm.addEventListener("submit", (e) => {
        let hasError = false;

        if (modeSelect && modeSelect.value === "smtp") {
          const smtpFields = [
            { inputId: "sp_smtp_host", errorId: "sp-smtp-host-error" },
            { inputId: "sp_smtp_port", errorId: "sp-smtp-port-error" },
            { inputId: "sp_smtp_user", errorId: "sp-smtp-user-error" },
            { inputId: "sp_smtp_pass", errorId: "sp-smtp-pass-error" },
          ];

          smtpFields.forEach(({ inputId, errorId }) => {
            const input = document.getElementById(inputId);
            const error = document.getElementById(errorId);
            if (!input || !error) return;
            if (!input.value.trim()) {
              error.style.display = "";
              input.classList.add("sp-input-error");
              hasError = true;
              input.addEventListener("input", () => {
                error.style.display = "none";
                input.classList.remove("sp-input-error");
              }, { once: true });
            } else {
              error.style.display = "none";
              input.classList.remove("sp-input-error");
            }
          });
        }

        if (hasError) e.preventDefault();
      });
    }

    const sendBtn      = document.getElementById("sp-send-test-email-btn");
    const recipientInput = document.getElementById("sp_test_email_to");
    const recipientError = document.getElementById("sp-test-email-error");
    const resultBox    = document.getElementById("sp-test-result-box");

    if (sendBtn && recipientInput) {
      sendBtn.addEventListener("click", async () => {
        const emailVal = recipientInput.value.trim();
        const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal);

        if (!emailValid) {
          recipientError.style.display = "";
          recipientInput.classList.add("sp-input-error");
          if (resultBox) resultBox.style.display = "none";
          recipientInput.addEventListener("input", () => {
            recipientError.style.display = "none";
            recipientInput.classList.remove("sp-input-error");
          }, { once: true });
          return;
        }

        recipientError.style.display = "none";
        recipientInput.classList.remove("sp-input-error");

        sendBtn.disabled = true;
        sendBtn.textContent = "Sending…";
        if (resultBox) resultBox.style.display = "none";

        try {
          const params = new URLSearchParams({
            action: "sp_send_test_email",
            nonce: spAdmin.testEmailNonce,
            email: emailVal,
          });
          const res = await fetch(spAdmin.ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: params,
          });
          const data = await res.json();

          if (resultBox) {
            resultBox.textContent = data.data?.message ?? (data.success ? "Email sent successfully." : "Email failed to send.");
            resultBox.className = "sp-test-result-box " + (data.success ? "sp-test-result-box-success" : "sp-test-result-box-error");
            resultBox.style.display = "";
          }
        } catch {
          if (resultBox) {
            resultBox.textContent = "An unexpected error occurred. Please try again.";
            resultBox.className = "sp-test-result-box sp-test-result-box-error";
            resultBox.style.display = "";
          }
        } finally {
          sendBtn.disabled = false;
          sendBtn.textContent = "Send Test Email";
        }
      });
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    initHardCharacterMax();
    initAutoExpand();
    initQuestionBuilder();
    initDashboardCards();
    initSurveyListSort();
    initEmailSettings();
  });
})();

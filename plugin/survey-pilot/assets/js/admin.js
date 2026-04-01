(() => {
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
        // Prevent navigation after a drag operation
        if (card.dataset.justDragged) {
          delete card.dataset.justDragged;
          return;
        }
        const target = e.target;
        if (
          target.closest("a") ||
          target.closest("button") ||
          target.closest(".sp-survey-desc") ||
          target.closest(".sp-survey-shortcode-display code")
        ) {
          return;
        }
        window.location.href = url;
      });

      [
        card.querySelector(".sp-copy-btn"),
        card.querySelector(".sp-survey-desc"),
        card.querySelector(".sp-survey-shortcode-display code"),
      ].forEach((el) => {
        if (!el) return;
        el.addEventListener("mouseenter", () => card.classList.add("sp-nohover"));
        el.addEventListener("mouseleave", () => card.classList.remove("sp-nohover"));
      });

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

    const questionsList = document.getElementById("sp-questions-list");
    const addQuestionBtn = document.getElementById("sp-add-question");
    const addPageBreakBtn = document.getElementById("sp-add-page-break");
    const templateEl = document.getElementById("sp-question-template");

    if (!questionsList || !addQuestionBtn || !templateEl) return;

    let nextIndex = parseInt(builder.getAttribute("data-next-index") || "0", 10);
    if (Number.isNaN(nextIndex)) nextIndex = 0;

    function cleanupPageBreaks() {
      // If there are no questions at all, remove every page break
      const hasQuestions = !!questionsList.querySelector(".sp-question-card");
      if (!hasQuestions) {
        questionsList.querySelectorAll(".sp-page-break").forEach((pb) => pb.remove());
        return;
      }

      // Remove leading page breaks (page 1 would otherwise be empty)
      let first = questionsList.firstElementChild;
      while (first && first.classList.contains("sp-page-break")) {
        first.remove();
        first = questionsList.firstElementChild;
      }

      // Merge consecutive page breaks, keeping only the first of each run
      let prev = null;
      Array.from(questionsList.children).forEach((el) => {
        if (
          el.classList.contains("sp-page-break") &&
          prev &&
          prev.classList.contains("sp-page-break")
        ) {
          el.remove();
          return; // prev stays pointing at the surviving page break
        }
        prev = el;
      });
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
    }

    function refreshPageNumbers() {
      let currentPage = 1;
      Array.from(questionsList.children).forEach((el) => {
        if (el.classList.contains("sp-page-break")) {
          currentPage++;
        } else if (el.classList.contains("sp-question-card")) {
          const pageInput = el.querySelector(".sp-page-input");
          if (pageInput) pageInput.value = currentPage;
        }
      });
    }

    function updateAddPageBreakBtn() {
      if (!addPageBreakBtn) return;
      const lastChild = questionsList.lastElementChild;
      const isEmpty = !lastChild;
      const isLastAPageBreak = lastChild && lastChild.classList.contains("sp-page-break");
      addPageBreakBtn.disabled = isEmpty || isLastAPageBreak;
    }

    const trashIconUrl = document.getElementById("sp-trash-icon-url")?.getAttribute("data-src") || "";

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
      if (addScaleBtn) addScaleBtn.disabled = rows.length >= SP_SCALE_MAX;
      if (rows.length <= 1) return;
      rows.forEach((row) => {
        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "button-link sp-scale-row-remove";
        removeBtn.setAttribute("aria-label", "Remove this option");
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
        <input type="text" class="regular-text" name="sp_questions[${questionIndex}][scale][${nextScaleIndex}][label]" placeholder="Label for ${nextVal}" />
      `;
      scaleContainer.appendChild(row);
      updateScaleRowTrash(card);
    }

    function attachPageBreakHandlers(pb) {
      const removeBtn = pb.querySelector(".sp-page-break-remove");
      if (removeBtn) {
        removeBtn.addEventListener("click", (e) => {
          e.preventDefault();
          pb.remove();
          refreshQuestionNumbers();
        });
      }
    }

    function addPageBreak() {
      const lastChild = questionsList.lastElementChild;
      if (!lastChild || lastChild.classList.contains("sp-page-break")) return;

      const pb = document.createElement("div");
      pb.className = "sp-page-break";
      pb.innerHTML = `
        <div class="sp-page-break-line"></div>
        <span class="sp-page-break-label">Page Break</span>
        <button type="button" class="button-link sp-page-break-remove" aria-label="Remove page break">
          ${trashIconUrl
            ? `<img src="${trashIconUrl}" alt="" class="sp-trash-icon" width="18" height="18">`
            : `<span class="dashicons dashicons-trash"></span>`}
        </button>
        <div class="sp-page-break-line"></div>
      `;
      questionsList.appendChild(pb);
      attachPageBreakHandlers(pb);
      refreshPageNumbers();
      updateAddPageBreakBtn();
    }

    if (addPageBreakBtn) {
      addPageBreakBtn.addEventListener("click", (e) => {
        e.preventDefault();
        addPageBreak();
      });
    }

    function attachQuestionHandlers(card) {
      const removeBtn = card.querySelector(".sp-question-remove");
      if (removeBtn) {
        removeBtn.addEventListener("click", (e) => {
          e.preventDefault();
          card.remove();
          refreshQuestionNumbers();
        });
      }

      const addScaleBtn = card.querySelector(".sp-add-scale");
      if (addScaleBtn) {
        addScaleBtn.addEventListener("click", (e) => {
          e.preventDefault();
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
          <input type="text" class="regular-text" name="sp_questions[${questionIndex}][scale][${scaleIndex}][label]" placeholder="Label for ${item.value}" />
        `;
        const labelInput = row.querySelector('input[type="text"]');
        if (labelInput && item.label) labelInput.value = item.label;
        scaleContainer.appendChild(row);
      });
    }

    function addQuestion() {
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

      questionsList.appendChild(card);
      attachQuestionHandlers(card);
      updateScaleRowTrash(card);
      initAutoExpand(card.querySelectorAll(".sp-auto-expand"));
      refreshQuestionNumbers();
    }

    addQuestionBtn.addEventListener("click", (e) => {
      e.preventDefault();
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

    if (form && questionsError) {
      form.addEventListener("submit", (e) => {

        const titleVal = titleInput?.value.trim() ?? "";

        // --- Synchronous checks (no async needed) ---
        let syncBlocked = false;

        if (!titleVal) {
          syncBlocked = true;
          showTitleError("Please enter a survey title.");
          titleInput?.scrollIntoView({ behavior: "smooth", block: "center" });
        } else if (titleIsDuplicate) {
          syncBlocked = true;
          showTitleError("A survey with this name already exists.");
          titleInput?.scrollIntoView({ behavior: "smooth", block: "center" });
        }

        if (!questionsList.querySelector(".sp-question-card")) {
          syncBlocked = true;
          questionsError.style.display = "";
          if (titleVal && !titleIsDuplicate) {
            questionsError.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        } else {
          questionsError.style.display = "none";
          let firstEmptyTextarea = null;
          questionsList.querySelectorAll(".sp-question-card").forEach((card) => {
            const ta  = card.querySelector(".sp-question-textarea");
            const err = card.querySelector(".sp-qtext-error");
            if (ta && !ta.value.trim()) {
              syncBlocked = true;
              if (err) err.style.display = "";
              ta.classList.add("sp-input-error");
              if (!firstEmptyTextarea) firstEmptyTextarea = ta;
            }
          });
          if (firstEmptyTextarea && (!syncBlocked || (titleVal && !titleIsDuplicate))) {
            firstEmptyTextarea.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }

        if (syncBlocked) {
          e.preventDefault();
          return;
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

  function initSortAndDrag() {
    const list = document.querySelector(".sp-survey-list");
    const select = document.getElementById("sp-sort-select");
    if (!list || !select) return;

    const STORAGE_KEY = "sp_sort_order";
    const saved = localStorage.getItem(STORAGE_KEY) || "updated_desc";
    select.value = saved;

    function sortList(key) {
      const cards = Array.from(list.querySelectorAll(".sp-survey-card"));

      if (key === "custom") {
        list.classList.add("sp-custom-order-mode");
        cards.forEach((c) => c.setAttribute("draggable", "true"));
        // Sort by stored sort_order if any card has one set
        const hasOrder = cards.some((c) => parseInt(c.dataset.sortOrder) > 0);
        if (hasOrder) {
          cards.sort(
            (a, b) => parseInt(a.dataset.sortOrder) - parseInt(b.dataset.sortOrder)
          );
          cards.forEach((c) => list.appendChild(c));
        }
        return;
      }

      list.classList.remove("sp-custom-order-mode");
      cards.forEach((c) => c.setAttribute("draggable", "false"));

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

    // Drag-and-drop for custom order
    let dragSrc = null;

    list.addEventListener("dragstart", (e) => {
      const card = e.target.closest(".sp-survey-card");
      if (!card) return;
      dragSrc = card;
      card.classList.add("sp-dragging");
      e.dataTransfer.effectAllowed = "move";
    });

    list.addEventListener("dragend", (e) => {
      const card = e.target.closest(".sp-survey-card");
      if (card) {
        card.classList.remove("sp-dragging");
        card.dataset.justDragged = "1";
      }
      list
        .querySelectorAll(".sp-drag-over")
        .forEach((c) => c.classList.remove("sp-drag-over"));
      dragSrc = null;
      saveCustomOrder();
    });

    function clearDropIndicators() {
      list
        .querySelectorAll(".sp-drop-above, .sp-drop-below")
        .forEach((c) => c.classList.remove("sp-drop-above", "sp-drop-below"));
    }

    function getDropPosition(card, clientY) {
      const rect = card.getBoundingClientRect();
      return clientY < rect.top + rect.height / 2 ? "above" : "below";
    }

    // Always use ::before (sp-drop-above) so only one pseudo-element renders.
    // For "below" cases, promote to the next card's "above" indicator.
    // Only use sp-drop-below (::after) when there is no next card.
    function setDropIndicator(card, pos) {
      clearDropIndicators();
      if (pos === "above") {
        card.classList.add("sp-drop-above");
      } else {
        let next = card.nextElementSibling;
        while (next && (!next.classList.contains("sp-survey-card") || next === dragSrc)) {
          next = next.nextElementSibling;
        }
        if (next) {
          next.classList.add("sp-drop-above");
        } else {
          card.classList.add("sp-drop-below");
        }
      }
    }

    list.addEventListener("dragover", (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";
      const card = e.target.closest(".sp-survey-card");
      if (!card || card === dragSrc) return;
      setDropIndicator(card, getDropPosition(card, e.clientY));
    });

    list.addEventListener("dragleave", (e) => {
      if (!list.contains(e.relatedTarget)) clearDropIndicators();
    });

    list.addEventListener("drop", (e) => {
      e.preventDefault();
      if (!dragSrc) return;

      // Use the card that currently has a drop indicator (covers drops in gaps)
      const above = list.querySelector(".sp-drop-above");
      const below = list.querySelector(".sp-drop-below");
      clearDropIndicators();

      if (above && above !== dragSrc) {
        list.insertBefore(dragSrc, above);
      } else if (below && below !== dragSrc) {
        list.insertBefore(dragSrc, below.nextSibling);
      }
      // If neither indicator is set (dropped back on itself) — do nothing
    });

    function saveCustomOrder() {
      if (typeof spAdmin === "undefined") return;
      const cards = Array.from(list.querySelectorAll(".sp-survey-card"));
      // Update data-sort-order attributes immediately for next page load
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
  }

  document.addEventListener("DOMContentLoaded", () => {
    initAutoExpand();
    initQuestionBuilder();
    initDashboardCards();
    initSortAndDrag();
  });
})();

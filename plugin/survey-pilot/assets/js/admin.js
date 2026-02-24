(() => {
  // Delete confirmation modal
  const openButtons = document.querySelectorAll("[data-sp-delete-url]");
  const modal = document.getElementById("sp-delete-modal");

  if (modal && openButtons.length > 0) {
    const overlay = modal.querySelector(".sp-modal-overlay");
    const dialog = modal.querySelector(".sp-modal-dialog");
    const cancelBtn = modal.querySelector("[data-sp-cancel]");
    const confirmBtn = modal.querySelector("[data-sp-confirm]");

    let deleteUrl = null;

    function openModal(url) {
      deleteUrl = url;
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
        if (!url) return;
        openModal(url);
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
    const templateEl = document.getElementById("sp-question-template");

    if (!questionsList || !addQuestionBtn || !templateEl) return;

    let nextIndex = parseInt(builder.getAttribute("data-next-index") || "0", 10);
    if (Number.isNaN(nextIndex)) nextIndex = 0;

    function refreshQuestionNumbers() {
      const cards = Array.from(questionsList.querySelectorAll(".sp-question-card"));
      cards.forEach((card, idx) => {
        const numEl = card.querySelector(".sp-question-label .sp-question-number");
        if (numEl) numEl.textContent = String(idx + 1);
      });
    }

    const trashIconUrl = document.getElementById("sp-trash-icon-url")?.getAttribute("data-src") || "";

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
      const lastRow = rows[rows.length - 1];
      const questionIndex = card.getAttribute("data-question-index");
      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "button-link sp-scale-row-remove";
      removeBtn.setAttribute("aria-label", "Remove this option");
      removeBtn.innerHTML = trashIconUrl
        ? `<img src="${trashIconUrl}" alt="" class="sp-trash-icon" width="20" height="20">`
        : "<span class=\"dashicons dashicons-trash\"></span>";
      removeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        lastRow.remove();
        updateScaleRowTrash(card);
      });
      lastRow.appendChild(removeBtn);
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

    // Existing questions rendered by PHP
    questionsList.querySelectorAll(".sp-question-card").forEach((card) => {
      if (!card.getAttribute("data-question-index")) {
        card.setAttribute("data-question-index", String(nextIndex++));
      }
      attachQuestionHandlers(card);
    });

    initAutoExpand(questionsList.querySelectorAll(".sp-auto-expand"));
    refreshQuestionNumbers();
  }

  document.addEventListener("DOMContentLoaded", () => {
    initAutoExpand();
    initQuestionBuilder();
  });
})();

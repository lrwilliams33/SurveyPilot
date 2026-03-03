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

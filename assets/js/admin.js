(function () {
    "use strict";

    const adminData = window.KargoSayaciAdminData || {};
    const optionName = adminData.optionName || "kargo_sayaci_settings";
    const form = document.querySelector('form[action="options.php"]');
    const previewPanel = document.getElementById("kargo-preview-panel");
    const previewDaySelect = document.getElementById("kargo-preview-day-select");
    const statusNode = document.getElementById("kargo-preview-status");
    const metaNode = document.getElementById("kargo-preview-meta");
    const normalNode = document.getElementById("kargo-preview-normal");
    const highlightNode = document.getElementById("kargo-preview-highlight");
    const textWrap = document.getElementById("kargo-preview-text");
    const deliveryNode = document.getElementById("kargo-preview-delivery");
    const closuresTable = document.getElementById("kargo-closures-table");
    const addClosureButton = document.getElementById("kargo-add-closure");

    if (!form || !previewPanel || !previewDaySelect || !statusNode || !metaNode || !normalNode || !highlightNode || !textWrap) {
        return;
    }

    function getField(path) {
        return form.querySelector('[name="' + optionName + path + '"]');
    }

    function getValue(path, fallbackValue) {
        const field = getField(path);
        if (!field) {
            return fallbackValue;
        }

        return typeof field.value === "string" ? field.value.trim() : fallbackValue;
    }

    function isChecked(path) {
        const field = getField(path);
        return !!(field && field.checked);
    }

    function getSelectedDayIndex() {
        return Number(previewDaySelect.value || 0);
    }

    function getSelectedDayLabel() {
        const option = previewDaySelect.options[previewDaySelect.selectedIndex];
        return option ? option.text : "Seçili gün";
    }

    function getDayData(dayIndex) {
        const basePath = "[days][" + dayIndex + "]";
        return {
            enabled: isChecked(basePath + "[enabled]"),
            start: getValue(basePath + "[start]", "00:00"),
            end: getValue(basePath + "[end]", "00:00"),
            showToday: isChecked(basePath + "[show_today]"),
            todayLabel: getValue(basePath + "[today_label]", "bugün") || "bugün"
        };
    }

    function buildHighlightText(template, dayData) {
        const todayLabel = dayData.showToday ? dayData.todayLabel : "";
        return String(template || "")
            .replace(/\{\{\s*today\s*\}\}/gi, todayLabel)
            .replace(/\s+/g, " ")
            .trim();
    }

    function renderTemplateText(node, template, dayData) {
        const todayLabel = dayData.showToday ? dayData.todayLabel : "";
        const pattern = /\{\{\s*today\s*\}\}/gi;
        let cursor = 0;
        let match = pattern.exec(template);

        node.textContent = "";

        while (match) {
            appendText(node, template.slice(cursor, match.index));

            if (todayLabel) {
                const todayNode = document.createElement("span");
                todayNode.className = "kargo-sayaci-preview__today";
                todayNode.textContent = todayLabel;
                node.appendChild(todayNode);
            }

            cursor = match.index + match[0].length;
            match = pattern.exec(template);
        }

        appendText(node, template.slice(cursor));
    }

    function appendText(node, value) {
        if (value) {
            node.appendChild(document.createTextNode(value));
        }
    }

    function isTimeRangeValid(dayData) {
        if (!/^\d{2}:\d{2}$/.test(dayData.start) || !/^\d{2}:\d{2}$/.test(dayData.end)) {
            return false;
        }

        return dayData.end > dayData.start;
    }

    function applyPreviewColors() {
        previewPanel.style.setProperty("--kargo-bg", getValue("[background_color]", "#c6623f") || "#c6623f");
        previewPanel.style.setProperty("--kargo-logo-bg", getValue("[box_text_color]", "#ffffff") || "#ffffff");
        previewPanel.style.setProperty("--kargo-text", getValue("[text_color]", "#111111") || "#111111");
        previewPanel.style.setProperty("--kargo-accent", getValue("[accent_text_color]", "#111111") || "#111111");
        previewPanel.style.setProperty("--kargo-today", getValue("[today_text_color]", "#c6623f") || "#c6623f");
        previewPanel.style.setProperty("--kargo-card-bg", getValue("[card_bg_color]", "#fff7f1") || "#fff7f1");
        previewPanel.style.setProperty("--kargo-body-bg", getValue("[body_bg_color]", "#fffaf6") || "#fffaf6");
        previewPanel.style.setProperty("--kargo-border", getValue("[border_color]", "#c6623f") || "#c6623f");
        previewPanel.style.setProperty("--kargo-delivery-label", getValue("[delivery_label_color]", "#5f514b") || "#5f514b");
        previewPanel.style.setProperty("--kargo-delivery-value", getValue("[delivery_value_color]", "#111111") || "#111111");
    }

    function renderStatus(message, type) {
        statusNode.textContent = message;
        statusNode.classList.toggle("is-warning", type === "warning");
    }

    function updatePreview() {
        const dayIndex = getSelectedDayIndex();
        const dayLabel = getSelectedDayLabel();
        const dayData = getDayData(dayIndex);
        const baseText = getValue("[normal_text]", "");
        const highlightTemplate = getValue("[highlight_text]", "");
        const deliveryText = getValue("[delivery_text]", "");
        const highlightText = buildHighlightText(highlightTemplate, dayData);
        const globalEnabled = isChecked("[enabled]");
        const isRangeValid = isTimeRangeValid(dayData);
        const basePreviewText = baseText ? " " + baseText + (highlightText ? " " : "") : "";

        applyPreviewColors();

        normalNode.textContent = basePreviewText;
        renderTemplateText(highlightNode, highlightTemplate, dayData);
        normalNode.style.display = basePreviewText ? "" : "none";
        highlightNode.style.display = highlightText ? "" : "none";
        textWrap.style.display = basePreviewText || highlightText ? "" : "none";
        if (deliveryNode) {
            deliveryNode.textContent = deliveryText || "Yarın kapında";
        }

        if (!globalEnabled) {
            renderStatus("Genel ayarda sayaç kapalı.", "warning");
        } else if (!dayData.enabled) {
            renderStatus(dayLabel + " için sayaç kapalı.", "warning");
        } else if (!isRangeValid) {
            renderStatus("Bitiş saati başlangıç saatinden büyük olmalı.", "warning");
        } else {
            renderStatus(dayLabel + " için görünüm aktif.", "success");
        }

        if (!dayData.enabled) {
            metaNode.textContent = dayLabel + " günü kapalı olduğu için ön yüzde görünmez.";
            return;
        }

        if (!isRangeValid) {
            metaNode.textContent = dayLabel + " için saat aralığını kontrol et. Örnek: 09:00 - 17:15.";
            return;
        }

        metaNode.textContent = dayLabel + " günü " + dayData.start + " - " + dayData.end + " arasında gösterilir. " + (dayData.showToday ? '"' + dayData.todayLabel + '" ifadesi kullanılacak.' : '"bugün" ifadesi kullanılmayacak.');
    }

    function getNextClosureIndex() {
        if (!closuresTable) {
            return 0;
        }

        let maxIndex = -1;
        closuresTable.querySelectorAll("input[name*='[closures]']").forEach(function (field) {
            const match = field.name.match(/\[closures\]\[(\d+)\]/);
            if (match) {
                maxIndex = Math.max(maxIndex, Number(match[1]));
            }
        });

        return maxIndex + 1;
    }

    function createClosureRow(index) {
        const row = document.createElement("tr");
        const prefix = optionName + "[closures][" + index + "]";

        row.innerHTML = [
            '<td><label class="kargo-sayaci-admin__checkbox"><input type="checkbox" name="' + prefix + '[enabled]" value="1" checked> Açık</label></td>',
            '<td><input type="text" class="regular-text" name="' + prefix + '[label]" value="" placeholder="Ramazan Bayramı"></td>',
            '<td class="kargo-sayaci-admin__date-time"><input type="date" name="' + prefix + '[start_date]" value=""><input type="time" name="' + prefix + '[start_time]" value="00:00"></td>',
            '<td class="kargo-sayaci-admin__date-time"><input type="date" name="' + prefix + '[end_date]" value=""><input type="time" name="' + prefix + '[end_time]" value="23:59"></td>',
            '<td><button type="button" class="button kargo-closure-remove">Sil</button></td>'
        ].join("");

        row.querySelectorAll("input, select, textarea").forEach(function (field) {
            field.addEventListener("input", updatePreview);
            field.addEventListener("change", updatePreview);
        });

        return row;
    }

    function bindClosureControls() {
        if (!closuresTable) {
            return;
        }

        closuresTable.addEventListener("click", function (event) {
            if (!event.target || !event.target.classList.contains("kargo-closure-remove")) {
                return;
            }

            const row = event.target.closest("tr");
            if (row) {
                row.remove();
            }
        });

        if (addClosureButton) {
            addClosureButton.addEventListener("click", function () {
                closuresTable.querySelector("tbody").appendChild(createClosureRow(getNextClosureIndex()));
            });
        }
    }

    form.querySelectorAll("input, select, textarea").forEach(function (field) {
        field.addEventListener("input", updatePreview);
        field.addEventListener("change", updatePreview);
    });

    if (window.jQuery) {
        window.jQuery(document.body).trigger("wc-enhanced-select-init");
    }

    bindClosureControls();
    updatePreview();
})();

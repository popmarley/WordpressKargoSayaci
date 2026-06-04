(function () {
    "use strict";

    const settings = window.KargoSayaciData;
    const elementId = "kargo-sayaci-countdown";
    const visibleRefreshMs = 1000;
    const hiddenRefreshMs = 60000;
    const mountRetryMs = 2000;
    const weekdayMap = {
        Sun: 0,
        Mon: 1,
        Tue: 2,
        Wed: 3,
        Thu: 4,
        Fri: 5,
        Sat: 6
    };

    if (!settings || !settings.enabled) {
        return;
    }

    const state = {
        timerId: 0,
        root: null,
        currentTarget: null,
        countdownTextNode: null,
        brandNode: null,
        textWrap: null,
        normalTextNode: null,
        highlightTextNode: null,
        deliveryNode: null,
        currentVariation: null,
        baseText: String(settings.textBefore || "").trim(),
        needsPlacementSync: true
    };

    let formatter = null;
    if (settings.timezone) {
        try {
            formatter = new Intl.DateTimeFormat("en-US", {
                timeZone: settings.timezone,
                weekday: "short",
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hourCycle: "h23"
            });
        } catch (error) {
            formatter = null;
        }
    }

    function init() {
        bindEvents();
        tick();
    }

    function bindEvents() {
        document.addEventListener("visibilitychange", handleImmediateRefresh);
        document.addEventListener("change", handleFormChange, true);

        if (window.jQuery) {
            window.jQuery(document.body)
                .on("found_variation", function (event, variation) {
                    state.currentVariation = variation || null;
                    handleImmediateRefresh();
                })
                .on("reset_data hide_variation", function () {
                    state.currentVariation = null;
                    handleImmediateRefresh();
                });
        }
    }

    function handleFormChange(event) {
        if (!event.target || !event.target.closest("form.variations_form")) {
            return;
        }

        handleImmediateRefresh();
    }

    function handleImmediateRefresh() {
        state.needsPlacementSync = true;
        clearScheduledTick();
        tick();
    }

    function clearScheduledTick() {
        if (state.timerId) {
            window.clearTimeout(state.timerId);
            state.timerId = 0;
        }
    }

    function scheduleNextTick(delay) {
        clearScheduledTick();
        state.timerId = window.setTimeout(tick, Math.max(250, delay));
    }

    function tick() {
        if (!ensureMounted()) {
            scheduleNextTick(mountRetryMs);
            return;
        }

        if (shouldHideForStock()) {
            hideCountdown();
            scheduleNextTick(hiddenRefreshMs);
            return;
        }

        const nowParts = getNowParts();

        if (isClosedBySpecialPeriod(nowParts)) {
            hideCountdown();
            scheduleNextTick(hiddenRefreshMs);
            return;
        }

        const daySettings = settings.days[String(nowParts.weekday)] || settings.days[nowParts.weekday];

        if (!daySettings || !daySettings.enabled) {
            hideCountdown();
            scheduleNextTick(getNextActiveDelay(nowParts));
            return;
        }

        const startMs = toPseudoUtcMs(nowParts, daySettings.start, 0);
        const endMs = toPseudoUtcMs(nowParts, daySettings.end, 0);
        const nowMs = getCurrentPseudoUtcMs(nowParts);

        if (endMs <= startMs) {
            hideCountdown();
            scheduleNextTick(getNextActiveDelay(nowParts));
            return;
        }

        if (nowMs < startMs) {
            hideCountdown();
            scheduleNextTick(startMs - nowMs);
            return;
        }

        if (nowMs >= endMs) {
            hideCountdown();
            scheduleNextTick(getNextActiveDelay(nowParts));
            return;
        }

        renderCountdown(endMs - nowMs, daySettings);

        if (document.hidden) {
            scheduleNextTick(Math.min(endMs - nowMs, hiddenRefreshMs));
            return;
        }

        scheduleNextTick(visibleRefreshMs);
    }

    function ensureMounted() {
        if (state.root && state.currentTarget && document.body.contains(state.root) && document.body.contains(state.currentTarget) && !state.needsPlacementSync) {
            return true;
        }

        const target = getInsertionTarget();
        if (!target) {
            return false;
        }

        if (!state.root || !document.body.contains(state.root)) {
            createCountdownElement();
        }

        if (state.currentTarget !== target || state.root.previousElementSibling !== target) {
            target.insertAdjacentElement("afterend", state.root);
            state.currentTarget = target;
        }

        state.needsPlacementSync = false;
        return true;
    }

    function createCountdownElement() {
        const root = document.createElement("div");
        const lead = document.createElement("div");
        const body = document.createElement("div");
        const leadIcon = createLeadIcon();
        const countdownTextNode = document.createElement("strong");
        const textWrap = document.createElement("span");
        const normalTextNode = document.createElement("span");
        const highlightTextNode = document.createElement("strong");
        const brandNode = createBrandNode();
        const deliveryNode = createDeliveryNode();

        root.id = elementId;
        root.className = "kargo-sayaci";
        root.style.setProperty("--kargo-bg", settings.style.backgroundColor);
        root.style.setProperty("--kargo-logo-bg", settings.style.boxTextColor);
        root.style.setProperty("--kargo-text", settings.style.textColor);
        root.style.setProperty("--kargo-accent", settings.style.accentTextColor);
        root.style.setProperty("--kargo-today", settings.style.todayTextColor || settings.style.accentTextColor);
        root.style.setProperty("--kargo-card-bg", settings.style.cardBgColor);
        root.style.setProperty("--kargo-body-bg", settings.style.bodyBgColor);
        root.style.setProperty("--kargo-border", settings.style.borderColor || settings.style.backgroundColor);
        root.style.setProperty("--kargo-delivery-label", settings.style.deliveryLabelColor || settings.style.textColor);
        root.style.setProperty("--kargo-delivery-value", settings.style.deliveryValueColor || settings.style.textColor);

        lead.className = "kargo-sayaci__lead";
        body.className = "kargo-sayaci__body";
        countdownTextNode.className = "kargo-sayaci__countdown-text";

        textWrap.className = "kargo-sayaci__text";
        textWrap.appendChild(countdownTextNode);
        textWrap.appendChild(normalTextNode);
        textWrap.appendChild(highlightTextNode);

        lead.appendChild(leadIcon);
        lead.appendChild(textWrap);
        root.appendChild(lead);

        if (brandNode) {
            body.appendChild(brandNode);
        }

        if (deliveryNode) {
            body.appendChild(deliveryNode);
        }

        if (body.childNodes.length) {
            root.appendChild(body);
        }

        state.root = root;
        state.countdownTextNode = countdownTextNode;
        state.brandNode = brandNode;
        state.textWrap = textWrap;
        state.normalTextNode = normalTextNode;
        state.highlightTextNode = highlightTextNode;
        state.deliveryNode = deliveryNode;
    }

    function createLeadIcon() {
        const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        const body = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const cabin = document.createElementNS("http://www.w3.org/2000/svg", "path");
        const wheelOne = document.createElementNS("http://www.w3.org/2000/svg", "circle");
        const wheelTwo = document.createElementNS("http://www.w3.org/2000/svg", "circle");

        svg.setAttribute("class", "kargo-sayaci__lead-icon");
        svg.setAttribute("viewBox", "0 0 24 24");
        svg.setAttribute("aria-hidden", "true");
        svg.setAttribute("focusable", "false");

        body.setAttribute("d", "M3 7h10v8H3z");
        cabin.setAttribute("d", "M13 10h4l3 3v2h-7z");
        wheelOne.setAttribute("cx", "7");
        wheelOne.setAttribute("cy", "17");
        wheelOne.setAttribute("r", "2");
        wheelTwo.setAttribute("cx", "17");
        wheelTwo.setAttribute("cy", "17");
        wheelTwo.setAttribute("r", "2");

        svg.appendChild(body);
        svg.appendChild(cabin);
        svg.appendChild(wheelOne);
        svg.appendChild(wheelTwo);

        return svg;
    }

    function createBrandNode() {
        const image = String(settings.deliveryImage || "").trim();

        if (!image) {
            return null;
        }

        const brand = document.createElement("div");
        const img = document.createElement("img");

        brand.className = "kargo-sayaci__brand";
        img.src = image;
        img.alt = "Yurtiçi Kargo";
        img.loading = "lazy";
        brand.appendChild(img);

        return brand;
    }

    function createDeliveryNode() {
        const text = String(settings.deliveryText || "").trim();

        if (!text) {
            return null;
        }

        const delivery = document.createElement("div");
        const content = document.createElement("div");
        const title = document.createElement("span");
        const label = document.createElement("span");

        delivery.className = "kargo-sayaci__delivery";
        content.className = "kargo-sayaci__delivery-content";
        title.className = "kargo-sayaci__delivery-title";
        title.textContent = "Tahmini Teslim:";

        if (text) {
            label.className = "kargo-sayaci__delivery-value";
            label.textContent = text;
            content.appendChild(title);
            content.appendChild(label);
            delivery.appendChild(content);
        }

        return delivery;
    }

    function renderCountdown(diffMs, daySettings) {
        const totalSeconds = Math.max(0, Math.floor(diffMs / 1000));
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const highlightText = buildHighlightText(daySettings);

        setNodeText(state.countdownTextNode, formatDurationText(hours, minutes, seconds));

        const baseText = state.baseText ? " " + state.baseText + (highlightText ? " " : "") : "";
        setNodeText(state.normalTextNode, baseText);
        renderTemplateText(state.highlightTextNode, String(settings.highlightText || ""), daySettings);
        state.textWrap.style.display = "";

        state.root.style.display = "block";
    }

    function formatDurationText(hours, minutes, seconds) {
        const parts = [];

        if (hours > 0) {
            parts.push(hours + " saat");
        }

        parts.push(minutes + " dakika");
        parts.push(seconds + " saniye");

        return parts.join(" ");
    }

    function setNodeText(node, value) {
        if (node.textContent !== value) {
            node.textContent = value;
        }
    }

    function hideCountdown() {
        if (state.root) {
            state.root.style.display = "none";
        }
    }

    function getNowParts() {
        if (formatter) {
            const parts = formatter.formatToParts(new Date());
            const partMap = {};

            parts.forEach(function (part) {
                partMap[part.type] = part.value;
            });

            return {
                year: Number(partMap.year),
                month: Number(partMap.month),
                day: Number(partMap.day),
                hour: Number(partMap.hour),
                minute: Number(partMap.minute),
                second: Number(partMap.second),
                weekday: weekdayMap[partMap.weekday] ?? new Date().getDay()
            };
        }

        const offsetHours = Number(settings.gmtOffset || 0);
        const now = new Date(Date.now() + offsetHours * 60 * 60 * 1000);

        return {
            year: now.getUTCFullYear(),
            month: now.getUTCMonth() + 1,
            day: now.getUTCDate(),
            hour: now.getUTCHours(),
            minute: now.getUTCMinutes(),
            second: now.getUTCSeconds(),
            weekday: now.getUTCDay()
        };
    }

    function getCurrentPseudoUtcMs(parts) {
        return Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second);
    }

    function toPseudoUtcMs(parts, timeValue, dayOffset) {
        const parsed = parseTimeValue(timeValue);
        return Date.UTC(parts.year, parts.month - 1, parts.day + dayOffset, parsed.hours, parsed.minutes, 0);
    }

    function parseTimeValue(timeValue) {
        const fragments = String(timeValue || "00:00").split(":");
        return {
            hours: Number(fragments[0] || 0),
            minutes: Number(fragments[1] || 0)
        };
    }

    function getNextActiveDelay(nowParts) {
        const nowMs = getCurrentPseudoUtcMs(nowParts);

        for (let dayOffset = 0; dayOffset < 8; dayOffset += 1) {
            const weekday = (nowParts.weekday + dayOffset) % 7;
            const daySettings = settings.days[String(weekday)] || settings.days[weekday];

            if (!daySettings || !daySettings.enabled) {
                continue;
            }

            const startMs = toPseudoUtcMs(nowParts, daySettings.start, dayOffset);
            const endMs = toPseudoUtcMs(nowParts, daySettings.end, dayOffset);

            if (endMs <= startMs || startMs <= nowMs) {
                continue;
            }

            return startMs - nowMs;
        }

        return hiddenRefreshMs;
    }

    function shouldHideForStock() {
        if (state.currentVariation) {
            if (settings.hideBackorder && state.currentVariation.backorders_allowed) {
                return true;
            }

            if (settings.hideOutOfStock && state.currentVariation.is_in_stock === false) {
                return true;
            }

            return false;
        }

        const productStock = settings.productStock || {};

        if (settings.hideBackorder && productStock.backordersAllowed) {
            return true;
        }

        if (settings.hideOutOfStock && productStock.isInStock === false) {
            return true;
        }

        return false;
    }

    function isClosedBySpecialPeriod(nowParts) {
        const closures = Array.isArray(settings.closures) ? settings.closures : [];
        const nowMs = getCurrentPseudoUtcMs(nowParts);

        for (let index = 0; index < closures.length; index += 1) {
            const closure = closures[index];
            const startMs = toPseudoUtcDateTimeMs(closure.startDate, closure.startTime);
            const endMs = toPseudoUtcDateTimeMs(closure.endDate, closure.endTime) + 59999;

            if (startMs && endMs && endMs >= startMs && nowMs >= startMs && nowMs <= endMs) {
                return true;
            }
        }

        return false;
    }

    function toPseudoUtcDateTimeMs(dateValue, timeValue) {
        const dateMatch = String(dateValue || "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
        const timeParts = parseTimeValue(timeValue);

        if (!dateMatch) {
            return 0;
        }

        return Date.UTC(Number(dateMatch[1]), Number(dateMatch[2]) - 1, Number(dateMatch[3]), timeParts.hours, timeParts.minutes, 0);
    }

    function getInsertionTarget() {
        const variationElement = document.querySelector(".woocommerce-variation.single_variation");
        if (variationElement && variationElement.parentNode) {
            return variationElement;
        }

        const stockDetailElement = document.querySelector(".ast-stock-detail");
        const inStockElement = document.querySelector(".stock.in-stock");
        if (stockDetailElement && inStockElement && stockDetailElement.parentNode) {
            return stockDetailElement;
        }

        const variationTableElement = document.querySelector("table.variations");
        if (variationTableElement && variationTableElement.parentNode) {
            return variationTableElement;
        }

        return null;
    }

    function buildHighlightText(daySettings) {
        const todayLabel = daySettings.showToday ? String(daySettings.todayLabel || "bugün").trim() : "";
        const template = String(settings.highlightText || "");

        return template
            .replace(/\{\{\s*today\s*\}\}/gi, todayLabel)
            .replace(/\s+/g, " ")
            .trim();
    }

    function renderTemplateText(node, template, daySettings) {
        const todayLabel = daySettings.showToday ? String(daySettings.todayLabel || "bugün").trim() : "";
        const pattern = /\{\{\s*today\s*\}\}/gi;
        let cursor = 0;
        let match = pattern.exec(template);

        node.textContent = "";

        while (match) {
            appendText(node, template.slice(cursor, match.index));

            if (todayLabel) {
                const todayNode = document.createElement("span");
                todayNode.className = "kargo-sayaci__today";
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

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

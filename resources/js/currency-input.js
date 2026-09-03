const parseIdNumber = (value) => {
    if (value === null || value === undefined || value === "") return null;
    if (typeof value === "number") return Number.isFinite(value) ? value : null;

    let text = String(value).trim().replace(/[^\d,.-]/g, "");
    if (!text) return null;

    const negative = text.startsWith("-");
    text = text.replace(/-/g, "");

    if (text.includes(",")) {
        text = text.replace(/\./g, "").replace(",", ".");
    } else if (text.includes(".")) {
        const parts = text.split(".");
        const lastPart = parts[parts.length - 1];
        text = lastPart.length <= 2 ? parts.slice(0, -1).join("") + "." + lastPart : parts.join("");
    }

    const number = Number(text);
    if (!Number.isFinite(number)) return null;
    return negative ? -number : number;
};

const formatIdNumber = (value, decimals = 0) => {
    const number = parseIdNumber(value);
    if (number === null) return "";

    return new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    }).format(number);
};

const shouldFormatAsCurrency = (input) => {
    const toggleId = input.dataset.currencyToggle;
    if (!toggleId) return true;
    return document.getElementById(toggleId)?.value === "nominal";
};

const normalizeCurrencyInput = (input, format = true) => {
    const number = parseIdNumber(input.value);
    if (number === null) {
        input.dataset.rawValue = "";
        return;
    }

    input.dataset.rawValue = String(number);
    if (format && shouldFormatAsCurrency(input)) {
        input.value = formatIdNumber(number, Number(input.dataset.currencyDecimals || 0));
    } else {
        input.value = String(number);
    }
};

const initializeCurrencyInput = (input) => {
    if (!input.dataset.currencyInitialized) {
        input.dataset.currencyInitialized = "true";
    }
    normalizeCurrencyInput(input, true);
};

const initializeCurrencyInputs = (root = document) => {
    const inputs = root.matches?.("[data-currency-input]")
        ? [root]
        : root.querySelectorAll?.("[data-currency-input]") || [];
    inputs.forEach(initializeCurrencyInput);
};

document.addEventListener("input", (event) => {
    if (event.target.matches?.("[data-currency-input]")) {
        normalizeCurrencyInput(event.target, true);
    }
}, true);

document.addEventListener("change", (event) => {
    if (event.target.matches?.("[data-currency-input]")) {
        normalizeCurrencyInput(event.target, true);
    }
}, true);

document.addEventListener("submit", (event) => {
    event.target.querySelectorAll?.("[data-currency-input]").forEach((input) => {
        normalizeCurrencyInput(input, false);
    });
}, true);

document.addEventListener("DOMContentLoaded", () => initializeCurrencyInputs());

window.initCurrencyInputs = initializeCurrencyInputs;
window.parseIdNumber = parseIdNumber;
window.formatIdNumber = formatIdNumber;

// utils.js
export const formatRupiah = (value) => {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
    }).format(value);
};

export const parseIdNumber = (value) => {
    if (value === null || value === undefined || value === "") return 0;
    if (typeof value === "number") return Number.isFinite(value) ? value : 0;

    let text = String(value).trim().replace(/[^\d,.-]/g, "");
    if (!text) return 0;

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
    return Number.isFinite(number) ? (negative ? -number : number) : 0;
};

export const formatIdNumber = (value) => {
    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 0,
    }).format(parseIdNumber(value));
};

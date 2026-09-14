import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from "react";
import axios from "axios";
import CreatableSelect from "react-select/creatable";
import { selectStyles } from "./ReactSelectField";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const normalizedType = (value) => String(value || "").trim().toLowerCase();
const configuredBundleDiscount = (promotion) => Number(promotion.bundle_discount ?? promotion.bundle_price ?? 0);

const bundleDiscountAmount = (promotion, selectedProducts = []) => {
    if (normalizedType(promotion.type) !== "bundle" || (promotion.bundle_discount == null && promotion.bundle_price == null)) {
        return null;
    }

    const quantities = new Map(selectedProducts.map((product) => [
        Number(product.id),
        Number(product.qty || 0),
    ]));
    const unitPrices = new Map(selectedProducts.map((product) => {
        const quantity = Number(product.qty || 0);
        const baseSubtotal = Number(product.baseSubtotal ?? product.base_subtotal ?? 0);
        return [Number(product.id), quantity > 0 ? Math.round(baseSubtotal / quantity) : 0];
    }));
    const rules = promotion.products || [];
    if (!rules.length || rules.some((rule) => !unitPrices.has(Number(rule.id)))) return null;

    const bundleCount = rules.reduce((count, rule) => Math.min(
        count,
        Math.floor((quantities.get(Number(rule.id)) || 0) / Number(rule.required_qty || 1)),
    ), Number.POSITIVE_INFINITY);
    const limitedBundleCount = promotion.max_qty
        ? Math.min(bundleCount, Number(promotion.max_qty))
        : bundleCount;
    if (!Number.isFinite(limitedBundleCount) || limitedBundleCount <= 0) return null;

    const bundleBasis = rules.reduce((total, rule) => total + (
        (unitPrices.get(Number(rule.id)) || 0) * Number(rule.required_qty || 1)
    ), 0);
    const discountPerBundle = Math.min(bundleBasis, Math.max(0, Math.round(configuredBundleDiscount(promotion))));

    return discountPerBundle * limitedBundleCount;
};

const dateRange = (item) => {
    if (!item.start_at && !item.end_at) return "";
    const format = (value) => value
        ? new Intl.DateTimeFormat("id-ID", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(new Date(value))
        : "-";

    return ` (${format(item.start_at)}–${format(item.end_at)})`;
};

const describePromotion = (promotion, selectedProducts = []) => {
    const products = (promotion.products || [])
        .map((product) => `${product.required_qty > 1 ? `${product.required_qty}x ` : ""}${product.name}`)
        .join(" + ");

    if (normalizedType(promotion.type) === "bundle") {
        const discount = bundleDiscountAmount(promotion, selectedProducts);
        const bonuses = (promotion.bonuses || []).map((bonus) => `${bonus.qty}x ${bonus.name}`).join(", ");
        const bonusText = bonuses ? ` — bonus: ${bonuses}` : "";
        return discount === null
            ? `${products || "Qualifying products"} — save ${money(configuredBundleDiscount(promotion))}${bonusText}`
            : `${products || "Qualifying products"} — save ${money(discount)}${bonusText}`;
    }

    const discount = normalizedType(promotion.discount_type) === "percentage"
        ? `${promotion.discount_value}%`
        : normalizedType(promotion.discount_type) === "fixed_price"
            ? `harga ${money(promotion.discount_value)}`
            : money(promotion.discount_value);

    return `${products || "Produk pilihan"} — hemat ${discount}${dateRange(promotion)}`;
};

const describeVoucher = (voucher) => {
    const discount = normalizedType(voucher.type) === "percentage"
        ? `${voucher.value}%`
        : money(voucher.value);
    const minimum = Number(voucher.min_purchase || 0) > 0 ? `, min. ${money(voucher.min_purchase)}` : "";

    return `${voucher.name || "Voucher"} — hemat ${discount}${minimum}${dateRange(voucher)}`;
};

const promotionMatchesCart = (promotion, selectedProducts) => {
    if (!selectedProducts.length) return false;

    const quantities = new Map(selectedProducts.map((product) => [Number(product.id), Number(product.qty || 0)]));
    const rules = promotion.products || [];

    if (promotion.type === "bundle") {
        return rules.length > 0 && rules.every((rule) => quantities.get(Number(rule.id)) >= Number(rule.required_qty || 1));
    }

    return rules.some((rule) => quantities.get(Number(rule.id)) > 0);
};

const AppliedDiscountTable = forwardRef(({ promotions, vouchers, appliedPromotionNames, onRemovePromotion, onRemoveVoucher, selectedProducts }, ref) => {
    const rows = [
        ...promotions.map((promotion) => ({
            key: `promotion:${promotion.code}`,
            kind: "Promo",
            code: promotion.code,
            detail: `${promotion.name} — ${describePromotion(promotion, selectedProducts)}`,
            status: appliedPromotionNames.includes(promotion.name) ? "Ready" : "Waiting for qualifying items",
            remove: () => onRemovePromotion(promotion.code),
        })),
        ...vouchers.map((voucher) => ({
            key: `voucher:${voucher.code}`,
            kind: "Voucher",
            code: voucher.code,
            detail: `${voucher.name || "Voucher"} — ${describeVoucher(voucher)}`,
            status: Number(voucher.min_purchase || 0) > selectedProducts.reduce((sum, item) => sum + Number(item.baseSubtotal || 0), 0)
                ? `Minimum ${money(voucher.min_purchase)} not reached`
                : "Ready",
            remove: () => onRemoveVoucher(voucher.code),
        })),
    ];
    const rowRefs = useRef([]);
    const [selectedKey, setSelectedKey] = useState(rows[0]?.key || null);

    useEffect(() => {
        if (!rows.some((row) => row.key === selectedKey)) setSelectedKey(rows[0]?.key || null);
    }, [rows, selectedKey]);

    const focusRow = (index) => {
        if (!rows.length) return;
        const nextIndex = Math.max(0, Math.min(rows.length - 1, index));
        setSelectedKey(rows[nextIndex].key);
        window.requestAnimationFrame(() => rowRefs.current[nextIndex]?.focus());
    };

    useImperativeHandle(ref, () => ({ focusFirstRow: () => focusRow(0) }), [rows]);

    const handleRowKeyDown = (event, index, row) => {
        if (event.key === "ArrowDown" || event.key === "ArrowUp" || event.key === "Home" || event.key === "End") {
            event.preventDefault();
            const nextIndex = event.key === "Home" ? 0 : event.key === "End" ? rows.length - 1 : index + (event.key === "ArrowDown" ? 1 : -1);
            focusRow(nextIndex);
            return;
        }
        if (event.key === "Enter") {
            event.preventDefault();
            event.currentTarget.querySelector("button")?.focus();
            return;
        }
        if (event.key === "Delete" || (event.ctrlKey && event.key === "Backspace")) {
            event.preventDefault();
            event.stopPropagation();
            row.remove();
        }
    };

    if (!rows.length) return null;

    return (
        <div className="table-responsive text-nowrap" style={{ marginTop: 8 }}>
            <table className="table table-sm table-bordered" style={{ marginBottom: 0 }}>
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Kode</th>
                        <th>Detail</th>
                        <th>Status</th>
                        <th className="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr
                            key={row.key}
                            ref={(element) => { rowRefs.current[index] = element; }}
                            tabIndex="0"
                            className={`${selectedKey === row.key ? "info " : ""}pos-promotion-row`}
                            onClick={() => setSelectedKey(row.key)}
                            onFocus={() => setSelectedKey(row.key)}
                            onKeyDown={(event) => handleRowKeyDown(event, index, row)}
                            title="↑/↓ pilih promo · Home/End pindah · Enter fokus hapus · Delete hapus"
                        >
                            <td>{row.kind}</td>
                            <td><strong>{row.code}</strong></td>
                            <td>{row.detail}</td>
                            <td>{row.status}</td>
                            <td className="text-center">
                                <button type="button" className="btn btn-danger btn-xs" onClick={row.remove}>
                                    <i className="fa fa-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
});

const Vouchers = ({
    appliedVouchers,
    onAddVoucher,
    onRemoveVoucher,
    appliedPromotions = [],
    onAddPromotion,
    onRemovePromotion,
    inputRef,
    outletId,
    appliedPromotionNames = [],
    selectedProducts = [],
    promotionTableRef,
}) => {
    const selectRef = useRef(null);
    const voucherCodeRef = useRef(null);
    const [availablePromotions, setAvailablePromotions] = useState([]);
    const [error, setError] = useState("");
    const [info, setInfo] = useState("");

    const cartBase = selectedProducts.reduce((sum, item) => sum + Number(item.baseSubtotal || 0), 0);
    const eligiblePromotions = availablePromotions.filter((promotion) => promotionMatchesCart(promotion, selectedProducts)
        && cartBase >= Number(promotion.min_purchase || 0));
    useImperativeHandle(inputRef, () => ({
        focus: () => voucherCodeRef.current?.focus(),
    }), []);

    useEffect(() => {
        axios.get("/voucher/options?outlet_id=" + encodeURIComponent(outletId || ""))
            .then((response) => {
                setAvailablePromotions(response.data.promotions || []);
            })
            .catch(() => setError("Daftar voucher dan promo tidak dapat dimuat."));
    }, [outletId]);

    const lookupCode = (value) => {
        const code = String(value || "").trim().toUpperCase();
        if (!code) return;
        axios.get("/voucher/lookup?code=" + encodeURIComponent(code) + "&outlet_id=" + encodeURIComponent(outletId || ""))
            .then((response) => {
                const result = response.data;
                if (result.kind === "promotion") {
                    onAddPromotion(result);
                    setInfo(`Promo ${result.code} dipasang. Promo dihitung setelah syarat item terpenuhi.`);
                } else {
                    onAddVoucher(result);
                    setInfo(`Voucher ${result.code} dipasang.`);
                }
                setError("");
            })
            .catch((requestError) => {
                setInfo("");
                setError(requestError.response?.data?.message || "Voucher atau promo tidak dapat digunakan.");
            });
    };

    const handleChange = (selected) => {
        if (!selected) return;
        if (selected.kind === "promotion") {
            onAddPromotion(selected.item);
            setInfo(`Promo ${selected.item.code} dipasang. Promo dihitung setelah syarat item terpenuhi.`);
            setError("");
            return;
        }
        if (selected.kind === "voucher") {
            onAddVoucher(selected.item);
            setInfo(`Voucher ${selected.item.code} dipasang.`);
            setError("");
            return;
        }
        lookupCode(selected.value);
    };

    return (
        <div className="form-group" style={{ marginTop: 12 }}>
            <label style={{ marginBottom: 5 }}>Voucher barcode <small>(F8 · scan only)</small></label>
            <div className="input-group">
                <input ref={voucherCodeRef} type="text" className="form-control" placeholder="Scan voucher barcode and press Enter" onKeyDown={(event) => { if (event.key === "Enter") { event.preventDefault(); lookupCode(event.currentTarget.value); event.currentTarget.value = ""; } }} />
                <span className="input-group-btn"><button type="button" className="btn btn-primary" onClick={() => { const value = voucherCodeRef.current?.value || ""; lookupCode(value); if (voucherCodeRef.current) voucherCodeRef.current.value = ""; }}>Apply</button></span>
            </div>
            <small className="text-muted">Voucher is separate from Rafaksi and bundle promotions. Scan the voucher code to apply it.</small>

            <label style={{ marginTop: 14, marginBottom: 5 }}>Active promotions</label>
            <div className="well well-sm" style={{ marginBottom: 6 }}>
                {eligiblePromotions.length === 0 && <span className="text-muted">Add qualifying products to see selectable promotions.</span>}
                {eligiblePromotions.map((promotion) => <button type="button" key={`choose-${promotion.code}`} className="btn btn-warning btn-sm" style={{ margin: 3 }} onClick={() => { onAddPromotion(promotion); setInfo(`Promotion ${promotion.code} selected.`); }}><i className="fa fa-check"></i> {promotion.name}</button>)}
            </div>
            <CreatableSelect
                ref={selectRef}
                options={eligiblePromotions.length ? [{ label: "Select another promotion", options: eligiblePromotions.map((promotion) => ({ value: promotion.code, label: `${promotion.code} — ${promotion.name} — ${describePromotion(promotion, selectedProducts)}`, kind: "promotion", item: promotion })) }] : []}
                value={null}
                onChange={handleChange}
                onCreateOption={lookupCode}
                isClearable
                isSearchable
                formatCreateLabel={(value) => `Use promotion code “${value}”`}
                placeholder="Optional: type a promotion code"
                styles={{
                    ...selectStyles,
                    control: (base, state) => ({ ...selectStyles.control(base, state), minHeight: 42, fontSize: 14 }),
                }}
                menuPortalTarget={document.body}
                menuPosition="fixed"
                noOptionsMessage={() => "No qualifying promotion found"}
            />
            {error && <div><small className="text-danger">{error}</small></div>}
            {info && <div><small className="text-success">{info}</small></div>}

            <AppliedDiscountTable
                ref={promotionTableRef}
                promotions={appliedPromotions}
                vouchers={appliedVouchers}
                appliedPromotionNames={appliedPromotionNames}
                onRemovePromotion={onRemovePromotion}
                onRemoveVoucher={onRemoveVoucher}
                selectedProducts={selectedProducts}
            />
        </div>
    );
};

export default Vouchers;

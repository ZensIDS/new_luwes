import React, { useEffect, useImperativeHandle, useRef, useState } from "react";
import axios from "axios";

const money = (value) => "Rp " + Number(value || 0).toLocaleString("id-ID");
const normalizedType = (value) => String(value || "").trim().toLowerCase();

const dateRange = (item) => {
    if (!item.start_at && !item.end_at) return "";
    const format = (value) => value
        ? new Intl.DateTimeFormat("id-ID", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(new Date(value))
        : "-";

    return " (" + format(item.start_at) + "–" + format(item.end_at) + ")";
};

const configuredPromotionText = (promotion) => {
    if (normalizedType(promotion.type) === "bundle") {
        const discount = Number(promotion.bundle_price || promotion.bundle_discount || 0);
        const bonuses = (promotion.bonuses || []).map((bonus) => bonus.qty + "x " + bonus.name).join(", ");
        const parts = [];
        if (discount > 0) parts.push("hemat " + money(discount) + " per bundling");
        if (bonuses) parts.push("bonus: " + bonuses);
        return parts.join(" · ") || "bonus bundling";
    }

    if (normalizedType(promotion.discount_type) === "percentage") {
        return "diskon " + promotion.discount_value + "%";
    }
    if (normalizedType(promotion.discount_type) === "fixed_price") {
        return "harga " + money(promotion.discount_value);
    }

    return "hemat " + money(promotion.discount_value);
};

const describeVoucher = (voucher) => {
    const discount = normalizedType(voucher.type) === "percentage"
        ? "diskon " + voucher.value + "%"
        : "hemat " + money(voucher.value);
    const minimum = Number(voucher.min_purchase || 0) > 0 ? ", minimal " + money(voucher.min_purchase) : "";

    return (voucher.name || "Voucher") + " — " + discount + minimum + dateRange(voucher);
};

const promotionAmount = (promotion, selectedProducts) => selectedProducts.reduce(
    (sum, product) => sum + (product.promotionBreakdown || [])
        .filter((detail) => detail.promotion_code === promotion.code || Number(detail.promotion_id) === Number(promotion.id))
        .reduce((detailSum, detail) => detailSum + Number(detail.amount || 0), 0),
    0
);

const promotionCondition = (promotion, selectedProducts, cartBase) => {
    if (!selectedProducts.length) {
        return { eligible: false, text: "Belum ada produk di transaksi." };
    }

    const quantities = new Map(selectedProducts.map((product) => [
        Number(product.id),
        Number(product.qty || 0),
    ]));
    const rules = promotion.products || [];
    const missing = [];

    if (!rules.length) {
        missing.push("Produk promo belum dikonfigurasi.");
    } else if (normalizedType(promotion.type) === "bundle") {
        rules.forEach((rule) => {
            const required = Math.ceil(Number(rule.required_qty || 1));
            const available = quantities.get(Number(rule.id)) || 0;
            if (available < required) {
                missing.push("Kurang " + (required - available) + "x " + (rule.name || "produk #" + rule.id));
            }
        });
    } else if (!rules.some((rule) => (quantities.get(Number(rule.id)) || 0) > 0)) {
        missing.push("Butuh: " + rules.map((rule) => rule.name || "produk #" + rule.id).join(", "));
    }

    const minimum = Number(promotion.min_purchase || 0);
    if (cartBase < minimum) {
        missing.push("Kurang " + money(minimum - cartBase) + " dari minimal pembelian " + money(minimum));
    }

    if (missing.length) {
        return { eligible: false, text: "Belum memenuhi: " + missing.join(" · ") };
    }

    const amount = promotionAmount(promotion, selectedProducts);
    return {
        eligible: true,
        text: amount > 0
            ? "Syarat terpenuhi — hemat " + money(amount)
            : "Syarat terpenuhi — menunggu perhitungan item promo",
    };
};

const modalStyle = {
    position: "fixed",
    inset: 0,
    background: "rgba(0, 0, 0, .45)",
    zIndex: 1050,
    padding: "5vh 15px",
};

const panelStyle = {
    background: "#fff",
    maxWidth: 980,
    maxHeight: "90vh",
    margin: "0 auto",
    overflow: "auto",
    borderRadius: 4,
    boxShadow: "0 8px 30px rgba(0,0,0,.3)",
};

const Vouchers = ({
    appliedVouchers = [],
    onAddVoucher,
    onRemoveVoucher,
    appliedPromotions = [],
    onAddPromotion,
    outletId,
    inputRef,
    selectedProducts = [],
    promotionTableRef,
    voucherBreakdown = [],
    onSyncPromotions,
}) => {
    const voucherCodeRef = useRef(null);
    const syncSignature = useRef(null);
    const [availableVouchers, setAvailableVouchers] = useState([]);
    const [availablePromotions, setAvailablePromotions] = useState([]);
    const [voucherModalOpen, setVoucherModalOpen] = useState(false);
    const [promotionModalOpen, setPromotionModalOpen] = useState(false);
    const [voucherSearch, setVoucherSearch] = useState("");
    const [promotionSearch, setPromotionSearch] = useState("");
    const [error, setError] = useState("");
    const [info, setInfo] = useState("");

    const cartBase = selectedProducts.reduce((sum, item) => sum + Number(item.baseSubtotal || 0), 0);

    useImperativeHandle(inputRef, () => ({
        focus: () => {
            setVoucherModalOpen(true);
            window.setTimeout(() => voucherCodeRef.current?.focus(), 0);
        },
    }), []);

    useImperativeHandle(promotionTableRef, () => ({
        open: () => setPromotionModalOpen(true),
    }), []);

    useEffect(() => {
        syncSignature.current = null;
        axios.get("/voucher/options?outlet_id=" + encodeURIComponent(outletId || ""))
            .then((response) => {
                const vouchers = response.data.vouchers || [];
                const promotions = response.data.promotions || [];
                setAvailableVouchers(vouchers);
                setAvailablePromotions(promotions);

                const signature = promotions.map((promotion) => promotion.code).sort().join("|");
                if (signature !== syncSignature.current) {
                    syncSignature.current = signature;
                    onSyncPromotions?.(promotions);
                }
            })
            .catch(() => setError("Daftar voucher dan promo tidak dapat dimuat."));
    }, [outletId]);

    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key !== "Escape") return;
            setVoucherModalOpen(false);
            setPromotionModalOpen(false);
        };
        window.addEventListener("keydown", closeOnEscape);

        return () => window.removeEventListener("keydown", closeOnEscape);
    }, []);

    const lookupCode = (value) => {
        const code = String(value || "").trim().toUpperCase();
        if (!code) return;
        axios.get("/voucher/lookup?code=" + encodeURIComponent(code) + "&outlet_id=" + encodeURIComponent(outletId || ""))
            .then((response) => {
                const result = response.data;
                if (result.kind === "promotion") {
                    onAddPromotion?.(result);
                    setInfo("Promo " + result.code + " tersedia dan akan dihitung bila syarat terpenuhi.");
                } else {
                    onAddVoucher?.(result);
                    setInfo("Voucher " + result.code + " dipasang.");
                }
                setError("");
                setVoucherSearch("");
            })
            .catch((requestError) => {
                setInfo("");
                setError(requestError.response?.data?.message || "Voucher atau promo tidak dapat digunakan.");
            });
    };

    const filteredVouchers = availableVouchers.filter((voucher) => {
        const term = voucherSearch.trim().toLowerCase();
        return !term || (voucher.code + " " + (voucher.name || "")).toLowerCase().includes(term);
    });
    const filteredPromotions = availablePromotions.filter((promotion) => {
        const term = promotionSearch.trim().toLowerCase();
        return !term || (promotion.code + " " + (promotion.name || "")).toLowerCase().includes(term);
    });

    const renderVoucherModal = () => voucherModalOpen && (
        <div style={modalStyle} role="dialog" aria-modal="true" onClick={() => setVoucherModalOpen(false)}>
            <div style={panelStyle} onClick={(event) => event.stopPropagation()}>
                <div className="box-header with-border">
                    <button type="button" className="close" onClick={() => setVoucherModalOpen(false)} aria-label="Tutup">&times;</button>
                    <h3 className="box-title"><i className="fa fa-ticket"></i> Cari / Scan Voucher <small>(F8)</small></h3>
                </div>
                <div className="box-body">
                    <div className="input-group">
                        <input
                            ref={voucherCodeRef}
                            type="text"
                            className="form-control input-lg"
                            value={voucherSearch}
                            onChange={(event) => setVoucherSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === "Enter") {
                                    event.preventDefault();
                                    lookupCode(voucherSearch);
                                }
                            }}
                            placeholder="Scan atau ketik kode voucher lalu Enter"
                            autoComplete="off"
                        />
                        <span className="input-group-btn">
                            <button type="button" className="btn btn-primary btn-lg" onClick={() => lookupCode(voucherSearch)}>Pakai</button>
                        </span>
                    </div>
                    <p className="text-muted small">Voucher dipilih manual atau melalui scanner. Promo rafaksi/bundling aktif otomatis bila syaratnya terpenuhi.</p>

                    {appliedVouchers.length > 0 && (
                        <div className="alert alert-success">
                            <strong>Voucher terpasang:</strong>
                            <ul style={{ margin: "6px 0 0 18px" }}>
                                {appliedVouchers.map((voucher) => {
                                    const amount = voucherBreakdown.find((item) => item.code === voucher.code)?.amount || 0;
                                    return (
                                        <li key={voucher.code}>
                                            {voucher.code} — potongan {money(amount)}
                                            <button type="button" className="btn btn-link btn-xs text-danger" onClick={() => onRemoveVoucher(voucher.code)}>Hapus</button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    )}

                    <h4>Daftar voucher aktif</h4>
                    <div className="table-responsive">
                        <table className="table table-bordered table-striped table-condensed">
                            <thead><tr><th>Kode</th><th>Voucher</th><th>Syarat</th><th>Aksi</th></tr></thead>
                            <tbody>
                                {filteredVouchers.map((voucher) => {
                                    const used = appliedVouchers.some((item) => item.code === voucher.code);
                                    return (
                                        <tr key={voucher.code}>
                                            <td><strong>{voucher.code}</strong></td>
                                            <td>{voucher.name}</td>
                                            <td>{describeVoucher(voucher)}</td>
                                            <td>
                                                <button type="button" className="btn btn-primary btn-xs" disabled={used} onClick={() => onAddVoucher(voucher)}>
                                                    {used ? "Terpasang" : "Pakai"}
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                                {!filteredVouchers.length && <tr><td colSpan="4" className="text-center text-muted">Voucher tidak ditemukan.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );

    const renderPromotionModal = () => promotionModalOpen && (
        <div style={modalStyle} role="dialog" aria-modal="true" onClick={() => setPromotionModalOpen(false)}>
            <div style={panelStyle} onClick={(event) => event.stopPropagation()}>
                <div className="box-header with-border">
                    <button type="button" className="close" onClick={() => setPromotionModalOpen(false)} aria-label="Tutup">&times;</button>
                    <h3 className="box-title"><i className="fa fa-bolt"></i> Status Promo Aktif <small>(F6)</small></h3>
                </div>
                <div className="box-body">
                    <p className="text-muted">Semua promo aktif dipantau otomatis. Promo yang syaratnya belum terpenuhi tidak memblokir transaksi.</p>
                    <input
                        type="text"
                        className="form-control"
                        value={promotionSearch}
                        onChange={(event) => setPromotionSearch(event.target.value)}
                        placeholder="Cari nama atau kode promo"
                    />
                    <div className="table-responsive" style={{ marginTop: 10 }}>
                        <table className="table table-bordered table-striped table-condensed">
                            <thead>
                                <tr><th>Promo</th><th>Jenis</th><th>Syarat</th><th>Status</th><th>Potongan</th></tr>
                            </thead>
                            <tbody>
                                {filteredPromotions.map((promotion) => {
                                    const condition = promotionCondition(promotion, selectedProducts, cartBase);
                                    const amount = promotionAmount(promotion, selectedProducts);
                                    const active = appliedPromotions.some((item) => item.code === promotion.code);
                                    return (
                                        <tr key={promotion.code} className={condition.eligible ? "success" : ""}>
                                            <td><strong>{promotion.name}</strong><br /><small>{promotion.code}</small></td>
                                            <td>{promotion.type === "flash_sale" ? "Rafaksi" : "Bundling"}</td>
                                            <td>{condition.eligible ? configuredPromotionText(promotion) : condition.text}</td>
                                            <td>
                                                <span className={condition.eligible ? "text-success" : "text-warning"}>
                                                    {active ? "Aktif otomatis" : "Tidak dipilih"}
                                                </span>
                                                {!condition.eligible && <><br /><small className="text-danger">{condition.text}</small></>}
                                            </td>
                                            <td>{amount > 0 ? "Hemat " + money(amount) : (condition.eligible ? configuredPromotionText(promotion) : "Belum digunakan")}</td>
                                        </tr>
                                    );
                                })}
                                {!filteredPromotions.length && <tr><td colSpan="5" className="text-center text-muted">Tidak ada promo aktif.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <>
            <div className="row" style={{ marginTop: 12 }}>
                <div className="col-sm-6">
                    <button type="button" className="btn btn-default btn-block" onClick={() => setVoucherModalOpen(true)}>
                        <i className="fa fa-ticket"></i> Voucher <small>(F8)</small>
                    </button>
                </div>
                <div className="col-sm-6">
                    <button type="button" className="btn btn-warning btn-block" onClick={() => setPromotionModalOpen(true)}>
                        <i className="fa fa-bolt"></i> Promo aktif <small>(F6)</small>
                    </button>
                </div>
            </div>
            {info && <div><small className="text-success">{info}</small></div>}
            {error && <div><small className="text-danger">{error}</small></div>}
            {renderVoucherModal()}
            {renderPromotionModal()}
        </>
    );
};

export default Vouchers;

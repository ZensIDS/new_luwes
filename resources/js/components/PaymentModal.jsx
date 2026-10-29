import React, { useEffect, useRef, useState } from "react";
import { SHOW_PAYMENT_REFERENCE, formatIdNumber, formatRupiah, parseIdNumber } from "../utils";

// Tunai = tanpa metode terpilih (payment_method_id kosong), selalu di urutan pertama.
const CASH_METHOD = { id: "", name: "Tunai", charge_percent: 0, is_fixed_amount: false };

const percentLabel = (value) => String(Number(value || 0)).replace(".", ",");

/**
 * Pop up pembayaran (Ctrl+Enter / Cmd+Enter).
 * - Metode: klik, Alt+1..9 (Option+1..9 di Mac) untuk langsung memilih, F7 / Shift+F7 untuk bergeser.
 * - Metode "nominal fix": uang diterima terisi otomatis (total + biaya), tinggal Enter.
 * - Metode dengan biaya tambahan (%): biaya dihitung dari total belanja dan ditampilkan.
 * - Enter = proses transaksi lalu cetak nota. Esc = tutup.
 */
const PaymentModal = ({ open, grandTotal, paymentMethods, initialMethodId, initialReference, initialPaid, onConfirm, onClose }) => {
    const [methodId, setMethodId] = useState("");
    const [reference, setReference] = useState("");
    const [paid, setPaid] = useState("");
    const [error, setError] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const paidRef = useRef(null);
    const referenceRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        setMethodId(initialMethodId === null || initialMethodId === undefined ? "" : String(initialMethodId));
        setReference(initialReference || "");
        setPaid(initialPaid || "");
        setError("");
        setSubmitting(false);
        const focusTimer = window.setTimeout(() => {
            paidRef.current?.focus();
            paidRef.current?.select?.();
        }, 0);
        return () => window.clearTimeout(focusTimer);
    }, [open]);

    if (!open) return null;

    const methods = [CASH_METHOD, ...paymentMethods];
    const currentIndex = Math.max(0, methods.findIndex((method) => String(method.id) === String(methodId)));
    const method = methods[currentIndex];
    const isCash = !method.id || /tunai|cash/i.test(method.name || "");
    const feePercent = Number(method.charge_percent || 0);
    // Pembulatan biaya ke rupiah penuh; server menghitung ulang dengan cara yang sama.
    const fee = feePercent > 0 ? Math.round(grandTotal * feePercent / 100) : 0;
    const amountDue = grandTotal + fee;
    const fixed = Boolean(method.id) && Boolean(method.is_fixed_amount);

    const paidValue = fixed ? amountDue : parseIdNumber(paid);
    const paidText = fixed ? formatIdNumber(amountDue) : paid;
    const enough = fixed || (paid.trim() !== "" && paidValue >= amountDue);
    const change = Math.max(0, paidValue - amountDue);
    const shortage = Math.max(0, amountDue - paidValue);
    const idle = !fixed && !paid.trim();

    const pickMethod = (index) => {
        if (submitting || !methods[index]) return;
        setMethodId(String(methods[index].id));
        setError("");
        window.setTimeout(() => paidRef.current?.focus(), 0);
    };

    const confirm = () => {
        if (submitting) return;
        if (!fixed) {
            if (!paid.trim()) {
                setError("Isi nominal uang diterima.");
                paidRef.current?.focus();
                return;
            }
            if (paidValue < amountDue) {
                setError(`Uang diterima kurang ${formatRupiah(shortage)}.`);
                paidRef.current?.focus();
                return;
            }
        }
        if (SHOW_PAYMENT_REFERENCE && !isCash && !reference.trim()) {
            setError("Nomor referensi wajib diisi untuk metode pembayaran ini.");
            referenceRef.current?.focus();
            return;
        }

        setError("");
        setSubmitting(true);
        Promise.resolve(onConfirm({
            paidValue,
            methodId: method.id === "" ? "" : method.id,
            methodName: method.name || "Tunai",
            reference: SHOW_PAYMENT_REFERENCE && !isCash ? reference.trim() : "",
        })).catch((err) => {
            // Gagal: modal tetap terbuka supaya kasir bisa memperbaiki dan menekan Enter lagi.
            setSubmitting(false);
            setError(err?.message || "Checkout gagal.");
            paidRef.current?.focus();
        });
        // Bila berhasil, halaman langsung berpindah ke nota (cetak otomatis), jadi modal dibiarkan "Memproses...".
    };

    const handleKeyDown = (event) => {
        if (event.key === "Escape") {
            event.preventDefault();
            event.stopPropagation();
            if (!submitting) onClose();
            return;
        }

        // Alt+1..9 (event.code: tetap benar di Mac walau Option+angka menghasilkan simbol lain).
        const digit = event.altKey ? /^(?:Digit|Numpad)([1-9])$/.exec(event.code || "") : null;
        if (digit) {
            event.preventDefault();
            event.stopPropagation();
            pickMethod(Number(digit[1]) - 1);
            return;
        }

        if (event.key === "F7") {
            event.preventDefault();
            event.stopPropagation();
            pickMethod((currentIndex + (event.shiftKey ? -1 : 1) + methods.length) % methods.length);
            return;
        }

        if (event.key === "Enter") {
            if (event.target?.tagName === "BUTTON") return;
            event.preventDefault();
            event.stopPropagation();
            confirm();
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            onKeyDown={handleKeyDown}
            style={{ position: "fixed", inset: 0, zIndex: 1040, background: "rgba(0,0,0,.45)", padding: "6vh 15px" }}
        >
            <div style={{ background: "#fff", maxWidth: 500, margin: "0 auto", borderRadius: 4, boxShadow: "0 8px 30px rgba(0,0,0,.3)" }}>
                <div className="box-header with-border">
                    <button type="button" className="close" onClick={() => !submitting && onClose()} aria-label="Tutup">&times;</button>
                    <h3 className="box-title"><i className="fa fa-money"></i> Pembayaran</h3>
                </div>
                <div className="box-body">
                    <div style={{ textAlign: "center", marginBottom: 12 }}>
                        <div className="text-muted" style={{ fontSize: 12 }}>Total Bayar</div>
                        <div style={{ fontSize: 32, fontWeight: 700 }}>{formatRupiah(amountDue)}</div>
                        {fee > 0 && (
                            <div className="text-muted" style={{ fontSize: 12 }}>
                                Belanja {formatRupiah(grandTotal)} + biaya {method.name} {percentLabel(feePercent)}% {formatRupiah(fee)}
                            </div>
                        )}
                    </div>

                    <div className="form-group">
                        <label>Metode Pembayaran <small className="text-muted">Alt+1–9 &nbsp;·&nbsp; F7 berikutnya &nbsp;·&nbsp; Shift+F7 sebelumnya</small></label>
                        <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>
                            {methods.map((item, index) => {
                                const selected = index === currentIndex;
                                return (
                                    <button
                                        key={`${item.id}-${index}`}
                                        type="button"
                                        tabIndex={-1}
                                        disabled={submitting}
                                        className={`btn btn-sm ${selected ? "btn-primary" : "btn-default"}`}
                                        onMouseDown={(event) => event.preventDefault()}
                                        onClick={() => pickMethod(index)}
                                        title={index < 9 ? `Alt+${index + 1}` : undefined}
                                    >
                                        {index < 9 && <kbd style={{ marginRight: 6 }}>{index + 1}</kbd>}
                                        {item.name}
                                        {Number(item.charge_percent || 0) > 0 && <small> +{percentLabel(item.charge_percent)}%</small>}
                                        {item.id !== "" && item.is_fixed_amount && <small> · pas</small>}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {SHOW_PAYMENT_REFERENCE && !isCash && (
                        <div className="form-group">
                            <label>Nomor Referensi <span className="text-danger">*</span></label>
                            <input
                                ref={referenceRef}
                                type="text"
                                className="form-control"
                                placeholder="Nomor transaksi / referensi pembayaran"
                                value={reference}
                                disabled={submitting}
                                onChange={(event) => setReference(event.target.value)}
                            />
                        </div>
                    )}

                    <div className="form-group">
                        <label>Uang Diterima {!fixed && <span className="text-danger">*</span>} {fixed && <small className="text-muted">terisi otomatis (nominal fix)</small>}</label>
                        <div className="input-group">
                            <input
                                ref={paidRef}
                                type="text"
                                inputMode="numeric"
                                autoComplete="off"
                                className="form-control input-lg"
                                placeholder={formatIdNumber(amountDue)}
                                value={paidText}
                                readOnly={fixed}
                                disabled={submitting}
                                style={fixed ? { background: "#f4f4f4" } : undefined}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setPaid(value === "" ? "" : formatIdNumber(value));
                                }}
                            />
                            {!fixed && (
                                <span className="input-group-btn">
                                    <button type="button" tabIndex={-1} className="btn btn-default btn-lg" disabled={submitting} onClick={() => { setPaid(formatIdNumber(amountDue)); paidRef.current?.focus(); }}>
                                        Uang Pas
                                    </button>
                                </span>
                            )}
                        </div>
                    </div>

                    <div style={{ padding: "8px 12px", border: `1px solid ${enough || idle ? "#00a65a" : "#dd4b39"}`, borderRadius: 4, background: enough || idle ? "#f0fff4" : "#fff5f5", color: enough || idle ? "#008d4c" : "#dd4b39", textAlign: "right" }}>
                        <div style={{ fontSize: 12 }}>{enough || idle ? "Kembalian" : "Kurang"}</div>
                        <div style={{ fontSize: 28, fontWeight: 700 }}>{formatRupiah(enough || idle ? change : shortage)}</div>
                    </div>

                    {error && <div className="alert alert-danger" style={{ marginTop: 10, marginBottom: 0 }}>{error}</div>}
                </div>
                <div className="box-footer" style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
                    <button type="button" className="btn btn-default" disabled={submitting} onClick={onClose}>Batal (Esc)</button>
                    <button type="button" className="btn btn-success" disabled={submitting} onClick={confirm}>
                        {submitting ? "Memproses..." : "Proses & Cetak (Enter)"}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default PaymentModal;
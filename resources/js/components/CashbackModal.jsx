import React, { useEffect, useRef, useState } from "react";
import { formatIdNumber, formatRupiah, parseIdNumber } from "../utils";

const MAX_QTY = 999;

/**
 * Pop up Cashback (Alt+C): kasir mengisi qty (lembar) dan total nominal cashback.
 * Perilaku seperti voucher: mengurangi total bayar. Nominal tidak boleh melebihi total belanja (maxAmount).
 * Enter = simpan, Esc = tutup tanpa mengubah. "Hapus" mengosongkan cashback.
 */
const CashbackModal = ({ open, maxAmount, value, onSave, onClose }) => {
    const [qty, setQty] = useState("1");
    const [amount, setAmount] = useState("");
    const [error, setError] = useState("");
    const qtyRef = useRef(null);
    const amountRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const hasValue = Number(value?.total) > 0;
        setQty(hasValue ? String(value.qty || 1) : "1");
        setAmount(hasValue ? formatIdNumber(value.total) : "");
        setError("");
        const timer = window.setTimeout(() => {
            qtyRef.current?.focus();
            qtyRef.current?.select?.();
        }, 0);
        return () => window.clearTimeout(timer);
    }, [open]);

    if (!open) return null;

    const amountValue = Math.round(parseIdNumber(amount) || 0);
    const qtyValue = Math.min(MAX_QTY, Math.max(0, Number.parseInt(qty, 10) || 0));
    const max = Math.max(0, Math.round(Number(maxAmount) || 0));

    const save = () => {
        if (amountValue <= 0) {
            // Nominal kosong = tidak ada cashback.
            onSave({ qty: 0, total: 0 });
            return;
        }
        if (qtyValue < 1) {
            setError("Isi jumlah (qty) cashback minimal 1.");
            qtyRef.current?.focus();
            return;
        }
        if (amountValue > max) {
            setError(`Nominal cashback tidak boleh melebihi total belanja (${formatRupiah(max)}).`);
            amountRef.current?.focus();
            return;
        }
        onSave({ qty: qtyValue, total: amountValue });
    };

    const handleKeyDown = (event) => {
        if (event.key === "Escape") {
            event.preventDefault();
            event.stopPropagation();
            onClose();
            return;
        }
        if (event.key === "Enter") {
            if (event.target?.tagName === "BUTTON") return;
            event.preventDefault();
            event.stopPropagation();
            save();
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            onKeyDown={handleKeyDown}
            onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}
            style={{ position: "fixed", inset: 0, zIndex: 1040, background: "rgba(0,0,0,.45)", padding: "10vh 15px" }}
        >
            <div style={{ background: "#fff", maxWidth: 380, margin: "0 auto", borderRadius: 4, boxShadow: "0 8px 30px rgba(0,0,0,.3)" }}>
                <div className="box-header with-border" style={{ padding: "8px 12px" }}>
                    <button type="button" className="close" onClick={onClose} aria-label="Tutup">&times;</button>
                    <h3 className="box-title" style={{ fontSize: 16 }}><i className="fa fa-ticket"></i> Cashback <small>(Alt+C)</small></h3>
                </div>
                <div className="box-body" style={{ padding: "12px" }}>
                    <div className="form-group" style={{ marginBottom: 10 }}>
                        <label>Qty (lembar)</label>
                        <input
                            ref={qtyRef}
                            type="text"
                            inputMode="numeric"
                            autoComplete="off"
                            className="form-control"
                            value={qty}
                            onFocus={(event) => event.target.select()}
                            onChange={(event) => { setQty(event.target.value.replace(/\D/g, "")); setError(""); }}
                        />
                    </div>
                    <div className="form-group" style={{ marginBottom: 6 }}>
                        <label>Total nominal cashback</label>
                        <div className="input-group">
                            <span className="input-group-addon">Rp</span>
                            <input
                                ref={amountRef}
                                type="text"
                                inputMode="numeric"
                                autoComplete="off"
                                className="form-control input-lg"
                                placeholder="0"
                                value={amount}
                                onFocus={(event) => event.target.select()}
                                onChange={(event) => {
                                    const raw = event.target.value;
                                    setAmount(raw === "" ? "" : formatIdNumber(raw));
                                    setError("");
                                }}
                            />
                        </div>
                    </div>
                    <div className="text-muted" style={{ fontSize: 12 }}>
                        Maksimal {formatRupiah(max)} (total belanja setelah promo dan voucher). Mengurangi total bayar seperti voucher.
                    </div>
                    {error && <div className="alert alert-danger" style={{ marginTop: 10, marginBottom: 0, padding: "6px 10px" }}>{error}</div>}
                </div>
                <div className="box-footer" style={{ display: "flex", gap: 8, justifyContent: "space-between", padding: "8px 12px" }}>
                    <button type="button" className="btn btn-default btn-sm text-danger" onClick={() => onSave({ qty: 0, total: 0 })}>Hapus cashback</button>
                    <div style={{ display: "flex", gap: 8 }}>
                        <button type="button" className="btn btn-default btn-sm" onClick={onClose}>Batal (Esc)</button>
                        <button type="button" className="btn btn-primary btn-sm" onClick={save}>Simpan (Enter)</button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CashbackModal;
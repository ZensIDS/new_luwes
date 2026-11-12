import React, { useEffect, useRef, useState } from "react";
import { formatRupiah } from "../utils";

const MAX_QTY = 999;

/**
 * Pop up Kresek (F9): daftar kresek aktif, centang untuk memilih, tiap kresek punya qty sendiri.
 * Panah atas/bawah pindah baris, Spasi centang, + / - ubah qty, angka 1-9 centang baris ke-n,
 * Enter atau Esc menutup (pilihan tersimpan). Pilihan dipakai Cart saat bayar; tanpa stok.
 */
const KresekModal = ({ open, options, selection, onChange, onClose }) => {
    const [active, setActive] = useState(0);
    const boxRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        setActive(0);
        const timer = window.setTimeout(() => boxRef.current?.focus(), 0);
        return () => window.clearTimeout(timer);
    }, [open]);

    if (!open) return null;

    const clampQty = (value) => Math.min(MAX_QTY, Math.max(1, Number.parseInt(value, 10) || 1));

    const toggle = (id) => {
        const next = { ...selection };
        if (next[id]) delete next[id];
        else next[id] = 1;
        onChange(next);
    };

    const setQty = (id, qty) => onChange({ ...selection, [id]: clampQty(qty) });

    const total = options.reduce((sum, item) => sum + (selection[item.id] ? Number(item.price || 0) * selection[item.id] : 0), 0);
    const pieces = options.reduce((sum, item) => sum + (selection[item.id] || 0), 0);

    const handleKeyDown = (event) => {
        if (event.key === "Escape" || event.key === "Enter") {
            event.preventDefault();
            event.stopPropagation();
            onClose();
            return;
        }
        // Saat mengetik qty, tombol lain dibiarkan untuk input itu sendiri.
        if (event.target?.tagName === "INPUT" && event.target.type !== "checkbox") return;
        const current = options[active];

        if (event.key === "ArrowDown") {
            event.preventDefault();
            setActive((index) => Math.min(options.length - 1, index + 1));
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            setActive((index) => Math.max(0, index - 1));
        } else if (event.key === " " && current) {
            event.preventDefault();
            toggle(current.id);
        } else if ((event.key === "+" || event.key === "=") && current) {
            event.preventDefault();
            setQty(current.id, (selection[current.id] || 0) + 1);
        } else if ((event.key === "-" || event.key === "_") && current) {
            event.preventDefault();
            const qty = selection[current.id] || 0;
            if (qty > 1) setQty(current.id, qty - 1);
            else if (qty === 1) toggle(current.id);
        } else if (/^[1-9]$/.test(event.key) && options[Number(event.key) - 1]) {
            event.preventDefault();
            const index = Number(event.key) - 1;
            setActive(index);
            toggle(options[index].id);
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            onKeyDown={handleKeyDown}
            onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}
            style={{ position: "fixed", inset: 0, zIndex: 1040, background: "rgba(0,0,0,.45)", padding: "8vh 15px" }}
        >
            <div ref={boxRef} tabIndex={-1} style={{ background: "#fff", maxWidth: 420, margin: "0 auto", borderRadius: 4, boxShadow: "0 8px 30px rgba(0,0,0,.3)", outline: "none" }}>
                <div className="box-header with-border" style={{ padding: "8px 12px" }}>
                    <button type="button" className="close" onClick={onClose} aria-label="Tutup">&times;</button>
                    <h3 className="box-title" style={{ fontSize: 16 }}><i className="fa fa-shopping-bag"></i> Kresek <small>(F9)</small></h3>
                </div>
                <div style={{ maxHeight: "50vh", overflowY: "auto" }}>
                    {!options.length && <div className="text-center text-muted" style={{ padding: 20 }}>Belum ada kresek aktif.</div>}
                    {options.map((item, index) => {
                        const qty = selection[item.id] || 0;
                        const checked = qty > 0;
                        return (
                            <div
                                key={item.id}
                                onClick={() => { setActive(index); toggle(item.id); }}
                                style={{ display: "flex", alignItems: "center", gap: 10, padding: "7px 12px", cursor: "pointer", borderBottom: "1px solid #f0f0f0", background: index === active ? "#f4f4f8" : checked ? "#f0fff4" : "#fff", borderLeft: index === active ? "3px solid #605ca8" : "3px solid transparent" }}
                            >
                                <input type="checkbox" checked={checked} readOnly tabIndex={-1} style={{ margin: 0, pointerEvents: "none" }} />
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: 600, fontSize: 13 }}>{item.name}</div>
                                    <div className="text-muted" style={{ fontSize: 12 }}>{formatRupiah(item.price)}</div>
                                </div>
                                {checked && (
                                    <div onClick={(event) => event.stopPropagation()} style={{ display: "flex", alignItems: "center", gap: 4 }}>
                                        <button type="button" tabIndex={-1} className="btn btn-default btn-xs" onClick={() => (qty > 1 ? setQty(item.id, qty - 1) : toggle(item.id))}>&minus;</button>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            className="form-control input-sm"
                                            value={qty}
                                            style={{ width: 52, textAlign: "center", padding: "2px 4px" }}
                                            onFocus={(event) => event.target.select()}
                                            onChange={(event) => setQty(item.id, event.target.value)}
                                        />
                                        <button type="button" tabIndex={-1} className="btn btn-default btn-xs" onClick={() => setQty(item.id, qty + 1)}>+</button>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
                <div className="box-footer" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "8px 12px" }}>
                    <div style={{ fontSize: 13 }}>
                        {pieces > 0 ? <>{pieces} pcs &nbsp;<b>{formatRupiah(total)}</b></> : <span className="text-muted">Belum ada kresek dipilih</span>}
                    </div>
                    <button type="button" className="btn btn-primary btn-sm" onClick={onClose}>Selesai (Enter)</button>
                </div>
                <div className="text-muted" style={{ fontSize: 11, padding: "0 12px 8px" }}>
                    ↑↓ pindah &nbsp;·&nbsp; Spasi / 1–9 centang &nbsp;·&nbsp; + / − qty &nbsp;·&nbsp; Esc / Enter tutup
                </div>
            </div>
        </div>
    );
};

export default KresekModal;
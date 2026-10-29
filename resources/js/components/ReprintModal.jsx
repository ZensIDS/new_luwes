import React, { useEffect, useRef, useState } from "react";
import axios from "axios";
import Swal from "sweetalert2";
import { formatRupiah } from "../utils";

/**
 * Modal Reprint (F4): cari nomor penjualan, pilih dengan panah atas/bawah + Enter,
 * lalu konfirmasi SweetAlert "Cetak" (Enter lagi) -> nota hasil reprint langsung dicetak.
 */
const ReprintModal = ({ open, outletId, onClose }) => {
    const [term, setTerm] = useState("");
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [active, setActive] = useState(0);
    const inputRef = useRef(null);
    const listRef = useRef(null);
    const requestRef = useRef(null);
    const timerRef = useRef(null);

    const load = (query) => {
        requestRef.current?.abort();
        const controller = new AbortController();
        requestRef.current = controller;
        setLoading(true);
        axios.get("/penjualan/reprint-search", {
            params: { outlet_id: outletId, q: query },
            headers: { Accept: "application/json" },
            signal: controller.signal,
        })
            .then((response) => {
                if (requestRef.current !== controller) return;
                setRows(Array.isArray(response.data?.data) ? response.data.data : []);
                setActive(0);
                setError("");
            })
            .catch((err) => {
                if (axios.isCancel(err) || err.code === "ERR_CANCELED") return;
                setError(err.response?.data?.message || "Gagal memuat daftar penjualan.");
            })
            .finally(() => {
                if (requestRef.current === controller) {
                    requestRef.current = null;
                    setLoading(false);
                }
            });
    };

    useEffect(() => {
        if (!open) return undefined;
        setTerm("");
        setRows([]);
        setActive(0);
        setError("");
        load("");
        const focusTimer = window.setTimeout(() => inputRef.current?.focus(), 0);
        return () => {
            window.clearTimeout(focusTimer);
            window.clearTimeout(timerRef.current);
            requestRef.current?.abort();
        };
    }, [open]);

    useEffect(() => {
        listRef.current?.querySelector('[data-active="true"]')?.scrollIntoView({ block: "nearest" });
    }, [active, rows]);

    if (!open) return null;

    const choose = (row) => {
        if (!row) return;
        onClose();
        // Jeda singkat: supaya Enter yang memilih baris tidak ikut "menekan" tombol Cetak di SweetAlert.
        window.setTimeout(() => {
            Swal.fire({
                icon: "question",
                title: "Reprint nota?",
                text: `${row.code} — ${row.date} — ${formatRupiah(row.total)}`,
                showCancelButton: true,
                confirmButtonText: "Cetak",
                cancelButtonText: "Batal",
                reverseButtons: true,
                focusConfirm: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `/penjualan/${row.id}/print?reprint=1&auto=1`;
                }
            });
        }, 120);
    };

    const handleKeyDown = (event) => {
        if (event.key === "Escape") {
            event.preventDefault();
            event.stopPropagation();
            onClose();
        } else if (event.key === "ArrowDown") {
            event.preventDefault();
            setActive((index) => Math.min(rows.length - 1, index + 1));
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            setActive((index) => Math.max(0, index - 1));
        } else if (event.key === "Enter") {
            event.preventDefault();
            event.stopPropagation();
            choose(rows[active]);
        }
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            onClick={onClose}
            onKeyDown={handleKeyDown}
            style={{ position: "fixed", inset: 0, zIndex: 1040, background: "rgba(0,0,0,.45)", padding: "8vh 15px" }}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{ background: "#fff", maxWidth: 760, maxHeight: "84vh", margin: "0 auto", overflow: "hidden", display: "flex", flexDirection: "column", borderRadius: 4, boxShadow: "0 8px 30px rgba(0,0,0,.3)" }}
            >
                <div className="box-header with-border">
                    <button type="button" className="close" onClick={onClose} aria-label="Tutup">&times;</button>
                    <h3 className="box-title"><i className="fa fa-print"></i> Reprint Nota <small>(F4)</small></h3>
                </div>
                <div className="box-body" style={{ paddingBottom: 6 }}>
                    <input
                        ref={inputRef}
                        type="search"
                        className="form-control input-lg"
                        placeholder="Cari nomor penjualan"
                        value={term}
                        autoComplete="off"
                        onChange={(event) => {
                            const value = event.target.value;
                            setTerm(value);
                            window.clearTimeout(timerRef.current);
                            timerRef.current = window.setTimeout(() => load(value.trim()), 250);
                        }}
                    />
                    <p className="text-muted small" style={{ margin: "6px 0 0" }}>
                        <kbd>↑</kbd> <kbd>↓</kbd> pilih &nbsp;·&nbsp; <kbd>Enter</kbd> buka konfirmasi cetak &nbsp;·&nbsp; <kbd>Esc</kbd> tutup
                    </p>
                </div>
                <div ref={listRef} style={{ overflowY: "auto", padding: "0 10px 10px" }}>
                    {error && <div className="alert alert-danger" style={{ margin: "6px 5px" }}>{error}</div>}
                    {!error && loading && !rows.length && <p className="text-muted" style={{ padding: "6px 5px" }}>Memuat...</p>}
                    {!error && !loading && !rows.length && <p className="text-muted" style={{ padding: "6px 5px" }}>Tidak ada penjualan yang cocok.</p>}
                    {rows.length > 0 && (
                        <table className="table table-condensed table-hover" style={{ marginBottom: 0 }}>
                            <thead>
                                <tr><th>No. Penjualan</th><th>Tanggal</th><th>Customer</th><th className="text-right">Total</th></tr>
                            </thead>
                            <tbody>
                                {rows.map((row, index) => (
                                    <tr
                                        key={row.id}
                                        data-active={index === active ? "true" : "false"}
                                        onMouseDown={(event) => event.preventDefault()}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => choose(row)}
                                        style={{ cursor: "pointer", background: index === active ? "#d9edf7" : undefined, fontWeight: index === active ? 700 : undefined }}
                                    >
                                        <td>{row.code}</td>
                                        <td>{row.date}</td>
                                        <td>{row.customer || "Umum"}</td>
                                        <td className="text-right">{formatRupiah(row.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
};

export default ReprintModal;
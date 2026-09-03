import React, { useState } from "react";
import axios from "axios";

const Vouchers = ({ appliedVouchers, onAddVoucher, onRemoveVoucher, inputRef, outletId }) => {
    const [code, setCode] = useState("");
    const [error, setError] = useState("");

    const lookup = (event) => {
        event.preventDefault();
        const normalized = code.trim().toUpperCase();
        if (!normalized) return;
        axios.get("/voucher/lookup?code=" + encodeURIComponent(normalized) + "&outlet_id=" + encodeURIComponent(outletId || ""))
            .then((response) => {
                onAddVoucher(response.data);
                setCode("");
                setError("");
            })
            .catch((requestError) => {
                setError(requestError.response?.data?.message || "Voucher tidak dapat digunakan.");
            });
    };

    return (
        <div className="form-group">
            <form className="input-group" onSubmit={lookup}>
                <input
                    ref={inputRef}
                    type="text"
                    className="form-control form-control-sm"
                    placeholder="Scan kode voucher lalu Enter (F8)"
                    value={code}
                    onChange={(event) => setCode(event.target.value)}
                />
                <span className="input-group-btn"><button className="btn btn-primary btn-sm">Pakai</button></span>
            </form>
            {error && <small className="text-danger">{error}</small>}
            <div style={{ marginTop: 5 }}>
                {appliedVouchers.map((voucher) => (
                    <span className="label label-success" style={{ marginRight: 5 }} key={voucher.code} title={`${voucher.name || "Voucher"} | Min. ${Number(voucher.min_purchase || 0).toLocaleString("id-ID")}`}>
                        {voucher.code} {voucher.name ? `— ${voucher.name}` : ""} {voucher.type === "percentage" ? (voucher.value + "%") : ("Rp " + Number(voucher.value).toLocaleString("id-ID"))}
                        <button type="button" className="btn btn-link btn-xs" style={{ color: "white", padding: "0 0 0 5px" }} onClick={() => onRemoveVoucher(voucher.code)}>×</button>
                    </span>
                ))}
            </div>
        </div>
    );
};

export default Vouchers;

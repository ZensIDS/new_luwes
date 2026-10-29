import React from "react";

const Barcodes = ({ barcode, handleScanBarcode, handleOnChangeBarcode, inputRef, notice = "" }) => {
    return (
        <form className="mb-1" onSubmit={handleScanBarcode}>
            <label htmlFor="pos-barcode-input">Scan Barcode / Voucher <small>(F3)</small></label>
            <input
                id="pos-barcode-input"
                ref={inputRef}
                type="text"
                className="form-control form-control-sm"
                style={{ height: "42px", fontSize: "18px" }}
                placeholder="Scan barcode produk atau voucher (VCRL...) lalu tekan Enter"
                autoComplete="off"
                value={barcode}
                onChange={handleOnChangeBarcode}
            />
            <p className="text-muted small" style={{ marginTop: 4, marginBottom: 8 }}>
                F3 fokus ke barcode · Enter tambah produk · kode berawalan VCRL dipasang sebagai voucher
            </p>
            {notice && <p className="text-success small" style={{ marginTop: -4, marginBottom: 8 }}>{notice}</p>}
        </form>
    );
};

export default Barcodes;
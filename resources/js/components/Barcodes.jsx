import React from "react";

const Barcodes = ({ barcode, handleScanBarcode, handleOnChangeBarcode, inputRef }) => {
    return (
        <form className="mb-1" onSubmit={handleScanBarcode}>
            <label htmlFor="pos-barcode-input">Scan Barcode <small>(F3)</small></label>
            <input
                id="pos-barcode-input"
                ref={inputRef}
                type="text"
                className="form-control form-control-sm"
                placeholder="Scan barcode lalu tekan Enter"
                autoComplete="off"
                value={barcode}
                onChange={handleOnChangeBarcode}
            />
            <p className="text-muted small" style={{ marginTop: 4, marginBottom: 8 }}>
                F3 fokus ke barcode · Enter tambah produk
            </p>
        </form>
    );
};

export default Barcodes;

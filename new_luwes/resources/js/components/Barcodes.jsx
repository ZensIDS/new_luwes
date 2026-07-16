import React from "react";

const Barcodes = ({ barcode, handleScanBarcode, handleOnChangeBarcode }) => {
    return (
        <form className="mb-1" onSubmit={handleScanBarcode}>
            <input
                type="text"
                className="form-control form-control-sm"
                placeholder="Scan Barcode..."
                value={barcode}
                onChange={handleOnChangeBarcode}
            />
            <br />
        </form>
    );
};

export default Barcodes;

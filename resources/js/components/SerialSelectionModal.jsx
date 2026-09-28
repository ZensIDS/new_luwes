// laravel_pos/resources/js/components/SerialSelectionModal.jsx
import React, { useEffect, useRef, useState } from "react";

const SerialSelectionModal = ({
    serialModal,
    setSerialModal,
    selectedProduct,
    selectedSerial,
    setSelectedSerial,
    availableSerials,
    handleSerialSelection,
}) => {
    const selectRef = useRef(null);
    const [select2Initialized, setSelect2Initialized] = useState(false);

    useEffect(() => {
        if (serialModal && selectRef.current && !select2Initialized) {
            $(selectRef.current).select2({
                placeholder: "Search serial number...",
                width: "100%",
            });

            $(selectRef.current).on("change", (e) => {
                setSelectedSerial(e.target.value);
            });

            setSelect2Initialized(true);
        }

        return () => {
            if (selectRef.current && select2Initialized) {
                try {
                    $(selectRef.current).off("change");
                    $(selectRef.current).select2("destroy");
                } catch (e) {
                    console.warn("Select2 cleanup error:", e);
                }
                setSelect2Initialized(false);
            }
        };
    }, [serialModal, select2Initialized]);

    if (!serialModal) return null;

    return (
        <div
            className="modal"
            style={{
                display: "block",
                position: "fixed",
                top: 0,
                left: 0,
                width: "100%",
                height: "100%",
                backgroundColor: "rgba(0,0,0,0.5)",
                zIndex: 1050,
            }}
        >
            <div className="modal-dialog" style={{ marginTop: "50px" }}>
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">
                            Select Serial Number for {selectedProduct?.name}
                        </h5>
                        <button
                            type="button"
                            className="close"
                            onClick={() => setSerialModal(false)}
                        >
                            <span>&times;</span>
                        </button>
                    </div>
                    <div className="modal-body">
                        <div className="form-group">
                            <label>Available Serial Numbers:</label>
                            <select
                                ref={selectRef}
                                className="form-control"
                                defaultValue={selectedSerial}
                            >
                                <option value="">Choose Serial Number</option>
                                {availableSerials.map(
                                    ({ id, serial, status }) => (
                                        <option key={`serial-${ id }`} value={serial}>
                                            [{status}] - {serial}
                                        </option>
                                    )
                                )}
                            </select>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={handleSerialSelection}
                            disabled={!selectedSerial}
                        >
                            Add to Cart
                        </button>
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={() => setSerialModal(false)}
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SerialSelectionModal;

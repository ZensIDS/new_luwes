import React from "react";

const Vouchers = ({
    vouchers,
    voucherId,
    setVoucherId,
    setVoucherDiscount,
}) => {
    const handleVoucherChange = (e) => {
        const selectedVoucherId = e.target.value;
        setVoucherId(selectedVoucherId);

        const selectedVoucher = vouchers.find((v) => v.id == selectedVoucherId);
        setVoucherDiscount(selectedVoucher ? selectedVoucher.value : 0);
    };

    return (
        <div className="form-group row">
            <div className="col-sm-8">
                <select
                    id="voucher"
                    className="form-control"
                    onChange={handleVoucherChange}
                >
                    <option value="">Select a voucher</option>
                    {vouchers.map((voucher) => (
                        <option key={`voucher-${ voucher.id }`} value={voucher.id}>
                            {voucher.name}, {voucher.value}
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-sm-4">
                <a className="btn btn-success btn-sm" href="/voucher/create">
                    Tambah Voucher
                </a>
            </div>
        </div>
    );
};

export default Vouchers;

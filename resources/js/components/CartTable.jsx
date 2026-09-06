import React from "react";
import { formatIdNumber, formatRupiah, parseIdNumber } from "../utils";
import CartTableBody from "./CartTableBody";
import ReactSelectField from "./ReactSelectField";
import Vouchers from "./Vouchers";

const CartTable = ({
    cart,
    getBaseSubtotal,
    getSubtotal,
    promotionTotal,
    voucherBreakdown,
    voucherTotal,
    grandTotal,
    customers,
    customerId,
    setCustomerId,
    customerInputRef,
    paidAmount,
    setPaidAmount,
    paymentMethods,
    paymentMethodId,
    setPaymentMethodId,
    paymentMethodInputRef,
    paidInputRef,
    voucherInputRef,
    outletId,
    appliedVouchers,
    onAddVoucher,
    onRemoveVoucher,
    appliedPromotions,
    onAddPromotion,
    onRemovePromotion,
    selectedProducts,
    appliedPromotionNames,
    promotionTableRef,
    handleChangeQty,
    handleClickIncrease,
    handleClickDecrease,
    handleClickDelete,
    handleEmptyCart,
    handleSubmit,
    errorMessage,
    selectedCartProductId,
    setSelectedCartProductId,
    cartTableRef,
}) => {
    const change = Math.max(0, parseIdNumber(paidAmount) - grandTotal);

    return (
        <>
            <div className="table-responsive text-nowrap" style={{ maxHeight: "45vh", overflowY: "auto", border: "1px solid #ddd" }}>
                <table className="table table-sm table-bordered">
                    <thead style={{ position: "sticky", top: 0, zIndex: 1, background: "#fff" }}>
                        <tr>
                            <th className="w-40">Produk</th>
                            <th className="w-10">Qty</th>
                            <th className="w-15">Harga Netto</th>
                            <th className="w-15">Aksi</th>
                            <th className="text-right w-20">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {!cart.length && <tr><td colSpan="5" className="text-center text-muted" style={{ padding: "28px 8px" }}>Belum ada item. Scan barcode atau pilih produk.</td></tr>}
                        <CartTableBody
                            ref={cartTableRef}
                            cart={cart}
                            handleChangeQty={handleChangeQty}
                            handleClickIncrease={handleClickIncrease}
                            handleClickDecrease={handleClickDecrease}
                            handleClickDelete={handleClickDelete}
                            selectedCartProductId={selectedCartProductId}
                            setSelectedCartProductId={setSelectedCartProductId}
                        />
                    </tbody>
                </table>
            </div>
            <div className="table-responsive text-nowrap" style={{ marginTop: 0 }}>
                <table className="table table-sm table-bordered" style={{ marginBottom: 0 }}>
                    <tbody>
                        <tr>
                            <td colSpan="4">Subtotal setelah Disc Toko</td>
                            <td className="text-right">{formatRupiah(getBaseSubtotal(cart))}</td>
                        </tr>
                        {promotionTotal > 0 && <tr>
                            <td colSpan="4">Potongan promo flash sale / bundling</td>
                            <td className="text-right text-danger">-{formatRupiah(promotionTotal)}</td>
                        </tr>}
                        <tr>
                            <td colSpan="4">Subtotal setelah promo</td>
                            <td className="text-right">{formatRupiah(getSubtotal(cart))}</td>
                        </tr>
                        {voucherBreakdown.map((voucher) => (
                            <tr key={voucher.code}>
                                <td colSpan="4">Voucher {voucher.code}</td>
                                <td className="text-right text-danger">-{formatRupiah(voucher.amount)}</td>
                            </tr>
                        ))}
                        <tr>
                            <td colSpan="4">Total Voucher</td>
                            <td className="text-right text-danger">-{formatRupiah(voucherTotal)}</td>
                        </tr>
                        <tr>
                            <th colSpan="4">Grand Total</th>
                            <th className="text-right">{formatRupiah(grandTotal)}</th>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Vouchers
                appliedVouchers={appliedVouchers}
                onAddVoucher={onAddVoucher}
                onRemoveVoucher={onRemoveVoucher}
                inputRef={voucherInputRef}
                outletId={outletId}
                appliedPromotionNames={appliedPromotionNames}
                appliedPromotions={appliedPromotions}
                onAddPromotion={onAddPromotion}
                onRemovePromotion={onRemovePromotion}
                selectedProducts={selectedProducts}
                promotionTableRef={promotionTableRef}
            />
            <div className="row">
                <div className="col-md-6">
                    <label>Customer <small>(F4)</small></label>
                    <ReactSelectField
                        ref={customerInputRef}
                        value={customerId}
                        onChange={setCustomerId}
                        placeholder="Pilih customer"
                        isClearable={false}
                        options={[
                            { value: "", label: "Umum" },
                            ...customers.map((customer) => ({
                                value: customer.id,
                                label: `${customer.name}${customer.no_telp ? ` — ${customer.no_telp}` : ""}`,
                            })),
                        ]}
                    />
                </div>
                <div className="col-md-6">
                    <label>Metode Pembayaran <small>(F7)</small></label>
                    <ReactSelectField
                        ref={paymentMethodInputRef}
                        value={paymentMethodId}
                        onChange={setPaymentMethodId}
                        placeholder="Pilih metode pembayaran"
                        isClearable={false}
                        options={[
                            { value: "", label: "Tunai / belum dipilih" },
                            ...paymentMethods.map((method) => ({ value: method.id, label: method.name })),
                        ]}
                    />
                </div>
                <div className="col-md-6">
                    <label>Uang Diterima <small>(F9)</small></label>
                    <div className="input-group input-group-sm">
                        <input
                            ref={paidInputRef}
                            type="text"
                            inputMode="numeric"
                            autoComplete="off"
                            className="form-control"
                            style={{ height: "40px", fontSize: "16px" }}
                            placeholder={formatIdNumber(grandTotal)}
                            value={paidAmount}
                            onChange={(event) => {
                                const value = event.target.value;
                                setPaidAmount(value === "" ? "" : formatIdNumber(value));
                            }}
                        />
                    </div>
                    <small className="text-muted">Masukkan contoh: 100.000. Nilai disimpan sebagai 100000.</small>
                </div>
            </div>
            <p className="text-muted small" style={{ marginTop: 10, marginBottom: 0 }}>
                <i className="fa fa-keyboard-o"></i> F2 Cari produk &nbsp;|&nbsp; F3 Scan &nbsp;|&nbsp; F4 Customer &nbsp;|&nbsp; F5 Item &nbsp;|&nbsp; F6 Promo dipilih &nbsp;|&nbsp; F7 Pembayaran &nbsp;|&nbsp; F8 Voucher/promo &nbsp;|&nbsp; F9 Uang &nbsp;|&nbsp; F10 Proses &nbsp;|&nbsp; Delete hapus baris terpilih
            </p>
            <div className="text-right" style={{ marginTop: 8 }}>
                <strong>Kembalian: {formatRupiah(change)}</strong>
            </div>
            {errorMessage && <div className="alert alert-danger" style={{ marginTop: 8 }}>{errorMessage}</div>}
            <div className="row" style={{ marginTop: 10 }}>
                <div className="col-sm-6">
                    <button type="button" className="btn btn-danger btn-block" onClick={handleEmptyCart} disabled={!cart.length}>Kosongkan</button>
                </div>
                <div className="col-sm-6">
                <button type="button" className="btn btn-success btn-block" onClick={handleSubmit} disabled={!cart.length || parseIdNumber(paidAmount) < grandTotal}>Process (F10)</button>
                </div>
            </div>
        </>
    );
};

export default CartTable;

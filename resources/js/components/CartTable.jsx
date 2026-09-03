import React from "react";
import { formatIdNumber, formatRupiah, parseIdNumber } from "../utils";
import CartTableBody from "./CartTableBody";

const CartTable = ({
    cart,
    getSubtotal,
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
            <div className="table-responsive text-nowrap">
                <table className="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th className="w-40">Produk</th>
                            <th className="w-10">Qty</th>
                            <th className="w-15">Harga Netto</th>
                            <th className="w-15">Aksi</th>
                            <th className="text-right w-20">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
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
                        <tr>
                            <td colSpan="4">Subtotal setelah Disc Toko</td>
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
            <div className="row">
                <div className="col-md-6">
                    <label>Customer <small>(F4)</small></label>
                    <select ref={customerInputRef} className="form-control input-sm" value={customerId} onChange={(event) => setCustomerId(event.target.value)}>
                        <option value="">Umum</option>
                        {customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}{customer.no_telp ? ` — ${customer.no_telp}` : ""}</option>)}
                    </select>
                </div>
                <div className="col-md-6">
                    <label>Metode Pembayaran <small>(F7)</small></label>
                    <select ref={paymentMethodInputRef} className="form-control input-sm" value={paymentMethodId} onChange={(event) => setPaymentMethodId(event.target.value)}>
                        <option value="">Tunai / belum dipilih</option>
                        {paymentMethods.map((method) => <option key={method.id} value={method.id}>{method.name}</option>)}
                    </select>
                </div>
                <div className="col-md-6">
                    <label>Uang Diterima <small>(F9)</small></label>
                    <div className="input-group input-group-sm">
                        <span className="input-group-addon">Rp</span>
                        <input
                            ref={paidInputRef}
                            type="text"
                            inputMode="numeric"
                            autoComplete="off"
                            className="form-control"
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
                <i className="fa fa-keyboard-o"></i> F3 Scan barcode &nbsp;|&nbsp; F5 Fokus tabel item &nbsp;|&nbsp; F7 Metode pembayaran &nbsp;|&nbsp; Tab/Shift+Tab pindah item &nbsp;|&nbsp; Alt+↑/↓ pilih item &nbsp;|&nbsp; Ctrl+Backspace/Ctrl+Delete hapus item terakhir
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

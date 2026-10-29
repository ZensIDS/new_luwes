import React from "react";
import { SHOW_PAYMENT_REFERENCE, formatRupiah } from "../utils";
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
    paymentReference,
    setPaymentReference,
    paymentMethodInputRef,
    paymentReferenceInputRef,
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
    onSyncPromotions,
    handleChangeQty,
    handleClickIncrease,
    handleClickDecrease,
    handleClickDelete,
    handleEmptyCart,
    handleSubmit,
    isSubmitting,
    errorMessage,
    selectedCartProductId,
    setSelectedCartProductId,
    cartTableRef,
}) => {
    const selectedPaymentMethod = paymentMethods.find((method) => String(method.id) === String(paymentMethodId));
    const requiresPaymentReference = SHOW_PAYMENT_REFERENCE && Boolean(paymentMethodId) && !/tunai|cash/i.test(selectedPaymentMethod?.name || "");

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
            <div className="table-responsive" style={{ marginTop: 0, border: "1px solid #ddd", borderTop: 0, background: "#fafafa", padding: "4px 10px" }}>
                <div style={{ display: "flex", flexWrap: "wrap", justifyContent: "flex-end", gap: "2px 18px", fontSize: 12, color: "#555" }}>
                    <span>Subtotal: <b>{formatRupiah(getBaseSubtotal(cart))}</b></span>
                    {promotionTotal > 0 && <span className="text-danger">Promo: <b>-{formatRupiah(promotionTotal)}</b></span>}
                    {promotionTotal > 0 && <span>Setelah promo: <b>{formatRupiah(getSubtotal(cart))}</b></span>}
                    {voucherTotal > 0 && (
                        <span className="text-danger" title={voucherBreakdown.map((voucher) => `${voucher.code}: -${formatRupiah(voucher.amount)}`).join("\n")}>
                            Voucher{voucherBreakdown.length ? ` (${voucherBreakdown.map((voucher) => voucher.code).join(", ")})` : ""}: <b>-{formatRupiah(voucherTotal)}</b>
                        </span>
                    )}
                </div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "baseline" }}>
                    <span style={{ fontSize: 15, fontWeight: 700 }}>Grand Total</span>
                    <span style={{ fontSize: 26, fontWeight: 700, lineHeight: 1.2 }}>{formatRupiah(grandTotal)}</span>
                </div>
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
                voucherBreakdown={voucherBreakdown}
                onSyncPromotions={onSyncPromotions}
            />
            <div className="pos-quick-field" data-quick-field="customer">
                <label>Customer <small>(F1)</small></label>
                <ReactSelectField
                    ref={customerInputRef}
                    value={customerId}
                    onChange={(value) => {
                        setCustomerId(value);
                        window.setTimeout(() => customerInputRef.current?.blur?.(), 0);
                    }}
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
            <div className="pos-quick-field" data-quick-field="payment">
                <label>Metode Pembayaran <small>(F7)</small></label>
                <ReactSelectField
                    ref={paymentMethodInputRef}
                    value={paymentMethodId}
                    onChange={(value) => {
                        setPaymentMethodId(value);
                        window.setTimeout(() => paymentMethodInputRef.current?.blur?.(), 0);
                    }}
                    placeholder="Pilih metode pembayaran"
                    isClearable={false}
                    options={[
                        { value: "", label: "Tunai / belum dipilih" },
                        ...paymentMethods.map((method) => ({ value: method.id, label: method.name })),
                    ]}
                />
            </div>
            <div className="row">
                {requiresPaymentReference && (
                    <div className="col-md-12">
                        <div className="form-group" style={{ marginTop: 6, marginBottom: 6 }}>
                            <label>Nomor Referensi <span className="text-danger">*</span></label>
                            <input
                                ref={paymentReferenceInputRef}
                                type="text"
                                className="form-control"
                                placeholder="Nomor transaksi / referensi pembayaran"
                                value={paymentReference}
                                required
                                aria-required="true"
                                onChange={(event) => setPaymentReference(event.target.value)}
                            />
                        </div>
                    </div>
                )}
            </div>
            <p style={{ marginTop: 8, marginBottom: 0, padding: "6px 10px", background: "#f4f4f8", borderLeft: "4px solid #605ca8", fontSize: 13, fontWeight: 700, color: "#222" }}>
                <i className="fa fa-keyboard-o"></i> F2 Cari produk &nbsp;|&nbsp; F3 Scan &nbsp;|&nbsp; F1 Customer &nbsp;|&nbsp; F4 Reprint &nbsp;|&nbsp; F5 Item &nbsp;|&nbsp; F6 Promo &nbsp;|&nbsp; F7 Pembayaran &nbsp;|&nbsp; F8 Voucher &nbsp;|&nbsp; Ctrl/Cmd+Enter Bayar (F10) &nbsp;|&nbsp; Insert / Option ubah qty (di item) &nbsp;|&nbsp; Delete hapus baris terpilih
            </p>
            {errorMessage && <div className="alert alert-danger" style={{ marginTop: 8 }}>{errorMessage}</div>}
            <div className="row" style={{ marginTop: 10 }}>
                <div className="col-sm-6">
                    <button type="button" className="btn btn-danger btn-block" onClick={handleEmptyCart} disabled={!cart.length}>Kosongkan</button>
                </div>
                <div className="col-sm-6">
                <button type="button" className="btn btn-success btn-block" onClick={handleSubmit} disabled={isSubmitting || !cart.length}>
                    {isSubmitting ? "Processing..." : "Bayar (Ctrl+Enter)"}
                </button>
                </div>
            </div>
        </>
    );
};

export default CartTable;
import React from "react";
import { formatRupiah } from "../utils";
import CartTableBody from "./CartTableBody";
// import Kas from "./Kas";
import Salesmans from "./Salesmans";
import Kasir from "./Kasir";

const CartTable = ({
    cart,
    discount,
    handleChangeQty,
    handleClickIncrease,
    handleClickDecrease,
    handleClickDelete,
    handleEmptyCart,
    getTotal,
    limitDiscount,
    total,
    errorMessage,
    handleDiscountChange,
    handleClickWishlist,
    handleChangeTotal,
    handleSubmit,
    // kas,
    // kasId,
    // setKasId,
    salesmans,
    salesmanId,
    setSalesmanId,
    kasir,
    kasirId,
    setKasirId,
    voucherDiscount,
}) => {
    return (
        <>
            <div className="table-responsive text-nowrap">
                <table className="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th className="w-50">Product Name</th>
                            <th className="w-10">Quantity</th>
                            <th className="w-15">Per Item</th>
                            <th className="w-15">Aksi</th>
                            <th className="text-right w-10">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <CartTableBody
                            cart={cart}
                            handleChangeQty={handleChangeQty}
                            handleClickIncrease={handleClickIncrease}
                            handleClickDecrease={handleClickDecrease}
                            handleClickDelete={handleClickDelete}
                        />
                        <tr>
                            <td colSpan="4">Total</td>
                            <td className="text-right">
                                {formatRupiah(getTotal(cart))}
                            </td>
                        </tr>
                        <tr>
                            <td colSpan="4">Discount</td>
                            <td>
                                <input
                                    type="number"
                                    min={0}
                                    max={limitDiscount}
                                    className="form-control form-control-sm"
                                    placeholder="Discount..."
                                    value={discount}
                                    onChange={handleDiscountChange}
                                />
                            </td>
                        </tr>
                        <tr>
                            <td colSpan="4">Voucher</td>
                            <td>
                                <input
                                    type="number"
                                    className="form-control form-control-sm"
                                    placeholder="Voucher..."
                                    value={voucherDiscount}
                                    readOnly
                                />
                            </td>
                        </tr>
                        <tr>
                            <th colSpan="4">Grand Total</th>
                            <th className="text-right">
                                {formatRupiah(
                                    getTotal(cart) - discount - voucherDiscount
                                )}
                            </th>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div className="row">
                <div className="col-4 col-sm-4">
                    <button
                        type="button"
                        className="btn btn-danger btn-block"
                        onClick={handleEmptyCart}
                        disabled={!cart.length}
                    >
                        Empty
                    </button>
                </div>
                <div className="col-4 col-sm-4">
                    <button
                        type="button"
                        className="btn btn-warning btn-block"
                        onClick={handleClickWishlist}
                        disabled={!cart.length}
                    >
                        Hold
                    </button>
                </div>
                {/* <div className="col-6 col-sm-6">
                    <button
                        type="button"
                        className="btn btn-success btn-block"
                        onClick={handleClickSubmit}
                        disabled={!cart.length}
                    >
                        Submit
                    </button>
                </div> */}
                <div className="col-4 col-sm-4">
                    <button
                        type="button"
                        className="btn btn-success btn-block"
                        data-toggle="modal"
                        data-target="#tombolSubmit"
                        disabled={!cart.length}
                    >
                        Submit
                    </button>

                    <div id="tombolSubmit" className="modal fade" role="dialog">
                        <div className="modal-dialog">
                            <div className="modal-content">
                                <div className="modal-header">
                                    <span className="bg-danger">
                                        {errorMessage}
                                    </span>
                                    <h4 className="modal-title">Checkout</h4>
                                    <button
                                        type="button"
                                        className="close"
                                        data-dismiss="modal"
                                    >
                                        &times;
                                    </button>
                                </div>
                                <div className="modal-body">
                                    {/* <Kas
                                        kas={kas}
                                        kasId={kasId}
                                        setKasId={setKasId}
                                    /> */}
                                    {/* <Kasir
                                        key={kasirId}
                                        kasir={kasir}
                                        kasirId={kasirId}
                                        setKasirId={setKasirId}
                                    /> */}
                                    <Salesmans
                                        key={salesmanId}
                                        salesmans={salesmans}
                                        salesmanId={salesmanId}
                                        setSalesmanId={setSalesmanId}
                                    />

                                    <p>Total Amount</p>
                                    <div className="form-group">
                                        <input
                                            type="text"
                                            placeholder="Total Amount"
                                            name="total"
                                            className="form-control total"
                                            value={total}
                                            onChange={handleChangeTotal}
                                            readOnly
                                        />
                                    </div>
                                </div>
                                <div className="modal-footer">
                                    <button
                                        className="btn btn-primary"
                                        type="button"
                                        onClick={handleSubmit}
                                    >
                                        Process
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-default"
                                        data-dismiss="modal"
                                    >
                                        Tutup
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default CartTable;

import React from "react";
import { formatRupiah } from "../utils";
const CartTableBody = ({
    cart,
    handleChangeQty,
    handleClickIncrease,
    handleClickDecrease,
    handleClickDelete,
}) => {
    return (
        <>
            {cart.map((c, index) => (
                <tr key={`item-${ index }`}>
                    <td>
                        {c.is_serialized && c.pivot.serial_number && (
                            <span className="badge bg-aqua">
                                SN: {c.pivot.serial_number}
                            </span>
                        )}
                        <span>{c.name}</span>
                    </td>
                    <td>
                        <input
                            type="number"
                            className="form-control form-control-sm qty text-center"
                            style={{ maxWidth: "60px" }}
                            value={c.pivot.qty}
                            onChange={(event) =>
                                handleChangeQty(c.id, event.target.value)
                            }
                        />
                    </td>
                    <td>{formatRupiah(c.harga_jual)}</td>
                    <td>
                        <button
                            className="btn btn-sm"
                            onClick={() => handleClickIncrease(c.id)}
                        >
                            <i className="fa fa-plus"></i>
                        </button>
                        <button
                            className="btn btn-sm"
                            onClick={() => handleClickDecrease(c.id)}
                        >
                            <i className="fa fa-minus"></i>
                        </button>
                        <button
                            className="btn btn-danger btn-sm"
                            onClick={() => handleClickDelete(c.id)}
                        >
                            <i className="fa fa-trash"></i>
                        </button>
                    </td>
                    <td className="text-right">
                        {formatRupiah(c.harga_jual * c.pivot.qty)}
                    </td>
                </tr>
            ))}
        </>
    );
};
export default CartTableBody;

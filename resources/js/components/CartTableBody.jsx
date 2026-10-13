import React, { forwardRef, useImperativeHandle, useRef } from "react";
import { formatRupiah } from "../utils";
const CartTableBody = forwardRef(({
    cart,
    handleChangeQty,
    handleClickIncrease,
    handleClickDecrease,
    handleClickDelete,
    selectedCartProductId,
    setSelectedCartProductId,
}, ref) => {
    const rowRefs = useRef([]);

    const focusRow = (index) => {
        if (!cart.length) return;
        const nextIndex = Math.max(0, Math.min(cart.length - 1, index));
        const nextItem = cart[nextIndex];
        setSelectedCartProductId(nextItem.id);
        window.requestAnimationFrame(() => {
            const row = rowRefs.current[nextIndex];
            row?.focus();
            row?.scrollIntoView({ block: "nearest" });
        });
    };

    const handleRowKeyDown = (event, index) => {
        const rowIsTarget = event.target === event.currentTarget;
        const moveWithTab = event.key === "Tab" && rowIsTarget;
        const moveWithArrow = event.key === "ArrowDown" || event.key === "ArrowUp";
        const moveWithAltArrow = event.altKey && moveWithArrow;

        if (moveWithTab || moveWithAltArrow || (rowIsTarget && moveWithArrow)) {
            const direction = event.key === "ArrowUp" || (event.key === "Tab" && event.shiftKey) ? -1 : 1;
            const nextIndex = index + direction;
            if (nextIndex >= 0 && nextIndex < cart.length) {
                event.preventDefault();
                focusRow(nextIndex);
            }
            return;
        }

        if (rowIsTarget && (event.key === "Home" || event.key === "End")) {
            event.preventDefault();
            focusRow(event.key === "Home" ? 0 : cart.length - 1);
            return;
        }

        if (rowIsTarget && event.key === "Enter") {
            event.preventDefault();
            event.currentTarget.querySelector("input.qty")?.focus();
        }
    };

    useImperativeHandle(ref, () => ({
        focusFirstRow: () => focusRow(0),
    }), [cart]);

    return (
        <>
            {cart.map((c, index) => (
                <tr
                    key={`item-${ index }`}
                    ref={(element) => { rowRefs.current[index] = element; }}
                    tabIndex="0"
                    className={selectedCartProductId === c.id ? "info" : (Number(c.oversold_qty || 0) > 0 ? "warning" : "")}
                    onClick={() => setSelectedCartProductId(c.id)}
                    onFocus={() => setSelectedCartProductId(c.id)}
                    onKeyDown={(event) => handleRowKeyDown(event, index)}
                    title="Tab/Shift+Tab atau ↑/↓ untuk berpindah item; Enter untuk edit qty"
                >
                    <td>
                        <span>{c.name}</span>
                        {Number(c.oversold_qty || 0) > 0 && (
                            <div
                                style={{
                                    display: "inline-block",
                                    marginLeft: 8,
                                    padding: "1px 6px",
                                    fontSize: 12,
                                    color: "#8a6d3b",
                                    background: "#fcf8e3",
                                    border: "1px solid #faebcc",
                                    borderRadius: 3,
                                }}
                                title="Penjualan tetap diproses; kelebihannya tercatat sebagai stok minus."
                            >
                                Melebihi stok sistem: stok {Number(c.availableStock || 0)}, minus {Number(c.oversold_qty)}
                            </div>
                        )}
                        {Number(c.expired_qty || 0) > 0 && (
                            <div
                                style={{
                                    display: "inline-block",
                                    marginLeft: 8,
                                    padding: "1px 6px",
                                    fontSize: 12,
                                    color: "#8a6d3b",
                                    background: "#fcf8e3",
                                    border: "1px solid #faebcc",
                                    borderRadius: 3,
                                }}
                                title="Batch expired tidak ikut dijual dan tidak dihitung sebagai stok tersedia."
                            >
                                Ada batch expired di sistem ({Number(c.expired_qty)}), informasikan admin outlet
                            </div>
                        )}
                    </td>
                    <td>
                        <input
                            type="number"
                            className="form-control form-control-sm qty text-center"
                            min="1"
                            style={{ maxWidth: "90px", minWidth: "78px", height: "40px", fontSize: "18px", fontWeight: 600 }}
                            value={c.pivot.qty}
                            onChange={(event) =>
                                handleChangeQty(c.id, event.target.value)
                            }
                        />
                    </td>
                    <td>{formatRupiah(c.cashier_unit_price ?? c.harga_jual)}</td>
                    <td>
                        <button
                            className="btn btn-sm"
                            type="button"
                            aria-label={`Tambah qty ${c.name}`}
                            onClick={() => handleClickIncrease(c.id)}
                        >
                            <i className="fa fa-plus"></i>
                        </button>
                        <button
                            className="btn btn-sm"
                            type="button"
                            aria-label={`Kurangi qty ${c.name}`}
                            onClick={() => handleClickDecrease(c.id)}
                        >
                            <i className="fa fa-minus"></i>
                        </button>
                        <button
                            className="btn btn-danger btn-sm"
                            type="button"
                            aria-label={`Hapus ${c.name}`}
                            onClick={() => handleClickDelete(c.id)}
                        >
                            <i className="fa fa-trash"></i>
                        </button>
                    </td>
                    <td className="text-right">
                        {formatRupiah(c.cashier_subtotal ?? (c.harga_jual * c.pivot.qty))}
                    </td>
                </tr>
            ))}
        </>
    );
});

export default CartTableBody;
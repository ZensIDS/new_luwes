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
        window.requestAnimationFrame(() => rowRefs.current[nextIndex]?.focus());
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
                    className={selectedCartProductId === c.id ? "info" : ""}
                    onClick={() => setSelectedCartProductId(c.id)}
                    onFocus={() => setSelectedCartProductId(c.id)}
                    onKeyDown={(event) => handleRowKeyDown(event, index)}
                    title="Tab/Shift+Tab atau ↑/↓ untuk berpindah item; Enter untuk edit qty"
                >
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

import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import axios from "axios";
import Swal from "sweetalert2";
import Barcodes from "./Barcodes.jsx";
import CartTable from "./CartTable";
import Gallery from "./Gallery";
import SerialSelectionModal from "./SerialSelectionModal.jsx";
import Vouchers from "./Vouchers.jsx";
import { formatIdNumber, parseIdNumber } from "../utils";

const Cart = () => {
    const outlet = window.outlet || {};
    const [cart, setCart] = useState([]);
    const [products, setProducts] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [customerId, setCustomerId] = useState("");
    const [paymentMethods, setPaymentMethods] = useState([]);
    const [paymentMethodId, setPaymentMethodId] = useState("");
    const [appliedVouchers, setAppliedVouchers] = useState([]);
    const [barcode, setBarcode] = useState("");
    const [search, setSearch] = useState("");
    const [paidAmount, setPaidAmount] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [serialModal, setSerialModal] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedSerial, setSelectedSerial] = useState("");
    const [availableSerials, setAvailableSerials] = useState([]);
    const [selectedCartProductId, setSelectedCartProductId] = useState(null);
    const barcodeRef = useRef(null);
    const searchRef = useRef(null);
    const voucherRef = useRef(null);
    const paidRef = useRef(null);
    const customerRef = useRef(null);
    const paymentMethodRef = useRef(null);
    const cartTableRef = useRef(null);

    const getSubtotal = (items = cart) => items.reduce(
        (sum, item) => sum + Number(item.cashier_subtotal ?? (Number(item.pivot.qty || 0) * Number(item.harga_jual || 0))), 0
    );

    const discountAmount = (base, voucher) => {
        if (base < Number(voucher.min_purchase || 0)) return 0;
        let amount = voucher.type === "percentage"
            ? Math.round(base * Math.min(100, Number(voucher.value || 0)) / 100)
            : Math.round(Number(voucher.value || 0));
        if (voucher.max_discount_amount !== null && voucher.max_discount_amount !== undefined) {
            amount = Math.min(amount, Number(voucher.max_discount_amount));
        }
        return Math.max(0, Math.min(base, amount));
    };

    const getVoucherBreakdown = () => {
        const lineBalances = cart.map((item) => Number(item.cashier_subtotal ?? (Number(item.pivot.qty || 0) * Number(item.harga_jual || 0))));
        return appliedVouchers.map((voucher) => {
            const eligibleIndexes = cart.map((item, index) => ({ item, index }))
                .filter(({ item, index }) => (voucher.product_id === null || voucher.product_id === undefined || Number(voucher.product_id) === Number(item.id)) && lineBalances[index] > 0)
                .map(({ index }) => index);
            const base = eligibleIndexes.reduce((sum, index) => sum + lineBalances[index], 0);
            const amount = discountAmount(base, voucher);
            let remaining = amount;
            eligibleIndexes.forEach((index, position) => {
                const reduction = position === eligibleIndexes.length - 1
                    ? Math.min(lineBalances[index], remaining)
                    : Math.min(lineBalances[index], Math.round(amount * lineBalances[index] / Math.max(1, base)));
                lineBalances[index] -= reduction;
                remaining -= reduction;
            });
            return { ...voucher, amount };
        });
    };

    const voucherBreakdown = getVoucherBreakdown();
    const voucherTotal = voucherBreakdown.reduce((sum, voucher) => sum + voucher.amount, 0);
    const grandTotal = Math.max(0, getSubtotal() - voucherTotal);

    const loadCart = () => {
        axios.get("/cart?outlet_id=" + outlet.id).then((response) => {
            setCart(response.data || []);
            setPaidAmount((current) => current === "" ? "" : current);
        }).catch((error) => setErrorMessage(error.response?.data?.message || "Gagal memuat keranjang."));
    };

    const loadProducts = (term = "") => {
        const params = new URLSearchParams({ outlet_id: outlet.id, status_produk: "all" });
        if (term) params.set("search", term);
        axios.get("/product?" + params.toString())
            .then((response) => setProducts(response.data.data || []))
            .catch(() => setErrorMessage("Gagal memuat produk outlet."));
    };

    const loadCustomers = () => {
        axios.get("/customer", { headers: { Accept: "application/json" } })
            .then((response) => {
                const values = response.data || [];
                setCustomers(values);
            });
    };

    const loadPaymentMethods = () => {
        axios.get("/payment", { headers: { Accept: "application/json" } })
            .then((response) => setPaymentMethods(response.data || []));
    };

    useEffect(() => {
        loadCart();
        loadProducts();
        loadCustomers();
        loadPaymentMethods();
        setTimeout(() => barcodeRef.current?.focus(), 100);
    }, []);

    const addToCart = (value, serialNumber = null) => {
        const product = products.find((item) => item.barcode === value || item.code === value);
        if (!product) {
            setErrorMessage("Barcode tidak ditemukan pada stok outlet.");
            return;
        }

        if (product.is_serialized && !serialNumber) {
            const serials = (product.owner_stocks || [])
                .filter((stock) => stock.serial_number && Number(stock.qty) > 0)
                .map((stock) => ({ id: stock.id, serial: stock.serial_number, status: "available" }));
            setSelectedProduct(product);
            setAvailableSerials(serials);
            setSerialModal(true);
            return;
        }

        axios.post("/cart", { barcode: product.barcode, serial_number: serialNumber, outlet_id: outlet.id })
            .then(() => {
                setBarcode("");
                setErrorMessage("");
                loadCart();
                loadProducts(search);
                barcodeRef.current?.focus();
            })
            .catch((error) => setErrorMessage(error.response?.data?.message || "Produk tidak dapat ditambahkan."));
    };

    const handleScanBarcode = (event) => {
        event.preventDefault();
        if (barcode.trim()) addToCart(barcode.trim());
    };

    const updateCart = (productId, quantity) => {
        axios.post("/cart-change-qty", { product_id: productId, qty: quantity, outlet_id: outlet.id })
            .then(() => loadCart())
            .catch((error) => setErrorMessage(error.response?.data?.message || "Quantity melebihi stok outlet."));
    };

    const deleteCartItem = (productId) => {
        axios.post("/cart/destroy", { product_id: productId, outlet_id: outlet.id })
            .then(() => {
                loadCart();
                barcodeRef.current?.focus();
            });
    };

    const emptyCart = () => {
        axios.post("/cart-empty", { _method: "DELETE", outlet_id: outlet.id })
            .then(() => setCart([]));
    };

    const holdTransaction = () => {
        if (!cart.length) return;
        const name = window.prompt("Nama transaksi hold:");
        if (!name) return;
        axios.post("/wishlist-pos", {
            cart,
            outlet_id: outlet.id,
            customer_id: customerId || null,
            name,
        }).then(() => {
            setCart([]);
            setAppliedVouchers([]);
            setErrorMessage("");
            barcodeRef.current?.focus();
        }).catch((error) => setErrorMessage(error.response?.data?.message || "Transaksi tidak dapat di-hold."));
    };

    const recallTransaction = () => {
        axios.get("/wishlist-pos/" + outlet.id, { headers: { Accept: "application/json" } })
            .then((response) => {
                const holds = response.data || {};
                const names = Object.keys(holds);
                if (!names.length) {
                    setErrorMessage("Tidak ada transaksi hold.");
                    return;
                }
                const name = window.prompt("Pilih nama hold:\n" + names.join("\n"), names[0]);
                if (!name || !holds[name]) return;
                const customerGroups = holds[name];
                const customerIds = Object.keys(customerGroups);
                const customer = customerIds.length === 1
                    ? customerIds[0]
                    : window.prompt("ID customer (kosong untuk Umum):", customerIds[0] || "");
                axios.post("/wishlist/move-to-cart", {
                    name,
                    customer_id: customer || null,
                    outlet_id: outlet.id,
                }).then(() => {
                    setCustomerId(customer || "");
                    setAppliedVouchers([]);
                    loadCart();
                    barcodeRef.current?.focus();
                });
            })
            .catch((error) => setErrorMessage(error.response?.data?.message || "Transaksi hold tidak dapat dimuat."));
    };

    const addVoucher = (voucher) => {
        if (appliedVouchers.some((item) => item.code === voucher.code)) {
            setErrorMessage("Voucher tersebut sudah ada di transaksi.");
            return;
        }
        setAppliedVouchers((current) => [...current, voucher]);
        setErrorMessage("");
    };

    const removeVoucher = (code) => {
        setAppliedVouchers((current) => current.filter((voucher) => voucher.code !== code));
    };

    const handleSubmit = () => {
        setErrorMessage("");
        axios.post("/penjualan", {
            outlet_id: outlet.id,
            customer_id: customerId || null,
            paid_amount: parseIdNumber(paidAmount || grandTotal),
            payment_method_id: paymentMethodId || null,
            payment_method_name: paymentMethods.find((method) => String(method.id) === String(paymentMethodId))?.name || "Tunai",
            voucher_codes: appliedVouchers.map((voucher) => voucher.code),
        }).then((response) => {
            Swal.fire("Success!", "Pesanan berhasil dibuat", "success").then(() => {
                window.localStorage.setItem("last-pos-sale", response.data.order.id);
                window.location.href = response.data.redirect;
            });
        }).catch((error) => {
            setErrorMessage(error.response?.data?.message || "Checkout gagal.");
        });
    };

    useEffect(() => {
        const shortcut = (event) => {
            const typing = ["INPUT", "SELECT", "TEXTAREA"].includes(document.activeElement?.tagName);
            if (event.key === "F3") { event.preventDefault(); barcodeRef.current?.focus(); }
            if (event.key === "F2") { event.preventDefault(); searchRef.current?.focus(); }
            if (event.key === "F4") { event.preventDefault(); customerRef.current?.focus(); }
            if (event.key === "F5") { event.preventDefault(); cartTableRef.current?.focusFirstRow(); }
            if (event.key === "F7") { event.preventDefault(); paymentMethodRef.current?.focus(); }
            if (event.key === "F8") { event.preventDefault(); voucherRef.current?.focus(); }
            if (event.key === "F9") { event.preventDefault(); paidRef.current?.focus(); }
            if (event.key === "F10") { event.preventDefault(); if (cart.length && parseIdNumber(paidAmount) >= grandTotal) handleSubmit(); }
            if (event.ctrlKey && event.key.toLowerCase() === "h") { event.preventDefault(); holdTransaction(); }
            if (event.ctrlKey && event.key.toLowerCase() === "l") { event.preventDefault(); recallTransaction(); }
            if (event.ctrlKey && event.key.toLowerCase() === "p") {
                const lastSale = window.localStorage.getItem("last-pos-sale");
                if (lastSale) { event.preventDefault(); window.location.href = "/penjualan/" + lastSale + "/print"; }
            }
            if (event.ctrlKey && ["Backspace", "Delete"].includes(event.key) && !typing && cart.length) {
                event.preventDefault();
                deleteCartItem(cart[cart.length - 1].id);
            }
            if (!typing && event.key === "+" && selectedCartProductId) {
                const item = cart.find((value) => value.id === selectedCartProductId);
                if (item) updateCart(item.id, Number(item.pivot.qty) + 1);
            }
            if (!typing && event.key === "-" && selectedCartProductId) {
                const item = cart.find((value) => value.id === selectedCartProductId);
                if (item) item.pivot.qty > 1 ? updateCart(item.id, Number(item.pivot.qty) - 1) : deleteCartItem(item.id);
            }
            if (!typing && event.key === "Delete" && selectedCartProductId) deleteCartItem(selectedCartProductId);
            if (event.key === "Escape") { setSerialModal(false); barcodeRef.current?.focus(); }
        };
        window.addEventListener("keydown", shortcut);
        return () => window.removeEventListener("keydown", shortcut);
    }, [cart, paidAmount, grandTotal, appliedVouchers, selectedCartProductId]);

    return (
        <div className="row">
            <SerialSelectionModal
                serialModal={serialModal}
                setSerialModal={setSerialModal}
                selectedProduct={selectedProduct}
                selectedSerial={selectedSerial}
                setSelectedSerial={setSelectedSerial}
                availableSerials={availableSerials}
                handleSerialSelection={() => {
                    if (selectedSerial && selectedProduct) addToCart(selectedProduct.barcode, selectedSerial);
                }}
            />
            <div className="col-md-6 col-lg-5">
                <Barcodes
                    barcode={barcode}
                    handleScanBarcode={handleScanBarcode}
                    handleOnChangeBarcode={(event) => setBarcode(event.target.value)}
                    inputRef={barcodeRef}
                />
                <Vouchers
                    appliedVouchers={appliedVouchers}
                    onAddVoucher={addVoucher}
                    onRemoveVoucher={removeVoucher}
                    inputRef={voucherRef}
                    outletId={outlet.id}
                />
                <CartTable
                    cart={cart}
                    getSubtotal={getSubtotal}
                    voucherBreakdown={voucherBreakdown}
                    voucherTotal={voucherTotal}
                    grandTotal={grandTotal}
                    customers={customers}
                    customerId={customerId}
                    setCustomerId={setCustomerId}
                    customerInputRef={customerRef}
                    paidAmount={paidAmount}
                    setPaidAmount={setPaidAmount}
                    paymentMethods={paymentMethods}
                    paymentMethodId={paymentMethodId}
                    setPaymentMethodId={setPaymentMethodId}
                    paymentMethodInputRef={paymentMethodRef}
                    paidInputRef={paidRef}
                    handleChangeQty={(productId, value) => {
                        const qty = Number.parseInt(value, 10);
                        if (Number.isInteger(qty) && qty >= 1) updateCart(productId, qty);
                    }}
                    handleClickIncrease={(productId) => {
                        const item = cart.find((value) => value.id === productId);
                        if (item) updateCart(productId, Number(item.pivot.qty) + 1);
                    }}
                    handleClickDecrease={(productId) => {
                        const item = cart.find((value) => value.id === productId);
                        if (item && Number(item.pivot.qty) > 1) updateCart(productId, Number(item.pivot.qty) - 1);
                        else deleteCartItem(productId);
                    }}
                    handleClickDelete={deleteCartItem}
                    handleEmptyCart={emptyCart}
                    handleSubmit={handleSubmit}
                    errorMessage={errorMessage}
                    selectedCartProductId={selectedCartProductId}
                    setSelectedCartProductId={setSelectedCartProductId}
                    cartTableRef={cartTableRef}
                />
            </div>
            <div className="col-md-6 col-lg-7">
                <input ref={searchRef} type="text" className="form-control" placeholder="Cari produk lalu tekan Enter (F2)" value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === "Enter") loadProducts(search); }} />
                <br />
                <Gallery products={products} addProductToCart={(value) => addToCart(value)} />
            </div>
        </div>
    );
};

export default Cart;

if (document.getElementById("cart")) {
    createRoot(document.getElementById("cart")).render(<Cart />);
}

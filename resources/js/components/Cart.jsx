import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import axios from "axios";
import Swal from "sweetalert2";
import Barcodes from "./Barcodes.jsx";
import CartTable from "./CartTable";
import Gallery from "./Gallery";
import { parseIdNumber } from "../utils";

const ProductSearchModal = ({ open, inputRef, search, onSearch, products, productsLoading, onAddProduct, onClose }) => {
    useEffect(() => {
        if (open) window.setTimeout(() => inputRef.current?.focus(), 0);
    }, [open, inputRef]);

    if (!open) return null;

    return (
        <div
            role="dialog"
            aria-modal="true"
            onClick={onClose}
            style={{ position: "fixed", inset: 0, zIndex: 1040, background: "rgba(0,0,0,.45)", padding: "5vh 15px" }}
        >
            <div
                onClick={(event) => event.stopPropagation()}
                style={{ background: "#fff", maxWidth: 1100, maxHeight: "90vh", margin: "0 auto", overflow: "auto", borderRadius: 4, boxShadow: "0 8px 30px rgba(0,0,0,.3)" }}
            >
                <div className="box-header with-border">
                    <button type="button" className="close" onClick={onClose} aria-label="Tutup">&times;</button>
                    <h3 className="box-title"><i className="fa fa-search"></i> Cari Produk <small>(F2)</small></h3>
                </div>
                <div className="box-body">
                    <input
                        ref={inputRef}
                        type="search"
                        className="form-control input-lg"
                        placeholder="Cari nama, kode, atau barcode produk"
                        value={search}
                        onChange={(event) => onSearch(event.target.value)}
                        autoComplete="off"
                    />
                    <p className="text-muted small">Tekan Tab lalu Enter untuk memilih produk, atau klik produk untuk memasukkannya ke transaksi.</p>
                    {productsLoading && <p className="text-muted text-center">Memuat produk outlet...</p>}
                    {!productsLoading && !products.length && <p className="text-muted text-center">Produk tidak ditemukan di outlet ini.</p>}
                    {!productsLoading && <Gallery products={products} addProductToCart={onAddProduct} />}
                </div>
            </div>
        </div>
    );
};

const Cart = () => {
    const outlet = window.outlet || {};
    const outletProductsUrl = window.POS_PRODUCTS_URL || (outlet.id ? `/outlet/${outlet.id}/products` : "");
    const [cart, setCart] = useState([]);
    const [products, setProducts] = useState([]);
    const [productsLoading, setProductsLoading] = useState(false);
    const [customers, setCustomers] = useState([]);
    const [customerId, setCustomerId] = useState("");
    const [paymentMethods, setPaymentMethods] = useState([]);
    const [paymentMethodId, setPaymentMethodId] = useState("");
    const [paymentReference, setPaymentReference] = useState("");
    const [appliedVouchers, setAppliedVouchers] = useState([]);
    const [appliedPromotions, setAppliedPromotions] = useState([]);
    const [barcode, setBarcode] = useState("");
    const [search, setSearch] = useState("");
    const [paidAmount, setPaidAmount] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [completedSale, setCompletedSale] = useState(null);
    const [isPrintingReceipt, setIsPrintingReceipt] = useState(false);
    const [selectedCartProductId, setSelectedCartProductId] = useState(null);
    const [productModalOpen, setProductModalOpen] = useState(false);
    const barcodeRef = useRef(null);
    const searchRef = useRef(null);
    const voucherRef = useRef(null);
    const paidRef = useRef(null);
    const customerRef = useRef(null);
    const paymentMethodRef = useRef(null);
    const paymentReferenceRef = useRef(null);
    const cartTableRef = useRef(null);
    const promotionTableRef = useRef(null);
    const productsRequestRef = useRef(null);
    const productSearchTimerRef = useRef(null);

    const getBaseSubtotal = (items = cart) => items.reduce(
        (sum, item) => sum + Number(item.cashier_base_subtotal ?? item.cashier_subtotal ?? (Number(item.pivot.qty || 0) * Number(item.harga_jual || 0))), 0
    );

    const getSubtotal = (items = cart) => items.reduce(
        (sum, item) => sum + Number(item.cashier_subtotal ?? (Number(item.pivot.qty || 0) * Number(item.harga_jual || 0))), 0
    );

    const getPromotionTotal = (items = cart) => items.reduce(
        (sum, item) => sum + Number(item.cashier_promotion_discount || 0), 0
    );

    const getAppliedPromotionNames = (items = cart) => [...new Set(
        items.flatMap((item) => item.cashier_promotions || [])
    )];

    const discountAmount = (base, voucher) => {
        if (base < Number(voucher.min_purchase || 0)) return 0;
        const type = String(voucher.type || "").trim().toLowerCase();
        let amount = type === "percentage"
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
            const scopedProductIds = Array.isArray(voucher.product_ids) && voucher.product_ids.length
                ? voucher.product_ids.map(Number)
                : null;
            const eligibleIndexes = cart.map((item, index) => ({ item, index }))
                .filter(({ item, index }) => {
                    const productAllowed = scopedProductIds
                        ? scopedProductIds.includes(Number(item.id))
                        : (voucher.product_id === null || voucher.product_id === undefined || Number(voucher.product_id) === Number(item.id));
                    return productAllowed && lineBalances[index] > 0;
                })
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

    const loadCart = (promotionCodes = appliedPromotions.map((promotion) => promotion.code)) => {
        const params = new URLSearchParams({ outlet_id: outlet.id });
        promotionCodes.forEach((code) => params.append("promotion_codes[]", code));
        axios.get("/cart?" + params.toString()).then((response) => {
            setCart(response.data || []);
            setPaidAmount((current) => current === "" ? "" : current);
        }).catch((error) => setErrorMessage(error.response?.data?.message || "Gagal memuat keranjang."));
    };

    const loadProducts = (term = "", immediate = false) => {
        const fetchProducts = () => {
            if (!outletProductsUrl) {
                setProducts([]);
                setProductsLoading(false);
                setErrorMessage("Outlet kasir tidak ditemukan.");
                return;
            }

            productsRequestRef.current?.abort();
            const controller = new AbortController();
            productsRequestRef.current = controller;
            const params = new URLSearchParams({ status_produk: "all", per_page: "25", compact: "1" });
            const normalizedTerm = String(term || "").trim();
            if (normalizedTerm) params.set("search", normalizedTerm);

            setProducts([]);
            setProductsLoading(true);
            axios.get(`${outletProductsUrl}?${params.toString()}`, {
                headers: { Accept: "application/json" },
                signal: controller.signal,
            })
                .then((response) => {
                    if (productsRequestRef.current !== controller) return;
                    setProducts(Array.isArray(response.data?.data) ? response.data.data : []);
                })
                .catch((error) => {
                    if (axios.isCancel(error) || error.code === "ERR_CANCELED") return;
                    setErrorMessage("Gagal memuat produk outlet.");
                })
                .finally(() => {
                    if (productsRequestRef.current === controller) {
                        productsRequestRef.current = null;
                        setProductsLoading(false);
                    }
                });
        };

        window.clearTimeout(productSearchTimerRef.current);
        setProducts([]);
        setProductsLoading(true);
        if (immediate) {
            fetchProducts();
            return;
        }
        productSearchTimerRef.current = window.setTimeout(fetchProducts, 250);
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

    const syncPromotions = (promotions) => {
        const nextPromotions = promotions || [];
        setAppliedPromotions(nextPromotions);
        loadCart(nextPromotions.map((promotion) => promotion.code));
    };

    useEffect(() => {
        loadCart();
        loadProducts("", true);
        loadCustomers();
        loadPaymentMethods();
        const focusTimer = setTimeout(() => barcodeRef.current?.focus(), 100);
        return () => {
            clearTimeout(focusTimer);
            window.clearTimeout(productSearchTimerRef.current);
            productsRequestRef.current?.abort();
        };
    }, []);

    const addToCart = (value) => {
        const code = String(value || "").trim();
        const localProduct = products.find((item) => item.barcode === code || item.code === code);
        const productRequest = localProduct
            ? Promise.resolve(localProduct)
            : axios.get(`${outletProductsUrl}?` + new URLSearchParams({ search: code, status_produk: "all", per_page: "25", compact: "1" }), {
                headers: { Accept: "application/json" },
            })
                .then((response) => (response.data.data || []).find((item) => item.barcode === code || item.code === code));

        productRequest.then((product) => {
            if (!product) {
                setErrorMessage("Barcode tidak ditemukan pada stok outlet.");
                return;
            }

            axios.post("/cart", { barcode: product.barcode || product.code, outlet_id: outlet.id })
                .then(() => {
                    setBarcode("");
                    setErrorMessage("");
                    loadCart();
                    barcodeRef.current?.focus();
                })
                .catch((error) => setErrorMessage(error.response?.data?.message || "Produk tidak dapat ditambahkan."));
        }).catch(() => setErrorMessage("Barcode tidak ditemukan pada stok outlet."));
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
                    loadCart([]);
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

    const addPromotion = (promotion) => {
        if (appliedPromotions.some((item) => item.code === promotion.code)) {
            setErrorMessage("Promo tersebut sudah dipilih di transaksi.");
            return;
        }
        const nextPromotions = [...appliedPromotions, promotion];
        setAppliedPromotions(nextPromotions);
        setErrorMessage("");
        loadCart(nextPromotions.map((item) => item.code));
    };

    const removePromotion = (code) => {
        const nextPromotions = appliedPromotions.filter((promotion) => promotion.code !== code);
        setAppliedPromotions(nextPromotions);
        setErrorMessage("");
        loadCart(nextPromotions.map((item) => item.code));
    };

    const resetCheckoutState = () => {
        setCart([]);
        setAppliedVouchers([]);
        setAppliedPromotions([]);
        setCustomerId("");
        setPaymentMethodId("");
        setPaymentReference("");
        setPaidAmount("");
        setBarcode("");
        setSelectedCartProductId(null);
        setCompletedSale(null);
        setErrorMessage("");
        barcodeRef.current?.focus();
    };

    const handleSubmit = () => {
        if (isSubmitting || completedSale) return;

        setErrorMessage("");
        const selectedPaymentMethod = paymentMethods.find((method) => String(method.id) === String(paymentMethodId));
        const paymentMethodName = selectedPaymentMethod?.name || "Tunai";
        const isCash = !paymentMethodId || /tunai|cash/i.test(paymentMethodName);
        const paidValue = parseIdNumber(paidAmount);

        if (!paidAmount.trim()) {
            setErrorMessage("Uang Diterima (F9) wajib diisi sebelum memproses penjualan.");
            paidRef.current?.focus();
            return;
        }

        if (paidValue < grandTotal) {
            setErrorMessage("Uang Diterima (F9) kurang dari Grand Total.");
            paidRef.current?.focus();
            return;
        }

        if (!isCash && !paymentReference.trim()) {
            setErrorMessage("Nomor Referensi wajib diisi untuk metode pembayaran ini.");
            paymentReferenceRef.current?.focus();
            return;
        }

        setIsSubmitting(true);
        axios.post("/penjualan", {
            outlet_id: outlet.id,
            customer_id: customerId || null,
            paid_amount: paidValue,
            payment_method_id: paymentMethodId || null,
            payment_method_name: paymentMethodName,
            payment_reference: isCash ? null : paymentReference.trim() || null,
            voucher_codes: appliedVouchers.map((voucher) => voucher.code),
            promotion_codes: appliedPromotions.map((promotion) => promotion.code),
        }).then((response) => {
            window.localStorage.setItem("last-pos-sale", response.data.order.id);
            setIsSubmitting(false);
            const completedSale = {
                code: response.data.order.code,
                print: response.data.print,
            };
            setCompletedSale(completedSale);
            Swal.fire({
                icon: "success",
                title: "Penjualan tersimpan",
                text: `Transaksi ${completedSale.code} berhasil disimpan. Pilih tindakan berikutnya.`,
                showCancelButton: true,
                confirmButtonText: "Cetak",
                cancelButtonText: "Close",
                allowOutsideClick: false,
                allowEscapeKey: false,
                reverseButtons: true,
            }).then((result) => {
                resetCheckoutState();
                if (result.isConfirmed) {
                    printCompletedSale(completedSale.print);
                } else {
                    window.location.reload();
                }
            });
        }).catch((error) => {
            setIsSubmitting(false);
            setErrorMessage(error.response?.data?.message || "Checkout gagal.");
        });
    };

    const printCompletedSale = (printUrl) => {
        if (!printUrl || isPrintingReceipt) return;

        setIsPrintingReceipt(true);
        const frame = document.createElement("iframe");
        frame.title = "Cetak struk";
        frame.setAttribute("aria-hidden", "true");
        Object.assign(frame.style, {
            position: "fixed",
            left: "-10000px",
            top: "0",
            width: "80mm",
            height: "1px",
            border: "0",
        });

        let fallbackTimer = null;
        const finishPrinting = () => {
            if (fallbackTimer) window.clearTimeout(fallbackTimer);
            frame.remove();
            setIsPrintingReceipt(false);
            window.location.reload();
        };

        frame.addEventListener("load", () => {
            const printWindow = frame.contentWindow;
            if (!printWindow) {
                setIsPrintingReceipt(false);
                frame.remove();
                setErrorMessage("Struk tidak dapat disiapkan untuk dicetak.");
                return;
            }

            printWindow.addEventListener("afterprint", finishPrinting, { once: true });
            printWindow.focus();
            printWindow.print();
            fallbackTimer = window.setTimeout(finishPrinting, 2000);
        }, { once: true });
        frame.addEventListener("error", () => {
            frame.remove();
            setIsPrintingReceipt(false);
            setErrorMessage("Struk tidak dapat disiapkan untuk dicetak.");
        }, { once: true });
        document.body.appendChild(frame);
        frame.src = printUrl;
    };

    useEffect(() => {
        const shortcut = (event) => {
            const typing = ["INPUT", "SELECT", "TEXTAREA"].includes(document.activeElement?.tagName);
            if (event.key === "F3") { event.preventDefault(); barcodeRef.current?.focus(); }
            if (event.key === "F2") {
                event.preventDefault();
                setProductModalOpen(true);
                loadProducts(search, true);
                window.setTimeout(() => searchRef.current?.focus(), 0);
            }
            if (event.key === "F4") { event.preventDefault(); customerRef.current?.focus(); }
            if (event.key === "F5") { event.preventDefault(); cartTableRef.current?.focusFirstRow(); }
            if (event.key === "F6") { event.preventDefault(); promotionTableRef.current?.open(); }
            if (event.key === "F7") { event.preventDefault(); paymentMethodRef.current?.focus(); }
            if (event.key === "F8") { event.preventDefault(); voucherRef.current?.focus(); }
            if (event.key === "F9") { event.preventDefault(); paidRef.current?.focus(); }
            if (event.key === "F10") { event.preventDefault(); if (!completedSale && cart.length) handleSubmit(); }
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
            if (event.key === "Escape") {
                setProductModalOpen(false);
                barcodeRef.current?.focus();
            }
        };
        window.addEventListener("keydown", shortcut);
        return () => window.removeEventListener("keydown", shortcut);
    }, [cart, paidAmount, grandTotal, appliedVouchers, appliedPromotions, selectedCartProductId, paymentReference, paymentMethodId, isSubmitting, completedSale, search]);

    return (
        <div className="row pos-cashier-layout">
            <ProductSearchModal
                open={productModalOpen}
                inputRef={searchRef}
                search={search}
                onSearch={(term) => {
                    setSearch(term);
                    loadProducts(term);
                }}
                products={products}
                productsLoading={productsLoading}
                onAddProduct={(value) => {
                    setProductModalOpen(false);
                    addToCart(value);
                }}
                onClose={() => setProductModalOpen(false)}
            />
            <div className="col-md-12 pos-cashier-column">
                <Barcodes
                    barcode={barcode}
                    handleScanBarcode={handleScanBarcode}
                    handleOnChangeBarcode={(event) => setBarcode(event.target.value)}
                    inputRef={barcodeRef}
                />
                <div className="pos-cashier-details">
                    <CartTable
                        cart={cart}
                        getBaseSubtotal={getBaseSubtotal}
                        getSubtotal={getSubtotal}
                        promotionTotal={getPromotionTotal()}
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
                        paymentReference={paymentReference}
                        setPaymentReference={setPaymentReference}
                        paymentMethodInputRef={paymentMethodRef}
                        paymentReferenceInputRef={paymentReferenceRef}
                        paidInputRef={paidRef}
                        voucherInputRef={voucherRef}
                        outletId={outlet.id}
                        appliedVouchers={appliedVouchers}
                        onAddVoucher={addVoucher}
                        onRemoveVoucher={removeVoucher}
                        appliedPromotions={appliedPromotions}
                        onAddPromotion={addPromotion}
                        onRemovePromotion={removePromotion}
                        selectedProducts={cart.map((item) => ({
                            id: item.id,
                            qty: Number(item.pivot?.qty || 0),
                            baseSubtotal: Number(item.cashier_base_subtotal || 0),
                            promotionBreakdown: item.cashier_promotion_breakdown || [],
                        }))}
                        appliedPromotionNames={getAppliedPromotionNames()}
                        promotionTableRef={promotionTableRef}
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
                        isSubmitting={isSubmitting}
                        errorMessage={errorMessage}
                        selectedCartProductId={selectedCartProductId}
                        setSelectedCartProductId={setSelectedCartProductId}
                        cartTableRef={cartTableRef}
                        onSyncPromotions={syncPromotions}
                    />
                </div>
            </div>
        </div>
    );
};

export default Cart;

if (document.getElementById("cart")) {
    createRoot(document.getElementById("cart")).render(<Cart />);
}

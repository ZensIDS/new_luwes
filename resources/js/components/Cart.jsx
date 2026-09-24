import React, { useState, useEffect } from "react";
import { createRoot } from "react-dom/client";
import axios from "axios";
import { sum } from "lodash";
import Swal from "sweetalert2";
import Barcodes from "./Barcodes.jsx";
import Customers from "./Customers";
// import Kas from "./Kas";
import CartTable from "./CartTable";
import Gallery from "./Gallery";
import Wishlist from "./Wishlist";
import SerialSelectionModal from "./SerialSelectionModal.jsx";
import Vouchers from "./Vouchers.jsx";

const Cart = () => {
    const [cart, setCart] = useState([]);
    const [products, setProducts] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [customerId, setCustomerId] = useState("");
    const [salesmans, setSalesmans] = useState([]);
    const [salesmanId, setSalesmanId] = useState("");
    const [vouchers, setVouchers] = useState([]);
    const [voucherId, setVoucherId] = useState("");
    // const [kas, setKas] = useState([]);
    // const [kasId, setKasId] = useState("");
    const [kasir, setKasir] = useState([]);
    const [kasirId, setKasirId] = useState("");
    const [barcode, setBarcode] = useState("");
    const [search, setSearch] = useState("");
    const [discount, setDiscount] = useState(0);
    const [voucherDiscount, setVoucherDiscount] = useState(0);
    const [total, setTotal] = useState(0);
    const [wishlist, setWishlist] = useState([]);
    const [errorMessage, setErrorMessage] = useState("");
    // Add these state variables to your existing Cart component
    const [serialModal, setSerialModal] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedSerial, setSelectedSerial] = useState("");
    const [availableSerials, setAvailableSerials] = useState([]);
    const outlet = window.outlet;
    const limitDiscount = window.user?.limit_discount || 0;

    useEffect(() => {
        setTotal(getTotal(cart) - discount - voucherDiscount);
    }, [cart, discount, voucherDiscount]);

    useEffect(() => {
        loadCart();
        loadProducts();
        loadCustomers();
        loadVouchers();
        loadSalesmans();
        // loadKas();
        loadKasir();
        loadWishlist();
    }, []);

    //Data Products
    const loadProducts = (search = "") => {
        let queryParams = [];

        if (search) {
            queryParams.push(`search=${search}`);
        }

        if (outlet && outlet.id) {
            queryParams.push(`outlet_id=${outlet.id}`);
        }

        const queryString =
            queryParams.length > 0 ? `?${queryParams.join("&")}` : "";

        axios.get(`/product${queryString}`).then((res) => {
            const products = res.data.data;
            setProducts(products);
            console.log(products);
        });
    };

    const handleChangeSearch = (event) => {
        setSearch(event.target.value);
    };

    const handleSeach = (event) => {
        if (event.keyCode === 13) {
            loadProducts(event.target.value);
        }
    };

    //Data Cart
    const loadCart = () => {
        axios.get("/cart").then((res) => {
            const cart = res.data;
            setCart(cart);
        });
    };

    const addToCart = (barcode, serialNumber = null) => {
        let product = products.find((p) => p.barcode === barcode);
        if (!!product) {
            console.log("product : ", product);
            console.log("product stocks: ", product.stocks);
            console.log("serial number: ", serialNumber);

            if (product.is_serialized && !serialNumber) {
                // Show serial selection modal
                console.log("Show serial selection modal");
                setSelectedProduct(product);

                // Convert to array instead of object
                const serials = [];
                if (product.stocks && Array.isArray(product.stocks)) {
                    product.stocks.forEach((stock) => {
                        if (stock.serial_number && stock.qty > 0) {
                            serials.push({
                                id: stock.id,
                                serial: stock.serial_number,
                                status: stock.status,
                            });
                        }
                    });
                }

                console.log("serials : ", serials);
                setAvailableSerials(serials);
                setSerialModal(true);
                return;
            }

            // For non-serialized products
            if (!product.is_serialized) {
                let cartItem = cart.find((c) => c.id === product.id);
                if (!!cartItem) {
                    setCart(
                        cart.map((c) => {
                            if (
                                c.id === product.id &&
                                product.qty > c.pivot.qty
                            ) {
                                c.pivot.qty += 1;
                            }
                            return c;
                        })
                    );
                } else {
                    if (product.qty > 0) {
                        product = {
                            ...product,
                            pivot: {
                                qty: 1,
                                product_id: product.id,
                                user_id: 1,
                            },
                        };
                        setCart([...cart, product]);
                    }
                }
            }

            const payload = { barcode };
            if (serialNumber) payload.serial_number = serialNumber;

            axios
                .post("/cart", payload)
                .then((res) => {
                    loadCart();
                    setSerialModal(false);
                    setSelectedSerial("");
                    console.log(res);
                })
                .catch((err) => {
                    console.log("Error!", err.response.data.message, "error");
                    Swal.fire("Error!", err.response.data.message, "error");
                });
        }
    };

    const handleSerialSelection = () => {
        if (selectedSerial && selectedProduct) {
            addToCart(selectedProduct.barcode, selectedSerial);
        }
    };

    const updateCart = (product_id, newQty) => {
        const updatedCart = cart.map((c) => {
            if (c.id === product_id) {
                c.pivot.qty = newQty;
            }
            return c;
        });
        setCart(updatedCart);
        axios
            .post("/cart-change-qty", { product_id, qty: newQty })
            .then((res) => {})
            .catch((err) => {
                console.log("Error!", err.response.data.message, "error");
                Swal.fire("Error!", err.response.data.message, "error").then(
                    () => {
                        location.reload();
                        setState((prevState) => ({
                            ...prevState,
                            error: true,
                        }));
                    }
                );
            });
    };

    const addProductToCart = (barcode) => {
        addToCart(barcode);
    };

    const handleClickIncrease = (product_id) => {
        const currentQty = cart.find((c) => c.id === product_id).pivot.qty;
        updateCart(product_id, Number(currentQty) + 1);
    };

    const handleClickDecrease = (product_id) => {
        const currentQty = cart.find((c) => c.id === product_id).pivot.qty;
        if (currentQty > 1) {
            updateCart(product_id, Number(currentQty) - 1);
        } else {
            // delete the product
            handleClickDelete(product_id);
        }
    };

    const handleChangeQty = (product_id, qty) => {
        const parsedQty = parseInt(qty, 10);
        if (!isNaN(parsedQty) && parsedQty >= 1) {
            updateCart(product_id, parsedQty);
        }
    };

    //Delete 1 item
    const handleClickDelete = (product_id) => {
        axios
            .post("/cart/destroy", { product_id, _method: "DELETE" })
            .then((res) => {
                const updatedCart = cart.filter((c) => c.id !== product_id);
                setCart(updatedCart);
            });
    };

    //Delete All item
    const handleEmptyCart = () => {
        Swal.fire({
            title: "Are you sure?",
            text: "Do you want to clear your cart?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, clear it!",
            cancelButtonText: "No, keep it",
        }).then((result) => {
            if (result.isConfirmed) {
                axios.post("/cart-empty", { _method: "DELETE" }).then((res) => {
                    setCart([]);
                });
            }
        });
    };

    const getTotal = (cart) => {
        return cart.reduce(
            (sum, item) => sum + item.pivot.qty * item.harga_jual,
            0
        );
    };

    const handleChangeTotal = (event) => {
        setTotal(event.target.value);
    };

    //Data Customers
    const loadCustomers = () => {
        axios
            .get("/customer")
            .then((res) => {
                const customers = res.data;
                setCustomers(customers);
                // Only set customerId if there are customers
                if (customers.length > 0) {
                    setCustomerId(customers[0].id);
                } else {
                    setCustomerId(null);
                }
            })
            .catch((error) => {
                console.error("Error loading customers:", error);
                setCustomers([]);
                setCustomerId(null);
            });
    };

    // Data Vouchers
    const loadVouchers = () => {
        axios
            .get("/voucher")
            .then((res) => {
                const vouchers = res.data ?? [];
                setVouchers(vouchers);

                if (vouchers.length > 0 && vouchers[0]?.id) {
                    setVoucherId(vouchers[0].id);
                } else {
                    setVoucherId(null); // or undefined or a fallback value
                }
            })
            .catch((error) => {
                console.error("Failed to load vouchers:", error);
                setVouchers([]);
                setVoucherId(null); // handle the error case gracefully
            });
    };

    // Data Salesmans
    const loadSalesmans = () => {
        axios
            .get("/salesman")
            .then((res) => {
                const salesmans = res.data ?? [];
                setSalesmans(salesmans);

                if (salesmans.length > 0 && salesmans[0]?.id) {
                    setSalesmanId(salesmans[0].id);
                } else {
                    setSalesmanId(null); // or undefined or a fallback value
                }
            })
            .catch((error) => {
                console.error("Failed to load salesmans:", error);
                setSalesmans([]);
                setSalesmanId(null); // handle the error case gracefully
            });
    };

    //Data Kas
    // const loadKas = () => {
    //     axios.get(`/kas?outlet_id=${outlet.id}`).then((res) => {
    //         const kas = res.data;
    //         setKas(kas);
    //     });
    // };

    //Data Kasir
    const loadKasir = () => {
        axios.get("/kasir").then((res) => {
            const kasir = res.data;
            setKasir(kasir);
        });
    };

    const handleOnChangeBarcode = (event) => {
        setBarcode(event.target.value);
    };

    const handleScanBarcode = (event) => {
        event.preventDefault();
        addToCart(barcode);
    };

    const handleDiscountChange = (event) => {
        let value = parseInt(event.target.value, 10);

        if (isNaN(value)) {
            setDiscount(0);
            return;
        }

        // Clamp value between 0 and limitDiscount
        value = Math.min(Math.max(value, 0), limitDiscount);
        setDiscount(value);
    };

    //Hold
    const loadWishlist = () => {
        axios.get(`/wishlist-pos/${outlet.id}`).then((res) => {
            setWishlist(res.data);
        });
    };

    const handleClickWishlist = () => {
        Swal.fire({
            title: "Wishlist Name",
            input: "text",
            showCancelButton: true,
            confirmButtonText: "Send",
            showLoaderOnConfirm: true,
            preConfirm: (name) => {
                return axios
                    .post("/wishlist-pos", {
                        cart,
                        customer_id: customerId,
                        outlet_id: outlet.id,
                        name: name,
                    })
                    .then((res) => {
                        loadWishlist();
                        loadCart();
                        loadWishlist();
                        loadProducts();
                        setBarcode("");
                        // setCustomerId("");
                        setSearch("");
                        setDiscount(0);
                        Swal.fire(
                            "Success!",
                            "Items have been added to your wishlist",
                            "success"
                        );
                    })
                    .catch((err) => {
                        console.log(
                            "Error!",
                            err.response.data.message,
                            "error"
                        );
                        Swal.fire("Error!", err.response.data.message, "error");
                    });
            },
            allowOutsideClick: () => !Swal.isLoading(),
        });
    };

    const handleMoveToCart = (name, customer_id) => {
        axios
            .post("/wishlist/move-to-cart", { name, customer_id })
            .then((res) => {
                loadCart();
                loadWishlist();
                loadProducts();
                setBarcode("");
                setCustomerId(customer_id);
                setSearch("");
                setDiscount(0);
            });
    };

    // Handle form submission
    const handleSubmit = (event) => {
        // Get the total amount from the input field
        // const totalAmount = $(".total").val();
        const totalAmount = getTotal(cart);

        // Send the POST request with the data
        axios
            .post("/penjualan", {
                customer_id: customerId,
                voucher_id: voucherId,
                outlet_id: outlet.id,
                salesman_id: salesmanId,
                kasir_id: kasirId,
                total: totalAmount,
                discount: discount,
                cart: cart,
            })
            .then((res) => {
                loadCart();
                loadProducts();
                loadCustomers();
                loadVouchers();
                loadSalesmans();
                // loadKas();
                loadKasir();
                loadWishlist();
                $("#tombolSubmit").modal("hide");
                Swal.fire(
                    "Success!",
                    "Pesanan berhasil dibuat",
                    "success"
                ).then(() => {
                    window.location.reload(true); // <-- added this line to refresh the page
                });
            })
            .catch((err) => {
                // console.log(err.response.data.message);
                // Swal.showValidationMessage(err.response.data.message);
                setErrorMessage(err.response.data.message);
            });
    };

    // Add an onClick event handler to the "OK" button in the modal footer
    // $("#checkout").on("click", handleSubmit);

    return (
        <div className="row">
            <SerialSelectionModal
                serialModal={serialModal}
                setSerialModal={setSerialModal}
                selectedProduct={selectedProduct}
                selectedSerial={selectedSerial}
                setSelectedSerial={setSelectedSerial}
                availableSerials={availableSerials}
                handleSerialSelection={handleSerialSelection}
            />
            <div className="col-12 col-sm-12">
                <Wishlist
                    wishlist={wishlist}
                    handleMoveToCart={handleMoveToCart}
                />
            </div>
            <div className="col-md-6 col-lg-5">
                <Barcodes
                    barcode={barcode}
                    handleScanBarcode={handleScanBarcode}
                    handleOnChangeBarcode={handleOnChangeBarcode}
                />
                <Vouchers
                    key={voucherId}
                    vouchers={vouchers}
                    voucherId={voucherId}
                    setVoucherId={setVoucherId}
                    setVoucherDiscount={setVoucherDiscount}
                />
                {/* <Customers
                    key={customerId}
                    customers={customers}
                    customerId={customerId}
                    setCustomerId={setCustomerId}
                /> */}
                <CartTable
                    cart={cart}
                    discount={discount}
                    voucherDiscount={voucherDiscount}
                    handleChangeQty={handleChangeQty}
                    handleClickIncrease={handleClickIncrease}
                    handleClickDecrease={handleClickDecrease}
                    handleClickDelete={handleClickDelete}
                    handleDiscountChange={handleDiscountChange}
                    getTotal={getTotal}
                    handleEmptyCart={handleEmptyCart}
                    handleClickWishlist={handleClickWishlist}
                    handleSubmit={handleSubmit}
                    handleChangeTotal={handleChangeTotal}
                    limitDiscount={limitDiscount}
                    total={total}
                    errorMessage={errorMessage}
                    // kas={kas}
                    // kasId={kasId}
                    // setKasId={setKasId}
                    salesmans={salesmans}
                    salesmanId={salesmanId}
                    setSalesmanId={setSalesmanId}
                    kasir={kasir}
                    kasirId={kasirId}
                    setKasirId={setKasirId}
                />
                <hr />
                {/* <Kas kas={kas} kasId={kasId} setKasId={setKasId} /> */}
            </div>
            <hr />
            <div className="col-md-6 col-lg-7">
                <div className="form-group">
                    <input
                        type="text"
                        className="form-control"
                        placeholder="Cari Product..."
                        onChange={handleChangeSearch}
                        onKeyDown={handleSeach}
                    />
                    <br />
                    <Gallery
                        products={products}
                        addProductToCart={addProductToCart}
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

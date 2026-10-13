import React from "react";
import { formatRupiah } from "../utils";

// Daftar hasil pencarian produk kasir: tanpa gambar, semua kolom rata kiri.
const Gallery = ({ products, addProductToCart }) => {
    return (
        <div className="table-responsive text-nowrap">
            <table className="table table-sm table-striped table-bordered">
                <thead>
                    <tr>
                        <th className="w-20 text-left">Kode</th>
                        <th className="w-35 text-left">Nama</th>
                        <th className="w-20 text-left">Total Stock</th>
                        <th className="w-20 text-left">Harga Jual</th>
                    </tr>
                </thead>
                <tbody>
                    {products.map((p) => {
                        const stock = Number(p.total_stock ?? 0);

                        return (
                            <tr
                                key={p.id}
                                className="cursor-pointer"
                                onClick={() => addProductToCart(p.barcode)}
                                tabIndex="0"
                                onKeyDown={(event) => {
                                    if (event.key === "Enter" || event.key === " ") {
                                        event.preventDefault();
                                        addProductToCart(p.barcode);
                                    }
                                }}
                                title="Tekan Enter untuk memasukkan produk"
                            >
                                <td className="text-left">{p.barcode}</td>
                                <td className="text-left">{p.name}</td>
                                <td className={"text-left" + (stock <= 0 ? " text-danger" : "")}>{stock}</td>
                                <td className="text-left">{formatRupiah(p.harga_jual)}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
};

export default Gallery;
import React from "react";
import "../../css/Gallery.css";
import { formatRupiah } from "../utils";

const Gallery = ({ products, addProductToCart }) => {
    return (
        <div className="table-responsive text-nowrap">
            <table className="table table-sm table-striped table-bordered">
                <thead>
                    <tr>
                        <th className="w-10 text-center">Image</th>
                        <th className="w-15">Kode</th>
                        <th className="w-25">Nama</th>
                        <th className="w-10">Total Stock</th>
                        <th className="w-30">Serials</th>
                        <th className="w-10 text-right">Harga Jual</th>
                    </tr>
                </thead>
                <tbody>
                    {products.map((p) => (
                        <tr
                            key={p.id}
                            className="cursor-pointer"
                            onClick={() => addProductToCart(p.barcode)}
                        >
                            <td className="text-center align-middle">
                                <img
                                    src={p.image_url}
                                    alt={p.name}
                                    style={{
                                        width: "50px",
                                        height: "50px",
                                        objectFit: "cover",
                                    }}
                                />
                            </td>

                            <td>{p.barcode}</td>
                            <td>{p.name}</td>
                            <td>{p.total_stock}</td>
                            <td>
                                {p.stocks
                                    .filter(
                                        (s) => s.available && s.serial_number
                                    )
                                    .map((s) => s.serial_number)
                                    .join(", ") || "N/A"}
                            </td>
                            <td className="text-right">
                                {formatRupiah(p.harga_jual)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default Gallery;

import React, { useState, useEffect } from "react";

const Wishlist = ({ wishlist, handleMoveToCart }) => {
    return (
        <div className="table-responsive">
            <table className="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Hold Name</th>
                        <th>Products</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {Object.entries(wishlist).map(([name, customers]) =>
                        Object.entries(customers).map(
                            ([customer_id, items]) => (
                                <tr key={`${name}-${customer_id}`}>
                                    <td>{name}</td>
                                    <td>
                                        {items
                                            .map((item) => item.name)
                                            .join(", ")}
                                    </td>
                                    <td>
                                        <button
                                            className="btn btn-sm btn-primary"
                                            onClick={() =>
                                                handleMoveToCart(
                                                    name,
                                                    customer_id
                                                )
                                            }
                                        >
                                            Move to Cart
                                        </button>
                                    </td>
                                </tr>
                            )
                        )
                    )}
                </tbody>
            </table>
        </div>
    );
};

export default Wishlist;

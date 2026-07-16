import React from "react";

const Customers = ({ customers, customerId, setCustomerId }) => {
    const handleCustomerChange = (e) => {
        setCustomerId(e.target.value);
    };

    return (
        <div className="form-group row">
            <div className="col-sm-8">
                <select
                    id="customer"
                    className="form-control"
                    value={customerId || ""}
                    onChange={handleCustomerChange}
                >
                    <option value="">Select a customer</option>
                    {customers.map((customer) => (
                        <option
                            key={`customer-${customer.id}`}
                            value={customer.id}
                        >
                            {customer.name}, {customer.alamat}
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-sm-4">
                <a className="btn btn-success btn-sm" href="/customer/create">
                    Tambah Customer
                </a>
            </div>
        </div>
    );
};

export default Customers;

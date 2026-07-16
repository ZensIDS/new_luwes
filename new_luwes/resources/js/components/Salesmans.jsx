import React from "react";
const Salesman = ({ salesmans, salesmanId, setSalesmanId }) => {
    return (
        <div className="form-group row">
            <div className="col-sm-12">
                <select
                    className="form-control"
                    value={salesmanId}
                    onChange={(e) => setSalesmanId(e.target.value)}
                    required
                >
                    <option value="" selected disabled>
                        Pilih Sales
                    </option>
                    {salesmans.map((salesman) => (
                        <option key={`salesman-${salesman.id}`} value={salesman.id}>
                            {`${salesman.name}`}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
};
export default Salesman;

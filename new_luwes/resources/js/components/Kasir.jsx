import React from "react";
const Kasir = ({ kasir, kasirId, setKasirId }) => {
    return (
        <div className="form-group row">
            <div className="col-sm-12">
                <select
                    className="form-control"
                    onChange={(e) => setKasirId(e.target.value)}
                    required
                >
                    <option value="" selected disabled>Pilih Kasir</option>
                    {kasir.map((kasir) => (
                        <option key={`kasir-${ kasir.id }`} value={kasir.id}>
                            {`${kasir.name}`}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
};
export default Kasir;

import React from "react";
const Kas = ({ kas, kasId, setKasId }) => {
    return (
        <div className="form-group row">
            <div className="col-sm-12">
                <select
                    className="form-control"
                    value={kasId}
                    onChange={(e) => setKasId(e.target.value)}
                    required
                >
                    <option value="" selected disabled>
                        Pilih Metode Pembayaran
                    </option>
                    {kas.map((kas) => (
                        <option key={`kas-${kas.id}`} value={kas.id}>
                            {`${kas.name}`}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
};
export default Kas;

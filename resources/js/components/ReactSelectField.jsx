import React, { forwardRef } from "react";
import Select from "react-select";

const selectStyles = {
    control: (base, state) => ({
        ...base,
        minHeight: 34,
        borderColor: state.isFocused ? "#3c8dbc" : "#d2d6de",
        boxShadow: state.isFocused ? "0 0 0 1px #3c8dbc" : "none",
        fontSize: 13,
    }),
    valueContainer: (base) => ({ ...base, padding: "2px 8px" }),
    indicatorsContainer: (base) => ({ ...base, minHeight: 34 }),
    menu: (base) => ({ ...base, zIndex: 99999 }),
    menuPortal: (base) => ({ ...base, zIndex: 99999 }),
};

const ReactSelectField = forwardRef(({ value, onChange, options, placeholder, isClearable = true }, ref) => {
    const selectedOption = options.find((option) => String(option.value) === String(value ?? "")) || null;

    return (
        <Select
            ref={ref}
            value={selectedOption}
            options={options}
            onChange={(option) => onChange(option?.value ?? "")}
            placeholder={placeholder}
            isClearable={isClearable}
            isSearchable
            styles={selectStyles}
            menuPortalTarget={document.body}
            menuPosition="fixed"
            noOptionsMessage={() => "Data tidak ditemukan"}
        />
    );
});

export { selectStyles };
export default ReactSelectField;

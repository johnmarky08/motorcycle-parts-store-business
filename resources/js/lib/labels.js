// Readable names for internal codes (roles, stock movements, dataset columns, model features),
// so screens never show values such as "sales_personnel" or "units_sold".

export const ROLE_LABELS = {
    admin: "Admin",
    instructor: "Instructor",
    inventory_clerk: "Inventory Clerk",
    sales_personnel: "Sales Personnel",
    user: "User",
};

export const MOVEMENT_LABELS = {
    received: "Stock Received",
    reduction: "Stock Reduced",
    sale: "Sale",
    correction: "Correction",
};

const FIELD_LABELS = {
    // dataset columns
    transaction_number: "Transaction Number",
    transaction_date: "Transaction Date",
    product_id: "Product ID",
    product_name: "Product Name",
    quantity: "Quantity",
    unit_price: "Unit Price",
    total_amount: "Total Amount",
    week_start: "Week Start",
    category: "Category",
    units_sold: "Units Sold",
    avg_unit_price: "Average Unit Price",
    revenue: "Revenue",
    // model features
    t: "Time Index (Weeks)",
    "t (week index)": "Time Index (Weeks)",
    woy: "Week of Year",
    month: "Month",
    paydays: "Paydays in the Week",
    product_code: "Product",
    category_code: "Category",
    price: "Price",
    lag1: "1 Week Earlier",
    lag2: "2 Weeks Earlier",
    lag3: "3 Weeks Earlier",
    lag4: "4 Weeks Earlier",
    roll4: "4-Week Average",
    roll8: "8-Week Average",
};

function titleCase(text) {
    return text
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

export function roleLabel(value) {
    return ROLE_LABELS[value] ?? titleCase(String(value ?? ""));
}

export function movementLabel(value) {
    return MOVEMENT_LABELS[value] ?? titleCase(String(value ?? ""));
}

/**
 * "units_sold" -> "Units Sold", "lag1" -> "1 Week Earlier",
 * "weekly units_sold per product" -> "Weekly Units Sold per Product".
 */
export function fieldLabel(value) {
    const text = String(value ?? "").trim();
    if (text === "") return "";
    if (FIELD_LABELS[text]) return FIELD_LABELS[text];
    const phrase = text
        .split(/\s+/)
        .map((word) => FIELD_LABELS[word] ?? (word.includes("_") ? titleCase(word) : word))
        .join(" ");
    return phrase.charAt(0).toUpperCase() + phrase.slice(1);
}

export function fieldList(values) {
    return (values ?? []).map(fieldLabel).join(", ");
}

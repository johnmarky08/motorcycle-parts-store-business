from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

import pandas as pd


ROOT = Path(__file__).resolve().parent
MIN_RECORDS = 1000
SCHEMAS = {
    "product_demand": {
        "date": "date",
        "required": [
            "date", "product_id", "units_sold", "stock_quantity", "unit_price",
            "product_index", "promotion", "day_of_week", "recent_demand", "day_index",
        ],
        "numeric": [
            "units_sold", "stock_quantity", "unit_price", "product_index",
            "promotion", "day_of_week", "recent_demand", "day_index",
        ],
        "target": "units_sold",
        "features": [
            "day_index", "stock_quantity", "unit_price", "product_index",
            "promotion", "day_of_week", "recent_demand",
        ],
        "date_key": "date",
    },
    "sales_history": {
        "date": "transaction_date",
        "required": [
            "transaction_date", "product_id", "quantity", "unit_price",
            "discount_rate", "product_index", "promotion", "day_of_week",
            "customer_factor", "total_amount", "day_index",
        ],
        "numeric": [
            "quantity", "unit_price", "discount_rate", "product_index",
            "promotion", "day_of_week", "customer_factor", "total_amount", "day_index",
        ],
        "target": "total_amount",
        "features": [
            "day_index", "product_index", "promotion", "day_of_week",
            "discount_rate", "customer_factor",
        ],
        "date_key": "transaction_date",
    },
}


def validate(dataset_id: str, path: Path, minimum_records: int = MIN_RECORDS) -> dict:
    schema = SCHEMAS[dataset_id]
    errors: list[str] = []
    warnings: list[str] = []
    if not path.is_file():
        return {"dataset": dataset_id, "path": str(path), "valid": False, "errors": ["File does not exist."], "warnings": []}
    try:
        dataframe = pd.read_csv(path)
    except Exception as error:
        return {"dataset": dataset_id, "path": str(path), "valid": False, "errors": [f"CSV could not be read: {error}"], "warnings": []}

    missing_columns = [column for column in schema["required"] if column not in dataframe.columns]
    if missing_columns:
        errors.append("Missing required columns: " + ", ".join(missing_columns))
    if len(dataframe) < minimum_records:
        errors.append(f"Dataset contains {len(dataframe)} records; at least {minimum_records} are required.")

    missing_values = {column: int(count) for column, count in dataframe.isna().sum().items() if count}
    if missing_values:
        errors.append("Missing values found: " + ", ".join(f"{key}={value}" for key, value in missing_values.items()))
    duplicate_rows = int(dataframe.duplicated().sum())
    if duplicate_rows:
        errors.append(f"Duplicate rows found: {duplicate_rows}.")

    invalid_numeric: dict[str, int] = {}
    for column in schema["numeric"]:
        if column not in dataframe:
            continue
        converted = pd.to_numeric(dataframe[column], errors="coerce")
        count = int(converted.isna().sum())
        if count:
            invalid_numeric[column] = count
    if invalid_numeric:
        errors.append("Invalid numeric values found: " + ", ".join(f"{key}={value}" for key, value in invalid_numeric.items()))

    invalid_dates = 0
    if schema["date_key"] in dataframe:
        parsed_dates = pd.to_datetime(dataframe[schema["date_key"]], errors="coerce")
        invalid_dates = int(parsed_dates.isna().sum())
        if invalid_dates:
            errors.append(f"Invalid {schema['date_key']} values found: {invalid_dates}. Use YYYY-MM-DD.")
        elif not parsed_dates.is_monotonic_increasing:
            warnings.append(f"{schema['date_key']} is not sorted chronologically.")

    invalid_product_ids = 0
    if "product_id" in dataframe:
        invalid_product_ids = int((~dataframe["product_id"].astype(str).str.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_-]*")).sum())
        if invalid_product_ids:
            errors.append(f"Invalid product_id values found: {invalid_product_ids}.")

    if all(column in dataframe for column in schema["numeric"]):
        numeric = dataframe[schema["numeric"]].apply(pd.to_numeric, errors="coerce")
        non_negative = ["units_sold", "stock_quantity", "unit_price", "recent_demand", "quantity", "total_amount", "customer_factor"]
        for column in non_negative:
            if column in numeric and (numeric[column] < 0).any():
                errors.append(f"Negative values found in {column}.")
        for column, maximum in (("promotion", 1), ("day_of_week", 6), ("discount_rate", 1), ("product_index", 5)):
            if column in numeric and ((numeric[column] < 0) | (numeric[column] > maximum)).any():
                errors.append(f"Values outside the valid range found in {column}.")

    if "day_index" in dataframe and schema["date_key"] in dataframe:
        day_index = pd.to_numeric(dataframe["day_index"], errors="coerce")
        if not day_index.isna().any() and not day_index.is_monotonic_increasing:
            warnings.append("day_index is not sorted chronologically.")

    leakage = []
    target = schema["target"]
    if target in schema["features"]:
        leakage.append(f"Target column {target} is included in the feature list.")
    if dataset_id == "product_demand" and {"units_sold", "recent_demand"} <= set(dataframe.columns):
        if (pd.to_numeric(dataframe["units_sold"], errors="coerce") == pd.to_numeric(dataframe["recent_demand"], errors="coerce")).mean() > 0.99:
            leakage.append("recent_demand matches current units_sold in over 99% of rows.")
    if dataset_id == "sales_history" and {"quantity", "unit_price", "total_amount"} <= set(dataframe.columns):
        expected = pd.to_numeric(dataframe["quantity"], errors="coerce") * pd.to_numeric(dataframe["unit_price"], errors="coerce")
        actual = pd.to_numeric(dataframe["total_amount"], errors="coerce")
        if (expected == actual).mean() > 0.99:
            leakage.append("total_amount is directly equal to quantity multiplied by unit_price.")
    if leakage:
        errors.extend("Target leakage: " + item for item in leakage)

    return {
        "dataset": dataset_id,
        "path": str(path),
        "records": len(dataframe),
        "columns": list(dataframe.columns),
        "data_types": {column: str(dataframe[column].dtype) for column in dataframe.columns},
        "target": target,
        "features": schema["features"],
        "missing_values": missing_values,
        "duplicate_rows": duplicate_rows,
        "invalid_dates": invalid_dates,
        "invalid_product_ids": invalid_product_ids,
        "errors": errors,
        "warnings": warnings,
        "valid": not errors,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate ML CSV datasets without modifying them.")
    parser.add_argument("--product-demand", type=Path, default=ROOT / "datasets" / "product_demand.csv")
    parser.add_argument("--sales-history", type=Path, default=ROOT / "datasets" / "sales_history.csv")
    parser.add_argument("--minimum-records", type=int, default=MIN_RECORDS)
    parser.add_argument("--json", action="store_true", dest="as_json")
    args = parser.parse_args()
    reports = [
        validate("product_demand", args.product_demand, args.minimum_records),
        validate("sales_history", args.sales_history, args.minimum_records),
    ]
    if args.as_json:
        print(json.dumps(reports, indent=2))
    else:
        for report in reports:
            print(f"{report['dataset']}: {'VALID' if report['valid'] else 'INVALID'}")
            print(f"  records: {report.get('records', 0)}")
            print(f"  target: {report['target']}")
            print("  data types: " + ", ".join(f"{key}={value}" for key, value in report["data_types"].items()))
            print(f"  duplicate rows: {report['duplicate_rows']}")
            for error in report["errors"]:
                print(f"  ERROR: {error}")
            for warning in report["warnings"]:
                print(f"  WARNING: {warning}")
    return 0 if all(report["valid"] for report in reports) else 1


if __name__ == "__main__":
    sys.exit(main())

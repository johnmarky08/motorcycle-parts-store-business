# Real-Data Readiness

The committed CSVs under `ml/datasets/` are deterministic synthetic development data. They are not sales records from the store and must not be presented as actual business activity. The blank files under `ml/templates/` are collection templates and contain no business records.

## Product demand dataset

### Pipeline-ready columns

| Column | Type | Required constraints |
|---|---|---|
| `date` | `YYYY-MM-DD` date | Observation date; chronological and consistent with `day_index` |
| `product_id` | string | Stable non-empty catalog identifier |
| `product_name` | string | Catalog label; do not use as a model feature |
| `category` | string | Current product category |
| `units_sold` | non-negative integer | Target; completed-sale quantity for the date/product |
| `stock_quantity` | non-negative integer | Available stock at the observation point |
| `unit_price` | non-negative number | Price used for the observation |
| `product_index` | integer 0–5 | Stable encoded product category for the current six-product development dataset; regenerate for a real catalog |
| `promotion` | integer 0/1 | Promotion indicator |
| `day_of_week` | integer 0–6 | Monday=0 |
| `recent_demand` | non-negative number | Prior-period demand only; never current `units_sold` |
| `day_index` | non-negative integer | Days since the dataset start |

Collect one row per product per day, including products with zero completed demand when they were active and available. Do not drop zero-sales observations. Missing sales should be represented as zero only when the source confirms the product was active and there were no completed sales; otherwise investigate the missing record.

## Sales prediction dataset

### Pipeline-ready columns

| Column | Type | Required constraints |
|---|---|---|
| `transaction_date` | `YYYY-MM-DD` date | Date of a completed transaction |
| `product_id` | string | Stable catalog identifier |
| `product_name` | string | Catalog label |
| `category` | string | Category at transaction time |
| `quantity` | non-negative integer | Completed sale quantity |
| `unit_price` | non-negative number | Price at transaction time |
| `discount_rate` | number 0–1 | Applied discount fraction |
| `product_index` | non-negative integer | Encoded catalog product; regenerate for a real catalog |
| `promotion` | integer 0/1 | Promotion indicator |
| `day_of_week` | integer 0–6 | Monday=0 |
| `customer_factor` | positive number | Only use if it is an approved anonymized business feature |
| `total_amount` | non-negative number | Target; server-calculated completed-sale amount |
| `day_index` | non-negative integer | Days since dataset start |

Collect completed sale items at transaction time. Aggregate to a consistent forecasting grain before training if the target is daily revenue; do not mix transaction-level and daily rows. Voided, cancelled, pending, and test transactions must be excluded.

## Collection and export rules

Use the Laravel `products`, `sales`, `sale_items`, and relevant `stock_movements` records. Join sale items to products by `product_id`, and include only `sales.status = completed`. Stock movement data can supply inventory availability and adjustment context for demand rows, but it must not introduce future information into a prediction row.

Export only the fields required by the templates. Do not export customer names, email addresses, passwords, authentication tokens, payment details, or other personal data. Prefer a read-only, administrator-controlled export script or database view, with access logging and a restricted output directory. Do not assume the current database has enough history for reliable training.

## Missing, discontinued, and unsold products

- Reject missing target, identifier, date, and model-feature values during validation.
- Investigate missing observations before imputing. Do not impute the target.
- Keep discontinued products in historical rows with an explicit status during preparation, but exclude them from future active-inventory predictions.
- Retain active products with no completed sales as zero-demand observations when inventory availability confirms they were sellable.
- Do not use future stock corrections, future sales totals, or post-observation fields as features.

## Validation and examples

Run:

```powershell
python ml\validate_datasets.py
python ml\validate_datasets.py --product-demand path\to\product_demand.csv --sales-history path\to\sales_history.csv
```

The validator reports records, columns, target/features, missing values, duplicates, invalid dates, invalid identifiers, domain errors, chronological inconsistencies, and obvious target leakage. It exits nonzero for critical errors and never modifies input files.

Example product-demand row (example only, not a business record):

```text
2026-01-15,P001,Example Brake Pad,Brake System,4,32,1250,0,0,3,5,14
```

Example sales-history row (example only, not a business record):

```text
2026-01-15,P001,Example Brake Pad,Brake System,2,1250,0.05,0,0,3,1.0,2375,14
```

The instructor must still confirm the final date grain, product encoding strategy, required history window, target definition, and whether any anonymized customer-derived feature is permitted.

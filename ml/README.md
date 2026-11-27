# Machine learning package

Python 3.10+ with scikit-learn. Laravel (`app/Services/Ml/MlService.php`) starts it with `proc_open`
(`python ml/cli.py train|predict`, JSON on stdin and stdout). No console window is opened on Windows:
`pythonw.exe` is used automatically when it exists next to `python.exe`, with `python.exe` as the fallback.

```
pip install -r ml/requirements.txt
python -m unittest discover -s ml/tests -v      # 10 tests, about 30 seconds
python ml/generate_datasets.py                  # regenerates ml/datasets/* and database/seeders/data/products.csv
```

| File | Purpose |
| --- | --- |
| `cli.py` | Entry point: `train` (evaluate, refit, save model) and `predict` (load saved model, forecast) |
| `sales_model.py` | Weekly revenue forecasting (Simple Linear Regression, detrended Random Forest) |
| `demand_model.py` | Weekly units per product; demand, slow-moving and best-selling workflows |
| `common.py` | Metrics (MAE, RMSE, MAPE, WAPE, R²), safe paths, SHA-256 verified model loading |
| `generate_datasets.py` | Deterministic generator of the demo data |
| `datasets/` | `sales_history.csv` (126,552 rows) and `product_demand.csv` (9,828 rows) |
| `templates/` | Column layout for your own CSV files (upload them in Knowledge Analysis) |
| `tests/test_ml.py` | Training, prediction, integrity and path-safety tests |

## Using real store data

1. Export your real sales as `sales_history.csv` with the columns in `templates/sales_history_template.csv`
   (one row per sold item; `transaction_date` as `YYYY-MM-DD HH:MM:SS`). Use the same `product_id` values as the Products table.
2. Build the weekly `product_demand.csv` (Monday `week_start`, one row per product and week, zeros included).
3. Instructor → Knowledge Analysis → Upload Dataset → choose the type → Train A Model → "Use For Predictions".

Or skip the CSV work: **Refresh Datasets From Orders** exports every order in the database (the seeded history plus all real orders) to the two datasets
"Live Sales History" and "Live Product Demand" (complete Monday–Sunday weeks only). Train on them to include what customers actually bought.

Both files need at least 1,000 rows (and at least 26 complete weeks of history). The synthetic demo data is deliberately
low-noise (clear trend, seasonality and payday effects; knobs at the top of `generate_datasets.py`), so expect lower accuracy on
real, noisier store data. Hold-out R² on the demo set: sales 0.87 (linear) / 0.79 (forest), demand 0.84 / 0.82.

## Models

Models are written to `storage/app/ml/models/` (never committed or public). Each saved model is recorded in MySQL with its metrics,
parameters, training date and SHA-256 hash; a model whose hash does not match is refused (pickle files must never be loaded from an
untrusted source). Predictions use the model marked **Active**; older versions stay available and can be re-activated.

**Both models:** `python ml/cli.py predict` accepts one or two models. With two, each model forecasts on its own and the combined result is their average
(for slow-moving and best-selling, the per-product forecasts are averaged before ranking); the response also carries each model's own result.

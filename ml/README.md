# Machine learning package

Python 3.10+ with scikit-learn. Laravel (`app/Services/Ml/MlService.php`) starts it with `proc_open`
(`python ml/cli.py train|predict`, JSON on stdin and stdout). No console window is opened on Windows:
`pythonw.exe` is used automatically when it exists next to `python.exe`, with `python.exe` as the fallback.

```
pip install -r ml/requirements.txt
python -m unittest discover -s ml/tests -v      # 8 tests, about 30 seconds
python ml/generate_datasets.py                  # regenerates ml/datasets/* and database/seeders/data/products.csv
```

| File | Purpose |
| --- | --- |
| `cli.py` | Entry point: `train` (evaluate, refit, save model) and `predict` (load saved model, forecast) |
| `sales_model.py` | Weekly revenue forecasting (Simple Linear Regression, detrended Random Forest) |
| `demand_model.py` | Weekly units per product; demand, slow-moving and best-selling workflows |
| `common.py` | Metrics (MAE, RMSE, MAPE, WAPE, R²), safe paths, SHA-256 verified model loading |
| `generate_datasets.py` | Deterministic generator of the demo data |
| `datasets/` | `sales_history.csv` (22,419 rows) and `product_demand.csv` (5,985 rows) |
| `templates/` | Column layout for your own CSV files (upload them in Knowledge Analysis) |
| `tests/test_ml.py` | Training, prediction, integrity and path-safety tests |

## Using real store data

1. Export your real sales as `sales_history.csv` with the columns in `templates/sales_history_template.csv`
   (one row per sold item; `transaction_date` as `YYYY-MM-DD HH:MM:SS`). Use the same `product_id` values as the Products table.
2. Build the weekly `product_demand.csv` (Monday `week_start`, one row per product and week, zeros included).
3. Instructor → Knowledge Analysis → Upload dataset → choose the type → Train a model → "Use for predictions".

Both files need at least 1,000 rows (and at least 26 complete weeks of history). Real data usually has stronger patterns than the
synthetic demo data, so the Random Forest should perform better than it does on the demo set.

## Models

Models are written to `storage/app/ml/models/` (never committed or public). Each saved model is recorded in MySQL with its metrics,
parameters, training date and SHA-256 hash; a model whose hash does not match is refused (pickle files must never be loaded from an
untrusted source). Predictions use the model marked **Active**; older versions stay available and can be re-activated.

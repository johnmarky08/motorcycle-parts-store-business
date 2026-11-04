# Requirements compliance matrix

Maps each requirement of the course assignment to where it is implemented and how it was verified.

| # | Requirement | Implementation | Verified by |
| --- | --- | --- | --- |
| 1 | Business transactional functionality | Inventory (`InventoryController`), sales (`SalesController`), users (`UserManagementController`) | `InventoryTest`, `SalesTest`, live smoke test |
| 2 | MySQL stores transaction data | MySQL/MariaDB default in `.env.example`; migrations in `database/migrations` | `migrate:fresh --seed` on MariaDB 10.11 |
| 3 | Simple Linear Regression | `ml/sales_model.py`, `ml/demand_model.py` (`fit_slr`) | `ml/tests/test_ml.py` |
| 4 | Random Forest Regression | same files (`RandomForestRegressor`, optional tuning) | `ml/tests/test_ml.py` |
| 5 | Save and load models "with improvements" | Models saved as `.joblib`, SHA-256 recorded in `ml_models`, verified before load, versioned, one active model per type; hold-out evaluation then refit on all data; detrended forest for sales; optional tuning | `test_models_are_saved_and_loaded`, `test_tampered_model_is_rejected` |
| 6 | At least 1,000 records per dataset (CSV) | `ml/datasets/sales_history.csv` (22,419), `product_demand.csv` (5,985); uploads must have ≥ 1,000 records | `DatasetInspector`, upload tests |
| 7 | Evaluation metrics and descriptions | MAE, RMSE, MAPE, WAPE, R² stored per model and described in Knowledge Analysis | `PredictionAndAnalysisTest` |
| 8 | Security | OWASP table in `README.md` section 5; headers, rate limits, validation, audit log | `SecurityTest`, `RoleAccessTest` |
| 9 | Functionality and reliable predictions | Four prediction workflows with ranges (≈80% interval) and restock suggestion | live smoke test, `test_ml.py` |
| 10 | Screens responsive | Tailwind responsive layouts, mobile drawer navigation | production build, manual layout review |
| 11 | Inventory management | Products, categories, stock movements, low/out-of-stock | `InventoryTest` |
| 12 | Sales transaction management | Multi-item sales, history, atomic stock deduction, idempotency | `SalesTest` |
| 13 | User management | Registration approval, edit, suspend, delete with last-admin protection | `AuthenticationTest`, `SecurityTest` |
| 14 | Login with user level / role | Server-enforced role middleware | `RoleAccessTest` |
| 15 | Product demand prediction | `/prediction` → demand workflow (per product, 1–365 days) | live smoke test |
| 16 | Sales prediction | sales workflow (1–365 days, history window) | live smoke test |
| 17 | Slow-moving product prediction | slow workflow (velocity percentile and days of cover) | live smoke test |
| 18 | Best-selling product prediction | best workflow (30/60/90 days) | live smoke test |
| 19 | Dashboard / menu with cards and chart by role | `DashboardController` + `Dashboard.svelte`; menu from `Role::navigation()` | `DashboardTest`, SSR render per role |
| 20 | Inventory clerk: inventory and relevant features | role `inventory_clerk` | `RoleAccessTest` |
| 21 | Sales personnel: sales and relevant features | role `sales_personnel` | `RoleAccessTest` |
| 22 | Admin: dashboard + prediction (input and output only) | role `admin`; metrics hidden in prediction output | `test_prediction_page_hides_metrics_from_admin_but_not_instructor` |
| 23 | Instructor: full dashboard + analysis with datasets, metrics, descriptions | role `instructor` (Knowledge Analysis page) | `RoleAccessTest`, live smoke test |

## Known gaps and honest notes

- The data is synthetic. Percentage errors for weekly product demand are high (about 57–61% MAPE, 41% WAPE) because weekly counts are small;
  sales forecasts are about 6–7% per week.
- Simple Linear Regression and Random Forest perform alike on the synthetic data.
- Password reset needs real SMTP credentials; messages from a free address may land in spam.
- The Windows no-console behaviour could not be tested in the Linux build environment.

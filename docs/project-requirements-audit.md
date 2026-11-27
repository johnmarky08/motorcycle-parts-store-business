# Requirements compliance matrix

Maps each requirement of the course assignment to where it is implemented and how it was verified.

| # | Requirement | Implementation | Verified by |
| --- | --- | --- | --- |
| 1 | Business transactional functionality | Inventory (`InventoryController`), sales (`SalesController`), users (`UserManagementController`) | `InventoryTest`, `SalesTest`, live smoke test |
| 2 | MySQL stores transaction data | MySQL/MariaDB default in `.env.example`; migrations in `database/migrations` | `migrate:fresh --seed` on MariaDB 10.11 |
| 3 | Simple Linear Regression | `ml/sales_model.py`, `ml/demand_model.py` (`fit_slr`) | `ml/tests/test_ml.py` |
| 4 | Random Forest Regression | same files (`RandomForestRegressor`, optional tuning) | `ml/tests/test_ml.py` |
| 5 | Save and load models "with improvements" | Models saved as `.joblib`, SHA-256 recorded in `ml_models`, verified before load, versioned, one active model per type; hold-out evaluation then refit on all data; detrended forest for sales; optional tuning | `test_models_are_saved_and_loaded`, `test_tampered_model_is_rejected` |
| 6 | At least 1,000 records per dataset (CSV) | `ml/datasets/sales_history.csv` (126,552), `product_demand.csv` (9,828); uploads must have ≥ 1,000 records | `DatasetInspector`, upload tests |
| 7 | Evaluation metrics and descriptions | MAE, RMSE, MAPE, WAPE, R² stored per model and described in Knowledge Analysis | `PredictionAndAnalysisTest` |
| 8 | Security | OWASP table in `README.md` section 5; headers, rate limits, validation, audit log | `SecurityTest`, `RoleAccessTest` |
| 9 | Functionality and reliable predictions | Four prediction workflows with ranges (≈80% interval) and restock suggestion | live smoke test, `test_ml.py` |
| 10 | All screens in full-screen mode | Every screen fills the whole window (no fixed-width column); full-screen button in the header and on the sign-in pages uses the browser Fullscreen API and stays on across Inertia page changes; web manifest `display: fullscreen` | production build, manual review |
| 11 | Inventory management | Products, categories, stock movements, low/out-of-stock | `InventoryTest` |
| 12 | Sales transaction management | Customers place orders in the shop (cart, checkout, atomic stock deduction, idempotency); every order is a sale with the customer's name; read-only history with date range for sales staff | `ShopTest`, live smoke test |
| 13 | User management | Instructor assigns any role; admin manages every account except instructor accounts and cannot assign the instructor role; password required on create, changing it on edit needs the current password; suspend, delete; self-change and last-manager protection; customers register themselves | `AuthenticationTest`, `SecurityTest` |
| 14 | Login with user level / role | Server-enforced role middleware; five roles (user, inventory clerk, sales personnel, admin, instructor) | `RoleAccessTest` |
| 15 | Product demand prediction | `/prediction` → demand workflow (per product, 1–365 days) | live smoke test |
| 16 | Sales prediction | sales workflow (1–365 days, history window) | live smoke test |
| 17 | Slow-moving product prediction | slow workflow (velocity percentile and days of cover) | live smoke test |
| 18 | Best-selling product prediction | best workflow (30/60/90 days) | live smoke test |
| 19 | Dashboard / menu with cards and chart by role | `DashboardController` + `Dashboard.svelte`; menu from `Role::navigation()` | `DashboardTest`, SSR render per role |
| 20 | Inventory clerk: inventory and relevant features | role `inventory_clerk` | `RoleAccessTest` |
| 21 | Sales personnel: sales and relevant features | role `sales_personnel` (sales history) | `RoleAccessTest`, `ShopTest` |
| 22 | Admin: dashboard + prediction (input and output only) | role `admin`; metrics removed from the prediction response on the server; admin cannot create or take over an instructor account to get around this | `test_prediction_page_hides_metrics_from_admin_but_not_instructor`, `test_admin_cannot_assign_the_instructor_role_or_change_instructor_accounts` |
| 23 | Instructor: full dashboard + analysis with datasets, metrics, descriptions | role `instructor` (Knowledge Analysis page, plus user management) | `RoleAccessTest`, live smoke test |
| 24 | Both models used together | Prediction "Both Models (Combined)" averages Simple Linear Regression and Random Forest; training can save both at once | `ml/tests/test_ml.py`, `PredictionAndAnalysisTest`, live smoke test |
| 25 | Datasets connected to real orders | "Refresh Datasets From Orders" exports orders to two CSV datasets for retraining | `PredictionAndAnalysisTest`, live smoke test |
| 26 | Customer shop (catalog, pictures, cart, checkout, orders) | `ShopController`, `OrderController`, `Shop.svelte`, `MyOrders.svelte`, `public/images` | `ShopTest`, live smoke test |

## Known gaps and honest notes

- The data is synthetic. Percentage errors for weekly product demand are high (about 57–61% MAPE, 41% WAPE) because weekly counts are small;
  sales forecasts are about 6–7% per week.
- Simple Linear Regression and Random Forest perform alike on the synthetic data.
- Password reset needs real SMTP credentials; messages from a free address may land in spam.
- Product pictures are generated SVG illustrations, not photographs.
- There is no payment gateway or order status: an order is a completed sale as soon as it is placed.
- Sales models: about 6.5–7% MAPE but R² close to 0 on the short (about 19-week) hold-out, because the remaining weekly variation is mostly random noise; see README section 7.

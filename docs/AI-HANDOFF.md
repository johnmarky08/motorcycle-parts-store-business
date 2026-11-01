# AI Technical Handoff

Audit date: 2026-11-02  
Repository: `johnmarky08/motorcycle-parts-store-business`  
Branch: `feat/frontend-completeness`

## 1. Project Overview

This is a Laravel/Inertia/Svelte motorcycle-parts store management system. It provides authenticated, role-restricted workspaces for inventory, sales, administration, business metrics, knowledge analysis, and machine-learning experiments.

### Stack

- PHP 8.2+, Laravel 12, Eloquent, PHPUnit
- Inertia Laravel 3.5+ and Inertia Svelte 3.9.1
- Svelte 5.57.3, Vite 6, Tailwind CSS 4
- Bits UI, shadcn-svelte, Lucide Svelte
- LayerChart, `d3-scale`, and `d3-shape`
- SQLite by default for local/test configuration; MySQL/MariaDB is supported by Laravel configuration
- Python 3.12 environment with pandas, NumPy, scikit-learn, and joblib

The Laravel routes return Inertia page responses for UI workspaces and JSON responses for analysis APIs. Svelte pages under `resources/js/Pages` receive Inertia props and use `fetch` or Inertia router requests for subsequent operations.

## 2. Project Structure

- `app/Http/Controllers/`: authentication, dashboard, inventory, sales, user-management, and analysis workflows.
- `app/Http/Middleware/`: Inertia shared props and `EnsureUserRole`.
- `app/Models/`: `User`, `Product`, `Category`, `Sale`, `SaleItem`, `StockMovement`, `RegistrationAudit`, and `MlModel`.
- `database/migrations/`: users/auth extensions, catalog, sales, registration audits, idempotency, and ML metadata schema.
- `database/seeders/DatabaseSeeder.php`: development categories, products, and role-based users.
- `routes/web.php`: all web, Inertia, and analysis endpoints.
- `resources/js/Pages/`: `Login`, `Register`, password recovery pages, `Dashboard`, `InventoryManagement`, `SalesManagement`, `UserManagement`, `Prediction`, and `KnowledgeAnalysis`.
- `resources/js/Components/Layout/`: `AppHeader` and `AppSidebar`.
- `resources/js/Components/Shared/`: `StatCard` and `TracktorSalesChart`.
- `resources/js/lib/components/ui/`: shared shadcn/Bits-compatible primitives, including Button, Card, Dialog, DropdownMenu, Select, Table, and Tooltip.
- `ml/pipeline.py`: dataset loading, chronological splitting, training, metrics, artifact persistence, and prediction.
- `ml/generate_datasets.py`: deterministic synthetic dataset generation.
- `ml/validate_datasets.py`: non-destructive dataset validation.
- `ml/datasets/`: synthetic product-demand and sales-history CSVs, currently 1,200 records each.
- `ml/templates/`: future real-data CSV templates.
- `ml/tests/`: Python pipeline and validator tests.
- `tests/Feature/`: Laravel feature tests for auth, dashboard, inventory, sales, and analysis.

Do not inspect or modify `node_modules`, `vendor`, build output, or Python cache directories as part of normal development.

## 3. Implemented Features

### Authentication and account recovery

`AuthController` handles login, logout, registration requests, password reset links, and password resets. Pages are `Login.svelte`, `Register.svelte`, `ForgotPassword.svelte`, and `ResetPassword.svelte`.

Routes include `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, and authenticated `POST /logout`.

Registration creates a pending account. Active accounts can authenticate; suspended or non-active accounts are rejected. Laravel password broker support is used for recovery.

### Role-based access

`EnsureUserRole` enforces roles in `routes/web.php`. `HandleInertiaRequests` shares the authenticated user as `auth.user` with id, name, email, role, and status.

`AppSidebar.svelte`, `AppHeader.svelte`, and `previewRoles.js` define the visible navigation. Backend middleware is authoritative.

### Dashboard

`DashboardController` calculates completed-sale totals, transaction count, product count, low/out-of-stock counts, inventory health, sales trends, best sellers, and slow-moving products. `Dashboard.svelte` consumes the `dashboard` Inertia prop and supports 30-day and 365-day ranges.

Route: `GET /dashboard`.

### Inventory management

`InventoryController` supports product/category CRUD, server-side search/filter/sort/pagination, stock adjustments, and stock movement history. Product and stock changes use database transactions. `Product`, `Category`, and `StockMovement` model the catalog and inventory history.

`InventoryManagement.svelte` is the primary UI.

Routes: `GET /inventory`, `POST /inventory`, `POST /inventory/categories`, `PUT /inventory/{product}`, `DELETE /inventory/{product}`, `POST /inventory/{product}/stock`, and `GET /inventory/{product}/movements`.

### Sales management

`SalesController` lists and filters sales, shows transaction details, and creates completed sales. The server calculates prices and totals from current products, locks products during stock checks, decrements stock, records stock movements, and rejects duplicate UUID idempotency keys.

`Sale` and `SaleItem` preserve transaction and line-item snapshots. `SalesManagement.svelte` provides the sales UI.

Routes: `GET /sales`, `GET /sales/{sale}`, and `POST /sales`.

### User management

`UserManagementController` provides admin-only listing, create/update/delete, status changes, registration approval, and rejection. `UserManagement.svelte` provides the admin interface.

Routes are grouped under `role:admin` at `/user-management`.

### Knowledge Analysis

`KnowledgeAnalysis.svelte` loads dataset validation, persisted model metadata, and business insights. `AnalysisController::insights()` returns best-selling and slow-moving products using completed sales.

Route: `GET /knowledge-analysis`; supporting APIs are `/analysis/datasets`, `/analysis/models`, and `/analysis/insights`.

### Prediction and machine learning

`Prediction.svelte` loads datasets and persisted models, can request training, selects a product for product-demand features, and requests predictions. `AnalysisController` validates inputs, invokes the Python pipeline, persists model metadata, and hides artifact paths from JSON serialization.

Analysis routes are protected for Admin and Instructor:

- `GET /analysis/datasets`
- `GET /analysis/models`
- `GET /analysis/business-features`
- `GET /analysis/insights`
- `POST /analysis/train`
- `POST /analysis/predict`

## 4. Roles and Permissions

The enforced role matrix in `routes/web.php` is:

| Role | Accessible workspaces and operations |
|---|---|
| Admin | Dashboard, user management, prediction, inventory, sales, and knowledge analysis |
| Instructor | Dashboard and knowledge analysis |
| Inventory Clerk | Dashboard and inventory management |
| Sales Personnel | Dashboard and sales management |

The frontend navigation mirrors this matrix, but authorization must always be evaluated by Laravel middleware rather than frontend state.

## 5. Machine Learning

### Algorithms and datasets

`ml/pipeline.py` supports:

- `simple_linear_regression`
- `random_forest_regression`

Datasets are:

- `ml/datasets/product_demand.csv`, target `units_sold`
- `ml/datasets/sales_history.csv`, target `total_amount`

Both are synthetic development datasets, not collected business transactions. Training requires at least 1,000 records. Product-demand features include day index, stock, price, product index, promotion, weekday, and recent demand. Sales-history features include day index, product index, promotion, weekday, discount rate, and customer factor.

### Training and evaluation

The pipeline loads and validates numeric schemas, performs an 80/20 chronological split, trains a scikit-learn pipeline with median imputation, and reports MAE, MSE, RMSE, R², training metrics, feature ranges, and metadata. Random forest uses a fixed random seed.

Models are saved as private joblib files under Laravel storage paths. Laravel stores metadata in `ml_models`; `MlModel` casts JSON fields for features, ranges, and metrics. The artifact path is hidden from API serialization. Existing model selection in `AnalysisController::train()` avoids replacing a saved model when the new model has a worse R².

### Current prediction behavior and limitations

- Python training always reads the synthetic CSVs.
- Product-demand requests can map current product price, stock, and completed sales in the last 30 days through `GET /analysis/business-features`.
- Product indexes are assigned by products sorted by database id and only indexes `0` through `5` are accepted.
- Promotion is currently `0` because the business schema has no promotion field.
- Sales-history business predictions are reported unavailable because the current schema does not supply discount rate, promotion, or customer-factor values.
- Product identity encoding has not been proven historically consistent with the synthetic training data.
- A returned product-demand value is not a validated real-product demand forecast.
- Actual-business training, stable product identity mapping, and complete sales-feature collection remain future work.

The previous Windows web-process failure was addressed in `AnalysisController::runPython()` by passing the Windows system environment values required by the Python subprocess and by logging sanitized execution diagnostics. Keep this fix intact.

## 6. Current Project State

### Git state verified during this audit

- Branch: `feat/frontend-completeness`
- HEAD: `b45a974 Merge remote-tracking branch 'origin/main' into feat/frontend-completeness`
- Existing implementation commits include the authentication, inventory, sales, dashboard, ML, UI, chart, cleanup, and README milestones.
- The working tree is not clean. The following files have uncommitted modifications and must be treated as active user work:
  - `app/Http/Controllers/AnalysisController.php`
  - `app/Http/Controllers/SalesController.php`
  - `app/Http/Controllers/UserManagementController.php`
  - `resources/js/Pages/KnowledgeAnalysis.svelte`
  - `resources/js/Pages/SalesManagement.svelte`
  - `resources/js/Pages/UserManagement.svelte`
  - `tests/Feature/SalesTest.php`

No staging, commits, migrations, or destructive commands were performed for this handoff. Do not reset or overwrite these files without reviewing their diffs first.

### Verified complete areas

- Route-level authentication and role middleware.
- Database-backed dashboard calculations.
- Inventory CRUD, stock movements, and stock adjustment transaction flow.
- Server-calculated sales and idempotency protection.
- Synthetic ML generation, validation, training, persistence, and prediction APIs.
- Shared Svelte layout and UI primitives.

### Partial or risky areas

- ML models are synthetic-data models and are not validated business forecasts.
- Product identity mapping is provisional.
- Sales-history prediction cannot use current business records with the existing schema.
- `previewRoles.js` remains in the frontend and should not become an authorization source.
- The current uncommitted changes may represent ongoing fixes to analysis, sales, user management, and their UI/tests; their intent must be established before further edits.
- Browser verification was not performed during this audit.

## 7. Testing and Setup

Install PHP and Node dependencies with `composer install` and `npm install`. Copy `.env.example` to `.env`, generate the key with `php artisan key:generate`, create `database/database.sqlite` before migrating when using SQLite, and run `php artisan migrate`. Install Python dependencies in a virtual environment using `python -m pip install -r ml/requirements.txt`.

For a non-default Python interpreter, configure `ML_PYTHON` locally, for example `ML_PYTHON=C:/Python312/python.exe`; do not commit `.env`.

Run development services with `php artisan serve` and `npm run dev`, or use the documented `composer run dev` script.

Available checks:

```text
php artisan test
python -m unittest discover -s ml/tests
npm run build
git diff --check
```

No tests or build were run during this audit, so this report does not claim fresh pass/fail results. The latest verified results recorded before this audit were 30 Laravel tests, 9 Python tests, and a passing frontend build; rerun them after resolving or reviewing the current uncommitted changes.

## 8. Recommended Next Steps

1. Review and classify the seven uncommitted files before making any further changes; preserve or commit them according to their actual intent.
2. Run focused Laravel, Python, frontend-build, and diff checks after the current changes are understood.
3. Inspect the current `AnalysisController` and prediction changes for compatibility with the documented synthetic-data limitations.
4. Verify user-management password update behavior and sales UI refresh behavior with focused tests and browser checks.
5. Treat real-data model training and stable product identity mapping as a separate future task; do not silently substitute database values into the existing synthetic model.
6. Avoid redesigning the approved light UI and shared button/sidebar patterns while implementing functional fixes.


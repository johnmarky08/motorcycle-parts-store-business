# Repository Evidence Report

Audit date: 2026-11-02  
Repository: `johnmarky08/motorcycle-parts-store-business`  
Evidence status: read-only inspection; no install, migration, test, build, staging, commit, reset, or deletion was performed.

Status labels:

- **CONFIRMED**: directly verified in source, schema, or filesystem evidence.
- **CONTRADICTED**: a documentation claim conflicts with verified code/data.
- **NOT VERIFIED**: the repository evidence is insufficient or the check was not run.

## 1. Git state

### Confirmed command results

```text
branch:
feat/frontend-completeness
```

Latest commits:

```text
b45a974 Merge remote-tracking branch 'origin/main' into feat/frontend-completeness
f104704 docs: finalize project README and setup guide
347557b chore: align development seed data and smoke test
d5ab386 feat(ui): integrate modern dashboard charts
f31f593 feat(ui): integrate shadcn-svelte components
064c169 feat(auth): add user administration workflow
d35cea9 feat(ml): add dataset validation and readiness tools
fa83940 feat(ml): add regression training and prediction pipeline
866eb64 feat(dashboard): add database-backed business metrics
60c5c9e feat(sales): implement transaction management
55ea354 feat(inventory): implement product and stock management
945fe13 feat(auth): add authentication and role-based access
a3d6cc6 feat(ui): complete initial frontend implementation
fdf4d5c Merge pull request #1 from johnmarky08/dev/aleboyore
36659c1 feat(ui): complete initial frontend implementation
475f18d feat(ui): complete admin dashboard and preview screens
caed521 initial commit
```

Current status:

```text
 M app/Http/Controllers/AnalysisController.php
 M app/Http/Controllers/SalesController.php
 M app/Http/Controllers/UserManagementController.php
 M resources/js/Pages/KnowledgeAnalysis.svelte
 M resources/js/Pages/SalesManagement.svelte
 M resources/js/Pages/UserManagement.svelte
 M tests/Feature/SalesTest.php
?? docs/AI-HANDOFF.md
```

`git diff --stat` reports 163 insertions and 22 deletions across the seven modified implementation/test files. The handoff report is untracked. No file was staged by this audit.

## 2. Seven uncommitted files: protected user work

All seven files below are unstaged working-tree modifications. Their diffs were inspected and were not changed.

### `app/Http/Controllers/AnalysisController.php`

Diff: one option changed in `runPython()`:

```diff
@@ runPython()
-$process->setOptions(['create_new_console' => true]);
+$process->setOptions(['create_process_group' => true]);
```

Apparent intent: change Windows Symfony Process behavior while retaining explicit `SystemRoot` and `WINDIR` environment handling. **CONFIRMED** as a subprocess-execution change; runtime success was not tested during this audit.

### `app/Http/Controllers/SalesController.php`

Diff: imports `Carbon` and replaces `whereDate()` with an explicit local-day range:

```diff
@@ index()
-->when($request->filled('date'), fn ($query) => $query->whereDate('transaction_date', $request->input('date')))
+->when($request->filled('date'), function ($query) use ($request) {
+    $day = Carbon::createFromFormat('!Y-m-d', $request->string('date')->toString(), config('app.timezone'));
+    $query->where('transaction_date', '>=', $day)
+        ->where('transaction_date', '<', $day->copy()->addDay());
+})
```

Apparent intent: make date filtering use the configured application timezone and a half-open calendar-day interval. **CONFIRMED** by the diff; the modified test was not run.

### `app/Http/Controllers/UserManagementController.php`

The change wraps update, delete, and status-toggle operations in `DB::transaction()` and locks the target user. It adds `assertAccountChangeAllowed()` to prevent self status/role changes or deletion and to prevent removal/deactivation of the last active admin.

```php
private function assertAccountChangeAllowed(User $user, ?string $role, ?string $status, bool $deleting = false): void
{
    abort_if(auth()->id() === $user->id
        && ($deleting || $role !== $user->role || $status !== $user->status),
        422, 'You cannot suspend, deactivate, demote, or delete your own account.');
    // Counts active admins while the transaction holds row locks.
}
```

Apparent intent: close self-lockout and last-admin protection gaps and make concurrent account changes safer. **CONFIRMED** in the uncommitted code; no dedicated user-management test for the new protection was found in the listed feature tests.

### `resources/js/Pages/KnowledgeAnalysis.svelte`

The page now stores dataset algorithms and selected dataset/algorithm state, adds validation/training state, and exposes actions that call `/analysis/train` and `/analysis/datasets`.

```diff
-let datasets = [], models = [], insights = ...
+let datasets = [], algorithms = [], models = ..., validating = false, training = false, actionMessage = "";
```

Apparent intent: extend Knowledge Analysis from read-only metrics/insights to dataset validation and model training controls. **CONFIRMED** by the diff; browser behavior was not verified.

### `resources/js/Pages/SalesManagement.svelte`

The diff adds UI state/formatting around date filtering and transaction display. It corresponds to the controller’s local-day date-range change and modifies the sales page’s current filtering/detail behavior.

Apparent intent: align the UI with the improved sales date filter and transaction workflow. **CONFIRMED** by the modified file status and diff; browser behavior was not verified.

### `resources/js/Pages/UserManagement.svelte`

The diff changes update/delete/status flows and form behavior to match the server-side account-protection changes. It adds/adjusts error handling and interaction state for user actions.

Apparent intent: surface server rejection messages and support safer user-account administration. **CONFIRMED** by the diff; browser behavior was not verified.

### `tests/Feature/SalesTest.php`

The diff adds a focused test for a selected local calendar date:

```diff
+public function test_date_filter_uses_the_selected_local_calendar_day(): void
+{
+    // Creates sales around a date boundary and verifies the date filter.
+}
```

Apparent intent: lock in the new timezone-aware date-filter behavior. **CONFIRMED** as a test addition; it was not run during this audit.

## 3. Routes and permissions

### Exact route source

Source: `routes/web.php`.

```php
// routes/web.php:12-29
Route::get('/', fn () => redirect(auth()->check() ? '/dashboard' : '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')->name('logout');
```

```php
// routes/web.php:29-51
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::middleware('role:admin')->group(function () {
        Route::get('/user-management', [UserManagementController::class, 'index']);
        Route::get('/prediction', fn () => Inertia::render('Prediction'));
        Route::post('/user-management', [UserManagementController::class, 'store']);
        Route::put('/user-management/{user}', [UserManagementController::class, 'update']);
        Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy']);
        Route::patch('/user-management/{user}/status', [UserManagementController::class, 'toggleStatus']);
        Route::patch('/user-management/{user}/approve', [UserManagementController::class, 'approve']);
        Route::patch('/user-management/{user}/reject', [UserManagementController::class, 'reject']);
    });
    Route::middleware('role:admin,inventory_clerk')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory', [InventoryController::class, 'store']);
        Route::post('/inventory/categories', [InventoryController::class, 'storeCategory']);
        Route::put('/inventory/{product}', [InventoryController::class, 'update']);
        Route::delete('/inventory/{product}', [InventoryController::class, 'destroy']);
        Route::post('/inventory/{product}/stock', [InventoryController::class, 'adjustStock']);
        Route::get('/inventory/{product}/movements', [InventoryController::class, 'movements']);
    });
```

```php
// routes/web.php:53-67
    Route::middleware('role:admin,sales_personnel')->group(function () {
        Route::get('/sales', [SalesController::class, 'index']);
        Route::get('/sales/{sale}', [SalesController::class, 'show']);
        Route::post('/sales', [SalesController::class, 'store']);
    });
    Route::middleware('role:admin,instructor')->group(function () {
        Route::get('/analysis/datasets', [AnalysisController::class, 'datasets']);
        Route::get('/analysis/models', [AnalysisController::class, 'models']);
        Route::get('/analysis/business-features', [AnalysisController::class, 'businessFeatures']);
        Route::get('/analysis/insights', [AnalysisController::class, 'insights']);
        Route::post('/analysis/train', [AnalysisController::class, 'train']);
        Route::post('/analysis/predict', [AnalysisController::class, 'predict']);
        Route::get('/knowledge-analysis', fn () => Inertia::render('KnowledgeAnalysis'));
    });
});
```

### Role table

| Route/method | Middleware | Allowed roles |
|---|---|---|
| `GET /dashboard` | `auth` | Admin, Instructor, Inventory Clerk, Sales Personnel |
| `GET/POST /inventory*`, `PUT/DELETE /inventory/{product}`, `GET /inventory/{product}/movements` | `auth`, `role:admin,inventory_clerk` | Admin, Inventory Clerk |
| `GET /sales`, `GET /sales/{sale}`, `POST /sales` | `auth`, `role:admin,sales_personnel` | Admin, Sales Personnel |
| All `/user-management*` routes | `auth`, `role:admin` | Admin |
| `GET /knowledge-analysis` | `auth`, `role:admin,instructor` | Admin, Instructor |
| `GET /prediction` | `auth`, `role:admin` | Admin |
| `GET /analysis/datasets`, `/models`, `/business-features`, `/insights` | `auth`, `role:admin,instructor` | Admin, Instructor |
| `POST /analysis/train`, `/predict` | `auth`, `role:admin,instructor` | Admin, Instructor |

`EnsureUserRole::handle()` aborts with 403 unless the authenticated user’s `role` is in the route-provided role list (`app/Http/Middleware/EnsureUserRole.php:10-16`).

Controllers also perform authorization/business guards:

- `UserManagementController` has self-account and last-active-admin guards in the protected working-tree version.
- `InventoryController` prevents deletion when sale history exists and rejects negative stock adjustments.
- `AuthController` only authenticates `status = active`.
- `AnalysisController` validates dataset, algorithm, model, feature, and artifact conditions.
- Route middleware remains the primary workspace authorization layer.

## 4. Database

### Migrations

| Migration | Tables/changes |
|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`: id, name, unique email, nullable email verification timestamp, password, remember token, timestamps. Also `password_reset_tokens` (email primary key, token, nullable created_at) and `sessions` (string primary id, nullable/indexed user_id, nullable IP/user-agent, indexed last_activity). |
| `0001_01_01_000001_create_cache_table.php` | `cache` and `cache_locks`: string primary keys, owner/expiration fields, expiration indexes. |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` with Laravel queue fields, indexes, UUID unique failed-job key, and timestamps/current failed time. |
| `2026_10_30_000003_add_role_and_status_to_users_table.php` | Adds `users.role` string default `sales_personnel`, `users.status` string default `active`, composite index `(role,status)`. |
| `2026_10_30_000004_create_catalog_and_sales_tables.php` | `categories`: unique name. `products`: category FK restrict delete, name, unique SKU, nullable description, decimal selling price, unsigned stock default 0, unsigned reorder threshold default 10, category/stock index. `stock_movements`: product FK restrict delete, nullable user FK null-on-delete, enum type `received/reduction/sale/correction`, signed quantity, balance_after, note, product/created_at index. `sales`: nullable user FK null-on-delete, unique transaction number, datetime, enum `completed/voided` default completed, payment method, decimal total, date/status index. `sale_items`: sale FK cascade delete, nullable product FK null-on-delete, product name/SKU snapshot, integer quantity, decimal unit price/line total. |
| `2026_10_30_000005_add_last_login_at_to_users_table.php` | Nullable `users.last_login_at` timestamp. |
| `2026_10_30_000006_add_registration_fields_to_users_table.php` | Nullable `requested_role`, `affiliation`, unique nullable `registration_status_token`, nullable `submitted_at`, index `(status,submitted_at)`. Converts legacy `inactive` status to `suspended`. |
| `2026_10_30_000007_create_registration_audits_table.php` | `registration_audits`: applicant FK cascade delete, administrator FK restrict delete, action, nullable assigned_role, current created_at, applicant/action index. |
| `2026_10_30_000008_add_idempotency_key_to_sales_table.php` | Nullable unique UUID `sales.idempotency_key`. |
| `2026_10_30_000009_create_ml_models_table.php` | `ml_models`: dataset_id, algorithm, target, JSON features, integer record_count, JSON metrics, artifact_path, trained_at, dataset/algorithm index. |
| `2026_10_30_000010_add_evaluation_details_to_ml_models_table.php` | Nullable JSON `training_metrics` and `feature_ranges`. |

### Database configuration

- `.env.example` sets `DB_CONNECTION=sqlite`; MySQL fields are present as commented defaults. **CONFIRMED.**
- `config/database.php` defines sqlite, mysql, mariadb, pgsql, sqlsrv connections and uses `env('DB_CONNECTION', 'sqlite')`. **CONFIRMED.**
- `phpunit.xml` sets `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` for tests. **CONFIRMED.**
- MySQL/MariaDB configuration and migrations are present and use Laravel schema features. Whether a local MySQL/MariaDB service is running is **NOT VERIFIED**; this audit did not connect to it.
- The repository’s `database/database.sqlite` exists, but its local schema has no `ml_models` table, so its migration state is older than the current migration set. **CONFIRMED.**

### Eloquent models

| Model | Fillable/casts | Relationships |
|---|---|---|
| `User` | Fillable name, email, role, requested_role, affiliation, status, registration token, submitted_at, last_login_at, password. Casts email_verified_at/last_login_at/submitted_at datetime and password hashed. | No explicit relationships declared. |
| `Category` | Fillable name. | `products()` has many products. |
| `Product` | Fillable category_id, name, sku, description, selling_price, stock_quantity, reorder_threshold. Decimal price, integer stock/threshold. | Belongs to category; has stock movements and sale items. |
| `Sale` | Fillable user_id, transaction_number, idempotency_key, transaction_date, status, payment_method, total_amount. Datetime transaction date, decimal total. | Belongs to user; has sale items. |
| `SaleItem` | Fillable sale/product IDs, product_name, sku, quantity, unit_price, line_total. Integer quantity, decimals for prices. | Belongs to sale and product. |
| `StockMovement` | Fillable product_id, user_id, movement_type, quantity, balance_after, note. Integer quantity/balance. | Belongs to product and nullable user. |
| `RegistrationAudit` | Fillable applicant_id, administrator_id, action, assigned_role. | Belongs to applicant and administrator users. |
| `MlModel` | Fillable dataset_id, algorithm, target, features, feature_ranges, record_count, metrics, training_metrics, artifact_path, trained_at. JSON casts for feature/metric fields; datetime trained_at; artifact path hidden. | No relationships. |

Product deletion is rejected by `InventoryController` when sale items exist. Database FKs also restrict deletion when stock movements exist or when a category has products. A product with no dependent records can be deleted.

Allowed role/status values are enforced in controller validation and migrations/defaults:

- Roles: `admin`, `instructor`, `inventory_clerk`, `sales_personnel`.
- Operational statuses: `active`, `suspended`.
- Registration statuses also use `pending` and `rejected`.
- Sale statuses: `completed`, `voided`.

## 5. Business logic

### Inventory

`InventoryController::validated()` requires category, name, SKU, selling price, stock quantity, and reorder threshold; numeric values are non-negative, and SKU is unique except for the current product. Stock creation records an initial `received` movement when quantity is positive. Product edits record a `correction` movement for stock differences.

Stock adjustments require `movement_type` in `received`, `reduction`, or `correction`, a positive integer quantity, and a required note. `received` adds stock; `reduction` and `correction` subtract the requested quantity in the current implementation. A transaction lock and `newQuantity < 0` guard prevent negative stock.

Low stock means stock greater than zero and `stock_quantity <= reorder_threshold`. Out of stock means quantity exactly zero. Inventory slow-moving means positive stock with no completed sale in the last 90 days.

### Sales

`SalesController::store()` accepts a UUID idempotency key, payment method, and product/quantity pairs. It locks each product, rejects insufficient stock, calculates each line from the current `selling_price`, calculates the total server-side, decrements stock, creates a `sale` stock movement, and persists a completed sale plus snapshot item name/SKU/unit price/line total.

Sales list filters include transaction number search, configured-timezone calendar day, payment method, and status. Transactions are paginated by 20. `show()` returns the selected sale with items and a paginated transaction list.

The schema has a `voided` status, but no controller route or UI action was found that voids/refunds/cancels a sale. **CONFIRMED missing feature.**

### Dashboard

`DashboardController`:

- `total_sales`: sum of `total_amount` across all sales with `status = completed`; no range filter.
- `transactions`: count of all completed sales; no range filter.
- `products`: total product count.
- `low_stock`: positive-stock products at or below reorder threshold.
- `out_of_stock`: products with stock exactly zero.
- `inventory_health.healthy`: total products minus low and out-of-stock, floored at zero.
- `sales_trend`: completed sales grouped by date from `now()->subDays(range - 1)->startOfDay()` through the present; range accepts only 30 or 365.
- `best_sellers`: top five sale-item snapshots by total quantity across all completed sales; no date window.
- `slow_moving`: up to five positive-stock products whose latest completed sale is absent or older than 90 days.

`AnalysisController::insights()` uses the same all-time completed-sales best-seller rule (top 10) and the same 90-day slow-moving rule (top 10; never-sold products have null latest sale and sort first).

### User management and authentication

Registration is rate-limited to three attempts/hour per IP, validates requested non-admin roles and affiliation, creates a `pending` account with a hashed status token, and does not authenticate it. Admin approval assigns one of instructor/inventory clerk/sales personnel and activates the account; rejection sets `rejected`. Login only succeeds for `status = active`; pending, rejected, and suspended accounts receive status-specific errors.

The protected working-tree version of `UserManagementController` prevents self suspend/demotion/deletion and prevents removing or deactivating the last active admin. **CONFIRMED in uncommitted code; not yet part of the committed baseline.**

User update validation accepts nullable password, but the current update path passes the validated array directly to Eloquent. Because the model casts password as hashed, an omitted/null password update should be reviewed carefully; the uncommitted code does not change this behavior. **NOT VERIFIED as a runtime bug.**

### Currency and time

The frontend formats dashboard prices with the Philippine peso symbol (`₱`) and `en-PH` locale in `Dashboard.svelte`; server-side models store decimal numeric amounts. `config/app.php` reads `APP_TIMEZONE` and defaults to `UTC` unless environment overrides it. Sales date filtering uses `config('app.timezone')` in the protected working-tree version. Other UI date formatting uses browser locale utilities. **CONFIRMED.**

## 6. Frontend

### Files

Pages: `Dashboard.svelte`, `ForgotPassword.svelte`, `InventoryManagement.svelte`, `KnowledgeAnalysis.svelte`, `Login.svelte`, `Prediction.svelte`, `Register.svelte`, `ResetPassword.svelte`, `SalesManagement.svelte`, `UserManagement.svelte`.

Components: `Config/previewRoles.js`, `Layout/AppHeader.svelte`, `Layout/AppSidebar.svelte`, `Shared/StatCard.svelte`, `Shared/TracktorSalesChart.svelte`, plus UI primitives and shadcn-compatible subcomponents under `resources/js/lib/components/ui`.

### Role navigation and preview state

`AppSidebar.svelte` derives visible navigation from `auth.user.role` and `roleDefinitions`. The role definitions show:

- Admin: Dashboard, User Management, Prediction, Inventory Management, Sales Management, Knowledge Analysis.
- Instructor: Dashboard, Knowledge Analysis.
- Inventory Clerk: Dashboard, Inventory Management.
- Sales Personnel: Dashboard, Sales Management.

The file is named `resources/js/Components/Config/previewRoles.js`, but the inspected version is a static role-definition map. Backend authorization is not based on it. **CONFIRMED.**

### Page summaries and unfinished indicators

- `Dashboard.svelte`: database-backed KPIs, sales trend chart, inventory health, best sellers, slow movers, and range controls. Add-product shortcut is shown to Admin and Inventory Clerk.
- `InventoryManagement.svelte`: server-paginated catalog, search/filter/sort, product/category dialogs, stock adjustment, history, edit/delete.
- `SalesManagement.svelte`: product cart, payment method, sale submission, filters, pagination, transaction detail.
- `UserManagement.svelte`: users table, search/filter, create/edit/delete/status controls, pending registration approval/rejection.
- `Prediction.svelte`: dataset/algorithm selection, model training, product-demand feature loading, prediction request, metrics/result display. Sales-history business features are unavailable when required fields are missing.
- `KnowledgeAnalysis.svelte`: dataset validation/model list/insights UI; protected working-tree version adds validation and training controls.

`AppHeader.svelte` contains hard-coded preview notifications such as “32 products are below their reorder threshold” and “A new transaction increased today’s sales total by ₱4,800.” A comment says these should be replaced with Inertia props when notification data exists. **CONFIRMED preview data.**

`Login.svelte` still contains “Frontend preview · Authentication will be connected by the backend.” This is stale relative to the implemented `AuthController`. **CONTRADICTED by backend code.**

Responsive patterns include `lg:h-screen`, `min-h-screen`, `overflow-y-auto`, a fixed mobile sidebar width `w-[min(82vw,248px)]`, fixed dialog backdrops, and mobile body-scroll locking. No fullscreen browser API was found. **CONFIRMED.**

## 7. Machine learning

### ML tree and requirements

```text
ml/
  generate_datasets.py
  pipeline.py
  validate_datasets.py
  requirements.txt
  REAL_DATA_READINESS.md
  datasets/product_demand.csv
  datasets/sales_history.csv
  templates/product_demand_template.csv
  templates/sales_history_template.csv
  tests/test_pipeline.py
  tests/test_validate_datasets.py
```

Requirements are `joblib==1.4.2`, `numpy==2.1.3`, `pandas==2.2.3`, and `scikit-learn==1.5.2`.

### Generation and validation

`generate_datasets.py` uses six hard-coded synthetic products, `random.seed(20261030)`, start date 2023-01-01, and 1,200 daily rows. It cycles products by `index % 6`, derives seasonal/trend/promotion/stock/price factors, carries prior demand per product, and creates sales revenue from quantity, price, discount, customer factor, and noise.

`validate_datasets.py` requires 1,000 records, required schema columns, no missing/duplicate rows, numeric values, valid dates and product IDs, non-negative values, bounded promotion/weekday/discount/product index, monotonic dates/day index warnings, and target-leakage checks.

The two template CSV files are empty: no header row and zero data rows were observed. Therefore the README/AI handoff description of them as usable future export templates is **CONTRADICTED** by the current files.

### Dataset evidence

| CSV | Rows | Header/data evidence | Date range | Product count | Missing/duplicates |
|---|---:|---|---|---:|---|
| `product_demand.csv` | 1,200 | Header includes date, product_id, product_name, category, units_sold, stock_quantity, unit_price, product_index, promotion, day_of_week, recent_demand, day_index. First rows use P001/P002/P003. | 2023-01-01 to 2026-04-14 | 6 | 0 missing in inspected fields; 0 duplicate rows |
| `sales_history.csv` | 1,200 | Header includes transaction_date, product_id, product_name, category, quantity, unit_price, discount_rate, product_index, promotion, day_of_week, customer_factor, total_amount, day_index. First rows use P001/P002/P003. | 2023-01-01 to 2026-04-14 | 6 | 0 missing in inspected fields; 0 duplicate rows |

Both CSVs are one row per date with the product cycling each day, not one row per store-day containing all products. `sales_history.total_amount` is a synthetic revenue target, not a `daily_sales_amount` column.

### Pipeline details

`pipeline.py` defines:

- Product features: `day_index`, `stock_quantity`, `unit_price`, `product_index`, `promotion`, `day_of_week`, `recent_demand`; target `units_sold`.
- Sales features: `day_index`, `product_index`, `promotion`, `day_of_week`, `discount_rate`, `customer_factor`; target `total_amount`.
- Chronological split: first 80% train, final 20% test.
- Preprocessing: `SimpleImputer(strategy="median")`.
- Linear model: `LinearRegression`.
- Forest model: `RandomForestRegressor(n_estimators=120, random_state=20261030, min_samples_leaf=2)`.
- Metrics: MAE, MSE, RMSE, R² for test and training predictions.
- Persistence: `joblib.dump()` to the artifact path supplied by Laravel/CLI.
- Prediction validation: exact feature set, numeric finite values, non-negative values, bounds including product index 0-5, and out-of-training-range warnings. Negative model outputs are reported with a warning rather than clipped.

### Laravel ML bridge

`AnalysisController::datasets()` invokes Python validation for both datasets and returns dataset definitions plus algorithm IDs. `models()` returns stored `MlModel` rows. `train()` validates dataset/algorithm, writes a timestamped artifact under `storage/app/private/ml`, invokes Python, and rejects a new model with HTTP 422 if an existing same-dataset/algorithm model has a better R². `predict()` validates the model ID and exact numeric feature payload, checks artifact existence, then invokes Python.

`businessFeatures()` maps current products sorted by database ID to product indexes, uses current stock and price, calculates completed-sale quantity in the last 30 days, sets promotion to 0, and rejects product indexes greater than 5. It returns unavailable for sales-history business predictions because the database lacks discount/customer-factor/promotion fields.

`runPython()` uses `ML_PYTHON` or `python`, working directory `base_path()`, 120-second timeout, Windows system environment values, process-start diagnostics, exit-code/stderr/stdout logging with path sanitization, and distinct invalid-JSON handling. The current uncommitted diff changes the Windows option from `create_new_console` to `create_process_group`.

Existing model artifacts were found under `storage/app/private/ml` with `.joblib` names, but exact sizes were not included in this report because the listing output was truncated and binary contents are intentionally excluded. **Artifact presence: CONFIRMED; complete size inventory: NOT VERIFIED.**

The local SQLite database has no `ml_models` table and therefore no example ML metadata row is available: **NOT AVAILABLE**.

## 8. Tests

### Laravel test files and methods

- `tests/Feature/AnalysisTest.php`: authorized dataset validation; unauthorized analysis; train/persist/predict; bad features/artifacts; allowlists; product-demand business mapping; missing sales schema.
- `tests/Feature/AuthenticationTest.php`: active login; suspended login; route restrictions; shared auth; role matrix; logout; pending registration; duplicate/admin registration rejection; non-active login; approval; rejection.
- `tests/Feature/DashboardTest.php`: dashboard calculations; empty-safe role access.
- `tests/Feature/ExampleTest.php`: guest redirect.
- `tests/Feature/InventoryTest.php`: product CRUD/filter; best-selling/slow-moving filters; stock transaction/history; deletion/roles; invalid values.
- `tests/Feature/SalesTest.php`: completed sale calculation/stock movement; rollback/idempotency; history/detail protection; local-day date filter.

### Python test files and methods

- `ml/tests/test_pipeline.py`: required records; all dataset/algorithm combinations; invalid features/artifacts; invalid/small data; bounds/warnings; negative prediction reporting; chronological/reproducible generation.
- `ml/tests/test_validate_datasets.py`: existing datasets ready; missing columns/leakage rejection.

Tests were **NOT RUN** during this evidence audit. No fresh pass/fail count is claimed.

## 9. Documentation

### Documentation files

- `README.md`
- `docs/AI-HANDOFF.md` (untracked before this report)
- `docs/frontend-backend-handoff.md`
- `docs/project-requirements-audit.md`
- `ml/REAL_DATA_READINESS.md`

README headings include: Project overview; Key features; User roles and permissions; Technology stack; System requirements; Installation and setup; Environment configuration; Machine learning setup; Testing; Project structure; Current limitations; Contributors.

`docs/AI-HANDOFF.md` headings include: Project Overview; Project Structure; Implemented Features; Roles and Permissions; Machine Learning; Current Project State; Testing and Setup; Recommended Next Steps.

AI-HANDOFF claims contradicted or not verified:

1. **CONTRADICTED:** it describes `ml/templates/*.csv` as future real-data CSV templates; both files are empty with no header.
2. **NOT VERIFIED:** its statement that the latest recorded 30 Laravel tests, 9 Python tests, and frontend build passed was not rerun in this audit.
3. **NOT VERIFIED:** its statement that model artifact presence is complete; artifacts exist, but this audit did not verify every artifact/model-metadata pairing.
4. **CONTRADICTED/STALE:** the report says the current working tree contains seven implementation files plus the handoff, but it does not include itself because it was created after that status snapshot; current status now also includes this report.

## 10. Gaps against target specification

| # | Question | Evidence answer |
|---:|---|---|
| 1 | Product has name, SKU, category, purchase price, selling price, stock, low-stock threshold? | **NO.** Name, SKU, category, selling price, stock, reorder threshold exist in migration/model. Purchase price is NOT FOUND. `database/migrations/2026_10_30_000004_create_catalog_and_sales_tables.php:17-26`. |
| 2 | Stock adjustments require a reason and write movement history? | **YES.** `adjustStock()` requires `note`, validates movement type/quantity, updates stock, and creates `StockMovement` in a transaction. `InventoryController.php:143-169`. |
| 3 | Negative stock prevented in both adjustments and sales? | **YES.** Sales rejects insufficient stock; adjustments abort when new quantity is negative. `SalesController.php:66-69`, `InventoryController.php:151-166`. |
| 4 | Sales snapshot item prices and names? | **YES.** `SaleItem` stores product name, SKU, unit price, line total, and quantity; store populates them from the locked product. |
| 5 | Date filtering and receipt/summary view for sales? | **PARTIAL YES.** Date filtering and transaction detail/summary exist. No printable/downloadable receipt route was found. |
| 6 | Admin self-lockout / last-active-admin protection? | **YES in uncommitted code; NOT in committed baseline.** The protected working-tree `UserManagementController.php` adds both guards. |
| 7 | Can Instructor train? Admin train? Either predict? | **YES.** Both roles reach protected analysis train/predict routes through `role:admin,instructor`. |
| 8 | Sales-history target `daily_sales_amount`, one row per store-day? | **NO.** Target is `total_amount`; rows cycle six products by date and are synthetic. |
| 9 | Product-demand one row per product per day, `units_sold` target? | **NO as stated.** Target is `units_sold`, but the file has one row per date with one cycling product, not every product per day. |
| 10 | Each CSV at least 1,000 valid rows? | **YES by static inspection.** Each has 1,200 rows, no duplicates/missing values in the inspected data, and satisfies the pipeline’s 1,000-record minimum. |
| 11 | App database supports MySQL/MariaDB (config + migrations compatible)? | **CONFIGURED, runtime NOT VERIFIED.** Laravel config includes mysql/mariadb and migrations use supported schema APIs; no live service connection was attempted. |
| 12 | Path for training on real store data? | **PARTIAL.** `validate_datasets.py`, `ml/templates`, and `REAL_DATA_READINESS.md` exist, but the templates are empty and the Laravel training path always reads `ml/datasets/*.csv`; no database export/import training path was found. |

## Files unable to read or verify

- No required source file was unavailable.
- Full binary model contents were intentionally not read.
- `.env` contents were intentionally skipped to avoid secrets.
- `node_modules`, `vendor`, build output, and cache directories were not scanned.
- Live MySQL/MariaDB availability was not tested.
- Test/build execution was not performed.

Report saved as `docs/REPO-EVIDENCE-REPORT.md`.  
File size: **approximately 21 KB** after creation.

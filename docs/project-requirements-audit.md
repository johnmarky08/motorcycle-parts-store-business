# Project Requirements Audit

Audit date: 2026-10-30  
Branch: `feat/frontend-completeness`

## 1. Overall application status

The Laravel authentication and server-side route authorization foundation is functional and the automated suite passes. The authenticated frontend is not yet synchronized with that foundation. The shared layout still treats a local `previewRole` value as the visible identity, navigation source, and dashboard data source. As a result, a real Instructor session can display Admin content and Admin navigation while Laravel correctly rejects restricted requests with `403 Forbidden`.

The authentication, role synchronization, dashboard data integration, and inventory persistence milestones are now implemented. The application is not yet ready for full end-to-end acceptance because sales refresh behavior, user-management refresh behavior, and ML workflows remain incomplete.

Stage 4 sales integration is now implemented. Sales history uses persisted, paginated records and server-side filters; the sale form submits product IDs and quantities only, while Laravel calculates prices and totals inside a locked database transaction. Completed sale stock movements and UUID idempotency protection are covered by feature tests. Admin User Management modal positioning now uses a fixed viewport backdrop with a vertically centered, internally scrollable container. Browser-level modal verification was not performed because no browser page was shared.

The modal audit also found that Inventory and Sales dialogs still relied on the native `<dialog open>` user-agent positioning. A global `dialog[open]` reset in [app.css](../resources/css/app.css) now keeps those dialogs static inside their existing fixed, centered, scrollable backdrops. Browser visual verification remains unavailable because no browser page was shared.

The machine-learning and Knowledge Analysis requirements are now **PARTIAL**. The repository contains deterministic, clearly labeled synthetic CSV datasets (1,200 records each), validation and training tests, a controlled Python CLI with Simple Linear Regression and Random Forest Regression, chronological held-out MAE/MSE/RMSE/R² metrics plus training metrics, joblib artifacts, range validation/warnings, and protected Laravel dataset/model/train/predict endpoints. Stage 5.2 corrected a generator leakage bug where current demand was written as its own historical feature, and removed silent negative-prediction clipping. Fresh verification loaded and predicted with all four combinations. Product demand test R² was 0.7456 (linear) and 0.6559 (forest); sales-history test R² was 0.6119 (linear) and 0.8064 (forest). The two pages now consume dataset validation, persisted model metrics, and live completed-sales insight responses. Prediction history, uploads, and background training remain future work, and deployment still requires Python and the pinned `ml/requirements.txt` environment.

The Stage 5.2 metadata migration could not be applied to the configured XAMPP MariaDB during verification because `127.0.0.1:3306` refused the connection. SQLite-backed Laravel tests passed; live MariaDB migration and endpoint verification remain pending until the database service is available.

The dataset-readiness audit confirms that both existing CSVs are synthetic, pipeline-shaped 1,200-row development datasets with no duplicate rows or critical validation errors. [REAL_DATA_READINESS.md](../ml/REAL_DATA_READINESS.md) documents the required real business collection format, privacy-safe Laravel export guidance, leakage rules, and example rows. Blank collection templates are in [ml/templates](../ml/templates), and [validate_datasets.py](../ml/validate_datasets.py) provides non-destructive validation with a nonzero exit status for critical errors. No real business dataset has been collected or validated; the final schema and history requirements still need instructor confirmation.

## 2. Confirmed findings

### Stage 3 inventory status

Inventory Management now uses the database-backed Laravel paginator and supports server-side search, category/status filters, name/price/stock/best-selling sorting, dashboard slow-moving filtering, product CRUD, category creation, protected deletion, transactional stock adjustments, and stock movement history. Product creation and stock edits record movement history, while products referenced by sales cannot be deleted. Automated inventory coverage and live MariaDB query verification passed on 2026-10-30.

| # | Severity | Finding | Evidence / affected files | Impact |
|---|---|---|---|---|
| 1 | CRITICAL | Authenticated frontend identity is controlled by preview state instead of `auth.user`. | [AppSidebar.svelte](../resources/js/Components/Layout/AppSidebar.svelte) initializes `previewRole` and derives `navigation` from `previewRoles`; [AppHeader.svelte](../resources/js/Components/Layout/AppHeader.svelte) exposes the selector on every authenticated page; [Dashboard.svelte](../resources/js/Pages/Dashboard.svelte) also maintains its own `previewRole`. [HandleInertiaRequests.php](../app/Http/Middleware/HandleInertiaRequests.php) does share the real user, but no authenticated page consumes it. | A real Instructor can see Admin navigation and Admin dashboard content. This is an authorization/identity presentation failure even though backend route middleware still blocks restricted requests. |
| 2 | CRITICAL | Sidebar user identity is hardcoded to John Dela Cruz and the Admin preview label. | [AppSidebar.svelte](../resources/js/Components/Layout/AppSidebar.svelte) renders `John Dela Cruz` and `{previewRoles[previewRole].label} preview`. | The logged-in applicant's name and role are not displayed; logout context is misleading. |
| 3 | HIGH | Dashboard content is mock/preview data and ignores the database-backed `dashboard` prop. | [DashboardController.php](../app/Http/Controllers/DashboardController.php) computes totals and a trend, but [Dashboard.svelte](../resources/js/Pages/Dashboard.svelte) exports no `dashboard` prop and renders hardcoded KPI values, charts, “Good morning, John,” role activity, metrics, and stock percentages. | Business data can be presented as if it were current when it is illustrative. Role-sensitive dashboard behavior is not authoritative. |
| 4 | HIGH | Preview-role changes are not a safe application state and can desynchronize multiple components. | [previewRoles.js](../resources/js/Components/Config/previewRoles.js) persists a role in `localStorage` and dispatches a window event; Sidebar, Header, and Dashboard each hold separate local copies. Pages remount and read the persisted role, while the actual authenticated user is ignored. | Explains inconsistent visible role changes and why a previous Admin preview can persist into an Instructor login. It must not be used to select permissions or identity. |
| 5 | RESOLVED | Inventory product creation/editing and stock adjustment were local-only in the page. | [InventoryManagement.svelte](../resources/js/Pages/InventoryManagement.svelte) now submits through the protected [InventoryController.php](../app/Http/Controllers/InventoryController.php) endpoints. | Covered by inventory feature tests and live MariaDB query execution. |
| 6 | HIGH | User update can overwrite an existing password with `null`. | [UserManagementController.php](../app/Http/Controllers/UserManagementController.php) includes nullable `password` in `validated()` and passes the entire result to `$user->update()`; [UserManagement.svelte](../resources/js/Pages/UserManagement.svelte) does not send a password field for normal edits. | Editing a user can make the account unable to authenticate. Password updates must be applied only when a new password was explicitly supplied. |
| 7 | RESOLVED | Sales UI state was initialized from mock/local props and was not refreshed after a successful sale. | [SalesManagement.svelte](../resources/js/Pages/SalesManagement.svelte) now consumes paginated Inertia `transactions` and `products` props and the success response triggers the normal Inertia page refresh. | Sales history, stock, and totals come from persisted records. Covered by sales feature tests. |
| 8 | MEDIUM | User Management also maintains a local copy after mutations and still contains fallback mock users. | [UserManagement.svelte](../resources/js/Pages/UserManagement.svelte) uses `initialUsers` or `mockUsers`, but success callbacks do not replace local state with returned/paged backend data. | Empty environments can show fabricated users; approval/status/create/delete results can remain stale until a full visit. |
| 9 | RESOLVED | Inventory frontend/backend field contracts were inconsistent. | The inventory page now uses database category IDs and submits `category_id` to [InventoryController.php](../app/Http/Controllers/InventoryController.php). | Product creation and update feature tests pass. |
| 10 | MEDIUM | Notifications and several page workflows are explicitly preview-only. | [AppHeader.svelte](../resources/js/Components/Layout/AppHeader.svelte) contains hardcoded notifications; [Prediction.svelte](../resources/js/Pages/Prediction.svelte) and [KnowledgeAnalysis.svelte](../resources/js/Pages/KnowledgeAnalysis.svelte) simulate results/training. | Users may mistake non-persistent demonstrations for current operational events or trained model output. Sales and inventory are no longer part of this preview-only finding. |
| 11 | MEDIUM | Registration status lookup is bearer-token based and has no notification or resubmission workflow. | [AuthController.php](../app/Http/Controllers/AuthController.php) hashes a token and exposes status through `/register?status_token=...`; `.env` uses `MAIL_MAILER=log`, and no registration notification is implemented. | The token avoids arbitrary email enumeration, but the operational feedback flow remains limited and local mail is not real delivery. |
| 12 | LOW | Dashboard and preview content contains fixed dates and hardcoded sample transactions/metrics. | [Dashboard.svelte](../resources/js/Pages/Dashboard.svelte), [Prediction.svelte](../resources/js/Pages/Prediction.svelte), and [KnowledgeAnalysis.svelte](../resources/js/Pages/KnowledgeAnalysis.svelte). | Visual demonstrations can become stale and are unsuitable as production business reporting. |

## 3. Authentication and account lifecycle

### Confirmed working in backend code

- Public registration is exposed through the guest routes in [routes/web.php](../routes/web.php).
- Registration validates email uniqueness, password confirmation/minimum length, non-admin requested roles, and affiliation in [AuthController.php](../app/Http/Controllers/AuthController.php).
- Registration creates a `pending` user and does not authenticate the applicant.
- Approval writes the administrator-selected final role and changes status to `active`.
- Rejection writes `rejected`.
- Status lookup uses a hashed bearer token rather than an arbitrary email lookup.
- Login requires `status = active`, regenerates the session, records `last_login_at`, and retains rate limiting.
- Logout invalidates the session and regenerates the CSRF token.
- Password reset routes use Laravel's password broker.

### Gaps and risks

- `AuthController::register` initially stores `role = sales_personnel` for a pending account even when a different role was requested. Approval overwrites it, but a pending record temporarily has an operational role that was not assigned by an administrator.
- No registration email notification exists. The configured log mailer is not production delivery.
- No email verification flow exists.
- There is no test for status-token lookup, rejected status display, password reset behavior, or redirect behavior after an intended protected URL.
- User Management does not expose audit history after recording approval/rejection events.

## 4. Role-based authorization review

The backend route policy currently matches the requested matrix:

| Feature | Admin | Instructor | Inventory Clerk | Sales Personnel |
|---|---:|---:|---:|---:|
| Dashboard | Yes | Yes | Yes | Yes |
| User Management | Yes | No | No | No |
| Prediction | Yes | No | No | No |
| Inventory Management | Yes | No | Yes | No |
| Sales Management | Yes | No | No | Yes |
| Knowledge Analysis | Yes | Yes | No | No |

Evidence:

- [routes/web.php](../routes/web.php) applies `auth` to all protected routes and `role:...` groups to restricted routes.
- [EnsureUserRole.php](../app/Http/Middleware/EnsureUserRole.php) compares the authenticated persisted `User::role`.
- No policies or gates were found under `app/`; middleware is the current authorization mechanism.
- Approval and role assignment are restricted by the Admin route group and validate the final role.

The backend is authoritative for request authorization. The frontend is not authoritative and currently exposes incorrect navigation because it uses the preview selector. This must be fixed even though direct unauthorized requests return `403`.

## 5. Page-by-page consistency audit

| Page | Backend source | Current assessment |
|---|---|---|
| Dashboard | `DashboardController` provides basic totals/trend | PARTIAL: page ignores props and renders mock Admin/role-preview data. |
| User Management | `UserManagementController` provides users and approval data | PARTIAL: approval actions exist, but identity/navigation is wrong, local state is stale, fallback mock users remain, and password edit handling is unsafe. |
| Prediction | Closure route renders the page | MISSING backend integration: all four workflows and results are simulated. |
| Inventory Management | `InventoryController` provides products/categories and mutation endpoints | PARTIAL: list is connected, product and stock mutations are local-only, category field names do not match, and movement history is absent. |
| Sales Management | `SalesController` provides products/transactions and transactional sale creation | PARTIAL: sale submission is connected, but local state does not refresh; pagination/filter/detail integration is incomplete. |
| Knowledge Analysis | Closure route renders the page | MISSING backend integration: datasets, upload, training, metrics, model saving/loading, and history are preview-only. |

## 6. Database and backend consistency

- User statuses are now `pending`, `active`, `rejected`, and `suspended`; the migration converts legacy `inactive` rows to `suspended`.
- User roles are strings with controller validation; there is no database-level role constraint.
- Product SKU, user email, transaction number, category name, foreign keys, and stock quantities have database constraints.
- Sales use a transaction and `lockForUpdate()` before checking/deducting stock.
- Stock movements record signed quantities and post-adjustment balances.
- Sale items preserve historical product name, SKU, price, quantity, and line total.
- Dashboard totals are queried from completed sales/products/users, but the page does not consume them.
- `DashboardController` uses `DATE(transaction_date)` and `groupBy(DB::raw(...))`, which is broadly portable but should be verified against the target MySQL version.
- The development `.env` reports `DB_CONNECTION=mysql`; PHPUnit deliberately uses SQLite `:memory:`. No live MySQL test was run during this audit.
- No destructive migration or database reset was run.

## 7. Existing tests and actual results

Commands run:

- `php artisan route:list --no-ansi` — passed; 33 routes listed, including registration, approval, role-protected business routes, login, logout, and reset routes.
- `php artisan test` — passed; 11 tests and 45 assertions.
- `npm run build` — passed; Vite transformed 773 modules and produced the production bundle.
- `git diff --check` — passed with existing CRLF normalization warnings only.
- Environment inspection confirmed `DB_CONNECTION=mysql`, `SESSION_DRIVER=file`, and `MAIL_MAILER=log` without printing credentials.

The current tests cover active/suspended login, registration, duplicate/admin-role validation, pending/rejected/suspended login rejection, approval/rejection authorization, audit records, logout, one restricted-route case, and the guest root redirect.

Missing test coverage includes:

- Separate successful login tests for Admin, Instructor, Inventory Clerk, and Sales Personnel.
- All role matrix combinations for every protected route.
- Authenticated Inertia `auth.user` prop contents.
- Role changes after logout/login and UI navigation visibility.
- Registration status-token lookup and rejected feedback.
- Password recovery/reset.
- User update preserving an existing password.
- Inventory CRUD, category mapping, stock movement history, negative-stock rollback, and authorization.
- Sales totals, duplicate line handling, rollback, stale UI refresh, filters, and details.
- Dashboard aggregate correctness.
- Browser-level tests for preview-role persistence, actual authenticated identity, responsive behavior, and error states.
- MySQL integration.

## 8. Recommended implementation order

1. **Remove preview identity from authenticated behavior.** Pass `auth.user` into the shared shell/pages, derive navigation from the persisted role, display the authenticated name/initials, and either remove the selector or explicitly isolate it to a non-authenticated design-demo mode.
2. **Make Dashboard database-backed.** Consume the controller props, add role-sensitive real aggregates, and remove hardcoded Admin/user/sample reporting from the authenticated path.

## Stage 2 implementation note

The authenticated dashboard now consumes database-backed props from
`DashboardController`. Revenue and transaction counts include only `completed`
sales. Inventory health separates healthy, low-stock, and zero-stock products
using each product's `reorder_threshold`. Best sellers aggregate completed
`sale_items`.

Slow-moving products are defined as in-stock products with no completed sale in
the previous 90 days; products with no completed sale history are included and
displayed as `Never`. This is an operational dashboard rule rather than an
existing persisted business setting, so it should be confirmed or adjusted
when formal inventory policy is established.
3. **Fix mutation contracts.** Preserve passwords on ordinary user edits, map inventory categories to IDs, submit inventory mutations to Laravel, and refresh local state from Inertia props after every mutation.
4. **Complete authorization tests.** Cover the full requested role matrix and verify Inertia shared user props after login and after role changes.
5. **Complete domain tests and UI error handling.** Add inventory/sales rollback and validation tests, audit history, pagination, and reliable loading/error/empty states.
6. **Implement dataset and ML requirements.** Add CSV persistence/validation with the 1,000-record rule, the approved Python CLI, real Linear Regression and Random Forest Regression training, metrics, model persistence/retraining, prediction history, and four real prediction workflows.
7. **Run MySQL and browser QA.** Validate migrations/queries on MySQL and test login/logout, role switching between accounts, navigation, responsive behavior, and mutation refresh in a real browser.

## 9. Configuration and clarification items

- Configure production mail delivery before claiming registration or password-reset notifications are operational.
- Confirm whether email verification is required.
- Decide whether rejected applicants may resubmit using the same email.
- Define dataset schemas, feature definitions, minimum history, model retention, and job execution policy.
- Define tax, discount, returns, refunds, voids, and payment-gateway behavior.
- Confirm the target MySQL version and run a real MySQL test environment.

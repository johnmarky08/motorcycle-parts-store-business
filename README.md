# One Prompt Away — Motorcycle Parts Store Management System

Business application with knowledge analysis and prediction for a motorcycle-parts store.
Laravel 12 · Inertia 3 · Svelte 5 · Tailwind · MySQL/MariaDB · Python (scikit-learn).

## 1. Features

| Area | What it does |
| --- | --- |
| Shop (User role) | Catalog of every part with name, category, picture, price and stock; search, category filters and sorting; product details; cart with edit and remove; checkout with a payment method; order history |
| Accounts | Register (instant customer account), sign in, password reset by email, role-based access |
| Inventory | Products, categories, audited stock movements (received / reduction / sale / correction), low and out-of-stock tracking |
| Sales | Every customer order becomes a sale automatically (stock is deducted atomically); sales staff see the full history with customer names, filters and a date range. Read-only: nobody enters sales by hand |
| User management | Instructors manage every account and role; admins manage every account except instructor accounts. Create users (password required), edit them (a new password needs the current one; blank keeps it), suspend or delete users |
| Knowledge analysis | Dataset preview, upload and refresh from orders; Simple Linear Regression, Random Forest Regression or both; train / save / load models; evaluation metrics with descriptions |
| Prediction | Product demand, sales, slow-moving products, best-selling products — with either model or both combined |
| Dashboard | Role-specific cards and charts (customers have no dashboard: they start in the shop) |
| Full-screen display | Every screen fills the whole browser window (no fixed-width column). The **full-screen button** in the header (or top-right on the sign-in pages) switches the browser to true full-screen mode, which stays on while moving between screens; installed as an app, it opens full-screen |

### Roles and access

| Role | Can open |
| --- | --- |
| User (customer) | Shop, My Orders |
| Inventory clerk | Dashboard, Inventory Management |
| Sales personnel | Dashboard, Sales Management (read-only history) |
| Admin | Dashboard, User Management, Prediction (inputs and outputs only — no metrics) |
| Instructor | Dashboard, User Management, Prediction, Knowledge Analysis (datasets, training, evaluation metrics and descriptions) |

The instructor is the most powerful role, then the admin. The instructor can change any account to any role. The admin manages every other
account but cannot assign the instructor role or edit, suspend or delete an instructor account (otherwise an admin could create an instructor
account and reach the evaluation metrics). Nobody can change or delete their own account there, and at least one active instructor or admin
always remains. Access is enforced on the server by route middleware, and the
dashboard only offers links to pages the role may open.

### Registration and shopping flow

1. Anyone can **Register** with name, email and password. The account is a **User** (customer) and is active immediately — the browser cannot choose a role.
2. Staff accounts are created or changed by an instructor or admin in **User Management**.
3. A customer adds parts to the cart (quantity can be edited or items removed), chooses a payment method and presses **Place Order**.
   There is no online payment gateway: the payment method is recorded only. Prices come from the database and stock is re-checked at checkout.
4. The order is saved as a sale with the customer's name, appears in the customer's **My Orders** and in the sales history, reduces stock and is
   written to the stock movement log. A retried or double-clicked checkout cannot create two orders.

## 2. Setup

Requirements: PHP 8.2+, Composer, Node 20+, MySQL 8 or MariaDB 10.6+, Python 3.10+.

```bash
composer install
npm install
cp .env.example .env            # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate

# create the empty database (name from DB_DATABASE in .env), for example:
#   CREATE DATABASE motorcycle_parts_store_management_system CHARACTER SET utf8mb4;

pip install -r ml/requirements.txt         # a virtualenv named .venv in the project folder is picked up automatically
php artisan migrate:fresh --seed           # tables, accounts, 48 customers, 63 products with pictures, 14,385 sales, datasets, 4 trained models
npm run build                              # or: npm run dev
php artisan serve
```

Open http://localhost:8000 — `/` goes to the sign-in page. Customers land in the shop after signing in; staff land on the dashboard.

`migrate --seed` also trains the four starter models. If Python is not ready it only prints a warning; install the packages and run
`php artisan ml:train` (add `--tune` for hyper-parameter tuning). Set `ML_PYTHON` in `.env` when the right Python is not found
(Windows example: `ML_PYTHON=C:/Python312/python.exe`).

### Default accounts (set in `.env` before seeding; defaults come from `config/store.php`)

| Role | Email | Password |
| --- | --- | --- |
| Instructor | instructor@oneprompt.test | Instructor@12345 |
| Admin | admin@oneprompt.test | Admin@12345 |
| Inventory clerk | inventory@oneprompt.test | Inventory@12345 |
| Sales personnel | sales@oneprompt.test | Sales@12345 |
| User (customer) | customer@oneprompt.test | Customer@12345 |

The seeder also creates a few more staff accounts and 48 customers (they appear by name on the seeded sales history).
**Change these passwords before any real deployment.**

### Password reset email (real sending)

Reset links are sent with Laravel's mailer. The free option is a Gmail account with an App password:

1. Google Account → Security → turn on 2-Step Verification → **App passwords** → create one.
2. In `.env` set `MAIL_USERNAME` (the Gmail address), `MAIL_PASSWORD` (the 16-character app password) and `MAIL_FROM_ADDRESS` (same Gmail address).
   The other `MAIL_*` lines are already set for `smtp.gmail.com:587`.
3. Check it: `php artisan mail:test you@example.com`.

Other options: `MAIL_MAILER=phpmail` uses PHP's built-in `mail()` function (needs a working sendmail on the server or `sendmail_path` in `php.ini`
on XAMPP); `MAIL_MAILER=log` only writes the message to `storage/logs`.

> **The reset email may arrive in the spam or junk folder** (mail from a free address and a local server is often filtered). The
> Forgot-password page says so, and users should check there if the message does not appear after a few minutes.
> The reset link opens at `APP_URL`, so set `APP_URL` to the address people really use.

## 3. Datasets and machine learning

| File (`ml/datasets/`) | Rows | One row is… |
| --- | --- | --- |
| `sales_history.csv` | 126,552 | a sold item on a transaction (81,214 transactions, Nov 2023 – Nov 2026) |
| `product_demand.csv` | 9,828 | units sold for one product in one week (63 products × 156 weeks) |

The data is **synthetic** (realistic parts catalog and prices, weekday/payday/seasonal patterns, growth, random noise), generated
deterministically by `python ml/generate_datasets.py`. The database is seeded from the same CSV, so dashboard, transactions and datasets agree.
Do not present it as real business data. Instructors can upload their own CSV (see `ml/README.md`); uploads are validated before saving
(columns, dates, numbers, at least 1,000 records, duplicates, missing values). **Refresh Datasets From Orders** exports the orders in
the database (the seeded history plus every real order) to two live datasets that can then be used to retrain the models.

```
Laravel (MlService) --proc_open, JSON on stdin--> python ml/cli.py train | predict --JSON on stdout--> Laravel
                              reads only CSV files and saved models (no database access from Python)
```

* **Both models at once:** in Prediction, *Both Models (Combined)* runs Simple Linear Regression and Random Forest Regression together and
  averages their forecasts (slow-moving and best-selling rankings use the averaged forecast); each model's own result is shown next to the
  combined one. In Knowledge Analysis, *Both Models (Train Both)* trains and saves both in one step.
* **Train once, load many times.** Training evaluates on a chronological hold-out (rolling 4-week-ahead forecasts), refits on all data, saves a
  `.joblib` model and records metrics, parameters and a SHA-256 hash in MySQL. Predictions only **load** the saved model.
* **Simple Linear Regression:** a linear trend on the time index (per product for demand), optional month seasonal index.
* **Random Forest Regression:** lag, rolling-mean and calendar features, recursive multi-step forecasting, optional tuning
  (`RandomizedSearchCV` + `TimeSeriesSplit`). For sales the forest models the ratio to a linear trend (a forest cannot extrapolate growth).
* **Slow-moving / best-selling:** derived from the demand model — predicted 30-day velocity against current stock (days of cover, percentile rank),
  and ranked predicted units and revenue for 30/60/90 days.
* **No console window:** Python is started with `proc_open` without a new console; on Windows `pythonw.exe` is used when present.
* **Metrics:** MAE, RMSE, MAPE (non-zero actuals only), WAPE and R², each described on the Knowledge Analysis page.

Results with the shipped data (80/20 split, no tuning):

| Task | Model | MAE | RMSE | MAPE | WAPE | R² |
| --- | --- | --- | --- | --- | --- | --- |
| Weekly sales (₱) | Simple Linear Regression | 14,157 | 17,715 | 6.8% | 6.8% | −0.02 |
| Weekly sales (₱) | Random Forest Regression | 13,852 | 18,077 | 6.5% | 6.7% | −0.07 |
| Weekly demand (units/product) | Simple Linear Regression | 1.96 | 2.71 | 57% | 41% | 0.50 |
| Weekly demand (units/product) | Random Forest Regression | 1.98 | 2.69 | 61% | 41% | 0.51 |

Read these honestly: weekly sales are predicted within about 6–7%, but the hold-out weeks vary only by random noise around the trend, so R²
is near zero. Product demand is small counts (about 4 units a week), so percentage errors are large by nature; WAPE and R² describe it better
than MAPE. Because the data is synthetic, the two algorithms perform alike; real data with richer patterns is where the forest should win.

## 4. Testing

```bash
php artisan test                                  # 92 tests (SQLite in memory): auth, roles, shop and checkout, orders, inventory, dashboard, security, prediction
python -m unittest discover -s ml/tests -v        # 10 tests: training, saved-model prediction, both-models blending, integrity and path safety
                                                  #   (use the Python that has ml/requirements.txt, e.g. .venv/Scripts/python on Windows)
npm run build                                     # production frontend build
```

Browser interaction was exercised with scripted HTTP requests against a running server (login per role, role access, all four predictions,
training); it is not part of the automated suites.

## 5. Security (OWASP Top 10:2021)

| Risk | Controls |
| --- | --- |
| A01 Broken access control | `role:` middleware on every route group (deny by default) and `active` check on every request, suspended users signed out, nobody can change their own role or remove the last instructor/admin, admins cannot create or take over instructor accounts, each role's dashboard only receives the figures it shows, metrics are removed from the admin's prediction response on the server, customers only see their own orders, prices/totals computed on the server, refusals logged |
| A02 Cryptographic failures | bcrypt hashing, encrypted sessions, `HttpOnly`/`SameSite=Lax` cookies, `SESSION_SECURE_COOKIE` + HSTS on HTTPS, `APP_DEBUG=false` by default |
| A03 Injection | Eloquent/query-builder bindings, `LIKE` wildcards escaped, FormRequest/validator rules on every input, Python started with an argument array (no shell), Svelte escapes output |
| A04 Insecure design | Customers cannot choose a role, checkout runs in a DB transaction with row locks and re-checks stock and prices on the server, idempotency keys against double submit, upload limits |
| A05 Misconfiguration | Content-Security-Policy, X-Frame-Options, nosniff, Referrer/Permissions-Policy, `no-store` on signed-in pages, friendly error pages, uploads outside `public/` |
| A06 Vulnerable components | Locked dependencies; `composer audit` and `npm audit` report no known vulnerabilities (checked Nov 27, 2026). Run them again, plus `pip list --outdated`, before every release |
| A07 Authentication failures | Login throttling per email+IP, generic failure message, session regeneration, POST-only logout with CSRF, strong-password rule everywhere, throttled registration, reset-link requests and reset submissions, password reset that never reveals whether an email exists, changing a password in User Management requires the current password, `role`/`status` are not mass-assignable |
| A08 Integrity failures | Saved models load only if their SHA-256 matches the database; Python reads only inside `storage/app/ml`; uploads are parsed and validated, never executed |
| A09 Logging & monitoring | `storage/logs/security-*.log`: sign-ins and failures, denials, registrations, user changes, orders, uploads, model training |
| A10 SSRF | The application never fetches user-supplied URLs |

Not covered by code: HTTPS termination, database privileges, backups, patching and web-server rate limiting. This is a hardened baseline,
not a certification.

## 6. Project structure

```
app/Http/Controllers   Auth, Dashboard, UserManagement, Inventory, Sales, Shop, Order, Prediction, KnowledgeAnalysis
app/Http/Middleware    EnsureUserRole, EnsureUserIsActive, SecurityHeaders, HandleInertiaRequests
app/Services/Ml        MlService (Laravel ↔ Python bridge, proc_open)
app/Services/Datasets  CSV validation, previews, registry
app/Mail               PhpMailTransport (MAIL_MAILER=phpmail)
app/Notifications      ResetPasswordNotification (branded reset email, views in resources/views/emails)
app/Support            ResetLink (sealed reset-link code), Like (LIKE escaping), NotificationFeed
ml/                    cli.py, sales_model.py, demand_model.py, common.py, datasets/, templates/, tests/
database/              migrations, factories, seeders (+ seeders/data/products.csv, generate_product_images.py)
public/images          SVG illustrations of every product and category (replace any file with a real photo of the same name)
public/favicon.svg     App icon (+ PNG sizes and site.webmanifest); images/logo.png is the reset-email logo (source: public/logo.svg)
resources/js/Pages     Login, Register, ForgotPassword, ResetPassword, Shop, MyOrders, Dashboard, Inventory, Sales, UserManagement, Prediction, KnowledgeAnalysis, Error
resources/js/Components  Layout (header, sidebar) and Shared (BrandLogo, FullscreenToggle, StatCard, sales chart)
resources/js/lib       shadcn-style UI components and label helpers
docs/                  Requirement-by-requirement compliance matrix
```

## 7. Current limitations

- The ML data is synthetic; see section 3 for what the metrics do and do not show.
- Product pictures are generated illustrations, not photographs. Brand photos are copyrighted and could not be downloaded in the build environment; to use real
  photos, save them as `public/images/products/<SKU>.jpg` and set the product's `image` column (or replace the `.svg` file).
- Checkout records the payment method only; there is no payment gateway, delivery tracking or order status — an order is a completed sale the moment it is placed.
- Email delivery needs your own SMTP credentials (see section 2); delivery to the inbox (instead of spam) cannot be guaranteed from a free address.
- On Windows, the analysis runs with the windowless `pythonw.exe` from the project `.venv` (verified with `php artisan serve` on Windows 11).
  If Windows **Smart App Control** blocks a freshly downloaded numpy/scipy DLL ("An Application Control policy has blocked this file"),
  install widely used releases instead: `pip install numpy==2.1.3 scipy==1.14.1 scikit-learn==1.5.2 pandas==2.2.3`.
- Models train on CSV datasets. New orders reach the models when an instructor presses **Refresh Datasets From Orders** and then retrains.
- The sales models reach about 6.5–7% MAPE, but their R² on the hold-out weeks is close to 0. The hold-out covers only about 19 weeks, and the
  week-to-week swings in that window are mostly random (the synthetic data draws daily orders at random), so there is little pattern left
  for any model to explain. Judge sales forecasts by MAPE/WAPE; R² is more meaningful for product demand (about 0.50).
- Not production-ready by test results alone: deployment, HTTPS, secrets management, monitoring and real-data governance still need review.

## 8. Contributors

Project team of the course assignment (instructor-assigned business application with knowledge analysis).

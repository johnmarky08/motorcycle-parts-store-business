# One Prompt Away — Motorcycle Parts Store Management System

Business application with knowledge analysis and prediction for a motorcycle-parts store.
Laravel 12 · Inertia 3 · Svelte 5 · Tailwind · MySQL/MariaDB · Python (scikit-learn).

## 1. Features

| Area | What it does |
| --- | --- |
| Accounts | Sign up with a requested role, administrator approval, sign in, password reset by email, role-based access |
| Inventory | Products, categories, audited stock movements (received / reduction / sale / correction), low and out-of-stock tracking |
| Sales | Multi-item transactions, history with filters, stock deducted atomically, duplicate-submit protection |
| User management | Approve or reject registrations (any of the four roles), edit, suspend, delete users |
| Knowledge analysis | Dataset preview and upload, Simple Linear Regression and Random Forest Regression, train / save / load models, evaluation metrics with descriptions |
| Prediction | Product demand, sales, slow-moving products, best-selling products |
| Dashboard | Role-specific cards and charts |

### Roles and access

| Role | Can open |
| --- | --- |
| Inventory clerk | Dashboard, Inventory Management |
| Sales personnel | Dashboard, Sales Management |
| Admin | Dashboard (full), User Management, Prediction (inputs and outputs only — no metrics) |
| Instructor | Dashboard (full), Prediction, Knowledge Analysis (datasets, training, evaluation metrics and descriptions) |

Access is enforced on the server by route middleware (`role:`), not only by hiding menu items.

### Registration flow

1. A visitor requests access on **Sign up** (name, email, password, requested role, affiliation). All four roles can be requested.
2. The account is **pending** and cannot sign in. The applicant gets a private status link.
3. An admin approves it in **User Management** (and may grant a different role than requested) or rejects it.

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
php artisan migrate:fresh --seed           # tables, accounts, 63 products, 14,385 sales, datasets, 4 trained models
npm run build                              # or: npm run dev
php artisan serve
```

Open http://localhost:8000 — `/` goes to the sign-in page.

`migrate --seed` also trains the four starter models. If Python is not ready it only prints a warning; install the packages and run
`php artisan ml:train` (add `--tune` for hyper-parameter tuning). Set `ML_PYTHON` in `.env` when the right Python is not found
(Windows example: `ML_PYTHON=C:/Python312/python.exe`).

### Default accounts (set in `.env` before seeding; defaults come from `config/store.php`)

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@oneprompt.test | Admin@12345 |
| Instructor | instructor@oneprompt.test | Instructor@12345 |
| Inventory clerk | inventory@oneprompt.test | Inventory@12345 |
| Sales personnel | sales@oneprompt.test | Sales@12345 |

The seeder also creates a few more staff accounts and two pending registrations so the approval screen has data.
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
| `sales_history.csv` | 22,419 | a sold item on a transaction (14,385 transactions, Jan 2025 – Nov 2026) |
| `product_demand.csv` | 5,985 | units sold for one product in one week (63 products × 95 weeks) |

The data is **synthetic** (realistic parts catalog and prices, weekday/payday/seasonal patterns, growth, random noise), generated
deterministically by `python ml/generate_datasets.py`. The database is seeded from the same CSV, so dashboard, transactions and datasets agree.
Do not present it as real business data. Instructors can upload their own CSV (see `ml/README.md`); uploads are validated before saving
(columns, dates, numbers, at least 1,000 records, duplicates, missing values).

```
Laravel (MlService) --proc_open, JSON on stdin--> python ml/cli.py train | predict --JSON on stdout--> Laravel
                              reads only CSV files and saved models (no database access from Python)
```

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
php artisan test                                  # 63 tests (SQLite in memory): auth, roles, inventory, sales, dashboard, security, prediction
python -m unittest discover -s ml/tests -v        # 8 tests: training, saved-model prediction, integrity and path safety
npm run build                                     # production frontend build
```

Browser interaction was exercised with scripted HTTP requests against a running server (login per role, role access, all four predictions,
training); it is not part of the automated suites.

## 5. Security (OWASP Top 10:2021)

| Risk | Controls |
| --- | --- |
| A01 Broken access control | `role:` middleware on every route group (deny by default) and `active` check on every request, suspended users signed out, admins cannot demote/suspend themselves or remove the last admin, prices/totals computed on the server, refusals logged |
| A02 Cryptographic failures | bcrypt hashing, encrypted sessions, `HttpOnly`/`SameSite=Lax` cookies, `SESSION_SECURE_COOKIE` + HSTS on HTTPS, `APP_DEBUG=false` by default |
| A03 Injection | Eloquent/query-builder bindings, `LIKE` wildcards escaped, FormRequest/validator rules on every input, Python started with an argument array (no shell), Svelte escapes output |
| A04 Insecure design | Approval-based registration, sales and stock changes in DB transactions with row locks, idempotency keys against double submit, upload limits |
| A05 Misconfiguration | Content-Security-Policy, X-Frame-Options, nosniff, Referrer/Permissions-Policy, `no-store` on signed-in pages, friendly error pages, uploads outside `public/` |
| A06 Vulnerable components | Locked dependencies — run `composer audit`, `npm audit` and `pip list --outdated` before release |
| A07 Authentication failures | Login throttling per email+IP, generic failure message, session regeneration, POST-only logout with CSRF, strong-password rule everywhere, throttled password reset that never reveals whether an email exists |
| A08 Integrity failures | Saved models load only if their SHA-256 matches the database; Python reads only inside `storage/app/ml`; uploads are parsed and validated, never executed |
| A09 Logging & monitoring | `storage/logs/security-*.log`: sign-ins and failures, denials, registrations, approvals, user changes, sales, uploads, model training |
| A10 SSRF | The application never fetches user-supplied URLs |

Not covered by code: HTTPS termination, database privileges, backups, patching and web-server rate limiting. This is a hardened baseline,
not a certification.

## 6. Project structure

```
app/Http/Controllers   Auth, Dashboard, UserManagement, Inventory, Sales, Prediction, KnowledgeAnalysis
app/Http/Middleware    EnsureUserRole, EnsureUserIsActive, SecurityHeaders, HandleInertiaRequests
app/Services/Ml        MlService (Laravel ↔ Python bridge, proc_open)
app/Services/Datasets  CSV validation, previews, registry
app/Mail               PhpMailTransport (MAIL_MAILER=phpmail)
ml/                    cli.py, sales_model.py, demand_model.py, common.py, datasets/, templates/, tests/
database/              migrations, factories, seeders (+ seeders/data/products.csv)
resources/js/Pages     Login, Register, ForgotPassword, ResetPassword, Dashboard, Inventory, Sales, UserManagement, Prediction, KnowledgeAnalysis, Error
resources/js/lib       shadcn-style UI components
docs/                  Requirement-by-requirement compliance matrix
```

## 7. Current limitations

- The ML data is synthetic; see section 3 for what the metrics do and do not show.
- Email delivery needs your own SMTP credentials (see section 2); delivery to the inbox (instead of spam) cannot be guaranteed from a free address.
- Windows behaviour of the Python launcher (no console window) was designed from the PHP/Windows process rules and the previous Tempus project;
  it was verified on Linux only. If a console window still appears on your machine, tell us whether you use XAMPP/Apache or `php artisan serve` so the launcher can be adjusted.
- Models are trained on the CSV datasets, not on live transactions; to include new sales, export them to the CSV layout and upload/retrain.
- Not production-ready by test results alone: deployment, HTTPS, secrets management, monitoring and real-data governance still need review.

## 8. Contributors

Project team of the course assignment (instructor-assigned business application with knowledge analysis).

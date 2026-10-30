# One Prompt Away — Motorcycle Parts Store Management System

## 1. Project overview

One Prompt Away is a Laravel and Inertia-based management system for a motorcycle-parts store. It brings authentication, role-aware workspaces, inventory operations, sales processing, business metrics, and machine-learning experiments into one application.

The system is designed to help store staff maintain an accurate catalog, track stock movements, record completed sales, identify best-selling and slow-moving products, and evaluate demand and sales prediction models.

## 2. Key features

- Session-based authentication, registration requests, password recovery, and logout.
- Role-based access control with administrator approval for registration requests.
- User management, role assignment, account status changes, approval, rejection, and deletion.
- Inventory catalog management for products and categories.
- Stock receiving, reduction, correction, and movement history.
- Sales transaction creation with server-calculated totals, stock checks, payment methods, and idempotency protection.
- Dashboard metrics for sales, transactions, products, low stock, inventory health, best sellers, and slow-moving products.
- Knowledge Analysis workspace for validated datasets, saved model metrics, and product insights.
- Product demand and sales-history prediction workflows.
- Simple Linear Regression and Random Forest Regression training.
- Persisted model metadata and private model artifacts.
- Synthetic dataset generation and validation tooling for development and testing.

The ML datasets currently included in the repository are synthetic development data. They are not production store transactions.

## 3. User roles and permissions

The backend middleware is authoritative. The frontend navigation reflects the same role matrix.

| Role | Permissions |
|---|---|
| Admin | Dashboard, user management, inventory, sales, prediction, and knowledge analysis |
| Instructor | Dashboard and knowledge analysis |
| Inventory Clerk | Dashboard and inventory management |
| Sales Personnel | Dashboard and sales management |

Authentication is required for application workspaces. Administrative, inventory, sales, and analysis endpoints are protected by role middleware.

## 4. Technology stack

- PHP 8.2 or newer
- Laravel 12
- Inertia.js Laravel adapter 3.5+
- Svelte 5.57.3
- Inertia Svelte 3.9.1
- Vite 6
- Tailwind CSS 4
- shadcn-svelte 1.7.2
- Bits UI 2.20.2
- LayerChart 2.1.0
- D3 Scale 4 and D3 Shape 3
- Lucide Svelte
- SQLite for the default local/test configuration, with MySQL or MariaDB supported through Laravel configuration
- Python 3.12 recommended for the current ML dependency set
- joblib 1.4.2
- NumPy 2.1.3
- pandas 2.2.3
- scikit-learn 1.5.2

Exact JavaScript versions and transitive dependencies are recorded in `package-lock.json`. Exact Python versions are pinned in `ml/requirements.txt`.

## 5. System requirements

Install the following before setup:

- PHP 8.2+
- Composer
- Node.js and npm compatible with the Vite 6 toolchain
- Python 3.12 recommended
- SQLite, or a configured MySQL/MariaDB database

For Windows, ensure `php`, `composer`, `node`, `npm`, and `python` are available in the terminal used to run the project.

## 6. Installation and setup

Clone the repository and enter the project directory:

```bash
git clone <repository-url>
cd motorcycle-parts-store-business
```

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and generate the application key:

```bash
copy .env.example .env
php artisan key:generate
```

On macOS or Linux, use:

```bash
cp .env.example .env
php artisan key:generate
```

The default `.env.example` uses SQLite. Create the database file before running migrations:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Configure the database values in `.env`, then run migrations:

```bash
php artisan migrate
```

Install the ML dependencies:

```bash
python -m venv .venv
```

Activate the environment and install the pinned packages:

```bash
# Windows PowerShell
.venv\Scripts\Activate.ps1
python -m pip install -r ml/requirements.txt
```

```bash
# macOS or Linux
source .venv/bin/activate
python -m pip install -r ml/requirements.txt
```

Start the application in separate terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

The Laravel `composer run dev` script can also start the Laravel server, queue listener, and Vite development server together.

For a production frontend build:

```bash
npm run build
```

## 7. Environment configuration

Use `.env.example` as the source of available configuration. At minimum, configure:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION`
- Database host, port, database name, username, and password when using MySQL or MariaDB
- Session, cache, queue, mail, and filesystem settings as appropriate for the environment

The optional `ML_PYTHON` environment variable selects the Python executable used by the Laravel analysis controller. Set it to the interpreter that has the packages from `ml/requirements.txt` installed when the default `python` command is not sufficient.

Never commit `.env` or credentials. Keep secrets in the local environment or deployment secret store.

## 8. Machine learning setup

### Datasets

The included synthetic datasets are:

- `ml/datasets/product_demand.csv`
- `ml/datasets/sales_history.csv`

Each current development dataset contains 1,200 records and is validated before training. CSV templates for future real-data exports are in `ml/templates/`. Real business data has not been collected or included by this repository.

### Generate datasets

To regenerate the deterministic synthetic datasets:

```bash
python ml/generate_datasets.py
```

### Validate datasets

Validate both datasets without modifying them:

```bash
python ml/validate_datasets.py
```

The validator checks required columns, record count, missing values, duplicates, numeric values, dates, identifier format, valid ranges, and common target-leakage conditions.

### Train and predict

The Python pipeline supports:

- `product_demand` with target `units_sold`
- `sales_history` with target `total_amount`
- `simple_linear_regression`
- `random_forest_regression`

Example training command:

```bash
python ml/pipeline.py train --dataset product_demand --algorithm simple_linear_regression --artifact storage/app/private/ml/product-demand.joblib
```

The pipeline reports MAE, MSE, RMSE, R², training metrics, feature ranges, and model metadata. Models are persisted as private joblib artifacts and their metadata is stored by Laravel.

Example prediction command:

```bash
python ml/pipeline.py predict --dataset product_demand --artifact storage/app/private/ml/product-demand.joblib --features "{\"day_index\":600,\"stock_quantity\":30,\"unit_price\":1250,\"product_index\":0,\"promotion\":0,\"day_of_week\":2,\"recent_demand\":10}"
```

The application requests training and prediction through authenticated Laravel analysis endpoints. Feature names, numeric values, valid ranges, and model artifacts are checked before a prediction is returned.

The current regression models are trained from the synthetic CSV datasets. Product-demand requests can use current database product and inventory values as inputs, but the product-index encoding has not yet been verified as historically consistent with training. The resulting value must not be treated as a validated real-product demand forecast. Training on actual business data and stable product identity mapping remain future improvements. Sales forecasting from business records is also limited because the current database does not provide all model features, including promotion, discount, and customer-factor information.

## 9. Testing

Run the Laravel feature and unit tests:

```bash
php artisan test
```

Run the Python test suite using the repository's unittest-based tests:

```bash
python -m unittest discover -s ml/tests
```

Validate the frontend production build:

```bash
npm run build
```

Check whitespace errors before committing:

```bash
git diff --check
```

## 10. Project structure

```text
app/
  Http/Controllers/       Laravel request and business workflows
  Http/Middleware/         Authentication and role enforcement
  Models/                  Eloquent models
database/
  migrations/              Application schema
  seeders/                 Development seed data
ml/
  datasets/                Synthetic development CSVs
  templates/               Real-data export templates
  pipeline.py              Training and prediction pipeline
  validate_datasets.py     Dataset readiness validator
  tests/                   Python ML tests
resources/
  css/                     Tailwind CSS entry point
  js/Components/           Shared application components
  js/Pages/                Inertia Svelte page components
  js/lib/components/ui/    Supplied shadcn-svelte-compatible primitives
routes/
  web.php                  Web, Inertia, authentication, and API-like routes
tests/
  Feature/                 Laravel feature coverage
```

## 11. Current limitations

- The included ML data is synthetic development data and must not be presented as operational business data.
- Browser interaction testing requires a running local application and is not part of the automated Laravel or Python suites.
- The frontend production build emits a non-blocking warning for a generated JavaScript chunk larger than 500 KB because of the charting and UI component dependencies.
- Password reset email delivery uses the configured Laravel mail driver; the default local configuration logs mail rather than delivering it.
- The analysis endpoints require a Python executable with the pinned ML dependencies available to the Laravel process. Configure `ML_PYTHON` when the default `python` command resolves to a different environment.

This project should not be considered production-ready solely because the automated tests pass. Deployment, infrastructure, mail delivery, secrets management, observability, and real-data governance require separate operational work.

## 12. Contributors

- [Contributor Name]

Replace the placeholder with the confirmed human project contributors before publishing.

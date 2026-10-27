# Frontend–Backend Integration Handoff

**Scope:** frontend integration contract for the Admin module. This document does not implement features, change the approved dashboard design, add dependencies, or change the database schema.

**Labels:** **Existing** means present in the repository; **Proposed** means a suggested integration shape; **Needs confirmation** means the frontend cannot safely decide it.

## 1. Frontend inventory

### Existing pages

| Page | Responsibility | Current data source |
|---|---|---|
| [Welcome.svelte](../resources/js/Pages/Welcome.svelte) | Root placeholder page at `/`. | Static markup |
| [Dashboard.svelte](../resources/js/Pages/Dashboard.svelte) | Approved Admin dashboard: KPIs, sales trend, inventory health, best sellers and slow-moving products. | Local sample constants; no backend props yet |
| [Prediction.svelte](../resources/js/Pages/Prediction.svelte) | Preview-only prediction inputs and illustrative result presentation at `/prediction`. | Local mock product/model options and sample result |

Page resolution is **Existing**: [app.js](../resources/js/app.js) eagerly resolves `./Pages/${name}.svelte`.

### Existing shared components

| Component | Responsibility |
|---|---|
| [AppSidebar.svelte](../resources/js/Components/AppSidebar.svelte) | Branding, Admin navigation and active item state |
| [AppHeader.svelte](../resources/js/Components/AppHeader.svelte) | Page title, notifications and current-user display |
| [StatCard.svelte](../resources/js/Components/StatCard.svelte) | Reusable KPI card with value, change and icon |

### Planned pages — not existing

These names are **Proposed** and should not be treated as implemented routes:

| Proposed page | Purpose |
|---|---|
| `UserManagement.svelte` | User list and user create/edit/deactivate workflows |
| `Inventory.svelte` or `Products.svelte` | Product catalog, stock levels and stock adjustments |
| `Sales.svelte` | Sales/transaction list and transaction detail |

Prediction and User Management now have frontend preview pages and temporary routes; no corresponding backend controller, persistence, trained model or authorization exists.

## 2. Naming conventions

### Backend/API/Inertia data — Proposed

Use `snake_case` for backend property names, request fields, query parameters and serialized relationships:

```json
{
  "product_id": 42,
  "product_name": "Kawasaki Ninja Brake Pad",
  "stock_quantity": 18,
  "reorder_threshold": 10,
  "transaction_date": "2026-10-28",
  "forecast_horizon_days": 30
}
```

Use numeric IDs as `*_id`; use ISO-8601 dates/timestamps; use integer quantities; use decimal numbers for monetary values and rates. Do not send formatted currency strings to the frontend contract.

Recommended status values are lowercase `snake_case` strings:

- User: `active`, `inactive`
- Product: `active`, `inactive`
- Transaction: `pending`, `completed`, `cancelled`, `refunded`
- Stock movement: `received`, `sale`, `return`, `adjustment`
- Prediction run: `queued`, `running`, `completed`, `failed`, `insufficient_data`
- Inventory health: `healthy`, `needs_attention`, `out_of_stock`

These values are **Proposed** and require confirmation before schema validation is implemented.

### Existing frontend view-model names

The dashboard currently uses local JavaScript properties such as `label`, `value`, `change`, `trend`, `name`, `sku`, `sold`, `stock`, `days`, `count` and `percent`. These are presentation-only and should not force backend naming.

**Integration recommendation:** map backend `snake_case` props into the existing component inputs in the page/controller boundary. Do not rename the approved components or redesign the dashboard merely to match API names.

## 3. Page data contracts

The following shapes are **Proposed** contracts. They are examples, not current endpoints.

### Dashboard — existing page

**Proposed Inertia props:**

```json
{
  "summary": {
    "total_sales": 482560.00,
    "sales_change_percent": 12.8,
    "product_count": 1284,
    "product_count_change_percent": 4.6,
    "low_stock_count": 32,
    "low_stock_change_percent": -8.2,
    "transaction_count": 846,
    "transaction_count_change_percent": 16.4
  },
  "sales_trend": [
    { "period": "2026-01", "actual_sales": 38200.00, "forecast_sales": null },
    { "period": "2026-10", "actual_sales": 482560.00, "forecast_sales": null },
    { "period": "2026-11", "actual_sales": null, "forecast_sales": 501200.00 }
  ],
  "best_sellers": [
    {
      "product_id": 42,
      "product_name": "Kawasaki Ninja Brake Pad",
      "sku": "BP-KN-004",
      "units_sold": 128,
      "sales_value": 38400.00
    }
  ],
  "slow_moving_products": [
    {
      "product_id": 18,
      "product_name": "Universal Chain Adjuster",
      "sku": "CA-UNI-09",
      "stock_quantity": 18,
      "days_since_last_sale": 124
    }
  ],
  "inventory_health": {
    "healthy_count": 1018,
    "needs_attention_count": 234,
    "out_of_stock_count": 32,
    "healthy_percent": 79.0
  }
}
```

Field types: IDs are integers; counts are integers; money and percentages are numbers; absent actual/forecast values are `null`. The dashboard currently formats these values for display.

### User Management — planned page

```json
{
  "users": [
    {
      "id": 7,
      "name": "John Dela Cruz",
      "email": "john@example.com",
      "role": "admin",
      "status": "active",
      "created_at": "2026-10-28T01:00:00+08:00",
      "last_login_at": "2026-10-28T01:30:00+08:00"
    }
  ],
  "roles": ["admin", "instructor", "inventory_clerk", "sales_personnel"],
  "filters": { "search": "", "role": null, "status": null },
  "pagination": { "current_page": 1, "per_page": 20, "total": 1 }
}
```

`role`, role list, status, last-login tracking and pagination shape are **Needs confirmation**. The existing `User` model only has `name`, `email`, password and email verification fields.

### Inventory/Product Management — planned page

```json
{
  "products": [
    {
      "id": 42,
      "sku": "BP-KN-004",
      "name": "Kawasaki Ninja Brake Pad",
      "category_name": "Brake System",
      "selling_price": 300.00,
      "cost_price": 180.00,
      "stock_quantity": 18,
      "reorder_threshold": 10,
      "stock_status": "healthy",
      "is_active": true
    }
  ],
  "categories": [{ "id": 3, "name": "Brake System" }],
  "pagination": { "current_page": 1, "per_page": 20, "total": 1 }
}
```

Product fields, category structure, whether cost price is exposed, stock location, SKU rules and threshold calculation are **Needs confirmation**. No product or inventory entity exists.

### Sales — planned page

```json
{
  "transactions": [
    {
      "id": 9001,
      "transaction_number": "TX-20261028-0001",
      "transaction_date": "2026-10-28T09:30:00+08:00",
      "status": "completed",
      "total_amount": 600.00,
      "created_by": { "id": 7, "name": "John Dela Cruz" },
      "items": [
        {
          "product_id": 42,
          "product_name": "Kawasaki Ninja Brake Pad",
          "quantity": 2,
          "unit_price": 300.00,
          "line_total": 600.00
        }
      ]
    }
  ],
  "filters": {
    "search": "",
    "status": null,
    "date_from": "2026-10-01",
    "date_to": "2026-10-28"
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 1 }
}
```

Transaction numbering, tax/discount/shipping fields, returns, cancellations and whether sales are entered manually or imported are **Needs confirmation**.

### Prediction — planned page

```json
{
  "latest_run": {
    "id": 12,
    "status": "completed",
    "model_name": "product_sales_regression",
    "model_version": "2026.10.1",
    "trained_at": "2026-10-27T23:00:00+08:00",
    "training_cutoff": "2026-10-27",
    "evaluation_metrics": { "mae": 4.2, "rmse": 6.8, "mape": 12.4 }
  },
  "predictions": [
    {
      "product_id": 42,
      "product_name": "Kawasaki Ninja Brake Pad",
      "forecast_date": "2026-11-01",
      "predicted_units": 14.5,
      "lower_bound": 10.0,
      "upper_bound": 19.0
    }
  ],
  "filters": {
    "product_id": null,
    "forecast_horizon_days": 30
  }
}
```

Model name/version, metrics, intervals, horizon, minimum history, product scope and stock recommendation are **Needs confirmation**. No regression model or prediction storage exists.

## 4. Integration approach

### Existing behavior

- `/` renders `Welcome`.
- `/dashboard` renders `Dashboard` through Inertia, but currently passes no props.
- `/prediction` renders `Prediction` through Inertia with no props; its product options and sample results are local preview data.
- No authenticated Admin routes, controllers or JSON endpoints exist.

### Proposed Inertia pattern

Use GET page routes that return an Inertia page and props:

```php
return Inertia::render('Dashboard', [
    'summary' => $summary,
    'sales_trend' => $salesTrend,
]);
```

Prefer server-side pagination/filtering for users, products and sales. Return validation failures using Laravel/Inertia standard errors rather than success-shaped fallback data.

### Proposed mutation pattern

Use named web routes and Inertia form submissions for browser Admin actions:

```text
GET    /admin/users              -> UserManagement
POST   /admin/users              -> create user
PATCH  /admin/users/{user}      -> update user
POST   /admin/users/{user}/deactivate
GET    /admin/products           -> Inventory/Product page
GET    /admin/sales              -> Sales page
GET    /admin/predictions        -> Prediction page
POST   /admin/predictions/runs   -> start a prediction run
```

All routes above are **Proposed**, not implemented. A queued prediction action may instead return a run ID and require polling or a refresh action; this is **Needs confirmation**.

## 5. UI actions, validation and error states

| UI control | Expected action | Required data / validation |
|---|---|---|
| Dashboard period selector | Reload/filter `sales_trend` and summary period | Valid supported period; formula for comparison period is **Needs confirmation** |
| Dashboard “Add product” | Navigate to product create flow | Destination and product fields are **Needs confirmation** |
| User search/filter | Reload users with query, role and status | Server-side pagination; allowed status/role values |
| User create/edit form | Submit user data | Required name/email; unique valid email; password-on-create rule; approved role |
| User deactivate action | Confirm then deactivate, not silently delete | Existing ownership/history behavior and confirmation copy are **Needs confirmation** |
| Product search/filter | Reload products by search/category/stock status | Valid category/status; pagination |
| Product form | Create/update catalog data | Required SKU/name/category/price; uniqueness, numeric and non-negative stock rules are **Proposed** |
| Stock adjustment | Record receipt/return/adjustment | Integer quantity, movement type, reason and actor; whether negative adjustments are allowed is **Needs confirmation** |
| Sales date/status filters | Reload transactions | Valid date range; allowed transaction statuses |
| Transaction detail | Show immutable line and amount snapshot | Return/refund semantics are **Needs confirmation** |
| Prediction input form | Submit product, model and forecast horizon | Current frontend preview validates required product/model fields and an integer horizon from 1 to 365 days; final limits and fields are **Needs confirmation** |
| Run/refresh prediction | Start or request a model run | Current frontend simulates a loading/result state locally; backend must expose `queued`, `running`, `completed`, `failed` and `insufficient_data` distinctly if those statuses are adopted |

Every page should support loading, empty, authorization-denied, validation-error and server-error states. These states do not currently exist in the frontend.

## 6. Roles

The repository does not currently define these roles or permissions. The matrix below is a **Proposed starting point based on the requested role names** and requires team confirmation.

| Role | Dashboard | Prediction | User management | Inventory/products | Sales |
|---|---|---|---|---|---|
| Admin | View; manage configuration if approved | View; run/refresh if approved | View and manage | View and manage | View and manage |
| Instructor | **Needs confirmation**: likely view only | **Needs confirmation** | No access unless assigned | No access unless assigned | No access unless assigned |
| Inventory Clerk | View inventory metrics; dashboard access **Needs confirmation** | View or no access **Needs confirmation** | No access | View, create/update stock; product edits **Needs confirmation** | No access or view-only **Needs confirmation** |
| Sales Personnel | View sales metrics; dashboard access **Needs confirmation** | No access or view-only **Needs confirmation** | No access | View product availability **Needs confirmation** | Create/view sales; returns **Needs confirmation** |

Backend authorization must enforce the final matrix server-side. Frontend navigation visibility is not a security boundary.

## 7. Outstanding integration decisions

Only the following decisions materially affect the frontend/backend contract:

1. What authentication flow protects Admin routes, and is email verification required?
2. Are Admin, Instructor, Inventory Clerk and Sales Personnel the complete role set?
3. Which role can access and mutate each page and action?
4. Are Product, Inventory and Sales separate pages or one combined operational page?
5. What are the product fields, SKU rules, categories, motorcycle compatibility fields and stock-location model?
6. How are low-stock, healthy-stock, out-of-stock, best-selling and slow-moving statuses calculated?
7. What exactly does “Total Sales” include: gross sales, net sales, tax, discounts, returns and refunds?
8. What currency, timezone, locale and reporting periods should the dashboard use?
9. Are transactions entered manually, imported, or integrated with another sales source?
10. Which regression model and input history/features are used for prediction?
11. What forecast horizon, granularity, output fields, evaluation metrics and minimum-history rule are required?
12. Are prediction runs synchronous or queued, and should runs/predictions be persisted?
13. What should the UI show for stale, failed or insufficient-data predictions?

## Immediate backend integration checklist

The backend developer can use immediately:

- Existing page/component paths and responsibilities in Section 1.
- `snake_case` contract naming and status vocabulary proposal in Section 2.
- Example Inertia prop shapes for each supported screen in Section 3.
- The distinction between current `/dashboard` behavior and proposed Admin routes in Section 4.
- UI action inputs and validation/error expectations in Section 5.

Team confirmation is still required for authentication, roles, domain fields, stock/sales formulas and the prediction contract before implementing controllers, migrations or persistence.

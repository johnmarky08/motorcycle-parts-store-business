# Final Frontend–Backend Integration Handoff

This document describes the current frontend preview and the backend work still required for the One Prompt Away motorcycle parts store system. It is an integration reference, not a claim that authentication, persistence, APIs, CSV ingestion, or machine-learning services already exist.

## 1. Architecture and source of truth

- **Stack:** Laravel 12, Inertia.js, Svelte 5, Vite 6 and Tailwind CSS 4.
- **Page resolution:** [app.js](../resources/js/app.js) eagerly resolves `./Pages/**/*.svelte` and renders `pages[\`./Pages/${name}.svelte\`]`. Page components must remain directly under `resources/js/Pages/`.
- **Routes:** [routes/web.php](../routes/web.php) currently uses temporary closure routes and `Inertia::render()`:

  | Route | Inertia page |
  |---|---|
  | `/` | `Welcome` |
  | `/login` | `Login` |
  | `/dashboard` | `Dashboard` |
  | `/user-management` | `UserManagement` |
  | `/prediction` | `Prediction` |
  | `/inventory` | `InventoryManagement` |
  | `/sales` | `SalesManagement` |
  | `/knowledge-analysis` | `KnowledgeAnalysis` |

- **Styling:** [app.css](../resources/css/app.css) imports Tailwind CSS 4 and Inter. The approved Dashboard baseline is the light slate/white interface from commit `318d613`.
- **Shared components:**
  - [AppHeader.svelte](../resources/js/Components/Layout/AppHeader.svelte): page title, preview-role selector, notification bell and mock notification panel.
  - [AppSidebar.svelte](../resources/js/Components/Layout/AppSidebar.svelte): branding, role-specific preview navigation, active state and responsive mobile drawer.
  - [StatCard.svelte](../resources/js/Components/Shared/StatCard.svelte): reusable KPI card with `label`, `value`, `change`, `trend` and `icon`.
  - [previewRoles.js](../resources/js/Components/Config/previewRoles.js): centralized preview roles, navigation, localStorage key and role-change event.

All domain pages currently use local mock state. Backend props and mutations should be introduced at the Inertia page boundary without changing the existing page names or visual component contracts unnecessarily.

## 2. Preview roles

The header selector is a **frontend testing mechanism**, not login, authentication or authorization. It defaults to `admin`, stores only the selected role key in `localStorage` under `motorcycle-parts-preview-role`, and dispatches a local `preview-role-change` event.

Current proposed preview menus:

| Preview role | Visible menu |
|---|---|
| Admin | Dashboard, User Management, Prediction |
| Instructor | Dashboard, Knowledge Analysis |
| Inventory Clerk | Dashboard, Inventory Management |
| Sales Personnel | Dashboard, Sales Management |

The direct routes remain available regardless of the selected menu. Server-side authorization must enforce the final policy.

## 3. Page inventory and integration needs

| Page and route | Intended role | Current preview UI | Backend work eventually required | Status |
|---|---|---|---|---|
| [Login.svelte](../resources/js/Pages/Login.svelte) · `/login` | All assigned users | Email, password visibility toggle, remember-me visual control, required/email validation, loading and invalid-credentials preview state. No role selector. | Authentication endpoint/session, identity field decision, password verification, rate limiting, reset and verification flows, redirect and `user_level`. | Implemented preview |
| [Dashboard.svelte](../resources/js/Pages/Dashboard.svelte) · `/dashboard` | All roles, with role-specific content | Admin baseline KPIs, sales chart, inventory health, best sellers and slow movers. Other previews show role-focused metrics and activity. | Summary statistics, trend series, inventory health, best sellers, slow movers and role-aware props. | Implemented preview |
| [UserManagement.svelte](../resources/js/Pages/UserManagement.svelte) · `/user-management` | Admin | Search, role/status filters, user table, add/edit form, activate/deactivate local actions and validation. | Paginated listing, create/update/deactivate actions, unique email validation, role permissions and audit behavior. | Implemented preview |
| [Prediction.svelte](../resources/js/Pages/Prediction.svelte) · `/prediction` | Admin preview; access for other roles needs confirmation | Four workflows: product demand, sales, slow-moving products and best-selling products. Includes inputs, validation, loading, sample result, failure, insufficient-data and reset states. | Prediction request/run lifecycle, model execution, result schema, errors, minimum history and permissions. | Implemented preview |
| [InventoryManagement.svelte](../resources/js/Pages/InventoryManagement.svelte) · `/inventory` | Inventory Clerk preview; Admin access needs confirmation | Search, category/status filters, derived summaries, add/edit product, SKU validation, stock receipt/reduction and confirmation. | Product/catalog persistence, stock movements, concurrency-safe stock validation and permissions. | Implemented preview |
| [SalesManagement.svelte](../resources/js/Pages/SalesManagement.svelte) · `/sales` | Sales Personnel preview; Admin access needs confirmation | Transaction history/search/filters, details, new-sale product search, cart, quantity/stock validation, confirmation and local stock reduction. | Transaction persistence, stock reservation/validation, payment handling, totals, actor and transaction status. | Implemented preview |
| [KnowledgeAnalysis.svelte](../resources/js/Pages/KnowledgeAnalysis.svelte) · `/knowledge-analysis` | Instructor preview | Dataset selector, metadata, sample rows/schema, search, quality summaries, illustrative analysis, training configuration, simulated states, MAE/RMSE/MAPE explanations and saved-model selection. | CSV ingestion/validation, dataset persistence, training jobs, metrics, model storage/loading and permissions. | Implemented preview |

## 4. Frontend field contracts

These are names currently used by the UI. A label identifies whether a field is already used by the frontend, proposed for backend integration, or unresolved.

### 4.1 Users

Current mock user objects use:

```js
{
  id: 1,                         // Existing frontend field; backend ID is proposed.
  name: "John Dela Cruz",       // Existing frontend field.
  email: "john@example.com",    // Existing frontend field.
  role: "admin",                // Existing frontend field; proposed backend name/value.
  status: "active",              // Existing frontend field; proposed status vocabulary.
  last_login_at: "Today, 9:42 AM" // Existing frontend display field; backend timestamp needs confirmation.
}
```

The form submits local `name`, `email`, `role` and `status`. Roles currently used are `admin`, `instructor`, `inventory_clerk` and `sales_personnel`. The backend’s assigned `user_level` is **Needs confirmation**: the Login page intentionally does not allow users to choose it. Whether the backend calls the persisted field `user_level`, `role`, or another field is **Needs confirmation**.

### 4.2 Products and inventory

Current product objects use:

```js
{
  id: 1,                         // Existing frontend field; backend ID proposed.
  name: "Kawasaki Ninja Brake Pad", // Existing frontend field.
  sku: "BP-KN-004",              // Existing frontend field; product-code naming needs confirmation.
  category: "Brake System",      // Existing frontend field; category relation is proposed.
  description: "...",            // Existing frontend field.
  selling_price: 1250,           // Existing frontend field; numeric decimal backend value proposed.
  stock_quantity: 128,           // Existing frontend field.
  reorder_threshold: 10          // Existing frontend field; preview rule, not confirmed policy.
}
```

The current UI derives `stockStatus` as:

- `out_of_stock` when `stock_quantity === 0`
- `low_stock` when quantity is less than or equal to `reorder_threshold`
- `in_stock` otherwise

The default preview threshold is 10 units. This formula, category structure, SKU uniqueness rules, cost price exposure, stock location, compatibility fields and movement model are **Needs confirmation**.

### 4.3 Sales

Current transaction objects use:

```js
{
  id: 9001,                                      // Existing frontend field; backend ID proposed.
  transaction_number: "TX-20261028-0001",       // Existing frontend field.
  transaction_date: "2026-10-28T09:30:00+08:00",// Existing frontend field.
  status: "completed",                           // Existing frontend field; final statuses need confirmation.
  payment_method: "Cash",                       // Existing frontend field; supported preview labels are Cash, GCash, Card, Bank Transfer.
  items: [{
    product_id: 1,                              // Existing frontend field.
    product_name: "Kawasaki Ninja Brake Pad",   // Existing snapshot field.
    sku: "BP-KN-004",                           // Existing preview cart field.
    quantity: 2,                               // Existing frontend field.
    unit_price: 1250,                          // Existing frontend field.
    line_total: 2500                           // Existing frontend derived field.
  }]
}
```

The UI derives `transactionTotal` from `items[].line_total`, total sales from transaction totals, transaction count from records, and average transaction value as total sales divided by transaction count. The preview intentionally excludes taxes, discounts, shipping, refunds, returns, voids and cancellations. Their inclusion, status vocabulary, payment integration and gross/net meaning are **Needs confirmation**.

### 4.4 Prediction

The Prediction page currently has four workflow keys: `demand`, `sales`, `slow` and `best`.

Shared preview model values:

- `simple_linear_regression`
- `random_forest_regression`

Product demand inputs use `productId`, `modelName` and integer `horizon` from 1–365 days. Its sample output uses `predicted_units`, lower/upper illustrative ranges, forecast date and model label.

Sales inputs use `historicalPeriod`, `horizon` and model selection. The sample output is a predicted sales total and trend.

Slow-moving inputs use `scope` and `historicalPeriod`. The sample output contains product name, SKU, predicted movement quantity and a risk label.

Best-selling inputs use `category` and forecast period. The sample output contains ranked product name, SKU, predicted units and illustrative sales value.

These fields are **Existing frontend fields**. Prediction request names, target variables, forecast granularity, result intervals, ranking definitions, minimum history, model version and persistence are **Needs confirmation**. No real model inference occurs.

### 4.5 Knowledge Analysis datasets and models

Current mock dataset objects use:

```js
{
  id: "sales-history",                         // Existing frontend field.
  name: "Motorcycle Sales History",             // Existing frontend field.
  description: "...",                          // Existing frontend field.
  records: 2486,                               // Existing mock metadata.
  columns: 8,                                  // Existing mock metadata.
  coverage: "Jan 1, 2025 – Oct 28, 2026",     // Existing display field.
  status: "validated",                         // Existing preview status.
  missing: 12,                                 // Existing mock quality summary.
  duplicates: 3,                               // Existing mock quality summary.
  target: "total_amount",                      // Existing preview target.
  features: ["transaction_date", "product_id", "quantity", "payment_method"],
  rows: [/* representative sample only */]
}
```

The product-demand preview uses `id: "product-demand"`, `records: 1864`, `columns: 7`, target `units_sold`, and representative features such as `date`, `product_id`, `category` and `stock_quantity`. Both record counts are illustrative metadata; no CSV has been uploaded or processed. The assignment requirement of at least 1,000 CSV records per required dataset is a proposed validation requirement that the backend must enforce.

Training configuration currently uses `datasetId`, `targetVariable`, `featureSelection`, `split` (default `"80/20"`), `horizon` and `modelType`. Training statuses shown by the UI are `ready`, `running`, `completed`, `failed` and `insufficient_data`.

Evaluation cards use `mae`, `rmse` and `mape`. The values and descriptions are mock examples. The saved-model preview uses `id`, `name`, `type`, `version`, `dataset`, `date`, `status`, `mae`, `rmse` and `mape`. Dataset schemas, target/feature compatibility, MAPE zero handling, model versioning, storage and loading are **Needs confirmation**.

## 5. Shared notification contract

The header currently displays local notification records shaped as:

```js
{
  id: 1,
  type: "stock",              // Existing preview value: stock, critical or sales.
  title: "Low-stock products need attention",
  description: "...",
  timestamp: "12 minutes ago",
  read: false
}
```

The UI supports unread count, opening/closing, mark-one-read, mark-all-read, outside-click and Escape dismissal. Backend notification persistence, event types, timestamps, pagination and read-state mutation are **Proposed** and need confirmation.

## 6. State and validation behavior

Current frontend preview behavior:

- **Login:** required email/password, email format, transient password visibility, loading and explicit invalid-credentials preview state. It never authenticates or stores passwords.
- **Users:** required name/email/role, email format, search, role/status filters, local add/edit and activate/deactivate.
- **Inventory:** required name/SKU/category/price/quantity/threshold, non-negative numeric values, duplicate SKU detection, search/category/status filters, positive stock adjustments, required adjustment note, reduction confirmation and negative-stock prevention.
- **Sales:** empty-cart validation, positive integer quantities, available-stock ceiling, derived line/transaction totals, payment selection, confirmation and local transaction/stock update.
- **Prediction:** workflow-specific required inputs, 1–365-day forecast validation where applicable, loading, sample result, failure, insufficient-data and reset states.
- **Knowledge Analysis:** dataset switching, preview-row search, invalid-file preview, empty-row state, feature/target/horizon validation, simulated training progress/completion/failure/insufficient-data states and saved-model selection.
- **Shared layouts:** responsive table containment, mobile navigation drawer, active navigation indicators, notifications, labels and keyboard-accessible controls.

These are client-side preview checks only. Backend validation must be authoritative and should return structured Inertia validation errors and distinct authorization, server-error, queued/running, failed and insufficient-data responses.

## 7. Proposed backend integration approach

No API or mutation route below currently exists. These are integration suggestions that follow the current Laravel/Inertia architecture:

1. Replace temporary closure routes with authenticated Laravel routes once the auth flow is selected.
2. Return page props using the existing page names, for example:

```php
return Inertia::render('InventoryManagement', [
    'products' => $products,
    'categories' => $categories,
    'filters' => $filters,
    'pagination' => $pagination,
]);
```

3. Use conventional Laravel form actions or Inertia form submissions for:
   - User listing/create/update/status changes.
   - Product listing/create/update and stock adjustments.
   - Sales listing/detail and sale completion.
   - Prediction request/run status/result.
   - Dataset upload/validation, training request/status, saved-model selection and prediction.
4. Preserve numeric values as numbers and timestamps as ISO-8601 values in backend props. Format currency and dates in the Svelte view.
5. Return server-side pagination/filter results for users, products and sales rather than relying on local arrays.

## 8. Machine-learning requirements and boundaries

The frontend exposes the required model choices **Simple Linear Regression** and **Random Forest Regression**. It exposes four prediction use cases: product demand, sales, slow-moving products and best-selling products. Knowledge Analysis exposes dataset preview, feature/target selection, illustrative 80/20 split, forecast horizon, training status, evaluation metrics and saved-model selection.

The assignment requires at least 1,000 CSV records per required dataset. The frontend displays mock metadata above that threshold and provides an explicit insufficient-data preview state, but it does not ingest files or count actual rows.

MAE, RMSE and MAPE are displayed where applicable with plain-language explanations. Their formulas, unit normalization, zero-value behavior, minimum data history, training architecture, queueing, evaluation split, model persistence, loading semantics and prediction freshness are **Needs confirmation**.

## 9. Outstanding team decisions

Prioritized decisions that affect implementation:

1. Authentication identity (email, username or both), session/remember-me behavior, verification and reset flow.
2. Final role values and server-side permissions for each page/action.
3. Product fields, category relationships, SKU uniqueness, stock location and reorder/stock-status rules.
4. Sales totals, currency/timezone, payment processing, transaction statuses and return/refund behavior.
5. Dataset CSV schemas, required columns, date granularity, missing/duplicate handling and the 1,000-record rule.
6. Prediction targets, features, history requirements, horizons, ranking definitions and output fields for all four workflows.
7. Training execution model, queued versus synchronous statuses, evaluation formulas, MAPE handling, model versioning and persistence/loading.
8. Backend error, authorization-denied, stale-data and insufficient-data response shapes.

## 10. Backend implementation checklist

1. Select and implement authentication, session handling and server-side role authorization.
2. Define migrations, entities, relationships and stable status/value vocabularies.
3. Implement product/catalog reads, product mutations and stock adjustment consistency.
4. Implement sale creation, transaction history/detail, stock validation and payment/status handling.
5. Supply Dashboard summary, chart, inventory health, best-seller and slow-mover props.
6. Implement dataset upload, CSV validation, metadata, quality checks and preview data.
7. Implement model training requests, status lifecycle, evaluation metrics and persisted model records/files.
8. Implement the four prediction actions and result/error/insufficient-data responses.
9. Connect the existing Inertia pages to real props and form actions while retaining current field mapping and visual states.
10. Add authoritative validation, authorization-denied handling, server-error handling, pagination and automated backend tests.

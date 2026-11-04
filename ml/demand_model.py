"""Product demand forecasting (weekly units per product).

Used for the Product Demand, Slow-Moving and Best-Selling workflows.
  * Simple Linear Regression: one linear trend per product (single predictor: week index),
    with an optional month-of-year seasonal index.
  * Random Forest Regression: one global model across products using lag/rolling/calendar features.
"""
from __future__ import annotations

from datetime import timedelta

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.model_selection import RandomizedSearchCV, TimeSeriesSplit

from common import MIN_DATASET_ROWS, MlError, regression_metrics

REQUIRED = ["week_start", "product_id", "category", "units_sold"]
FEATURES = ["t", "woy", "month", "product_code", "category_code", "price",
            "lag1", "lag2", "lag3", "lag4", "roll4", "roll8"]
EVAL_HORIZON = 4      # weeks forecast ahead at every evaluation origin
MIN_LAG = 8
MIN_WEEKS = 26


def load_panel(path):
    df = pd.read_csv(path)
    missing = [c for c in REQUIRED if c not in df.columns]
    if missing:
        raise MlError("invalid_dataset", "Missing required columns: " + ", ".join(missing))
    rows = len(df)
    if rows < MIN_DATASET_ROWS:
        raise MlError("insufficient_data", f"The dataset has {rows} rows; at least {MIN_DATASET_ROWS} are required.")
    df["week_start"] = pd.to_datetime(df["week_start"], errors="coerce")
    df["units_sold"] = pd.to_numeric(df["units_sold"], errors="coerce")
    if "avg_unit_price" not in df.columns:
        df["avg_unit_price"] = 0.0
    df["avg_unit_price"] = pd.to_numeric(df["avg_unit_price"], errors="coerce").fillna(0.0)
    df["product_id"] = pd.to_numeric(df["product_id"], errors="coerce")
    df = df.dropna(subset=["week_start", "units_sold", "product_id"])
    df = df[df["units_sold"] >= 0]
    df["product_id"] = df["product_id"].astype(int)
    for col in ("sku", "product_name"):
        if col not in df.columns:
            df[col] = df["product_id"].astype(str)
    weeks = pd.date_range(df["week_start"].min(), df["week_start"].max(), freq="7D")
    if len(weeks) < MIN_WEEKS:
        raise MlError("insufficient_data", f"Only {len(weeks)} weeks of history; at least {MIN_WEEKS} are required.")
    meta = (df.sort_values("week_start").groupby("product_id")
            .agg(sku=("sku", "last"), name=("product_name", "last"), category=("category", "last"),
                 price=("avg_unit_price", "last")).reset_index())
    pivot = (df.pivot_table(index="week_start", columns="product_id", values="units_sold", aggfunc="sum")
             .reindex(weeks).fillna(0.0))
    return pivot, meta, rows


def features_for(week: pd.Timestamp, t: int, hist: np.ndarray, code: int, cat_code: int, price: float) -> list[float]:
    def lag(n):
        return hist[-n] if len(hist) >= n else hist[0]
    return [t, int(week.isocalendar().week), week.month, code, cat_code, price,
            lag(1), lag(2), lag(3), lag(4), float(np.mean(hist[-4:])), float(np.mean(hist[-8:]))]


def _codes(meta: pd.DataFrame):
    pcode = {int(p): i for i, p in enumerate(sorted(meta["product_id"]))}
    cats = sorted(meta["category"].astype(str).unique())
    ccode = {c: i for i, c in enumerate(cats)}
    info = {int(r.product_id): dict(sku=str(r.sku), name=str(r["name"]), category=str(r.category), price=float(r.price),
                                    code=pcode[int(r.product_id)], cat_code=ccode[str(r.category)])
            for _, r in meta.iterrows()}
    return info


def training_matrix(pivot: pd.DataFrame, info: dict, start: int, end: int):
    X, y = [], []
    cols = list(pivot.columns)
    for pid in cols:
        vals = pivot[pid].values
        meta = info[int(pid)]
        for i in range(max(start, MIN_LAG), end):
            X.append(features_for(pivot.index[i], i, vals[:i], meta["code"], meta["cat_code"], meta["price"]))
            y.append(vals[i])
    return np.array(X, dtype=float), np.array(y, dtype=float)


def _rf(params, seed):
    base = dict(n_estimators=300, min_samples_leaf=2, max_features=0.7, max_depth=None, random_state=seed, n_jobs=1)
    base.update(params)
    return RandomForestRegressor(**base)


def _tune(X, y, seed):
    grid = {"n_estimators": [200, 300, 400], "max_depth": [6, 10, 14, None],
            "min_samples_leaf": [1, 2, 4, 8], "max_features": [0.4, 0.6, 0.8, 1.0]}
    search = RandomizedSearchCV(_rf({}, seed), grid, n_iter=8, cv=TimeSeriesSplit(n_splits=3),
                                scoring="neg_mean_absolute_error", random_state=seed, n_jobs=1)
    search.fit(X, y)
    return dict(search.best_params_)


# ---- Simple Linear Regression ------------------------------------------------
def fit_slr(pivot: pd.DataFrame, end: int, seasonal: bool) -> dict:
    t = np.arange(end).reshape(-1, 1)
    coefs, fitted = {}, {}
    for pid in pivot.columns:
        y = pivot[pid].values[:end]
        lr = LinearRegression().fit(t, y)
        coefs[int(pid)] = (float(lr.intercept_), float(lr.coef_[0]))
        fitted[int(pid)] = np.clip(lr.predict(t), 0, None)
    month_idx = [1.0] * 12
    if seasonal:
        months = pivot.index[:end].month
        for m in range(1, 13):
            mask = months == m
            if mask.any():
                actual = sum(pivot[pid].values[:end][mask].sum() for pid in pivot.columns)
                est = sum(fitted[int(pid)][mask].sum() for pid in pivot.columns)
                month_idx[m - 1] = float(actual / est) if est > 0 else 1.0
    return {"coefs": coefs, "month_index": month_idx, "seasonal": seasonal}


def slr_forecast(model: dict, pid: int, weeks: list[pd.Timestamp], ts: list[int]) -> list[float]:
    a, b = model["coefs"].get(int(pid), (0.0, 0.0))
    return [max(0.0, (a + b * t) * model["month_index"][w.month - 1]) for w, t in zip(weeks, ts)]


# ---- Random Forest recursive forecast (all products at once) --------------------
def rf_forecast(model, pivot_hist: dict[int, np.ndarray], info: dict, last_week: pd.Timestamp, start_t: int, steps: int):
    hist = {pid: np.array(v, dtype=float) for pid, v in pivot_hist.items()}
    out = {pid: [] for pid in hist}
    pids = list(hist)
    for k in range(steps):
        week = last_week + timedelta(days=7 * (k + 1))
        X = np.array([features_for(week, start_t + k, hist[pid], info[pid]["code"], info[pid]["cat_code"],
                                   info[pid]["price"]) for pid in pids], dtype=float)
        preds = np.clip(model.predict(X), 0, None)
        for pid, p in zip(pids, preds):
            out[pid].append(float(p))
            hist[pid] = np.append(hist[pid], p)
    return out


def evaluate(pivot, info, split_idx, algorithm, model, slr_model=None):
    actual, predicted, per_product = [], [], {}
    origin = split_idx
    n = len(pivot)
    while origin < n:
        steps = min(EVAL_HORIZON, n - origin)
        last_week = pivot.index[origin - 1]
        if algorithm == "simple_linear_regression":
            fc = {int(pid): slr_forecast(slr_model, pid, [last_week + timedelta(days=7 * (k + 1)) for k in range(steps)],
                                         [origin + k for k in range(steps)]) for pid in pivot.columns}
        else:
            fc = rf_forecast(model, {int(pid): pivot[pid].values[:origin] for pid in pivot.columns}, info,
                             last_week, origin, steps)
        for pid in pivot.columns:
            a = pivot[pid].values[origin:origin + steps].tolist()
            p = fc[int(pid)]
            actual.extend(a)
            predicted.extend(p)
            per_product.setdefault(int(pid), []).extend((np.array(p) - np.array(a)).tolist())
        origin += steps
    return regression_metrics(actual, predicted), per_product


def train(path, algorithm, split, tune, seasonal, seed):
    pivot, meta, rows = load_panel(path)
    info = _codes(meta)
    n = len(pivot)
    split_idx = max(MIN_LAG + 12, int(n * split))
    if n - split_idx < 8:
        raise MlError("insufficient_data", "Not enough weeks left for the test split. Use a smaller training share.")

    params, notes = {}, []
    if algorithm == "simple_linear_regression":
        slr_eval = fit_slr(pivot, split_idx, seasonal)
        metrics, residuals = evaluate(pivot, info, split_idx, algorithm, None, slr_eval)
        final_model = fit_slr(pivot, n, seasonal)
        notes.append("One linear trend is fitted per product (single predictor: week index).")
        if seasonal:
            notes.append("Month-of-year seasonal index applied on top of each trend.")
    else:
        X_tr, y_tr = training_matrix(pivot, info, 0, split_idx)
        if tune:
            params = _tune(X_tr, y_tr, seed)
            notes.append("Hyperparameters tuned with time-series cross-validation.")
        eval_model = _rf(params, seed).fit(X_tr, y_tr)
        metrics, residuals = evaluate(pivot, info, split_idx, algorithm, eval_model)
        X_all, y_all = training_matrix(pivot, info, 0, n)
        final_model = _rf(params, seed).fit(X_all, y_all)
        notes.append("Final model refit on all data after evaluation.")

    all_res = [r for v in residuals.values() for r in v]
    bundle = {
        "model": final_model, "info": info, "first_week": pivot.index[0].strftime("%Y-%m-%d"),
        "last_week": pivot.index[-1].strftime("%Y-%m-%d"),
        "history": {int(pid): pivot[pid].values.tolist() for pid in pivot.columns},
        "resid_std": float(np.std(all_res)) if all_res else 0.0,
        "resid_std_product": {pid: float(np.std(v)) for pid, v in residuals.items()},
    }
    info_out = {
        "rows": rows, "points": n, "train_points": split_idx, "test_points": n - split_idx,
        "features": FEATURES if algorithm != "simple_linear_regression" else ["t (week index)"],
        "target": "weekly units_sold per product", "params": params, "notes": notes,
        "eval_protocol": f"Rolling {EVAL_HORIZON}-week-ahead forecasts across the hold-out period, pooled over all products.",
        "trained_through": bundle["last_week"], "granularity": "weekly", "products": len(info),
    }
    return bundle, {"metrics": metrics, **info_out}


def forecast_all(bundle: dict, algorithm: str, weeks: int) -> dict[int, list[float]]:
    pivot_hist = {int(p): np.array(v) for p, v in bundle["history"].items()}
    last = pd.Timestamp(bundle["last_week"])
    n = len(next(iter(pivot_hist.values())))
    if algorithm == "simple_linear_regression":
        wk = [last + timedelta(days=7 * (k + 1)) for k in range(weeks)]
        return {pid: slr_forecast(bundle["model"], pid, wk, [n + k for k in range(weeks)]) for pid in pivot_hist}
    return rf_forecast(bundle["model"], pivot_hist, bundle["info"], last, n, weeks)


def units_over_days(weekly: list[float], days: int) -> float:
    total = 0.0
    for i, v in enumerate(weekly):
        total += v * min(1.0, max(0.0, days / 7 - i))
    return total


def predict_demand(bundle, algorithm, product_id: int, horizon_days: int, stock: int | None) -> dict:
    pid = int(product_id)
    if pid not in bundle["info"]:
        raise MlError("unknown_product", "This product was not part of the model's training data. Retrain the model.")
    weeks = int(np.ceil(horizon_days / 7))
    fc = forecast_all(bundle, algorithm, weeks)[pid]
    total = units_over_days(fc, horizon_days)
    sigma = bundle["resid_std_product"].get(pid, bundle["resid_std"]) * np.sqrt(weeks) * 1.28
    last = pd.Timestamp(bundle["last_week"])
    hist = bundle["history"][pid]
    hist_weeks = [last - timedelta(days=7 * i) for i in range(min(12, len(hist)) - 1, -1, -1)]
    recent = [{"label": w.strftime("%b %d"), "value": float(v)} for w, v in zip(hist_weeks, hist[-12:])]
    forecast = [{"label": (last + timedelta(days=7 * (k + 1))).strftime("%b %d"), "value": round(v, 2)}
                for k, v in enumerate(fc)]
    result = {
        "product": {"id": pid, **{k: bundle["info"][pid][k] for k in ("sku", "name", "category")}},
        "predicted_units": round(total, 1), "lower": round(max(0.0, total - sigma), 1),
        "upper": round(total + sigma, 1), "weekly_average": round(total / (horizon_days / 7), 2),
        "recent_weekly": recent, "forecast_weekly": forecast,
        "forecast_start": (last + timedelta(days=7)).strftime("%Y-%m-%d"),
    }
    if stock is not None:
        result["current_stock"] = int(stock)
        result["suggested_restock"] = int(max(0, np.ceil(result["upper"] - stock)))
    return result


def _percentile_rank(values: dict[int, float]) -> dict[int, float]:
    ordered = sorted(values.values())
    n = len(ordered)
    return {k: (sum(1 for x in ordered if x < v) + 0.5 * sum(1 for x in ordered if x == v)) / n for k, v in values.items()}


def predict_slow(bundle, algorithm, products: list[dict], history_months: int, limit: int = 15) -> dict:
    fc = forecast_all(bundle, algorithm, 5)
    pred30 = {pid: units_over_days(v, 30) for pid, v in fc.items()}
    rank = _percentile_rank(pred30)
    weeks_back = max(4, int(round(history_months * 4.345)))
    items = []
    for p in products:
        pid = int(p["id"])
        if pid not in pred30 or int(p["stock"]) <= 0:
            continue
        hist = bundle["history"][pid][-weeks_back:]
        hist_30 = float(np.mean(hist)) * 30 / 7 if hist else 0.0
        daily = pred30[pid] / 30
        cover = (p["stock"] / daily) if daily > 0.001 else 999.0
        pct = rank[pid]
        if pct <= 0.20 and cover >= 90:
            risk = "high"
        elif pct <= 0.40 and cover >= 60:
            risk = "medium"
        elif pct <= 0.40 or cover >= 120:
            risk = "low"
        else:
            continue
        items.append({"id": pid, "sku": p["sku"], "name": p["name"], "category": p["category"], "stock": int(p["stock"]),
                      "predicted_units_30d": round(pred30[pid], 1), "historical_units_30d": round(hist_30, 1),
                      "cover_days": round(min(cover, 999.0), 0), "velocity_percentile": round(pct * 100, 0), "risk": risk})
    order = {"high": 0, "medium": 1, "low": 2}
    items.sort(key=lambda x: (order[x["risk"]], -x["cover_days"]))
    return {"items": items[:limit], "flagged_total": len(items), "products_evaluated": len(products),
            "rules": "High: bottom 20% predicted velocity and 90+ days of stock cover. Medium: bottom 40% and 60+ days. Low: bottom 40% or 120+ days of cover."}


def predict_best(bundle, algorithm, products: list[dict], period_days: int, limit: int = 10) -> dict:
    weeks = int(np.ceil(period_days / 7))
    fc = forecast_all(bundle, algorithm, weeks)
    rows = []
    for p in products:
        pid = int(p["id"])
        if pid not in fc:
            continue
        units = units_over_days(fc[pid], period_days)
        rows.append({"id": pid, "sku": p["sku"], "name": p["name"], "category": p["category"],
                     "predicted_units": round(units, 1), "predicted_revenue": round(units * float(p["price"]), 2),
                     "stock": int(p["stock"])})
    rows.sort(key=lambda r: -r["predicted_units"])
    total = sum(r["predicted_units"] for r in rows) or 1.0
    for i, r in enumerate(rows, 1):
        r["rank"] = i
        r["share_percent"] = round(r["predicted_units"] / total * 100, 1)
    return {"items": rows[:limit], "products_evaluated": len(rows), "period_days": period_days}

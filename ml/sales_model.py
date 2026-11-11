"""Sales forecasting (weekly revenue): Simple Linear Regression and Random Forest Regression.

Transaction rows are aggregated to complete Monday-Sunday weeks. Weekly totals are far less noisy than
daily totals, which gives a more reliable forecast; requested horizons in days are pro-rated from weeks.
"""
from __future__ import annotations

from datetime import timedelta

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.model_selection import RandomizedSearchCV, TimeSeriesSplit

from common import MIN_DATASET_ROWS, MlError, regression_metrics

REQUIRED = ["transaction_number", "transaction_date", "quantity", "total_amount"]
FEATURES = ["t", "woy", "month", "paydays", "lag1", "lag2", "lag3", "lag4", "roll4", "roll8"]
EVAL_HORIZON = 4      # weeks forecast ahead at every evaluation origin
MIN_LAG = 8
MIN_WEEKS = 26
PAYDAYS = {14, 15, 16, 29, 30, 31}


def load_series(path) -> tuple[pd.Series, int]:
    df = pd.read_csv(path, usecols=lambda c: c in REQUIRED, dtype=str)
    missing = [c for c in REQUIRED if c not in df.columns]
    if missing:
        raise MlError("invalid_dataset", "Missing required columns: " + ", ".join(missing))
    rows = len(df)
    if rows < MIN_DATASET_ROWS:
        raise MlError("insufficient_data", f"The dataset has {rows} rows; at least {MIN_DATASET_ROWS} are required.")
    df["ts"] = pd.to_datetime(df["transaction_date"], errors="coerce")
    df["amount"] = pd.to_numeric(df["total_amount"], errors="coerce")
    df = df.dropna(subset=["ts", "amount"])
    df = df[df["amount"] >= 0]
    if df.empty:
        raise MlError("invalid_dataset", "No valid rows after cleaning dates and amounts.")
    daily = df.groupby(df["ts"].dt.normalize())["amount"].sum()
    full = pd.date_range(daily.index.min(), daily.index.max(), freq="D")
    daily = daily.reindex(full, fill_value=0.0)
    weekly = daily.resample("W-MON", label="left", closed="left").sum()
    counts = daily.resample("W-MON", label="left", closed="left").count()
    weekly = weekly[counts == 7]          # complete weeks only
    if len(weekly) < MIN_WEEKS:
        raise MlError("insufficient_data", f"Only {len(weekly)} complete weeks of history; at least {MIN_WEEKS} are required.")
    return weekly.astype(float), rows


def row_features(week: pd.Timestamp, t: int, hist: list[float]) -> list[float]:
    def lag(n):
        return hist[-n] if len(hist) >= n else hist[0]
    paydays = sum(1 for k in range(7) if (week + timedelta(days=k)).day in PAYDAYS)
    return [t, int(week.isocalendar().week), week.month, paydays, lag(1), lag(2), lag(3), lag(4),
            float(np.mean(hist[-4:])), float(np.mean(hist[-8:]))]


def training_matrix(series: pd.Series, start: int, end: int):
    values = series.values.tolist()
    X, y = [], []
    for i in range(max(start, MIN_LAG), end):
        X.append(row_features(series.index[i], i, values[:i]))
        y.append(values[i])
    return np.array(X, dtype=float), np.array(y, dtype=float)


def _rf(params: dict, seed: int) -> RandomForestRegressor:
    base = dict(n_estimators=300, min_samples_leaf=3, max_features=0.8, max_depth=None, random_state=seed, n_jobs=1)
    base.update(params)
    return RandomForestRegressor(**base)


def _tune(X, y, seed: int) -> dict:
    grid = {"n_estimators": [200, 300, 400], "max_depth": [3, 5, 8, None],
            "min_samples_leaf": [1, 2, 3, 5], "max_features": [0.4, 0.6, 0.8, 1.0]}
    search = RandomizedSearchCV(_rf({}, seed), grid, n_iter=8, cv=TimeSeriesSplit(n_splits=3),
                                scoring="neg_mean_absolute_error", random_state=seed, n_jobs=1)
    search.fit(X, y)
    return dict(search.best_params_)


# ---- Simple Linear Regression -------------------------------------------------
def fit_slr(series: pd.Series, end: int, seasonal: bool) -> dict:
    t = np.arange(end).reshape(-1, 1)
    y = series.values[:end]
    lr = LinearRegression().fit(t, y)
    trend = np.clip(lr.predict(t), 0, None)
    month_idx = [1.0] * 12
    if seasonal:
        months = series.index[:end].month
        for m in range(1, 13):
            mask = months == m
            denom = trend[mask].sum()
            if mask.any() and denom > 0:
                month_idx[m - 1] = float(y[mask].sum() / denom)
    return {"intercept": float(lr.intercept_), "slope": float(lr.coef_[0]), "month_index": month_idx, "seasonal": seasonal}


def slr_forecast(model: dict, weeks: list[pd.Timestamp], ts: list[int]) -> list[float]:
    return [max(0.0, (model["intercept"] + model["slope"] * t) * model["month_index"][w.month - 1]) for w, t in zip(weeks, ts)]


# ---- Random Forest (detrended) --------------------------------------------------
def fit_trend(series: pd.Series, end: int) -> tuple[float, float]:
    lr = LinearRegression().fit(np.arange(end).reshape(-1, 1), series.values[:end])
    return float(lr.intercept_), float(lr.coef_[0])


def trend_at(trend: tuple[float, float], t) -> np.ndarray:
    """Linear trend value(s), never below 1 so ratios stay finite."""
    return np.maximum(trend[0] + trend[1] * np.asarray(t, dtype=float), 1.0)


def detrend(series: pd.Series, trend: tuple[float, float]) -> pd.Series:
    return series / trend_at(trend, np.arange(len(series)))


# A Random Forest cannot extrapolate a growing level, so it models the ratio to a linear trend
# and the forecast is multiplied back by the trend ("hybrid trend + forest" improvement).
def rf_forecast(model, hist: list[float], last_week: pd.Timestamp, start_t: int, steps: int) -> list[float]:
    hist = list(hist)
    out = []
    for k in range(steps):
        week = last_week + timedelta(days=7 * (k + 1))
        pred = max(0.0, float(model.predict(np.array([row_features(week, start_t + k, hist)], dtype=float))[0]))
        out.append(pred)
        hist.append(pred)
    return out


def evaluate(series: pd.Series, split_idx: int, algorithm: str, model, slr_model=None, trend=None):
    values = series.values.tolist()
    det_values = detrend(series, trend).values.tolist() if trend else None
    actual, predicted = [], []
    origin = split_idx
    while origin < len(series):
        steps = min(EVAL_HORIZON, len(series) - origin)
        last_week = series.index[origin - 1]
        if algorithm == "simple_linear_regression":
            wk = [last_week + timedelta(days=7 * (k + 1)) for k in range(steps)]
            preds = slr_forecast(slr_model, wk, [origin + k for k in range(steps)])
        else:
            ratios = rf_forecast(model, det_values[:origin], last_week, origin, steps)
            preds = [float(r * trend_at(trend, origin + k)) for k, r in enumerate(ratios)]
        actual.extend(values[origin:origin + steps])
        predicted.extend(preds)
        origin += steps
    residuals = (np.array(predicted) - np.array(actual)).tolist()
    return regression_metrics(actual, predicted), residuals


def train(path, algorithm: str, split: float, tune: bool, seasonal: bool, seed: int):
    series, rows = load_series(path)
    n = len(series)
    split_idx = max(MIN_LAG + 12, int(n * split))
    if n - split_idx < 8:
        raise MlError("insufficient_data", "Not enough weeks left for the test split. Use a smaller training share.")

    params, notes = {}, []
    if algorithm == "simple_linear_regression":
        slr_eval = fit_slr(series, split_idx, seasonal)
        metrics, residuals = evaluate(series, split_idx, algorithm, None, slr_eval)
        final_model = fit_slr(series, n, seasonal)
        notes.append("A single linear trend on the week index (single predictor).")
        if seasonal:
            notes.append("Month-of-year seasonal index applied on top of the linear trend.")
    else:
        trend_eval = fit_trend(series, split_idx)
        X_tr, y_tr = training_matrix(detrend(series, trend_eval), 0, split_idx)
        if tune:
            params = _tune(X_tr, y_tr, seed)
            notes.append("Hyperparameters tuned with time-series cross-validation.")
        eval_model = _rf(params, seed).fit(X_tr, y_tr)
        metrics, residuals = evaluate(series, split_idx, algorithm, eval_model, trend=trend_eval)
        trend_final = fit_trend(series, n)
        final_model = _rf(params, seed).fit(*training_matrix(detrend(series, trend_final), 0, n))
        notes.append("Forest models the ratio to a linear trend (it cannot extrapolate growth by itself).")
        notes.append("Final model refit on all data after evaluation.")

    bundle = {
        "model": final_model, "history": series.values.tolist(),
        "trend": trend_final if algorithm != "simple_linear_regression" else None,
        "first_week": series.index[0].strftime("%Y-%m-%d"), "last_week": series.index[-1].strftime("%Y-%m-%d"),
        "resid_std": float(np.std(residuals)) if residuals else 0.0,
    }
    info = {
        "rows": rows, "points": n, "train_points": split_idx, "test_points": n - split_idx,
        "features": FEATURES if algorithm != "simple_linear_regression" else ["t (week index)"],
        "target": "weekly total_amount", "params": params, "notes": notes,
        "eval_protocol": f"Rolling {EVAL_HORIZON}-week-ahead forecasts across the hold-out period.",
        "trained_through": bundle["last_week"], "granularity": "weekly",
    }
    return bundle, {"metrics": metrics, **info}


def predict(bundle: dict, algorithm: str, horizon_days: int, history_months: int) -> dict:
    hist = bundle["history"]
    first = pd.Timestamp(bundle["first_week"])
    last = pd.Timestamp(bundle["last_week"])
    n = len(hist)
    weeks = int(np.ceil(horizon_days / 7))
    week_starts = [last + timedelta(days=7 * (k + 1)) for k in range(weeks)]
    if algorithm == "simple_linear_regression":
        preds = slr_forecast(bundle["model"], week_starts, [n + k for k in range(weeks)])
    else:
        trend = tuple(bundle["trend"])
        det_hist = (np.array(hist) / trend_at(trend, np.arange(n))).tolist()
        ratios = rf_forecast(bundle["model"], det_hist, last, n, weeks)
        preds = [float(r * trend_at(trend, n + k)) for k, r in enumerate(ratios)]

    weights = [min(1.0, max(0.0, horizon_days / 7 - k)) for k in range(weeks)]
    total = float(sum(p * w for p, w in zip(preds, weights)))
    sigma = bundle["resid_std"] * np.sqrt(horizon_days / 7) * 1.28
    prev_weeks = max(1, int(round(horizon_days / 7)))
    prev = hist[-prev_weeks:]
    prev_total = float(sum(prev)) * (horizon_days / 7) / prev_weeks
    change = ((total - prev_total) / prev_total * 100) if prev_total > 0 else None

    hist_weeks = max(4, int(round(history_months * 4.345)))
    hist_idx = [first + timedelta(days=7 * i) for i in range(n)]
    history = [{"label": d.strftime("%b %d"), "value": round(float(v), 2)} for d, v in zip(hist_idx[-hist_weeks:], hist[-hist_weeks:])]
    forecast = [{"label": d.strftime("%b %d"), "value": round(p, 2)} for d, p in zip(week_starts, preds)]
    return {
        "predicted_total": round(total, 2), "lower": round(max(0.0, total - sigma), 2), "upper": round(total + sigma, 2),
        "daily_average": round(total / horizon_days, 2), "previous_period_total": round(prev_total, 2),
        "change_percent": None if change is None else round(change, 2),
        "forecast_start": week_starts[0].strftime("%Y-%m-%d"),
        "forecast_end": (week_starts[0] + timedelta(days=horizon_days - 1)).strftime("%Y-%m-%d"),
        "history_weekly": history, "forecast_weekly": forecast,
    }


def combine_sales(results: list[dict]) -> dict:
    """Average of several single-model sales results."""
    first = results[0]
    mean = lambda key: float(np.mean([r[key] for r in results]))
    total = mean("predicted_total")
    prev = first["previous_period_total"]
    return {
        "predicted_total": round(total, 2), "lower": round(mean("lower"), 2), "upper": round(mean("upper"), 2),
        "daily_average": round(mean("daily_average"), 2), "previous_period_total": prev,
        "change_percent": None if prev <= 0 else round((total - prev) / prev * 100, 2),
        "forecast_start": first["forecast_start"], "forecast_end": first["forecast_end"], "history_weekly": first["history_weekly"],
        "forecast_weekly": [{"label": first["forecast_weekly"][k]["label"], "value": round(float(np.mean([r["forecast_weekly"][k]["value"] for r in results])), 2)}
                            for k in range(len(first["forecast_weekly"]))],
    }

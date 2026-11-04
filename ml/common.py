"""Shared helpers for the One Prompt Away ML package.

Design notes (improvements over per-request training):
  * Models are trained once, saved to disk and only *loaded* for predictions.
  * Python never touches the database or its credentials: it reads CSV files and
    JSON passed in by Laravel.
  * Every file path must resolve inside the base directory given by Laravel.
  * Saved model files are verified against a SHA-256 hash stored in the database
    before they are loaded (joblib uses pickle, so a tampered file must never load).
"""
from __future__ import annotations

import hashlib
import math
import os
from pathlib import Path
from typing import Any

import joblib
import numpy as np

BUNDLE_VERSION = 1
MIN_DATASET_ROWS = 1000


class MlError(Exception):
    """Expected, user-presentable failure."""

    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code
        self.message = message


def safe_path(path: str, base_dir: str) -> Path:
    base = Path(os.path.realpath(base_dir))
    target = Path(os.path.realpath(path))
    if base != target and base not in target.parents:
        raise MlError("invalid_path", "File path is outside the allowed storage directory.")
    return target


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def save_bundle(bundle: dict, path: Path) -> str:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    joblib.dump(bundle, tmp, compress=3)
    os.replace(tmp, path)
    return sha256_file(path)


def load_bundle(path: str, base_dir: str, expected_sha256: str | None) -> dict:
    target = safe_path(path, base_dir)
    if not target.is_file():
        raise MlError("model_missing", "The saved model file could not be found. Train the model again.")
    if not expected_sha256 or sha256_file(target) != expected_sha256:
        raise MlError("model_integrity", "The saved model file failed its integrity check and was not loaded.")
    bundle = joblib.load(target)
    if not isinstance(bundle, dict) or bundle.get("bundle_version") != BUNDLE_VERSION:
        raise MlError("model_incompatible", "The saved model is from an incompatible version. Train it again.")
    return bundle


def regression_metrics(y_true, y_pred) -> dict[str, float | None]:
    """MAE, RMSE, MAPE, WAPE and R2.

    MAPE is computed only over observations where the actual value is not zero
    (a percentage error is undefined at zero). `mape_n` reports how many were used.
    """
    y_true = np.asarray(y_true, dtype=float)
    y_pred = np.asarray(y_pred, dtype=float)
    err = y_pred - y_true
    mae = float(np.mean(np.abs(err)))
    rmse = float(math.sqrt(np.mean(err**2)))
    nz = y_true != 0
    mape = float(np.mean(np.abs(err[nz] / y_true[nz])) * 100) if nz.any() else None
    denom = float(np.sum(np.abs(y_true)))
    wape = float(np.sum(np.abs(err)) / denom * 100) if denom > 0 else None
    ss_res = float(np.sum(err**2))
    ss_tot = float(np.sum((y_true - y_true.mean()) ** 2))
    r2 = 1 - ss_res / ss_tot if ss_tot > 0 else None
    return {
        "mae": round(mae, 4),
        "rmse": round(rmse, 4),
        "mape": None if mape is None else round(mape, 4),
        "r2": None if r2 is None else round(r2, 4),
        "wape": None if wape is None else round(wape, 4),
        "mape_n": int(nz.sum()),
        "n": int(len(y_true)),
    }


def clean(value: Any) -> Any:
    """Convert numpy/pandas values into JSON-safe python values (NaN -> None)."""
    if isinstance(value, dict):
        return {str(k): clean(v) for k, v in value.items()}
    if isinstance(value, (list, tuple)):
        return [clean(v) for v in value]
    if isinstance(value, np.ndarray):
        return [clean(v) for v in value.tolist()]
    if isinstance(value, (np.integer,)):
        return int(value)
    if isinstance(value, (np.floating, float)):
        f = float(value)
        return None if math.isnan(f) or math.isinf(f) else f
    if isinstance(value, np.bool_):
        return bool(value)
    return value

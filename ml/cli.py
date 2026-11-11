"""Command line entry point used by Laravel.

Usage:  python ml/cli.py <train|predict>      (JSON request on stdin, JSON response on stdout)

Responses always have the shape {"ok": true, ...} or {"ok": false, "code": "...", "error": "..."}.
Nothing else is ever written to stdout.
"""
from __future__ import annotations

import json
import sys
import warnings
from datetime import datetime, timezone
from pathlib import Path

warnings.filterwarnings("ignore")
sys.path.insert(0, str(Path(__file__).resolve().parent))

import numpy as np  # noqa: E402

import demand_model  # noqa: E402
import sales_model  # noqa: E402
from common import BUNDLE_VERSION, MlError, clean, load_bundle, safe_path, save_bundle  # noqa: E402

ALGORITHMS = ("simple_linear_regression", "random_forest_regression")
TASKS = {"sales": sales_model, "demand": demand_model}


def _bool(value, default=False) -> bool:
    return default if value is None else bool(value)


def cmd_train(req: dict) -> dict:
    task, algorithm = req.get("task"), req.get("algorithm")
    if task not in TASKS or algorithm not in ALGORITHMS:
        raise MlError("invalid_request", "Unsupported task or algorithm.")
    base = req["base_dir"]
    dataset = safe_path(req["dataset_path"], base)
    output = safe_path(req["output_path"], base)
    if not dataset.is_file():
        raise MlError("dataset_missing", "The dataset file could not be found.")
    split = float(req.get("split", 0.8))
    if not 0.5 <= split <= 0.95:
        raise MlError("invalid_request", "Training share must be between 50% and 95%.")
    seed = int(req.get("seed", 42))
    module = TASKS[task]
    bundle, info = module.train(dataset, algorithm, split, _bool(req.get("tune")), _bool(req.get("seasonal"), True), seed)
    bundle.update({"bundle_version": BUNDLE_VERSION, "task": task, "algorithm": algorithm,
                   "trained_at": datetime.now(timezone.utc).isoformat()})
    digest = save_bundle(bundle, output)
    return {"ok": True, "sha256": digest, "size": output.stat().st_size, "split": split, **info}


def _predict_one(bundle: dict, workflow: str, p: dict, products: list[dict]) -> dict:
    algorithm = bundle["algorithm"]
    if workflow == "sales":
        if bundle["task"] != "sales":
            raise MlError("invalid_request", "A sales model is required for sales prediction.")
        return sales_model.predict(bundle, algorithm, int(p["horizon_days"]), int(p.get("history_months", 6)))
    if bundle["task"] != "demand":
        raise MlError("invalid_request", "A product demand model is required for this workflow.")
    if workflow == "demand":
        return demand_model.predict_demand(bundle, algorithm, int(p["product_id"]), int(p["horizon_days"]), p.get("stock"))
    if workflow == "slow":
        return demand_model.predict_slow(bundle, algorithm, products, int(p.get("history_months", 6)))
    if workflow == "best":
        return demand_model.predict_best(bundle, algorithm, products, int(p.get("period_days", 30)))
    raise MlError("invalid_request", "Unsupported workflow.")


def _combine(workflow: str, bundles: list[dict], results: list[dict], p: dict, products: list[dict]) -> dict:
    """Blend several models into one prediction (the average of their forecasts)."""
    if workflow == "sales":
        return sales_model.combine_sales(results)
    if workflow == "demand":
        return demand_model.combine_demand(results, p.get("stock"))
    if workflow == "slow":
        fc = demand_model.average_forecasts([demand_model.forecast_all(b, b["algorithm"], 5) for b in bundles])
        return demand_model.predict_slow(bundles[0], bundles[0]["algorithm"], products, int(p.get("history_months", 6)), fc=fc)
    weeks = int(np.ceil(int(p.get("period_days", 30)) / 7))
    fc = demand_model.average_forecasts([demand_model.forecast_all(b, b["algorithm"], weeks) for b in bundles])
    return demand_model.predict_best(bundles[0], bundles[0]["algorithm"], products, int(p.get("period_days", 30)), fc=fc)


def cmd_predict(req: dict) -> dict:
    workflow = req.get("workflow")
    specs = req.get("models") or [{"model_path": req["model_path"], "sha256": req.get("sha256")}]
    if not 1 <= len(specs) <= 2:
        raise MlError("invalid_request", "One or two models are supported.")
    bundles = [load_bundle(spec["model_path"], req["base_dir"], spec.get("sha256")) for spec in specs]
    p, products = req.get("params", {}), req.get("products", [])
    results = [_predict_one(bundle, workflow, p, products) for bundle in bundles]

    response = {"ok": True, "workflow": workflow, "trained_through": bundles[0].get("last_week")}
    if len(bundles) == 1:
        return {**response, "algorithm": bundles[0]["algorithm"], "result": results[0]}
    return {**response, "algorithm": "both", "result": _combine(workflow, bundles, results, p, products),
            "components": [{"algorithm": b["algorithm"], "result": r} for b, r in zip(bundles, results)]}


def main() -> int:
    command = sys.argv[1] if len(sys.argv) > 1 else ""
    try:
        request = json.loads(sys.stdin.read() or "{}")
        if command == "train":
            response = cmd_train(request)
        elif command == "predict":
            response = cmd_predict(request)
        else:
            raise MlError("invalid_request", "Unknown command.")
        code = 0
    except MlError as exc:
        response, code = {"ok": False, "code": exc.code, "error": exc.message}, 0
    except Exception as exc:  # unexpected - report type only, never a traceback with paths
        response, code = {"ok": False, "code": "internal_error", "error": f"{type(exc).__name__}: {exc}"}, 1
    sys.stdout.write(json.dumps(clean(response), allow_nan=False))
    return code


if __name__ == "__main__":
    sys.exit(main())

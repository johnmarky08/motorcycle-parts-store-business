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


def cmd_predict(req: dict) -> dict:
    workflow = req.get("workflow")
    bundle = load_bundle(req["model_path"], req["base_dir"], req.get("sha256"))
    algorithm = bundle["algorithm"]
    p = req.get("params", {})
    if workflow == "sales":
        if bundle["task"] != "sales":
            raise MlError("invalid_request", "A sales model is required for sales prediction.")
        result = sales_model.predict(bundle, algorithm, int(p["horizon_days"]), int(p.get("history_months", 6)))
    elif bundle["task"] != "demand":
        raise MlError("invalid_request", "A product demand model is required for this workflow.")
    elif workflow == "demand":
        result = demand_model.predict_demand(bundle, algorithm, int(p["product_id"]), int(p["horizon_days"]),
                                             p.get("stock"))
    elif workflow == "slow":
        result = demand_model.predict_slow(bundle, algorithm, req.get("products", []), int(p.get("history_months", 6)))
    elif workflow == "best":
        result = demand_model.predict_best(bundle, algorithm, req.get("products", []), int(p.get("period_days", 30)))
    else:
        raise MlError("invalid_request", "Unsupported workflow.")
    return {"ok": True, "workflow": workflow, "algorithm": algorithm, "trained_through": bundle.get("last_week"),
            "result": result}


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

from __future__ import annotations

import argparse
import json
import math
from datetime import datetime, timezone
from pathlib import Path

import joblib
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.pipeline import Pipeline


BASE = Path(__file__).resolve().parent
DATASETS = {
    "product_demand": {
        "file": BASE / "datasets" / "product_demand.csv",
        "target": "units_sold",
        "features": ["day_index", "stock_quantity", "unit_price", "product_index", "promotion", "day_of_week", "recent_demand"],
    },
    "sales_history": {
        "file": BASE / "datasets" / "sales_history.csv",
        "target": "total_amount",
        "features": ["day_index", "product_index", "promotion", "day_of_week", "discount_rate", "customer_factor"],
    },
}
FEATURE_BOUNDS = {
    "day_index": (0, None),
    "stock_quantity": (0, None),
    "unit_price": (0, None),
    "product_index": (0, 5),
    "promotion": (0, 1),
    "day_of_week": (0, 6),
    "recent_demand": (0, None),
    "discount_rate": (0, 1),
    "customer_factor": (0, None),
}
ALGORITHMS = {
    "simple_linear_regression": LinearRegression,
    "random_forest_regression": lambda: RandomForestRegressor(
        n_estimators=120, random_state=20261030, min_samples_leaf=2
    ),
}


class JsonArgumentParser(argparse.ArgumentParser):
    def error(self, message: str) -> None:
        raise ValueError(message)


def load_dataset(dataset_id: str) -> tuple[dict, pd.DataFrame]:
    if dataset_id not in DATASETS:
        raise ValueError("Unsupported dataset.")
    definition = DATASETS[dataset_id]
    dataframe = pd.read_csv(definition["file"])
    required = set(definition["features"] + [definition["target"]])
    missing = sorted(required - set(dataframe.columns))
    if missing:
        raise ValueError(f"Dataset is missing required columns: {', '.join(missing)}")
    if len(dataframe) < 1000:
        raise ValueError("Dataset must contain at least 1,000 records.")
    model_columns = definition["features"] + [definition["target"]]
    numeric = dataframe[model_columns].apply(pd.to_numeric, errors="coerce")
    invalid_columns = sorted(numeric.columns[numeric.isnull().any()].tolist())
    if invalid_columns:
        raise ValueError(f"Dataset contains missing or invalid numeric values in: {', '.join(invalid_columns)}")
    dataframe[model_columns] = numeric
    if dataframe[definition["target"]].lt(0).any():
        raise ValueError("Dataset contains negative target values.")
    return definition, dataframe


def metrics_for(actual: pd.Series, predicted) -> dict:
    mse = mean_squared_error(actual, predicted)
    return {
        "mae": float(mean_absolute_error(actual, predicted)),
        "mse": float(mse),
        "rmse": float(mse ** 0.5),
        "r2": float(r2_score(actual, predicted)),
    }


def split_dataset(dataframe: pd.DataFrame, features: list[str], target: str):
    split_at = int(len(dataframe) * 0.8)
    return (
        dataframe[features].iloc[:split_at],
        dataframe[features].iloc[split_at:],
        dataframe[target].iloc[:split_at],
        dataframe[target].iloc[split_at:],
    )


def train(args: argparse.Namespace) -> dict:
    definition, dataframe = load_dataset(args.dataset)
    features = definition["features"]
    target = definition["target"]
    x_train, x_test, y_train, y_test = split_dataset(dataframe, features, target)
    estimator = ALGORITHMS[args.algorithm]()
    model = Pipeline([("imputer", SimpleImputer(strategy="median")), ("model", estimator)])
    model.fit(x_train, y_train)
    predictions = model.predict(x_test)
    training_metrics = metrics_for(y_train, model.predict(x_train))
    metrics = metrics_for(y_test, predictions)
    artifact = Path(args.artifact)
    artifact.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(model, artifact)
    return {
        "dataset_id": args.dataset,
        "algorithm": args.algorithm,
        "target": target,
        "features": features,
        "record_count": len(dataframe),
        "metrics": metrics,
        "training_metrics": training_metrics,
        "feature_ranges": {
            feature: {"min": float(dataframe[feature].min()), "max": float(dataframe[feature].max())}
            for feature in features
        },
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "artifact": str(artifact),
    }


def predict(args: argparse.Namespace) -> dict:
    definition, dataframe = load_dataset(args.dataset)
    try:
        model = joblib.load(args.artifact)
    except Exception as error:
        raise ValueError(f"Unable to load model artifact: {error}") from error
    try:
        values = json.loads(args.features)
    except json.JSONDecodeError as error:
        raise ValueError("Features must be valid JSON.") from error
    if not isinstance(values, dict):
        raise ValueError("Features must be a JSON object.")
    expected = set(definition["features"])
    received = set(values)
    missing = sorted(expected - received)
    unexpected = sorted(received - expected)
    if missing:
        raise ValueError(f"Missing features: {', '.join(missing)}")
    if unexpected:
        raise ValueError(f"Unexpected features: {', '.join(unexpected)}")
    try:
        row = {feature: float(values[feature]) for feature in definition["features"]}
    except (TypeError, ValueError) as error:
        raise ValueError("All features must be numeric.") from error
    if not all(math.isfinite(value) for value in row.values()):
        raise ValueError("All features must be finite numeric values.")
    if any(value < 0 for value in row.values()):
        raise ValueError("Features cannot be negative.")
    for feature, value in row.items():
        lower, upper = FEATURE_BOUNDS[feature]
        if (lower is not None and value < lower) or (upper is not None and value > upper):
            raise ValueError(f"{feature} is outside its valid range.")
    ranges = {
        feature: {"min": float(dataframe[feature].min()), "max": float(dataframe[feature].max())}
        for feature in definition["features"]
    }
    warnings = [
        f"{feature} is outside the training range ({limits['min']:.2f} to {limits['max']:.2f})."
        for feature, limits in ranges.items()
        if row[feature] < limits["min"] or row[feature] > limits["max"]
    ]
    prediction = float(model.predict(pd.DataFrame([row]))[0])
    if not math.isfinite(prediction):
        raise ValueError("Model returned an invalid prediction.")
    result = {
        "dataset_id": args.dataset,
        "target": definition["target"],
        "prediction": prediction,
        "features": row,
        "warnings": warnings,
        "target_is_non_negative": True,
    }
    if prediction < 0:
        result["warnings"].append("The model returned a negative value for a non-negative target; review the inputs before using it.")
    return result


def main() -> None:
    parser = JsonArgumentParser()
    parser.add_argument("action", choices=["validate", "train", "predict"])
    parser.add_argument("--dataset", required=True, choices=sorted(DATASETS))
    parser.add_argument("--algorithm", choices=sorted(ALGORITHMS))
    parser.add_argument("--artifact")
    parser.add_argument("--features")
    args = parser.parse_args()
    if args.action == "validate":
        definition, dataframe = load_dataset(args.dataset)
        result = {
            "dataset_id": args.dataset,
            "record_count": len(dataframe),
            "required_columns": definition["features"] + [definition["target"]],
            "missing_values": int(dataframe.isnull().sum().sum()),
            "duplicate_rows": int(dataframe.duplicated().sum()),
            "valid": True,
        }
    elif args.action == "train":
        if not args.algorithm or not args.artifact:
            raise ValueError("Training requires an algorithm and artifact path.")
        result = train(args)
    else:
        if not args.artifact or not args.features:
            raise ValueError("Prediction requires an artifact and feature values.")
        result = predict(args)
    print(json.dumps(result))


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(json.dumps({"error": str(error)}))
        raise SystemExit(1)

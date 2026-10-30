import json
import math
import subprocess
import sys
import tempfile
import unittest
from argparse import Namespace
from pathlib import Path

from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
PIPELINE = ROOT / "pipeline.py"
sys.path.insert(0, str(ROOT))
import pipeline


class PipelineTest(unittest.TestCase):
    def run_pipeline(self, *arguments):
        result = subprocess.run(
            [sys.executable, str(PIPELINE), *arguments],
            check=True,
            capture_output=True,
            text=True,
        )
        return json.loads(result.stdout)

    def run_pipeline_error(self, *arguments):
        result = subprocess.run(
            [sys.executable, str(PIPELINE), *arguments],
            check=False,
            capture_output=True,
            text=True,
        )
        self.assertNotEqual(result.returncode, 0)
        return json.loads(result.stdout)["error"]

    def test_datasets_validate_with_required_record_count(self):
        for dataset in ("product_demand", "sales_history"):
            result = self.run_pipeline("validate", "--dataset", dataset)
            self.assertTrue(result["valid"])
            self.assertGreaterEqual(result["record_count"], 1000)
            self.assertEqual(result["missing_values"], 0)

    def test_all_dataset_algorithm_combinations_train_and_predict(self):
        with tempfile.TemporaryDirectory() as directory:
            for dataset, features in (
                ("product_demand", '{"day_index": 600, "stock_quantity": 30, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10}'),
                ("sales_history", '{"day_index": 600, "product_index": 0, "promotion": 0, "day_of_week": 2, "discount_rate": 0.05, "customer_factor": 1}'),
            ):
                for algorithm in ("simple_linear_regression", "random_forest_regression"):
                    artifact = Path(directory) / f"{dataset}-{algorithm}.joblib"
                    result = self.run_pipeline(
                        "train", "--dataset", dataset, "--algorithm", algorithm, "--artifact", str(artifact)
                    )
                    self.assertTrue(artifact.exists())
                    for metric in ("mae", "mse", "rmse", "r2"):
                        self.assertTrue(math.isfinite(result["metrics"][metric]))
                        self.assertTrue(math.isfinite(result["training_metrics"][metric]))
                    prediction = self.run_pipeline(
                        "predict", "--dataset", dataset, "--artifact", str(artifact), "--features", features
                    )
                    self.assertTrue(math.isfinite(prediction["prediction"]))
                    loaded = pipeline.joblib.load(artifact)
                    self.assertEqual(list(loaded.named_steps), ["imputer", "model"])

    def test_prediction_rejects_invalid_features_and_artifacts(self):
        with tempfile.TemporaryDirectory() as directory:
            artifact = Path(directory) / "model.joblib"
            self.run_pipeline(
                "train", "--dataset", "product_demand", "--algorithm",
                "simple_linear_regression", "--artifact", str(artifact)
            )
            for features, expected in (
                ('{"day_index": 1}', "Missing features"),
                ('{"day_index": 1, "stock_quantity": 2, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10, "extra": 3}', "Unexpected features"),
                ('{"day_index": "nope", "stock_quantity": 2, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10}', "All features must be numeric"),
            ):
                self.assertIn(
                    expected,
                    self.run_pipeline_error(
                        "predict", "--dataset", "product_demand", "--artifact", str(artifact), "--features", features
                    ),
                )
            corrupted = Path(directory) / "corrupted.joblib"
            corrupted.write_text("not a model", encoding="utf-8")
            self.assertIn(
                "Unable to load model artifact",
                self.run_pipeline_error(
                    "predict", "--dataset", "product_demand", "--artifact", str(corrupted),
                    "--features", '{"day_index": 1, "stock_quantity": 2, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10}',
                ),
            )

    def test_dataset_validation_rejects_invalid_identifier_and_small_or_invalid_data(self):
        self.assertIn("invalid choice", self.run_pipeline_error("validate", "--dataset", "unknown"))
        with tempfile.TemporaryDirectory() as directory:
            source = ROOT / "datasets" / "product_demand.csv"
            small = Path(directory) / "small.csv"
            small.write_text("\n".join(source.read_text(encoding="utf-8").splitlines()[:3]), encoding="utf-8")
            invalid = Path(directory) / "invalid.csv"
            invalid.write_text(source.read_text(encoding="utf-8").replace("8,", "bad,", 1), encoding="utf-8")
            with patch.dict(pipeline.DATASETS, {
                "product_demand": {
                    **pipeline.DATASETS["product_demand"],
                    "file": small,
                }
            }):
                with self.assertRaisesRegex(ValueError, "at least 1,000"):
                    pipeline.load_dataset("product_demand")
            with patch.dict(pipeline.DATASETS, {
                "product_demand": {
                    **pipeline.DATASETS["product_demand"],
                    "file": invalid,
                }
            }):
                with self.assertRaisesRegex(ValueError, "invalid numeric values"):
                    pipeline.load_dataset("product_demand")

    def test_out_of_range_features_warn_and_negative_features_fail(self):
        with tempfile.TemporaryDirectory() as directory:
            artifact = Path(directory) / "model.joblib"
            self.run_pipeline(
                "train", "--dataset", "product_demand", "--algorithm",
                "simple_linear_regression", "--artifact", str(artifact)
            )
            result = self.run_pipeline(
                "predict", "--dataset", "product_demand", "--artifact", str(artifact),
                "--features", '{"day_index": 5000, "stock_quantity": 30, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10}',
            )
            self.assertTrue(result["warnings"])
            self.assertIn("day_index", result["warnings"][0])
            self.assertIn("cannot be negative", self.run_pipeline_error(
                "predict", "--dataset", "product_demand", "--artifact", str(artifact),
                "--features", '{"day_index": -1, "stock_quantity": 30, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10}',
            ))

    def test_negative_model_prediction_is_reported_not_silently_clipped(self):
        args = Namespace(
            dataset="product_demand",
            artifact="unused",
            features='{"day_index": 600, "stock_quantity": 30, "unit_price": 1250, "product_index": 0, "promotion": 0, "day_of_week": 2, "recent_demand": 10}',
        )
        class NegativeModel:
            def predict(self, _rows):
                return [-4]
        with patch("pipeline.joblib.load", return_value=NegativeModel()):
            result = pipeline.predict(args)
        self.assertEqual(result["prediction"], -4)
        self.assertTrue(any("negative value" in warning for warning in result["warnings"]))

    def test_chronological_split_and_dataset_generation_are_reproducible(self):
        definition, dataframe = pipeline.load_dataset("product_demand")
        x_train, x_test, _, _ = pipeline.split_dataset(dataframe, definition["features"], definition["target"])
        self.assertLess(x_train["day_index"].max(), x_test["day_index"].min())
        source = ROOT / "datasets" / "product_demand.csv"
        before = source.read_bytes()
        subprocess.run([sys.executable, str(ROOT / "generate_datasets.py")], check=True)
        self.assertEqual(before, source.read_bytes())


if __name__ == "__main__":
    unittest.main()

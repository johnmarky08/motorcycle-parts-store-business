"""Run from the project root:  python -m unittest discover -s ml/tests -v

Uses the shipped datasets in /datasets and a temporary model directory.
"""
import json
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CLI = ROOT / "ml" / "cli.py"


def run(command: str, payload: dict) -> dict:
    proc = subprocess.run([sys.executable, str(CLI), command], input=json.dumps(payload), capture_output=True, text=True)
    return json.loads(proc.stdout)


class MlPackageTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.base = Path(tempfile.mkdtemp())
        shutil.copytree(ROOT / "ml" / "datasets", cls.base / "datasets")
        cls.models = {}
        for task, dataset in (("sales", "sales_history.csv"), ("demand", "product_demand.csv")):
            for algorithm in ("simple_linear_regression", "random_forest_regression"):
                out = run("train", dict(task=task, algorithm=algorithm, base_dir=str(cls.base),
                                        dataset_path=str(cls.base / "datasets" / dataset),
                                        output_path=str(cls.base / "models" / f"{task}_{algorithm}.joblib"),
                                        split=0.8, tune=False, seasonal=False, seed=42))
                assert out["ok"], out
                cls.models[(task, algorithm)] = out

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(cls.base, ignore_errors=True)

    def predict(self, task, algorithm, workflow, params, products=None, sha=None):
        model = self.models[(task, algorithm)]
        return run("predict", dict(workflow=workflow, base_dir=str(self.base),
                                   model_path=str(self.base / "models" / f"{task}_{algorithm}.joblib"),
                                   sha256=sha or model["sha256"], params=params, products=products or []))

    def test_training_reports_all_metrics(self):
        for out in self.models.values():
            for key in ("mae", "rmse", "mape", "r2", "wape"):
                self.assertIn(key, out["metrics"])
            self.assertGreater(out["rows"], 1000)

    def test_models_are_saved_and_loaded(self):
        for (task, algorithm), out in self.models.items():
            self.assertTrue((self.base / "models" / f"{task}_{algorithm}.joblib").is_file())
            self.assertEqual(len(out["sha256"]), 64)

    def test_sales_prediction(self):
        for algorithm in ("simple_linear_regression", "random_forest_regression"):
            out = self.predict("sales", algorithm, "sales", {"horizon_days": 30, "history_months": 6})
            self.assertTrue(out["ok"], out)
            self.assertGreater(out["result"]["predicted_total"], 0)
            self.assertLessEqual(out["result"]["lower"], out["result"]["upper"])

    def test_demand_prediction_and_restock(self):
        out = self.predict("demand", "random_forest_regression", "demand", {"product_id": 2, "horizon_days": 30, "stock": 0})
        self.assertTrue(out["ok"], out)
        self.assertGreater(out["result"]["predicted_units"], 0)
        self.assertGreater(out["result"]["suggested_restock"], 0)

    def test_slow_and_best_rankings(self):
        products = [dict(id=i, sku=f"S{i}", name=f"P{i}", category="C", stock=50 * i, price=100.0) for i in range(1, 30)]
        best = self.predict("demand", "random_forest_regression", "best", {"period_days": 30}, products)
        self.assertTrue(best["ok"], best)
        units = [item["predicted_units"] for item in best["result"]["items"]]
        self.assertEqual(units, sorted(units, reverse=True))
        slow = self.predict("demand", "simple_linear_regression", "slow", {"history_months": 6}, products)
        self.assertTrue(slow["ok"], slow)

    def predict_both(self, task, workflow, params, products=None):
        specs = [{"model_path": str(self.base / "models" / f"{task}_{a}.joblib"), "sha256": self.models[(task, a)]["sha256"]}
                 for a in ("simple_linear_regression", "random_forest_regression")]
        return run("predict", dict(workflow=workflow, base_dir=str(self.base), models=specs, params=params, products=products or []))

    def test_both_models_sales_is_the_average_of_the_two(self):
        both = self.predict_both("sales", "sales", {"horizon_days": 30, "history_months": 6})
        self.assertTrue(both["ok"], both)
        self.assertEqual(both["algorithm"], "both")
        totals = [c["result"]["predicted_total"] for c in both["components"]]
        self.assertEqual(len(totals), 2)
        self.assertAlmostEqual(both["result"]["predicted_total"], sum(totals) / 2, places=1)

    def test_both_models_demand_and_rankings(self):
        demand = self.predict_both("demand", "demand", {"product_id": 2, "horizon_days": 30, "stock": 0})
        self.assertTrue(demand["ok"], demand)
        units = [c["result"]["predicted_units"] for c in demand["components"]]
        self.assertAlmostEqual(demand["result"]["predicted_units"], sum(units) / 2, delta=0.11)
        products = [dict(id=i, sku=f"S{i}", name=f"P{i}", category="C", stock=40 * i, price=100.0) for i in range(1, 30)]
        best = self.predict_both("demand", "best", {"period_days": 30}, products)
        slow = self.predict_both("demand", "slow", {"history_months": 6}, products)
        self.assertTrue(best["ok"] and slow["ok"], (best, slow))
        ranked = [i["predicted_units"] for i in best["result"]["items"]]
        self.assertEqual(ranked, sorted(ranked, reverse=True))

    def test_tampered_model_is_rejected(self):
        out = self.predict("demand", "simple_linear_regression", "demand", {"product_id": 2, "horizon_days": 7}, sha="0" * 64)
        self.assertFalse(out["ok"])
        self.assertEqual(out["code"], "model_integrity")

    def test_paths_outside_base_dir_are_rejected(self):
        out = run("predict", dict(workflow="demand", base_dir=str(self.base), model_path="/etc/passwd", sha256="x", params={}))
        self.assertFalse(out["ok"])
        self.assertEqual(out["code"], "invalid_path")

    def test_small_dataset_is_refused(self):
        small = self.base / "datasets" / "small.csv"
        lines = (self.base / "datasets" / "sales_history.csv").read_text().splitlines()[:200]
        small.write_text("\n".join(lines))
        out = run("train", dict(task="sales", algorithm="simple_linear_regression", base_dir=str(self.base),
                                dataset_path=str(small), output_path=str(self.base / "models" / "x.joblib")))
        self.assertFalse(out["ok"])
        self.assertEqual(out["code"], "insufficient_data")


if __name__ == "__main__":
    unittest.main()

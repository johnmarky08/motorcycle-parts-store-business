import csv
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
from validate_datasets import validate


class DatasetValidationTest(unittest.TestCase):
    def test_existing_synthetic_datasets_are_pipeline_ready(self):
        for dataset_id, filename in (
            ("product_demand", "product_demand.csv"),
            ("sales_history", "sales_history.csv"),
        ):
            report = validate(dataset_id, ROOT / "datasets" / filename)
            self.assertTrue(report["valid"], report["errors"])
            self.assertEqual(report["records"], 1200)
            self.assertEqual(report["duplicate_rows"], 0)

    def test_validator_rejects_missing_columns_and_leakage(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "leaky.csv"
            with path.open("w", newline="", encoding="utf-8") as handle:
                writer = csv.DictWriter(handle, fieldnames=["date", "product_id", "units_sold", "recent_demand"])
                writer.writeheader()
                writer.writerow({"date": "2026-01-01", "product_id": "P001", "units_sold": 3, "recent_demand": 3})
            report = validate("product_demand", path)
            self.assertFalse(report["valid"])
            self.assertTrue(any("Missing required columns" in error for error in report["errors"]))
            self.assertTrue(any("recent_demand" in error for error in report["errors"]))


if __name__ == "__main__":
    unittest.main()

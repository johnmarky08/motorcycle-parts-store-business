from __future__ import annotations

import csv
import math
import random
from datetime import date, timedelta
from pathlib import Path


ROOT = Path(__file__).resolve().parent / "datasets"
PRODUCTS = [
    ("P001", "Kawasaki Ninja Brake Pad", "Brake System", 1250),
    ("P002", "Yamaha MT-15 Air Filter", "Engine", 680),
    ("P003", "Honda Click Drive Belt", "Drivetrain", 1450),
    ("P004", "NGK Iridium Spark Plug", "Electrical", 520),
    ("P005", "DID 428H Chain Set", "Drivetrain", 2380),
    ("P006", "Michelin City Pro Tire", "Tires", 3150),
]


def build() -> None:
    ROOT.mkdir(parents=True, exist_ok=True)
    random.seed(20261030)
    start = date(2023, 1, 1)

    demand_rows = []
    sales_rows = []
    previous_demand = [8] * len(PRODUCTS)
    for index in range(1200):
        current = start + timedelta(days=index)
        product_id, name, category, price = PRODUCTS[index % len(PRODUCTS)]
        product_index = index % len(PRODUCTS)
        seasonal = 1 + 0.22 * math.sin(index / 45 + product_index)
        trend = 1 + index / 3200
        promotion = 1 if index % 37 in (0, 1, 2) else 0
        stock = max(round(34 + random.gauss(0, 14) + 10 * math.cos(index / 29)), 4)
        price_factor = max(0.65, 1.35 - price / 5000)
        recent_demand = previous_demand[product_index]
        expected = (7 + product_index * 1.8) * seasonal * trend * price_factor * (1.18 if promotion else 1)
        quantity = max(0, round(0.45 * recent_demand + 0.55 * expected + random.gauss(0, 2.4)))
        previous_demand[product_index] = quantity
        demand_rows.append(
            {
                "date": current.isoformat(),
                "product_id": product_id,
                "product_name": name,
                "category": category,
                "units_sold": quantity,
                "stock_quantity": stock,
                "unit_price": price,
                "product_index": product_index,
                "promotion": promotion,
                "day_of_week": current.weekday(),
                "recent_demand": recent_demand,
                "day_index": index,
            }
        )
        discount_rate = 0.05 + (0.08 if promotion else 0) + random.uniform(-0.02, 0.02)
        customer_factor = 1 + 0.12 * math.sin(index / 31 + product_index)
        revenue_noise = random.gauss(0, price * 0.12)
        revenue = max(0, quantity * price * (1 - discount_rate) * customer_factor + revenue_noise)
        sales_rows.append(
            {
                "transaction_date": current.isoformat(),
                "product_id": product_id,
                "product_name": name,
                "category": category,
                "quantity": quantity,
                "unit_price": price,
                "discount_rate": round(discount_rate, 4),
                "product_index": product_index,
                "promotion": promotion,
                "day_of_week": current.weekday(),
                "customer_factor": round(customer_factor, 4),
                "total_amount": round(revenue, 2),
                "day_index": index,
            }
        )

    for filename, rows in (("product_demand.csv", demand_rows), ("sales_history.csv", sales_rows)):
        with (ROOT / filename).open("w", newline="", encoding="utf-8") as handle:
            writer = csv.DictWriter(handle, fieldnames=rows[0].keys())
            writer.writeheader()
            writer.writerows(rows)


if __name__ == "__main__":
    build()

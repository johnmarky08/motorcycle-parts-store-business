"""Generate the demo datasets for the One Prompt Away motorcycle parts store.

Deterministic (fixed seed). Produces:
  database/seeders/data/products.csv  - product catalog used by ProductSeeder
  ml/datasets/sales_history.csv       - one row per sold line item (transaction level)
  ml/datasets/product_demand.csv      - weekly units sold per product

Run from the project root:  python ml/generate_datasets.py
"""
from __future__ import annotations

import csv
import math
import random
from datetime import date, datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SEED = 2026
START = date(2025, 1, 6)   # Monday
END = date(2026, 11, 1)    # Sunday  -> 95 complete weeks

# (name, category, sku, price, popularity, max_qty, description)
P = [
    # Brake System
    ("Kawasaki Ninja Brake Pad", "Brake System", "BP-KN-004", 1250, 7, 1, "Front brake pad set for Kawasaki Ninja models."),
    ("Honda Click 125 Front Brake Pad", "Brake System", "BP-HC-005", 380, 9, 1, "Semi-metallic front brake pad for Honda Click 125."),
    ("Yamaha Mio Sporty Brake Shoe", "Brake System", "BS-YM-006", 260, 8, 1, "Rear brake shoe set for Yamaha Mio Sporty."),
    ("Suzuki Raider 150 Brake Pad", "Brake System", "BP-SR-014", 420, 6, 1, "Front brake pad set for Suzuki Raider 150."),
    ("Honda Beat Rear Brake Shoe", "Brake System", "BS-HB-013", 290, 7, 1, "Rear brake shoe set for Honda Beat."),
    ("Brembo Universal Racing Brake Pad", "Brake System", "BP-BR-010", 2650, 2, 1, "High-performance sintered brake pad for big bikes."),
    ("Brake Disc Rotor 220mm", "Brake System", "BD-UNI-011", 1850, 3, 1, "Floating 220mm stainless brake disc rotor."),
    ("Stainless Brake Hose Kit", "Brake System", "BH-UNI-015", 1480, 1.2, 1, "Braided stainless brake hose kit for improved lever feel."),
    # Engine
    ("Honda TMX 155 Piston Kit", "Engine", "PK-HT-021", 1850, 3, 1, "Standard-size piston kit with rings for Honda TMX 155."),
    ("Yamaha NMAX Head Gasket", "Engine", "GK-YN-022", 540, 2.5, 1, "Cylinder head gasket for Yamaha NMAX 155."),
    ("Suzuki Raider Clutch Cable", "Engine", "CC-SR-015", 220, 1.4, 1, "Replacement clutch cable for Suzuki Raider."),
    ("Honda Click CVT Roller Set", "Engine", "RS-HC-023", 650, 6, 1, "CVT weight roller set for Honda Click scooters."),
    ("Yamaha Mio Pulley Set", "Engine", "PS-YM-024", 1980, 3, 1, "Front pulley set for Yamaha Mio models."),
    ("Engine Oil Seal Kit", "Engine", "OS-UNI-025", 340, 2, 1, "Universal engine oil seal assortment kit."),
    ("Honda XRM Carburetor Rebuild Kit", "Engine", "CB-HX-026", 780, 2.5, 1, "Jets, needle and gasket rebuild kit for Honda XRM."),
    ("Radiator Coolant 1L", "Engine", "RC-UNI-027", 285, 6, 2, "Pre-mixed long-life radiator coolant, 1 liter."),
    ("Universal Throttle Cable", "Engine", "TC-UNI-028", 190, 3, 1, "Universal throttle cable with adjustable ends."),
    # Electrical
    ("NGK Iridium Spark Plug", "Electrical", "SP-NGK-009", 520, 9, 2, "Long-life iridium spark plug for common motorcycle engines."),
    ("NGK CR7HSA Spark Plug", "Electrical", "SP-NGK-031", 145, 10, 2, "Standard NGK spark plug for 110-150cc engines."),
    ("LED Signal Light Set", "Electrical", "SL-LED-030", 650, 1.3, 1, "Pair of amber LED turn signal lights."),
    ("Motorcycle Battery 12V 5Ah", "Electrical", "BT-12V-032", 1250, 5, 1, "Maintenance-free 12V 5Ah motorcycle battery."),
    ("Yamaha Aerox LED Headlight Bulb", "Electrical", "HL-YA-033", 890, 4, 1, "High-brightness LED headlight bulb for Yamaha Aerox."),
    ("Universal Horn 12V", "Electrical", "HN-UNI-034", 360, 3, 1, "Compact 12V disc horn."),
    ("Honda Click Regulator Rectifier", "Electrical", "RR-HC-035", 1150, 2.5, 1, "Voltage regulator rectifier for Honda Click."),
    ("Yamaha Mio Ignition Coil", "Electrical", "IC-YM-036", 780, 3.5, 1, "Ignition coil assembly for Yamaha Mio models."),
    # Drivetrain
    ("DID 428H Chain Set", "Drivetrain", "CH-DID-428", 2380, 6, 1, "Heavy-duty chain and sprocket set."),
    ("RK 520 O-Ring Chain", "Drivetrain", "CH-RK-520", 3450, 2.5, 1, "Sealed O-ring chain for 250cc and above."),
    ("Honda Click Drive Belt", "Drivetrain", "DB-HC-018", 1450, 8, 1, "CVT drive belt for Honda Click scooters."),
    ("Yamaha NMAX Drive Belt", "Drivetrain", "DB-YN-038", 1680, 4.5, 1, "CVT drive belt for Yamaha NMAX 155."),
    ("Universal Chain Adjuster", "Drivetrain", "CA-UNI-09", 280, 1.1, 1, "Aluminum chain adjuster pair."),
    ("Front Sprocket 14T", "Drivetrain", "FS-UNI-039", 340, 3, 1, "14-tooth steel front sprocket."),
    ("Rear Sprocket 428-42T", "Drivetrain", "RS-UNI-040", 620, 3, 1, "42-tooth 428 pitch rear sprocket."),
    ("Honda XRM Clutch Cable", "Drivetrain", "CC-HX-041", 210, 3, 1, "Clutch cable for Honda XRM 125."),
    # Tires
    ("Michelin City Pro Tire 80/90-17", "Tires", "TR-MCP-017", 3150, 3.5, 1, "Urban commuter tire with wet-road grip."),
    ("Pirelli Diablo Rosso 120/70-17", "Tires", "TR-PDR-042", 5850, 1.4, 1, "Sport-touring front tire."),
    ("Kenda K324 Tire 70/90-14", "Tires", "TR-KND-043", 1250, 6, 1, "Economy scooter tire."),
    ("Corsa Platinum Tire 90/80-17", "Tires", "TR-CPL-044", 1850, 5, 1, "Street tire with all-round grip."),
    ("Inner Tube 17 inch", "Tires", "IT-17-045", 220, 8, 2, "Butyl inner tube for 17-inch rims."),
    ("Inner Tube 14 inch", "Tires", "IT-14-046", 195, 6, 2, "Butyl inner tube for 14-inch rims."),
    ("Tubeless Valve Stem Set", "Tires", "VS-UNI-047", 90, 4, 4, "Rubber snap-in tubeless valve stems, set of 4."),
    ("Tire Sealant 500ml", "Tires", "TS-UNI-048", 420, 2, 1, "Puncture-sealing tire sealant."),
    # Filters
    ("Yamaha MT-15 Air Filter", "Filters", "AF-YM-021", 680, 5, 1, "Replacement air filter for Yamaha MT-15."),
    ("Honda Click Air Filter", "Filters", "AF-HC-049", 340, 8, 1, "OEM-style air filter element for Honda Click."),
    ("K&N Universal Air Filter", "Filters", "AF-KN-050", 2250, 1.1, 1, "Washable high-flow universal air filter."),
    ("Honda TMX Oil Filter", "Filters", "OF-HT-051", 150, 7, 2, "Oil filter for Honda TMX 125/155."),
    ("Yamaha NMAX Oil Filter", "Filters", "OF-YN-052", 185, 5, 2, "Oil filter for Yamaha NMAX."),
    ("Universal Fuel Filter", "Filters", "FF-UNI-053", 120, 3, 2, "Inline universal fuel filter."),
    # Lubricants
    ("Motul 7100 10W-40 1L", "Lubricants", "OL-MT-710", 890, 7, 3, "Fully synthetic 4T engine oil, 1 liter."),
    ("Motul 3000 20W-50 1L", "Lubricants", "OL-MT-300", 520, 8, 3, "Mineral 4T engine oil, 1 liter."),
    ("Castrol Power1 10W-40 1L", "Lubricants", "OL-CS-054", 650, 7, 3, "Semi-synthetic 4T engine oil, 1 liter."),
    ("Shell Advance AX7 10W-40 1L", "Lubricants", "OL-SH-055", 480, 8, 3, "Semi-synthetic motorcycle oil, 1 liter."),
    ("Petron Sprint 4T 1L", "Lubricants", "OL-PT-056", 320, 9, 3, "Everyday 4T engine oil, 1 liter."),
    ("Gear Oil 80W-90 120ml", "Lubricants", "GO-UNI-057", 85, 10, 3, "Transmission gear oil for scooters, 120 ml."),
    ("Chain Lube 400ml", "Lubricants", "CL-UNI-058", 260, 6, 2, "Spray chain lubricant, 400 ml."),
    ("Brake Fluid DOT 4 250ml", "Lubricants", "BF-DOT4-012", 195, 5, 2, "DOT 4 brake fluid, 250 ml."),
    # Accessories
    ("Handlebar USB Charger", "Accessories", "UC-UNI-037", 320, 4, 1, "Waterproof dual-port USB charger."),
    ("Universal Phone Holder", "Accessories", "PH-UNI-059", 450, 4, 1, "Handlebar phone mount with vibration dampener."),
    ("Half-Face Motorcycle Helmet", "Accessories", "HM-HF-060", 1450, 3, 1, "ICC-certified half-face helmet."),
    ("Riding Gloves", "Accessories", "RG-UNI-061", 680, 3, 1, "Padded full-finger riding gloves."),
    ("Waterproof Rain Cover", "Accessories", "RC-WP-062", 520, 3.5, 1, "Motorcycle rain cover with storage pouch."),
    ("Side Mirror Set", "Accessories", "SM-UNI-063", 420, 5, 1, "Universal side mirror pair, 10 mm thread."),
    ("Honda Click Seat Cover", "Accessories", "SC-HC-064", 580, 3, 1, "Water-resistant seat cover for Honda Click."),
    ("Rubber Handle Grips", "Accessories", "HG-UNI-065", 190, 5, 1, "Anti-slip rubber handle grips."),
]

# month -> multiplier per category (1..12)
SEASON = {
    "Brake System": [.95, .95, .95, .9, .95, 1.1, 1.15, 1.15, 1.1, 1.1, 1.0, 1.0],
    "Engine": [.9, .95, 1.1, 1.2, 1.15, .95, .9, .9, .95, 1.0, 1.0, 1.05],
    "Electrical": [.95, .95, .95, .95, .95, 1.0, 1.0, 1.0, 1.0, 1.05, 1.1, 1.25],
    "Drivetrain": [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.05],
    "Tires": [.85, .85, .9, .9, 1.0, 1.25, 1.3, 1.3, 1.2, 1.1, .95, .9],
    "Filters": [.95, 1.0, 1.1, 1.15, 1.1, 1.0, .95, .95, .95, 1.0, 1.0, 1.0],
    "Lubricants": [.95, .95, 1.1, 1.2, 1.15, 1.0, .95, .95, 1.0, 1.0, 1.0, 1.1],
    "Accessories": [.9, .9, .95, 1.0, 1.0, 1.2, 1.3, 1.3, 1.2, 1.1, 1.1, 1.3],
}
# Final stock overrides (product name -> stock); others are derived.
STOCK_OVERRIDE = {
    "Honda Click Drive Belt": 0,
    "Yamaha MT-15 Air Filter": 8,
    "Michelin City Pro Tire 80/90-17": 4,
    "Brake Disc Rotor 220mm": 0,
    "Honda Click Seat Cover": 3,
    "Universal Chain Adjuster": 62,
    "Suzuki Raider Clutch Cable": 48,
    "LED Signal Light Set": 54,
    "Stainless Brake Hose Kit": 35,
    "K&N Universal Air Filter": 22,
    "Honda XRM Carburetor Rebuild Kit": 6,
    "Yamaha NMAX Drive Belt": 7,
    "Gear Oil 80W-90 120ml": 9,
}
PAYMENTS = ["Cash", "GCash", "Card", "Bank Transfer"]
PAY_W = [55, 28, 9, 8]
DOW_W = [0.86, 0.92, 0.95, 1.0, 1.12, 1.28, 1.15]  # Mon..Sun


def main() -> None:
    rng = random.Random(SEED)
    products = []
    for i, (name, cat, sku, price, pop, maxq, desc) in enumerate(P, start=1):
        cost = round(price * rng.uniform(0.60, 0.74), 2)
        reorder = 20 if pop >= 8 else 15 if pop >= 5 else 10 if pop >= 3 else 6
        if name in STOCK_OVERRIDE:
            stock = STOCK_OVERRIDE[name]
        else:
            stock = int(reorder * rng.uniform(1.6, 5.5) + pop * 3)
        products.append(dict(id=i, sku=sku, name=name, category=cat, description=desc, cost_price=cost,
                             selling_price=price, stock_quantity=stock, reorder_threshold=reorder,
                             pop=pop, maxq=maxq))

    days = (END - START).days + 1
    items, tx_no, per_day = [], 0, {}
    for d in range(days):
        day = START + timedelta(days=d)
        growth = 1.0 + 0.16 * (d / 365)
        payday = 1.25 if day.day in (14, 15, 16, 29, 30, 31) else 1.0
        month_all = [1.0, .97, 1.0, 1.02, 1.0, 1.05, 1.06, 1.05, 1.03, 1.03, 1.05, 1.18][day.month - 1]
        lam = 17.0 * growth * DOW_W[day.weekday()] * payday * month_all
        n_tx = max(0, round(rng.gauss(lam, math.sqrt(lam) * 0.9)))
        weights = [p["pop"] * SEASON[p["category"]][day.month - 1] for p in products]
        for _ in range(n_tx):
            tx_no += 1
            when = datetime(day.year, day.month, day.day, rng.randint(8, 19), rng.randint(0, 59), rng.randint(0, 59))
            n_lines = 1 + (rng.random() < 0.38) + (rng.random() < 0.14) + (rng.random() < 0.05)
            chosen = set()
            number = f"TX-{day:%Y%m%d}-{tx_no:05d}"
            pay = rng.choices(PAYMENTS, PAY_W)[0]
            for _l in range(n_lines):
                p = rng.choices(products, weights)[0]
                if p["id"] in chosen:
                    continue
                chosen.add(p["id"])
                qty = 1 if p["maxq"] == 1 else rng.choices(range(1, p["maxq"] + 1), [6] + [2] * (p["maxq"] - 1))[0]
                price = p["selling_price"]
                items.append(dict(transaction_number=number, transaction_date=when.strftime("%Y-%m-%d %H:%M:%S"),
                                  product_id=p["id"], sku=p["sku"], product_name=p["name"], category=p["category"],
                                  quantity=qty, unit_price=f"{price:.2f}", total_amount=f"{price * qty:.2f}",
                                  payment_method=pay))
        per_day[day] = n_tx

    (ROOT / "ml/datasets").mkdir(parents=True, exist_ok=True)
    (ROOT / "database/seeders/data").mkdir(parents=True, exist_ok=True)

    with open(ROOT / "database/seeders/data/products.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["id", "sku", "name", "category", "description", "selling_price", "stock_quantity", "reorder_threshold"])
        for p in products:
            w.writerow([p["id"], p["sku"], p["name"], p["category"], p["description"],
                        f"{p['selling_price']:.2f}", p["stock_quantity"], p["reorder_threshold"]])

    cols = ["transaction_number", "transaction_date", "product_id", "sku", "product_name", "category",
            "quantity", "unit_price", "total_amount", "payment_method"]
    with open(ROOT / "ml/datasets/sales_history.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        w.writerows(items)

    # weekly demand panel
    weeks = days // 7
    agg = {}
    for it in items:
        d = datetime.strptime(it["transaction_date"], "%Y-%m-%d %H:%M:%S").date()
        wk = (d - START).days // 7
        k = (it["product_id"], wk)
        a = agg.setdefault(k, [0, 0.0, set()])
        a[0] += it["quantity"]
        a[1] += float(it["total_amount"])
        a[2].add(it["transaction_number"])
    with open(ROOT / "ml/datasets/product_demand.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["week_start", "product_id", "sku", "product_name", "category", "units_sold", "revenue", "transactions", "avg_unit_price"])
        for wk in range(weeks):
            ws = START + timedelta(days=7 * wk)
            for p in products:
                u, rev, tx = agg.get((p["id"], wk), [0, 0.0, set()])
                w.writerow([ws.isoformat(), p["id"], p["sku"], p["name"], p["category"], u, f"{rev:.2f}", len(tx), f"{p['selling_price']:.2f}"])

    print(f"products={len(products)} transactions={tx_no} sale_items={len(items)} weeks={weeks} demand_rows={weeks*len(products)}")


if __name__ == "__main__":
    main()

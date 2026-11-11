"""Generates the product and category illustrations (SVG) used by the shop.

Run from the project root:  python database/seeders/data/generate_product_images.py
Replace any generated file with a real photo of the same name (or set products.image) when you have one.
"""
import csv
import hashlib
import re
from pathlib import Path
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parents[3]
OUT_PRODUCTS = ROOT / "public/images/products"
OUT_CATEGORIES = ROOT / "public/images/categories"

PALETTE = {  # category -> (background top, background bottom, accent)
    "Brake System": ("#fee2e2", "#fecaca", "#dc2626"),
    "Engine": ("#e0e7ff", "#c7d2fe", "#4338ca"),
    "Electrical": ("#fef9c3", "#fde68a", "#d97706"),
    "Drivetrain": ("#dcfce7", "#bbf7d0", "#15803d"),
    "Tires": ("#e2e8f0", "#cbd5e1", "#334155"),
    "Filters": ("#cffafe", "#a5f3fc", "#0e7490"),
    "Lubricants": ("#ffedd5", "#fed7aa", "#c2410c"),
    "Accessories": ("#f3e8ff", "#e9d5ff", "#7e22ce"),
}
DARK, MID, LIGHT = "#0f172a", "#475569", "#f8fafc"


def shade(accent: str, seed: str) -> str:
    """A slight per-product variation of the accent colour."""
    h = int(hashlib.md5(seed.encode()).hexdigest()[:4], 16) % 5
    r, g, b = (int(accent[i:i + 2], 16) for i in (1, 3, 5))
    k = [0.85, 0.95, 1.0, 1.1, 1.2][h]
    return "#%02x%02x%02x" % tuple(max(0, min(255, int(c * k))) for c in (r, g, b))


# ---- glyphs, all drawn inside a 300x300 box centred at (300, 190) ------------------------------------
def brake_pad(a):
    return f'''<path d="M170 215 Q170 150 230 135 L370 135 Q430 150 430 215 Z" fill="{MID}"/>
<path d="M185 215 Q185 165 235 150 L365 150 Q415 165 415 215 Z" fill="{a}"/>
<g stroke="{LIGHT}" stroke-width="5" stroke-linecap="round" opacity=".55"><path d="M240 170v35M280 165v40M320 165v40M360 170v35"/></g>
<rect x="165" y="215" width="270" height="22" rx="8" fill="{DARK}"/>'''


def brake_disc(a):
    holes = "".join(f'<circle cx="{300 + 70 * __import__("math").cos(i * 0.785):.1f}" cy="{190 + 70 * __import__("math").sin(i * 0.785):.1f}" r="9" fill="{LIGHT}"/>' for i in range(8))
    return f'''<circle cx="300" cy="190" r="115" fill="{MID}"/><circle cx="300" cy="190" r="100" fill="{a}" opacity=".9"/>{holes}
<circle cx="300" cy="190" r="34" fill="{DARK}"/><circle cx="300" cy="190" r="14" fill="{LIGHT}"/>'''


def piston(a):
    rings = "".join(f'<rect x="215" y="{y}" width="170" height="9" rx="4" fill="{DARK}"/>' for y in (118, 140, 162))
    return f'''<rect x="210" y="100" width="180" height="130" rx="22" fill="{a}"/>{rings}
<rect x="270" y="230" width="60" height="70" fill="{MID}"/><circle cx="300" cy="215" r="20" fill="{DARK}"/><circle cx="300" cy="215" r="8" fill="{LIGHT}"/>'''


def spark_plug(a):
    return f'''<rect x="272" y="82" width="56" height="30" rx="8" fill="{DARK}"/><rect x="280" y="112" width="40" height="80" rx="10" fill="{LIGHT}" stroke="{MID}" stroke-width="4"/>
<polygon points="262,192 338,192 338,222 262,222" fill="{a}"/><rect x="284" y="222" width="32" height="40" fill="{MID}"/>
<path d="M300 262v26h22" stroke="{DARK}" stroke-width="7" fill="none" stroke-linecap="round"/>'''


def battery(a):
    return f'''<rect x="190" y="130" width="220" height="130" rx="16" fill="{DARK}"/><rect x="190" y="130" width="220" height="40" rx="16" fill="{a}"/>
<rect x="222" y="112" width="30" height="20" rx="4" fill="{MID}"/><rect x="348" y="112" width="30" height="20" rx="4" fill="{MID}"/>
<path d="M310 185l-34 38h26l-10 28 38-42h-28z" fill="#fde047"/>'''


def bolt(a):
    return f'''<circle cx="300" cy="190" r="105" fill="{a}" opacity=".9"/><path d="M322 108l-76 100h48l-18 80 82-112h-52z" fill="{LIGHT}"/>'''


def gear(a, r_out=110):
    import math
    teeth = "".join(f'<rect x="{300 - 14}" y="{190 - r_out - 12}" width="28" height="30" rx="5" fill="{a}" transform="rotate({i * 30} 300 190)"/>' for i in range(12))
    return f'''{teeth}<circle cx="300" cy="190" r="{r_out - 8}" fill="{a}"/><circle cx="300" cy="190" r="{r_out - 38}" fill="{LIGHT}"/><circle cx="300" cy="190" r="24" fill="{DARK}"/>'''


def chain(a):
    links = "".join(f'<rect x="{150 + i * 55}" y="{170 + (i % 2) * 14}" width="62" height="34" rx="17" fill="none" stroke="{a if i % 2 else DARK}" stroke-width="10"/>' for i in range(6))
    return links + f'<circle cx="170" cy="260" r="0" fill="{a}"/>'


def tire(a):
    import math
    tread = "".join(f'<rect x="293" y="76" width="14" height="22" rx="3" fill="{MID}" transform="rotate({i * 18} 300 190)"/>' for i in range(20))
    spokes = "".join(f'<rect x="296" y="120" width="8" height="70" rx="4" fill="{LIGHT}" transform="rotate({i * 72} 300 190)"/>' for i in range(5))
    return f'''<circle cx="300" cy="190" r="118" fill="{DARK}"/>{tread}<circle cx="300" cy="190" r="86" fill="{MID}"/><circle cx="300" cy="190" r="70" fill="{a}"/>{spokes}<circle cx="300" cy="190" r="16" fill="{DARK}"/>'''


def filter_element(a):
    pleats = "".join(f'<rect x="{208 + i * 22}" y="124" width="12" height="128" rx="3" fill="{a}" opacity="{0.95 if i % 2 else 0.65}"/>' for i in range(8))
    return f'''<rect x="190" y="100" width="220" height="40" rx="12" fill="{DARK}"/><rect x="190" y="236" width="220" height="40" rx="12" fill="{DARK}"/>
<rect x="198" y="136" width="204" height="108" fill="{LIGHT}"/>{pleats}'''


def oil_filter(a):
    return f'''<rect x="225" y="105" width="150" height="170" rx="22" fill="{a}"/><rect x="225" y="105" width="150" height="34" rx="14" fill="{DARK}"/>
<rect x="225" y="238" width="150" height="37" rx="12" fill="{DARK}"/><rect x="245" y="160" width="110" height="52" rx="8" fill="{LIGHT}"/><rect x="258" y="176" width="84" height="8" rx="4" fill="{MID}"/><rect x="258" y="192" width="56" height="8" rx="4" fill="{MID}"/>'''


def oil_bottle(a):
    return f'''<rect x="270" y="78" width="60" height="38" rx="8" fill="{DARK}"/><path d="M262 116h76l20 28v120a20 20 0 0 1-20 20H262a20 20 0 0 1-20-20V144z" fill="{a}"/>
<rect x="250" y="168" width="100" height="70" rx="10" fill="{LIGHT}"/><path d="M300 178c-14 18-20 28-20 38a20 20 0 0 0 40 0c0-10-6-20-20-38z" fill="{a}"/>'''


def spray(a):
    return f'''<rect x="270" y="72" width="60" height="30" rx="8" fill="{MID}"/><rect x="250" y="100" width="100" height="180" rx="20" fill="{a}"/>
<rect x="262" y="150" width="76" height="76" rx="10" fill="{LIGHT}"/><path d="M300 162c-10 14-14 20-14 28a14 14 0 0 0 28 0c0-8-4-14-14-28z" fill="{a}"/>'''


def helmet(a):
    return f'''<path d="M180 220a120 120 0 0 1 240 0v30a20 20 0 0 1-20 20H200a20 20 0 0 1-20-20z" fill="{a}"/><path d="M180 218h240" stroke="{DARK}" stroke-width="8"/>
<path d="M205 200h190v28H205z" fill="{DARK}" opacity=".85"/><path d="M215 150a100 100 0 0 1 90-54" stroke="{LIGHT}" stroke-width="10" fill="none" stroke-linecap="round" opacity=".55"/>'''


def mirror(a):
    return f'''<rect x="292" y="190" width="16" height="96" rx="6" fill="{MID}"/><ellipse cx="300" cy="140" rx="92" ry="64" fill="{DARK}"/><ellipse cx="300" cy="140" rx="78" ry="50" fill="{a}" opacity=".8"/>
<path d="M240 125q40-30 100-10" stroke="{LIGHT}" stroke-width="8" fill="none" stroke-linecap="round" opacity=".6"/>'''


def phone_holder(a):
    return f'''<rect x="240" y="92" width="120" height="196" rx="20" fill="{DARK}"/><rect x="250" y="108" width="100" height="160" rx="8" fill="{a}" opacity=".85"/>
<rect x="215" y="150" width="26" height="14" rx="6" fill="{MID}"/><rect x="359" y="150" width="26" height="14" rx="6" fill="{MID}"/><circle cx="300" cy="278" r="5" fill="{LIGHT}"/>'''


def glove(a):
    fingers = "".join(f'<rect x="{214 + i * 36}" y="{96 + abs(i - 1.5) * 12:.0f}" width="30" height="92" rx="14" fill="{a}"/>' for i in range(4))
    return f'''{fingers}<rect x="208" y="172" width="150" height="104" rx="26" fill="{a}"/><rect x="190" y="206" width="52" height="30" rx="14" fill="{a}" transform="rotate(-25 216 220)"/><rect x="214" y="256" width="150" height="26" rx="8" fill="{DARK}"/>'''


def cover(a):
    return f'''<path d="M170 250c0-90 50-140 130-140s130 50 130 140z" fill="{a}"/><circle cx="215" cy="262" r="26" fill="{DARK}"/><circle cx="385" cy="262" r="26" fill="{DARK}"/><circle cx="215" cy="262" r="10" fill="{LIGHT}"/><circle cx="385" cy="262" r="10" fill="{LIGHT}"/>'''


def seat(a):
    return f'''<path d="M150 210c10-70 90-96 150-80 40-14 110 0 150 70 4 16-8 30-24 30H178c-16 0-30-6-28-20z" fill="{a}"/><rect x="236" y="240" width="130" height="30" rx="8" fill="{DARK}"/>'''


def grip(a):
    ridges = "".join(f'<rect x="{204 + i * 26}" y="146" width="10" height="88" rx="5" fill="{LIGHT}" opacity=".5"/>' for i in range(6))
    return f'''<rect x="172" y="140" width="256" height="100" rx="40" fill="{a}"/>{ridges}<rect x="428" y="170" width="20" height="40" rx="6" fill="{MID}"/>'''


def tube(a):
    return f'''<circle cx="300" cy="190" r="110" fill="none" stroke="{DARK}" stroke-width="44"/><circle cx="300" cy="190" r="110" fill="none" stroke="{a}" stroke-width="14"/><rect x="288" y="62" width="24" height="42" rx="6" fill="{MID}"/>'''


def cable(a):
    return f'''<path d="M150 260c70-130 130 60 200-40s60-80 110-100" stroke="{DARK}" stroke-width="14" fill="none" stroke-linecap="round"/><path d="M150 260c70-130 130 60 200-40s60-80 110-100" stroke="{a}" stroke-width="6" fill="none" stroke-linecap="round"/>
<circle cx="150" cy="260" r="16" fill="{MID}"/><circle cx="460" cy="120" r="16" fill="{MID}"/>'''


def coolant(a):
    return f'''<rect x="244" y="96" width="112" height="190" rx="22" fill="{a}"/><rect x="274" y="76" width="52" height="26" rx="8" fill="{DARK}"/><rect x="244" y="150" width="112" height="82" fill="{LIGHT}" opacity=".9"/><path d="M300 164c-14 20-18 28-18 36a18 18 0 0 0 36 0c0-8-4-16-18-36z" fill="{a}"/>'''


def pick(category, name):
    n = name.lower()
    if category == "Brake System":
        if "disc" in n or "rotor" in n: return brake_disc
        if "hose" in n: return cable
        return brake_pad
    if category == "Engine":
        if "coolant" in n: return coolant
        if "cable" in n: return cable
        if "roller" in n or "pulley" in n: return lambda a: gear(a, 100)
        return piston
    if category == "Electrical":
        if "spark" in n: return spark_plug
        if "battery" in n: return battery
        return bolt
    if category == "Drivetrain":
        if "chain" in n and "adjuster" not in n: return chain
        if "belt" in n: return lambda a: gear(a, 92)
        if "cable" in n: return cable
        return gear
    if category == "Tires":
        if "tube" in n or "valve" in n: return tube
        if "sealant" in n: return spray
        return tire
    if category == "Filters":
        return oil_filter if "oil" in n or "fuel" in n else filter_element
    if category == "Lubricants":
        return spray if "lube" in n or "spray" in n else oil_bottle
    if category == "Accessories":
        if "helmet" in n: return helmet
        if "mirror" in n: return mirror
        if "phone" in n or "usb" in n: return phone_holder
        if "glove" in n: return glove
        if "rain" in n: return cover
        if "seat" in n: return seat
        return grip
    return bolt


def svg(category, label, sub, glyph, accent):
    top, bottom, _ = PALETTE.get(category, PALETTE["Accessories"])
    uid = re.sub(r"\W", "", label)[:12] or "x"
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 450" role="img" aria-label="{escape(label)}">
<defs><linearGradient id="bg{uid}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{top}"/><stop offset="1" stop-color="{bottom}"/></linearGradient></defs>
<rect width="600" height="450" fill="url(#bg{uid})"/>
<circle cx="300" cy="190" r="150" fill="#ffffff" opacity=".55"/>
<ellipse cx="300" cy="332" rx="130" ry="14" fill="#0f172a" opacity=".12"/>
{glyph(accent)}
<rect x="40" y="368" width="520" height="54" rx="14" fill="#ffffff" opacity=".92"/>
<text x="300" y="393" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-size="22" font-weight="700" fill="#0f172a">{escape(label)}</text>
<text x="300" y="413" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-size="14" fill="#64748b">{escape(sub)}</text>
</svg>
'''


def main():
    OUT_PRODUCTS.mkdir(parents=True, exist_ok=True)
    OUT_CATEGORIES.mkdir(parents=True, exist_ok=True)
    with open(ROOT / "database/seeders/data/products.csv", newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))
    for row in rows:
        words = row["name"].split()
        label = " ".join(words[:3]) if len(" ".join(words[:3])) <= 30 else " ".join(words[:2])
        accent = shade(PALETTE[row["category"]][2], row["sku"])
        (OUT_PRODUCTS / f"{row['sku']}.svg").write_text(svg(row["category"], label, f"{row['category']} · {row['sku']}", pick(row["category"], row["name"]), accent), encoding="utf-8")
    for category, (_, _, accent) in PALETTE.items():
        glyph = {"Brake System": brake_pad, "Engine": piston, "Electrical": spark_plug, "Drivetrain": gear, "Tires": tire,
                 "Filters": filter_element, "Lubricants": oil_bottle, "Accessories": helmet}[category]
        slug = re.sub(r"[^a-z0-9]+", "-", category.lower()).strip("-")
        (OUT_CATEGORIES / f"{slug}.svg").write_text(svg(category, category, "Motorcycle Parts", glyph, accent), encoding="utf-8")
    (OUT_CATEGORIES / "general.svg").write_text(svg("Accessories", "Motorcycle Part", "One Prompt Away", bolt, PALETTE["Accessories"][2]), encoding="utf-8")
    print(f"{len(rows)} product images, {len(PALETTE) + 1} category images")


if __name__ == "__main__":
    main()

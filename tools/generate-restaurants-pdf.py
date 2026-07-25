#!/usr/bin/env python3
"""
Génère un PDF listant les restaurants sans dessert probable (92, 93, 94).
Usage : python3 tools/generate-restaurants-pdf.py
"""
from __future__ import annotations

import csv
from collections import defaultdict
from datetime import date
from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parent.parent
CSV_PATH = ROOT / "outils" / "restaurants-sans-dessert-92-93-94.csv"
PDF_PATHS = [
    ROOT / "outils" / "restaurants-sans-dessert-92-93-94.pdf",
    ROOT / "a-telecharger" / "restaurants-sans-dessert-92-93-94.pdf",
]

DEPT_LABELS = {
    "92": "Hauts-de-Seine (92)",
    "93": "Seine-Saint-Denis (93)",
    "94": "Val-de-Marne (94)",
}


def load_rows() -> list[dict[str, str]]:
    with CSV_PATH.open(encoding="utf-8", newline="") as f:
        return list(csv.DictReader(f))


def clean(value: str | None) -> str:
    if not value:
        return ""
    text = (
        value.replace("\n", " ")
        .replace("\r", " ")
        .strip()
    )
    replacements = {
        "—": "-",
        "–": "-",
        "’": "'",
        "‘": "'",
        "“": '"',
        "”": '"',
        "…": "...",
        "œ": "oe",
        "Œ": "Oe",
        "æ": "ae",
        "Æ": "Ae",
    }
    for src, dst in replacements.items():
        text = text.replace(src, dst)
    return text.encode("latin-1", errors="replace").decode("latin-1")


def to_pdf_text(value: str) -> str:
    return clean(value)


class RestaurantPDF(FPDF):
    def header(self) -> None:
        if self.page_no() == 1:
            return
        self.set_font("Helvetica", "I", 8)
        self.set_text_color(100, 100, 100)
        self.cell(0, 8, "Casa Dessert - Restaurants sans dessert probable (92, 93, 94)", align="L")
        self.cell(0, 8, f"Page {self.page_no()}", align="R", new_x="LMARGIN", new_y="NEXT")
        self.ln(1)

    def footer(self) -> None:
        self.set_y(-12)
        self.set_font("Helvetica", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 10, f"Genere le {date.today().strftime('%d/%m/%Y')} - Source OpenStreetMap", align="C")


def build_pdf(rows: list[dict[str, str]]) -> bytes:
    pdf = RestaurantPDF(orientation="L", unit="mm", format="A4")
    pdf.set_auto_page_break(auto=True, margin=14)
    pdf.add_page()

    total = len(rows)
    by_dept: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in rows:
        by_dept[row["dept"]].append(row)

    # Page de garde
    pdf.set_font("Helvetica", "B", 22)
    pdf.set_text_color(61, 46, 36)
    pdf.cell(0, 14, to_pdf_text("Casa Dessert"), new_x="LMARGIN", new_y="NEXT", align="C")
    pdf.ln(2)
    pdf.set_font("Helvetica", "B", 16)
    pdf.set_text_color(26, 26, 26)
    pdf.cell(0, 10, to_pdf_text("Liste des restaurants sans dessert probable"), new_x="LMARGIN", new_y="NEXT", align="C")
    pdf.ln(4)
    pdf.set_font("Helvetica", "", 11)
    pdf.set_text_color(80, 80, 80)
    pdf.multi_cell(
        0,
        6,
        to_pdf_text(
            f"Zone : Hauts-de-Seine (92), Seine-Saint-Denis (93), Val-de-Marne (94)\n"
            f"Total : {total:,} etablissements".replace(",", " ")
            + "\n"
            "Classification heuristique (fast-food, kebab, asiatique, etc.) - a verifier sur place ou via carte en ligne."
        ),
        align="C",
    )
    pdf.ln(6)
    pdf.set_font("Helvetica", "B", 11)
    pdf.set_text_color(26, 26, 26)
    pdf.cell(0, 8, to_pdf_text("Repartition par departement"), new_x="LMARGIN", new_y="NEXT", align="C")
    pdf.set_font("Helvetica", "", 10)
    for dept in ("92", "93", "94"):
        count = len(by_dept.get(dept, []))
        label = DEPT_LABELS.get(dept, dept)
        pdf.cell(0, 7, to_pdf_text(f"- {label} : {count:,} etablissements".replace(",", " ")), new_x="LMARGIN", new_y="NEXT", align="C")

    col_widths = [8, 48, 72, 34, 14, 28, 24, 52]
    headers = ["#", "Nom", "Adresse", "Ville", "CP", "Type", "Cuisine", "Raison"]

    row_num = 0
    for dept in ("92", "93", "94"):
        dept_rows = by_dept.get(dept, [])
        if not dept_rows:
            continue

        pdf.add_page()
        pdf.set_font("Helvetica", "B", 13)
        pdf.set_text_color(61, 46, 36)
        pdf.cell(0, 10, to_pdf_text(DEPT_LABELS.get(dept, dept)), new_x="LMARGIN", new_y="NEXT")
        pdf.set_font("Helvetica", "", 9)
        pdf.set_text_color(80, 80, 80)
        pdf.cell(0, 6, to_pdf_text(f"{len(dept_rows)} etablissements"), new_x="LMARGIN", new_y="NEXT")
        pdf.ln(2)

        pdf.set_fill_color(237, 228, 216)
        pdf.set_text_color(26, 26, 26)
        pdf.set_font("Helvetica", "B", 7.5)
        for header, width in zip(headers, col_widths):
            pdf.cell(width, 7, header, border=1, fill=True)
        pdf.ln()

        pdf.set_font("Helvetica", "", 7)
        fill = False
        for row in dept_rows:
            row_num += 1
            values = [
                str(row_num),
                clean(row.get("nom")),
                clean(row.get("adresse")),
                clean(row.get("ville")),
                clean(row.get("cp")),
                clean(row.get("type")),
                clean(row.get("cuisine")),
                clean(row.get("raison")),
            ]
            line_height = 5
            x0 = pdf.get_x()
            y0 = pdf.get_y()
            max_lines = 1
            wrapped: list[list[str]] = []
            for value, width in zip(values, col_widths):
                lines = pdf.multi_cell(width, line_height, value, dry_run=True, output="LINES")
                if not lines:
                    lines = [""]
                wrapped.append(lines)
                max_lines = max(max_lines, len(lines))

            row_height = max_lines * line_height
            if y0 + row_height > pdf.h - pdf.b_margin:
                pdf.add_page()
                pdf.set_fill_color(237, 228, 216)
                pdf.set_font("Helvetica", "B", 7.5)
                for header, width in zip(headers, col_widths):
                    pdf.cell(width, 7, header, border=1, fill=True)
                pdf.ln()
                pdf.set_font("Helvetica", "", 7)
                x0 = pdf.get_x()
                y0 = pdf.get_y()

            x = x0
            if fill:
                pdf.set_fill_color(248, 246, 242)
            else:
                pdf.set_fill_color(255, 255, 255)

            for value_lines, width in zip(wrapped, col_widths):
                pdf.set_xy(x, y0)
                pdf.multi_cell(
                    width,
                    line_height,
                    "\n".join(value_lines),
                    border=1,
                    fill=fill,
                    max_line_height=line_height,
                )
                x += width

            pdf.set_xy(x0, y0 + row_height)
            fill = not fill

    return pdf.output()


def main() -> None:
    if not CSV_PATH.exists():
        raise SystemExit(f"Fichier introuvable : {CSV_PATH}")

    rows = load_rows()
    pdf_bytes = build_pdf(rows)

    for path in PDF_PATHS:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(pdf_bytes)
        print(f"Écrit : {path} ({len(rows)} restaurants)")


if __name__ == "__main__":
    main()

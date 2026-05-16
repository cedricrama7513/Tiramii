#!/usr/bin/env python3
"""Génère suivi-factures-restaurants-pro.xlsx (Excel Mac / Windows, formules + aperçu solde)."""
from __future__ import annotations

import json
from pathlib import Path

import xlsxwriter

ROOT = Path(__file__).resolve().parent.parent
TOOLS = Path(__file__).resolve().parent
CLIENTS_JSON = TOOLS / "pro-clients.json"
OUT_DIRS = [ROOT / "outils", ROOT / "a-telecharger"]
BASE_NAME = "suivi-factures-restaurants-pro.xlsx"

MAX_ROW = 120
ALERT_THRESHOLD = 500
DEPOSIT_AMOUNT = 500
LAST_PREFILL_ROW = 13

HEADERS = [
    "Date facture",
    "N° facture",
    "Montant facture (€)",
    "Versement (€)",
    "Solde (€)",
    "Alerte solde",
    "Payé ? (Oui / Non)",
    "Alerte facture > 500 €",
    "Encours impayé (€)",
    "Alerte encours",
    "Message envoyé ?",
    "Date relance",
]


def load_restaurants() -> list[dict]:
    raw = json.loads(CLIENTS_JSON.read_text(encoding="utf-8"))
    clients = raw.get("clients") or []
    if not clients:
        raise SystemExit("pro-clients.json : clients vide")
    return clients


def pad2(n: int) -> str:
    return f"{n:02d}"


def build_invoice_rows(restaurant: dict) -> list[dict]:
    rows = []
    prefix = restaurant.get("invoicePrefix", "FAC")
    paid_months = set(restaurant.get("paidMonths") or [])
    deposit_months = set(restaurant.get("depositMonths") or restaurant.get("paidMonths") or [])
    for month in range(1, 13):
        paid = month in paid_months
        deposit = DEPOSIT_AMOUNT if month in deposit_months else 0
        rows.append(
            {
                "date": f"2026-{pad2(month)}-01",
                "inv": f"{prefix}-2026-{pad2(month)}",
                "deposit": deposit,
                "paid": "Oui" if paid else "Non",
                "message": "Oui" if paid else "Non",
                "relance": f"2026-{pad2(month)}-15" if paid else "",
            }
        )
    return rows


def solde_formula(row: int, opening: float) -> str:
    """Formules en anglais dans le fichier (Excel Mac FR les affiche en SOMME)."""
    if opening > 0:
        return f"={opening}+SUM($D$2:D{row})-SUM($C$2:C{row})"
    return f"=SUM($D$2:D{row})-SUM($C$2:C{row})"


def preview_solde(prefill: list[dict], row: int, opening: float) -> float:
    """Aperçu initial (C vide) : versements cumulés — pour la valeur cache Mac Excel."""
    dep_sum = sum(
        (prefill[i]["deposit"] if i < len(prefill) else 0) for i in range(row - 1)
    )
    return round(opening + dep_sum, 2)


def sheet_ref(name: str) -> str:
    return "'" + name.replace("'", "''") + "'"


def build_workbook(path: Path, restaurants: list[dict]) -> None:
    wb = xlsxwriter.Workbook(str(path))
    wb.set_calc_mode("auto")

    fmt_header = wb.add_format(
        {"bold": True, "font_color": "#FFFFFF", "bg_color": "#3D2E24", "align": "center", "valign": "vcenter", "text_wrap": True}
    )
    fmt_solde_header = wb.add_format(
        {"bold": True, "font_color": "#FFFFFF", "bg_color": "#1B5E20", "align": "center", "valign": "vcenter", "text_wrap": True}
    )
    fmt_input = wb.add_format({"num_format": "#,##0.00", "bg_color": "#FFFDE7"})
    fmt_solde = wb.add_format({"num_format": "#,##0.00", "bg_color": "#E8F5E9", "bold": True})
    fmt_alert = wb.add_format({"bold": True, "font_color": "#B00020", "text_wrap": True})
    fmt_money = wb.add_format({"num_format": "#,##0.00"})

    solde_cols = {3, 4, 5}

    for restaurant in restaurants:
        name = str(restaurant.get("sheetName") or restaurant["name"])[:31]
        ws = wb.add_worksheet(name)
        ws.freeze_panes(1, 0)

        widths = [12, 18, 11, 14, 12, 28, 10, 26, 14, 26, 14, 12]
        for col, w in enumerate(widths):
            ws.set_column(col, col, w)

        for col, title in enumerate(HEADERS):
            ws.write(0, col, title, fmt_solde_header if col in solde_cols else fmt_header)

        opening = float(restaurant.get("openingBalance") or 0)
        prefill = build_invoice_rows(restaurant)

        for r in range(2, MAX_ROW + 1):
            xr = r - 1
            data = prefill[r - 2] if r - 2 < len(prefill) else None

            if data:
                ws.write(xr, 0, data["date"])
                ws.write(xr, 1, data["inv"])
            ws.write_blank(xr, 2, None, fmt_input)

            dep = data["deposit"] if data else 0
            ws.write_number(xr, 3, dep, fmt_input)

            formula = solde_formula(r, opening)
            cached = preview_solde(prefill, r, opening)
            ws.write_formula(xr, 4, formula, fmt_solde, cached)

            ws.write_formula(
                xr,
                5,
                f'=IF(E{r}<=0,"⚠ SOLDE ÉPUISÉ — faire verser {DEPOSIT_AMOUNT} €","")',
                fmt_alert,
                "",
            )
            ws.write(xr, 6, data["paid"] if data else "Non")
            ws.write_formula(
                xr,
                7,
                f'=IF(C{r}>{ALERT_THRESHOLD},"⚠ Facture > {ALERT_THRESHOLD} € — à relancer","")',
                fmt_alert,
                "",
            )
            ws.write_formula(
                xr,
                8,
                f'=SUM($C$2:$C${MAX_ROW})-SUMIF($G$2:$G${MAX_ROW},"Oui",$C$2:$C${MAX_ROW})',
                fmt_money,
                0,
            )
            ws.write_formula(
                xr,
                9,
                f'=IF(I{r}>{ALERT_THRESHOLD},"⚠ ENVOYER MESSAGE PAIEMENT","")',
                fmt_alert,
                "",
            )
            ws.write(xr, 10, data["message"] if data else "Non")
            if data and data["relance"]:
                ws.write(xr, 11, data["relance"])

    ws_syn = wb.add_worksheet("Synthèse")
    syn_headers = [
        "Restaurant",
        "Solde crédit actuel (€)",
        "Alerte solde",
        "Encours impayé (€)",
        "Alerte encours",
    ]
    for col, h in enumerate(syn_headers):
        ws_syn.write(0, col, h, fmt_header)
    for idx, restaurant in enumerate(restaurants, start=1):
        ref = sheet_ref(str(restaurant.get("sheetName") or restaurant["name"])[:31])
        ws_syn.write(idx, 0, restaurant["name"])
        cached_e = preview_solde(build_invoice_rows(restaurant), LAST_PREFILL_ROW, float(restaurant.get("openingBalance") or 0))
        ws_syn.write_formula(idx, 1, f"={ref}!E{LAST_PREFILL_ROW}", fmt_money, cached_e)
        ws_syn.write_formula(
            idx,
            2,
            f'=IF(B{idx + 1}<=0,"⚠ RECHARGER {DEPOSIT_AMOUNT} €","OK")',
            fmt_alert,
            "OK",
        )
        ws_syn.write_formula(
            idx,
            3,
            f'=SUM({ref}!$C$2:$C${MAX_ROW})-SUMIF({ref}!$G$2:$G${MAX_ROW},"Oui",{ref}!$C$2:$C${MAX_ROW})',
            fmt_money,
            0,
        )
        ws_syn.write_formula(
            idx,
            4,
            f'=IF(D{idx + 1}>{ALERT_THRESHOLD},"⚠ RELANCER","OK")',
            fmt_alert,
            "OK",
        )

    ws_help = wb.add_worksheet("Mode emploi")
    ws_help.set_column(0, 0, 78)
    names = " · ".join(r["name"] for r in restaurants)
    lines = [
        f"1. Un onglet par restaurant ({names}).",
        "2. Colonne C : montant facture → colonne E (solde) se met à jour.",
        f"3. Colonne D : versement client ({DEPOSIT_AMOUNT} €).",
        "4. EXCEL MAC : en haut, cliquez « Activer la modification » (bannière jaune).",
        "5. Saisissez le montant en C, puis touche Entrée ou Tab.",
        "6. Si le solde ne bouge pas : Excel → Préférences → Calcul → Automatique.",
        "7. Retéléchargez le .xlsx sur casadessert.fr/outils/ (pas l’ancien .xls).",
    ]
    for i, line in enumerate(lines):
        ws_help.write(i, 0, line)

    wb.close()


def main() -> None:
    restaurants = load_restaurants()
    for out_dir in OUT_DIRS:
        out_dir.mkdir(parents=True, exist_ok=True)
        path = out_dir / BASE_NAME
        build_workbook(path, restaurants)
        print("Written", path)


if __name__ == "__main__":
    main()

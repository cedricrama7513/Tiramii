#!/usr/bin/env python3
"""Génère rentabilite-casa-dessert.xlsx — CA, charges, bénéfice, BEP."""
from __future__ import annotations

from pathlib import Path

import xlsxwriter

ROOT = Path(__file__).resolve().parent.parent
OUT_DIRS = [ROOT / "outils", ROOT / "a-telecharger"]
BASE_NAME = "rentabilite-casa-dessert.xlsx"

MONTHS = [
    "Janvier",
    "Février",
    "Mars",
    "Avril",
    "Mai",
    "Juin",
    "Juillet",
    "Août",
    "Septembre",
    "Octobre",
    "Novembre",
    "Décembre",
]
MONTH_COLS = list(range(1, 13))  # B=1 .. M=12
TOTAL_COL = 13  # N
LABEL_COL = 0  # A


def col_letter(n: int) -> str:
    return chr(ord("A") + n)


def build_workbook(path: Path) -> None:
    wb = xlsxwriter.Workbook(str(path))
    wb.set_calc_mode("auto")

    fmt_title = wb.add_format(
        {"bold": True, "font_size": 14, "font_color": "#3D2E24", "bottom": 2}
    )
    fmt_header = wb.add_format(
        {
            "bold": True,
            "font_color": "#FFFFFF",
            "bg_color": "#3D2E24",
            "align": "center",
            "valign": "vcenter",
            "border": 1,
        }
    )
    fmt_section = wb.add_format(
        {"bold": True, "bg_color": "#E8E0D8", "font_color": "#3D2E24", "border": 1}
    )
    fmt_label = wb.add_format({"border": 1, "valign": "vcenter"})
    fmt_input = wb.add_format(
        {"num_format": "#,##0.00", "bg_color": "#FFFDE7", "border": 1}
    )
    fmt_input_pct = wb.add_format(
        {"num_format": "0.0%", "bg_color": "#FFFDE7", "border": 1}
    )
    fmt_money = wb.add_format({"num_format": "#,##0.00 €", "border": 1})
    fmt_money_bold = wb.add_format(
        {"num_format": "#,##0.00 €", "bold": True, "border": 1, "bg_color": "#E8F5E9"}
    )
    fmt_pct = wb.add_format({"num_format": "0.0%", "border": 1})
    fmt_pct_bold = wb.add_format(
        {"num_format": "0.0%", "bold": True, "border": 1, "bg_color": "#E8F5E9"}
    )
    fmt_kpi_label = wb.add_format({"bold": True, "font_size": 11})
    fmt_kpi_val = wb.add_format(
        {"bold": True, "font_size": 12, "num_format": "#,##0.00 €", "font_color": "#1B5E20"}
    )
    fmt_kpi_pct = wb.add_format(
        {"bold": True, "font_size": 12, "num_format": "0.0%", "font_color": "#1B5E20"}
    )
    fmt_alert = wb.add_format({"bold": True, "font_color": "#B00020", "text_wrap": True})

    # --- Feuille Synthèse ---
    ws_s = wb.add_worksheet("Synthèse")
    ws_s.set_column(0, 0, 34)
    ws_s.set_column(1, 1, 18)
    ws_s.write(0, 0, "Rentabilité Casa Dessert — Synthèse annuelle", fmt_title)
    ws_s.write(2, 0, "Indicateur", fmt_kpi_label)
    ws_s.write(2, 1, "Valeur", fmt_kpi_label)

    # Références vers feuille Mensuel (lignes 1-indexed dans Excel)
    # Row 6 = total CA, 12 = marge brute, 20 = charges fixes, 22 = bénéfice
    ws_s.write(3, 0, "Chiffre d'affaires annuel")
    ws_s.write_formula(3, 1, "=Mensuel!N8", fmt_kpi_val, 0)
    ws_s.write(4, 0, "Marge brute annuelle")
    ws_s.write_formula(4, 1, "=Mensuel!N15", fmt_kpi_val, 0)
    ws_s.write(5, 0, "Marge brute (%)")
    ws_s.write_formula(5, 1, "=IF(Mensuel!N8=0,0,Mensuel!N15/Mensuel!N8)", fmt_kpi_pct, 0)
    ws_s.write(6, 0, "Charges fixes annuelles")
    ws_s.write_formula(6, 1, "=Mensuel!N25", fmt_kpi_val, 0)
    ws_s.write(7, 0, "Bénéfice net annuel")
    ws_s.write_formula(7, 1, "=Mensuel!N27", fmt_kpi_val, 0)
    ws_s.write(8, 0, "Rentabilité nette (%)")
    ws_s.write_formula(8, 1, "=IF(Mensuel!N8=0,0,Mensuel!N27/Mensuel!N8)", fmt_kpi_pct, 0)
    ws_s.write(10, 0, "CA mensuel moyen")
    ws_s.write_formula(10, 1, "=Mensuel!N8/12", fmt_kpi_val, 0)
    ws_s.write(11, 0, "Bénéfice mensuel moyen")
    ws_s.write_formula(11, 1, "=Mensuel!N27/12", fmt_kpi_val, 0)
    ws_s.write(13, 0, "Seuil de rentabilité (BEP) — CA/mois")
    ws_s.write_formula(
        13,
        1,
        "=IF(Mensuel!N8=0,0,Mensuel!N25/12/(Mensuel!N15/Mensuel!N8))",
        fmt_kpi_val,
        0,
    )
    ws_s.write(15, 0, "Alerte", fmt_alert)
    ws_s.write_formula(
        15,
        1,
        '=IF(Mensuel!N27<0,"⚠ Perte : augmentez le CA ou baissez les charges",IF(Mensuel!N8/12<(Mensuel!N25/12)/(Mensuel!N15/Mensuel!N8),"⚠ Sous le BEP en moyenne","✓ Rentable"))',
        fmt_alert,
        "",
    )

    # --- Feuille Mensuel ---
    ws = wb.add_worksheet("Mensuel")
    ws.freeze_panes(3, 1)
    ws.set_column(0, 0, 32)
    for c in MONTH_COLS:
        ws.set_column(c, c, 11)
    ws.set_column(TOTAL_COL, TOTAL_COL, 13)

    ws.merge_range(0, 0, 0, TOTAL_COL, "Suivi mensuel — saisissez les cellules jaunes", fmt_title)
    ws.write(2, LABEL_COL, "Poste", fmt_header)
    for i, m in enumerate(MONTHS):
        ws.write(2, MONTH_COLS[i], m[:4], fmt_header)
    ws.write(2, TOTAL_COL, "TOTAL année", fmt_header)

    def row_sum_formula(r: int) -> str:
        b, m = col_letter(1), col_letter(12)
        return f"=SUM({b}{r}:{m}{r})"

    def row_diff_formula(r_a: int, r_b: int) -> str:
        return f"={col_letter(1)}{r_a}-{col_letter(1)}{r_b}"

    # Lignes Excel 1-based
    R = {
        "ca_b2b": 4,
        "ca_b2c": 5,
        "ca_atelier": 6,
        "ca_autre": 7,
        "ca_total": 8,
        "blank1": 9,
        "pct_mat": 10,
        "cout_mat": 11,
        "cout_emb": 12,
        "cout_liv": 13,
        "cout_var_total": 14,
        "marge_brute": 15,
        "marge_brute_pct": 16,
        "blank2": 17,
        "fix_loyer": 18,
        "fix_energie": 19,
        "fix_assur": 20,
        "fix_compta": 21,
        "fix_salaires": 22,
        "fix_vehicule": 23,
        "fix_autres": 24,
        "fix_total": 25,
        "blank3": 26,
        "benefice": 27,
        "rentabilite_pct": 28,
        "cumul": 29,
    }

    rows_def = [
        (R["ca_b2b"], "CA — Restaurants B2B", fmt_input, True),
        (R["ca_b2c"], "CA — B2C / événements (jours off)", fmt_input, True),
        (R["ca_atelier"], "CA — Ateliers", fmt_input, True),
        (R["ca_autre"], "CA — Autre", fmt_input, True),
        (R["ca_total"], "TOTAL chiffre d'affaires", fmt_money_bold, False),
        (R["blank1"], "", None, False),
        (R["pct_mat"], "% matières premières (sur CA)", fmt_input_pct, True),
        (R["cout_mat"], "Coût matières premières", fmt_money, False),
        (R["cout_emb"], "Emballage & conditionnement", fmt_input, True),
        (R["cout_liv"], "Livraison & logistique", fmt_input, True),
        (R["cout_var_total"], "TOTAL coûts variables", fmt_money_bold, False),
        (R["marge_brute"], "MARGE BRUTE", fmt_money_bold, False),
        (R["marge_brute_pct"], "Marge brute (%)", fmt_pct_bold, False),
        (R["blank2"], "", None, False),
        (R["fix_loyer"], "Loyer labo / cuisine", fmt_input, True),
        (R["fix_energie"], "Énergie (élec, gaz)", fmt_input, True),
        (R["fix_assur"], "Assurance pro", fmt_input, True),
        (R["fix_compta"], "Comptable", fmt_input, True),
        (R["fix_salaires"], "Salaires / apprentis", fmt_input, True),
        (R["fix_vehicule"], "Véhicule", fmt_input, True),
        (R["fix_autres"], "Autres charges fixes", fmt_input, True),
        (R["fix_total"], "TOTAL charges fixes", fmt_money_bold, False),
        (R["blank3"], "", None, False),
        (R["benefice"], "BÉNÉFICE NET", fmt_money_bold, False),
        (R["rentabilite_pct"], "Rentabilité nette (%)", fmt_pct_bold, False),
        (R["cumul"], "Bénéfice cumulé", fmt_money, False),
    ]

    ca_total = R["ca_total"]
    pct_mat = R["pct_mat"]
    cout_mat = R["cout_mat"]
    cout_emb = R["cout_emb"]
    cout_liv = R["cout_liv"]
    cout_var = R["cout_var_total"]
    marge = R["marge_brute"]
    marge_pct = R["marge_brute_pct"]
    fix_start = R["fix_loyer"]
    fix_end = R["fix_autres"]
    fix_total = R["fix_total"]
    benefice = R["benefice"]
    rent_pct = R["rentabilite_pct"]
    cumul = R["cumul"]

    for row_num, label, cell_fmt, is_input in rows_def:
        if label == "":
            continue
        if label.startswith("CA —") or label.startswith("TOTAL CA") or label.startswith("MARGE") or label.startswith("BÉNÉFICE") or label.startswith("TOTAL chiffre") or label.startswith("TOTAL coûts") or label.startswith("TOTAL charges"):
            if label.startswith("TOTAL") or label.startswith("MARGE") or label.startswith("BÉNÉFICE"):
                ws.write(row_num - 1, LABEL_COL, label, fmt_section)
            else:
                ws.write(row_num - 1, LABEL_COL, label, fmt_label)
        elif label.startswith("%") or "charges fixes" in label.lower() or label in (
            "Emballage & conditionnement",
            "Livraison & logistique",
            "Loyer labo / cuisine",
            "Énergie (élec, gaz)",
            "Assurance pro",
            "Comptable",
            "Salaires / apprentis",
            "Véhicule",
            "Autres charges fixes",
        ):
            ws.write(row_num - 1, LABEL_COL, label, fmt_label)
        else:
            ws.write(row_num - 1, LABEL_COL, label, fmt_label)

        for mc in MONTH_COLS:
            c = col_letter(mc)
            xr = row_num - 1
            if is_input:
                default = 0 if "pct" not in str(cell_fmt.num_format) else 0.35
                if row_num == pct_mat:
                    ws.write_number(xr, mc, 0.35, fmt_input_pct)
                else:
                    ws.write_number(xr, mc, 0, cell_fmt)
            elif row_num == ca_total:
                ws.write_formula(
                    xr,
                    mc,
                    f"=SUM({c}{R['ca_b2b']}:{c}{R['ca_autre']})",
                    fmt_money_bold,
                    0,
                )
            elif row_num == cout_mat:
                ws.write_formula(
                    xr,
                    mc,
                    f"=IF({c}{ca_total}=0,0,{c}{ca_total}*{c}{pct_mat})",
                    fmt_money,
                    0,
                )
            elif row_num == cout_var:
                ws.write_formula(
                    xr,
                    mc,
                    f"={c}{cout_mat}+{c}{cout_emb}+{c}{cout_liv}",
                    fmt_money_bold,
                    0,
                )
            elif row_num == marge:
                ws.write_formula(
                    xr, mc, f"={c}{ca_total}-{c}{cout_var}", fmt_money_bold, 0
                )
            elif row_num == marge_pct:
                ws.write_formula(
                    xr,
                    mc,
                    f"=IF({c}{ca_total}=0,0,{c}{marge}/{c}{ca_total})",
                    fmt_pct_bold,
                    0,
                )
            elif row_num == fix_total:
                ws.write_formula(
                    xr,
                    mc,
                    f"=SUM({c}{fix_start}:{c}{fix_end})",
                    fmt_money_bold,
                    0,
                )
            elif row_num == benefice:
                ws.write_formula(
                    xr, mc, f"={c}{marge}-{c}{fix_total}", fmt_money_bold, 0
                )
            elif row_num == rent_pct:
                ws.write_formula(
                    xr,
                    mc,
                    f"=IF({c}{ca_total}=0,0,{c}{benefice}/{c}{ca_total})",
                    fmt_pct_bold,
                    0,
                )
            elif row_num == cumul:
                if mc == MONTH_COLS[0]:
                    ws.write_formula(xr, mc, f"={c}{benefice}", fmt_money, 0)
                else:
                    prev = col_letter(mc - 1)
                    ws.write_formula(
                        xr, mc, f"={prev}{cumul}+{c}{benefice}", fmt_money, 0
                    )

        # Colonne total année
        xr = row_num - 1
        if is_input and row_num == pct_mat:
            ws.write_formula(
                xr,
                TOTAL_COL,
                f"=AVERAGE(B{pct_mat}:M{pct_mat})",
                fmt_pct,
                0.35,
            )
        elif is_input:
            ws.write_formula(xr, TOTAL_COL, row_sum_formula(row_num), fmt_money, 0)
        elif row_num in (marge_pct, rent_pct):
            if row_num == marge_pct:
                ws.write_formula(
                    xr,
                    TOTAL_COL,
                    f"=IF(N{ca_total}=0,0,N{marge}/N{ca_total})",
                    fmt_pct_bold,
                    0,
                )
            else:
                ws.write_formula(
                    xr,
                    TOTAL_COL,
                    f"=IF(N{ca_total}=0,0,N{benefice}/N{ca_total})",
                    fmt_pct_bold,
                    0,
                )
        elif row_num == cumul:
            ws.write_formula(xr, TOTAL_COL, row_sum_formula(benefice), fmt_money, 0)
        else:
            ws.write_formula(xr, TOTAL_COL, row_sum_formula(row_num), fmt_money_bold, 0)

    # --- Feuille Simulateur ---
    ws_sim = wb.add_worksheet("Simulateur")
    ws_sim.set_column(0, 0, 36)
    ws_sim.set_column(1, 1, 16)
    ws_sim.write(0, 0, "Simulateur rapide — embauche & BEP", fmt_title)
    sim = [
        ("CA mensuel visé (€)", 10000, fmt_input),
        ("% matières premières", 0.35, fmt_input_pct),
        ("Coûts variables fixes/mois (€)", 800, fmt_input),
        ("Charges fixes mensuelles (€)", 4500, fmt_input),
        ("", None, None),
        ("Marge brute mensuelle (€)", None, fmt_money_bold),
        ("Marge brute (%)", None, fmt_pct_bold),
        ("Bénéfice net mensuel (€)", None, fmt_money_bold),
        ("Rentabilité nette (%)", None, fmt_pct_bold),
        ("Seuil rentabilité BEP (€ CA/mois)", None, fmt_kpi_val),
        ("", None, None),
        ("Coût 1 salarié mi-temps/mois (€)", 1400, fmt_input),
        ("CA supplémentaire requis (€)", None, fmt_money),
    ]
    for i, (label, val, f) in enumerate(sim):
        if not label:
            continue
        ws_sim.write(2 + i, 0, label, fmt_label)
        if val is not None:
            ws_sim.write_number(2 + i, 1, val, f)
        elif i == 5:
            ws_sim.write_formula(2 + i, 1, "=B3*(1-B4)-B5", fmt_money_bold, 0)
        elif i == 6:
            ws_sim.write_formula(2 + i, 1, "=IF(B3=0,0,B8/B3)", fmt_pct_bold, 0)
        elif i == 7:
            ws_sim.write_formula(2 + i, 1, "=B8-B6", fmt_money_bold, 0)
        elif i == 8:
            ws_sim.write_formula(2 + i, 1, "=IF(B3=0,0,B10/B3)", fmt_pct_bold, 0)
        elif i == 9:
            ws_sim.write_formula(
                2 + i, 1, "=IF(B8/B3=0,0,B6/(B8/B3))", fmt_kpi_val, 0
            )
        elif i == 12:
            ws_sim.write_formula(
                2 + i, 1, "=IF(B8/B3=0,0,B14/(B8/B3))", fmt_money, 0
            )

    # --- Mode emploi ---
    ws_h = wb.add_worksheet("Mode emploi")
    ws_h.set_column(0, 0, 78)
    lines = [
        "1. Feuille « Mensuel » : saisissez le CA et les charges (cellules jaunes) mois par mois.",
        "2. % matières premières : ex. 35 % = 0,35 (coût matière calculé automatiquement sur le CA).",
        "3. Feuille « Synthèse » : CA annuel, marge, bénéfice, rentabilité %, BEP (seuil de rentabilité).",
        "4. Feuille « Simulateur » : testez un scénario (CA visé, embauche) sans toucher au suivi réel.",
        "5. BEP = charges fixes mensuelles ÷ marge brute %. En dessous → vous perdez de l'argent.",
        "6. Rentabilité nette % = bénéfice net ÷ CA. Objectif pâtisserie B2B : > 10–15 % net.",
        "7. Excel Mac : cliquez « Activer la modification » à l'ouverture.",
    ]
    for i, line in enumerate(lines):
        ws_h.write(i, 0, line)

    wb.close()


def main() -> None:
    for out_dir in OUT_DIRS:
        out_dir.mkdir(parents=True, exist_ok=True)
        path = out_dir / BASE_NAME
        build_workbook(path)
        print("Written", path)


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Trésorerie Casa Dessert : registre des entrées/sorties (JSON) -> fichier Excel.

Le registre contient des données financières : il ne doit JAMAIS être committé
(dépôt public). Il est lu/écrit à l'emplacement donné par --dir ou par la
variable TRESORERIE_DIR (défaut : tools/tresorerie-data/, ignoré par git).

Commandes :
  python3 tools/tresorerie.py init  --solde-initial 1200 --annee 2026
  python3 tools/tresorerie.py add   --date 2026-11-23 --type sortie --montant 45,50 \
                                    --libelle "Mascarpone" --categorie "Matières premières"
  python3 tools/tresorerie.py config --solde-initial 1500 --annee 2026
  python3 tools/tresorerie.py list
  python3 tools/tresorerie.py build
"""
from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import date, datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DIR = Path(__file__).resolve().parent / "tresorerie-data"
JSON_NAME = "tresorerie.json"
XLSX_NAME = "tresorerie-casa-dessert.xlsx"
LAST_ROW = 5000

MONTHS = [
    "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
    "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre",
]
HEADERS = [
    "Date", "Libellé", "Catégorie", "Tiers", "Entrée (€)",
    "Sortie (€)", "Solde (€)", "Moyen de paiement", "Note",
]


def data_dir(arg: str | None) -> Path:
    return Path(arg or os.environ.get("TRESORERIE_DIR") or DEFAULT_DIR)


def parse_amount(raw: str) -> float:
    s = str(raw).replace("\u00a0", "").replace(" ", "").replace("€", "").replace(",", ".")
    value = round(float(s), 2)
    if value <= 0:
        raise ValueError("Le montant doit être strictement positif (le type entree/sortie donne le sens).")
    return value


def load(path: Path) -> dict:
    if not path.is_file():
        sys.exit(f"Registre introuvable : {path} (lancez d'abord la commande init).")
    return json.loads(path.read_text(encoding="utf-8"))


def save(path: Path, data: dict) -> None:
    data["mouvements"].sort(key=lambda m: (m["date"], m.get("ordre", 0)))
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def cmd_init(args: argparse.Namespace) -> None:
    path = data_dir(args.dir) / JSON_NAME
    if path.exists() and not args.force:
        sys.exit(f"{path} existe déjà (utilisez --force pour écraser).")
    save(path, {
        "soldeInitial": parse_amount(args.solde_initial) if args.solde_initial not in (None, "0") else 0.0,
        "annee": args.annee,
        "mouvements": [],
    })
    print(f"Registre créé : {path}")


def cmd_config(args: argparse.Namespace) -> None:
    path = data_dir(args.dir) / JSON_NAME
    data = load(path)
    if args.solde_initial is not None:
        data["soldeInitial"] = round(float(str(args.solde_initial).replace(",", ".").replace(" ", "")), 2)
    if args.annee is not None:
        data["annee"] = args.annee
    save(path, data)
    print(f"Solde initial : {data['soldeInitial']:.2f} € — année affichée : {data['annee']}")


def cmd_add(args: argparse.Namespace) -> None:
    path = data_dir(args.dir) / JSON_NAME
    data = load(path)
    datetime.strptime(args.date, "%Y-%m-%d")
    data["mouvements"].append({
        "date": args.date,
        "libelle": args.libelle,
        "type": args.type,
        "montant": parse_amount(args.montant),
        "categorie": args.categorie or "",
        "tiers": args.tiers or "",
        "moyen": args.moyen or "",
        "note": args.note or "",
        "ordre": len(data["mouvements"]),
    })
    save(path, data)
    print(f"Ajouté : {args.date} {args.type} {args.montant} € — {args.libelle}")


def cmd_list(args: argparse.Namespace) -> None:
    data = load(data_dir(args.dir) / JSON_NAME)
    solde = float(data.get("soldeInitial", 0))
    print(f"Solde initial : {solde:.2f} €")
    for i, m in enumerate(data["mouvements"], 1):
        solde += m["montant"] if m["type"] == "entree" else -m["montant"]
        sign = "+" if m["type"] == "entree" else "-"
        print(f"{i:>3}  {m['date']}  {sign}{m['montant']:>9.2f}  {solde:>10.2f}  {m['categorie']:<22} {m['libelle']}")
    print(f"Solde actuel  : {solde:.2f} €")


def build_workbook(data: dict, out: Path) -> None:
    import xlsxwriter

    moves = data["mouvements"]
    opening = float(data.get("soldeInitial", 0))
    year = int(data.get("annee") or (moves[0]["date"][:4] if moves else date.today().year))
    first = 3
    rng = lambda col: f"Mouvements!${col}${first}:${col}${LAST_ROW}"

    wb = xlsxwriter.Workbook(str(out))
    wb.set_calc_mode("auto")
    f = {
        "title": wb.add_format({"bold": True, "font_size": 14, "font_color": "#3D2E24", "bottom": 2}),
        "head": wb.add_format({"bold": True, "font_color": "#FFFFFF", "bg_color": "#3D2E24",
                               "align": "center", "valign": "vcenter", "border": 1, "text_wrap": True}),
        "text": wb.add_format({"border": 1}),
        "date": wb.add_format({"border": 1, "num_format": "dd/mm/yyyy", "align": "center"}),
        "in": wb.add_format({"border": 1, "num_format": "#,##0.00 €", "font_color": "#1B5E20"}),
        "out": wb.add_format({"border": 1, "num_format": "#,##0.00 €", "font_color": "#B00020"}),
        "money": wb.add_format({"border": 1, "num_format": "#,##0.00 €"}),
        "bold": wb.add_format({"border": 1, "num_format": "#,##0.00 €", "bold": True, "bg_color": "#E8F5E9"}),
        "label": wb.add_format({"bold": True, "border": 1, "bg_color": "#E8E0D8"}),
        "open": wb.add_format({"border": 1, "italic": True, "bg_color": "#F5F0EA"}),
        "openmoney": wb.add_format({"border": 1, "num_format": "#,##0.00 €", "bold": True, "bg_color": "#F5F0EA"}),
        "year": wb.add_format({"bold": True, "bg_color": "#FFFDE7", "border": 1, "align": "center"}),
        "pct": wb.add_format({"border": 1, "num_format": "0.0%"}),
        "alert": wb.add_format({"bold": True, "font_color": "#B00020"}),
        "note": wb.add_format({"italic": True, "font_color": "#666666"}),
    }

    ws_s = wb.add_worksheet("Synthèse")
    ws_m = wb.add_worksheet("Mouvements")
    ws_c = wb.add_worksheet("Par catégorie")

    ws_m.set_column(0, 0, 12)
    ws_m.set_column(1, 1, 38)
    ws_m.set_column(2, 3, 22)
    ws_m.set_column(4, 6, 14)
    ws_m.set_column(7, 7, 18)
    ws_m.set_column(8, 8, 36)
    for c, title in enumerate(HEADERS):
        ws_m.write(0, c, title, f["head"])
    ws_m.set_row(0, 30)
    ws_m.write(1, 0, "", f["open"])
    ws_m.write(1, 1, "Solde initial", f["open"])
    for c in (2, 3, 4, 5, 7, 8):
        ws_m.write(1, c, "", f["open"])
    ws_m.write_number(1, 6, opening, f["openmoney"])

    solde = opening
    for i, m in enumerate(moves):
        r = 2 + i
        xr = r + 1
        d = datetime.strptime(m["date"], "%Y-%m-%d")
        entree = m["montant"] if m["type"] == "entree" else None
        sortie = m["montant"] if m["type"] == "sortie" else None
        solde = round(solde + (entree or 0) - (sortie or 0), 2)
        ws_m.write_datetime(r, 0, d, f["date"])
        ws_m.write(r, 1, m["libelle"], f["text"])
        ws_m.write(r, 2, m.get("categorie", ""), f["text"])
        ws_m.write(r, 3, m.get("tiers", ""), f["text"])
        if entree is not None:
            ws_m.write_number(r, 4, entree, f["in"])
        else:
            ws_m.write_blank(r, 4, None, f["in"])
        if sortie is not None:
            ws_m.write_number(r, 5, sortie, f["out"])
        else:
            ws_m.write_blank(r, 5, None, f["out"])
        ws_m.write_formula(r, 6, f"=G{xr - 1}+E{xr}-F{xr}", f["money"], solde)
        ws_m.write(r, 7, m.get("moyen", ""), f["text"])
        ws_m.write(r, 8, m.get("note", ""), f["text"])
    last = 2 + len(moves)
    ws_m.freeze_panes(1, 0)
    ws_m.autofilter(0, 0, max(last - 1, 1), len(HEADERS) - 1)

    total_in = round(sum(m["montant"] for m in moves if m["type"] == "entree"), 2)
    total_out = round(sum(m["montant"] for m in moves if m["type"] == "sortie"), 2)
    ws_s.set_column(0, 0, 30)
    ws_s.set_column(1, 6, 16)
    ws_s.write(0, 0, "Trésorerie Casa Dessert — Synthèse", f["title"])
    kpis = [
        ("Solde initial", "=Mouvements!G2", opening),
        ("Total entrées", f"=SUM({rng('E')})", total_in),
        ("Total sorties", f"=SUM({rng('F')})", total_out),
        ("Solde actuel", "=B4+B5-B6", round(opening + total_in - total_out, 2)),
    ]
    for i, (label, formula, value) in enumerate(kpis):
        ws_s.write(3 + i, 0, label, f["label"])
        ws_s.write_formula(3 + i, 1, formula, f["bold"] if label == "Solde actuel" else f["money"], value)
    ws_s.write(7, 0, "Alerte", f["label"])
    ws_s.write_formula(
        7, 1, '=IF(B7<0,"⚠ Trésorerie négative","")', f["alert"],
        "⚠ Trésorerie négative" if opening + total_in - total_out < 0 else "",
    )

    ws_s.write(9, 0, "Année affichée (modifiable)", f["label"])
    ws_s.write_number(9, 1, year, f["year"])
    for c, title in enumerate(["Mois", "Solde début", "Entrées", "Sorties", "Net", "Solde fin"]):
        ws_s.write(11, c, title, f["head"])

    def month_total(col: str, y: int, mth: int) -> float:
        key = "entree" if col == "E" else "sortie"
        return round(sum(
            m["montant"] for m in moves
            if m["type"] == key and m["date"][:7] == f"{y}-{mth:02d}"
        ), 2)

    before = [m for m in moves if m["date"] < f"{year}-01-01"]
    start = round(opening + sum(m["montant"] if m["type"] == "entree" else -m["montant"] for m in before), 2)
    for mth in range(1, 13):
        r = 11 + mth
        xr = r + 1
        crit = (f'{rng("A")},">="&DATE($B$10,{mth},1),{rng("A")},"<"&DATE($B$10,{mth + 1},1)')
        e_val, s_val = month_total("E", year, mth), month_total("F", year, mth)
        ws_s.write(r, 0, MONTHS[mth - 1], f["label"])
        if mth == 1:
            formula = (f'=Mouvements!$G$2+SUMIFS({rng("E")},{rng("A")},"<"&DATE($B$10,1,1))'
                       f'-SUMIFS({rng("F")},{rng("A")},"<"&DATE($B$10,1,1))')
            ws_s.write_formula(r, 1, formula, f["money"], start)
        else:
            ws_s.write_formula(r, 1, f"=F{xr - 1}", f["money"], start)
        ws_s.write_formula(r, 2, f'=SUMIFS({rng("E")},{crit})', f["in"], e_val)
        ws_s.write_formula(r, 3, f'=SUMIFS({rng("F")},{crit})', f["out"], s_val)
        net = round(e_val - s_val, 2)
        ws_s.write_formula(r, 4, f"=C{xr}-D{xr}", f["money"], net)
        fin = round(start + net, 2)
        ws_s.write_formula(r, 5, f"=B{xr}+E{xr}", f["bold"], fin)
        start = fin
    ws_s.write(24, 0, "Total année", f["label"])
    ws_s.write_blank(24, 1, None, f["label"])
    y_in = round(sum(month_total("E", year, k) for k in range(1, 13)), 2)
    y_out = round(sum(month_total("F", year, k) for k in range(1, 13)), 2)
    ws_s.write_formula(24, 2, "=SUM(C13:C24)", f["in"], y_in)
    ws_s.write_formula(24, 3, "=SUM(D13:D24)", f["out"], y_out)
    ws_s.write_formula(24, 4, "=C25-D25", f["bold"], round(y_in - y_out, 2))
    ws_s.write_blank(24, 5, None, f["label"])
    ws_s.write(26, 0, "Les feuilles sont générées depuis le registre : ne pas saisir à la main, "
                      "donnez les mouvements à l'agent.", f["note"])

    cats = sorted({m.get("categorie") or "Non classé" for m in moves})
    ws_c.set_column(0, 0, 30)
    ws_c.set_column(1, 3, 16)
    ws_c.write(0, 0, "Répartition par catégorie (tous mouvements)", f["title"])
    for c, title in enumerate(["Catégorie", "Entrées", "Sorties", "% des sorties"]):
        ws_c.write(2, c, title, f["head"])
    for i, cat in enumerate(cats):
        r = 3 + i
        xr = r + 1
        crit_cell = f"$A{xr}"
        if cat == "Non classé":
            ce = f'=SUMIFS({rng("E")},{rng("C")},"")'
            cs = f'=SUMIFS({rng("F")},{rng("C")},"")'
        else:
            ce = f'=SUMIFS({rng("E")},{rng("C")},{crit_cell})'
            cs = f'=SUMIFS({rng("F")},{rng("C")},{crit_cell})'
        in_v = round(sum(m["montant"] for m in moves if m["type"] == "entree" and (m.get("categorie") or "Non classé") == cat), 2)
        out_v = round(sum(m["montant"] for m in moves if m["type"] == "sortie" and (m.get("categorie") or "Non classé") == cat), 2)
        ws_c.write(r, 0, cat, f["text"])
        ws_c.write_formula(r, 1, ce, f["in"], in_v)
        ws_c.write_formula(r, 2, cs, f["out"], out_v)
        ws_c.write_formula(
            r, 3, f"=IF(SUM($C$4:$C${3 + len(cats)})=0,0,C{xr}/SUM($C$4:$C${3 + len(cats)}))",
            f["pct"], (out_v / total_out) if total_out else 0,
        )
    wb.close()


def cmd_build(args: argparse.Namespace) -> None:
    d = data_dir(args.dir)
    data = load(d / JSON_NAME)
    out = Path(args.out) if args.out else d / XLSX_NAME
    out.parent.mkdir(parents=True, exist_ok=True)
    build_workbook(data, out)
    print(f"Fichier généré : {out}")


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--dir", help="Dossier du registre (défaut : $TRESORERIE_DIR ou tools/tresorerie-data)")
    sub = p.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("init")
    s.add_argument("--solde-initial", default="0")
    s.add_argument("--annee", type=int, default=date.today().year)
    s.add_argument("--force", action="store_true")
    s.set_defaults(fn=cmd_init)

    s = sub.add_parser("config", help="Modifier solde initial / année affichée")
    s.add_argument("--solde-initial")
    s.add_argument("--annee", type=int)
    s.set_defaults(fn=cmd_config)

    s = sub.add_parser("add")
    s.add_argument("--date", required=True, help="AAAA-MM-JJ")
    s.add_argument("--type", required=True, choices=["entree", "sortie"])
    s.add_argument("--montant", required=True, help="positif, virgule acceptée")
    s.add_argument("--libelle", required=True)
    s.add_argument("--categorie")
    s.add_argument("--tiers")
    s.add_argument("--moyen")
    s.add_argument("--note")
    s.set_defaults(fn=cmd_add)

    sub.add_parser("list").set_defaults(fn=cmd_list)

    s = sub.add_parser("build")
    s.add_argument("--out", help="Chemin du .xlsx (défaut : dans le dossier du registre)")
    s.set_defaults(fn=cmd_build)

    args = p.parse_args()
    args.fn(args)


if __name__ == "__main__":
    main()

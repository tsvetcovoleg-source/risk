#!/usr/bin/env python3
"""Fetch public Moldovan financial statements by IDNO and emit JSON rows.

Output format on stdout:
{
  "rows": [
    {
      "IDNO": "...",
      "REPORT_KEY": "2024-BNS",
      "META_CSV": "...",
      "BIL_CSV": "...",
      "PNL_CSV": "...",
      "EQT_CSV": "...",
      "CF_CSV": "..."
    }
  ]
}
"""

from __future__ import annotations

import argparse
import asyncio
import csv
import json
import re
import sys
from io import StringIO
from typing import Any
from urllib.parse import urlencode

import pandas as pd
from bs4 import BeautifulSoup
from playwright.async_api import async_playwright


def build_search_url(
    idno: str,
    year_from: int = 2020,
    year_to: int = 2026,
    size: int = 10,
    sort: str = "year,desc",
    report: int = 1,
) -> str:
    params = {
        "idno": idno,
        "from": year_from,
        "to": year_to,
        "size": size,
        "sort": sort,
        "report": report,
    }
    return "https://depozitar.statistica.md/search?" + urlencode(params)


def clean_cell(value: Any) -> str:
    if pd.isna(value):
        return "0"

    text = str(value).strip()
    if text in ["", "nan", "None"]:
        return "0"

    return text


def get_info_map(report_data: dict[str, Any]) -> dict[str, str]:
    info_df = report_data["info_df"].copy()
    if info_df.empty:
        return {}

    return dict(zip(info_df["field"], info_df["value"]))


def build_report_key(report_data: dict[str, Any]) -> str:
    info_map = get_info_map(report_data)
    year = str(info_map.get("Anul bugetar", "")).strip()
    source = str(info_map.get("Sursa", "")).strip()

    return f"{year}-{source}"


def dataframe_to_csv_text(df: pd.DataFrame | None, section_name: str) -> str:
    output = StringIO()
    writer = csv.writer(
        output,
        delimiter=";",
        quoting=csv.QUOTE_MINIMAL,
        lineterminator="\n",
    )

    if df is None or len(df) == 0:
        writer.writerow(["SECTION", "EMPTY"])
        writer.writerow([section_name, "1"])
        return output.getvalue()

    df = df.copy()
    indicator_col = None
    code_col = None

    for column in df.columns:
        column_name = str(column).strip()
        if column_name.lower() == "indicatori":
            indicator_col = column
        if "Cod rd" in column_name:
            code_col = column

    value_cols = [column for column in df.columns if column not in [indicator_col, code_col]]
    header = ["SECTION", "CODE", "INDICATOR"] + [
        f"VALUE_{index + 1}" for index in range(len(value_cols))
    ]
    writer.writerow(header)

    for _, row in df.iterrows():
        out_row = [
            section_name,
            clean_cell(row.get(code_col, "")),
            clean_cell(row.get(indicator_col, "")),
        ]
        for column in value_cols:
            out_row.append(clean_cell(row.get(column, 0)))
        writer.writerow(out_row)

    return output.getvalue()


def meta_to_csv_text(report_data: dict[str, Any]) -> str:
    output = StringIO()
    writer = csv.writer(
        output,
        delimiter=";",
        quoting=csv.QUOTE_MINIMAL,
        lineterminator="\n",
    )

    info_map = get_info_map(report_data)
    writer.writerow(["FIELD", "VALUE"])

    for key, value in info_map.items():
        writer.writerow([clean_cell(key), clean_cell(value)])

    writer.writerow(["PERIOD_FROM", clean_cell(report_data.get("period_from", ""))])
    writer.writerow(["PERIOD_TO", clean_cell(report_data.get("period_to", ""))])

    return output.getvalue()


def parse_financial_report(html: str) -> dict[str, Any]:
    soup = BeautifulSoup(html, "lxml")
    full_text = soup.get_text("\n", strip=True)

    try:
        tables = pd.read_html(StringIO(html))
    except ValueError:
        tables = []

    info_fields = [
        "Statut document",
        "Tip document",
        "Anul bugetar",
        "Sursa",
        "Denumirea entităţii juridice",
        "Cod IDNO",
        "Cod CUIÎO",
        "Cod poştal",
        "Cod CUATM",
        "Adresa",
        "Cod CAEM",
        "Cod CFP",
        "Cod CFOJ",
        "Numărul mediu al salariaţilor în perioada de gestiune",
        "Persoana (Administrator) responsabilă de semnarea situațiilor financiare",
        "Unitatea de măsură",
    ]

    lines = [line.strip() for line in full_text.split("\n") if line.strip()]
    info_data = []
    normalized_fields = {field.rstrip(":"): field.rstrip(":") for field in info_fields}

    for index, line in enumerate(lines):
        clean_line = line.rstrip(":").strip()
        if clean_line in normalized_fields:
            value = lines[index + 1] if index + 1 < len(lines) else ""
            info_data.append({"field": clean_line, "value": value})

    info_df = pd.DataFrame(info_data).drop_duplicates()
    period_match = re.search(
        r"pentru perioada\s+(\d{2}/\d{2}/\d{4})\s*-\s*(\d{2}/\d{2}/\d{4})",
        full_text,
    )

    report_period_from = period_match.group(1) if period_match else ""
    report_period_to = period_match.group(2) if period_match else ""

    bilan_df = tables[0].copy() if len(tables) > 0 else pd.DataFrame()
    pnl_df = tables[1].copy() if len(tables) > 1 else pd.DataFrame()
    equity_df = tables[2].copy() if len(tables) > 2 else pd.DataFrame()
    cashflow_df = tables[3].copy() if len(tables) > 3 else pd.DataFrame()

    return {
        "info_df": info_df,
        "bilan_df": bilan_df.fillna(0),
        "pnl_df": pnl_df.fillna(0),
        "equity_df": equity_df.fillna(0),
        "cashflow_df": cashflow_df.fillna(0),
        "period_from": report_period_from,
        "period_to": report_period_to,
    }


async def get_profile_link_by_idno(
    idno: str,
    year_from: int = 2020,
    year_to: int = 2026,
    size: int = 10,
    sort: str = "year,desc",
    report: int = 1,
    wait_seconds: int = 8,
) -> str | None:
    search_url = build_search_url(
        idno=idno,
        year_from=year_from,
        year_to=year_to,
        size=size,
        sort=sort,
        report=report,
    )

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        page = await browser.new_page(viewport={"width": 1600, "height": 3000})
        await page.goto(search_url, wait_until="networkidle", timeout=120000)
        await page.wait_for_timeout(wait_seconds * 1000)
        html = await page.content()
        await browser.close()

    soup = BeautifulSoup(html, "lxml")
    for link in soup.find_all("a", href=True):
        href = link["href"]
        if "/economic-agent/" in href:
            if href.startswith("/"):
                return "https://depozitar.statistica.md" + href
            return href

    return None


async def get_all_reports_by_idno(idno: str, year_from: int = 2020, year_to: int = 2026) -> list[dict[str, Any]]:
    profile_url = await get_profile_link_by_idno(
        idno=idno,
        year_from=year_from,
        year_to=year_to,
    )
    if not profile_url:
        print(f"{idno} | профиль не найден", file=sys.stderr)
        return []

    print(f"{idno} | профиль: {profile_url}", file=sys.stderr)
    reports = []

    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        page = await browser.new_page(viewport={"width": 1800, "height": 5000})
        await page.goto(profile_url, wait_until="networkidle", timeout=120000)
        await page.wait_for_timeout(5000)

        tabs = page.locator('[role="tab"]')
        tab_opened = False
        for index in range(await tabs.count()):
            tab = tabs.nth(index)
            text = (await tab.inner_text()).strip()
            if "Situaţii financiare publice" in text:
                await tab.click()
                await page.wait_for_timeout(3000)
                tab_opened = True
                break

        if not tab_opened:
            await browser.close()
            print("Вкладка 'Situaţii financiare publice' не найдена.", file=sys.stderr)
            return []

        all_texts = await page.locator("body *").all_text_contents()
        periods = []
        for text in all_texts:
            clean_text = text.strip()
            if re.fullmatch(r"20\d{2}-[A-Z]+", clean_text):
                periods.append(clean_text)
        periods = list(dict.fromkeys(periods))

        print(f"Найдено периодов: {len(periods)}", file=sys.stderr)
        for period in periods:
            try:
                period_btn = page.locator(f"text={period}")
                if await period_btn.count() == 0:
                    continue

                await period_btn.first.click()
                await page.wait_for_timeout(4000)
                html = await page.content()
                report_data = parse_financial_report(html)
                parsed_key = build_report_key(report_data)

                if not parsed_key.strip() or parsed_key == "-":
                    print(f"{period} | не удалось определить ключ отчета", file=sys.stderr)
                    continue

                reports.append({
                    "period_button": period,
                    "report_key": parsed_key,
                    "data": report_data,
                })
                print(f"{period} | обработано как {parsed_key}", file=sys.stderr)
            except Exception as exc:  # noqa: BLE001 - keep processing other periods.
                print(f"{period} | ошибка: {exc}", file=sys.stderr)
                continue

        await browser.close()

    return reports


def reports_to_result_rows(idno: str, reports: list[dict[str, Any]]) -> list[dict[str, str]]:
    rows = []
    for item in reports:
        report_data = item["data"]
        info_map = get_info_map(report_data)
        idno_value = str(info_map.get("Cod IDNO", idno)).strip()
        report_key = build_report_key(report_data)

        rows.append({
            "IDNO": idno_value,
            "REPORT_KEY": report_key,
            "META_CSV": meta_to_csv_text(report_data),
            "BIL_CSV": dataframe_to_csv_text(report_data["bilan_df"], "BIL"),
            "PNL_CSV": dataframe_to_csv_text(report_data["pnl_df"], "PNL"),
            "EQT_CSV": dataframe_to_csv_text(report_data["equity_df"], "EQT"),
            "CF_CSV": dataframe_to_csv_text(report_data["cashflow_df"], "CF"),
        })

    return rows


async def main() -> int:
    parser = argparse.ArgumentParser(description="Fetch financial data by IDNO.")
    parser.add_argument("idno")
    parser.add_argument("--year-from", type=int, default=2020)
    parser.add_argument("--year-to", type=int, default=2026)
    args = parser.parse_args()

    idno = args.idno.strip()
    if not re.fullmatch(r"\d{5,20}", idno):
        print("IDNO must contain 5-20 digits.", file=sys.stderr)
        return 2

    reports = await get_all_reports_by_idno(idno, args.year_from, args.year_to)
    rows = reports_to_result_rows(idno, reports)
    json.dump({"rows": rows}, sys.stdout, ensure_ascii=False)
    return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))

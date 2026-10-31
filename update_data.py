#!/usr/bin/env python3
"""Refresh the CSV/JSON datasets used by the Bitcoinflation charts.

All data sources are free and require no API key:

  * BTC-USD price history  -> blockchain.info chart API (full history, month-end close)
  * M1 / M2 money supply   -> FRED (Federal Reserve) series M1NS / M2NS
  * USD CPI inflation      -> FRED series CPIAUCNS (NSA), month-over-month % change
  * Global M3 money index  -> OECD SDMX dataflow DSD_STES@DF_MONAGG (broad money, 2015 = 100)
  * BTC supply / inflation -> CoinMetrics community API (SplyCur, daily)

The realtime spot price shown at the top of each chart is fetched at request
time by the PHP pages themselves (see the Coinbase spot call in each .php file),
so it is not handled here.

Run from the repository root:

    python3 update_data.py
"""

import csv
import datetime as dt
import io
import json
import os
import subprocess
import sys
import urllib.request
from collections import defaultdict

ROOT = os.path.dirname(os.path.abspath(__file__))
UA = "bitcoinflation-updater"


def _fetch_urllib(url, timeout):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read().decode("utf-8", "replace")


def _fetch_curl(url, timeout):
    # Some hosts (e.g. the OECD SDMX endpoint behind Cloudflare) reject
    # Python's TLS fingerprint, so fall back to the curl binary.
    result = subprocess.run(
        ["curl", "-sSL", "--max-time", str(timeout), "-H", f"User-Agent: {UA}", url],
        capture_output=True,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.decode("utf-8", "replace").strip())
    return result.stdout.decode("utf-8", "replace")


def fetch(url, timeout=90, retries=3):
    """GET a URL and return the decoded body, retrying transient failures."""
    last_err = None
    for attempt in range(1, retries + 1):
        for getter in (_fetch_urllib, _fetch_curl):
            try:
                return getter(url, timeout)
            except Exception as exc:  # noqa: BLE001 - report and retry
                last_err = exc
        print(f"    ! attempt {attempt} failed for {url}: {last_err}", file=sys.stderr)
    raise RuntimeError(f"failed to fetch {url}: {last_err}")


def write(path, text):
    full = os.path.join(ROOT, path)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "w", newline="") as fh:
        fh.write(text)
    print(f"  wrote {path} ({len(text.splitlines())} lines)")


# --------------------------------------------------------------------------
# BTC-USD monthly exchange rate (blockchain.info, month-end close)
# --------------------------------------------------------------------------
def build_monthly_price():
    print("BTC-USD monthly price (blockchain.info)...")
    raw = fetch("https://api.blockchain.info/charts/market-price"
                "?timespan=all&format=json&sampled=false")
    values = json.loads(raw)["values"]

    by_month = defaultdict(list)
    for point in values:
        d = dt.datetime.fromtimestamp(point["x"], dt.UTC)
        by_month[(d.year, d.month)].append((d.day, float(point["y"])))

    rows = ["Month,BTC-USD Exchange Rate"]
    for (year, month) in sorted(by_month):
        days = sorted(by_month[(year, month)])
        # month-end close = last day of the month that has a non-zero price
        priced = [v for _, v in days if v > 0]
        close = priced[-1] if priced else 0.0
        rows.append(f"{year:04d}-{month:02d}-01,{close}")

    body = "\n".join(rows) + "\n"
    for directory in ("btc-usd-m1-adjusted", "btc-usd-m2-adjusted",
                      "btc-global-m3-adjusted", "cpi-adjusted-exchange-rate"):
        write(os.path.join(directory, "monthly-btc-usd-exchange-rate.csv"), body)


# --------------------------------------------------------------------------
# FRED helpers (M1, M2, CPI)
# --------------------------------------------------------------------------
def fred_series(series_id):
    """Return an ordered list of (YYYY-MM-DD, float) from a FRED CSV export."""
    raw = fetch(f"https://fred.stlouisfed.org/graph/fredgraph.csv?id={series_id}")
    out = []
    for row in csv.DictReader(io.StringIO(raw)):
        date = row["observation_date"]
        value = row[series_id]
        if value in ("", "."):
            continue
        out.append((date, float(value)))
    out.sort()
    return out


def build_m1():
    print("M1 money supply (FRED M1NS)...")
    rows = ["DATE,M1NS"]
    for date, value in fred_series("M1NS"):
        if date >= "2009-01-01":
            rows.append(f"{date},{value}")
    write("btc-usd-m1-adjusted/m1-supply.csv", "\n".join(rows) + "\n")


def build_m2():
    print("M2 money supply (FRED M2NS)...")
    rows = ["DATE,M2 Money Supply of USD"]
    for date, value in fred_series("M2NS"):
        if date >= "2009-01-01":
            rows.append(f"{date},{value}")
    write("btc-usd-m2-adjusted/m2-supply.csv", "\n".join(rows) + "\n")


def build_cpi():
    print("CPI month-over-month inflation (FRED CPIAUCNS)...")
    series = fred_series("CPIAUCNS")
    rows = ["DATE,Monthly CPI Delta"]
    prev = None
    for date, value in series:
        if prev is not None and prev > 0:
            mom = (value / prev - 1.0) * 100.0
            if date >= "2009-01-01":
                rows.append(f"{date},{mom:.9f}")
        prev = value
    write("cpi-adjusted-exchange-rate/monthly-usd-inflation-rate.csv",
          "\n".join(rows) + "\n")


# --------------------------------------------------------------------------
# Global M3 index (OECD SDMX, broad money MABM, 2015 = 100)
# --------------------------------------------------------------------------
def build_m3():
    print("Global M3 index (OECD DF_MONAGG)...")
    url = ("https://sdmx.oecd.org/public/rest/data/"
           "OECD.SDD.STES,DSD_STES@DF_MONAGG,4.0/all"
           "?startPeriod=2009-01&dimensionAtObservation=AllDimensions&format=csvfile")
    raw = fetch(url, timeout=120)
    rows = ["Month,Global M3 (2015 indexed)"]
    for row in csv.DictReader(io.StringIO(raw)):
        if (row["REF_AREA"] == "OECD" and row["FREQ"] == "M"
                and row["MEASURE"] == "MABM" and row["UNIT_MEASURE"] == "IX"
                and row["ADJUSTMENT"] == "N" and row["OBS_STATUS"] == "A"):
            rows.append(f"{row['TIME_PERIOD']},{row['OBS_VALUE']}")
    rows[1:] = sorted(set(rows[1:]))
    write("btc-global-m3-adjusted/global_m3.csv", "\n".join(rows) + "\n")


# --------------------------------------------------------------------------
# BTC supply inflation (CoinMetrics community API)
# --------------------------------------------------------------------------
def build_btc_inflation():
    print("BTC supply inflation (CoinMetrics SplyCur)...")
    url = ("https://community-api.coinmetrics.io/v4/timeseries/asset-metrics"
           "?assets=btc&metrics=SplyCur&frequency=1d&page_size=10000"
           "&start_time=2009-01-03")
    raw = fetch(url, timeout=120)
    data = json.loads(raw)["data"]

    supply = {}
    for point in data:
        day = dt.date.fromisoformat(point["time"][:10])
        supply[day] = float(point["SplyCur"])

    json_rows = []   # for index.php ({"t": unix, "v": rate})
    csv_rows = ["timestamp,value"]
    for day in sorted(supply):
        prev = supply.get(day - dt.timedelta(days=1))
        cur = supply[day]
        if not prev or prev <= 0:
            continue
        # annualised daily issuance rate (fraction); reproduces the old
        # supply/inflation_rate series with correlation ~0.9998
        rate = (cur - prev) / prev * 365.0
        unix = int(dt.datetime(day.year, day.month, day.day,
                               tzinfo=dt.UTC).timestamp())
        json_rows.append({"t": unix, "v": rate})
        csv_rows.append(f"{day.strftime('%m-%d-%Y')},{rate}")

    write("btc-supply-inflation/historic-btc-inflation-rate.json",
          json.dumps(json_rows))
    write("btc-supply-inflation/historic-btc-inflation-rate.csv",
          "\n".join(csv_rows) + "\n")

    # anchor point used by index.php's forward projection
    last_day = max(supply)
    anchor = {
        "t": int(dt.datetime(last_day.year, last_day.month, last_day.day,
                              tzinfo=dt.UTC).timestamp()),
        "supply": supply[last_day],
    }
    write("btc-supply-inflation/latest-supply.json", json.dumps(anchor))
    print(f"    latest supply point: {last_day} = {supply[last_day]:.4f} BTC")


def main():
    build_monthly_price()
    build_m1()
    build_m2()
    build_cpi()
    build_m3()
    build_btc_inflation()
    print("done.")


if __name__ == "__main__":
    main()

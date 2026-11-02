#!/usr/bin/env python3
"""Refresh the CSV/JSON datasets used by the Bitcoinflation charts.

All data sources are free and require no API key:

  * BTC-USD price history  -> blockchain.info chart API (full history, month-end close)
  * Global M1 / M2 / M3    -> OECD SDMX dataflow DSD_STES@DF_MONAGG (national money
                              stocks) combined with BIS SDMX dataflow WS_XRU
                              (month-end exchange rates) to produce one
                              USD-denominated world aggregate
  * USD CPI inflation      -> FRED series CPIAUCNS (NSA), month-over-month % change
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
import time
import urllib.request
from collections import defaultdict

ROOT = os.path.dirname(os.path.abspath(__file__))
UA = "bitcoinflation-updater"


def _fetch_urllib(url, timeout):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read().decode("utf-8", "replace")


def _fetch_curl(url, timeout):
    # curl is the primary transport: some hosts (e.g. the OECD SDMX endpoint
    # behind Cloudflare) reject Python's TLS fingerprint outright, and others
    # (e.g. FRED) hang urllib's read until it times out.
    #
    # HTTP/1.1 is forced because FRED's HTTP/2 endpoint intermittently aborts
    # mid-response with "stream was not closed cleanly: INTERNAL_ERROR", which
    # curl reports as exit code 92. Those aborts come in bursts, so a retry can
    # easily land inside the same burst; HTTP/1.1 sidesteps them entirely.
    result = subprocess.run(
        ["curl", "-sSL", "--http1.1", "--max-time", str(timeout),
         "-H", f"User-Agent: {UA}", url],
        capture_output=True,
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.decode("utf-8", "replace").strip())
    return result.stdout.decode("utf-8", "replace")


def fetch(url, timeout=90, retries=4):
    """GET a URL and return the decoded body, retrying transient failures.

    FRED in particular goes unresponsive in bursts that last minutes, so the
    backoff is deliberately generous: a retry that fires immediately just lands
    inside the same outage.
    """
    last_err = None
    for attempt in range(1, retries + 1):
        for getter in (_fetch_curl, _fetch_urllib):
            try:
                return getter(url, timeout)
            except Exception as exc:  # noqa: BLE001 - report and retry
                last_err = exc
        print(f"    ! attempt {attempt} failed for {url}: {last_err}", file=sys.stderr)
        if attempt < retries:
            time.sleep(min(2 ** attempt, 15))
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
    for directory in ("btc-global-m1-adjusted", "btc-global-m2-adjusted",
                      "btc-global-m3-adjusted", "cpi-adjusted-exchange-rate",
                      "btc-in-gold", "btc-raw-vs-adjusted",
                      "btc-in-real-goods", "btc-vs-sp500"):
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

    # USD purchasing power, indexed to 100 at January 2009: the inverse of the
    # CPI index. A dollar buys less every year, and this is the series that
    # makes the decay explicit.
    base = None
    pp_rows = ["Month,USD Purchasing Power (Jan 2009 = 100)"]
    for date, value in series:
        if date < "2009-01-01" or value <= 0:
            continue
        if base is None:
            base = value
        pp_rows.append(f"{date},{base / value * 100.0:.4f}")
    write("usd-purchasing-power/purchasing-power.csv", "\n".join(pp_rows) + "\n")


# --------------------------------------------------------------------------
# Global money supply, all currencies converted to USD
#
# Two key-less sources are combined into a single world aggregate:
#
#   * OECD SDMX dataflow DSD_STES@DF_MONAGG gives each country's narrow money
#     (MANM) and broad money (MABM) in its own currency, monthly. The OECD
#     aggregate rows in that file are an index, not a level, so they cannot be
#     summed -- we sum the national stocks ourselves instead.
#   * BIS SDMX dataflow WS_XRU gives the month-end exchange rate of each
#     currency against the USD.
#
# Each country's stock is converted at its own end-of-period rate and the
# results are summed, so a currency depreciation cannot inflate the total the
# way it would if the local-currency amounts were simply added together.
# --------------------------------------------------------------------------

# OECD REF_AREA -> BIS WS_XRU REF_AREA. The euro area is carried as a single
# block (EA20/XM) so that its member states are not double counted.
OECD_TO_BIS_FX = {
    "AUS": "AU", "BGR": "BG", "BRA": "BR", "CAN": "CA", "CHE": "CH",
    "CHL": "CL", "CHN": "CN", "COL": "CO", "CRI": "CR", "CZE": "CZ",
    "DNK": "DK", "EA20": "XM", "GBR": "GB", "HUN": "HU", "IDN": "ID",
    "IND": "IN", "ISL": "IS", "ISR": "IL", "JPN": "JP", "KOR": "KR",
    "MEX": "MX", "NOR": "NO", "NZL": "NZ", "POL": "PL", "ROU": "RO",
    "RUS": "RU", "SWE": "SE", "TUR": "TR", "USA": "US", "ZAF": "ZA",
}

# Area codes whose money stock is reported in millions of local currency.
MONEY_UNIT_MULT = 6


def _month_after(month):
    """Return the YYYY-MM string following the given one."""
    year, mon = int(month[:4]), int(month[5:7])
    mon += 1
    if mon > 12:
        year, mon = year + 1, 1
    return f"{year:04d}-{mon:02d}"


def _fetch_oecd_money():
    """Return {(area, measure): {YYYY-MM: value}} in millions of local currency.

    Uses the not-seasonally-adjusted (ADJUSTMENT=N) monthly series, which is
    the longest and most complete vintage. Values flagged as anything other
    than a normal observation are skipped so that estimates and breaks do not
    silently enter the aggregate.
    """
    print("Global money supply, national currency (OECD DF_MONAGG)...")
    url = ("https://sdmx.oecd.org/public/rest/data/"
           "OECD.SDD.STES,DSD_STES@DF_MONAGG,4.0/all"
           "?startPeriod=2009-01&dimensionAtObservation=AllDimensions&format=csvfile")
    raw = fetch(url, timeout=180)

    series = defaultdict(dict)
    for row in csv.DictReader(io.StringIO(raw)):
        if (row["FREQ"] != "M" or row["UNIT_MEASURE"] != "XDC"
                or row["ADJUSTMENT"] != "N" or row["OBS_STATUS"] != "A"):
            continue
        area = row["REF_AREA"]
        if area not in OECD_TO_BIS_FX:
            continue
        if row["UNIT_MULT"] != str(MONEY_UNIT_MULT):
            print(f"    ! unexpected UNIT_MULT for {area}: {row['UNIT_MULT']}",
                  file=sys.stderr)
            continue
        try:
            series[(area, row["MEASURE"])][row["TIME_PERIOD"]] = float(row["OBS_VALUE"])
        except ValueError:
            continue

    areas = sorted({area for (area, _measure) in series})
    print(f"    {len(areas)} reporting areas")
    return series


def _fetch_bis_fx():
    """Return {BIS area: {YYYY-MM: local currency units per USD}}.

    Month-end ("E") observations, which line up with month-end money stocks.
    """
    print("Month-end exchange rates vs USD (BIS WS_XRU)...")
    url = ("https://stats.bis.org/api/v1/data/BIS,WS_XRU,1.0/M...E"
           "?dimensionAtObservation=AllDimensions&format=csvfile")
    raw = fetch(url, timeout=180)

    rates = defaultdict(dict)
    for row in csv.DictReader(io.StringIO(raw)):
        if row["COLLECTION"] != "E":
            continue
        try:
            rates[row["REF_AREA"]][row["TIME_PERIOD"]] = float(row["OBS_VALUE"])
        except ValueError:
            continue
    print(f"    {len(rates)} currency areas")
    return rates


def _build_usd_aggregate():
    """Sum every reporting area's money stock converted to USD.

    Returns {YYYY-MM: {"M1": float, "M2": float, "M3": float}} in millions of
    USD. M1 is narrow money (OECD MANM), M2 is broad money (MABM), and M3 is
    their sum -- the closest available approximation of M3 on a consistent
    basis, since the OECD reports narrow and broad money on the same
    definitions across all reporting areas.

    A country's last published stock is carried forward when its money series
    lags its exchange rate by a month or two. The published data never lags by
    more than two months, so no stale value is ever carried for long.
    """
    money = _fetch_oecd_money()
    fx = _fetch_bis_fx()

    def last_at_or_before(values, month):
        candidates = [m for m in values if m <= month]
        return values[max(candidates)] if candidates else None

    # Observation window: from January 2009, the month Bitcoin was created, to
    # the last month any area reports.
    months = set()
    for (_area, _measure), values in money.items():
        months.update(values)
    end = max(months)

    aggregate = {}
    month = "2009-01"
    while month <= end:
        totals = {"M1": 0.0, "M2": 0.0, "M3": 0.0}
        contributing = 0
        for area, bis_area in OECD_TO_BIS_FX.items():
            narrow = last_at_or_before(money.get((area, "MANM"), {}), month)
            broad = last_at_or_before(money.get((area, "MABM"), {}), month)
            if narrow is None or broad is None:
                continue
            if bis_area == "US":
                rate = 1.0
            else:
                rate = last_at_or_before(fx.get(bis_area, {}), month)
            if not rate:
                continue
            totals["M1"] += narrow / rate
            totals["M2"] += broad / rate
            totals["M3"] += (narrow + broad) / rate
            contributing += 1
        # Require essentially the whole panel so the series has no early
        # months built from only a handful of countries.
        if contributing >= len(OECD_TO_BIS_FX) - 1:
            aggregate[month] = totals
        month = _month_after(month)

    print(f"    {len(aggregate)} months, {contributing} areas in the final month")
    return aggregate


def build_global_money():
    aggregate = _build_usd_aggregate()
    if not aggregate:
        raise RuntimeError("no overlapping money supply data")

    months = sorted(aggregate)

    # One file per chart so each page reads exactly what it needs.
    for directory, measure in (("btc-global-m1-adjusted", "M1"),
                               ("btc-global-m2-adjusted", "M2"),
                               ("btc-global-m3-adjusted", "M3")):
        rows = ["Month,Global %s Supply of All Currencies (USD)" % measure]
        for month in months:
            rows.append(f"{month}-01,{aggregate[month][measure]:.0f}")
        write(os.path.join(directory, "global-money-supply-usd.csv"),
              "\n".join(rows) + "\n")

    # Shared per-month composition table, useful for auditing the aggregate.
    rows = ["Month,M1,M2,M3,Contributing Areas"]
    for month in months:
        totals = aggregate[month]
        rows.append(f"{month}-01,{totals['M1']:.0f},{totals['M2']:.0f},"
                    f"{totals['M3']:.0f},{len(OECD_TO_BIS_FX)}")
    write("global-money-supply.csv", "\n".join(rows) + "\n")

    first, last = months[0], months[-1]
    for measure in ("M1", "M2", "M3"):
        grew = aggregate[last][measure] / aggregate[first][measure]
        print(f"    {measure}: {aggregate[first][measure] / 1e6:,.2f}T USD "
              f"-> {aggregate[last][measure] / 1e6:,.2f}T USD ({grew:.2f}x)")


def build_money_supply_growth():
    """Year-over-year growth rate of the global money supply, per measure.

    Reads the shared aggregate written by build_global_money() and converts
    each level series into a 12-month percent change, so the chart shows the
    rate of debasement rather than the level. Months whose year-ago value is
    missing are skipped instead of interpolated, which is why the series
    starts in 2010.
    """
    print("Global money supply year-over-year growth...")
    levels = {}
    with open(os.path.join(ROOT, "global-money-supply.csv")) as fh:
        for row in csv.DictReader(fh):
            levels[row["Month"][:7]] = {
                "M1": float(row["M1"]),
                "M2": float(row["M2"]),
                "M3": float(row["M3"]),
            }

    months = sorted(levels)
    rows = ["Month,Global M1 YoY %,Global M2 YoY %,Global M3 YoY %"]
    for month in months:
        prior = f"{int(month[:4]) - 1:04d}-{month[5:7]}"
        if prior not in levels:
            continue
        values = []
        for measure in ("M1", "M2", "M3"):
            base = levels[prior][measure]
            values.append((levels[month][measure] / base - 1.0) * 100.0
                          if base > 0 else 0.0)
        rows.append(f"{month}-01,{values[0]:.4f},{values[1]:.4f},{values[2]:.4f}")
    write("global-money-supply-growth/global-money-supply-yoy.csv",
          "\n".join(rows) + "\n")


# --------------------------------------------------------------------------
# Gold price (Yahoo Finance, key-less chart API)
# --------------------------------------------------------------------------
def build_gold_price():
    """Month-end gold price in USD per troy ounce.

    Yahoo's chart endpoint is key-less and returns front-month COMEX gold
    (GC=F) back to 2000, which covers Bitcoin's whole history. The daily
    series is requested explicitly with a period range: asking for
    range=max down-samples to monthly bars that drop occasional months, while
    a daily request has no gaps. Only the final trading day of each month is
    kept so the series lines up with the month-end BTC close used everywhere
    else.
    """
    print("Gold price (Yahoo Finance GC=F)...")
    start = int(dt.datetime(2009, 1, 1, tzinfo=dt.UTC).timestamp())
    end = int(dt.datetime.now(dt.UTC).timestamp())
    raw = fetch("https://query1.finance.yahoo.com/v8/finance/chart/"
                f"GC=F?period1={start}&period2={end}&interval=1d")
    result = json.loads(raw)["chart"]["result"][0]
    timestamps = result["timestamp"]
    closes = result["indicators"]["quote"][0]["close"]

    by_month = {}
    for ts, close in zip(timestamps, closes):
        if close is None:
            continue
        day = dt.datetime.fromtimestamp(ts, dt.UTC)
        by_month[(day.year, day.month)] = (day.day, float(close))

    rows = ["Month,Gold Price (USD per troy ounce)"]
    for (year, month) in sorted(by_month):
        rows.append(f"{year:04d}-{month:02d}-01,{by_month[(year, month)][1]:.2f}")
    write("btc-in-gold/gold-price-usd.csv", "\n".join(rows) + "\n")


# --------------------------------------------------------------------------
# S&P 500 monthly close (Yahoo Finance, key-less chart API)
# --------------------------------------------------------------------------
def build_sp500():
    """Month-end S&P 500 close, the equity benchmark BTC is compared against.

    Uses the same Yahoo chart endpoint as the gold price, but with a daily
    request over the full period so no months are dropped by down-sampling;
    only the final trading day of each month is kept. The index starts in
    2005, which is well before the first priced BTC month.
    """
    print("S&P 500 (Yahoo Finance ^GSPC)...")
    start = int(dt.datetime(2005, 1, 1, tzinfo=dt.UTC).timestamp())
    end = int(dt.datetime.now(dt.UTC).timestamp())
    raw = fetch("https://query1.finance.yahoo.com/v8/finance/chart/"
                f"%5EGSPC?period1={start}&period2={end}&interval=1d")
    result = json.loads(raw)["chart"]["result"][0]
    timestamps = result["timestamp"]
    closes = result["indicators"]["quote"][0]["close"]

    by_month = {}
    for ts, close in zip(timestamps, closes):
        if close is None:
            continue
        day = dt.datetime.fromtimestamp(ts, dt.UTC)
        by_month[(day.year, day.month)] = (day.day, float(close))

    rows = ["Month,S&P 500 Close"]
    for (year, month) in sorted(by_month):
        rows.append(f"{year:04d}-{month:02d}-01,{by_month[(year, month)][1]:.2f}")
    write("btc-vs-sp500/sp500-monthly-close.csv", "\n".join(rows) + "\n")


# --------------------------------------------------------------------------
# US median home price and average hourly earnings (FRED)
# --------------------------------------------------------------------------
def build_real_goods():
    """US median new-home price and average hourly earnings, monthly.

    The home price series is quarterly, so it is forward-filled onto the
    monthly grid; both are carried forward when a month is missing so the
    PHP pages can join them against the monthly BTC price without gaps.
    """
    print("US median home price (FRED MSPUS)...")
    homes = fred_series("MSPUS")
    print("US average hourly earnings (FRED CES0500000003)...")
    wages = fred_series("CES0500000003")

    def fill_monthly(series):
        """Forward-fill a (date, value) series onto a continuous monthly grid."""
        lookup = {date[:7]: value for date, value in series}
        out = {}
        last = None
        month = series[0][0][:7]
        end = series[-1][0][:7]
        while month <= end:
            if month in lookup:
                last = lookup[month]
            if last is not None:
                out[month] = last
            month = _month_after(month)
        return out

    homes_monthly = fill_monthly(homes)
    wages_monthly = fill_monthly(wages)

    months = sorted(set(homes_monthly) & set(wages_monthly))
    rows = ["Month,Median New Home Price (USD),Average Hourly Earnings (USD)"]
    for month in months:
        rows.append(f"{month}-01,{homes_monthly[month]:.0f},{wages_monthly[month]:.2f}")
    write("btc-in-real-goods/us-home-price-and-wages.csv",
          "\n".join(rows) + "\n")


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
    build_cpi()
    build_global_money()
    build_money_supply_growth()
    build_gold_price()
    build_sp500()
    build_real_goods()
    build_btc_inflation()
    print("done.")


if __name__ == "__main__":
    main()

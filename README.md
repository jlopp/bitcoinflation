# bitcoinflation

A Collection of Unique Bitcoin Price Charts

## Charts

| Chart | Directory |
| --- | --- |
| Bitcoin Supply Inflation | `btc-supply-inflation/` |
| Bitcoin Supply Adjusted to USD M1 Supply | `btc-usd-m1-adjusted/` |
| Bitcoin Supply Adjusted to USD M2 Supply | `btc-usd-m2-adjusted/` |
| Bitcoin Supply Adjusted to Global M3 Supply | `btc-global-m3-adjusted/` |
| Bitcoin Exchange Rate in 2009 Dollars (CPI) | `cpi-adjusted-exchange-rate/` |

## Updating the data

The CSV/JSON datasets are refreshed from free, key-less APIs by running:

```sh
python3 update_data.py
```

| Dataset | Source |
| --- | --- |
| `monthly-btc-usd-exchange-rate.csv` | [blockchain.info](https://www.blockchain.com/explorer/charts/market-price) chart API (full history, month-end close) |
| `m1-supply.csv` | [FRED `M1NS`](https://fred.stlouisfed.org/series/M1NS) |
| `m2-supply.csv` | [FRED `M2NS`](https://fred.stlouisfed.org/series/M2NS) |
| `monthly-usd-inflation-rate.csv` | [FRED `CPIAUCNS`](https://fred.stlouisfed.org/series/CPIAUCNS), month-over-month % change |
| `global_m3.csv` | [OECD SDMX](https://data-explorer.oecd.org/) dataflow `DSD_STES@DF_MONAGG` (broad money, 2015 = 100) |
| `historic-btc-inflation-rate.json` / `.csv` | [CoinMetrics community API](https://docs.coinmetrics.io/api/v4/) `SplyCur` (daily supply) |
| `latest-supply.json` | Anchor (date + supply) for the forward projection in the supply inflation chart |

Each chart also shows a realtime spot price, fetched at request time from the
free [Coinbase spot price API](https://api.coinbase.com/v2/prices/BTC-USD/spot).

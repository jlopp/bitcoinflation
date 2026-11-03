<?php
      // Load the monthly gold price (USD per troy ounce), month-end close.
      $gold_prices = array();
      if (($handle = fopen("gold-price-usd.csv", "r")) !== FALSE) {
        fgetcsv($handle, 1000, ","); // header
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $gold_prices[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read gold price CSV file!";
        exit(1);
      }

      // Load historical exchange rate data from CSV
      $exchange_rates = array();
      if (($handle = fopen("monthly-btc-usd-exchange-rate.csv", "r")) !== FALSE) {
        fgetcsv($handle, 1000, ","); // header
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $exchange_rates[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read historic exchange rate CSV file!";
        exit(1);
      }

      // index gold by month so the two series stay aligned
      $goldByMonth = array();
      foreach ($gold_prices as $entry) {
        $goldByMonth[substr($entry[0], 0, 7)] = (float) $entry[1];
      }

      // build the BTC price in troy ounces of gold
      $xValues = array();
      $yValues = array();
      $lastGold = NULL;
      foreach ($exchange_rates as $rate) {
        $month = substr($rate[0], 0, 7);
        if (isset($goldByMonth[$month])) {
          $lastGold = $goldByMonth[$month];
        }
        $price = (float) $rate[1];
        if ($lastGold === NULL || $price <= 0) {
          continue;
        }
        $xValues[] = '"' . $rate[0] . '"';
        $yValues[] = '"' . ($price / $lastGold) . '"';
      }

      // realtime spot price, converted at the latest gold price
      $spot = @file_get_contents("https://api.coinbase.com/v2/prices/BTC-USD/spot");
      $spotData = $spot === FALSE ? NULL : json_decode($spot, true);
      if ($spotData === NULL || !isset($spotData["data"]["amount"])) {
        echo "Failed to read realtime exchange rate data from Coinbase!";
        exit(1);
      }
      $currentExchangeRate = (float) $spotData["data"]["amount"];
      $btcInGold = $currentExchangeRate / $lastGold;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Bitcoinflation - Bitcoin Priced in Gold</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="bitcoin, inflation" />
    <meta name="Robots" content="index,follow" />
    <meta name="description" content="The Bitcoin exchange rate priced in troy ounces of gold" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bitcoinflation" />
    <meta name="twitter:description" content="The Bitcoin exchange rate priced in troy ounces of gold." />
    <meta name="twitter:image" content="https://www.lopp.net/bitcoinflation/btc-in-gold.png" />
    <meta name="twitter:site" content="@lopp" />
    <meta name="twitter:creator" content="@lopp" />

    <meta property="og:type" content="website" />
    <meta property="og:description" content="The Bitcoin exchange rate priced in troy ounces of gold." />
    <meta property="og:image" content="https://www.lopp.net/bitcoinflation/btc-in-gold.png" />
    <meta property="og:url" content="https://www.lopp.net/bitcoinflation/" />
    <meta property="og:title" content="Bitcoinflation" />

    <script>
      window.PlotlyConfig = {MathJaxConfig: 'local'};
    </script>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <script src="../scale-toggle.js"></script>
    <style>
      :root {
        --bg: #f6f7f9;
        --bg-card: #ffffff;
        --text: #16181d;
        --text-muted: #5b616e;
        --accent: #f7931a; /* bitcoin orange */
        --accent-soft: rgba(247, 147, 26, 0.12);
        --border: #e4e6eb;
        --shadow: 0 1px 2px rgba(16, 18, 22, 0.04), 0 8px 24px rgba(16, 18, 22, 0.06);
        --radius: 14px;
      }

      @media (prefers-color-scheme: dark) {
        :root {
          --bg: #0e1013;
          --bg-card: #171a1f;
          --text: #ecedef;
          --text-muted: #9aa0ab;
          --accent-soft: rgba(247, 147, 26, 0.16);
          --border: #262a31;
          --shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 8px 24px rgba(0, 0, 0, 0.35);
        }
      }

      * {
        box-sizing: border-box;
      }

      html {
        -webkit-text-size-adjust: 100%;
      }

      body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
          "Helvetica Neue", Arial, sans-serif;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
      }

      .wrap {
        max-width: 1120px;
        margin: 0 auto;
        padding: 0 20px;
      }

      /* ---------- Header ---------- */
      header.site {
        padding: 26px 0 18px;
      }

      header.site .wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
      }

      .logo {
        display: inline-flex;
        align-items: center;
        gap: 11px;
        text-decoration: none;
        color: inherit;
      }

      .logo .mark {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--accent);
        color: #fff;
        display: grid;
        place-items: center;
        font-weight: 700;
        font-size: 17px;
        line-height: 1;
      }

      .logo h1 {
        margin: 0;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
        font-weight: 700;
      }

      .back {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
      }

      .back:hover {
        color: var(--accent);
      }

      /* ---------- Content ---------- */
      main {
        padding-bottom: 56px;
      }

      .page-head {
        max-width: 780px;
        margin: 6px 0 22px;
      }

      .page-title {
        margin: 0 0 10px;
        font-size: clamp(1.4rem, 3.6vw, 1.95rem);
        font-weight: 700;
        letter-spacing: -0.02em;
        line-height: 1.2;
      }

      .lede {
        margin: 0 0 16px;
        color: var(--text-muted);
        font-size: 0.97rem;
      }

      .lede a {
        color: var(--accent);
        text-decoration: none;
      }

      .lede a:hover {
        text-decoration: underline;
      }

      .stat {
        display: inline-flex;
        flex-direction: column;
        gap: 2px;
        background: var(--accent-soft);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px 18px;
      }

      .stat .label {
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--text-muted);
      }

      .stat .value {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.02em;
      }

      /* ---------- Chart ---------- */
      .chart-card {
        margin-top: 6px;
        padding: 10px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
      }

      #chart {
        width: 100%;
        height: clamp(360px, 66vh, 700px);
      }

      /* ---------- Footer ---------- */
      footer.site {
        border-top: 1px solid var(--border);
        padding: 22px 0 38px;
        color: var(--text-muted);
        font-size: 0.85rem;
        text-align: center;
      }

      footer.site a {
        color: var(--accent);
        text-decoration: none;
      }

      footer.site a:hover {
        text-decoration: underline;
      }

      @media (max-width: 480px) {
        header.site {
          padding: 20px 0 14px;
        }
        .stat {
          width: 100%;
        }
      }

      @media (prefers-reduced-motion: reduce) {
        * {
          transition: none !important;
        }
      }
    </style>
  </head>

  <body>
    <header class="site">
      <div class="wrap">
        <a class="logo" href="../index.html">
          <span class="mark" aria-hidden="true">₿</span>
          <h1>Bitcoinflation</h1>
        </a>
        <a class="back" href="../index.html">← All charts</a>
      </div>
    </header>

    <main class="wrap">
      <div class="page-head">
        <h2 class="page-title">Bitcoin Priced in Gold</h2>
        <p class="lede">
          Every other chart here adjusts the dollar price of Bitcoin by a fiat
          measure — CPI, or the growth of the money supply. Gold is different:
          it is the one monetary asset that is not issued by anyone, so pricing
          Bitcoin against it removes fiat debasement without assuming a fiat
          inflation figure. A rising line means Bitcoin is gaining against the
          oldest form of money. Gold is the front-month COMEX price in USD per
          troy ounce.
        </p>
        <div class="stat">
          <span class="label">Current price in troy ounces of gold</span>
          <span class="value"><?= number_format($btcInGold, 2) ?> oz</span>
        </div>
      </div>

      <div class="chart-card">
        <div id="chart" class="plotly-graph-div"></div>
      </div>
    </main>

    <script>
      window.PLOTLYENV = window.PLOTLYENV || {};
      var layout = {
        autosize: true,
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        hovermode: 'closest',
        showlegend: true,

        legend: {
          orientation: "h",
          x: 0.5,
          xanchor: "center",
        },

        xaxis: {
          showgrid: false,
          automargin: true,
          autorange: true,

          showspikes: true,
          spikemode: 'across',
          spikethickness: 0.5,
          spikecolor: '#333',
          spikedash: 'line',
        },

        yaxis: {
          title: 'BTC Price in Troy Ounces of Gold',
          type: 'log',
          hoverformat: '.4r',
          titlefont: {
            family: 'Open Sans, Arial',
            weight: 500,
            size: 12,
            color: '#666'
          },
          tickfont: {
            size: 9,
            color: '#666',
          },
          tickwidth: 5,
          ticklen: 5,
          tickcolor: '#999',
          dtick: 1,

          showgrid: false,
          zeroline: false,

          showspikes: true,
          spikemode: 'across',
          spikethickness: 0.5,
          spikecolor: '#333',
          spikedash: 'line',
        }
      }

      var series = {
        x: [<?= implode(",", $xValues) ?>],
        y: [<?= implode(",", $yValues) ?>],
        type: 'scatter',
        mode: 'lines',
        name: 'Bitcoin Exchange Rate in Troy Ounces of Gold',
        line: { color: 'rgb(212, 175, 55)', width: 1 },
        opacity: 0.85,
        hoverinfo: 'name+y',
        hoverlabel: {
          bordercolor: '#FFF',
          font: { family: 'Open Sans, Arial', weight: 500, size: 10 },
        },
      }

      Plotly.newPlot('chart', [series], layout, {responsive: true});
      addYAxisScaleToggle('chart');
    </script>

    <footer class="site">
      <div class="wrap">
        Data refreshed from free, key-less public APIs. <a href="../index.html">Back to all charts</a>
      </div>
    </footer>
  </body>
</html>

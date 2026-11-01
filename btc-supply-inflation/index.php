<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Bitcoinflation - Tracking the Inflation Rate of Bitcoin's Money Supply</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="bitcoin, inflation" />
    <meta name="Robots" content="index,follow" />
    <meta name="description" content="Tracking the inflation rate of Bitcoin's money supply" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bitcoinflation" />
    <meta name="twitter:description" content="Tracking the inflation rate of Bitcoin's money supply." />
    <meta name="twitter:image" content="https://www.lopp.net/bitcoinflation/screenshot.png" />
    <meta name="twitter:site" content="@lopp" />
    <meta name="twitter:creator" content="@lopp" />

    <meta property="og:type" content="website" />
    <meta property="og:description" content="Tracking the inflation rate of Bitcoin's money supply." />
    <meta property="og:image" content="https://www.lopp.net/bitcoinflation/screenshot.png" />
    <meta property="og:url" content="https://www.lopp.net/bitcoinflation/" />
    <meta property="og:title" content="Bitcoinflation" />

    <script>
      window.PlotlyConfig = {MathJaxConfig: 'local'};
    </script>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
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
        <h2 class="page-title">Bitcoin Supply Inflation</h2>
        <p class="lede">
          Bitcoin's rate of monetary inflation is highly predictable. This chart shows the historical actual inflation rate in realtime along with the projected future inflation rate. To learn more about Bitcoin's issuance schedule, check out <a href="https://en.bitcoin.it/wiki/Controlled_supply">this wiki article</a>.
        </p>
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

       // X-axis styling
        xaxis: {

          range: ['2009-01-03','2100-01-01'],

          showgrid: false,
          automargin: true,
          //autorange: true,
          //rangeslider: {range: ['2019-01-03 12:00', '2019-02-15 12:00']},

          // vertical crosshair
          showspikes:true,
          spikemode:'across',
          spikethickness:0.5,
          spikecolor:'#333',
          spikedash:'line',
        },

      // Y-axis styling
        yaxis: {

          title:       'Annualized Inflation Rate %',
          type:        'log',
          hoverformat: '.4r',
          titlefont: {
              family: 'Open Sans, Arial',
              weight: 500,
              size: 12,
              color: '#666'
          },
          tickfont: {
            size:9,
            color: '#666',
          },
          tickwidth: 5,
          ticklen: 5,
          tickcolor: '#999',
          dtick: 1,

          showgrid: false,
          //automargin: true,
          zeroline: false,

          // horizontal crosshair
          showspikes:true,
          spikemode:'across',
          spikethickness:0.5,
          spikecolor:'#333',
          spikedash:'line',
        }
      }

      var inflation = {
         x: [],
         y: [],
         marker: {
            opacity: 0
         },
         line: {
            color:'',
            width: 1,
            dash: ''
         },
         opacity:0.75,
            showlegend:true,

         name:'Bitcoin Annualized Monetary Supply Inflation Rate',   hoverinfo:'name+y',

         yaxis:'y',

         hoverlabel: {
            bordercolor:'#FFF',
            font: { family: 'Open Sans, Arial', weight: 500, size: 10, },
         },
      }

<?php
      // Load historical inflation data from CSV
      $json = file_get_contents("historic-btc-inflation-rate.json");
      if ($json === FALSE) {
        echo "Failed to read historic inflation rate json file!";
        exit(1);
      }
      $rates = json_decode($json, true);
      $historic_rates = array(); // formatted for chart

      foreach ($rates as $rate) {
        $rateDate = new DateTime();
        $rateDate->setTimestamp($rate['t']);
        $historic_rates[] = array($rateDate->format("Y-m-d"), $rate['v']);
      }

      // anchor the projection on the most recent supply snapshot written by
      // update_data.py (falls back to a 2023-09-19 snapshot if it is missing)
      $currentSupply = 19489541; // 9.19.2023 0:00 GMT
      $currentDate = new DateTime();
      $currentDate->setTimestamp(1695081600);
      $latest = @file_get_contents("latest-supply.json");
      if ($latest !== FALSE) {
        $snapshot = json_decode($latest, true);
        if ($snapshot !== NULL && isset($snapshot["t"], $snapshot["supply"])) {
          $currentDate->setTimestamp($snapshot["t"]);
          $currentSupply = $snapshot["supply"];
        }
      }

      // Bitcoin halving dates (UTC); the block subsidy is derived from the
      // current date so the projection needs no external block-height lookup.
      $halvingDates = array();
      $halving = new DateTime('2012-11-28');
      for ($i = 0; $i < 32; $i++) {
        $halvingDates[] = clone $halving;
        $halving->add(new DateInterval('P1461D')); // ~4 years between halvings
      }

      // calculate future inflation
      while ($currentDate->format("Y") < 2100) {
        // derive the block subsidy from how many halvings have occurred
        $halvingsPassed = 0;
        foreach ($halvingDates as $halvingDate) {
          if ($currentDate >= $halvingDate) {
            $halvingsPassed++;
          }
        }
        $currentBlockReward = 50 / (2 ** $halvingsPassed);
        $currentInflationRate = ($currentBlockReward * 144 * 365) / $currentSupply;

        // prevent duplicate dates
        $lastDate = new DateTime($historic_rates[count($historic_rates) - 1][0]);
        $lastDate->add(new DateInterval('P1D'));
        if ($currentDate > $lastDate) {
          $historic_rates[] = array($currentDate->format("Y-m-d"), $currentInflationRate);
        }

        // add another day and the day's worth of block subsidies
        $currentSupply += $currentBlockReward * 144;
        $currentDate->add(new DateInterval('P1D'));
      }

      // build the JSON required for Plotly
      $xValues = array();
      $yValues = array();
      foreach ($historic_rates as $rate) {
        $xValues[] = '"' . substr($rate[0], 0, 10) . '"'; // truncate any timestamp data from 2009-02-01T00:00:00Z to Y-m-d
        $yValues[] = '"' . ($rate[1]*100) . '"';
      }
      $chartJSON = '[{"line": {"color": "rgb(31, 119, 180)"}, "name": "Bitcoin Annualized Monetary Supply Inflation Rate (%)", "type": "scatter", "x": ['
                    . implode(",", $xValues)
                    . '], "y": ['
                    . implode(",", $yValues)
                    . "]}]";
?>
      Plotly.newPlot('chart', <?= $chartJSON ?>, layout, {responsive: true});
    </script>

    <footer class="site">
      <div class="wrap">
        Data refreshed from free, key-less public APIs. <a href="../index.html">Back to all charts</a>
      </div>
    </footer>
  </body>
</html>

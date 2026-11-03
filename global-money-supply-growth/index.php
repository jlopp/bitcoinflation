<?php
      // Load the year-over-year growth of the global money supply, all
      // currencies converted to USD.
      $growth = array();
      if (($handle = fopen("global-money-supply-yoy.csv", "r")) !== FALSE) {
        fgetcsv($handle, 1000, ","); // header
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $growth[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read global money supply growth CSV file!";
        exit(1);
      }

      $xValues = array();
      $m1Values = array();
      $m2Values = array();
      $m3Values = array();
      $latestM2 = NULL;
      $latestM3 = NULL;
      foreach ($growth as $entry) {
        $xValues[] = '"' . $entry[0] . '"';
        $m1Values[] = '"' . $entry[1] . '"';
        $m2Values[] = '"' . $entry[2] . '"';
        $m3Values[] = '"' . $entry[3] . '"';
        $latestM2 = (float) $entry[2];
        $latestM3 = (float) $entry[3];
      }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Bitcoinflation - The Year-over-Year Growth of the Global Money Supply</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="bitcoin, inflation" />
    <meta name="Robots" content="index,follow" />
    <meta name="description" content="The year-over-year growth rate of the global money supply of all currencies since 2009" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bitcoinflation" />
    <meta name="twitter:description" content="The year-over-year growth rate of the global money supply of all currencies." />
    <meta name="twitter:image" content="https://www.lopp.net/bitcoinflation/global-money-supply-growth.png" />
    <meta name="twitter:site" content="@lopp" />
    <meta name="twitter:creator" content="@lopp" />

    <meta property="og:type" content="website" />
    <meta property="og:description" content="The year-over-year growth rate of the global money supply of all currencies." />
    <meta property="og:image" content="https://www.lopp.net/bitcoinflation/global-money-supply-growth.png" />
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

      .stat-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
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
        <h2 class="page-title">Global Money Supply Growth</h2>
        <p class="lede">
          The level charts show how much money exists; this one shows how fast
          it is being created. Each line is the 12-month percent change in the
          global money supply of all currencies, converted to USD. The
          2020&ndash;2021 spike is the clearest monetary event of Bitcoin's
          lifetime: broad money grew roughly 19% year-over-year at its peak.
          M1 is definitionally unstable because the US reclassified savings
          deposits in May 2020, so its spike overstates true narrow-money
          growth; M2 and M3 are comparable over this window.
        </p>
        <div class="stat-row">
          <div class="stat">
            <span class="label">Global M2 growth today</span>
            <span class="value"><?= number_format($latestM2, 1) ?>%</span>
          </div>
          <div class="stat">
            <span class="label">Global M3 growth today</span>
            <span class="value"><?= number_format($latestM3, 1) ?>%</span>
          </div>
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
          title: 'Year-over-Year Growth (%)',
          type: 'linear',
          hoverformat: '.2f',
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

          showgrid: false,
          zeroline: true,
          zerolinecolor: '#999',

          showspikes: true,
          spikemode: 'across',
          spikethickness: 0.5,
          spikecolor: '#333',
          spikedash: 'line',
        }
      }

      var m1 = {
        x: [<?= implode(",", $xValues) ?>],
        y: [<?= implode(",", $m1Values) ?>],
        type: 'scatter',
        mode: 'lines',
        name: 'Global M1 YoY (%)',
        line: { color: 'rgb(44, 160, 44)', width: 1, dash: 'dot' },
        opacity: 0.7,
        hoverinfo: 'name+y',
        hoverlabel: {
          bordercolor: '#FFF',
          font: { family: 'Open Sans, Arial', weight: 500, size: 10 },
        },
      }

      var m2 = {
        x: [<?= implode(",", $xValues) ?>],
        y: [<?= implode(",", $m2Values) ?>],
        type: 'scatter',
        mode: 'lines',
        name: 'Global M2 YoY (%)',
        line: { color: 'rgb(31, 119, 180)', width: 1 },
        opacity: 0.8,
        hoverinfo: 'name+y',
        hoverlabel: {
          bordercolor: '#FFF',
          font: { family: 'Open Sans, Arial', weight: 500, size: 10 },
        },
      }

      var m3 = {
        x: [<?= implode(",", $xValues) ?>],
        y: [<?= implode(",", $m3Values) ?>],
        type: 'scatter',
        mode: 'lines',
        name: 'Global M3 YoY (%)',
        line: { color: 'rgb(247, 147, 26)', width: 1 },
        opacity: 0.8,
        hoverinfo: 'name+y',
        hoverlabel: {
          bordercolor: '#FFF',
          font: { family: 'Open Sans, Arial', weight: 500, size: 10 },
        },
      }

      Plotly.newPlot('chart', [m1, m2, m3], layout, {responsive: true});
      addYAxisScaleToggle('chart', { default: 'linear' });
    </script>

    <footer class="site">
      <div class="wrap">
        Data refreshed from free, key-less public APIs. <a href="../index.html">Back to all charts</a>
      </div>
    </footer>
  </body>
</html>

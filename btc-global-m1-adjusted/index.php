<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Bitcoinflation - Tracking the Global M1 Adjusted Bitcoin Exchange Rate in 2009 Dollars</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="bitcoin, inflation" />
    <meta name="Robots" content="index,follow" />
    <meta name="description" content="Bitcoinflation - Tracking the Global M1 Adjusted Bitcoin Exchange Rate in 2009 Dollars" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bitcoinflation" />
    <meta name="twitter:description" content="Bitcoinflation - Tracking the Global M1 Adjusted Bitcoin Exchange Rate in 2009 Dollars" />
    <meta name="twitter:image" content="https://www.lopp.net/bitcoinflation/exchange-rate-global-m1.png" />
    <meta name="twitter:site" content="@lopp" />
    <meta name="twitter:creator" content="@lopp" />

    <meta property="og:type" content="website" />
    <meta property="og:description" content="Bitcoinflation - Tracking the Global M1 Adjusted Bitcoin Exchange Rate in 2009 Dollars" />
    <meta property="og:image" content="https://www.lopp.net/bitcoinflation/exchange-rate-global-m1.png" />
    <meta property="og:url" content="https://www.lopp.net/bitcoinflation/" />
    <meta property="og:title" content="Bitcoinflation" />

    <script>
      window.PlotlyConfig = {MathJaxConfig: 'local'};
    </script>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>
    <style>
      html, body {
        width: 99%;
        height: 99%;
      }
    </style>
  </head>
<?php
      // Load the global M1 money supply, all currencies converted to USD.
      // Built by update_data.py from OECD national money stocks + BIS month-end
      // exchange rates, so the values are directly comparable across countries.
      $money_supply = array();
      if (($handle = fopen("global-money-supply-usd.csv", "r")) !== FALSE) {
        // throw away header row
        fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $money_supply[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read global money supply CSV file!";
        exit(1);
      }

      // Load historical exchange rate data from CSV
      $exchange_rates = array();
      if (($handle = fopen("monthly-btc-usd-exchange-rate.csv", "r")) !== FALSE) {
        // throw away header row
        fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $exchange_rates[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read historic exchange rate CSV file!";
        exit(1);
      }

      // the January 2009 supply that every later month is adjusted back to
      $startingSupply = $money_supply[0][1];

      // index the supply by month so price and supply stay aligned even if one
      // series is missing a month; carry the last known value forward
      $supplyByMonth = array();
      foreach ($money_supply as $entry) {
        $supplyByMonth[substr($entry[0], 0, 7)] = $entry[1];
      }

      // build the JSON required for Plotly
      $xValues = array();
      $yValues = array();
      $currentSupply = $startingSupply;
      foreach ($exchange_rates as $rate) {
        $month = substr($rate[0], 0, 7);
        if (isset($supplyByMonth[$month])) {
          $currentSupply = $supplyByMonth[$month];
        }
        $adjustedExchangeRate = $rate[1] * ($startingSupply / $currentSupply);

        $xValues[] = '"' . $rate[0] . '"';
        $yValues[] = '"' . $adjustedExchangeRate . '"';
      }

      // calculate the realtime exchange rate (free Coinbase spot price, no API key)
      $currentExchangeRate = 0;
      $adjustedCurrentExchangeRate = 0;
      $spot = @file_get_contents("https://api.coinbase.com/v2/prices/BTC-USD/spot");
      $spotData = $spot === FALSE ? NULL : json_decode($spot, true);
      if ($spotData !== NULL && isset($spotData["data"]["amount"])) {
        $currentExchangeRate = (float) $spotData["data"]["amount"];
        $adjustedCurrentExchangeRate = number_format(
          $currentExchangeRate * ($startingSupply / $currentSupply));
      } else {
        echo "Failed to read realtime exchange rate data from Coinbase!";
        exit(1);
      }
?>
  <body>
    <p style="text-align:center">
      If you're familiar with Bitcoin then you've surely seen a chart of its exchange rate. However, price charts over long periods of time are flawed because the value (and supply) of fiat currencies is not constant. This chart adjusts the USD value based upon the change in the global M1 money supply of all currencies that has occurred since January 2009 when Bitcoin was created. Each country's money supply is converted to USD at its own exchange rate before being summed, so the aggregate is directly comparable across currencies; the USD value is adjusted monthly based upon <a href="https://data-explorer.oecd.org/">money supply data from the OECD</a> and <a href="https://data.bis.org/">exchange rate data from the BIS</a>.
    </p>
    <p style="text-align:center">
      The current BTC exchange rate adjusted to the global M1 supply of all currencies in January 2009 is $<strong><?php echo $adjustedCurrentExchangeRate; ?></strong>
    </p>
    <div id="chart" class="plotly-graph-div" style="height:100%; width:100%;"></div>
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

          range: ['2009-01-03','2023-01-01'],

          showgrid: false,
          automargin: true,
          autorange: true,
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

          title:       'BTC Price in 2009 USD',
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

         name:'Bitcoin Exchange Rate adjusted for Global M1',   hoverinfo:'name+y',

         yaxis:'y',

         hoverlabel: {
            bordercolor:'#FFF',
            font: { family: 'Open Sans, Arial', weight: 500, size: 10, },
         },
      }

<?php
      $chartJSON = '[{"line": {"color": "rgb(31, 119, 180)"}, "name": "Bitcoin Exchange Rate Adjusted for Global M1 of All Currencies", "type": "scatter", "x": ['
                    . implode(",", $xValues)
                    . '], "y": ['
                    . implode(",", $yValues)
                    . "]}]";
?>
      Plotly.newPlot('chart', <?= $chartJSON ?>, layout, {responsive: true});
    </script>
  </body>
</html>

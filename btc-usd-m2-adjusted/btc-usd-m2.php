<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Bitcoinflation - Tracking the M2 Adjusted Bitcoin Exchange Rate in 2009 Dollars</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="bitcoin, inflation" />
    <meta name="Robots" content="index,follow" />
    <meta name="description" content="Tracking the M2 Adjusted Bitcoin Exchange Rate in 2009 Dollars" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bitcoinflation" />
    <meta name="twitter:description" content="Tracking the M2 Adjusted Bitcoin Exchange Rate in 2009 Dollars" />
    <meta name="twitter:image" content="https://www.lopp.net/bitcoinflation/exchange-rate-2009-m2.png" />
    <meta name="twitter:site" content="@lopp" />
    <meta name="twitter:creator" content="@lopp" />

    <meta property="og:type" content="website" />
    <meta property="og:description" content="Tracking the M2 Adjusted Bitcoin Exchange Rate in 2009 Dollars" />
    <meta property="og:image" content="https://www.lopp.net/bitcoinflation/exchange-rate-2009-m2.png" />
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
      // Load historical M2 money supply data from CSV
      // CSV is from https://fred.stlouisfed.org/series/M2NS
      $m2_supply = array();
      if (($handle = fopen("m2-supply.csv", "r")) !== FALSE) {
        // throw away header row
        fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $m2_supply[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read historic M2 supply CSV file!";
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

      // this is the value that will be adjusted monthly to M2 supply
      $startingM2 = $m2_supply[0][1];

      // build the JSON required for Plotly
      $xValues = array();
      $yValues = array();
      foreach ($exchange_rates as $rate) {
        $currentM2 = array_shift($m2_supply)[1];
        $m2Delta = $startingM2 / $currentM2;
        $adjustedExchangeRate = $rate[1] * $m2Delta;

        $xValues[] = '"' . $rate[0] . '"';
        $yValues[] = '"' . $adjustedExchangeRate . '"';
      }

      // calculate the realtime exchange rate
      $currentExchangeRate = 0;
      $adjustedCurrentExchangeRate = 0;
      $now = new DateTime();
      $now = $now->sub(new DateInterval('PT2H'));
      $timestamp = $now->getTimestamp();
      if (($handle = fopen("https://api.glassnode.com/v1/metrics/market/price_usd_close?a=btc&api_key=2CU0VqMuRLqcqbD3MLUK1tx02Zy&i=1h&f=csv&timestamp_format=humanized&s=$timestamp", "r")) !== FALSE) {
        // throw away header row
        fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $currentExchangeRate = $data[1];
        }
        $adjustedCurrentExchangeRate = number_format($currentExchangeRate * $m2Delta);
        fclose($handle);
      } else {
        echo "Failed to read realtime exchange rate data from glassnode!";
        exit(1);
      }
?>
  <body>
    <p style="text-align:center">
      If you're familiar with Bitcoin then you've surely seen a chart of its exchange rate. However, price charts over long periods of time are flawed because the value (and supply) of the dollar is not constant. This chart adjusts the USD value based upon the change in the M2 money supply that has occurred since January 2009 when Bitcoin was created; the USD value is adjusted monthly based upon <a href="https://fred.stlouisfed.org/series/M2NS">M2 data from the Federal Reserve</a>.
    </p>
    <p style="text-align:center">
      The current BTC exchange rate adjusted to the M2 supply of USD in January 2009 is $<strong><?php echo $adjustedCurrentExchangeRate; ?></strong>
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

         name:'Bitcoin Exchange Rate adjusted for USD M2',   hoverinfo:'name+y',

         yaxis:'y',

         hoverlabel: {
            bordercolor:'#FFF',
            font: { family: 'Open Sans, Arial', weight: 500, size: 10, },
         },
      }

<?php
      $chartJSON = '[{"line": {"color": "rgb(31, 119, 180)"}, "name": "Bitcoin Exchange Rate Adjusted for M2 of USD", "type": "scatter", "x": ['
                    . implode(",", $xValues)
                    . '], "y": ['
                    . implode(",", $yValues)
                    . "]}]";
?>
      Plotly.newPlot('chart', <?= $chartJSON ?>, layout, {responsive: true});
    </script>
  </body>
</html>
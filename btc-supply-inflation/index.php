<!DOCTYPE html>
<html lang="en">
  <head>
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
      html, body {
        width: 99%;
        height: 99%;
      }
    </style>
  </head>

  <body>
    <p style="text-align:center">
      Bitcoin's rate of monetary inflation is highly predictable. This chart shows the historical actual inflation rate in realtime along with the projected future inflation rate. To learn more about Bitcoin's issuance schedule, check out <a href="https://en.bitcoin.it/wiki/Controlled_supply">this wiki article</a>.
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

      $currentHeight = 808361; // 9.19.2023 0:00 GMT
      $currentSupply = 19489541; // 9.19.2023 0:00 GMT
      $currentDate = new DateTime();
      $currentDate->setTimestamp(1695081600);

      // calculate future inflation
      while ($currentDate->format("Y") < 2100) {
        // calculate annualized inflation rate for today at current block height
        $currentBlockEra = ceil($currentHeight / 210000);
        $currentBlockReward = 50 * (0.5 ** ($currentBlockEra - 1));
        $currentInflationRate = ($currentBlockReward * 144 * 365) / $currentSupply;

        // prevent duplicate dates
        $lastDate = new DateTime($historic_rates[count($historic_rates) - 1][0]);
        $lastDate->add(new DateInterval('P1D'));
        if ($currentDate > $lastDate) {
          $historic_rates[] = array($currentDate->format("Y-m-d"), $currentInflationRate);
        }

        // add another day, 144 blocks, and day's worth of block subsidies
        $currentHeight += 144;
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
  </body>
</html>
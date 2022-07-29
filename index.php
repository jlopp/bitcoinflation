<!DOCTYPE html>
<html lang="en">
  <head>
    <title>Bitcoinflation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="bitcoin, inflation" />
    <meta name="Robots" content="index,follow" />
    <meta name="description" content="Tracking the inflation rate of Bitcoin's money supply" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Bitcoinflation" />
    <meta name="twitter:description" content="Tracking the inflation rate of Bitcoin's money supply." />
    <meta name="twitter:image" content="" />

    <script type="text/javascript">
      window.PlotlyConfig = {MathJaxConfig: 'local'};
    </script>
    <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>

  </head>

  <body>
    <div id="chart" class="plotly-graph-div" style="height:100%; width:100%;"></div>
    <script type="text/javascript">
      var layout = {

        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',


        // chart dimensions responsive, see CSS
        // height: 700,
        margin: { l: 50, r: 50, b: 45, t: 25, pad: 0 },


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

          title:       '',
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
          //showgrid: false,

          range: [-0.5,3.5],


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

         name:'Inflation Rate',   hoverinfo:'name+y',

         yaxis:'y',

         hoverlabel: {
            bordercolor:'#FFF',
            font: { family: 'Open Sans, Arial', weight: 500, size: 10, },
         },
      }

<?php
      // Load historical inflation data from CSV
      $historic_rates = array();
      if (($handle = fopen("historic-btc-inflation-rate.csv", "r")) !== FALSE) {
        // throw away header row
        fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $historic_rates[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read historic inflation rate CSV file!";
        exit(1);
      }

      // determine unix timestamp in seconds of last day in static CSV for next API call
      $newestDate = new DateTime($historic_rates[count($historic_rates) - 1][0]);
      $timestamp = $newestDate->getTimestamp();

      // Fill in gap between last historical CSV data and today from glassnode API
      if (($handle = fopen("https://api.glassnode.com/v1/metrics/supply/inflation_rate?a=btc&api_key=2CU0VqMuRLqcqbD3MLUK1tx02Zy&i=24h&f=csv&timestamp_format=humanized&s=$timestamp", "r")) !== FALSE) {
        // throw away header row
        fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $historic_rates[] = $data;
        }
        fclose($handle);
      } else {
        echo "Failed to read inflation rate data from glassnode!";
        exit(1);
      }

      // Fill in future projected inflation rate based upon mining subsidy up until Jan 1, 2100
      $currentHeight = 0;
      if (($handle = fopen("https://blockstream.info/api/blocks/tip/height", "r")) !== FALSE) {
        $currentHeight = fgetcsv($handle, 1000, ",")[0];
        fclose($handle);
      } else {
        echo "Failed to read current block height from blockstream!";
        exit(1);
      }

      $currentDate = new DateTime();
      $currentDate = $currentDate->sub(new DateInterval('P3D'));
      $timestamp = $currentDate->getTimestamp();

      // determine current supply; use the most recent value
      $currentSupply = 0;
      if (($handle = fopen("https://api.glassnode.com/v1/metrics/supply/current?a=btc&api_key=2CU0VqMuRLqcqbD3MLUK1tx02Zy&i=24h&f=csv&timestamp_format=humanized&s=$timestamp", "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
          $currentSupply = $data[1];
        }
        fclose($handle);
      } else {
        echo "Failed to read current money supply from glassnode!";
        exit(1);
      }

      // calculate future inflation
      while ($currentDate->format("Y") < 2100) {
        // calculate annualized inflation rate for today at current block height
        $currentBlockEra = floor($currentHeight / 210000);
        $currentBlockReward = 50 * (0.5 ** currentBlockEra);
        $currentInflationRate = ($currentBlockReward * 144 * 365) / $currentSupply;

        $historic_rates[] = array($currentDate->format("Y-m-d"), $currentInflationRate);

        // add another day, 144 blocks, and day's worth of block subsidies
        $currentHeight += 144;
        $currentSupply += $currentBlockReward * 144;
        $currentDate->add(new DateInterval('P1D'));
      }
      //print_r($historic_rates);
      echo "var rates = " . json_encode($historic_rates) . ";\n";
?>
      // Create chart
      Plotly.newPlot('chart', [rates], layout, {responsive: true});
    </script>
  </body>
</html>
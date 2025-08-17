<?php
// This script takes a historical json snapshot of bitcoin supply inflation rates
// from glassnode and converts it to a CSV for bitcoinflation

// update CSV from https://studio.glassnode.com/charts/supply.InflationRate?a=BTC
// https://api.glassnode.com/v1/metrics/supply/inflation_rate?a=BTC&i=24h&referrer=charts

$output = "timestamp,value\n";
$inflationJson = json_decode(file_get_contents("inflation_rate.json"));

foreach ($inflationJson as $day) {
	if ($day->v == null)
		continue;
	$date = gmdate("m-d-Y", $day->t);
	$rate = $day->v;
	$output .= "$date,$rate\n";
}

file_put_contents("historic-btc-inflation-rate.csv", $output);
?>
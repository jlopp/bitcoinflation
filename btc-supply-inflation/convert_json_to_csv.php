<?php
// This script converts the historical bitcoin supply inflation rate JSON
// (produced by update_data.py from the free CoinMetrics community API)
// into a CSV for bitcoinflation.
//
// The JSON is refreshed by running `python3 update_data.py` from the repo root.

$output = "timestamp,value\n";
$inflationJson = json_decode(file_get_contents("historic-btc-inflation-rate.json"));

foreach ($inflationJson as $day) {
	if ($day->v == null)
		continue;
	$date = gmdate("m-d-Y", $day->t);
	$rate = $day->v;
	$output .= "$date,$rate\n";
}

file_put_contents("historic-btc-inflation-rate.csv", $output);
?>
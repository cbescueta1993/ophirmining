<?php
$binance_futures_url = "https://fapi.binance.com/fapi/v1/exchangeInfo";

$response = file_get_contents($binance_futures_url);
$data = json_decode($response, true);

$symbol=$_GET['symbol'];
foreach ($data['symbols'] as $s) {
    if ($s['symbol'] === $symbol) {
        echo "$symbol= Precision Info:\n";
        echo "Price Precision: " . $s['pricePrecision'] . "\n";
        echo "Quantity Precision: " . $s['quantityPrecision'] . "\n";
        echo "Minimum Order Size: " . $s['filters'][1]['minQty'] . "\n";
    }
}
?>
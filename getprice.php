<?php

$config = include 'config.php';

$api_key = $config['api_key'];
$api_secret = $config['api_secret'];
$binance_futures_url = $config['binance_futures_url']; // Binance Futures API URL

function get_futures_price($symbol) {
    global $api_key, $binance_futures_url;

    $url = $binance_futures_url . "/fapi/v1/ticker/price?symbol=" . $symbol;

    $headers = [
        "X-MBX-APIKEY: $api_key"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// **Get BTCUSDT Futures Price**
//$symbol = "BTCUSDT"; // Change to any symbol, e.g., ETHUSDT
//$response = get_futures_price($symbol);

if (isset($response['price'])) {
    echo "Current Futures Price of $symbol: " . $response['price'] . " USDT\n";
} else {
 //   echo "Error fetching price: " . json_encode($response);
}

?>

<?php
$config = include 'config.php';

$api_key = $config['api_key'];
$api_secret = $config['api_secret'];
$binance_futures_url = $config['binance_futures_url']; // Binance Futures API URL

function createTrailingStopOrder($symbol, $positionSide, $quantity, $activationPrice, $callbackRate = 3) {
    global $api_key, $api_secret, $binance_futures_url;
   
    $timestamp = round(microtime(true) * 1000);

    $params = [
        "symbol" => $symbol,
        "side" => $positionSide === "LONG" ? "BUY" : "SELL",
        "positionSide" => $positionSide, // LONG or SHORT
        "type" => "TRAILING_STOP_MARKET",
        "quantity" => $quantity,
        "activationPrice" => $activationPrice,
        "callbackRate" => $callbackRate, // 3% trailing stop loss
        "workingType" => "CONTRACT_PRICE",
        "priceProtect" => "FALSE",
        "timestamp" => $timestamp
    ];

    $queryString = http_build_query($params);
    $signature = hash_hmac('sha256', $queryString, $api_secret);
    $queryString .= "&signature=" . $signature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $binance_futures_url . "?" . $queryString);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-MBX-APIKEY: $api_key"
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Example usage:
$symbol = "BTCUSDT"; // Change to the trading pair you want
$positionSide = "LONG"; // Set to "SHORT" for short position
$quantity = 0.01; // Change based on your lot size
$activationPrice = 83000; // Change based on your strategy

$orderResponse = createTrailingStopOrder($symbol, $positionSide, $quantity, $activationPrice);
print_r($orderResponse);
?>
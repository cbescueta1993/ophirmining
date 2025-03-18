<?php
$config = include 'config.php';
$api_key = $config['api_key'];
$api_secret = $config['api_secret'];
$binance_futures_url = $config['binance_futures_url'];

function getMinUsdtAmount($symbol) {
    global $api_key, $binance_futures_url;

    $headers = ["X-MBX-APIKEY: $api_key"];

    // 🔹 Fetch symbol info for precision, min order size, and min notional
    $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $exchange_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $exchangeData = json_decode($response, true);

    $minQty = 0.001; // Default minimum order size
    $defaultMinNotional = 5; // Initial assumption, will be adjusted dynamically

    foreach ($exchangeData['symbols'] as $s) {
        if ($s['symbol'] === $symbol) {
            foreach ($s['filters'] as $filter) {
                if ($filter['filterType'] === 'LOT_SIZE') {
                    $minQty = floatval($filter['minQty']); // Minimum order quantity
                }
                if ($filter['filterType'] === 'NOTIONAL') {
                    $defaultMinNotional = floatval($filter['minNotional']); // Adjust dynamically if available
                }
            }
            break;
        }
    }

    // 🔹 Fetch market price
    $price_url = $binance_futures_url . "/fapi/v1/ticker/price?symbol=" . $symbol;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $price_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $priceData = json_decode($response, true);
    $marketPrice = isset($priceData['price']) ? floatval($priceData['price']) : null;

    if (!$marketPrice) {
        die("Error fetching market price for $symbol.");
    }

    // 🔹 Adjust default minimum notional dynamically based on market price
    $dynamicMinNotional = max($defaultMinNotional, $minQty * $marketPrice);

    return round($dynamicMinNotional, 2); // Round to 2 decimal places
}

// Example Usage
$symbol = "DOGEUSDT"; // Change this to any crypto pair
$minUsdt = getMinUsdtAmount($symbol);
echo "Minimum USDT required for $symbol: $minUsdt USDT\n";


?>
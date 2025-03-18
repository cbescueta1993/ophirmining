<?php

$config = include 'config.php';

$api_key = $config['api_key'];
$api_secret = $config['api_secret'];
$binance_futures_url = $config['binance_futures_url'];

// Get timestamp
$timestamp = round(microtime(true) * 1000);

// Create query string
$query = "timestamp=$timestamp";

// Generate signature
$signature = hash_hmac('sha256', $query, $api_secret);

// Final API URL
$url = "$binance_futures_url/fapi/v2/positionRisk?$query&signature=$signature";

// Set headers
$headers = ["X-MBX-APIKEY: $api_key"];

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute request
$response = curl_exec($ch);
curl_close($ch);

// Decode JSON response
$data = json_decode($response, true);

// Find total margin for all positions
$total_margin = 0;
foreach ($data as $position) {
    if ($position['positionAmt'] != "0") {
        $symbol = $position['symbol'];
        $position_amt = abs(floatval($position['positionAmt']));
        $entry_price = floatval($position['entryPrice']);
        $leverage = floatval($position['leverage']);
        
        // Calculate position margin
        if ($entry_price > 0 && $leverage > 0) {
            $position_margin = ($position_amt * $entry_price) / $leverage;
            echo "📌 $symbol Position Margin: $position_margin USDT\n";
            $total_margin += $position_margin;
        }
    }
}

// Print total margin
echo "🔹 Total Margin Across Positions: $total_margin USDT\n";
?>

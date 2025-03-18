<?php

$config = include 'config.php';

$api_key = $config['api_key'];
$api_secret = $config['api_secret'];
$binance_futures_url = $config['binance_futures_url'];//"https://fapi.binance.com"; // Binance Futures API URL

function binance_request($endpoint, $params = []) {
    global $api_key, $api_secret, $binance_futures_url;

    $timestamp = round(microtime(true) * 1000);
    $params['timestamp'] = $timestamp;
    
    $query_string = http_build_query($params);
    $signature = hash_hmac('sha256', $query_string, $api_secret);
    $url = $binance_futures_url . $endpoint . '?' . $query_string . '&signature=' . $signature;

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

// **Get Futures Wallet Balance**
$response = binance_request('/fapi/v2/account');

if (isset($response['code'])) {
    die("Error: " . $response['msg']);
}

// Extract USDT balance (or other assets if available)
foreach ($response['assets'] as $asset) {
    if ($asset['asset'] === 'USDT') {
        echo "Futures Wallet Balance (USDT): " . $asset['walletBalance'] . " USDT\n";
        echo "Available Balance: " . $asset['availableBalance'] . " USDT\n";
        break;
    }
}

?>

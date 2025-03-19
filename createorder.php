<?php
date_default_timezone_set('Asia/Manila');
ignore_user_abort(true); // Allow script to continue if client disconnects
$start_time = microtime(true); // Start time
$config = include 'config.php';
$logFile = 'log.txt';

if($config['mode']=='LIVE'){
	$binance_futures_url = $config['binance_futures_url'];
	$api_key = $config['api_key'];
	$api_secret = $config['api_secret'];
}else{
	$binance_futures_url = $config['binance_futures_url_testnet'];
	$api_key = $config['api_key_testnet'];
	$api_secret = $config['api_secret_testnet'];
}
//https://testnet.binancefuture.com instead of https://fapi.binance.com.
/*live 
apikey=u8CJD8TRZYmDOEyjKkN536kwlV1BtteaFbWkgmBEH66UpZ6dU29dDNayV0JaF0H1	
secret=rt0eMO4GfYA58F0TmUpkH5kQ1bv5whyF37Udp9fC1SJ7W0Y03NoUBzlETgWiYeiF
/*
/*testnet
Apikey:aec106be6cc44c825f23488b7521c34ef66aa91864e90b1a1f246440246cd45e
Secret:6b9bd2fd3ad5a2c5c4d57ffe8a9a66305ec5292a1f9a6330a78b393f24cd6380
*/
/*
curl -H 'Content-Type: application/json; charset=utf-8' -d '{"text": "BTCUSD Greater Than 9000"}' -X POST http://localhost:8081/ophirmining/redirect.php

*/
$symbol = $config['symbol'];//xrp doge   sol
$side= $config['side'];
$leverage = $config['leverage'];
$amount_usdt = $config['amount_usdt'];
$amount_usdt_limit_per_day = $config['amount_usdt_limit_per_day'];
$isactive = $config['isactive'];


$input=isset($_GET["input"])?$_GET["input"]:'';
//die($input);
//die($input);
$tp_percentage=$config['tp_percentage'];
$sl_percentage=$config['sl_percentage'];

$parts = explode(';', $input);

if (count($parts) > 1) {
	$alertSymbol=$parts[0];
	$alerSide=$parts[1];
	$tp_percentageTemp=$parts[2];
    $sl_percentageTemp=$parts[3];
	
	$symbol=$alertSymbol;
	if($side!='BOTH'){
		if($side!=$alerSide){
			die("not bias");
		}else{
			$side=$alerSide;
			$tp_percentage=$tp_percentageTemp;
            $sl_percentage=$sl_percentageTemp;
		}
	}else{
		$side=$alerSide;
		$tp_percentage=$tp_percentageTemp;
        $sl_percentage=$sl_percentageTemp;
	}
	
}
logTrade("ON PROCESS COIN=".$symbol.";side=".$side.";leverage=$leverage;tp@".($tp_percentage*100)."%;sl@".($sl_percentage*100)."%");

function getMarketPrice($symbol) {
    global $api_key, $binance_futures_url,$symbol;

    try {
        $url = $binance_futures_url . "/fapi/v1/ticker/price?symbol=" . urlencode($symbol);
        $headers = ["X-MBX-APIKEY: $api_key"];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout for better reliability

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $data = json_decode($response, true);

        // Validate API response
        if (!isset($data['price'])) {
            throw new Exception("Invalid API response: " . json_encode($data));
        }

        return floatval($data['price']); // Return price as float for consistency

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		die("$symbol;".$e->getMessage());
        return null; // Return null in case of failure
    }
}


function getTotalMargin(){
    global $api_key, $binance_futures_url, $api_secret,$symbol;

    try {
        $timestamp = round(microtime(true) * 1000);
        $params['timestamp'] = $timestamp;
        
        $query = http_build_query($params);
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

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $data = json_decode($response, true);

        // Check if response is valid
        if (!is_array($data)) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Find total margin for all positions
        $total_margin = 0;
        foreach ($data as $position) {
            if (isset($position['positionAmt']) && $position['positionAmt'] != "0") {
                $symbol = $position['symbol'];
                $position_amt = abs(floatval($position['positionAmt']));
                $entry_price = floatval($position['entryPrice']);
                $leverage = floatval($position['leverage']);

                // Calculate position margin
                if ($entry_price > 0 && $leverage > 0) {
                    $position_margin = ($position_amt * $entry_price) / $leverage;
                    $total_margin += $position_margin;
                }
            }
        }

        return $total_margin;

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		die("$symbol;".$e->getMessage());
        return 0; // Return 0 in case of an error
    }
}


function check_if_symbol_exists($symbol) {
    global $api_key, $binance_futures_url, $api_secret;

    try {
        $timestamp = round(microtime(true) * 1000);
        $params['timestamp'] = $timestamp;
        
        $query = http_build_query($params);
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

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $data = json_decode($response, true);

        // Validate API response
        if (!is_array($data)) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Find if the symbol exists with an open position
        foreach ($data as $position) {
            if (abs($position['positionAmt']) > 0) {
                if ($position['symbol'] === $symbol) {
                    return true;
                }
            }
        }

        return false;

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		die("$symbol;".$e->getMessage());
        return false; // Return false in case of an error
    }
}



function binance_request($endpoint, $params = [], $method = 'POST') {
    global $api_key, $api_secret, $binance_futures_url,$symbol;

    try {
        $timestamp = round(microtime(true) * 1000);
        $params['timestamp'] = $timestamp;
        
        $query = http_build_query($params);
        $signature = hash_hmac('sha256', $query, $api_secret);

        $url = $binance_futures_url . $endpoint . '?' . $query . '&signature=' . $signature;

        $headers = [
            "X-MBX-APIKEY: $api_key"
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout for better reliability

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $data = json_decode($response, true);

        // Validate API response
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg());
        }

        if (isset($data['code']) && $data['code'] != 200) {
            throw new Exception("Binance API Error: " . json_encode($data));
        }

        return $data;

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		//die("$symbol;".$e->getMessage());
        return null; // Return null in case of failure
    }
}



// Save trade success message to logs
function logTrade($message) {
    global $logFile;
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . "\n", FILE_APPEND);
}
/*
function getdecimal($symbol,$type){
	$symboldecimal;
	$symbolqty;
	$jsonString = '[
	{"coin":"DOGEUSDT","decimal":6,"qty":0},
	{"coin":"XRPUSDT","decimal":4,"qty":1},
	{"coin":"BNBUSDT","decimal":3,"qty":2}]
	';

	// Decode JSON to associative array
	$data = json_decode($jsonString, true);

	// Search for dogeusdt
	$targetCoin = $symbol;
	$found = null;

	foreach ($data as $item) {
		if ($item['coin'] === $targetCoin) {
			$found = $item;
			break;
		}
	}

	// Output result
	if ($found) {
		$symboldecimal=$found['decimal'];
		$symbolqty=$found['qty'];
	} else {
		$symboldecimal=1;
		$symbolqty=0;
	}
	return ($type=='decimal')?$symboldecimal:$symbolqty;
}*/


function getSymbolPrecision($symbol) {
    global $api_key, $binance_futures_url;

    try {
        $headers = ["X-MBX-APIKEY: $api_key"];
        $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $exchange_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $exchangeData = json_decode($response, true);

        // Validate API response
        if (!isset($exchangeData['symbols']) || !is_array($exchangeData['symbols'])) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Default precision values
        $precision = [
            'price' => 2,  // Default price precision
            'qty' => 2,    // Default quantity precision
            'minQty' => 0.01 // Default minimum quantity
        ];

        // Find the symbol and extract precision details
        foreach ($exchangeData['symbols'] as $s) {
            if (isset($s['symbol']) && $s['symbol'] === $symbol) {
                foreach ($s['filters'] as $filter) {
                    if ($filter['filterType'] === 'PRICE_FILTER') {
                        $precision['price'] = abs(log10(floatval($filter['tickSize'])));
                    }
                    if ($filter['filterType'] === 'LOT_SIZE') {
                        $precision['qty'] = abs(log10(floatval($filter['stepSize'])));
                        $precision['minQty'] = floatval($filter['minQty']);
                    }
                }
                return $precision;
            }
        }

        // If symbol is not found, throw an error
        throw new Exception("Symbol $symbol not found in exchange info");

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		die("$symbol;".$e->getMessage());
        return [
            'price' => 2,
            'qty' => 2,
            'minQty' => 0.01
        ]; // Return default values in case of an error
    }
}


function getDynamicMinNotional($symbol) {
    global $api_key, $binance_futures_url;

    try {
        $headers = ["X-MBX-APIKEY: $api_key"];
        $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $exchange_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $exchangeData = json_decode($response, true);

        // Validate API response
        if (!isset($exchangeData['symbols']) || !is_array($exchangeData['symbols'])) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Default minimum notional value
        $minNotional = 5.0;

        // Find the symbol and extract MIN_NOTIONAL filter
        foreach ($exchangeData['symbols'] as $s) {
            if (isset($s['symbol']) && $s['symbol'] === $symbol) {
                foreach ($s['filters'] as $filter) {
                    if ($filter['filterType'] === 'MIN_NOTIONAL') {
                        $minNotional = floatval($filter['notional']);
                        return $minNotional;
                    }
                }
                break;
            }
        }

        // If symbol is not found, throw an error
        throw new Exception("Symbol $symbol not found in exchange info");

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		die("$symbol;".$e->getMessage());
        return 5.0; // Return default value in case of an error
    }
}


function getPercentPriceFilter($symbol) {
    global $api_key, $binance_futures_url;

    try {
        $headers = ["X-MBX-APIKEY: $api_key"];
        $exchange_url = $binance_futures_url . "/fapi/v1/exchangeInfo";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $exchange_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        // Decode JSON response
        $exchangeData = json_decode($response, true);

        // Validate API response
        if (!isset($exchangeData['symbols']) || !is_array($exchangeData['symbols'])) {
            throw new Exception('Invalid API response: ' . $response);
        }

        // Search for the symbol and extract the PERCENT_PRICE filter
        foreach ($exchangeData['symbols'] as $s) {
            if (isset($s['symbol']) && $s['symbol'] === $symbol) {
                foreach ($s['filters'] as $filter) {
                    if ($filter['filterType'] === 'PERCENT_PRICE') {
                        return [
                            'minPrice' => isset($filter['minPrice']) ? floatval($filter['minPrice']) : 0,
                            'maxPrice' => isset($filter['maxPrice']) ? floatval($filter['maxPrice']) : PHP_FLOAT_MAX
                        ];
                    }
                }
                break;
            }
        }

        // If symbol not found, throw an error
        throw new Exception("Symbol $symbol not found in exchange info");

    } catch (Exception $e) {
        logTrade("$symbol;".$e->getMessage());
		die("$symbol;".$e->getMessage());
        return [
            'minPrice' => 0,
            'maxPrice' => PHP_FLOAT_MAX // Safe defaults
        ];
    }
}




//START HERE ===================================================================<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<< 

if($isactive==0){
	die("inactive!");
}
/*
$TotalMargin = getTotalMargin();
if($TotalMargin<=$amount_usdt_limit_per_day){
	
}else{
	logTrade("$symbol limit per day reach!");
	die("limit per day reach!");
}
*/
/*
if(check_if_symbol_exists($symbol)){
	logTrade("$symbol already exists!");
	die("already exists!");
}*/


// **Step 1: Get Current Market Price**
//$ticker_response = binance_request('/fapi/v1/ticker/price', ['symbol' => $symbol], 'GET');
//if (!isset($ticker_response['price'])) {
//    die("Error fetching market price.");
//}
// Get the latest XRPUSDT market price
$entryPrice = getMarketPrice($symbol);

if (!$entryPrice) {
	logTrade("$symbol already exists!");
    die("Error fetching market price.");
}
//***************************************end calculate trade param

// Fetch precision, minQty, and minNotional
$precision = getSymbolPrecision($symbol);
$precisionDecimal = $precision['price']; // Price precision
$precisionQty = $precision['qty']; // Quantity precision
$minQty = $precision['minQty']; // Minimum order quantity
$minNotional = getDynamicMinNotional($symbol); // Get minimum order value in USDT

// Fetch PERCENT_PRICE filter
$percentPriceFilter = getPercentPriceFilter($symbol); // Implement this function
$minAllowedPrice = $percentPriceFilter['minPrice'];
$maxAllowedPrice = $percentPriceFilter['maxPrice'];

// Calculate TP and SL
$takeProfit = $side === "BUY" ? $entryPrice * (1 + $tp_percentage) : $entryPrice * (1 - $tp_percentage);
$stopLoss = $side === "BUY" ? $entryPrice * (1 - $sl_percentage) : $entryPrice * (1 + $sl_percentage);

// **Ensure TP and SL are within Binance’s allowed price range**
$takeProfit = max($minAllowedPrice, min($takeProfit, $maxAllowedPrice));
$stopLoss = max($minAllowedPrice, min($stopLoss, $maxAllowedPrice));

// Round TP and SL to the correct precision
$takeProfit = round($takeProfit, $precisionDecimal);
$stopLoss = round($stopLoss, $precisionDecimal);

// Compute quantity based on leverage
$quantity = round((($amount_usdt * $leverage) / $entryPrice), $precisionQty);

// Ensure quantity meets minimum requirements
if ($quantity < $minQty) {
    $quantity = $minQty;
}

// Ensure order meets minNotional (quantity * price >= minNotional)
$notional = $quantity * $entryPrice;
if ($notional < $minNotional) {
    $quantity = round(($minNotional / $entryPrice), $precisionQty); // Adjust quantity to meet minNotional
}

// Format numbers correctly
$entryPrice = number_format($entryPrice, $precisionDecimal, '.', '');
$quantity = number_format($quantity, $precisionQty, '.', '');
$takeProfit = number_format($takeProfit, $precisionDecimal, '.', '');
$stopLoss = number_format($stopLoss, $precisionDecimal, '.', '');


echo "entryPrice:".$entryPrice;
echo "\n";
echo "takeProfit:".$takeProfit;
echo "\n";
echo "stopLoss:".$stopLoss;
echo "\n";
echo "Quantity:".$quantity;
echo "\n";
echo "precisionDecimal:".$precisionDecimal;
echo "\n";
echo "precisionQty:".$precisionQty;
echo "\n";
echo "minQty:".$minQty;
echo "\n";
//***********************************end calculate trade param

/*
// **Step 1: Set Margin Mode to ISOLATED**
$margin_mode_response = binance_request('/fapi/v1/marginType', [
    'symbol' => $symbol,
    'marginType' => 'ISOLATED'
]);

// Ignore error if margin type is already set
if (isset($margin_mode_response['code']) && $margin_mode_response['code'] != -4046) { 
    //die("Margin Mode Error: " . $margin_mode_response['msg']);
	echo "already set margin mode to isolated";
	echo "\n";
}
    */
$timestamp = round(microtime(true) * 1000);
$callbackRate=3;

// **Step 2: Set Leverage**
$leverage_response = binance_request('/fapi/v1/leverage', [
    'symbol' => $symbol,
    'leverage' => $leverage,
    'timestamp' => $timestamp,
    'recvWindow' => 10000 
]);



if (isset($leverage_response['code'])) {
    echo "Leverage Error: " . $leverage_response['msg'];
	echo "\n";
}



// **Step 3: Open Market Long Position**

$order_response = binance_request('/fapi/v1/order', [
    'symbol' => $symbol,
    'side' => $side,
    'type' => 'MARKET',
    'quantity' => $quantity,
    'timestamp' => $timestamp,
    'recvWindow' => 10000 // Increase recvWindow to allow slight delay
]);



$order_response = binance_request('/fapi/v1/order', [
    'symbol' => $symbol,
    'side' => $side,
    'type' => 'TRAILING_STOP_MARKET',
    'quantity' => $quantity,
    "activationPrice" => ($side=="BUY")?$stopLoss:$takeProfit,
    "callbackRate" => $callbackRate, // 3% trailing stop loss
    'timestamp' => $timestamp,
    "workingType" => "CONTRACT_PRICE",
    "priceProtect" => "FALSE",
    'recvWindow' => 10000 // Increase recvWindow to allow slight delay
    //,"reduceOnly" => "true"
]);



/*$order_response = binance_request('/fapi/v1/order', [
    'symbol' => $symbol,
    'side' => $side,
    'type' => 'LIMIT',
    'quantity' => $quantity,
    'price' => $entryPrice, // Adjusted price
    'timeInForce' => 'GTC'
]);*/

//echo "Entry Price: $entryPrice\n";
if (isset($order_response['code'])) {
    die("Order Error: " . $order_response['msg']);
}

if (!isset($order_response['orderId'])) {
	die("Order Error: " . $order_response['msg']);
}

$order_id = $order_response['orderId'];

$tp_sl_side = ($side === 'BUY') ? 'SELL' : 'BUY';

// **Step 4: Set Take Profit (TP)**
/*
$tp_response = binance_request('/fapi/v1/order', [
	'orderId' => $order_id,
    'symbol' => $symbol,
    'side' => $tp_sl_side,
    'type' => 'TAKE_PROFIT_MARKET',
    'quantity' => $quantity,
    'stopPrice' => $takeProfit,
    'closePosition' => 'true'
]);
if (isset($tp_response['code'])) {
    die("Take Profit Error: " . $tp_response['msg']);
}
*/
/*
$tp_response = binance_request('/fapi/v1/order', [
	'orderId' => $order_id,
    'symbol' => $symbol,
    'side' => $tp_sl_side,
    'type' => 'LIMIT',  // Use LIMIT instead
    'quantity' => $quantity,
    'price' => $takeProfit,  // Set price instead of stopPrice
    'timeInForce' => 'GTC'  // Keep the order open
]);*/


//echo "Take Profit: $takeProfit\n";


// **Step 5: Set Stop Loss (SL)**
/*
$sl_response = binance_request('/fapi/v1/order', [
	'orderId' => $order_id,
    'symbol' => $symbol,
    'side' => $tp_sl_side,
    'type' => 'STOP_MARKET',
    'quantity' => $quantity,
    'stopPrice' => $stopLoss,
    'closePosition' => 'true'
]);
if (isset($sl_response['code'])) {
    die("Stop Loss Error: " . $sl_response['msg']);
}

/*
/*
$sl_response = binance_request('/fapi/v1/order', [
	'orderId' => $order_id,
    'symbol' => $symbol,
    'side' => $tp_sl_side,
    'type' => 'LIMIT',  // Change from STOP_MARKET to STOP_LIMIT
    'quantity' => $quantity,  // Stop price triggers the limit order
    'price' => $stopLoss,  // Set actual limit price slightly below stop price
    'timeInForce' => 'GTC'  // Keep order open until filled
]);*/

//echo "Stop Loss: $stopLoss\n";

$msg= "[DONE];COIN=".$symbol.";side=".$side.";entry=$entryPrice;tp=$takeProfit;sl=$stopLoss;leverage=$leverage;tp@".($tp_percentage*100)."%;sl@".($sl_percentage*100)."%";
echo $msg."\n";
$end_time = microtime(true); // End time
$execution_time = $end_time - $start_time; // Calculate execution time

echo "Execution Time: " . number_format($execution_time, 4) . " seconds";
logTrade($msg);

if (isset($_GET['logs'])) {
    echo file_exists($logFile) ? file_get_contents($logFile) : "No logs yet.";
    exit;
}

?>
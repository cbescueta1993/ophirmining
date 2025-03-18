<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$configFile = 'config.php';
$logFile = 'log.txt';

$config = include $configFile;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$mode = $_POST['mode'];
    $side = $_POST['side'];
    $symbol = $_POST['symbol'];
    $leverage = (int) $_POST['leverage'];
    $amount = (float) $_POST['amount'];
    $tp_percentage=$_POST['tp_percentage'];
	$sl_percentage=$_POST['sl_percentage'];
	$binance_futures_url=$config['binance_futures_url'];
	$api_key=$config['api_key'];
	$api_secret=$config['api_secret'];
	$binance_futures_url_testnet=$config['binance_futures_url_testnet'];
	$api_key_testnet=$config['api_key_testnet'];
	$api_secret_testnet=$config['api_secret_testnet'];
	$amount_usdt_limit_per_day=(float) $_POST['amount_usdt_limit_per_day'];
	$isactive = (int) $_POST['isactive'];
	
    $configData = "<?php\nreturn [\n    'mode' => '$mode',\n    'side' => '$side',\n    'symbol' => '$symbol',\n    'leverage' => $leverage,\n    'amount_usdt' => $amount,\n    'tp_percentage' => $tp_percentage,\n    'sl_percentage' => $sl_percentage,\n    'binance_futures_url' => '$binance_futures_url',\n    'api_key' => '$api_key',\n    'api_secret' => '$api_secret',\n    'binance_futures_url_testnet' => '$binance_futures_url_testnet',\n    'api_key_testnet' => '$api_key_testnet',\n    'api_secret_testnet' => '$api_secret_testnet',\n    'symbol' => '$symbol',\n    'amount_usdt_limit_per_day' => $amount_usdt_limit_per_day,\n    'isactive' => $isactive,\n];";
    
    file_put_contents($configFile, $configData);
    echo json_encode(['success' => true, 'message' => "Configuration updated successfully!"]);
    exit;
}

if (isset($_GET['logs'])) {
    echo file_exists($logFile) ? file_get_contents($logFile) : "No logs yet.";
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="icon" type="image/png" href="ophirmining.png" >
    <style>
        .message { 
            color: green; 
            opacity: 1;
            transition: opacity 1s ease-in-out;
        }
        .hidden { opacity: 0; }
        textarea { width: 100%; height: 200px; }
		.logo-container {
		  display: flex;
		  align-items: center;
		  justify-content: center;
		  padding: 10px;
		  background-color: black;
		  width: 100%;
		}

		/* Mobile screens */
		@media (max-width: 768px) {
		  .logo-container {
			justify-content: flex-start;
			padding: 10px 15px;
		  }
		}

		/* Larger screens */
		@media (min-width: 1024px) {
		  .logo-container {
			padding: 20px;
		  }
		}
    </style>
    <script>
        function updateConfig(event) {
            event.preventDefault();
            let formData = new FormData(document.getElementById('configForm'));
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
				showSuccessModal();
                let messageElem = document.getElementById('message');
                messageElem.innerText = data.message;
                messageElem.classList.remove('hidden'); // Show message
                
                // Hide message after 5 seconds
                setTimeout(() => {
                    messageElem.classList.add('hidden');
                }, 5000);
            });
        }

        function refreshLogs() {
            fetch('?logs=1')
            .then(response => response.text())
            .then(data => {
                document.getElementById('logs').value = data;
            });
        }
		
		function showLogoutModal() {
            var myModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            myModal.show();
        }

		function showSuccessModal() {
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            let countdown = 3;
            document.getElementById('countdown').innerText = countdown;
            let interval = setInterval(() => {
                countdown--;
                document.getElementById('countdown').innerText = countdown;
                if (countdown <= 0) {
                    clearInterval(interval);
                    successModal.hide();
                }
            }, 1000);
        }
		
        setInterval(refreshLogs, 5000);
    </script>
</head>
<body>
	<div class="logo-container">
    <img src="ophirmining.png" alt="Ophir Mining Logo" width="100" height="100">
	</div>

	<div class="container mt-5">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?>!</h2>
        <button onclick="showLogoutModal()" class="btn btn-danger">Logout</button>
    </div>
    <div class="container mt-4">
        <h4>Trading Config</h4>
        <p id="message" class="message hidden"></p>
        <form id="configForm" onsubmit="updateConfig(event)">
			<div class="mb-3">
                <label class="form-label">MODE:</label>
                <select name="mode" class="form-select">
                    <option value="LIVE" <?php echo ($config['mode'] === 'LIVE') ? 'selected' : ''; ?>>LIVE</option>
                    <option value="TESTNET" <?php echo ($config['mode'] === 'TESTNET') ? 'selected' : ''; ?>>TESTNET</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Side:</label>
                <select name="side" class="form-select">
                    <option value="BUY" <?php echo ($config['side'] === 'BUY') ? 'selected' : ''; ?>>BUY</option>
                    <option value="SELL" <?php echo ($config['side'] === 'SELL') ? 'selected' : ''; ?>>SELL</option>
					<option value="BOTH" <?php echo ($config['side'] === 'BOTH') ? 'selected' : ''; ?>>BOTH</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Symbol:</label>
                <input type="text" name="symbol" class="form-control" value="<?php echo $config['symbol']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Leverage:</label>
                <input type="number" name="leverage" class="form-control" value="<?php echo $config['leverage']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Amount (USDT):</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo $config['amount_usdt']; ?>" required>
            </div>
			<div class="mb-3">
                <label class="form-label">TP:</label>
                <input type="number" step="any" name="tp_percentage" class="form-control" value="<?php echo $config['tp_percentage']; ?>" required>
            </div>
			<div class="mb-3">
                <label class="form-label">SL:</label>
                <input type="number" step="any" name="sl_percentage" class="form-control" value="<?php echo $config['sl_percentage']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Amount limit per day (USDT):</label>
                <input type="number" step="0.01" name="amount_usdt_limit_per_day" class="form-control" value="<?php echo $config['amount_usdt_limit_per_day']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Is Active:</label>
                <input type="number" step="1" name="isactive" class="form-control" value="<?php echo $config['isactive']; ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Apply Changes</button>
        </form>
        <h4 class="mt-4">Trade Logs</h4>
        <textarea id="logs" class="form-control" readonly></textarea>
    </div>
	
	<!-- Bootstrap Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="logout.php" class="btn btn-danger">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>
	
	<!-- Bootstrap Success Modal with Countdown -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    Action completed successfully!<br>
                    Closing in <span id="countdown">5</span> seconds...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

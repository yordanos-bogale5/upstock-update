<?php
// Access token for Upstox API
$accessToken = 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI1NkI0QkgiLCJqdGkiOiI2ODZiNTQ0ZmZkNDllNDQ3OTgxYjQwOTAiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1MTg2NDM5OSwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzUxOTI1NjAwfQ.zVcdRyKg11m-6-gPHT04GijyyuiOMcAR3e_yApvVMBM';

// API Configuration
define('API_HOST', 'https://api.upstox.com/v2');
define('API_VERSION', '2.0');

// Function to log errors
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, __DIR__ . '/error.log');
}

// Initialize data array with defaults
$marketData = [
    'nifty50' => [
        'price' => '--',
        'change' => '--',
        'change_percent' => '--',
        'timestamp' => '--'
    ],
    'niftybank' => [
        'price' => '--',
        'change' => '--',
        'change_percent' => '--',
        'timestamp' => '--'
    ]
];

// Skip initial data fetching to speed up page load
// Client-side JavaScript will fetch the data immediately after page load
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Market Prices - Upstox</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .market-card {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .market-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .index-name {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .price {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .change {
            padding: 3px 8px;
            border-radius: 3px;
            display: inline-block;
            font-weight: bold;
        }
        .positive {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .negative {
            background-color: #ffebee;
            color: #d32f2f;
        }
        .neutral {
            background-color: #f5f5f5;
            color: #757575;
        }
        .timestamp {
            font-size: 0.9rem;
            color: #757575;
            margin-top: 10px;
        }
        .loading {
            text-align: center;
            color: #757575;
            margin: 20px 0;
            font-style: italic;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.9rem;
            color: #757575;
        }
        #status {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .connected { background: #28a745; color: white; }
        .disconnected { background: #dc3545; color: white; }
        #debug {
            position: fixed;
            bottom: 10px;
            left: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Live Market Prices</h1>
        
        <div id="status" class="disconnected">Disconnected</div>
        
        <!-- Nifty 50 Card -->
        <div class="market-card" id="nifty50-card">
            <div class="index-name">Nifty 50</div>
            <div class="price" id="nifty50-price"><?php echo $marketData['nifty50']['price']; ?></div>
            <div class="change neutral" id="nifty50-change">
                <?php echo $marketData['nifty50']['change']; ?> (<?php echo $marketData['nifty50']['change_percent']; ?>%)
            </div>
            <div class="timestamp" id="nifty50-timestamp">Last updated: <?php echo $marketData['nifty50']['timestamp']; ?></div>
        </div>
        
        <!-- Nifty Bank Card -->
        <div class="market-card" id="niftybank-card">
            <div class="index-name">Nifty Bank</div>
            <div class="price" id="niftybank-price"><?php echo $marketData['niftybank']['price']; ?></div>
            <div class="change neutral" id="niftybank-change">
                <?php echo $marketData['niftybank']['change']; ?> (<?php echo $marketData['niftybank']['change_percent']; ?>%)
            </div>
            <div class="timestamp" id="niftybank-timestamp">Last updated: <?php echo $marketData['niftybank']['timestamp']; ?></div>
        </div>
        
        <div class="loading" id="connection-status">Loading market data...</div>
        
        <div class="footer">
            <p>Data provided by Upstox API</p>
        </div>
    </div>

    <div id="debug"></div>

    <script>
        let debugLog = [];
        const maxDebugLogs = 50;

        function logDebug(message) {
            const timestamp = new Date().toLocaleTimeString();
            debugLog.unshift(`${timestamp} - ${message}`);
            if (debugLog.length > maxDebugLogs) {
                debugLog.pop();
            }
            document.getElementById('debug').innerHTML = debugLog.join('<br>');
        }

        // Format a number to 2 decimal places
        function formatNumber(num) {
            return parseFloat(num).toFixed(2);
        }
        
        // Update the UI with new price data
        function updateMarketData(data) {
            if (data.error) {
                document.getElementById('connection-status').innerText = 'Error: ' + data.error;
                document.getElementById('connection-status').style.display = 'block';
                document.getElementById('status').className = 'disconnected';
                document.getElementById('status').textContent = 'Disconnected';
                return;
            }
            
            document.getElementById('connection-status').style.display = 'none';
            document.getElementById('status').className = 'connected';
            document.getElementById('status').textContent = 'Connected';
            
            try {
                // Update Nifty 50
                if (data.nifty50) {
                    document.getElementById('nifty50-price').textContent = data.nifty50.price;
                    
                    const changeElement = document.getElementById('nifty50-change');
                    changeElement.textContent = `${data.nifty50.change} (${data.nifty50.change_percent}%)`;
                    
                    // Remove existing classes
                    changeElement.classList.remove('positive', 'negative', 'neutral');
                    
                    // Add appropriate class based on change direction
                    if (parseFloat(data.nifty50.change) > 0) {
                        changeElement.classList.add('positive');
                    } else if (parseFloat(data.nifty50.change) < 0) {
                        changeElement.classList.add('negative');
                    } else {
                        changeElement.classList.add('neutral');
                    }
                    
                    document.getElementById('nifty50-timestamp').textContent = `Last updated: ${data.nifty50.timestamp}`;
                    logDebug('Updated Nifty 50: ' + data.nifty50.price);
                }
                
                // Update Nifty Bank
                if (data.niftybank) {
                    document.getElementById('niftybank-price').textContent = data.niftybank.price;
                    
                    const changeElement = document.getElementById('niftybank-change');
                    changeElement.textContent = `${data.niftybank.change} (${data.niftybank.change_percent}%)`;
                    
                    // Remove existing classes
                    changeElement.classList.remove('positive', 'negative', 'neutral');
                    
                    // Add appropriate class based on change direction
                    if (parseFloat(data.niftybank.change) > 0) {
                        changeElement.classList.add('positive');
                    } else if (parseFloat(data.niftybank.change) < 0) {
                        changeElement.classList.add('negative');
                    } else {
                        changeElement.classList.add('neutral');
                    }
                    
                    document.getElementById('niftybank-timestamp').textContent = `Last updated: ${data.niftybank.timestamp}`;
                    logDebug('Updated Nifty Bank: ' + data.niftybank.price);
                }
            } catch (error) {
                logDebug('Error updating market data: ' + error.message);
            }
        }

        // Use a lighter approach for data fetching with AJAX instead of SSE
        function fetchMarketData() {
            logDebug('Fetching market data...');
            
            fetch('market_data_light.php')
                .then(response => response.json())
                .then(data => {
                    updateMarketData(data);
                    // Schedule next update
                    setTimeout(fetchMarketData, 2000);
                })
                .catch(error => {
                    logDebug('Error fetching data: ' + error.message);
                    document.getElementById('connection-status').innerText = 'Connection error. Retrying...';
                    document.getElementById('connection-status').style.display = 'block';
                    document.getElementById('status').className = 'disconnected';
                    document.getElementById('status').textContent = 'Disconnected';
                    // Retry after delay
                    setTimeout(fetchMarketData, 3000);
                });
        }

        // Toggle debug panel with Ctrl+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                const debugPanel = document.getElementById('debug');
                debugPanel.style.display = debugPanel.style.display === 'none' ? 'block' : 'none';
            }
        });

        // Start fetching data when page loads
        document.addEventListener('DOMContentLoaded', fetchMarketData);
    </script>
</body>
</html>

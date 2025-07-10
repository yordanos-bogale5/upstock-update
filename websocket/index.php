<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Market Prices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .price-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
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
        .error-message {
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            background: #f8d7da;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center mb-4">Live Market Prices</h1>
        
        <div id="status" class="disconnected">Disconnected</div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="price-card">
                    <h3>Nifty 50</h3>
                    <div id="nifty50-price">Loading...</div>
                    <div id="nifty50-change">-</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="price-card">
                    <h3>Nifty Bank</h3>
                    <div id="niftybank-price">Loading...</div>
                    <div id="niftybank-change">-</div>
                </div>
            </div>
        </div>

        <div id="error-message" class="error-message"></div>
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

        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            logDebug('Error: ' + message);
        }

        function connectSSE() {
            logDebug('Attempting to connect to SSE...');
            const evtSource = new EventSource('live_prices.php');
            
            evtSource.onopen = function() {
                document.getElementById('status').className = 'connected';
                document.getElementById('status').textContent = 'Connected';
                document.getElementById('error-message').style.display = 'none';
                logDebug('SSE connection established');
            };
            
            evtSource.onerror = function(error) {
                document.getElementById('status').className = 'disconnected';
                document.getElementById('status').textContent = 'Disconnected';
                logDebug('SSE connection error: ' + JSON.stringify(error));
                evtSource.close();
                setTimeout(connectSSE, 5000); // Reconnect after 5 seconds
            };
            
            evtSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    logDebug('Received data: ' + JSON.stringify(data));
                    
                    if (data.error) {
                        showError(data.error);
                        return;
                    }
                    
                    updatePrices(data);
                } catch (e) {
                    logDebug('Error parsing message: ' + e.message);
                    showError('Error processing data: ' + e.message);
                }
            };
        }

        function updatePrices(data) {
            if (data.error) {
                showError(data.error);
                return;
            }

            const errorDiv = document.getElementById('error-message');
            errorDiv.style.display = 'none';

            try {
                // Update Nifty 50
                if (data.nifty50 && data.nifty50.status === 'success' && data.nifty50.data) {
                    const nifty50Instrument = Object.values(data.nifty50.data)[0]; // Get first key's value
                    if (nifty50Instrument) {
                        const price = nifty50Instrument.ltp;
                        const prevClose = nifty50Instrument.close;
                        const change = price - prevClose;
                        const changePercent = (change / prevClose) * 100;

                        document.getElementById('nifty50-price').textContent = price.toFixed(2);
                        const nifty50ChangeElement = document.getElementById('nifty50-change');
                        nifty50ChangeElement.textContent = `${change.toFixed(2)} (${changePercent.toFixed(2)}%)`;
                        nifty50ChangeElement.className = 'change ' + (change >= 0 ? 'positive' : 'negative');
                        
                        logDebug('Updated Nifty 50: ' + price);
                    }
                }

                // Update Nifty Bank
                if (data.niftybank && data.niftybank.status === 'success' && data.niftybank.data) {
                    const niftybankInstrument = Object.values(data.niftybank.data)[0]; // Get first key's value
                    if (niftybankInstrument) {
                        const price = niftybankInstrument.ltp;
                        const prevClose = niftybankInstrument.close;
                        const change = price - prevClose;
                        const changePercent = (change / prevClose) * 100;

                        document.getElementById('niftybank-price').textContent = price.toFixed(2);
                        const niftybankChangeElement = document.getElementById('niftybank-change');
                        niftybankChangeElement.textContent = `${change.toFixed(2)} (${changePercent.toFixed(2)}%)`;
                        niftybankChangeElement.className = 'change ' + (change >= 0 ? 'positive' : 'negative');
                        
                        logDebug('Updated Nifty Bank: ' + price);
                    }
                }
            } catch (error) {
                showError('Error processing data: ' + error.message);
                logDebug('Data processing error: ' + error.message);
                logDebug('Raw data: ' + JSON.stringify(data));
            }
        }

        // Toggle debug panel with Ctrl+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'd') {
                const debugPanel = document.getElementById('debug');
                debugPanel.style.display = debugPanel.style.display === 'none' ? 'block' : 'none';
            }
        });

        // Connect when page loads
        connectSSE();
    </script>
</body>
</html>

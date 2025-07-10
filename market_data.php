<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Access token for Upstox API
$accessToken = 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI1NkI0QkgiLCJqdGkiOiI2ODZiNTQ0ZmZkNDllNDQ3OTgxYjQwOTAiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1MTg2NDM5OSwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzUxOTI1NjAwfQ.zVcdRyKg11m-6-gPHT04GijyyuiOMcAR3e_yApvVMBM';

// API Configuration
define('API_HOST', 'https://api.upstox.com/v2');
define('API_VERSION', '2.0');

// Function to log errors
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, __DIR__ . '/error.log');
}

// Function to get live prices from API
function getLivePrices($accessToken) {
    $ch = curl_init();
    
    // Set headers
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $accessToken,
        'Api-Version: ' . API_VERSION
    ];

    $symbols = ['NSE_INDEX|Nifty 50', 'NSE_INDEX|Nifty Bank'];
    $data = [
        'nifty50' => null,
        'niftybank' => null
    ];

    foreach ($symbols as $symbol) {
        $url = API_HOST . "/market-quote/ltp?symbol=" . urlencode($symbol);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        // Handle cURL errors
        if ($curl_error) {
            logError("$symbol CURL Error: $curl_error");
            return false;
        }
        
        // Handle HTTP errors
        if ($http_code !== 200) {
            logError("$symbol HTTP Error: $http_code Response: $response");
            return false;
        }
        
        // Decode response
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("$symbol JSON Error: " . json_last_error_msg());
            return false;
        }

        // Process the data
        if (isset($decoded['data'][$symbol])) {
            $index = ($symbol === 'NSE_INDEX|Nifty 50') ? 'nifty50' : 'niftybank';
            $quote = $decoded['data'][$symbol];
            $ltp = $quote['last_price'];
            $close = $quote['close_price'];
            $change = $ltp - $close;
            $change_percent = ($close > 0) ? ($change / $close) * 100 : 0;
            
            $data[$index] = [
                'price' => number_format($ltp, 2),
                'change' => number_format($change, 2),
                'change_percent' => number_format($change_percent, 2),
                'timestamp' => date('H:i:s')
            ];
        } else {
            logError("$symbol: Data not found in response: " . print_r($decoded, true));
        }
    }
    
    curl_close($ch);
    
    // Make sure we have data for both indices
    if ($data['nifty50'] === null || $data['niftybank'] === null) {
        logError("Incomplete data retrieved");
        return false;
    }
    
    return $data;
}

// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable timeouts
set_time_limit(0);
ignore_user_abort(true);

// Clear any existing output buffer
if (ob_get_level()) ob_end_clean();

// Send an initial comment to establish the connection
echo "retry: 3000\n";
echo ": " . str_repeat(" ", 2048) . "\n\n"; // Padding for IE
flush();

// Continuous loop to send updates
while (true) {
    try {
        $prices = getLivePrices($accessToken);
        
        if ($prices === false) {
            // Send error message to client
            echo "data: " . json_encode(['error' => 'Failed to fetch prices']) . "\n\n";
        } else {
            // Send the data
            echo "data: " . json_encode($prices) . "\n\n";
        }
        
        // Flush the output buffer to send to client immediately
        ob_flush();
        flush();
        
        // Wait before next update
        sleep(3); // Update every 3 seconds
    } catch (Exception $e) {
        logError("Exception: " . $e->getMessage());
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        ob_flush();
        flush();
        sleep(5); // Wait longer on error
    }
}

<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Error handler for API
function handleError($message) {
    echo json_encode([
        'error' => true,
        'message' => $message
    ]);
    exit;
}

// This script acts as a bridge between live_prices.php and the frontend
// It returns the latest prices for Nifty 50 and Nifty Bank indexes

// Create a file to store the latest prices if it doesn't exist
$cacheFile = __DIR__ . '/market_data_cache.json';

// Check if php is already running live_prices.php
$isLivePricesRunning = false;
$output = [];
exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV', $output);

foreach ($output as $line) {
    if (strpos($line, 'live_prices.php') !== false) {
        $isLivePricesRunning = true;
        break;
    }
}

// If live_prices.php is not running, start it in the background
if (!$isLivePricesRunning) {
    // Start the process in the background with no window
    pclose(popen('start /B php live_prices.php > NUL', 'r'));
    
    // Initialize the cache file if it doesn't exist
    if (!file_exists($cacheFile)) {
        $initialData = [
            'nifty50' => [
                'price' => '--',
                'change' => '--',
                'change_percent' => '--',
                'timestamp' => date('H:i:s')
            ],
            'niftybank' => [
                'price' => '--',
                'change' => '--',
                'change_percent' => '--',
                'timestamp' => date('H:i:s')
            ],
            'last_updated' => time()
        ];
        if (!file_put_contents($cacheFile, json_encode($initialData))) {
            handleError('Failed to create cache file. Check permissions.');
        }
    }
}

// Check if market_data_cache.json exists
if (!file_exists($cacheFile)) {
    handleError('Cache file not found: ' . $cacheFile);
}

// Get data from the cache file
try {
    $jsonData = file_get_contents($cacheFile);
    
    if ($jsonData === false) {
        handleError('Failed to read cache file. Check permissions.');
    }
    
    $data = json_decode($jsonData, true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        handleError('Invalid JSON in cache file: ' . json_last_error_msg());
    }
    
    // Check if the data is stale (older than 10 seconds)
    $timestamp = isset($data['last_updated']) ? $data['last_updated'] : 0;
    if (time() - $timestamp > 10) {
        $data['status'] = 'stale';
    } else {
        $data['status'] = 'fresh';
    }
    
    echo json_encode($data);
} catch (Exception $e) {
    handleError('Exception: ' . $e->getMessage());
}
?>

<?php
// Suppress deprecation warnings
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use function Amp\Websocket\Client\connect;
use Upstox\Client\Configuration;
use Upstox\Client\Api\WebsocketApi;
use Com\Upstox\Marketdatafeeder\Rpc\Proto\FeedResponse;
use Amp\Websocket\Client\WebsocketConnection;
use Amp\Socket\ConnectContext;
use Amp\Socket\ClientTlsContext;

// Disable SSL verification globally for this script
putenv('GUZZLE_SSL_VERIFY=false');
putenv('SSL_CERT_FILE=');

// File to store market data cache
$cacheFile = __DIR__ . '/market_data_cache.json';

/**
 * Function to decode Protobuf messages.
 */
function decodeProtobuf($buffer)
{
    $feedResponse = new FeedResponse();
    if ($buffer !== null && $buffer !== '') {
        try {
            $feedResponse->mergeFromString($buffer);
        } catch (\Exception $e) {
            echo "Error decoding Protobuf message: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    return $feedResponse;
}

/**
 * Function to save market data to cache file.
 */
function saveMarketData($nifty50Data, $niftyBankData)
{
    global $cacheFile;
    $data = [
        'nifty50' => $nifty50Data,
        'niftybank' => $niftyBankData,
        'last_updated' => time()
    ];
    file_put_contents($cacheFile, json_encode($data));
}

/**
 * Function to get market data feed authorization.
 */
function getMarketDataFeedAuthorize($apiVersion, $configuration)
{
    // Create a Guzzle client with SSL verification disabled
    $client = new Client([
        'verify' => false,
        'curl' => [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CAINFO => null,
            CURLOPT_CAPATH => null
        ]
    ]);
    $apiInstance = new WebsocketApi(
        $client,
        $configuration
    );
    try {
        echo "Attempting to get market data feed authorization...\n";
        $response = $apiInstance->getMarketDataFeedAuthorize($apiVersion);
        echo "Authorization response: " . print_r($response, true) . "\n";
        return $response;
    } catch (\Exception $e) {
        echo "Authorization failed: " . $e->getMessage() . "\n";
        if ($e->getPrevious()) {
            echo "Previous error: " . $e->getPrevious()->getMessage() . "\n";
        }
        throw $e; // Re-throw to be caught by the main try/catch
    }
}

/**
 * Main function to fetch live market updates.
 */
function fetchLivePrices($accessToken)
{
    $apiVersion = '2.0';
    // Configure with your access token
    $configuration = Configuration::getDefaultConfiguration();
    $configuration->setAccessToken($accessToken);

    // Initialize market data
    $marketData = [
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
        ]
    ];

    // Save initial data
    saveMarketData($marketData['nifty50'], $marketData['niftybank']);

    try {
        echo "Starting Upstox WebSocket connection...\n";

        // Get the authorized URL for the WebSocket connection
        $response = getMarketDataFeedAuthorize($apiVersion, $configuration);
        echo "Authorization successful!\n";
        echo "WebSocket URI: " . $response['data']['authorized_redirect_uri'] . "\n";
        echo "Connecting to WebSocket server...\n";

        // Create WebSocket connection with SSL verification disabled
        $context = new ConnectContext();
        $tlsContext = new ClientTlsContext('api.upstox.com', [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]);
        $context = $context->withTlsContext($tlsContext);

        try {
            echo "Establishing WebSocket connection...\n";
            $connection = connect($response['data']['authorized_redirect_uri'], null, $context);
            echo "WebSocket connection established successfully!\n";
        } catch (\Exception $e) {
            echo "WebSocket connection failed: " . $e->getMessage() . "\n";
            if ($e->getPrevious()) {
                echo "Previous connection error: " . $e->getPrevious()->getMessage() . "\n";
            }
            throw $e;
        }

        echo "----------------------------------------\n";

        // Subscribe to Nifty Bank and Nifty 50
        $data = [
            "guid" => uniqid(),
            "method" => "sub",
            "data" => [
                "mode" => "ltpc", // Using LTPC mode for faster updates
                "instrumentKeys" => ["NSE_INDEX|Nifty Bank", "NSE_INDEX|Nifty 50"]
            ]
        ];

        // Send subscription request
        $subscriptionJson = json_encode($data);
        echo "Sending subscription request: " . $subscriptionJson . "\n";
        $connection->sendBinary($subscriptionJson);
        echo "Subscription request sent for:\n";
        echo "- Nifty Bank\n";
        echo "- Nifty 50\n";
        echo "Waiting for live price updates...\n";
        echo "----------------------------------------\n";

        // Process incoming messages
        foreach ($connection as $message) {
            $payload = $message->buffer();
            echo "Received raw message: " . bin2hex($payload) . "\n"; // Debug raw message

            // Handle heartbeat messages
            if ($payload === '100') {
                echo "Heartbeat message received\n";
                continue;
            }

            if (!empty($payload)) {
                try {
                    $decodedData = decodeProtobuf($payload);
                    $jsonData = json_decode($decodedData->serializeToJsonString(), true);
                    echo "Decoded JSON data: " . print_r($jsonData, true) . "\n";

                    // Update the market data cache if we have feeds data
                    if (isset($jsonData['feeds'])) {
                        // Check for Nifty 50 updates
                        if (isset($jsonData['feeds']['NSE_INDEX|Nifty 50']) &&
                            isset($jsonData['feeds']['NSE_INDEX|Nifty 50']['ltpc'])) {
                            $nifty50 = $jsonData['feeds']['NSE_INDEX|Nifty 50']['ltpc'];
                            $ltp = $nifty50['ltp'];
                            $cp = $nifty50['cp'];
                            $change = $ltp - $cp;
                            $change_percent = ($cp > 0) ? ($change / $cp) * 100 : 0;
                            $marketData['nifty50'] = [
                                'price' => number_format($ltp, 2),
                                'change' => number_format($change, 2),
                                'change_percent' => number_format($change_percent, 2),
                                'timestamp' => date('H:i:s')
                            ];
                        }

                        // Check for Nifty Bank updates
                        if (isset($jsonData['feeds']['NSE_INDEX|Nifty Bank']) &&
                            isset($jsonData['feeds']['NSE_INDEX|Nifty Bank']['ltpc'])) {
                            $niftyBank = $jsonData['feeds']['NSE_INDEX|Nifty Bank']['ltpc'];
                            $ltp = $niftyBank['ltp'];
                            $cp = $niftyBank['cp'];
                            $change = $ltp - $cp;
                            $change_percent = ($cp > 0) ? ($change / $cp) * 100 : 0;
                            $marketData['niftybank'] = [
                                'price' => number_format($ltp, 2),
                                'change' => number_format($change, 2),
                                'change_percent' => number_format($change_percent, 2),
                                'timestamp' => date('H:i:s')
                            ];
                        }

                        // Save updated market data to cache file
                        saveMarketData($marketData['nifty50'], $marketData['niftybank']);
                    }

                    // Print the live prices
                    if (isset($jsonData['feeds'])) {
                        echo "\n=== LIVE PRICE UPDATE ===\n";
                        echo "Time: " . date('Y-m-d H:i:s') . "\n";
                        if (isset($jsonData['feeds']['NSE_INDEX|Nifty 50'])) {
                            $nifty50Data = $jsonData['feeds']['NSE_INDEX|Nifty 50']['ltpc'];
                            $ltp = $nifty50Data['ltp'];
                            $cp = $nifty50Data['cp'];
                            $change = $ltp - $cp;
                            $change_percent = ($cp > 0) ? ($change / $cp) * 100 : 0;
                            echo "Nifty 50: " . number_format($ltp, 2) . " (" .
                                number_format($change, 2) . " / " .
                                number_format($change_percent, 2) . "%)\n";
                        }
                        if (isset($jsonData['feeds']['NSE_INDEX|Nifty Bank'])) {
                            $niftyBankData = $jsonData['feeds']['NSE_INDEX|Nifty Bank']['ltpc'];
                            $ltp = $niftyBankData['ltp'];
                            $cp = $niftyBankData['cp'];
                            $change = $ltp - $cp;
                            $change_percent = ($cp > 0) ? ($change / $cp) * 100 : 0;
                            echo "Nifty Bank: " . number_format($ltp, 2) . " (" .
                                number_format($change, 2) . " / " .
                                number_format($change_percent, 2) . "%)\n";
                        }
                        echo "------------------------\n";
                    }
                } catch (\Exception $e) {
                    echo "Error processing message: " . $e->getMessage() . "\n";
                    echo "Raw payload: " . bin2hex($payload) . "\n";
                }
            }
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        if ($e->getPrevious()) {
            echo "Previous error: " . $e->getPrevious()->getMessage() . "\n";
        }
    }
}

// Upstox API access token
$accessToken = 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI1NkI0QkgiLCJqdGkiOiI2ODZmNTk3MjFhMTAwMTc2MTE5ODIyNTIiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1MjEyNzg1OCwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzUyMTg0ODAwfQ.eU-4Egus6e93h3MggCKoxxV24vBLisT0BzO7dMnRKC8'; // Replace with your actual access token
fetchLivePrices($accessToken);
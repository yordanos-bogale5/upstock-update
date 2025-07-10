<?php
// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Generate demo data with slight variations to simulate live updates
function generateDemoData() {
    static $baseNifty = 22150.75;
    static $baseBank = 47325.50;
    
    // Add small random variations to simulate price changes
    $niftyVariation = mt_rand(-50, 50) / 10;
    $bankVariation = mt_rand(-100, 100) / 10;
    
    $currentNifty = $baseNifty + $niftyVariation;
    $currentBank = $baseBank + $bankVariation;
    
    // Calculate changes from previous day closing
    $niftyClose = 22100.25;
    $bankClose = 47200.75;
    
    $niftyChange = $currentNifty - $niftyClose;
    $niftyChangePercent = ($niftyChange / $niftyClose) * 100;
    
    $bankChange = $currentBank - $bankClose;
    $bankChangePercent = ($bankChange / $bankClose) * 100;
    
    return [
        'nifty50' => [
            'price' => number_format($currentNifty, 2),
            'change' => number_format($niftyChange, 2),
            'change_percent' => number_format($niftyChangePercent, 2),
            'timestamp' => date('H:i:s')
        ],
        'niftybank' => [
            'price' => number_format($currentBank, 2),
            'change' => number_format($bankChange, 2),
            'change_percent' => number_format($bankChangePercent, 2),
            'timestamp' => date('H:i:s')
        ]
    ];
}

// Generate and return demo data
echo json_encode(generateDemoData());

<?php
// Upstox API Configuration
define('UPSTOX_ACCESS_TOKEN', 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI1NkI0QkgiLCJqdGkiOiI2ODY2MTJiNWVmNmFiMTE1Yjg0YjBhMjMiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1MTUxOTkyNSwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzUxNTgwMDAwfQ.ua7k41YL3_p97QLXN6UVA419rtQWbvA53N2Lk6fuFyQ');

// API Configuration
define('API_HOST', 'https://api.upstox.com/v2');
define('API_VERSION', '2.0');

// WebSocket Configuration
define('WEBSOCKET_HOST', 'api.upstox.com');
define('WEBSOCKET_PORT', 443);

// Error Reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 1);

// Time Zone
date_default_timezone_set('Asia/Kolkata'); // Indian Standard Time 
<?php

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

//db
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'branchwatch');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

//app
defined('APP_NAME')    || define('APP_NAME', 'BranchWatch');
defined('APP_TZ')      || define('APP_TZ', 'Asia/Manila');   // timezone
defined('DB_TZ')       || define('DB_TZ', '+08:00');        
defined('CURRENCY')    || define('CURRENCY', '₱');
defined('ONLINE_SECS') || define('ONLINE_SECS', 120);        // vendor counts as online indicator
defined('POLL_MS')     || define('POLL_MS', 10000);          // dashboard refresh interval (10 s)
defined('BASE_URL')    || define('BASE_URL', null);        

const EXPENSE_CATEGORIES = [
    'Supplies', 'Utilities', 'Rent', 'Salaries',
    'Transportation', 'Maintenance', 'Restocking', 'Other',
];

<?php

// Define base dir
define('ManiaControlDir', __DIR__);

// Set process settings
ini_set('memory_limit', '128M');
if (function_exists('date_default_timezone_get') && function_exists('date_default_timezone_set')) {
	date_default_timezone_set(@date_default_timezone_get());
}

// Error handling
ini_set('log_errors', true);
ini_set('display_errors', '1');
ini_set('error_reporting', -1);
ini_set('display_startup_errors', true);
if (!is_dir('logs')) {
	mkdir('logs');
}
ini_set('error_log', 'logs/ManiaControl_' . getmypid() . '.log');

// Load ManiaControl class
require_once __DIR__ . '/core/ManiaControl.php';

// Start ManiaControl
error_log('Loading ManiaControl v' . ManiaControl\ManiaControl::VERSION . '...');

$maniaControl = new ManiaControl\ManiaControl();
$maniaControl->run();

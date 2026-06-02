<?php
/**
 * Basic configuration for the SME Credit Decision System.
 * Database values are configured for the target hosting environment.
 */

const APP_NAME = 'SME Credit Decision System';
const APP_ASSET_VERSION = '2026.06.02.2';

// Automatically detects the application base URL for root and subfolder deployments.
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
$applicationRoot = str_replace('\\', '/', __DIR__);
$detectedBaseUrl = '/';

if ($documentRoot !== '' && strpos($applicationRoot, rtrim($documentRoot, '/')) === 0) {
    $relativePath = trim(substr($applicationRoot, strlen(rtrim($documentRoot, '/'))), '/');
    $detectedBaseUrl = $relativePath === '' ? '/' : '/' . $relativePath . '/';
}

define('BASE_URL', $detectedBaseUrl);

// MySQL connection settings.
const DB_HOST = 'localhost';
const DB_NAME = 'pubquest_risk';
const DB_USER = 'pubquest_admin';
const DB_PASS = '#7K{#iELyX[N';

// Development error reporting. Disable display_errors in production.
const APP_DEBUG = true;

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

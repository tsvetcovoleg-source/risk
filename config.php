<?php
/**
 * Basic configuration for the SME Credit Decision System.
 * Database values are configured for the target hosting environment.
 */

const APP_NAME = 'SME Credit Decision System';
const APP_ASSET_VERSION = '2026.06.02.3';

// Automatically detects the application base URL for root and subfolder deployments.
// Prefer the public script URL over the filesystem path, because hosting aliases can
// place the application files in a subfolder while serving them from the domain root.
$detectedBaseUrl = '/';
$baseUrlDetectedFromScript = false;
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$scriptFilename = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']) : '';
$applicationRoot = str_replace('\\', '/', __DIR__);

if ($scriptName !== '' && $scriptFilename !== '' && strpos($scriptFilename, $applicationRoot . '/') === 0) {
    $relativeScriptPath = substr($scriptFilename, strlen($applicationRoot) + 1);

    if ($relativeScriptPath !== '' && substr($scriptName, -strlen($relativeScriptPath)) === $relativeScriptPath) {
        $basePath = trim(substr($scriptName, 0, -strlen($relativeScriptPath)), '/');
        $detectedBaseUrl = $basePath === '' ? '/' : '/' . $basePath . '/';
        $baseUrlDetectedFromScript = true;
    }
}

if (!$baseUrlDetectedFromScript) {
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';

    if ($documentRoot !== '' && strpos($applicationRoot, rtrim($documentRoot, '/')) === 0) {
        $relativePath = trim(substr($applicationRoot, strlen(rtrim($documentRoot, '/'))), '/');
        $detectedBaseUrl = $relativePath === '' ? '/' : '/' . $relativePath . '/';
    }
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

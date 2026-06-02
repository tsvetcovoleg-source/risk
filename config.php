<?php
/**
 * Basic configuration for the SME Credit Decision System.
 * Update database values before deploying to a real environment.
 */

const APP_NAME = 'SME Credit Decision System';
const BASE_URL = '/';

// MySQL connection placeholders.
const DB_HOST = 'DB_HOST';
const DB_NAME = 'DB_NAME';
const DB_USER = 'DB_USER';
const DB_PASS = 'DB_PASS';

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

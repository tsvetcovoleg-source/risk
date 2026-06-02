<?php
/**
 * Basic configuration for the SME Credit Decision System.
 * Database values are configured for the target hosting environment.
 */

const APP_NAME = 'SME Credit Decision System';
const BASE_URL = '/';

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

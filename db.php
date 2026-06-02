<?php
/**
 * PDO database connection bootstrap.
 * Future SQL queries should use prepared statements with this $pdo instance.
 */

$pdo = null;
$dbConnectionError = null;

$databaseConfigured = DB_HOST !== 'DB_HOST'
    && DB_NAME !== 'DB_NAME'
    && DB_USER !== 'DB_USER';

if (!$databaseConfigured) {
    $dbConnectionError = 'Database credentials are not configured yet.';
} else {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        $dbConnectionError = APP_DEBUG
            ? $exception->getMessage()
            : 'Database connection is currently unavailable.';
    }
}

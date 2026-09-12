<?php

declare(strict_types=1);

/**
 * PDMIS — PDO database connection.
 *
 * Credentials come from environment variables so the same code works on
 * local XAMPP and on Sevalla. Sevalla: Applications → Networking →
 * Add internal connection → “Add environment variables to the application”.
 *
 * Accepted names (first match wins):
 *   Host     DB_HOST / DATABASE_HOST / PDMIS_DB_HOST
 *   Name     DB_NAME / DATABASE_NAME / PDMIS_DB_NAME
 *   User     DB_USER / DATABASE_USER / PDMIS_DB_USER
 *   Password DB_PASS / DB_PASSWORD / DATABASE_PASSWORD / PDMIS_DB_PASS
 *   Port     DB_PORT / DATABASE_PORT / PDMIS_DB_PORT  (default 3306)
 */

function pdmisReadEnv(string ...$keys): string
{
    foreach ($keys as $key) {
        foreach ([getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function pdmisIsLocalDatabaseEnv(): bool
{
    $appEnv = strtolower(pdmisReadEnv('APP_ENV'));

    if ($appEnv === 'local') {
        return true;
    }

    if ($appEnv === 'production' || $appEnv === 'staging') {
        return false;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    return $host === ''
        || str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
        || str_starts_with($host, '[::1]');
}

$dbHost = pdmisReadEnv('DB_HOST', 'DATABASE_HOST', 'PDMIS_DB_HOST');
$dbName = pdmisReadEnv('DB_NAME', 'DATABASE_NAME', 'PDMIS_DB_NAME');
$dbUser = pdmisReadEnv('DB_USER', 'DATABASE_USER', 'PDMIS_DB_USER');
$dbPass = pdmisReadEnv('DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD', 'PDMIS_DB_PASS');
$dbPort = pdmisReadEnv('DB_PORT', 'DATABASE_PORT', 'PDMIS_DB_PORT');
$dbCharset = 'utf8mb4';

if (pdmisIsLocalDatabaseEnv()) {
    $dbHost = $dbHost !== '' ? $dbHost : 'localhost';
    $dbName = $dbName !== '' ? $dbName : 'PartnershipRegistry';
    $dbUser = $dbUser !== '' ? $dbUser : 'root';
    $dbPort = $dbPort !== '' ? $dbPort : '3306';
}

if ($dbPort === '') {
    $dbPort = '3306';
}

if ($dbHost === '' || $dbName === '' || $dbUser === '') {
    error_log('PDMIS database connection failed: missing DB_HOST, DB_NAME, or DB_USER environment variables.');
    http_response_code(500);
    exit('Database connection failed. Please contact the system administrator.');
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $dbHost,
    $dbPort,
    $dbName,
    $dbCharset
);

$pdoOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $exception) {
    error_log('PDMIS database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact the system administrator.');
}

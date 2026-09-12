<?php

declare(strict_types=1);

/**
 * PDMIS — PDO database connection.
 *
 * Reads Sevalla / hosting credentials from $_ENV, $_SERVER, then getenv().
 * Local XAMPP still works when those variables are unset.
 */

$host     = $_ENV['DB_HOST']     ?? $_SERVER['DB_HOST']     ?? getenv('DB_HOST');
$port     = $_ENV['DB_PORT']     ?? $_SERVER['DB_PORT']     ?? getenv('DB_PORT')     ?? 3306;
$db       = $_ENV['DB_NAME']     ?? $_SERVER['DB_NAME']     ?? getenv('DB_NAME');
$user     = $_ENV['DB_USER']     ?? $_SERVER['DB_USER']     ?? getenv('DB_USER');
$password = $_ENV['DB_PASS']     ?? $_SERVER['DB_PASS']     ?? getenv('DB_PASS');

if ($host === false || $host === null || $host === '') {
    $host = $_ENV['DATABASE_HOST'] ?? $_SERVER['DATABASE_HOST'] ?? getenv('DATABASE_HOST') ?: 'localhost';
}

if ($port === false || $port === null || $port === '') {
    $port = $_ENV['DATABASE_PORT'] ?? $_SERVER['DATABASE_PORT'] ?? getenv('DATABASE_PORT') ?: 3306;
}

if ($db === false || $db === null || $db === '') {
    $db = $_ENV['DATABASE_NAME'] ?? $_SERVER['DATABASE_NAME'] ?? getenv('DATABASE_NAME') ?: 'PartnershipRegistry';
}

if ($user === false || $user === null || $user === '') {
    $user = $_ENV['DATABASE_USER'] ?? $_SERVER['DATABASE_USER'] ?? getenv('DATABASE_USER') ?: 'root';
}

if ($password === false || $password === null || $password === '') {
    $password = $_ENV['DB_PASSWORD']
        ?? $_SERVER['DB_PASSWORD']
        ?? getenv('DB_PASSWORD')
        ?: ($_ENV['DATABASE_PASSWORD'] ?? $_SERVER['DATABASE_PASSWORD'] ?? getenv('DATABASE_PASSWORD') ?: '');
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, (string) $user, (string) $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
} catch (PDOException $e) {
    error_log('PDMIS database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed: ' . $e->getMessage());
}

if (file_exists(__DIR__ . '/schema.sql')) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
        $pdo->exec('DROP TABLE IF EXISTS agreement_history, agreement_draft, proposal_draft, agreement, contact, partner, users, campus;');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');

        $sql = file_get_contents(__DIR__ . '/schema.sql');
        $pdo->exec($sql);
    } catch (PDOException $e) {
        die('SQL Error: ' . $e->getMessage());
    }
}

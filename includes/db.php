<?php
require_once __DIR__ . '/config.php';

// Parser DB_HOST pour extraire host et port
$dbHost = DB_HOST;
$dbPort = 3306; // Default MySQL port

if (str_contains($dbHost, ':')) {
    $parts = explode(':', $dbHost);
    $dbHost = $parts[0];
    $dbPort = intval($parts[1]);
}

// Forcer TCP/IP au lieu de socket Unix
if ($dbHost === 'localhost') {
    $dbHost = '127.0.0.1';
}

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // En production : logger l'erreur au lieu de l'afficher
    error_log('DB Connection Error: ' . $e->getMessage());
    die('Une erreur est survenue. Veuillez réessayer plus tard.');
}
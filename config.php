<?php
/**
 * XFILES — Configuration centrale
 */

// --- ENVIRONNEMENT ---
if (file_exists(__DIR__ . '/config.infinityfree.php')) {
    // Environnement InfinityFree
    require_once __DIR__ . '/config.infinityfree.php';
    $dbPort = 3306;
} else {
    // Environnement Local
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'xfiles');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    // MODIFICATION ICI : On définit le sous-dossier pour que le CSS fonctionne
    define('BASE_URL', '/mini/');

    $dbPort = 3307; // Votre port local[cite: 2]
}

// --- CONNEXION PDO ---
$dbHost = DB_HOST;

if (strpos($dbHost, ':') !== false) {
    $parts  = explode(':', $dbHost);
    $dbHost = $parts[0];
    $dbPort = intval($parts[1]);
}

$_dsn = "mysql:host=$dbHost;port=$dbPort;dbname=" . DB_NAME . ";charset=utf8mb4";

try {
    $pdo = new PDO($_dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    die('Une erreur est survenue. Veuillez réessayer plus tard.');
}

// --- SESSION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function csrfCheck(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// --- FONCTIONS UTILITAIRES ---
require_once __DIR__ . '/includes/functions.php';
<?php
/**
 * XFILES — Configuration centrale
 * Connexion BDD PDO + constantes globales
 */

// --- ENVIRONNEMENT ---
if (getenv('DB_HOST') !== false) {
    // Render (variables d'environnement)
    $dbHost = getenv('DB_HOST');
    $dbPort = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306';
    if ($dbPort && !str_contains($dbHost, ':')) {
        define('DB_HOST', $dbHost . ':' . $dbPort);
    } else {
        define('DB_HOST', $dbHost);
    }
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASSWORD'));
    define('BASE_URL', '/');
} elseif ((isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') || (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], ['127.0.0.1', '::1'], true))) {
    // Local / Replit
    define('DB_HOST', '127.0.0.1:3306');
    define('DB_NAME', 'xfiles');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', '/');
} elseif (file_exists(__DIR__ . '/config.infinityfree.php')) {
    // InfinityFree
    require_once __DIR__ . '/config.infinityfree.php';
} else {
    // Fallback (Replit / any host)
    define('DB_HOST', '127.0.0.1:3306');
    define('DB_NAME', 'xfiles');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', '/');
}

// --- HTTPS EN PRODUCTION ---
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $hostNoPort = strtolower(explode(':', $host)[0]);
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
    $isLocal = in_array($hostNoPort, ['localhost', '127.0.0.1', '::1'], true)
        || str_contains($hostNoPort, '.replit.dev')
        || str_contains($hostNoPort, '.repl.co')
        || str_contains($hostNoPort, '.replit.app')
        || str_contains($hostNoPort, '.kirk.replit.dev')
        || in_array($serverAddr, ['127.0.0.1', '::1'], true);
    $httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!$isLocal && !$httpsOn && $host !== '' && isset($_SERVER['REQUEST_URI'])) {
        header('Location: https://' . $host . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

// --- CONSTANTES ---
define('SITE_NAME', 'XFILES');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx']);

// --- CONNEXION PDO ---
$dbHost = DB_HOST;
$dbPort = 3306;

if (str_contains($dbHost, ':')) {
    $parts  = explode(':', $dbHost);
    $dbHost = $parts[0];
    $dbPort = intval($parts[1]);
}

if ($dbHost === 'localhost') {
    $dbHost = '127.0.0.1';
}

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    die('Une erreur est survenue. Veuillez réessayer plus tard.');
}

// --- SESSION ---
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Génère un champ CSRF hidden
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

/**
 * Vérifie le token CSRF
 */
function csrfCheck(): bool
{
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// --- FONCTIONS UTILITAIRES ---
require_once __DIR__ . '/includes/functions.php';

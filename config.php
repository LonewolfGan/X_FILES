<?php
/**
 * XFILES — Configuration centrale
 * Connexion BDD PDO + constantes globales
 */



// --- ENVIRONNEMENT ---
if (getenv('DB_HOST') !== false) {
    // Render (variables d'environnement)
    $dbHost = getenv('DB_HOST');
    $dbPort = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3307';
    if ($dbPort && !str_contains($dbHost, ':')) {
        define('DB_HOST', $dbHost . ':' . $dbPort);
    } else {
        define('DB_HOST', $dbHost);
    }
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASS', getenv('DB_PASSWORD'));
    define('BASE_URL', '/');
} elseif (file_exists(__DIR__ . '/config.infinityfree.php')) {
    // InfinityFree
    require_once __DIR__ . '/config.infinityfree.php';
} else {
    // Local / Replit — connect via Unix socket to avoid TCP port conflicts
    define('DB_HOST', '127.0.0.1:3307');
    define('DB_NAME', 'xfiles');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    // Auto-detect BASE_URL so CSS/JS links work whether the project is at the
    // server root (http://localhost/) or in a subfolder (http://localhost/xfiles/).
    // config.php is always at the project root, so we compare its real path
    // against the document root to build the correct URL prefix.
    $_docRoot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    $_projRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
    $_subPath  = ($_docRoot !== '' && str_starts_with($_projRoot, $_docRoot))
        ? substr($_projRoot, strlen($_docRoot))
        : '';
    define('BASE_URL', $_subPath !== '' ? $_subPath . '/' : '/');
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
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'XFILES');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
}
if (!defined('ALLOWED_TYPES')) {
    define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx']);
}

// --- CONNEXION PDO ---
$dbHost = DB_HOST;
$dbPort = 3307;

if (str_contains($dbHost, ':')) {
    $parts  = explode(':', $dbHost);
    $dbHost = $parts[0];
    $dbPort = intval($parts[1]);
}

if ($dbHost === 'localhost') {
    $dbHost = '127.0.0.1';
}

// Use Unix socket when available (Replit local dev) to avoid TCP port conflicts
$_socketPath = '/home/runner/mysql-run/mysqld.sock';
$_dsn = file_exists($_socketPath)
    ? 'mysql:unix_socket=' . $_socketPath . ';dbname=' . DB_NAME . ';charset=utf8mb4'
    : 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . DB_NAME . ';charset=utf8mb4';

try {
    $pdo = new PDO(
        $_dsn,
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
    ini_set('session.use_strict_mode', '1');

    $isHttpsProxy = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'httponly'  => true,
        'samesite'  => $isHttpsProxy ? 'None' : 'Lax',
        'secure'    => $isHttpsProxy,
    ]);
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

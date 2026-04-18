<?php
// Configuration multi-environnement
// 1. Render (variables d'environnement)
// 2. InfinityFree (config.infinityfree.php)
// 3. Local (valeurs par défaut)

if (getenv('DB_HOST') !== false) {
    // Render
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
} elseif (file_exists(__DIR__ . '/../config.infinityfree.php')) {
    // InfinityFree
    require_once __DIR__ . '/../config.infinityfree.php';
    return;
} else {
    // Local
    define('DB_HOST', '127.0.0.1:3307');
    define('DB_NAME', 'xfiles');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('BASE_URL', '/mini/');
}

define('SITE_NAME', 'XFILES');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx']);
?>
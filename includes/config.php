<?php
// Support Render environment variables
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3307';

define('DB_HOST', $dbHost . ($dbPort ? ':' . $dbPort : ''));
define('DB_NAME', getenv('DB_NAME') ?: 'xfiles');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('SITE_NAME', 'XFILES');
define('BASE_URL', getenv('RENDER_EXTERNAL_HOSTNAME') ? '/' : '/mini/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx']);
?>
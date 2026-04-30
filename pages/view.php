<?php
/**
 * XFILES — Affichage inline des fichiers pour preview
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$docId = intval($_GET['id'] ?? 0);

if ($docId === 0) {
    http_response_code(404);
    die('Document non trouvé');
}

$stmt = $pdo->prepare("SELECT file, file_type, file_size, user_id, status FROM documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    die('Document non trouvé');
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isOwner = isset($_SESSION['user_id']) && (int)$doc['user_id'] === (int)$_SESSION['user_id'];
if (!$isAdmin && !$isOwner && $doc['status'] !== 'approuve') {
    http_response_code(403);
    die('Accès refusé');
}

$filePath = __DIR__ . '/../uploads/documents/' . $doc['file'];
if (!file_exists($filePath)) {
    http_response_code(404);
    die('Fichier non trouvé sur le serveur');
}

$filename = $doc['file'] ?: 'document';
$mimeType = $doc['file_type'] ?: 'application/octet-stream';
$size = $doc['file_size'] ?: filesize($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=3600');

if ($mimeType === 'application/pdf') {
    header('X-Content-Type-Options: nosniff');
}

readfile($filePath);
exit;

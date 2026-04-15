<?php
/**
 * Téléchargement des fichiers stockés en BLOB
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

$docId = intval($_GET['id'] ?? 0);

if ($docId === 0) {
    http_response_code(404);
    die('Document non trouvé');
}

// Récupérer le document
$stmt = $pdo->prepare("SELECT file, file_data, file_type, file_size, title FROM documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc || empty($doc['file_data'])) {
    http_response_code(404);
    die('Document non trouvé');
}

// Définir les headers pour le téléchargement
$filename = $doc['file'] ?: $doc['title'];
$mimeType = $doc['file_type'] ?: 'application/octet-stream';
$size = $doc['file_size'] ?: strlen($doc['file_data']);

// Nettoyer le nom de fichier
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, no-cache, must-revalidate');

// Afficher le contenu BLOB
echo $doc['file_data'];
exit;

<?php
/**
 * Affichage inline des fichiers pour preview
 * Force Content-Disposition: inline pour les PDF
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
$stmt = $pdo->prepare("SELECT file, file_data, file_type, file_size FROM documents WHERE id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc || empty($doc['file_data'])) {
    http_response_code(404);
    die('Document non trouvé');
}

// Headers pour affichage inline (pas téléchargement)
$filename = $doc['file'] ?: 'document';
$mimeType = $doc['file_type'] ?: 'application/octet-stream';
$size = $doc['file_size'] ?: strlen($doc['file_data']);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=3600');

// Pour les PDF, ajouter des headers spécifiques
if ($mimeType === 'application/pdf') {
    header('X-Content-Type-Options: nosniff');
}

echo $doc['file_data'];
exit;

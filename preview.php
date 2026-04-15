<?php
/**
 * Endpoint pour preview des fichiers en BLOB
 * Retourne JSON avec les infos du fichier pour le modal
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

header('Content-Type: application/json');

$docId = intval($_GET['id'] ?? 0);

if ($docId === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Document non trouvé']);
    exit;
}

// Récupérer le document
$stmt = $pdo->prepare("
    SELECT d.id, d.title, d.file, d.file_type, d.file_size, d.type,
           u.name as user_name, m.name as module_name
    FROM documents d 
    JOIN users u ON d.user_id = u.id 
    LEFT JOIN modules m ON d.module_id = m.id
    WHERE d.id = ?
");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    echo json_encode(['error' => 'Document non trouvé']);
    exit;
}

// Déterminer le type de preview
$previewType = 'generic';
if (strpos($doc['file_type'], 'image/') === 0) {
    $previewType = 'image';
} elseif ($doc['file_type'] === 'application/pdf') {
    $previewType = 'pdf';
}

// Générer une URL de données pour les images (base64)
$dataUrl = null;
if ($previewType === 'image') {
    $stmt = $pdo->prepare("SELECT file_data FROM documents WHERE id = ?");
    $stmt->execute([$docId]);
    $fileData = $stmt->fetchColumn();
    if ($fileData) {
        $dataUrl = 'data:' . $doc['file_type'] . ';base64,' . base64_encode($fileData);
    }
}

echo json_encode([
    'id' => $doc['id'],
    'title' => $doc['title'],
    'filename' => $doc['file'],
    'type' => $doc['type'],
    'fileType' => $doc['file_type'],
    'fileSize' => $doc['file_size'],
    'previewType' => $previewType,
    'userName' => $doc['user_name'],
    'moduleName' => $doc['module_name'],
    'dataUrl' => $dataUrl,
    'viewUrl' => '/mini/view.php?id=' . $doc['id'],  // Pour iframe inline
    'downloadUrl' => '/mini/download.php?id=' . $doc['id']
]);

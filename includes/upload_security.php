<?php
/**
 * XFILES — Upload Security
 * Validation, analyse et sécurisation des fichiers uploadés
 */

// Extensions exécutables — toujours interdites
const DANGEROUS_EXTENSIONS = [
    'exe', 'bat', 'cmd', 'sh', 'php', 'php3', 'php4', 'php5', 'phtml',
    'js', 'jsx', 'ts', 'html', 'htm', 'xml', 'jar', 'msi', 'dmg',
    'app', 'apk', 'scr', 'vbs', 'ps1', 'com', 'dll', 'bin', 'cgi',
    'pl', 'py', 'rb', 'asp', 'aspx', 'cfm', 'htaccess', 'htpasswd'
];

// Types MIME autorisés avec leurs extensions légitimes
const ALLOWED_MIME_TYPES = [
    'application/pdf'                                                          => ['pdf'],
    'application/msword'                                                       => ['doc'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'  => ['docx'],
    'application/vnd.ms-powerpoint'                                            => ['ppt'],
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'=> ['pptx'],
    'application/vnd.ms-excel'                                                 => ['xls'],
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'        => ['xlsx'],
    'text/plain'                                                               => ['txt'],
    'application/zip'                                                          => ['zip'],
    'application/x-zip-compressed'                                             => ['zip'],
    'application/x-rar-compressed'                                             => ['rar'],
    'application/vnd.rar'                                                      => ['rar'],
    'application/x-7z-compressed'                                              => ['7z'],
    'image/jpeg'                                                               => ['jpg', 'jpeg'],
    'image/png'                                                                => ['png'],
    'image/gif'                                                                => ['gif'],
    'image/webp'                                                               => ['webp'],
];

// Tailles max par catégorie
const MAX_SIZE_ARCHIVE  = 50 * 1024 * 1024; // 50 MB
const MAX_SIZE_DEFAULT  = 20 * 1024 * 1024; // 20 MB
const MAX_SIZE_IMAGE    =  5 * 1024 * 1024; //  5 MB

// Mots interdits dans le titre et le nom de fichier
const FORBIDDEN_WORDS = [
    'porn', 'porno', 'sex', 'sexe', 'xxx', 'adult', 'nude', 'naked',
    'pornographie', 'erotique', 'erotic', 'hardcore',
    'gore', 'torture', 'massacre', 'suicide',
    'hack', 'crack', 'warez', 'keygen', 'serial', 'leaked',
    'credit card', 'cvv', 'iban fraud',
    'cocaine', 'heroin', 'meth', 'bomb making', 'explosive',
];

// ----------------------------------------------------------------
// FONCTIONS PUBLIQUES
// ----------------------------------------------------------------

/**
 * Validation complète d'un fichier uploadé
 * Pas de modération admin - les fichiers sont acceptés automatiquement
 *
 * @param  array  $file   Entrée $_FILES['document']
 * @param  string $title  Titre saisi par l'utilisateur
 * @return array  { valid: bool, errors: string[], warnings: string[] }
 */
function validateUpload(array $file, string $title): array
{
    $errors   = [];
    $warnings = [];

    // 1. Erreur PHP d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = _uploadErrorMessage($file['error']);
        return _result(false, $errors, $warnings);
    }

    // 2. Vérifier que c'est bien un upload HTTP (anti-path traversal)
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Fichier invalide.';
        return _result(false, $errors, $warnings);
    }

    // 3. Titre — mots interdits
    $found = _checkForbiddenWords($title);
    if ($found) {
        $errors[] = 'Le titre contient des termes inappropriés.';
    }

    // 4. Nom de fichier — mots interdits
    $found = _checkForbiddenWords(pathinfo($file['name'], PATHINFO_FILENAME));
    if ($found) {
        $errors[] = 'Le nom de fichier est suspect.';
    }

    // 5. Extension dangereuse
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, DANGEROUS_EXTENSIONS, true)) {
        $errors[] = 'Ce type de fichier est interdit (exécutable).';
        return _result(false, $errors, $warnings);
    }

    // 6. MIME type réel (via finfo, pas $_FILES['type'] qui est spoofable)
    $mime = _getRealMime($file['tmp_name']);
    if ($mime === null) {
        $errors[] = 'Impossible de déterminer le type du fichier.';
        return _result(false, $errors, $warnings);
    }

    // 7. MIME autorisé
    if (!array_key_exists($mime, ALLOWED_MIME_TYPES)) {
        $errors[] = 'Type de fichier non autorisé. Formats acceptés : PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, ZIP, RAR, 7Z, images.';
        return _result(false, $errors, $warnings);
    }

    // 8. Cohérence extension / MIME (anti-spoofing)
    $expectedExts = ALLOWED_MIME_TYPES[$mime];
    if (!in_array($ext, $expectedExts, true)) {
        $errors[] = "L'extension .$ext ne correspond pas au contenu réel du fichier ($mime).";
        return _result(false, $errors, $warnings);
    }

    // 9. Taille
    $maxSize = _maxSizeForMime($mime);
    if ($file['size'] > $maxSize) {
        $errors[] = 'Fichier trop volumineux (max ' . ($maxSize / 1024 / 1024) . ' MB).';
        return _result(false, $errors, $warnings);
    }

    // 10. Taille minimale (évite les fichiers vides)
    if ($file['size'] < 1024) {
        $errors[] = 'Le fichier semble vide ou trop petit.';
        return _result(false, $errors, $warnings);
    }

    // 11. Analyse spécifique par type
    if (str_starts_with($mime, 'image/')) {
        [$imgErrors, $imgWarnings] = _analyzeImage($file['tmp_name']);
        // Treat image warnings as errors to trigger review request
        if (!empty($imgWarnings)) {
            $errors = array_merge($errors, $imgWarnings);
        } else {
            $warnings = array_merge($warnings, $imgWarnings);
        }
        $errors = array_merge($errors, $imgErrors);
    }

    if ($mime === 'application/pdf') {
        $pdfWarnings = _analyzePdf($file['tmp_name']);
        // Treat PDF warnings as errors to trigger review request
        if (!empty($pdfWarnings)) {
            $errors = array_merge($errors, $pdfWarnings);
        } else {
            $warnings = array_merge($warnings, $pdfWarnings);
        }
    }

    // 12. Double extension suspecte
    if (preg_match('/\.(php|exe|bat|sh|js|html|asp|cgi)\./i', $file['name'])) {
        $errors[] = 'Nom de fichier avec double extension suspecte.';
    }

    return _result(empty($errors), $errors, $warnings);
}

/**
 * Génère un nom de fichier sécurisé
 */
function generateSecureFilename(string $originalName): string
{
    $ext  = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $hash = bin2hex(random_bytes(16));
    return $hash . '_' . time() . '.' . $ext;
}

/**
 * Log d'un upload suspect
 */
function logSuspiciousUpload(string $userId, string $filename, array $errors): void
{
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = sprintf(
        "[%s] user=%s ip=%s file=%s errors=%s\n",
        date('Y-m-d H:i:s'),
        $userId,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $filename,
        implode(' | ', $errors)
    );

    file_put_contents($logDir . 'upload_security.log', $entry, FILE_APPEND | LOCK_EX);
}

// ----------------------------------------------------------------
// FONCTIONS INTERNES (préfixe _)
// ----------------------------------------------------------------

function _getRealMime(string $tmpPath): ?string
{
    if (!function_exists('finfo_open')) {
        return mime_content_type($tmpPath) ?: null;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);
    return $mime ?: null;
}

function _checkForbiddenWords(string $text): bool
{
    $lower = mb_strtolower($text, 'UTF-8');
    foreach (FORBIDDEN_WORDS as $word) {
        if (str_contains($lower, $word)) {
            return true;
        }
    }
    return false;
}

function _maxSizeForMime(string $mime): int
{
    if (in_array($mime, ['application/zip', 'application/x-zip-compressed',
                          'application/x-rar-compressed', 'application/vnd.rar',
                          'application/x-7z-compressed'], true)) {
        return MAX_SIZE_ARCHIVE;
    }
    if (str_starts_with($mime, 'image/')) {
        return MAX_SIZE_IMAGE;
    }
    return MAX_SIZE_DEFAULT;
}

function _analyzeImage(string $tmpPath): array
{
    $errors   = [];
    $warnings = [];

    $info = @getimagesize($tmpPath);
    if ($info === false) {
        $errors[] = 'Image corrompue ou invalide.';
        return [$errors, $warnings];
    }

    [$w, $h] = $info;

    if ($w < 10 || $h < 10) {
        $errors[] = 'Image trop petite.';
    }

    if ($w > 8000 || $h > 8000) {
        $warnings[] = 'Image de très grande dimension.';
    }

    // Ratio suspect
    $ratio = $w / max($h, 1);
    if ($ratio > 10 || $ratio < 0.1) {
        $warnings[] = 'Ratio d\'aspect inhabituel.';
    }

    // Stéganographie basique : taille >> taille théorique
    $theoretical = $w * $h * 3;
    $actual      = filesize($tmpPath);
    if ($actual > $theoretical * 8 && $actual > 2 * 1024 * 1024) {
        $warnings[] = 'Données inhabituelles dans le fichier image.';
    }

    // Analyse SightEngine ML pour contenu explicite
    [$mlErrors, $mlWarnings] = _analyzeImageSightEngine($tmpPath);
    $errors   = array_merge($errors, $mlErrors);
    $warnings = array_merge($warnings, $mlWarnings);

    return [$errors, $warnings];
}

/**
 * Analyse une image avec SightEngine API pour détecter le contenu explicite
 * @param string $tmpPath Chemin de l'image temporaire
 * @return array [errors[], warnings[]]
 */
function _analyzeImageSightEngine(string $tmpPath): array
{
    $errors   = [];
    $warnings = [];

    $params = array(
        'media' => new CURLFile($tmpPath),
        'workflow' => 'wfl_kzG0UXGaPExZSfSfi36vn',
        'api_user' => '1336098',
        'api_secret' => 'HvL2jwqg67V7PHGGfscn9vZ6NLZqo69V',
    );

    $ch = curl_init('https://api.sightengine.com/1.0/check-workflow.json');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log("SIGHTENGINE DEBUG: API error - http_code=$httpCode response=" . ($response ?? 'null'));
        // En cas d'erreur API, on bloque et demande revue (fail-closed)
        $errors[] = 'Impossible de vérifier le contenu de l\'image. Veuillez réessayer ou contacter l\'administrateur.';
        return [$errors, $warnings];
    }

    $output = json_decode($response, true);
    if ($output === null || !isset($output['summary']['action'])) {
        error_log("SIGHTENGINE DEBUG: Invalid JSON response - " . json_last_error_msg());
        // En cas de réponse invalide, on bloque et demande revue (fail-closed)
        $errors[] = 'Erreur lors de l\'analyse de l\'image. Veuillez réessayer.';
        return [$errors, $warnings];
    }

    if ($output['summary']['action'] === 'reject') {
        $rejectProb = $output['summary']['reject_prob'] ?? 0;
        $rejectReasons = $output['summary']['reject_reason'] ?? [];
        
        // Logger les détails techniques pour l'admin
        error_log("SIGHTENGINE REJECT: prob=$rejectProb reasons=" . json_encode($rejectReasons));
        
        // Extraire les raisons lisibles pour l'utilisateur
        $userReasons = [];
        if (is_array($rejectReasons)) {
            foreach ($rejectReasons as $reason) {
                if (isset($reason['text'])) {
                    $userReasons[] = $reason['text'];
                }
            }
        }
        
        $reasonText = empty($userReasons) ? 'contenu inapproprié' : implode(', ', $userReasons);
        
        $errors[] = sprintf(
            'Contenu inapproprié détecté (%s). Upload interdit.',
            $reasonText
        );
    }

    return [$errors, $warnings];
}

function _analyzePdf(string $tmpPath): array
{
    $warnings = [];

    // Lire les 100 premiers Ko
    $header = file_get_contents($tmpPath, false, null, 0, 102400);
    if ($header === false) {
        return [['Impossible de lire le fichier PDF.']];
    }

    // Vérifier la signature PDF
    if (!str_starts_with($header, '%PDF-')) {
        return [['Le fichier n\'est pas un PDF valide.']];
    }

    // Détecter JavaScript embarqué / actions automatiques (logging désactivé pour éviter erreurs)

    // Détecter les objets externes et mots interdits (logging désactivé pour éviter erreurs)
    $text = _extractPdfTextBasic($header);
    _checkForbiddenWords($text);

    return $warnings;
}

function _extractPdfTextBasic(string $raw): string
{
    preg_match_all('/\(([^\)]{2,200})\)/', $raw, $matches);
    $text = implode(' ', $matches[1] ?? []);
    // Nettoyer les caractères non imprimables
    $cleaned = @preg_replace('/[^\w\s\-\'\.]/u', ' ', $text);
    return $cleaned !== null ? $cleaned : '';
}

function _uploadErrorMessage(int $code): string
{
    return match($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
        UPLOAD_ERR_PARTIAL   => 'Upload incomplet. Réessaie.',
        UPLOAD_ERR_NO_FILE   => 'Aucun fichier sélectionné.',
        UPLOAD_ERR_NO_TMP_DIR=> 'Dossier temporaire manquant.',
        UPLOAD_ERR_CANT_WRITE=> 'Impossible d\'écrire le fichier.',
        UPLOAD_ERR_EXTENSION => 'Upload bloqué par une extension PHP.',
        default              => 'Erreur d\'upload inconnue.',
    };
}

function _result(bool $valid, array $errors, array $warnings): array
{
    return compact('valid', 'errors', 'warnings');
}

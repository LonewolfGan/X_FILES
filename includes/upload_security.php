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
        $errors   = array_merge($errors, $imgErrors);
        $warnings = array_merge($warnings, $imgWarnings);
    }

    if ($mime === 'application/pdf') {
        $pdfWarnings = _analyzePdf($file['tmp_name']);
        $warnings = array_merge($warnings, $pdfWarnings);
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

    // Analyse NudeNet ML pour contenu explicite
    [$mlErrors, $mlWarnings] = _analyzeImageNudeNet($tmpPath);
    $errors   = array_merge($errors, $mlErrors);
    $warnings = array_merge($warnings, $mlWarnings);

    return [$errors, $warnings];
}

/**
 * Analyse une image avec NudeNet (Python) pour détecter le contenu explicite
 * @param string $tmpPath Chemin de l'image temporaire
 * @return array [errors[], warnings[]]
 */
function _analyzeImageNudeNet(string $tmpPath): array
{
    $errors   = [];
    $warnings = [];

    // Chemin vers le script Python
    $pythonScript = __DIR__ . '/../scripts/analyze_image.py';
    error_log("NUDENET DEBUG: Script path=$pythonScript exists=" . (file_exists($pythonScript) ? 'yes' : 'no'));

    if (!file_exists($pythonScript)) {
        error_log("NUDENET DEBUG: Script Python introuvable");
        return [$errors, $warnings];
    }

    // Vérifier que Python est disponible
    $pythonCmd = null;
    
    // Sur Railway (Docker): utiliser le venv Python avec nudenet installé
    $railwayPython = '/usr/local/bin/nudenet-python';
    if (file_exists($railwayPython)) {
        $test = shell_exec($railwayPython . ' --version 2>&1');
        error_log("NUDENET DEBUG: Testing Railway venv python - result=" . ($test ?? 'null'));
        if ($test !== null && str_contains($test, 'Python')) {
            $pythonCmd = $railwayPython;
        }
    }
    
    // Sur Windows : essayer le chemin spécifique d'abord
    if ($pythonCmd === null) {
        $isWindows = DIRECTORY_SEPARATOR === '\\\\';
        
        if ($isWindows) {
            // Essayer d'abord le python du user (où nudenet est installé)
            $userPython = 'C:\\Users\\atlas\\AppData\\Local\\Programs\\Python\\Python310\\python.exe';
            if (file_exists($userPython)) {
                $test = shell_exec('"' . $userPython . '" --version 2>&1');
                error_log("NUDENET DEBUG: Testing user python - result=" . ($test ?? 'null'));
                if ($test !== null && str_contains($test, 'Python')) {
                    $pythonCmd = '"' . $userPython . '"';
                }
            }
        }
        
        // Fallback sur les commandes génériques
        if ($pythonCmd === null) {
            // Sur Linux/Render: python3 en priorité
            $commands = $isWindows ? ['python', 'python3', 'py'] : ['python3', 'python'];
            
            foreach ($commands as $cmd) {
                $test = shell_exec($cmd . ' --version 2>&1');
                error_log("NUDENET DEBUG: Testing $cmd - result=" . ($test ?? 'null'));
                // Vérifier que c'est vraiment Python et pas le message du Microsoft Store
                if ($test !== null && str_contains($test, 'Python') && !str_contains($test, 'introuvable') && !str_contains($test, 'Microsoft Store')) {
                    $pythonCmd = $cmd;
                    break;
                }
            }
        }
    }

    if ($pythonCmd === null) {
        error_log("NUDENET DEBUG: Python non trouvé");
        return [$errors, $warnings];
    }
    error_log("NUDENET DEBUG: Using Python cmd=$pythonCmd");

    // Exécuter le script Python
    $escapedPath = escapeshellarg($tmpPath);
    $escapedScript = escapeshellarg($pythonScript);
    $command = "$pythonCmd $escapedScript $escapedPath 2>&1";
    error_log("NUDENET DEBUG: Command=$command");

    $output = shell_exec($command);
    error_log("NUDENET DEBUG: Output=" . ($output ?? 'null'));

    if ($output === null || empty($output)) {
        error_log("NUDENET DEBUG: Output vide");
        return [$errors, $warnings];
    }

    // Parser le JSON
    $result = json_decode($output, true);
    if ($result === null || !isset($result['safe'], $result['unsafe'])) {
        error_log("NUDENET DEBUG: JSON invalide - error=" . json_last_error_msg());
        return [$errors, $warnings];
    }
    error_log("NUDENET DEBUG: Result parsed - safe=" . $result['safe'] . " unsafe=" . $result['unsafe']);

    $safeScore   = (float) ($result['safe'] ?? 1.0);
    $unsafeScore = (float) ($result['unsafe'] ?? 0.0);

    // Seuils de détection NudeNet - PAS DE MODÉRATION, juste blocage si explicite
    if ($unsafeScore > 0.7) {
        // Contenu explicitement inapproprié - BLOCAGE STRICT
        $errors[] = sprintf(
            'Contenu explicite detecte dans l\'image (confiance: %d%%). Upload interdit.',
            round($unsafeScore * 100)
        );
    }
    // < 0.7 : accepté automatiquement (pas de review)

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

    // Détecter JavaScript embarqué (risque XSS/exploit)
    if (preg_match('/\/JavaScript|\/JS\s/i', $header)) {
        $warnings[] = 'PDF contenant du JavaScript.';
    }

    // Détecter les actions automatiques
    if (preg_match('/\/OpenAction|\/AA\s/i', $header)) {
        $warnings[] = 'PDF avec action automatique.';
    }

    // Détecter des images embarquées
    if (preg_match('/\/XObject|\/Image/i', $header)) {
        $warnings[] = 'PDF contenant des images.';
    }

    // Mots interdits dans le texte brut
    $text = _extractPdfTextBasic($header);
    if (_checkForbiddenWords($text)) {
        $warnings[] = 'Contenu textuel suspect détecté dans le PDF.';
    }

    return [$warnings];
}

function _extractPdfTextBasic(string $raw): string
{
    preg_match_all('/\(([^\)]{2,200})\)/', $raw, $matches);
    $text = implode(' ', $matches[1] ?? []);
    // Nettoyer les caractères non imprimables
    return preg_replace('/[^\w\s\-\'\.]/u', ' ', $text);
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

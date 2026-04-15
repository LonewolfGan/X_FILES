<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/upload_security.php';

requireLogin();

$currentUser = getCurrentUser($pdo);
$userId      = $currentUser['id'];
$userName    = $currentUser['name'];
$userAvatar  = $currentUser['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName);

$filieres = $pdo->query("SELECT * FROM filieres ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$modules  = $pdo->query("SELECT * FROM modules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$success = null;
$errors  = [];
$warnings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $title    = trim($_POST['title'] ?? '');
    $type     = $_POST['type'] ?? '';
    $moduleId = $_POST['module_id'] ?? '';

    if (empty($title))    $errors[] = "Le titre est obligatoire.";
    if (empty($type))     $errors[] = "Le type de document est obligatoire.";
    if (empty($moduleId)) $errors[] = "Veuillez choisir un module.";

    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        // DEBUG: Log des infos fichier
        error_log("UPLOAD DEBUG: Fichier recu - " . $_FILES['document']['name'] . " - Type: " . $_FILES['document']['type']);
        
        // Validation de sécurité
        $securityCheck = validateUpload($_FILES['document'], $title);
        
        // DEBUG: Log resultat validation
        error_log("UPLOAD DEBUG: Validation result - valid=" . ($securityCheck['valid'] ? 'true' : 'false') . " - errors=" . json_encode($securityCheck['errors'] ?? []) . " - warnings=" . json_encode($securityCheck['warnings'] ?? []));
        
        // Ajouter les warnings si présents (même si valide)
        if (!empty($securityCheck['warnings'])) {
            $warnings = array_merge($warnings ?? [], $securityCheck['warnings']);
        }
        
        if (!$securityCheck['valid']) {
            $errors = array_merge($errors, $securityCheck['errors']);
            error_log("UPLOAD DEBUG: Validation failed - errors: " . json_encode($securityCheck['errors']));
            // Log des tentatives suspectes
            logSuspiciousUpload($userId, $_FILES['document']['name'], $securityCheck['errors']);
        } else {
            error_log("UPLOAD DEBUG: Validation passed, proceeding with upload");
            // Tous les uploads sont acceptés automatiquement (pas de validation admin)
            $status = 'approuve';
            
            // Lire le fichier uploadé en BLOB
            $tmpPath = $_FILES['document']['tmp_name'];
            $originalName = $_FILES['document']['name'];
            $fileType = $_FILES['document']['type'] ?: mime_content_type($tmpPath);
            $fileSize = $_FILES['document']['size'];
            $fileData = file_get_contents($tmpPath);
            
            if ($fileData === false) {
                $errors[] = "Erreur lors de la lecture du fichier.";
                error_log("UPLOAD DEBUG: Erreur file_get_contents");
            } else {
                error_log("UPLOAD DEBUG: Fichier lu en mémoire - taille=" . strlen($fileData));
                
                // Stocker en BLOB dans la base
                $stmt = $pdo->prepare("
                    INSERT INTO documents (title, file, file_data, file_type, file_size, type, module_id, user_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $dbResult = $stmt->execute([$title, $originalName, $fileData, $fileType, $fileSize, $type, $moduleId, $userId, $status]);
                
                error_log("UPLOAD DEBUG: DB execute result=" . ($dbResult ? 'true' : 'false'));
                if (!$dbResult) {
                    error_log("UPLOAD DEBUG: DB error info=" . json_encode($stmt->errorInfo()));
                }
                if ($dbResult) {
                    $success = "Document publié avec succès ! Il est maintenant visible par tous.";
                    error_log("UPLOAD DEBUG: Upload reussi - ID=" . $pdo->lastInsertId());
                } else {
                    $errors[] = "Erreur lors de l'enregistrement dans la base de données.";
                    error_log("UPLOAD DEBUG: Erreur DB - " . json_encode($stmt->errorInfo()));
                }
            }
        }
    } else {
        $errors[] = "Veuillez sélectionner un fichier à uploader.";
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload - XFILES</title>
    <script>
        (function() {
            var s = localStorage.getItem('theme');
            var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', s || sys);
        })();
    </script>
    <link rel="stylesheet" href="/mini/assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/mini/assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/buttons.css?v=<?= time()?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css">
    <style>
        .upload-container {
            max-width: 700px;
            margin: 2rem auto;
        }

        .upload-card {
            background: var(--dash-white);
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: var(--dash-shadow);
        }

        .upload-card .form-group {
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0;
        }

        .upload-card .form-row {
            margin-bottom: 2rem;
            gap: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: start;
        }

        .upload-card .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .upload-card .form-control {
            width: 100% !important;
        }


        .drop-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            background: var(--bg-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-bottom: 0;
        }

        .drop-zone:hover,
        .drop-zone.active {
            border-color: var(--yellow);
            background: rgba(251, 191, 36, 0.05);
        }

        .drop-zone i {
            font-size: 2.5rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .drop-zone p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .file-name-display {
            margin-top: 1rem;
            font-weight: 600;
            color: var(--text-main);
            display: none;
        }
    </style>
</head>

<body class="dashboard-mode">
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <header class="dashboard-header header-centered">
                <div class="header-back-col">
                    <a href="/mini/dashboard.php" class="btn btn-header">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Retour</span>
                    </a>
                </div>
                <h1 class="header-title-centered">Partager une ressource</h1>
                <div class="header-actions">
                    <a href="/mini/index.php" class="btn btn-icon">
                        <i class="fa-solid fa-house"></i>
                    </a>
                    <button id="dash-theme-toggle" class="btn btn-icon">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="/mini/dashboard.php?view=settings" class="header-avatar-link">
                        <img src="<?= htmlspecialchars($userAvatar ?? 'https://ui-avatars.com/api/?name=User&background=fbbf24&color=000') ?>" alt="Avatar" class="header-avatar">
                    </a>
                </div>
            </header>

            <div class="upload-container">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($warnings)): ?>
                    <div class="alert" style="background: var(--primary-transparent); border-color: var(--primary); color: var(--text-main);">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <ul class="error-list" style="margin: 0; padding-left: 1.25rem;">
                            <?php foreach ($warnings as $warning): ?>
                                <li><?= htmlspecialchars($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="upload-card">
                    <form action="/mini/upload.php" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Titre du document</label>
                            <input type="text" name="title" class="form-control" placeholder="Ex: Résumé de Mathématiques S1" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-control nice-select-trigger" required>
                                    <option value="cours">Cours</option>
                                    <option value="td">TD</option>
                                    <option value="tp">TP</option>
                                    <option value="examen">Examen</option>
                                    <option value="resume">Résumé</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Module</label>
                                <select name="module_id" class="form-control nice-select-trigger" required>
                                    <?php foreach ($modules as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fichier</label>
                            <label for="doc-input" class="drop-zone" id="drop-zone">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Cliquez ou glissez-déposez votre fichier ici</p>
                                <p class="drop-zone-help">(PDF, DOCX, PPTX, ZIP - Max 20MB)</p>
                                <div id="file-name" class="file-name-display"></div>
                            </label>
                            <input type="file" name="document" id="doc-input" hidden required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="upload_doc" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i> Envoyer le document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/js/jquery.nice-select.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.nice-select-trigger').niceSelect();

            // Auto-hide alerts
            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);
        });

        const dropZone = document.getElementById('drop-zone');
        const input = document.getElementById('doc-input');
        const fileNameDisplay = document.getElementById('file-name');

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('active');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('active');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('active');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateFileName(e.dataTransfer.files[0].name);
            }
        });

        input.addEventListener('change', () => {
            if (input.files.length) {
                updateFileName(input.files[0].name);
            }
        });

        function updateFileName(name) {
            fileNameDisplay.textContent = "Fichier sélectionné : " + name;
            fileNameDisplay.style.display = 'block';
        }
    </script>
</body>

</html>
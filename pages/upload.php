<?php
/**
 * XFILES — Upload de documents
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload_security.php';

requireLogin();

$currentUser = getCurrentUser($pdo);
$userId      = $currentUser['id'];
$userName    = $currentUser['name'];
$userAvatar  = $currentUser['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName);

$filieres = $pdo->query("SELECT * FROM filieres ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$modules  = $pdo->query("SELECT * FROM modules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$success  = null;
$errors   = [];
$warnings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {

    if (!csrfCheck()) {
        $errors[] = "Erreur de sécurité. Réessayez.";
    }

    $title    = trim($_POST['title']      ?? '');
    $type     = $_POST['type']            ?? '';
    $moduleId = intval($_POST['module_id'] ?? 0);

    if (empty($title))  $errors[] = "Le titre est obligatoire.";
    if (empty($type))   $errors[] = "Le type est obligatoire.";
    if (!in_array($type, ['cours', 'td', 'tp', 'examen', 'resume'], true))
        $errors[] = "Type invalide.";
    if (!$moduleId) {
        $errors[] = "Veuillez choisir un module.";
    } else {
        $check = $pdo->prepare("SELECT id FROM modules WHERE id = ?");
        $check->execute([$moduleId]);
        if (!$check->fetch()) $errors[] = "Module invalide.";
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Veuillez sélectionner un fichier.";
    }

    if (empty($errors)) {
        $validation = validateUpload($_FILES['document'], $title);

        if (!empty($validation['warnings'])) {
            $warnings = $validation['warnings'];
        }

        if (!$validation['valid']) {
            logSuspiciousUpload(
                (string)$userId,
                $_FILES['document']['name'],
                $validation['errors']
            );

            $ext             = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $isDangerousFile = in_array($ext, DANGEROUS_EXTENSIONS, true);

            if ($isDangerousFile) {
                $errors = array_merge($errors, $validation['errors']);
            } else {
                $reason  = implode(', ', $validation['errors']);
                $tempDir = __DIR__ . '/../uploads/temp/';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $tempFilename = 'temp_' . uniqid() . '_' . generateSecureFilename($_FILES['document']['name']);

                if (!move_uploaded_file($_FILES['document']['tmp_name'], $tempDir . $tempFilename)) {
                    $errors[] = "Erreur lors de la sauvegarde temporaire du fichier.";
                } else {
                    $_SESSION['pending_review'] = [
                        'title'            => $title,
                        'temp_file'        => $tempFilename,
                        'file_type'        => $_FILES['document']['type'],
                        'file_size'        => $_FILES['document']['size'],
                        'type'             => $type,
                        'module_id'        => $moduleId,
                        'user_id'          => $userId,
                        'author_name'      => $userName,
                        'rejection_reason' => $reason,
                    ];
                    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&show_review_modal=1');
                    exit;
                }
            }
        } else {
            $secureFilename = generateSecureFilename($_FILES['document']['name']);
            $uploadDir      = __DIR__ . '/../uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $htaccess = $uploadDir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\n");
            }

            if (!move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $secureFilename)) {
                $errors[] = "Erreur lors de la sauvegarde du fichier. Vérifie les permissions du dossier uploads/.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO documents
                        (title, file, file_type, file_size, type, module_id, user_id, author_name, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approuve')
                ");
                $ok = $stmt->execute([
                    $title,
                    $secureFilename,
                    $_FILES['document']['type'],
                    $_FILES['document']['size'],
                    $type,
                    $moduleId,
                    $userId,
                    $userName,
                ]);

                if ($ok) {
                    $success = "Document uploadé avec succès !";
                } else {
                    @unlink($uploadDir . $secureFilename);
                    $errors[] = "Erreur base de données.";
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partager une ressource — XFILES</title>
    <meta name="description" content="Partagez vos ressources académiques avec la communauté XFILES : cours, TD, TP, annales d'examens et synthèses. Aidez d'autres étudiants à réussir." />
    <meta name="robots" content="noindex, follow" />
    <meta name="theme-color" content="#fbbf24" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
    <script>
        (function() {
            var s = localStorage.getItem('theme');
            var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', s || sys);
        })();
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/buttons.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/upload.css?v=1.1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/ui.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/responsive.css?v=1.1.0">
</head>

<body class="dashboard-mode">
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <header class="dashboard-header header-centered">
                <div class="header-back-col">
                    <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-header">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Retour</span>
                    </a>
                </div>
                <h1 class="header-title-centered">Partager une ressource</h1>
                <div class="header-actions">
                    <a href="<?= BASE_URL ?>index.php" class="btn btn-icon">
                        <i class="fa-solid fa-house"></i>
                    </a>
                    <button id="dash-theme-toggle" class="btn btn-icon">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="<?= BASE_URL ?>pages/dashboard.php?view=settings" class="header-avatar-link">
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
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <ul class="error-list">
                            <?php foreach ($warnings as $warning): ?>
                                <li><?= htmlspecialchars($warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="upload-card">
                    <form action="<?= BASE_URL ?>pages/upload.php" method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
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
                                <i class="fa-solid fa-cloud-arrow-up"></i> Envoyer le document
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

            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);
        });

        const dropZone = document.getElementById('drop-zone');
        const input    = document.getElementById('doc-input');
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
    <script src="<?= BASE_URL ?>js/dashboard.js"></script>
</body>

</html>

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

$success = null;
$errors  = [];
$warnings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {

    // CSRF
    if (!csrfCheck()) {
        $errors[] = "Erreur de sécurité. Réessayez.";
    }

    $title    = trim($_POST['title']     ?? '');
    $type     = $_POST['type']           ?? '';
    $moduleId = intval($_POST['module_id'] ?? 0);

    if (empty($title))    $errors[] = "Le titre est obligatoire.";
    if (empty($type))     $errors[] = "Le type est obligatoire.";
    if (!in_array($type, ['cours','td','tp','examen','resume'], true))
        $errors[] = "Type invalide.";
    if (!$moduleId)       $errors[] = "Veuillez choisir un module.";
    else {
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

            // Vérifier si c'est un fichier dangereux (extension exécutable)
            $isDangerousFile = false;
            $originalName = $_FILES['document']['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (in_array($ext, DANGEROUS_EXTENSIONS, true)) {
                $isDangerousFile = true;
            }

            if ($isDangerousFile) {
                // BLOCAGE COMPLET pour fichiers exécutables dangereux - pas de review possible
                $errors = array_merge($errors, $validation['errors']);
            } else {
                // Fichier autorisé (PDF, images, documents) mais avec problème de contenu
                // Stocker les infos en session pour permettre demande de revue
                $reason = implode(', ', $validation['errors']);
                
                // Stocker le fichier temporairement dans un dossier temp
                $tempDir = __DIR__ . '/../uploads/temp/';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                
                $tempFilename = 'temp_' . uniqid() . '_' . generateSecureFilename($_FILES['document']['name']);
                
                if (!move_uploaded_file($_FILES['document']['tmp_name'], $tempDir . $tempFilename)) {
                    $errors[] = "Erreur lors de la sauvegarde temporaire du fichier.";
                } else {
                    // Stocker les infos en session
                    $_SESSION['pending_review'] = [
                        'title' => $title,
                        'temp_file' => $tempFilename,
                        'file_type' => $_FILES['document']['type'],
                        'file_size' => $_FILES['document']['size'],
                        'type' => $type,
                        'module_id' => $moduleId,
                        'user_id' => $userId,
                        'author_name' => $userName,
                        'rejection_reason' => $reason,
                    ];
                    
                    // Redirection vers le dashboard avec le modal de revue
                    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&show_review_modal=1');
                    exit;
                }
            }
        } else {
            // Générer nom sécurisé
            $secureFilename = generateSecureFilename($_FILES['document']['name']);

            // Chemin upload
            $uploadDir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Bloquer PHP dans uploads/
            $htaccess = $uploadDir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\n");
            }

            if (!move_uploaded_file($_FILES['document']['tmp_name'], $uploadDir . $secureFilename)) {
                $errors[] = "Erreur lors de la sauvegarde du fichier. Vérifie les permissions du dossier uploads/.";
            } else {
                // Fichier validé -> approuvé automatiquement
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
                    // Supprimer le fichier si la DB a échoué
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
    <title>Upload - XFILES</title>
    <script>
        (function() {
            var s = localStorage.getItem('theme');
            var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', s || sys);
        })();
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css?v=1.0.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css?v=1.0.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/buttons.css?v=1.0.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/ui.css?v=1.0.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/responsive.css?v=1.0.0">
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
                    <div class="alert alert-success" style="color: var(--text-main);">
                        <i class="fa-solid fa-circle-check" style="color: var(--text-main);"></i>
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

        // Theme toggle
        (function() {
            var btn = document.getElementById('dash-theme-toggle');
            var html = document.documentElement;

            function updateIcon() {
                btn.innerHTML = html.getAttribute('data-theme') === 'dark' ?
                    '<i class="fa-solid fa-sun"></i>' :
                    '<i class="fa-solid fa-moon"></i>';
            }
            updateIcon();
            btn.addEventListener('click', function() {
                var t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', t);
                localStorage.setItem('theme', t);
                updateIcon();
            });
        })();

        // Mobile sidebar toggle
        (function() {
            var sidebar = document.querySelector('.dashboard-sidebar');
            if (!sidebar) return;
            
            var overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);

            var toggleBtn = document.createElement('button');
            toggleBtn.className = 'mobile-sidebar-toggle';
            toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
            toggleBtn.setAttribute('aria-label', 'Menu');
            document.body.appendChild(toggleBtn);

            function openSidebar() {
                sidebar.classList.add('active');
                overlay.classList.add('active');
                toggleBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            }

            function closeSidebar() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i>';
            }

            toggleBtn.addEventListener('click', function() {
                if (sidebar.classList.contains('active')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            overlay.addEventListener('click', closeSidebar);

            document.querySelectorAll('.sidebar-nav a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 1024) {
                        closeSidebar();
                    }
                });
            });
        })();
    </script>
</body>

</html>
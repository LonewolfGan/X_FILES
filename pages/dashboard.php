<?php
/**
 * XFILES — Dashboard principal
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload_security.php';

requireLogin();

$currentUser = getCurrentUser($pdo);
$userId      = $currentUser['id'] ?? null;
$userName    = $currentUser['name'] ?? $_SESSION['user_name'] ?? 'Utilisateur';
$userLogin   = $currentUser['login'] ?? '';
$userAvatar  = $currentUser['avatar'] ?? $_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=fbbf24&color=000';
$userRole    = $_SESSION['role'] ?? 'etudiant';
$userFiliere = $currentUser['filiere_code'] ?? '';

$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarView = $_GET['view'] ?? '';
$sidebarTypes = isset($_GET['types']) ? explode(',', $_GET['types']) : [];
$isAdmin = ($userRole === 'admin');

// Handle Profile Update
$update_success = null;
$update_errors  = [];
$review_success = null;
$review_errors  = [];

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $review_success = "Document supprimé avec succès.";
}

// Suppression d'un document par son propriétaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_own_doc'])) {
    if (!csrfCheck()) {
        $review_errors[] = "Erreur de sécurité CSRF. Réessayez.";
    } else {
        $docId = intval($_POST['doc_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, user_id, file FROM documents WHERE id = ? AND user_id = ?");
        $stmt->execute([$docId, $userId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            $review_errors[] = "Document introuvable ou accès refusé.";
        } else {
            $pdo->beginTransaction();
            try {
                $delStmt = $pdo->prepare("DELETE FROM documents WHERE id = ? AND user_id = ?");
                $delStmt->execute([$docId, $userId]);

                $filePath = __DIR__ . '/../uploads/documents/' . $doc['file'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }

                $pdo->commit();
                header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&deleted=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $review_errors[] = "Erreur lors de la suppression du document.";
            }
        }
    }
}

// Handle Review Request from Session (after upload rejection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_review'])) {
    if (!csrfCheck()) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_error=csrf');
        exit;
    }
    
    $tempFile = $_POST['temp_file'] ?? '';
    $title = $_POST['title'] ?? '';
    $fileType = $_POST['file_type'] ?? '';
    $fileSize = $_POST['file_size'] ?? '';
    $type = $_POST['type'] ?? '';
    $moduleId = $_POST['module_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $authorName = $_POST['author_name'] ?? '';
    $rejectionReason = $_POST['rejection_reason'] ?? '';
    
    if ($tempFile && $title) {
        $tempDir = __DIR__ . '/../uploads/temp/';
        $uploadDir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $htaccess = $uploadDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\n");
        }
        
        $secureFilename = generateSecureFilename($tempFile);
        $tempFilePath = $tempDir . $tempFile;
        $destFile = $uploadDir . $secureFilename;
        
        if (rename($tempFilePath, $destFile)) {
            $stmt = $pdo->prepare("
                INSERT INTO documents
                    (title, file, file_type, file_size, type, module_id, user_id, author_name, status, rejection_reason, review_requested)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, 1)
            ");
            $ok = $stmt->execute([
                $title,
                $secureFilename,
                $fileType,
                $fileSize,
                $type,
                $moduleId,
                $userId,
                $authorName,
                $rejectionReason,
            ]);
            
            if ($ok) {
                header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_sent=1');
                exit;
            } else {
                rename($destFile, $tempFilePath);
                header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_error=db');
                exit;
            }
        } else {
            header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_error=move');
            exit;
        }
    } else {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_error=none');
        exit;
    }
}

// Cancel review request (delete temp file and clear session)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_review'])) {
    if (!csrfCheck()) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_error=csrf');
        exit;
    }
    
    $tempFile = $_POST['temp_file'] ?? '';
    if ($tempFile) {
        $tempDir = __DIR__ . '/../uploads/temp/';
        $tempFilePath = $tempDir . $tempFile;
        
        if (is_file($tempFilePath)) {
            @unlink($tempFilePath);
        }
    }
    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs&review_cancelled=1');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!csrfCheck()) {
        $update_errors[] = "Erreur de sécurité CSRF. Réessayez.";
    } else {
    $newName    = trim($_POST['name'] ?? '');
    $newFiliere = trim($_POST['filiere'] ?? '');
    $newPass    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $currentPass = $_POST['current_password'] ?? '';

    if (empty($newName))  $update_errors[] = "Le nom est requis.";

    // Security: Check current password
    if (!password_verify($currentPass, $currentUser['password_hash'])) {
        $update_errors[] = "Le mot de passe actuel est incorrect. Modification refusée.";
    }

    // Password change logic
    $passHash = $currentUser['password_hash'];
    if (!empty($newPass)) {
        if (strlen($newPass) < 8) {
            $update_errors[] = "Le nouveau mot de passe doit faire au moins 8 caractères.";
        } elseif ($newPass !== $confirmPass) {
            $update_errors[] = "Les mots de passe ne correspondent pas.";
        } else {
            $passHash = password_hash($newPass, PASSWORD_DEFAULT);
        }
    }

    // Avatar upload
    $avatarPath = $userAvatar;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];
        $fileType = $_FILES['avatar']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['jpg', 'gif', 'png', 'jpeg', 'webp'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/uploads/avatars/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }

            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $avatarPath = BASE_URL . 'uploads/avatars/' . $newFileName;
            } else {
                $update_errors[] = "Erreur lors du déplacement du fichier téléchargé.";
            }
        } else {
            $update_errors[] = "Format d'image non autorisé. Utilisez JPG, GIF, PNG ou WEBP.";
        }
    }

    if (empty($update_errors)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, filiere_code = ?, password_hash = ?, avatar = ? WHERE id = ?");
        if ($stmt->execute([$newName, $newFiliere, $passHash, $avatarPath, $userId])) {
            $update_success = "Profil mis à jour avec succès !";
            // Refresh current user data
            $currentUser = getCurrentUser($pdo);
            $userName    = $currentUser['name'];
            $userLogin   = $currentUser['login'];
            $userAvatar  = $currentUser['avatar'];
            $userFiliere = $currentUser['filiere_code'];
            $_SESSION['user_name']   = $userName;
            $_SESSION['user_avatar'] = $userAvatar;
        } else {
            $update_errors[] = "Une erreur est survenue lors de la mise à jour.";
        }
    }
    }
}

$filieres = $pdo->query('SELECT * FROM filieres ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$types = getDocTypes();

// Fetch user's documents for "Mes documents" view
$userDocs = [];
if ($sidebarView === 'my-docs') {
    $stmt = $pdo->prepare("
        SELECT d.*, m.name as module_name, m.code as module_code, f.name as filiere_name
        FROM documents d
        LEFT JOIN modules m ON d.module_id = m.id
        LEFT JOIN filieres f ON m.filiere_code = f.code
        WHERE d.user_id = ?
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$userId]);
    $userDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$rawFilieres      = $_GET['filieres'] ?? ($_GET['filiere'] ?? '');
$selectedFilieres = array_values(array_filter(explode(',', $rawFilieres)));
$rawTypes         = $_GET['types'] ?? ($_GET['type'] ?? '');
$selectedTypes    = array_values(array_filter(explode(',', $rawTypes), fn($t) => isset($types[$t])));
$rawSemesters     = $_GET['semesters'] ?? ($_GET['semester'] ?? '');
$selectedSemesters = array_values(array_filter(array_map('intval', explode(',', $rawSemesters)), fn($s) => $s >= 1 && $s <= 6));
$search           = trim($_GET['q'] ?? '');
$sort             = $_GET['sort'] ?? 'recent';
$view             = $_GET['view'] ?? 'explorer';
$page             = max(1, (int)($_GET['page'] ?? 1));
$perPage          = 10;
$offset           = ($page - 1) * $perPage;

$where  = ["d.status = 'approuve'"];
$params = [];

if (!empty($selectedFilieres)) {
    $placeholders = implode(',', array_fill(0, count($selectedFilieres), '?'));
    $where[]  = "f.code IN ($placeholders)";
    $params   = array_merge($params, $selectedFilieres);
}

if (!empty($selectedTypes)) {
    $placeholders = implode(',', array_fill(0, count($selectedTypes), '?'));
    $where[] = "d.type IN ($placeholders)";
    $params  = array_merge($params, $selectedTypes);
}

if (!empty($selectedSemesters)) {
    $placeholders = implode(',', array_fill(0, count($selectedSemesters), '?'));
    $where[]  = "m.semester IN ($placeholders)";
    $params   = array_merge($params, $selectedSemesters);
}

if ($search !== '') {
    $where[] = '(d.title LIKE ? OR m.name LIKE ? OR f.name LIKE ? OR u.name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$orderBy = 'd.created_at DESC';
if ($sort === 'oldest') {
    $orderBy = 'd.created_at ASC';
} elseif ($sort === 'title') {
    $orderBy = 'd.title ASC';
}

$urlParams = array_filter([
    'filieres'  => !empty($selectedFilieres)  ? implode(',', $selectedFilieres)  : null,
    'types'     => !empty($selectedTypes)     ? implode(',', $selectedTypes)     : null,
    'semesters' => !empty($selectedSemesters) ? implode(',', $selectedSemesters) : null,
    'q'         => $search ?: null,
    'sort'      => ($sort !== 'recent') ? $sort : null,
], fn($v) => $v !== null);

$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_docs,
        SUM(CASE WHEN type = 'cours' THEN 1 ELSE 0 END) AS cours_count,
        SUM(CASE WHEN type = 'td' THEN 1 ELSE 0 END) AS td_count,
        SUM(CASE WHEN type = 'examen' THEN 1 ELSE 0 END) AS examen_count
    FROM documents
    WHERE status = 'approuve'
");
$statsData = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stats = [
    'documents' => (int)($statsData['total_docs'] ?? 0),
    'cours'     => (int)($statsData['cours_count'] ?? 0),
    'td'        => (int)($statsData['td_count'] ?? 0),
    'examen'    => (int)($statsData['examen_count'] ?? 0),
];

$countSql = "
    SELECT COUNT(*)
    FROM documents d
    LEFT JOIN modules m ON d.module_id = m.id
    LEFT JOIN filieres f ON m.filiere_code = f.code
    LEFT JOIN users u ON d.user_id = u.id
    WHERE " . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalDocs = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalDocs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$docsSql = "
    SELECT
        d.id,
        d.title,
        d.type,
        d.created_at,
        d.file,
        m.name AS module_name,
        m.code AS module_code,
        m.semester,
        f.name AS filiere_name,
        f.code AS filiere_code,
        COALESCE(d.author_name, u.name, 'Auteur inconnu') AS author_name,
        u.avatar AS author_avatar
    FROM documents d
    LEFT JOIN modules m ON d.module_id = m.id
    LEFT JOIN filieres f ON m.filiere_code = f.code
    LEFT JOIN users u ON d.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";
$docsStmt = $pdo->prepare($docsSql);
$docsStmt->execute($params);
$documents = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

$semesterCounts = [];
$semesterStmt = $pdo->query("
    SELECT m.semester, COUNT(*) AS total
    FROM documents d
    LEFT JOIN modules m ON d.module_id = m.id
    WHERE d.status = 'approuve'
    GROUP BY m.semester
    ORDER BY m.semester
");
foreach ($semesterStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (!empty($row['semester'])) {
        $semesterCounts[$row['semester']] = (int)$row['total'];
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — XFILES</title>
    <meta name="description" content="Accède à tes ressources académiques, gère tes documents partagés et explore des milliers de cours, TD, TP et annales sur XFILES." />
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
    <link rel="stylesheet" href="<?= BASE_URL ?>css/modal.css?v=1.1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/ui.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/responsive.css?v=1.1.0">
</head>

<body class="dashboard-mode">
    <div class="dashboard-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <header class="dashboard-header <?= in_array($view, ['settings', 'my-docs']) ? 'header-centered' : '' ?>">
                <?php if ($view === 'settings'): ?>
                    <div class="header-back-col">
                        <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-header">
                            <i class="fa-solid fa-arrow-left"></i>
                        </a>
                    </div>
                    <h1 class="header-title">Paramètres</h1>
                <?php elseif ($view === 'my-docs'): ?>
                    <div class="header-back-col">
                        <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-header">
                            <i class="fa-solid fa-arrow-left"></i>
                        </a>
                    </div>
                    <h1 class="header-title">Mes documents</h1>
                <?php else: ?>
                    <div class="header-left">
                        <h1 class="header-title">Dashboard</h1>
                    </div>
                <?php endif; ?>

                <div class="header-actions">
                    <a href="<?= BASE_URL ?>pages/upload.php" class="btn btn-header">
                        <i class="fa-solid fa-plus"></i>
                        <span class="upload-btn-text">Upload</span>
                    </a>
                    <a href="<?= BASE_URL ?>index.php" class="btn btn-icon">
                        <i class="fa-solid fa-house"></i>
                    </a>
                    <button id="dash-theme-toggle" class="btn btn-icon">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="<?= BASE_URL ?>pages/dashboard.php?view=settings" class="header-avatar-link">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="header-avatar">
                    </a>
                </div>
            </header>

            <?php if ($view === 'settings'): ?>
                <div class="settings-container">

                    <?php if ($update_success): ?>
                        <div class="alert alert-success">
                            <i class="fa-solid fa-circle-check"></i>
                            <?= htmlspecialchars($update_success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($update_errors)): ?>
                        <div class="alert alert-danger">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <ul class="error-list">
                                <?php foreach ($update_errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="settings-card card-premium">
                        <form action="<?= BASE_URL ?>pages/dashboard.php?view=settings" method="post" enctype="multipart/form-data" class="settings-form">
                            <?= csrfField() ?>
                            <div class="avatar-upload-section">
                                <div class="current-avatar-wrapper">
                                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" id="avatar-preview" class="settings-avatar-preview">
                                    <label for="avatar-input" class="avatar-edit-badge">
                                        <i class="fa-solid fa-camera"></i>
                                    </label>
                                </div>
                                <input type="file" name="avatar" id="avatar-input" hidden accept="image/*">
                                <div class="avatar-info">
                                    <h3>Photo de profil</h3>
                                    <p>Cliquez sur l'icône pour changer votre avatar (JPG, PNG ou WEBP)</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nom complet</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userName) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Login</label>
                                    <input type="text" class="form-control" value="@<?= htmlspecialchars($userLogin) ?>" disabled>
                                    <small class="form-hint">Le login ne peut pas être modifié</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Ma Filière</label>
                                <select name="filiere" class="form-control nice-select-trigger">
                                    <?php foreach ($filieres as $f): ?>
                                        <option value="<?= htmlspecialchars($f['code']) ?>" <?= ($userFiliere === $f['code']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr class="form-divider">

                            <div class="password-change-notice">
                                <i class="fa-solid fa-lock"></i>
                                <span>Veuillez confirmer votre mot de passe actuel pour toute modification.</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Mot de passe actuel</label>
                                <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                            </div>

                            <hr class="form-divider">

                            <div class="password-change-notice">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>Laissez les champs suivants vides si vous ne souhaitez pas changer votre mot de passe.</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Nouveau mot de passe</label>
                                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    document.getElementById('avatar-input').addEventListener('change', function(e) {
                        if (e.target.files && e.target.files[0]) {
                            var reader = new FileReader();
                            reader.onload = function(e) {
                                document.getElementById('avatar-preview').src = e.target.result;
                            };
                            reader.readAsDataURL(e.target.files[0]);
                        }
                    });
                </script>

            <?php elseif ($view === 'my-docs'): ?>
                <div class="my-docs-container">
                    
                    <?php if ($review_success): ?>
                        <div class="alert alert-success">
                            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($review_success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($review_errors)): ?>
                        <div class="alert alert-error">
                            <?php foreach ($review_errors as $err): ?>
                                <p><i class="fa-solid fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($userDocs)): ?>
                        <div class="empty-state my-docs-empty">
                            <i class="fa-solid fa-folder-open my-docs-empty-icon"></i>
                            <h3>Aucun document</h3>
                            <p>Vous n'avez pas encore uploadé de documents.</p>
                            <a href="<?= BASE_URL ?>pages/upload.php" class="btn btn-primary my-docs-upload-btn">
                                <i class="fa-solid fa-plus"></i> Uploader un document
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="docs-table-container my-docs-table-container">
                            <table class="docs-table my-docs-table">
                                <thead class="my-docs-table-head">
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Module</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userDocs as $doc): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($doc['title']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $doc['type'] ?>"><?= ucfirst($doc['type']) ?></span>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($doc['module_name'] ?? 'N/A') ?>
                                            </td>
                                            <td>
                                                <?php if ($doc['status'] === 'approuve'): ?>
                                                    <span class="status-approved"><i class="fa-solid fa-check-circle"></i> Approuvé</span>
                                                <?php elseif ($doc['status'] === 'en_attente'): ?>
                                                    <span class="status-pending"><i class="fa-solid fa-hourglass-half"></i> En attente</span>
                                                <?php elseif ($doc['status'] === 'rejete'): ?>
                                                    <span class="status-rejected"><i class="fa-solid fa-ban"></i> Rejeté</span>
                                                    <?php if (!empty($doc['rejection_reason'])): ?>
                                                        <br><small class="my-docs-file-meta"><?= htmlspecialchars($doc['rejection_reason']) ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?>
                                            </td>
                                            <td class="my-docs-actions-cell">
                                                <div class="action-btn-group">
                                                    <a href="<?= BASE_URL ?>pages/view.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-sm btn-secondary my-docs-action-btn" title="Voir">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>pages/download.php?id=<?= (int)$doc['id'] ?>" class="btn btn-sm btn-primary my-docs-action-btn" title="Télécharger">
                                                        <i class="fa-solid fa-download"></i>
                                                    </a>

                                                    <form method="POST" action="<?= BASE_URL ?>pages/dashboard.php?view=my-docs" class="my-docs-delete-form">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                                                        <input type="hidden" name="delete_own_doc" value="1">
                                                        <button type="submit" class="btn btn-sm btn-primary my-docs-action-btn">
                                                            <i class="fa-solid fa-trash"></i> Supprimer
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <form class="search-hero-input" method="get" action="<?= BASE_URL ?>pages/dashboard.php">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Rechercher un document...">
                    <button type="submit" class="btn btn-header">Rechercher</button>
                </form>

                <div class="filter-pills">
                    <a href="<?= buildUrl(array_merge($urlParams, ['types' => null, 'page' => null])) ?>" class="pill <?= empty($selectedTypes) ? 'active' : '' ?>"><i class="fa-solid fa-border-all"></i> Tous</a>
                    <a href="<?= toggleTypeUrl($selectedTypes, 'cours',  $urlParams) ?>" class="pill <?= in_array('cours',  $selectedTypes) ? 'active' : '' ?>"><i class="fa-solid fa-book"></i> Cours</a>
                    <a href="<?= toggleTypeUrl($selectedTypes, 'td',     $urlParams) ?>" class="pill <?= in_array('td',     $selectedTypes) ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Travaux Dirigés</a>
                    <a href="<?= toggleTypeUrl($selectedTypes, 'tp',     $urlParams) ?>" class="pill <?= in_array('tp',     $selectedTypes) ? 'active' : '' ?>"><i class="fa-solid fa-flask"></i> Travaux Pratiques</a>
                    <a href="<?= toggleTypeUrl($selectedTypes, 'examen', $urlParams) ?>" class="pill <?= in_array('examen', $selectedTypes) ? 'active' : '' ?>"><i class="fa-solid fa-file-circle-question"></i> Examens</a>
                    <a href="<?= toggleTypeUrl($selectedTypes, 'resume', $urlParams) ?>" class="pill <?= in_array('resume', $selectedTypes) ? 'active' : '' ?>"><i class="fa-solid fa-note-sticky"></i> Résumés</a>
                </div>

                <div class="content-toolbar">
                    <div class="results-info">
                        <span class="results-count-num"><?= $totalDocs ?></span> résultat(s)
                    </div>
                    <div class="toolbar-actions">
                        <div class="sort-options">
                            <a href="<?= buildUrl(array_merge($urlParams, ['sort' => null, 'page' => null])) ?>" class="sort-btn <?= $sort === 'recent' ? 'active' : '' ?>">Récent</a>
                            <a href="<?= buildUrl(array_merge($urlParams, ['sort' => 'oldest', 'page' => null])) ?>" class="sort-btn <?= $sort === 'oldest' ? 'active' : '' ?>">Ancien</a>
                            <a href="<?= buildUrl(array_merge($urlParams, ['sort' => 'title', 'page' => null])) ?>" class="sort-btn <?= $sort === 'title' ? 'active' : '' ?>">Titre</a>
                        </div>
                        <button class="filters-toggle-btn" onclick="document.querySelector('.dashboard-filters').classList.toggle('collapsed')">
                            <i class="fa-solid fa-sliders"></i> Filtres
                        </button>
                    </div>
                </div>

                <div class="explorer-container">
                    <section>
                        <?php if (empty($documents)): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
                                <h3>Aucun document trouvé</h3>
                                <p>Ajoute des ressources pour voir les cartes apparaître ici.</p>
                            </div>
                        <?php else: ?>
                            <div class="doc-grid-v2">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="doc-card-premium">
                                        <div class="doc-card-header" onclick="openPreview(<?= $doc['id'] ?>)">
                                            <span class="type-tag <?= typeClass($doc['type']) ?>">
                                                <i class="fa-solid <?= typeIcon($doc['type']) ?>"></i>
                                                <?= htmlspecialchars(typeLabel($doc['type'])) ?>
                                            </span>
                                            <i class="fa-regular fa-bookmark icon-muted"></i>
                                        </div>

                                        <h3 class="doc-card-title" onclick="openPreview(<?= $doc['id'] ?>)"><?= htmlspecialchars($doc['title']) ?></h3>

                                        <div class="doc-module-row" onclick="openPreview(<?= $doc['id'] ?>)">
                                            <i class="fa-solid fa-layer-group"></i>
                                            <span><?= htmlspecialchars($doc['module_name'] ?? 'Module') ?></span>
                                        </div>

                                        <div class="doc-module-row" onclick="openPreview(<?= $doc['id'] ?>)">
                                            <i class="fa-solid fa-graduation-cap"></i>
                                            <span><?= htmlspecialchars($doc['filiere_name'] ?? 'Filière') ?></span>
                                        </div>

                                        <div class="doc-card-footer">
                                            <img
                                                src="<?= htmlspecialchars($doc['author_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($doc['author_name'] ?? 'X')) ?>"
                                                alt="Auteur"
                                                class="doc-card-avatar">
                                            <div class="doc-card-actions">
                                                <a href="<?= BASE_URL ?>pages/view.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Voir">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>pages/download.php?id=<?= (int)$doc['id'] ?>" class="btn btn-sm btn-primary" title="Télécharger">
                                                    <i class="fa-solid fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="<?= buildUrl(array_merge($urlParams, ['page' => $page - 1])) ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                if ($totalPages <= 7) {
                                    $pageRange = range(1, $totalPages);
                                } elseif ($page <= 4) {
                                    $pageRange = array_merge(range(1, 5), ['…', $totalPages]);
                                } elseif ($page >= $totalPages - 3) {
                                    $pageRange = array_merge([1, '…'], range($totalPages - 4, $totalPages));
                                } else {
                                    $pageRange = [1, '…', $page - 1, $page, $page + 1, '…', $totalPages];
                                }
                                foreach ($pageRange as $p):
                                ?>
                                    <?php if ($p === '…'): ?>
                                        <span class="page-btn page-btn-dots">…</span>
                                    <?php else: ?>
                                        <a href="<?= buildUrl(array_merge($urlParams, ['page' => $p])) ?>"
                                            class="page-btn <?= $p === $page ? 'page-btn-active' : '' ?>">
                                            <?= $p ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= buildUrl(array_merge($urlParams, ['page' => $page + 1])) ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <aside class="dashboard-filters">
                        <h3>Filtres</h3>

                        <div class="filter-section">
                            <div class="filter-section-title">Filières</div>
                            <div class="filter-options">
                                <?php foreach ($filieres as $filiere): ?>
                                    <?php $filiereActive = in_array($filiere['code'], $selectedFilieres); ?>
                                    <a href="<?= toggleValueUrl($selectedFilieres, $filiere['code'], 'filieres', $urlParams) ?>"
                                        class="filter-option <?= $filiereActive ? 'active' : '' ?>">
                                        <div class="filter-option-label">
                                            <strong><?= htmlspecialchars($filiere['code']) ?></strong>
                                            <span class="filter-option-name"><?= htmlspecialchars($filiere['name']) ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="filter-section">
                            <div class="filter-section-title">Semestre</div>
                            <div class="semester-grid">
                                <?php for ($s = 1; $s <= 6; $s++): ?>
                                    <a href="<?= toggleValueUrl($selectedSemesters, (string)$s, 'semesters', $urlParams) ?>"
                                        class="semester-btn <?= in_array($s, $selectedSemesters) ? 'active' : '' ?>">S<?= $s ?></a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Review Request Modal -->
    <div id="reviewModal" class="modal-overlay" onclick="closeReviewModal(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">Demande de revue</h3>
                <button class="modal-close" onclick="closeReviewModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-content">
                <div class="review-modal-body">
                    <div class="review-modal-header">
                        <div class="review-modal-header-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <p class="review-modal-header-text">Ce document a été rejeté</p>
                    </div>
                    <div class="review-modal-reason-box">
                        <div class="review-modal-reason-label">Raison du rejet</div>
                        <div id="reviewRejectionReason" class="review-modal-reason-text"></div>
                    </div>
                    <div class="review-modal-footer">
                        <p class="review-modal-help-text">Vous pouvez demander une revue manuelle ou supprimer ce document.</p>
                        <div class="review-modal-buttons">
                            <form method="POST" action="<?= BASE_URL ?>pages/dashboard.php?view=my-docs">
                                <?= csrfField() ?>
                                <input type="hidden" name="cancel_review" value="1">
                                <input type="hidden" name="temp_file" id="cancelTempFile">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fa-solid fa-xmark"></i> Annuler
                                </button>
                            </form>
                            <form method="POST" action="<?= BASE_URL ?>pages/dashboard.php?view=my-docs">
                                <?= csrfField() ?>
                                <input type="hidden" name="confirm_review" value="1">
                                <input type="hidden" name="temp_file" id="confirmTempFile">
                                <input type="hidden" name="title" id="confirmTitle">
                                <input type="hidden" name="file_type" id="confirmFileType">
                                <input type="hidden" name="file_size" id="confirmFileSize">
                                <input type="hidden" name="type" id="confirmType">
                                <input type="hidden" name="module_id" id="confirmModuleId">
                                <input type="hidden" name="user_id" id="confirmUserId">
                                <input type="hidden" name="author_name" id="confirmAuthorName">
                                <input type="hidden" name="rejection_reason" id="confirmRejectionReason">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-paper-plane"></i> Demander revue
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Review Modal Functions (part 1 - no XModal dependency)
        function openReviewModal(docId, rejectionReason) {
            document.getElementById('reviewDocId').value = docId;
            document.getElementById('reviewDocIdDelete').value = docId;
            document.getElementById('reviewRejectionReason').textContent = rejectionReason;
            document.getElementById('reviewModal').classList.add('active');
        }

        function closeReviewModal(event) {
            if (!event || event.target.id === 'reviewModal') {
                document.getElementById('reviewModal').classList.remove('active');
            }
        }

    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/js/jquery.nice-select.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.nice-select-trigger').niceSelect();

            // Auto-hide success alerts
            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);

            // Auto-open review modal if coming from rejected upload
            const urlParams = new URLSearchParams(window.location.search);
            const reviewDocId = urlParams.get('review_doc_id');
            const reviewReason = urlParams.get('reason');
            if (reviewDocId && reviewReason) {
                openReviewModal(reviewDocId, decodeURIComponent(reviewReason));
                // Clean URL without reloading
                const newUrl = window.location.pathname + '?view=my-docs';
                window.history.replaceState({}, document.title, newUrl);
            }
        });

        // Preview functions - open in new tab using browser native preview
        function openPreview(docId) {
            const viewUrl = '<?= BASE_URL ?>pages/view.php?id=' + docId;
            window.open(viewUrl, '_blank');
        }

    </script>
    <script src="<?= BASE_URL ?>js/dashboard.js"></script>
    <script src="<?= BASE_URL ?>js/modal.js"></script>

    <script>
        // Auto-open review modal if pending_review exists in session
        <?php if (isset($_SESSION['pending_review'])): 
            $pending = $_SESSION['pending_review'];
            unset($_SESSION['pending_review']); // Vider après lecture pour éviter boucle
        ?>
        document.getElementById('reviewRejectionReason').textContent = <?= json_encode($pending['rejection_reason']) ?>;
        document.getElementById('cancelTempFile').value = <?= json_encode($pending['temp_file']) ?>;
        document.getElementById('confirmTempFile').value = <?= json_encode($pending['temp_file']) ?>;
        document.getElementById('confirmTitle').value = <?= json_encode($pending['title']) ?>;
        document.getElementById('confirmFileType').value = <?= json_encode($pending['file_type']) ?>;
        document.getElementById('confirmFileSize').value = <?= json_encode($pending['file_size']) ?>;
        document.getElementById('confirmType').value = <?= json_encode($pending['type']) ?>;
        document.getElementById('confirmModuleId').value = <?= json_encode($pending['module_id']) ?>;
        document.getElementById('confirmUserId').value = <?= json_encode($pending['user_id']) ?>;
        document.getElementById('confirmAuthorName').value = <?= json_encode($pending['author_name']) ?>;
        document.getElementById('confirmRejectionReason').value = <?= json_encode($pending['rejection_reason']) ?>;
        document.getElementById('reviewModal').classList.add('active');
        <?php endif; ?>
    </script>
</body>

</html>
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

/** @var PDO $pdo */

requireLogin();

$currentUser = getCurrentUser($pdo);
$userId      = $currentUser['id'];
$userName    = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Utilisateur');
$userEmail   = $currentUser['email'] ?? '';
$userAvatar  = $currentUser['avatar'] ?? ($_SESSION['user_avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=fbbf24&color=000');
$userRole    = $_SESSION['role'] ?? 'etudiant';
$userFiliere = $currentUser['filiere_code'] ?? '';

// Handle Profile Update
$update_success = null;
$update_errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newName    = trim($_POST['name'] ?? '');
    $newEmail   = trim($_POST['email'] ?? '');
    $newFiliere = trim($_POST['filiere'] ?? '');
    $newPass    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $currentPass = $_POST['current_password'] ?? '';

    if (empty($newName))  $update_errors[] = "Le nom est requis.";
    if (empty($newEmail)) $update_errors[] = "L'email est requis.";

    // Security: Check current password
    if (!password_verify($currentPass, $currentUser['password_hash'])) {
        $update_errors[] = "Le mot de passe actuel est incorrect. Modification refusée.";
    }

    // Check email uniqueness if changed
    if (empty($update_errors) && $newEmail !== $userEmail) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$newEmail, $userId]);
        if ($stmt->fetch()) $update_errors[] = "Cet email est déjà utilisé par un autre utilisateur.";
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
                $avatarPath = '/mini/uploads/avatars/' . $newFileName;
            } else {
                $update_errors[] = "Erreur lors du déplacement du fichier téléchargé.";
            }
        } else {
            $update_errors[] = "Format d'image non autorisé. Utilisez JPG, GIF, PNG ou WEBP.";
        }
    }

    if (empty($update_errors)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, filiere_code = ?, password_hash = ?, avatar = ? WHERE id = ?");
        if ($stmt->execute([$newName, $newEmail, $newFiliere, $passHash, $avatarPath, $userId])) {
            $update_success = "Profil mis à jour avec succès !";
            // Refresh current user data
            $currentUser = getCurrentUser($pdo);
            $userName    = $currentUser['name'];
            $userEmail   = $currentUser['email'];
            $userAvatar  = $currentUser['avatar'];
            $userFiliere = $currentUser['filiere_code'];
            $_SESSION['user_name']   = $userName;
            $_SESSION['user_avatar'] = $userAvatar;
        } else {
            $update_errors[] = "Une erreur est survenue lors de la mise à jour.";
        }
    }
}

$filieres = $pdo->query('SELECT * FROM filieres ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$types = [
    'cours'  => ['label' => 'Cours',    'icon' => 'fa-book'],
    'td'     => ['label' => 'TD',       'icon' => 'fa-list-check'],
    'tp'     => ['label' => 'TP',       'icon' => 'fa-flask'],
    'examen' => ['label' => 'Examen',   'icon' => 'fa-file-circle-question'],
    'resume' => ['label' => 'Résumé',   'icon' => 'fa-note-sticky'],
];

function typeLabel(string $type): string
{
    $map = [
        'cours'  => 'Cours',
        'td'     => 'TD',
        'tp'     => 'TP',
        'examen' => 'Examen',
        'resume' => 'Résumé',
    ];
    return $map[$type] ?? ucfirst($type);
}

function typeIcon(string $type): string
{
    $map = [
        'cours'  => 'fa-book',
        'td'     => 'fa-list-check',
        'tp'     => 'fa-flask',
        'examen' => 'fa-file-circle-question',
        'resume' => 'fa-note-sticky',
    ];
    return $map[$type] ?? 'fa-file-lines';
}

function typeClass(string $type): string
{
    return 'doc-type-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
}

function buildUrl(array $params): string
{
    $params = array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    });

    return '/mini/dashboard.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

function toggleTypeUrl(array $currentTypes, string $type, array $urlParams): string
{
    if (in_array($type, $currentTypes, true)) {
        $newTypes = array_values(array_filter($currentTypes, fn($t) => $t !== $type));
    } else {
        $newTypes = array_merge($currentTypes, [$type]);
    }
    $params = array_merge($urlParams, [
        'types' => !empty($newTypes) ? implode(',', $newTypes) : null,
        'page'  => null,
    ]);
    return buildUrl($params);
}

function toggleValueUrl(array $current, string $value, string $key, array $urlParams): string
{
    if (in_array($value, $current, true)) {
        $next = array_values(array_filter($current, fn($v) => $v !== $value));
    } else {
        $next = array_merge($current, [$value]);
    }
    $params = array_merge($urlParams, [
        $key   => !empty($next) ? implode(',', $next) : null,
        'page' => null,
    ]);
    return buildUrl($params);
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
        u.name AS author_name,
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
    <title>Dashboard - XFILES</title>
    <script>
        (function() {
            var s = localStorage.getItem('theme');
            var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', s || sys);
        })();
    </script>
    <link rel="stylesheet" href="/mini/assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/mini/assets/css/dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/mini/assets/css/components/buttons.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/mini/assets/css/components/modal.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css">
</head>

<body class="dashboard-mode">
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="dashboard-content">
            <header class="dashboard-header <?= $view === 'settings' ? 'header-centered' : '' ?>">
                <?php if ($view === 'settings'): ?>
                    <div class="header-back-col">
                        <a href="/mini/dashboard.php" class="btn btn-header">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Retour</span>
                        </a>
                    </div>
                    <h1 class="header-title">Paramètres</h1>
                <?php else: ?>
                    <div class="header-left">
                        <h1 class="header-title">Dashboard</h1>
                    </div>
                <?php endif; ?>

                <div class="header-actions">
                    <a href="/mini/upload.php" class="btn btn-header">
                        <i class="fa-solid fa-plus"></i>
                        <span class="upload-btn-text">Upload</span>
                    </a>
                    <a href="/mini/index.php" class="btn btn-icon">
                        <i class="fa-solid fa-house"></i>
                    </a>
                    <button id="dash-theme-toggle" class="btn btn-icon">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="/mini/dashboard.php?view=settings" class="header-avatar-link">
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
                        <form action="/mini/dashboard.php?view=settings" method="post" enctype="multipart/form-data" class="settings-form">
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
                                    <label class="form-label">Adresse Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userEmail) ?>" required>
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

            <?php else: ?>

                <form class="search-hero-input" method="get" action="/mini/dashboard.php">
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
                    <div class="sort-options">
                        <a href="<?= buildUrl(array_merge($urlParams, ['sort' => null, 'page' => null])) ?>" class="sort-btn <?= $sort === 'recent' ? 'active' : '' ?>">Récent</a>
                        <a href="<?= buildUrl(array_merge($urlParams, ['sort' => 'oldest', 'page' => null])) ?>" class="sort-btn <?= $sort === 'oldest' ? 'active' : '' ?>">Ancien</a>
                        <a href="<?= buildUrl(array_merge($urlParams, ['sort' => 'title', 'page' => null])) ?>" class="sort-btn <?= $sort === 'title' ? 'active' : '' ?>">Titre</a>
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
                                    <a href="#" onclick="openPreview(<?= $doc['id'] ?>); return false;" class="doc-card-premium">
                                        <div class="doc-card-header">
                                            <span class="type-tag <?= typeClass($doc['type']) ?>">
                                                <i class="fa-solid <?= typeIcon($doc['type']) ?>"></i>
                                                <?= htmlspecialchars(typeLabel($doc['type'])) ?>
                                            </span>
                                            <i class="fa-regular fa-bookmark icon-muted"></i>
                                        </div>

                                        <h3><?= htmlspecialchars($doc['title']) ?></h3>

                                        <div class="doc-module-row">
                                            <i class="fa-solid fa-layer-group"></i>
                                            <span><?= htmlspecialchars($doc['module_name'] ?? 'Module') ?></span>
                                        </div>

                                        <div class="doc-module-row">
                                            <i class="fa-solid fa-graduation-cap"></i>
                                            <span><?= htmlspecialchars($doc['filiere_name'] ?? 'Filière') ?></span>
                                        </div>

                                        <div class="doc-card-footer">
                                            <img
                                                src="<?= htmlspecialchars($doc['author_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($doc['author_name'] ?? 'X')) ?>"
                                                alt="Auteur"
                                                class="doc-card-avatar">
                                            <div class="doc-module-row doc-module-right">
                                                <i class="fa-solid fa-clock"></i>
                                                <span><?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </a>
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

    <!-- Modal Preview -->
    <div id="previewModal" class="modal-overlay" onclick="closePreview(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="previewTitle" class="modal-title">Preview</h3>
                <button class="modal-close" onclick="closePreview()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="previewContent" class="modal-content"></div>
            <div class="modal-actions">
                <span id="previewInfo" class="doc-meta"></span>
                <a id="previewDownload" href="#" class="btn btn-primary">
                    <i class="fa-solid fa-download"></i> Télécharger
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/js/jquery.nice-select.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.nice-select-trigger').niceSelect();

            // Auto-hide success alerts
            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);
        });

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

        // Preview functions
        async function openPreview(docId) {
            try {
                const response = await fetch('/mini/preview.php?id=' + docId);
                const data = await response.json();

                if (data.error) {
                    alert('Erreur: ' + data.error);
                    return;
                }

                document.getElementById('previewTitle').textContent = data.title;
                document.getElementById('previewDownload').href = data.downloadUrl;

                const content = document.getElementById('previewContent');
                const downloadBtn = document.getElementById('previewDownload');

                if (data.previewType === 'image' && data.dataUrl) {
                    content.innerHTML = '<img src="' + data.dataUrl + '" class="modal-preview-image" alt="">';
                    downloadBtn.style.display = 'inline-flex';
                } else if (data.previewType === 'pdf') {
                    content.innerHTML = '<iframe src="' + data.viewUrl + '" class="modal-preview-pdf" type="application/pdf"></iframe>';
                    downloadBtn.style.display = 'none';
                } else {
                    content.innerHTML = `
                        <div class="modal-preview-generic">
                            <i class="fa-solid fa-file"></i>
                            <p>${data.filename}</p>
                            <p>Type: ${data.fileType}</p>
                        </div>
                    `;
                    downloadBtn.style.display = 'inline-flex';
                }

                document.getElementById('previewModal').classList.add('active');
            } catch (err) {
                alert('Erreur lors du chargement du preview');
            }
        }

        function closePreview(event) {
            if (!event || event.target.id === 'previewModal') {
                document.getElementById('previewModal').classList.remove('active');
            }
        }
    </script>
</body>

</html>
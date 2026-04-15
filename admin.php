<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

// Vérifier si l'utilisateur est admin
$currentUser = getCurrentUser($pdo);
if ($currentUser['role'] !== 'admin') {
    header('Location: /mini/dashboard.php');
    exit;
}

$success = null;
$errors = [];

// Actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Supprimer un document (BLOB - suppression uniquement en DB)
    if (isset($_POST['delete_doc'])) {
        $docId = intval($_POST['doc_id']);
        
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        if ($stmt->execute([$docId])) {
            $success = "Document supprimé avec succès.";
        } else {
            $errors[] = "Erreur lors de la suppression du document.";
        }
    }
    
    // Changer le rôle d'un utilisateur
    if (isset($_POST['toggle_role'])) {
        $userId = intval($_POST['user_id']);
        $newRole = $_POST['new_role'];
        
        if (in_array($newRole, ['etudiant', 'admin'])) {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$newRole, $userId])) {
                $success = "Rôle mis à jour avec succès.";
            } else {
                $errors[] = "Erreur lors de la mise à jour du rôle.";
            }
        }
    }
    
    // Supprimer un utilisateur (BLOB - suppression en cascade uniquement en DB)
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        
        // Ne pas permettre de supprimer son propre compte
        if ($userId === $currentUser['id']) {
            $errors[] = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            // Supprimer les documents de l'utilisateur (en BLOB, pas de fichiers à supprimer)
            $stmt = $pdo->prepare("DELETE FROM documents WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Supprimer l'utilisateur
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $success = "Utilisateur et ses documents supprimés avec succès.";
            } else {
                $errors[] = "Erreur lors de la suppression de l'utilisateur.";
            }
        }
    }
}

// Statistiques
$stats = [
    'total_docs' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'docs_today' => $pdo->query("SELECT COUNT(*) FROM documents WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];

// Récupérer tous les documents avec infos utilisateur
$docs = $pdo->query("SELECT d.*, u.name as user_name, u.email as user_email, m.name as module_name, f.name as filiere_name 
                     FROM documents d 
                     JOIN users u ON d.user_id = u.id 
                     LEFT JOIN modules m ON d.module_id = m.id
                     LEFT JOIN filieres f ON m.filiere_code = f.code
                     ORDER BY d.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les utilisateurs
$users = $pdo->query("SELECT u.*, f.name as filiere_name, 
                      (SELECT COUNT(*) FROM documents WHERE user_id = u.id) as doc_count
                      FROM users u 
                      LEFT JOIN filieres f ON u.filiere_code = f.code
                      ORDER BY u.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$userName = $currentUser['name'];
$userAvatar = $currentUser['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userName);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - XFILES</title>
    <link rel="stylesheet" href="/mini/assets/css/style.css">
    <link rel="stylesheet" href="/mini/assets/css/dashboard.css">
    <link rel="stylesheet" href="/mini/assets/css/components/buttons.css">
    <link rel="stylesheet" href="/mini/assets/css/components/modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() {
            var s = localStorage.getItem('theme');
            var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', s || sys);
        })();
    </script>
    <style>
        /* Styles spécifiques Admin */
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .admin-title { font-size: 1.75rem; font-weight: 700; }
        .admin-badge { background: var(--primary); color: var(--black); padding: 0.25rem 0.75rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--text-main); }
        .stat-label { font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem; }
        .stat-icon { width: 40px; height: 40px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; background: var(--primary-transparent); color: var(--primary); }
        
        /* Admin Sections */
        .admin-section { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .section-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .section-title { font-size: 1.125rem; font-weight: 600; }
        .section-content { padding: 1.5rem; }
        
        /* Data Table */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table th { text-align: left; padding: 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border-color); }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; }
        .data-table tbody tr { border-radius: var(--radius-md); transition: background 0.2s; }
        .data-table tbody tr:hover { background: var(--primary-transparent); }
        .data-table tbody tr:hover td:first-child { border-radius: var(--radius-md) 0 0 var(--radius-md); }
        .data-table tbody tr:hover td:last-child { border-radius: 0 var(--radius-md) var(--radius-md) 0; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        /* Badges */
        .badge { display: inline-flex; padding: 0.25rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 500; min-width: 60px; justify-content: center; }
        .badge-success { background: var(--primary-transparent); color: var(--primary); }
        .badge-warning { background: var(--secondary-transparent); color: var(--text-main); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        [data-theme="dark"] .badge-danger { background: rgba(239, 68, 68, 0.2); }
        
        /* Buttons */
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
        .btn-danger { background: rgba(239, 68, 68, 0.9); color: white; }
        .btn-danger:hover { background: #ef4444; }
        
        /* Document & User */
        .doc-title { font-weight: 500; color: var(--text-main); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; text-decoration: none; }
        .doc-title:hover { color: var(--primary); }
        .doc-meta { font-size: 0.75rem; color: var(--text-muted); }
        .user-info { display: flex; align-items: center; gap: 0.75rem; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-name { font-weight: 500; }
        .user-email { font-size: 0.75rem; color: var(--text-muted); }
        
        /* Tabs */
        .tabs { display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; }
        .tab { padding: 0.75rem 1rem; font-weight: 500; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; }
        .tab.active { color: var(--text-main); border-bottom-color: var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 0.75rem; }
            .data-table th, .data-table td { padding: 0.5rem; }
        }
    </style>
</head>
<body class="dashboard-mode">
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="dashboard-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1 class="header-title">Administration</h1>
                </div>
                <div class="header-actions">
                    
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
            
            <div class="admin-container">
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <ul class="error-list" style="margin: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-file"></i></div>
                        <div class="stat-value"><?= number_format($stats['total_docs']) ?></div>
                        <div class="stat-label">Documents totaux</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                        <div class="stat-label">Utilisateurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="stat-value"><?= number_format($stats['total_admins']) ?></div>
                        <div class="stat-label">Administrateurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
                        <div class="stat-value"><?= number_format($stats['docs_today']) ?></div>
                        <div class="stat-label">Uploads aujourd'hui</div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" onclick="showTab('docs')">Documents</div>
                    <div class="tab" onclick="showTab('users')">Utilisateurs</div>
                </div>
                
                <!-- Documents Tab -->
                <div id="docs" class="tab-content active">
                    <div class="admin-section">
                        <div class="section-header">
                            <h2 class="section-title">Tous les documents</h2>
                            <span class="badge"><?= count($docs) ?> total</span>
                        </div>
                        <div class="section-content" style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Auteur</th>
                                        <th>Filière/Module</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($docs as $doc): ?>
                                        <tr>
                                            <td>
                                                <a href="#" onclick="openPreview(<?= $doc['id'] ?>); return false;" class="doc-title" title="<?= htmlspecialchars($doc['title']) ?>">
                                                    <?= htmlspecialchars($doc['title']) ?>
                                                </a>
                                                <div class="doc-meta"><?= htmlspecialchars($doc['file']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge badge-success"><?= htmlspecialchars($doc['type']) ?></span>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($doc['user_name']) ?>" alt="" class="user-avatar">
                                                    <div>
                                                        <div class="user-name"><?= htmlspecialchars($doc['user_name']) ?></div>
                                                        <div class="user-email"><?= htmlspecialchars($doc['user_email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($doc['filiere_name'] ?? 'N/A') ?></div>
                                                <div class="doc-meta"><?= htmlspecialchars($doc['module_name'] ?? 'N/A') ?></div>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></td>
                                            <td>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Supprimer ce document définitivement ?')">
                                                    <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                                    <button type="submit" name="delete_doc" class="btn btn-danger btn-sm">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Users Tab -->
                <div id="users" class="tab-content">
                    <div class="admin-section">
                        <div class="section-header">
                            <h2 class="section-title">Tous les utilisateurs</h2>
                            <span class="badge"><?= count($users) ?> total</span>
                        </div>
                        <div class="section-content" style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Email</th>
                                        <th>Filière</th>
                                        <th>Rôle</th>
                                        <th>Documents</th>
                                        <th>Inscription</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <img src="<?= htmlspecialchars($user['avatar'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($user['name'])) ?>" alt="" class="user-avatar">
                                                    <div>
                                                        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                                        <div class="doc-meta">ID: <?= $user['id'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['filiere_name'] ?? 'Non définie') ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span class="badge badge-warning">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Étudiant</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $user['doc_count'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <?php if ($user['id'] !== $currentUser['id']): ?>
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="new_role" value="<?= $user['role'] === 'admin' ? 'etudiant' : 'admin' ?>">
                                                            <button type="submit" name="toggle_role" class="btn btn-sm" title="Changer le rôle">
                                                                <i class="fa-solid <?= $user['role'] === 'admin' ? 'fa-user' : 'fa-shield-halved' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="post" style="display: inline;" onsubmit="return confirm('Supprimer cet utilisateur et tous ses documents ?')">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="doc-meta">(Vous)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
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
            <div id="previewContent" class="modal-content">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-actions">
                <span id="previewInfo" class="doc-meta"></span>
                <a id="previewDownload" href="#" class="btn btn-primary">
                    <i class="fa-solid fa-download"></i> Télécharger
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
        }
        
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
                const footer = document.querySelector('.modal-actions');
                const downloadBtn = document.getElementById('previewDownload');
                
                if (data.previewType === 'image' && data.dataUrl) {
                    content.innerHTML = '<img src="' + data.dataUrl + '" class="modal-preview-image" alt="">';
                    downloadBtn.style.display = 'inline-flex';
                } else if (data.previewType === 'pdf') {
                    content.innerHTML = '<iframe src="' + data.viewUrl + '" class="modal-preview-pdf" type="application/pdf"></iframe>';
                    footer.style.display = 'none';
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

    <script>
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
    </script>
</body>
</html>

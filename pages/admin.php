<?php
/**
 * XFILES — Administration
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$currentUser = getCurrentUser($pdo);

$success = null;
$errors = [];

// Actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $errors[] = "Erreur de sécurité CSRF. Réessayez.";
    } else {
    // Supprimer un document (suppression fichier disque + DB)
    if (isset($_POST['delete_doc'])) {
        $docId = intval($_POST['doc_id'] ?? 0);
        error_log("ADMIN DELETE: doc_id=$docId, POST data=" . json_encode($_POST));
        
        if ($docId > 0) {
            // Récupérer le fichier avant suppression
            $stmt = $pdo->prepare("SELECT file FROM documents WHERE id = ?");
            $stmt->execute([$docId]);
            $doc = $stmt->fetch();
            
            if ($doc) {
                // Supprimer le fichier du disque
                $filePath = __DIR__ . '/../uploads/documents/' . $doc['file'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                
                // Supprimer de la base
                $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                if ($stmt->execute([$docId])) {
                    $success = "Document supprimé avec succès.";
                    error_log("ADMIN DELETE: Success - doc_id=$docId, title=" . ($doc['title'] ?? 'N/A'));
                } else {
                    $errors[] = "Erreur lors de la suppression du document.";
                    error_log("ADMIN DELETE: Failed - " . json_encode($stmt->errorInfo()));
                }
            } else {
                $errors[] = "Document non trouvé.";
            }
        } else {
            $errors[] = "ID de document invalide.";
            error_log("ADMIN DELETE: Invalid doc_id");
        }
    }

    // Approuver un document
    if (isset($_POST['approve_doc'])) {
        $docId = intval($_POST['doc_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE documents SET status = 'approuve', review_requested = 0, rejection_reason = NULL WHERE id = ?");
        if ($stmt->execute([$docId])) {
            $success = "Document approuvé.";
        } else {
            $errors[] = "Erreur lors de l'approbation.";
        }
    }

    // Rejeter un document
    if (isset($_POST['reject_doc'])) {
        $docId = intval($_POST['doc_id'] ?? 0);
        if ($docId > 0) {
            $stmt = $pdo->prepare("SELECT file FROM documents WHERE id = ?");
            $stmt->execute([$docId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                $filePath = __DIR__ . '/../uploads/documents/' . $doc['file'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
                $delStmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                if ($delStmt->execute([$docId])) {
                    $success = "Document rejeté et supprimé.";
                } else {
                    $errors[] = "Erreur lors du rejet.";
                }
            } else {
                $errors[] = "Document introuvable.";
            }
        } else {
            $errors[] = "ID de document invalide.";
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
    
    // Réinitialiser le mot de passe d'un utilisateur
    if (isset($_POST['reset_password'])) {
        $userId = intval($_POST['user_id']);
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $resetErrors = adminResetPassword($userId, $newPass, $confirmPass, $pdo);
        if (empty($resetErrors)) {
            $success = "Mot de passe réinitialisé avec succès.";
        } else {
            $errors = array_merge($errors, $resetErrors);
        }
    }

    // Supprimer un utilisateur - Les documents sont conservés (user_id devient NULL)
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);

        // Ne pas permettre de supprimer son propre compte
        if ($userId === $currentUser['id']) {
            $errors[] = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            // Détacher les documents de l'utilisateur (les garder dans la base)
            $stmt = $pdo->prepare("UPDATE documents SET user_id = NULL WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Supprimer l'utilisateur
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$userId])) {
                $success = "Utilisateur supprimé. Ses documents ont été conservés.";
            } else {
                $errors[] = "Erreur lors de la suppression de l'utilisateur.";
            }
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
    'pending_docs' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'en_attente'")->fetchColumn(),
];

// Récupérer les documents en attente de validation (y compris ceux avec demande de revue)
// Si review_requested n'existe pas encore, on ignore l'erreur
$pendingDocs = [];
$reviewRequestsCount = 0;
try {
    $pendingDocs = $pdo->query("SELECT d.*, COALESCE(d.author_name, u.name, 'Utilisateur supprimé') as user_name, u.login as user_login, m.name as module_name, f.name as filiere_name
                         FROM documents d
                         LEFT JOIN users u ON d.user_id = u.id
                         LEFT JOIN modules m ON d.module_id = m.id
                         LEFT JOIN filieres f ON m.filiere_code = f.code
                         WHERE d.status = 'en_attente'
                         ORDER BY d.review_requested DESC, d.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    $reviewRequestsCount = $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'en_attente' AND review_requested = 1")->fetchColumn();
} catch (PDOException $e) {
    // Fallback: colonne review_requested n'existe pas encore
    $pendingDocs = $pdo->query("SELECT d.*, COALESCE(d.author_name, u.name, 'Utilisateur supprimé') as user_name, u.login as user_login, m.name as module_name, f.name as filiere_name
                         FROM documents d
                         LEFT JOIN users u ON d.user_id = u.id
                         LEFT JOIN modules m ON d.module_id = m.id
                         LEFT JOIN filieres f ON m.filiere_code = f.code
                         WHERE d.status = 'en_attente'
                         ORDER BY d.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $reviewRequestsCount = 0;
}

// Récupérer tous les documents avec infos utilisateur
$docs = $pdo->query("SELECT d.*, COALESCE(d.author_name, u.name, 'Utilisateur supprimé') as user_name, u.login as user_login, m.name as module_name, f.name as filiere_name
                     FROM documents d
                     LEFT JOIN users u ON d.user_id = u.id
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
    <title>Administration — XFILES</title>
    <meta name="robots" content="noindex, nofollow" />
    <meta name="theme-color" content="#fbbf24" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/buttons.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/modal.css?v=1.1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/ui.css?v=1.1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/responsive.css?v=1.1.0">
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
        .stats-grid { display: flex; flex-direction: row; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: nowrap; }
        .stat-card { background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); flex: 1; min-width: 0; }
        @media (max-width: 1024px) {
            .stats-grid { flex-wrap: wrap; }
            .stat-card { flex: 1 1 calc(50% - 0.75rem); min-width: 200px; }
        }
        @media (max-width: 640px) {
            .stat-card { flex: 1 1 100%; }
        }
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
        .data-table th { text-align: left; padding: 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border-color); background: transparent; }
        .data-table td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; }
        .data-table tbody tr { border-radius: var(--radius-md); transition: background 0.2s; }
        .data-table tbody tr:hover { background: var(--primary-transparent); }
        .data-table tbody tr:hover td:first-child { border-radius: var(--radius-md) 0 0 var(--radius-md); }
        .data-table tbody tr:hover td:last-child { border-radius: 0 var(--radius-md) var(--radius-md) 0; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        /* Empty state */
        .empty-state { text-align: center; padding: 3rem 1.5rem; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        .empty-state p { font-size: 1rem; }
        
        /* Badges */
        .badge { display: inline-flex; padding: 0.25rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 500; min-width: 60px; justify-content: center; }
        .badge-success { background: var(--primary-transparent); color: var(--primary); }
        .badge-warning { background: var(--secondary-transparent); color: var(--text-main); }
        .badge-danger { background: var(--primary-transparent); color: var(--black); }
        [data-theme="dark"] .badge-danger { background: var(--primary-transparent); }
        
        /* Buttons */
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; border: none; cursor: pointer; border-radius: var(--radius-md); transition: all 0.2s; }
        .btn-success { background: var(--primary); color: var(--black); }
        .btn-success:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-danger { background: var(--black); color: var(--primary); }
        .btn-danger:hover { background: var(--secondary-hover); transform: translateY(-1px); }
        .btn-secondary { background: var(--bg-tertiary); color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: var(--border-color); }
        
        /* Action Buttons */
        .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
        .action-buttons .btn-sm { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; }
        
        /* File link with preview */
        .file-link { color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .file-link:hover { text-decoration: underline; }
        .preview-btn { color: var(--primary); cursor: pointer; background: var(--primary-transparent); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.75rem; margin-left: 0.5rem; border: none; }
        .preview-btn:hover { background: var(--primary); color: var(--black); }
        
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
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="dashboard-content">
            <header class="dashboard-header">
                <div class="header-left">
                    <h1 class="header-title">Administration</h1>
                </div>
                <div class="header-actions">
                    
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
            
            <div class="admin-container">
                <?php if ($success): ?>
                    <div class="alert alert-success" id="successAlert" style="color: var(--text-main);">
                        <i class="fa-solid fa-check-circle" style="color: var(--text-main);"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                    <script>
                        setTimeout(function() {
                            var el = document.getElementById('successAlert');
                            if (el) el.style.display = 'none';
                        }, 5000);
                    </script>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" id="errorAlert" style="color: var(--text-main);">
                        <i class="fa-solid fa-circle-exclamation" style="color: var(--text-main);"></i>
                        <ul class="error-list" style="margin: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <script>
                        setTimeout(function() {
                            var el = document.getElementById('errorAlert');
                            if (el) el.style.display = 'none';
                        }, 5000);
                    </script>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-file"></i></div>
                        <div class="stat-value"><?= number_format($stats['total_docs']) ?></div>
                        <div class="stat-label">Documents totaux</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                        <div class="stat-label">Utilisateurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="stat-value"><?= number_format($stats['total_admins']) ?></div>
                        <div class="stat-label">Administrateurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="stat-value"><?= number_format($stats['docs_today']) ?></div>
                        <div class="stat-label">Uploads aujourd'hui</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div class="stat-value"><?= number_format($stats['pending_docs']) ?></div>
                        <div class="stat-label">Documents en attente</div>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" data-tab="pending">
                        En attente 
                        <?php if ($stats['pending_docs'] > 0): ?>
                            <span class="badge danger"><?= $stats['pending_docs'] ?></span>
                        <?php endif; ?>
                        <?php if ($reviewRequestsCount > 0): ?>
                            <span style="color: var(--text-main); font-size: 0.75rem; margin-left: 0.25rem;">(<?= $reviewRequestsCount ?> revue<?= $reviewRequestsCount > 1 ? 's' : '' ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="tab" data-tab="docs">Documents</div>
                    <div class="tab" data-tab="users">Utilisateurs</div>
                </div>

                <!-- Pending Documents Tab -->
                <div id="pending" class="tab-content active">
                    <div class="admin-section">
                        <div class="section-header">
                            <h2 class="section-title">Documents en attente de validation</h2>
                            <span class="badge <?= $stats['pending_docs'] > 0 ? 'danger' : '' ?>"><?= count($pendingDocs) ?> en attente</span>
                            <?php if ($reviewRequestsCount > 0): ?>
                                <span class="badge" style="background: var(--primary-transparent); color: var(--text-main); margin-left: 0.5rem;"><i class="fa-solid fa-flag"></i> <?= $reviewRequestsCount ?> demande<?= $reviewRequestsCount > 1 ? 's' : '' ?> de revue</span>
                            <?php endif; ?>
                        </div>
                        <div class="section-content" style="overflow-x: auto;">
                            <?php if (empty($pendingDocs)): ?>
                                <div class="empty-state" style="text-align: center; padding: 3rem;">
                                    <i class="fa-solid fa-check-circle" style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;"></i>
                                    <p>Aucun document en attente de validation.</p>
                                </div>
                            <?php else: ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fichier</th>
                                            <th>Titre</th>
                                            <th>Utilisateur</th>
                                            <th>Type</th>
                                            <th>Module</th>
                                            <th>Raison</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingDocs as $doc): ?>
                                            <tr>
                                                <td><?= $doc['id'] ?></td>
                                                <td>
                                                    <div style="display: flex; gap: 0.5rem;">
                                                        <a href="<?= BASE_URL ?>pages/view.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="preview-btn" title="Voir">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>pages/download.php?id=<?= (int)$doc['id'] ?>" class="preview-btn" title="Télécharger" style="background: var(--primary); color: var(--black);">
                                                            <i class="fa-solid fa-download"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td><?= e($doc['title']) ?></td>
                                                <td><?= e($doc['user_name']) ?> (@<?= e($doc['user_login']) ?>)</td>
                                                <td><?= e($doc['type']) ?></td>
                                                <td><?= e($doc['module_name'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php if (isset($doc['review_requested']) && $doc['review_requested']): ?>
                                                        <span class="badge" style="background: var(--primary-transparent); color: var(--text-main);"><i class="fa-solid fa-flag"></i> Revue demandée</span>
                                                        <?php if (!empty($doc['rejection_reason'])): ?>
                                                            <br><small style="color: var(--text-muted);">Rejet: <?= e($doc['rejection_reason']) ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge warning"><?= e($doc['rejection_reason'] ?? 'En attente') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></td>
                                                <td>
                                                    <div class="action-buttons" style="display: flex; gap: 0.5rem; align-items: center;">
                                                        <form method="POST" style="display:inline;">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                                                            <button type="submit" name="approve_doc" class="btn btn-success btn-sm" title="Approuver">
                                                                <i class="fa-solid fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form id="rejectForm<?= (int)$doc['id'] ?>" method="POST" style="display:inline;">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                                                            <input type="hidden" name="rejection_reason" value="Document rejeté par l'administrateur">
                                                            <input type="hidden" name="reject_doc" value="1">
                                                        </form>
                                                        <button type="button" onclick="confirmRejectDoc(<?= (int)$doc['id'] ?>)" class="btn btn-danger btn-sm" title="Rejeter">
                                                            <i class="fa-solid fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Documents Tab -->
                <div id="docs" class="tab-content">
                    <div class="admin-section">
                        <div class="section-header">
                            <h2 class="section-title">Tous les documents</h2>
                            <span class="badge"><?= count($docs) ?> total</span>
                        </div>
                        <?php if (empty($docs)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-folder-open"></i>
                            <p>Aucun document</p>
                        </div>
                        <?php else: ?>
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
                                            </td>
                                            <td>
                                                <span class="badge badge-success"><?= htmlspecialchars($doc['type']) ?></span>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($doc['user_name']) ?>" alt="" class="user-avatar">
                                                    <div>
                                                        <div class="user-name"><?= htmlspecialchars($doc['user_name']) ?></div>
                                                        <div class="user-email">@<?= htmlspecialchars($doc['user_login']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($doc['filiere_name'] ?? 'N/A') ?></div>
                                                <div class="doc-meta"><?= htmlspecialchars($doc['module_name'] ?? 'N/A') ?></div>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></td>
                                            <td>
                                                <form id="delForm<?= (int)$doc['id'] ?>" method="POST" style="display:inline;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                                                    <input type="hidden" name="delete_doc" value="1">
                                                </form>
                                                <button type="button" onclick="confirmDeleteDoc(<?= (int)$doc['id'] ?>)" class="btn btn-sm" style="background: var(--primary); color: var(--black);">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Users Tab -->
                <div id="users" class="tab-content">
                    <div class="admin-section">
                        <div class="section-header">
                            <h2 class="section-title">Tous les utilisateurs</h2>
                            <span class="badge"><?= count($users) ?> total</span>
                        </div>
                        <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-users"></i>
                            <p>Aucun utilisateur</p>
                        </div>
                        <?php else: ?>
                        <div class="section-content" style="overflow-x: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Login</th>
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
                                            <td>@<?= htmlspecialchars($user['login']) ?></td>
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
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="new_role" value="<?= $user['role'] === 'admin' ? 'etudiant' : 'admin' ?>">
                                                            <button type="submit" name="toggle_role" class="btn btn-sm" title="Changer le rôle">
                                                                <i class="fa-solid <?= $user['role'] === 'admin' ? 'fa-user' : 'fa-shield-halved' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm" title="Réinitialiser le mot de passe" onclick='showResetModal(<?= (int)$user['id'] ?>, <?= json_encode($user['login']) ?>)'>
                                                            <i class="fa-solid fa-key"></i>
                                                        </button>
                                                        <form id="userDelForm<?= $user['id'] ?>" method="post" style="display: inline;">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <input type="hidden" name="delete_user" value="1">
                                                        </form>
                                                        <button type="button" onclick="confirmDeleteUser(<?= $user['id'] ?>)" class="btn btn-danger btn-sm" title="Supprimer">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Message (erreurs/succès) -->
    <div id="messageModal" class="modal-overlay" style="display: none; z-index: 9999;" onclick="closeMessageModal(event)">
        <div class="modal-container" style="max-width: 400px; text-align: center;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div id="messageIcon" style="font-size: 3rem; margin-bottom: 0.5rem;"></div>
            </div>
            <div class="modal-content">
                <h3 id="messageTitle" style="margin-bottom: 1rem;"></h3>
                <p id="messageText" style="color: var(--text-muted); margin-bottom: 1.5rem;"></p>
                <button type="button" class="btn btn-primary" onclick="closeMessageModal()" style="min-width: 100px;">OK</button>
            </div>
        </div>
    </div>

    <!-- Modal Confirm -->
    <div id="confirmModal" class="modal-overlay" style="display: none; z-index: 9999;" onclick="closeConfirmModal(event)">
        <div class="modal-container" style="max-width: 400px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="confirmTitle" class="modal-title">Confirmation</h3>
                <button class="modal-close" onclick="closeConfirmModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-content">
                <p id="confirmText" style="margin-bottom: 1.5rem; color: var(--text-muted);"></p>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Annuler</button>
                    <button type="button" id="confirmBtn" class="btn btn-primary">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Approve Document -->
    <div id="approveModal" class="modal-overlay" style="display: none;" onclick="closeApproveModal(event)">
        <div class="modal-container" style="max-width: 400px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-check-circle" style="color: var(--success-color);"></i> Approuver le document</h3>
                <button class="modal-close" onclick="closeApproveModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-content">
                <form method="POST" id="approveForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="doc_id" id="approveDocId">
                    <p style="margin-bottom: 1rem;">Document: <strong id="approveDocName"></strong></p>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Le document sera approuvé et visible par tous les utilisateurs.</p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeApproveModal()">Annuler</button>
                        <button type="submit" name="approve_doc" class="btn btn-success">
                            <i class="fa-solid fa-check"></i> Approuver
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Modal Reset Password -->
    <div id="resetModal" class="modal-overlay" style="display: none;" onclick="closeResetModal(event)">
        <div class="modal-container" style="max-width: 400px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-key"></i> Réinitialiser le mot de passe</h3>
                <button class="modal-close" onclick="closeResetModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-content">
                <form method="POST" id="resetForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" id="resetUserId">
                    <p style="margin-bottom: 1rem;">Utilisateur: <strong id="resetUserLogin"></strong></p>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" placeholder="8 caractères min." required minlength="8">
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Confirmer</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Répéter le mot de passe" required minlength="8">
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-check"></i> Réinitialiser
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Reject Document -->
    <div id="rejectModal" class="modal-overlay" style="display: none;" onclick="closeRejectModal(event)">
        <div class="modal-container" style="max-width: 450px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fa-solid fa-times-circle"></i> Rejeter le document</h3>
                <button class="modal-close" onclick="closeRejectModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-content">
                <form method="POST" id="rejectForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="doc_id" id="rejectDocId">
                    <div class="form-group">
                        <label class="form-label">Document</label>
                        <p id="rejectDocName" class="form-value" style="font-weight: 500; color: var(--text-color);"></p>
                    </div>
                    <div class="form-group">
                        <label for="rejectReason" class="form-label">Raison du rejet</label>
                        <textarea name="reject_reason" id="rejectReason" class="form-input" rows="3" placeholder="Indiquez la raison du rejet..." required></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Annuler</button>
                        <button type="submit" name="reject_doc" class="btn btn-danger">
                            <i class="fa-solid fa-times"></i> Rejeter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Ensure buttons in tables are clickable */
        .data-table button,
        .data-table form,
        .action-buttons button {
            pointer-events: auto !important;
            position: relative;
            z-index: 10;
        }
        .data-table td {
            position: relative;
        }
    </style>
    <script src="<?= BASE_URL ?>js/modal.js"></script>
    <script>
        // Wrapper pour XModal avec fallback
        function showConfirm(message, onConfirm, title) {
            if (typeof XModal !== 'undefined' && XModal.confirm) {
                XModal.confirm(message, onConfirm, null, title || 'Confirmer');
            } else {
                if (confirm(message)) {
                    onConfirm();
                }
            }
        }

        // Admin confirmation functions
        function confirmDeleteDoc(docId) {
            showConfirm('Supprimer ce document définitivement ?', function() {
                document.getElementById('delForm' + docId).submit();
            }, 'Confirmer');
        }

        function confirmRejectDoc(docId) {
            showConfirm('Rejeter ce document ?', function() {
                document.getElementById('rejectForm' + docId).submit();
            }, 'Confirmer');
        }

        function confirmDeleteUser(userId) {
            showConfirm('Supprimer cet utilisateur et tous ses documents ?', function() {
                document.getElementById('userDelForm' + userId).submit();
            }, 'Confirmer');
        }

        function showTab(tab, clickedElement) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));

            // Show selected tab
            document.getElementById(tab).classList.add('active');
            if (clickedElement) {
                clickedElement.classList.add('active');
            } else {
                document.querySelector('.tab[data-tab="' + tab + '"]').classList.add('active');
            }

            // Save active tab to localStorage
            localStorage.setItem('adminActiveTab', tab);
        }

        // Add event listeners to tabs on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            // Restore active tab from localStorage
            var savedTab = localStorage.getItem('adminActiveTab');
            if (savedTab) {
                showTab(savedTab);
            }

            document.querySelectorAll('.tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var tabId = this.getAttribute('data-tab');
                    showTab(tabId, this);
                });
            });
        });

        function closeMessageModal(event) {
            if (!event || event.target.id === 'messageModal') {
                document.getElementById('messageModal').style.display = 'none';
            }
        }

        // Approve modal functions
        function showApproveModal(docId, docName) {
            document.getElementById('approveDocId').value = docId;
            document.getElementById('approveDocName').textContent = docName;
            document.getElementById('approveModal').style.display = 'flex';
        }

        function closeApproveModal(event) {
            if (!event || event.target.id === 'approveModal') {
                document.getElementById('approveModal').style.display = 'none';
            }
        }
        
        function closePreview(event) {
            if (!event || event.target.id === 'previewModal') {
                document.getElementById('previewModal').classList.remove('active');
                document.getElementById('previewFrame').src = '';
            }
        }

        // Reset password modal functions
        function showResetModal(userId, userLogin) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUserLogin').textContent = userLogin;
            document.getElementById('resetModal').style.display = 'flex';
        }

        function closeResetModal(event) {
            if (!event || event.target.id === 'resetModal') {
                document.getElementById('resetModal').style.display = 'none';
            }
        }

        // Reject document modal functions
        function showRejectModal(docId, docName) {
            document.getElementById('rejectDocId').value = docId;
            document.getElementById('rejectDocName').textContent = docName;
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal(event) {
            if (!event || event.target.id === 'rejectModal') {
                document.getElementById('rejectModal').style.display = 'none';
            }
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
    </script>
</body>
</html>

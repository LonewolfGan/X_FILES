<?php
/**
 * XFILES — Demande de revue après rejet d'upload
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];

$docId = intval($_GET['doc_id'] ?? 0);
$errors = [];
$success = null;

if ($docId === 0) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs');
    exit;
}

// Vérifier que le document appartient à l'utilisateur et est rejeté
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND user_id = ? AND status = 'rejete'");
$stmt->execute([$docId, $userId]);
$doc = $stmt->fetch();

if (!$doc) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs');
    exit;
}

// Traiter la demande de revue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_review'])) {
    if (!csrfCheck()) {
        $errors[] = "Erreur de sécurité CSRF. Réessayez.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE documents SET review_requested = 1, status = 'en_attente' WHERE id = ?");
            if ($stmt->execute([$docId])) {
                $success = "Votre demande de revue a été envoyée à l'administrateur.";
            } else {
                $errors[] = "Erreur lors de l'envoi de la demande.";
            }
        } catch (PDOException $e) {
            $errors[] = "Fonctionnalité indisponible.";
        }
    }
}

// Refus de demande de revue: suppression définitive du document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decline_review'])) {
    if (!csrfCheck()) {
        $errors[] = "Erreur de sécurité CSRF. Réessayez.";
    } else {
        $pdo->beginTransaction();
        try {
            $delStmt = $pdo->prepare("DELETE FROM documents WHERE id = ? AND user_id = ? AND status = 'rejete'");
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
            $errors[] = "Impossible de supprimer le document pour le moment.";
        }
    }
}

$pageTitle = 'Demande de revue - XFILES';
$pageCss = [
    BASE_URL . 'css/dashboard.css',
    BASE_URL . 'css/buttons.css',
    BASE_URL . 'css/forms.css',
    BASE_URL . 'css/modal.css',
    BASE_URL . 'css/responsive.css',
];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <main class="dashboard-content review-page">
        <style>
            .review-page {
                display: flex;
                flex-direction: column;
                align-items: center;
                min-height: calc(100vh - 80px);
                padding: 2rem;
            }
            
            .review-container {
                width: 100%;
                max-width: 640px;
                margin-top: 2rem;
            }
            
            .review-header {
                text-align: center;
                margin-bottom: 2.5rem;
            }
            
            .review-header h1 {
                font-family: var(--font-heading);
                font-size: 1.75rem;
                font-weight: 700;
                color: var(--text-main);
                margin-bottom: 0.5rem;
            }
            
            .review-header p {
                color: var(--text-muted);
                font-size: 0.95rem;
            }
            
            .review-card {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: var(--radius-lg);
                padding: 2rem;
                margin-bottom: 1.5rem;
            }
            
            .review-status {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
                margin-bottom: 2rem;
                padding-bottom: 1.5rem;
                border-bottom: 1px solid var(--border-color);
            }
            
            .review-status-icon {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: var(--primary-transparent);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .review-status-icon i {
                color: var(--primary);
                font-size: 1.25rem;
            }
            
            .review-status-text {
                text-align: left;
            }
            
            .review-status-text strong {
                display: block;
                color: var(--text-main);
                font-size: 1rem;
            }
            
            .review-status-text span {
                color: var(--text-muted);
                font-size: 0.85rem;
            }
            
            .review-section {
                margin-bottom: 1.5rem;
            }
            
            .review-section:last-child {
                margin-bottom: 0;
            }
            
            .review-label {
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
                margin-bottom: 0.5rem;
            }
            
            .review-value {
                font-size: 0.95rem;
                color: var(--text-main);
                font-weight: 500;
            }
            
            .review-reason {
                background: var(--bg-color);
                border: 1px solid var(--border-color);
                border-radius: var(--radius-md);
                padding: 1rem 1.25rem;
                font-family: var(--font-mono, monospace);
                font-size: 0.9rem;
                color: var(--text-main);
                line-height: 1.5;
            }
            
            .review-actions-card {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-radius: var(--radius-lg);
                padding: 1.5rem;
            }
            
            .review-actions-title {
                font-size: 1rem;
                font-weight: 600;
                color: var(--text-main);
                margin-bottom: 0.5rem;
                text-align: center;
            }
            
            .review-actions-desc {
                font-size: 0.9rem;
                color: var(--text-muted);
                text-align: center;
                margin-bottom: 1.5rem;
            }
            
            .review-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
            }
            
            .review-buttons form {
                display: inline;
            }
            
            .review-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.875rem 1.5rem;
                border-radius: var(--radius-md);
                font-weight: 600;
                font-size: 0.9rem;
                cursor: pointer;
                transition: all 0.2s ease;
                border: none;
            }
            
            .review-btn-primary {
                background: var(--primary);
                color: var(--black);
            }
            
            .review-btn-primary:hover {
                background: var(--primary-hover);
                transform: translateY(-1px);
            }
            
            .review-btn-secondary {
                background: transparent;
                color: var(--text-muted);
                border: 1px solid var(--border-color);
            }
            
            .review-btn-secondary:hover {
                border-color: var(--text-main);
                color: var(--text-main);
            }
            
            .review-back {
                margin-top: 2rem;
                text-align: center;
            }
            
            .review-back a {
                color: var(--text-muted);
                font-size: 0.9rem;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                transition: color 0.2s;
            }
            
            .review-back a:hover {
                color: var(--primary);
            }
            
            .review-success {
                text-align: center;
                padding: 3rem 2rem;
            }
            
            .review-success-icon {
                width: 64px;
                height: 64px;
                background: var(--primary-transparent);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
            }
            
            .review-success-icon i {
                color: var(--primary);
                font-size: 1.5rem;
            }
            
            .review-success h2 {
                font-family: var(--font-heading);
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .review-success p {
                color: var(--text-muted);
                margin-bottom: 1.5rem;
            }
            
            @media (max-width: 640px) {
                .review-page {
                    padding: 1rem;
                }
                
                .review-card,
                .review-actions-card {
                    padding: 1.5rem;
                }
                
                .review-buttons {
                    flex-direction: column;
                }
                
                .review-btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>

        <div class="review-container">
            
            <?php if ($success): ?>
                <div class="review-card review-success">
                    <div class="review-success-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2>Demande envoyée</h2>
                    <p><?= htmlspecialchars($success) ?></p>
                    <a href="<?= BASE_URL ?>pages/dashboard.php?view=my-docs" class="review-btn review-btn-primary">
                        <i class="fa-solid fa-arrow-left"></i> Retour à mes documents
                    </a>
                </div>
            <?php else: ?>
                
                <div class="review-header">
                    <h1>Demande de revue</h1>
                    <p>Document rejeté — Actions disponibles</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                        <?php foreach ($errors as $err): ?>
                            <p><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="review-card">
                    <div class="review-status">
                        <div class="review-status-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="review-status-text">
                            <strong>Document rejeté</strong>
                        </div>
                    </div>
                    
                    <div class="review-section">
                        <div class="review-label">Raison du rejet</div>
                        <div class="review-reason">
                            <?= nl2br(htmlspecialchars($doc['rejection_reason'] ?? 'Raison non spécifiée')) ?>
                        </div>
                    </div>
                </div>

                <div class="review-actions-card">
                    <div class="review-actions-title">Que souhaitez-vous faire ?</div>
                    <div class="review-actions-desc">
                        Vous pouvez demander une revue manuelle ou supprimer définitivement ce document.
                    </div>
                    
                    <div class="review-buttons">
                        <form method="POST" data-confirm="Le document sera supprimé définitivement. Continuer ?">
                            <?= csrfField() ?>
                            <button type="submit" name="decline_review" class="review-btn review-btn-secondary">
                                <i class="fa-solid fa-trash"></i> Supprimer
                            </button>
                        </form>
                        <form method="POST">
                            <?= csrfField() ?>
                            <button type="submit" name="request_review" class="review-btn review-btn-primary">
                                <i class="fa-solid fa-flag"></i> Demander une revue
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="review-back">
                    <a href="<?= BASE_URL ?>pages/dashboard.php?view=my-docs">
                        <i class="fa-solid fa-arrow-left"></i> Retour sans action
                    </a>
                </div>
                
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="<?= BASE_URL ?>js/modal.js"></script>
</body>
</html>

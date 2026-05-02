<?php
/**
 * XFILES — Demande de revue après rejet d'upload
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser($pdo);
$userId      = $currentUser['id'];

$docId  = intval($_GET['doc_id'] ?? 0);
$errors = [];
$success = null;

if ($docId === 0) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ? AND user_id = ? AND status = 'rejete'");
$stmt->execute([$docId, $userId]);
$doc = $stmt->fetch();

if (!$doc) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php?view=my-docs');
    exit;
}

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

$bodyClass = 'dashboard-mode';
$pageTitle = 'Demande de revue - XFILES';
$pageCss   = [
    BASE_URL . 'css/dashboard.css',
    BASE_URL . 'css/buttons.css',
    BASE_URL . 'css/forms.css',
    BASE_URL . 'css/modal.css',
    BASE_URL . 'css/review.css',
    BASE_URL . 'css/responsive.css',
];
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content review-page">
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
                    <div class="alert alert-error mb-3">
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
<script src="<?= BASE_URL ?>js/dashboard.js"></script>
</body>
</html>

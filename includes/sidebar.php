<?php
/**
 * XFILES — Sidebar dashboard partagée
 */
$currentPage   = basename($_SERVER['PHP_SELF']);
$sidebarView   = $_GET['view'] ?? '';
$sidebarTypes  = isset($_GET['types']) ? explode(',', $_GET['types']) : [];

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$_sc = (isset($pdo) && $pdo instanceof PDO) ? getSidebarTypeCounts($pdo) : [];
?>
<aside class="dashboard-sidebar">
    <a href="<?= BASE_URL ?>index.php" class="sidebar-logo">
        <i class="fa-solid fa-graduation-cap"></i>
        <span class="brand-text">XFILES</span>
    </a>

    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>pages/dashboard.php" class="nav-item <?= ($currentPage === 'dashboard.php' && empty($sidebarView) && empty($sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?view=my-docs" class="nav-item <?= ($sidebarView === 'my-docs') ? 'active' : '' ?>">
            <i class="fa-solid fa-folder-open"></i>
            <span>Mes documents</span>
        </a>
        <?php if ($isAdmin): ?>
            <a href="<?= BASE_URL ?>pages/admin.php" class="nav-item <?= ($currentPage === 'admin.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Administration</span>
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>pages/dashboard.php?view=settings" class="nav-item <?= ($sidebarView === 'settings') ? 'active' : '' ?>">
            <i class="fa-solid fa-gear"></i>
            <span>Paramètres</span>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=cours" class="nav-item <?= (in_array('cours', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-book"></i>
            <span>Cours</span>
            <?php if (!empty($_sc['cours'])): ?><em class="nav-count"><?= $_sc['cours'] ?></em><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=td" class="nav-item <?= (in_array('td', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i>
            <span>Travaux Dirigés</span>
            <?php if (!empty($_sc['td'])): ?><em class="nav-count"><?= $_sc['td'] ?></em><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=tp" class="nav-item <?= (in_array('tp', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-flask"></i>
            <span>Travaux Pratiques</span>
            <?php if (!empty($_sc['tp'])): ?><em class="nav-count"><?= $_sc['tp'] ?></em><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=examen" class="nav-item <?= (in_array('examen', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-file-circle-question"></i>
            <span>Examens</span>
            <?php if (!empty($_sc['examen'])): ?><em class="nav-count"><?= $_sc['examen'] ?></em><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=resume" class="nav-item <?= (in_array('resume', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-note-sticky"></i>
            <span>Résumés</span>
            <?php if (!empty($_sc['resume'])): ?><em class="nav-count"><?= $_sc['resume'] ?></em><?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>pages/logout.php" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Quitter</span>
        </a>
    </div>
</aside>

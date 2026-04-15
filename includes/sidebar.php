<?php
// Démarrer session si pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarView = $_GET['view'] ?? '';
$sidebarTypes = isset($_GET['types']) ? explode(',', $_GET['types']) : [];

// Vérifier si l'utilisateur est admin (si db disponible)
$isAdmin = false;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userRole = $stmt->fetchColumn();
        $isAdmin = ($userRole === 'admin');
    } catch (Exception $e) {
        $isAdmin = false;
    }
}
?>
<aside class="dashboard-sidebar">
    <a href="/mini/index.php" class="sidebar-logo">
        <i class="fa-solid fa-graduation-cap"></i>
        <span class="brand-text">XFILES</span>
    </a>

    <nav class="sidebar-nav">
        <a href="/mini/dashboard.php" class="nav-item <?= ($currentPage === 'dashboard.php' && empty($sidebarView) && empty($sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i>
            <span>Dashboard</span>
        </a>
        <?php if ($isAdmin): ?>
            <a href="/mini/admin.php" class="nav-item <?= ($currentPage === 'admin.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Administration</span>
            </a>
        <?php endif; ?>
        <a href="/mini/dashboard.php?view=settings" class="nav-item <?= ($sidebarView === 'settings') ? 'active' : '' ?>">
            <i class="fa-solid fa-gear"></i>
            <span>Paramètres</span>
        </a>
        <a href="/mini/dashboard.php?types=cours" class="nav-item <?= (in_array('cours', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-book"></i>
            <span>Cours</span>
        </a>
        <a href="/mini/dashboard.php?types=td" class="nav-item <?= (in_array('td', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i>
            <span>Travaux Dirigés</span>
        </a>
        <a href="/mini/dashboard.php?types=tp" class="nav-item <?= (in_array('tp', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-flask"></i>
            <span>Travaux Pratiques</span>
        </a>
        <a href="/mini/dashboard.php?types=examen" class="nav-item <?= (in_array('examen', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-file-circle-question"></i>
            <span>Examens</span>
        </a>
        <a href="/mini/dashboard.php?types=resume" class="nav-item <?= (in_array('resume', $sidebarTypes)) ? 'active' : '' ?>">
            <i class="fa-solid fa-note-sticky"></i>
            <span>Résumés</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/mini/logout.php" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Quitter</span>
        </a>
    </div>
</aside>
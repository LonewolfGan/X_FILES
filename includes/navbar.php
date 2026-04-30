<?php
/**
 * XFILES — Navbar publique (page d'accueil)
 */
?>
<header>
  <div class="container">
    <nav class="navbar">
      <a href="<?= BASE_URL ?>index.php" class="brand">
        <i class="fa-solid fa-graduation-cap"></i> XFILES
      </a>

      <div class="nav-menu" id="nav-menu">
        <div class="nav-links">
          <a href="<?= BASE_URL ?>pages/dashboard.php">Explorer</a>
          <a href="<?= BASE_URL ?>index.php#ressources">Filières</a>
          <a href="<?= BASE_URL ?>index.php#testimonials">À propos</a>
        </div>
        <div class="nav-actions">
          <button id="theme-toggle" class="btn btn-ghost">
            <i class="fa-solid fa-moon"></i> Mode sombre
          </button>
          <?php if (isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-primary">Go To Dashboard</a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>pages/register.php" class="btn btn-primary">Commencer</a>
          <?php endif; ?>
        </div>
      </div>

      <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Menu">
        <i class="fa-solid fa-bars"></i>
      </button>
    </nav>
  </div>
</header>

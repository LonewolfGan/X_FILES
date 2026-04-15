<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

/** @var PDO $pdo  */
$filieres = $pdo->query('SELECT * FROM filieres ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr" data-theme="light">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $pageTitle ?? 'XFILES - L\'Intelligence Collective' ?></title>
  <script>
    (function() {
      const saved = localStorage.getItem('theme');
      const sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', saved || sys);
    })();
  </script>
  <script src="/mini/assets/js/index.js?v=<?= time() ?>" defer></script>
  <link rel="stylesheet" href="/mini/assets/css/style.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="/mini/assets/css/index.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="/mini/assets/css/components/buttons.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="/mini/assets/css/components/cards.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="/mini/assets/css/components/forms.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="/mini/assets/css/components/footer.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
  <header>
    <div class="container">
      <nav class="navbar">
        <a href="/mini/index.php" class="brand">
          <i class="fa-solid fa-graduation-cap"></i> XFILES
        </a>

        <div class="nav-menu" id="nav-menu">
          <div class="nav-links">
            <a href="/mini/dashboard.php">Explorer</a>
            <a href="/mini/index.php#ressources">Filières</a>
            <a href="/mini/index.php#testimonials">À propos</a>
          </div>
          <div class="nav-actions">
            <button id="theme-toggle" class="btn btn-ghost">
              <i class="fa-solid fa-moon"></i> Mode sombre
            </button>
            <?php if (isLoggedIn()): ?>
              <a href="/mini/dashboard.php" class="btn btn-primary">Go To Dashboard</a>
            <?php else: ?>
              <a href="/mini/register.php" class="btn btn-primary">Commencer</a>
            <?php endif; ?>
          </div>
        </div>

        <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Menu">
          <i class="fa-solid fa-bars"></i>
        </button>
      </nav>
    </div>
  </header>

  <header class="hero-header">
    <div class="container">
      <div class="hero-section">
        <div class="hero-content">
          <h1 class="hero-title">Le savoir à la portée de tous.</h1>
          <p class="hero-subtitle">
            Partagez, découvrez et améliorez des ressources académiques au sein
            d'une communauté d'étudiants.
          </p>
          <div class="hero-actions">
            <a href="/mini/register.php" class="btn btn-primary btn-lg">
              <i class="fa-solid fa-cloud-arrow-up"></i> Commencer
            </a>
            <a href="/mini/dashboard.php" class="btn btn-secondary btn-lg">
              <i class="fa-solid fa-magnifying-glass"></i> Explorer les Ressources
            </a>
          </div>
        </div>
        <div class="hero-visual">
          <img src="/mini/assets/img/Team-pana.png" alt="Illustration collaborative" class="hero-image-animate" />
        </div>
      </div>
    </div>
  </header>

  <main>
    <section id="ressources" class="container section-ressources">
      <h2 class="section-title">Nos Ressources Académiques</h2>
      <div class="ressources-grid">
        <?php
        $categories = [
          ['icon' => 'fa-laptop-code',    'title' => 'Informatique',      'link' => '/mini/dashboard.php?filiere=IL'],
          ['icon' => 'fa-calculator',     'title' => 'Mathématiques',     'link' => '/mini/dashboard.php?filiere=SDBDIA'],
          ['icon' => 'fa-briefcase',      'title' => 'Management',        'link' => '/mini/dashboard.php?filiere=MGSI'],
          ['icon' => 'fa-flask',          'title' => 'Sciences',          'link' => '/mini/dashboard.php?filiere=SITCN'],
          ['icon' => 'fa-scale-balanced', 'title' => 'Droit & Sciences Po', 'link' => '/mini/dashboard.php'],
          ['icon' => 'fa-language',       'title' => 'Langues & Lettres', 'link' => '/mini/dashboard.php'],
        ];
        foreach ($categories as $cat): ?>
          <div class="card ressource-card">
            <div class="ressource-icon"><i class="fa-solid <?= $cat['icon'] ?>"></i></div>
            <h3 class="ressource-title"><?= htmlspecialchars($cat['title']) ?></h3>
            <a href="<?= htmlspecialchars($cat['link']) ?>" class="btn btn-outline">Explorer <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section id="testimonials" class="container section-testimonials">
      <h2 class="section-title">Ils réussissent grâce à XFILES</h2>
      <div class="testimonials-grid">
        <?php
        $testimonials = [
          ['name' => 'Lucas M.',  'role' => 'Étudiant en Master Informatique', 'text' => 'Grâce aux fiches de révision partagées, j\'ai pu valider mon semestre avec mention.'],
          ['name' => 'Sarah B.',  'role' => 'Étudiante en L3 Mathématiques',   'text' => 'Les annales et corrections détaillées m\'ont sauvé la vie.'],
          ['name' => 'Emma D.',   'role' => 'Étudiante en Droit',              'text' => 'Partager mes propres synthèses m\'a aidé à mieux mémoriser mes cours.'],
        ];
        foreach ($testimonials as $t):
          $initials = urlencode($t['name']);
        ?>
          <div class="card testimonial-card">
            <div class="testimonial-content">
              <i class="fa-solid fa-quote-left quote-icon"></i>
              <p>"<?= htmlspecialchars($t['text']) ?>"</p>
            </div>
            <div class="testimonial-author">
              <img src="https://ui-avatars.com/api/?name=<?= $initials ?>&background=fbbf24&color=000" alt="<?= htmlspecialchars($t['name']) ?>" class="author-avatar">
              <div class="author-info">
                <h4><?= htmlspecialchars($t['name']) ?></h4>
                <span><?= htmlspecialchars($t['role']) ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="/mini/index.php" class="brand">
            <i class="fa-solid fa-graduation-cap"></i> XFILES
          </a>
          <p class="footer-desc">
            Partagez, découvrez et améliorez des ressources académiques au sein d'une communauté d'étudiants.
          </p>
          <div class="social-links">
            <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
            <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin"></i></a>
            <a href="#" aria-label="GitHub"><i class="fa-brands fa-github"></i></a>
          </div>
        </div>

        <div class="footer-links">
          <h4>Ressources</h4>
          <ul>
            <li><a href="/mini/dashboard.php?type=cours">Cours & Synthèses</a></li>
            <li><a href="/mini/dashboard.php?type=examen">Annales d'examens</a></li>
            <li><a href="/mini/dashboard.php?type=tp">Tutoriels</a></li>
            <li><a href="/mini/dashboard.php">Filières</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Communauté</h4>
          <ul>
            <li><a href="/mini/dashboard.php?sort=recent">Classements</a></li>
            <li><a href="/mini/dashboard.php">Top Contributeurs</a></li>
            <li><a href="/mini/dashboard.php">Forum d'entraide</a></li>
            <li><a href="#">Règles</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>XFILES</h4>
          <ul>
            <li><a href="/mini/index.php#about">À propos</a></li>
            <li><a href="mailto:contact@xfiles.local">Nous contacter</a></li>
            <li><a href="#">Mentions légales</a></li>
            <li><a href="#">Confidentialité</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 XFILES — L'Intelligence Collective. Tous droits réservés.</p>
      </div>
    </div>
  </footer>
</body>

</html>
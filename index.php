<?php
/**
 * XFILES — Page d'accueil
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$filieres = $pdo->query('SELECT * FROM filieres ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle       = 'XFILES — Partage de Ressources Académiques Gratuit';
$pageDescription = 'XFILES est la plateforme gratuite de partage de ressources académiques pour étudiants. Accédez à des cours, TD, TP, annales d\'examens et synthèses dans toutes les filières.';
$pageCss = [
    BASE_URL . 'css/index.css',
    BASE_URL . 'css/buttons.css',
    BASE_URL . 'css/cards.css',
    BASE_URL . 'css/forms.css',
    BASE_URL . 'css/footer.css',
    BASE_URL . 'css/responsive.css',
];
include __DIR__ . '/includes/header.php';
?>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "EducationalOrganization",
    "name": "XFILES",
    "description": "Plateforme gratuite de partage de ressources académiques pour étudiants : cours, TD, TP et annales d'examens.",
    "url": "https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'xfiles.replit.app') ?>",
    "logo": "https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'xfiles.replit.app') ?>/images/Team-pana.png",
    "sameAs": [],
    "offers": {
      "@type": "Offer",
      "price": "0",
      "priceCurrency": "EUR",
      "description": "Accès gratuit à des milliers de ressources académiques"
    }
  }
  </script>
  <script src="<?= BASE_URL ?>js/index.js?v=1.0.0" defer></script>

  <?php include __DIR__ . '/includes/navbar.php'; ?>

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
            <a href="<?= BASE_URL ?>pages/register.php" class="btn btn-primary btn-lg">
              <i class="fa-solid fa-cloud-arrow-up"></i> Commencer
            </a>
            <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-secondary btn-lg">
              <i class="fa-solid fa-magnifying-glass"></i> Explorer les Ressources
            </a>
          </div>
        </div>
        <div class="hero-visual">
          <img src="<?= BASE_URL ?>images/Team-pana.png" alt="Étudiants collaborant sur XFILES" class="hero-image-animate" width="560" height="420" fetchpriority="high" />
        </div>
      </div>
    </div>
  </header>

  <main id="main-content">
    <section id="ressources" class="container section-ressources">
      <h2 class="section-title">Nos Ressources Académiques</h2>
      <div class="ressources-grid">
        <?php foreach ($filieres as $f): ?>
          <div class="card ressource-card">
            <div class="ressource-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></div>
            <h3 class="ressource-title"><?= htmlspecialchars($f['name']) ?></h3>
            <a href="<?= BASE_URL ?>pages/dashboard.php?filiere=<?= htmlspecialchars($f['code']) ?>" class="btn btn-outline">Explorer <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
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
              <i class="fa-solid fa-quote-left quote-icon" aria-hidden="true"></i>
              <p>"<?= htmlspecialchars($t['text']) ?>"</p>
            </div>
            <div class="testimonial-author">
              <img src="https://ui-avatars.com/api/?name=<?= $initials ?>&background=fbbf24&color=000" alt="Photo de profil de <?= htmlspecialchars($t['name']) ?>" class="author-avatar" width="48" height="48" loading="lazy">
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
          <a href="<?= BASE_URL ?>pages/login.php" class="brand" aria-label="XFILES — Accueil">
            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> XFILES
          </a>
          <p class="footer-desc">
            Partagez, découvrez et améliorez des ressources académiques au sein d'une communauté d'étudiants.
          </p>
          <div class="social-links">
            <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter" aria-hidden="true"></i></a>
            <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
            <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin" aria-hidden="true"></i></a>
            <a href="#" aria-label="GitHub"><i class="fa-brands fa-github" aria-hidden="true"></i></a>
          </div>
        </div>

        <div class="footer-links">
          <h4>Ressources</h4>
          <ul>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?type=cours">Cours & Synthèses</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?type=examen">Annales d'examens</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?type=tp">Tutoriels</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php">Filières</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Communauté</h4>
          <ul>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php">Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>pages/register.php">S'inscrire</a></li>
            <li><a href="<?= BASE_URL ?>pages/login.php">Se connecter</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>XFILES</h4>
          <ul>
            <li><a href="<?= BASE_URL ?>index.php">Accueil</a></li>
            <li><a href="mailto:honoretchohlo02@gmail.com">Nous contacter</a></li>
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
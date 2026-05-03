<?php
/**
 * XFILES — Page d'accueil
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$filieres = $pdo->query('SELECT * FROM filieres ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$statModules  = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
$statFilieres = count($filieres);
$statDocs     = $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'approuve'")->fetchColumn();
$statUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'etudiant'")->fetchColumn();

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

  <!-- ══ HERO ═══════════════════════════════════════════════════════════ -->
  <header class="hero-header">
    <div class="container">
      <div class="hero-section">
        <div class="hero-content">
          <span class="hero-badge"><i class="fa-solid fa-bolt"></i> 100 % gratuit, toujours</span>
          <h1 class="hero-title">Le savoir à la portée de tous.</h1>
          <p class="hero-subtitle">
            Partagez, découvrez et téléchargez des ressources académiques au sein
            d'une communauté d'étudiants engagée.
          </p>
          <div class="hero-actions">
            <a href="<?= BASE_URL ?>pages/register.php" class="btn btn-primary btn-lg">
              <i class="fa-solid fa-cloud-arrow-up"></i> Commencer gratuitement
            </a>
            <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-secondary btn-lg">
              <i class="fa-solid fa-magnifying-glass"></i> Explorer les ressources
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

    <!-- ══ STATS BAR ═══════════════════════════════════════════════════ -->
    <section class="section-stats-bar">
      <div class="container">
        <div class="stats-bar-grid">
          <div class="stats-bar-item">
            <span class="stats-bar-num"><?= (int)$statFilieres ?></span>
            <span class="stats-bar-label">Filières</span>
          </div>
          <div class="stats-bar-divider"></div>
          <div class="stats-bar-item">
            <span class="stats-bar-num"><?= (int)$statModules ?>+</span>
            <span class="stats-bar-label">Modules disponibles</span>
          </div>
          <div class="stats-bar-divider"></div>
          <div class="stats-bar-item">
            <span class="stats-bar-num">5</span>
            <span class="stats-bar-label">Types de ressources</span>
          </div>
          <div class="stats-bar-divider"></div>
          <div class="stats-bar-item">
            <span class="stats-bar-num">100 %</span>
            <span class="stats-bar-label">Gratuit &amp; accessible</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FILIÈRES ════════════════════════════════════════════════════ -->
    <section id="ressources" class="container section-ressources">
      <div class="section-heading">
        <h2 class="section-title">Explorez par filière</h2>
        <p class="section-subtitle">Des ressources organisées par filière pour retrouver exactement ce qu'il vous faut.</p>
      </div>
      <div class="ressources-grid">
        <?php foreach ($filieres as $f): ?>
          <div class="card ressource-card">
            <div class="ressource-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></div>
            <h3 class="ressource-title"><?= htmlspecialchars($f['name']) ?></h3>
            <p><?= htmlspecialchars($f['description'] ?? 'Accédez aux cours, TD, TP et annales d\'examens de cette filière.') ?></p>
            <a href="<?= BASE_URL ?>pages/dashboard.php?filiere=<?= htmlspecialchars($f['code']) ?>" class="btn btn-outline">
              Explorer <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ══ COMMENT ÇA MARCHE ═══════════════════════════════════════════ -->
    <section class="section-how">
      <div class="container">
        <div class="section-heading">
          <h2 class="section-title">Comment ça marche ?</h2>
          <p class="section-subtitle">Rejoignez XFILES en trois étapes simples et profitez du savoir collectif.</p>
        </div>
        <div class="steps-grid">
          <div class="step-card">
            <div class="step-number">01</div>
            <div class="step-icon"><i class="fa-solid fa-user-plus"></i></div>
            <h3>Créez votre compte</h3>
            <p>Inscrivez-vous gratuitement en quelques secondes. Aucune carte bancaire requise, jamais.</p>
          </div>
          <div class="step-arrow"><i class="fa-solid fa-arrow-right"></i></div>
          <div class="step-card">
            <div class="step-number">02</div>
            <div class="step-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <h3>Découvrez les ressources</h3>
            <p>Parcourez des cours, TD, TP et annales classés par filière, module et semestre.</p>
          </div>
          <div class="step-arrow"><i class="fa-solid fa-arrow-right"></i></div>
          <div class="step-card">
            <div class="step-number">03</div>
            <div class="step-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <h3>Partagez à votre tour</h3>
            <p>Publiez vos propres documents et contribuez à la réussite de toute la communauté.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ TYPES DE RESSOURCES ════════════════════════════════════════ -->
    <section class="container section-types">
      <div class="section-heading">
        <h2 class="section-title">Tous types de ressources</h2>
        <p class="section-subtitle">Cinq catégories de documents pour couvrir tous vos besoins académiques.</p>
      </div>
      <div class="types-grid">
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=cours" class="type-showcase-card">
          <div class="type-showcase-icon type-icon-cours"><i class="fa-solid fa-book-open"></i></div>
          <div class="type-showcase-body">
            <h3>Cours</h3>
            <p>Fiches de cours complètes et synthèses de chapitres rédigées par vos pairs.</p>
          </div>
          <i class="fa-solid fa-arrow-right type-showcase-arrow"></i>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=td" class="type-showcase-card">
          <div class="type-showcase-icon type-icon-td"><i class="fa-solid fa-list-check"></i></div>
          <div class="type-showcase-body">
            <h3>Travaux Dirigés</h3>
            <p>Séries d'exercices avec corrigés détaillés pour consolider vos acquis.</p>
          </div>
          <i class="fa-solid fa-arrow-right type-showcase-arrow"></i>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=tp" class="type-showcase-card">
          <div class="type-showcase-icon type-icon-tp"><i class="fa-solid fa-flask"></i></div>
          <div class="type-showcase-body">
            <h3>Travaux Pratiques</h3>
            <p>Rapports et guides de TP pour préparer et réussir vos séances en laboratoire.</p>
          </div>
          <i class="fa-solid fa-arrow-right type-showcase-arrow"></i>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=examen" class="type-showcase-card">
          <div class="type-showcase-icon type-icon-examen"><i class="fa-solid fa-file-circle-question"></i></div>
          <div class="type-showcase-body">
            <h3>Annales d'examens</h3>
            <p>Sujets d'examens des années précédentes pour s'entraîner dans les meilleures conditions.</p>
          </div>
          <i class="fa-solid fa-arrow-right type-showcase-arrow"></i>
        </a>
        <a href="<?= BASE_URL ?>pages/dashboard.php?types=resume" class="type-showcase-card">
          <div class="type-showcase-icon type-icon-resume"><i class="fa-solid fa-note-sticky"></i></div>
          <div class="type-showcase-body">
            <h3>Résumés</h3>
            <p>Notes condensées et mémos visuels pour réviser efficacement avant les épreuves.</p>
          </div>
          <i class="fa-solid fa-arrow-right type-showcase-arrow"></i>
        </a>
      </div>
    </section>

    <!-- ══ TÉMOIGNAGES ════════════════════════════════════════════════ -->
    <section id="temoignages" class="container section-testimonials">
      <div class="section-heading">
        <h2 class="section-title">Ils réussissent grâce à XFILES</h2>
        <p class="section-subtitle">Des étudiants qui ont amélioré leurs résultats grâce au partage de connaissances.</p>
      </div>
      <div class="testimonials-grid">
        <?php
        $testimonials = [
          ['name' => 'Lucas M.',  'role' => 'Étudiant en Master Informatique', 'text' => 'Grâce aux fiches de révision partagées, j\'ai pu valider mon semestre avec mention. La qualité des documents est vraiment au rendez-vous.'],
          ['name' => 'Sarah B.',  'role' => 'Étudiante en L3 Mathématiques',   'text' => 'Les annales et corrections détaillées m\'ont sauvé la vie avant les partiels. Je recommande à tous mes camarades.'],
          ['name' => 'Emma D.',   'role' => 'Étudiante en Droit',              'text' => 'Partager mes propres synthèses m\'a aidé à mieux mémoriser mes cours. Un cercle vertueux que j\'encourage vivement.'],
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

    <!-- ══ APPEL À L'ACTION ═══════════════════════════════════════════ -->
    <section class="section-cta">
      <div class="container">
        <div class="cta-card">
          <div class="cta-content">
            <h2>Prêt à rejoindre la communauté ?</h2>
            <p>Créez votre compte gratuitement et accédez à toutes les ressources en quelques secondes.</p>
          </div>
          <div class="cta-actions">
            <a href="<?= BASE_URL ?>pages/register.php" class="btn btn-primary btn-lg">
              <i class="fa-solid fa-user-plus"></i> S'inscrire gratuitement
            </a>
            <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-secondary btn-lg">
              <i class="fa-solid fa-arrow-right-to-bracket"></i> Se connecter
            </a>
          </div>
        </div>
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
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?types=cours">Cours &amp; Synthèses</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?types=examen">Annales d'examens</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?types=td">Travaux Dirigés</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?types=tp">Travaux Pratiques</a></li>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php?types=resume">Résumés</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>Communauté</h4>
          <ul>
            <li><a href="<?= BASE_URL ?>pages/dashboard.php">Tableau de bord</a></li>
            <li><a href="<?= BASE_URL ?>pages/register.php">S'inscrire</a></li>
            <li><a href="<?= BASE_URL ?>pages/login.php">Se connecter</a></li>
            <li><a href="<?= BASE_URL ?>pages/upload.php">Publier un document</a></li>
          </ul>
        </div>

        <div class="footer-links">
          <h4>XFILES</h4>
          <ul>
            <li><a href="<?= BASE_URL ?>index.php">Accueil</a></li>
            <li><a href="#ressources">Nos filières</a></li>
            <li><a href="#temoignages">Témoignages</a></li>
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

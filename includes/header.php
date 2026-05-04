<?php
/**
 * XFILES — Header HTML partagé
 * Usage: $pageTitle, $pageDescription, $pageCss, $pageRobots, $pageOgImage doivent être définis avant inclusion
 */
if (!isset($pageTitle))       $pageTitle       = 'XFILES — Partage de Ressources Académiques';
if (!isset($pageCss))         $pageCss         = [];
if (!isset($pageDescription)) $pageDescription = 'XFILES est une plateforme gratuite de partage de ressources académiques. Accédez à des cours, TD, TP et annales d\'examens partagés par des étudiants.';
if (!isset($pageRobots))      $pageRobots      = 'index, follow';
if (!isset($pageOgImage))     $pageOgImage     = '';

$canonicalUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'xfiles.replit.app') . ($_SERVER['REQUEST_URI'] ?? '/');
$ogImage      = $pageOgImage ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? 'xfiles.replit.app') . '/images/Team-pana.png';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- SEO Core -->
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>" />
  <meta name="robots" content="<?= e($pageRobots) ?>" />
  <link rel="canonical" href="<?= e($canonicalUrl) ?>" />

  <!-- Open Graph -->
  <meta property="og:type"        content="website" />
  <meta property="og:site_name"   content="XFILES" />
  <meta property="og:locale"      content="fr_FR" />
  <meta property="og:title"       content="<?= e($pageTitle) ?>" />
  <meta property="og:description" content="<?= e($pageDescription) ?>" />
  <meta property="og:url"         content="<?= e($canonicalUrl) ?>" />
  <meta property="og:image"       content="<?= e($ogImage) ?>" />
  <meta property="og:image:alt"   content="XFILES — Partage de ressources académiques" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="<?= e($pageTitle) ?>" />
  <meta name="twitter:description" content="<?= e($pageDescription) ?>" />
  <meta name="twitter:image"       content="<?= e($ogImage) ?>" />

  <!-- Theme color (matches brand) -->
  <meta name="theme-color" content="#fbbf24" />

  <!-- Preconnect for performance -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
  <link rel="preconnect" href="https://ui-avatars.com" crossorigin />

  <!-- Dark/Light mode flash prevention -->
  <script>
    (function() {
      var s = localStorage.getItem('theme');
      var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', s || sys);
    })();
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.body.classList.add('page-ready');
      document.querySelectorAll('a[href]').forEach(function(link) {
        var href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('javascript') || link.target === '_blank') return;
        link.addEventListener('click', function(e) {
          e.preventDefault();
          document.body.style.opacity = '0';
          setTimeout(function() { window.location.href = href; }, 220);
        });
      });
    });
  </script>

  <!-- Stylesheets -->
  <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css?v=1.1.0" />
  <?php foreach ($pageCss as $css): ?>
  <link rel="stylesheet" href="<?= $css ?>?v=1.1.0" />
  <?php endforeach; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <!-- JSON-LD: Organization -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "XFILES",
    "description": "Plateforme gratuite de partage de ressources académiques pour étudiants",
    "url": "https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'xfiles.replit.app') ?>",
    "potentialAction": {
      "@type": "SearchAction",
      "target": {
        "@type": "EntryPoint",
        "urlTemplate": "https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'xfiles.replit.app') ?>/pages/dashboard.php?q={search_term_string}"
      },
      "query-input": "required name=search_term_string"
    }
  }
  </script>
</head>
<body<?= isset($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>
<!-- Skip navigation for accessibility -->
<a class="skip-link" href="#main-content">Aller au contenu principal</a>

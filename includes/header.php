<?php
/**
 * XFILES — Header HTML partagé
 * Usage: $pageTitle, $pageCss (array) doivent être définis avant inclusion
 */
if (!isset($pageTitle)) $pageTitle = 'XFILES';
if (!isset($pageCss))   $pageCss = [];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="XFILES - Plateforme de partage de ressources académiques. Partagez, découvrez et améliorez vos cours, TD, TP et examens." />
  <title><?= e($pageTitle) ?></title>
  <script>
    (function() {
      var s = localStorage.getItem('theme');
      var sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', s || sys);
    })();
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css?v=1.0.0" />
  <?php foreach ($pageCss as $css): ?>
  <link rel="stylesheet" href="<?= $css ?>?v=1.0.0" />
  <?php endforeach; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body<?= isset($bodyClass) ? ' class="' . e($bodyClass) . '"' : '' ?>>

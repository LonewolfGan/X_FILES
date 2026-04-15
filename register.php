<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
  header('Location: ' . BASE_URL . 'index.php');
  exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name     = trim($_POST['name'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm  = $_POST['confirm'] ?? '';
  $filiere  = $_POST['filiere'] ?? '';

  $errors = register($name, $email, $password, $confirm, $filiere, $pdo);
  if (empty($errors)) {
    $success = true;
  }
}

$filieres = $pdo->query('SELECT * FROM filieres')->fetchAll();
?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Créer un compte – XFILES</title>
  <script>
    (function() {
      const saved = localStorage.getItem('theme');
      const sys = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', saved || sys);
    })();
  </script>
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css" />
  <link rel="stylesheet" href="assets/css/components/forms.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/components/buttons.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="assets/css/components/auth.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>

  <a href="index.php" class="auth-top-logo">
    <i class="fa-solid fa-graduation-cap"></i> XFILES
  </a>

  <div class="auth-box">

    <div class="auth-illustration-strip">
      <img src="assets/img/Mobile login-pana.png" alt="Inscription" />
      <div class="auth-strip-text">
        <h2>Rejoins la communauté !</h2>
        <p>Cours, annales, synthèses — tout ce dont tu as besoin pour réussir.</p>
      </div>
    </div>

    <div class="auth-form-area">
      <?php if ($success): ?>
        <div class="auth-success-state">
          <div class="success-icon-wrapper">
            <i class="fa-solid fa-check"></i>
          </div>
          <h2>Félicitations !</h2>
          <p>Ton compte XFILES a été créé avec succès. Tu es maintenant connecté !</p>
          <a href="index.php" class="btn-auth btn-auth-welcome">Accéder à mon espace <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      <?php else: ?>
        <h1>Créer un compte</h1>
        <p class="auth-subtitle">C'est gratuit et rapide !</p>

        <?php if (!empty($errors)): ?>
          <div class="auth-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?php foreach ($errors as $e) echo '<p>' . htmlspecialchars($e) . '</p>'; ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="" id="register-form">

          <div class="form-row">
            <div class="form-group">
              <label for="name"><i class="fa-solid fa-user"></i> Nom complet</label>
              <input type="text" id="name" name="name"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                placeholder="Ahmed Benali"
                class="form-control" required />
            </div>
            <div class="form-group">
              <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
              <input type="email" id="email" name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                placeholder="email@ensias.ma"
                class="form-control" required />
            </div>
          </div>

          <div class="form-group">
            <label for="filiere"><i class="fa-solid fa-graduation-cap"></i> Filière</label>
            <select id="filiere" name="filiere" class="form-control">
              <option value="">-- Choisir une filière --</option>
              <?php foreach ($filieres as $f): ?>
                <option value="<?= $f['code'] ?>"
                  <?= (($_POST['filiere'] ?? '') === $f['code']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($f['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="password"><i class="fa-solid fa-lock"></i> Mot de passe</label>
              <div class="input-password-wrapper">
                <input type="password" id="password" name="password"
                  placeholder="8 caractères min."
                  class="form-control" required />
                <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="form-group">
              <label for="confirm"><i class="fa-solid fa-lock"></i> Confirmation</label>
              <div class="input-password-wrapper">
                <input type="password" id="confirm" name="confirm"
                  placeholder="Répète ton mot de passe"
                  class="form-control" required />
                <button type="button" class="toggle-password" onclick="togglePassword('confirm', this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-auth">
            Créer mon compte <i class="fa-solid fa-arrow-right"></i>
          </button>

        </form>

        <p class="auth-footer-text">
          Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/js/jquery.nice-select.min.js"></script>
  <script src="/mini/assets/js/auth.js?v=<?= time() ?>" defer></script>
  <script>
    $(document).ready(function() {
      $('select').niceSelect();
    });
  </script>
</body>

</html>
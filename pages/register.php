<?php
/**
 * XFILES — Page d'inscription
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $errors[] = 'Erreur de sécurité. Réessayez.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';
        $filiere  = $_POST['filiere'] ?? '';

        $errors = register($name, $login, $password, $confirm, $filiere, $pdo);
        if (empty($errors)) {
            $success = true;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM filieres ORDER BY name ASC');
$stmt->execute();
$filieres = $stmt->fetchAll();

$pageTitle       = 'Créer un compte gratuit – XFILES';
$pageDescription = 'Crée ton compte XFILES gratuitement en 30 secondes. Accède à des milliers de ressources académiques et partage tes propres cours et annales avec la communauté.';
$pageRobots      = 'index, follow';
$pageCss = [
    BASE_URL . 'css/auth.css',
    BASE_URL . 'css/forms.css',
    BASE_URL . 'css/buttons.css',
    'https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css',
];
include __DIR__ . '/../includes/header.php';
?>

  <a href="<?= BASE_URL ?>index.php" class="auth-top-logo">
    <i class="fa-solid fa-graduation-cap"></i> XFILES
  </a>

  <div class="auth-box">

    <div class="auth-illustration-strip">
      <img src="<?= BASE_URL ?>images/Mobile-login-pana.png" alt="Inscription" />
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
          <a href="<?= BASE_URL ?>index.php" class="btn-auth btn-auth-welcome">Accéder à mon espace <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      <?php else: ?>
        <h1>Créer un compte</h1>
        <p class="auth-subtitle">C'est gratuit et rapide !</p>

        <?php if (!empty($errors)): ?>
          <div class="auth-alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?php foreach ($errors as $e) echo '<p>' . e($e) . '</p>'; ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="" id="register-form">
          <?= csrfField() ?>

          <div class="form-row">
            <div class="form-group">
              <label for="name"><i class="fa-solid fa-user"></i> Nom complet</label>
              <input type="text" id="name" name="name"
                value="<?= e($_POST['name'] ?? '') ?>"
                placeholder="Ahmed Benali"
                class="form-control" required />
            </div>
            <div class="form-group">
              <label for="login"><i class="fa-solid fa-at"></i> Login</label>
              <input type="text" id="login" name="login"
                value="<?= e($_POST['login'] ?? '') ?>"
                placeholder="Ton identifiant unique (ex: ahmed, sara...)"
                class="form-control" required />
            </div>
          </div>

          <div class="form-group">
            <label for="filiere"><i class="fa-solid fa-graduation-cap"></i> Filière</label>
            <select id="filiere" name="filiere" class="form-control">
              <option value="">-- Choisir une filière --</option>
              <?php foreach ($filieres as $f): ?>
                <option value="<?= e($f['code']) ?>"
                  <?= (($_POST['filiere'] ?? '') === $f['code']) ? 'selected' : '' ?>>
                  <?= e($f['name']) ?>
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
          Vous avez déjà un compte ? <a href="<?= BASE_URL ?>pages/login.php">Se connecter</a>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/js/jquery.nice-select.min.js"></script>
  <script src="<?= BASE_URL ?>js/auth.js?v=1.0.0" defer></script>
  <script>
    $(document).ready(function() {
      $('select').niceSelect();
    });
  </script>
</body>
</html>
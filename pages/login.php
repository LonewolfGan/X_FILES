<?php
/**
 * XFILES — Page de connexion
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'pages/dashboard.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck()) {
        $error = 'Erreur de sécurité. Réessayez.';
    } else {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($login, $password, $pdo)) {
            $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'pages/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Login ou mot de passe incorrect.';
        }
    }
}

$pageTitle       = 'Connexion – XFILES';
$pageDescription = 'Connecte-toi à ton compte XFILES pour accéder à tes ressources académiques : cours, TD, TP, annales et synthèses partagés par la communauté.';
$pageRobots      = 'noindex, follow';
$pageCss = [
    BASE_URL . 'css/auth.css',
    BASE_URL . 'css/forms.css',
    BASE_URL . 'css/buttons.css',
];
include __DIR__ . '/../includes/header.php';
?>

  <a href="<?= BASE_URL ?>index.php" class="auth-top-logo">
    <i class="fa-solid fa-graduation-cap"></i> XFILES
  </a>

  <div class="auth-box">
    <div class="auth-illustration-strip">
      <img src="<?= BASE_URL ?>images/Mobile-login-pana.png" alt="Connexion" />
      <div class="auth-strip-text">
        <h2>Bon retour parmi nous !</h2>
        <p>Des milliers de ressources t'attendent pour booster tes révisions.</p>
      </div>
    </div>

    <div class="auth-form-area">
      <h1>Connexion</h1>
      <p class="auth-subtitle">Connecte-toi à ton compte XFILES</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="login-form">
        <?= csrfField() ?>

        <div class="form-group">
          <label for="login"><i class="fa-solid fa-user"></i> Login</label>
          <input type="text" id="login" name="login"
                 value="<?= e($_POST['login'] ?? '') ?>"
                 placeholder="Ton identifiant"
                 class="form-control" required />
        </div>

        <div class="form-group">
          <label for="password"><i class="fa-solid fa-lock"></i> Mot de passe</label>
          <div class="input-password-wrapper">
            <input type="password" id="password" name="password"
                   placeholder="Ton mot de passe"
                   class="form-control" required />
            <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-auth">
          Se connecter <i class="fa-solid fa-arrow-right"></i>
        </button>

        <div class="auth-divider"><span>ou</span></div>

        <a href="<?= BASE_URL ?>pages/register.php" class="btn-auth-outline">
          Créer un compte gratuit
        </a>

      </form>
    </div>
  </div>

  <script src="<?= BASE_URL ?>js/auth.js?v=1.0.0" defer></script>
</body>
</html>

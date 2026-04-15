<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');  
    $password = $_POST['password'] ?? '';

    if (login($email, $password, $pdo)) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    } else {
        $error = 'Email ou mot de passe incorrect.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion – XFILES</title>
  <script>
    (function() {
      const saved = localStorage.getItem('theme');
      const sys   = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', saved || sys);
    })();
  </script>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/components/forms.css" />
  <link rel="stylesheet" href="assets/css/components/buttons.css" />
  <link rel="stylesheet" href="assets/css/components/auth.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <script src="/mini/assets/js/auth.js?v=<?= time() ?>" defer></script>
</head>
<body>

  <a href="index.php" class="auth-top-logo">
    <i class="fa-solid fa-graduation-cap"></i> XFILES
  </a>

  <div class="auth-box">
    <div class="auth-illustration-strip">
      <img src="assets/img/Mobile login-cuate.png" alt="Connexion" />
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
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="login-form">

        <div class="form-group">
          <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="ton.email@ensias.ma"
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

        <div class="auth-form-options">
          <label class="remember-me">
            <input type="checkbox" name="remember" /> Se souvenir de moi
          </label>
          <a href="#" class="forgot-link">Mot de passe oublié ?</a>
        </div>

        <button type="submit" class="btn-auth">
          Se connecter <i class="fa-solid fa-arrow-right"></i>
        </button>

        <div class="auth-divider"><span>ou</span></div>

        <a href="register.php" class="btn-auth-outline">
          Créer un compte gratuit
        </a>

      </form>
    </div>
  </div>
</body>
</html>

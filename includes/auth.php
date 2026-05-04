<?php
/**
 * XFILES — Authentification et contrôle d'accès
 * Sessions, password_hash/verify, RBAC, mot de passe oublié
 */

// --- VÉRIFICATION DE SESSION ---

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function hasRole(string $role): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (!hasRole($role)) {
        http_response_code(403);
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

// --- AUTHENTIFICATION ---

/**
 * Connecte un utilisateur par login (pas email)
 */
function login(string $login, string $password, PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE login = ?');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_login']  = $user['login'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['role']        = $user['role'];
        return true;
    }
    return false;
}

/**
 * Déconnecte l'utilisateur et détruit la session
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}

/**
 * Récupère l'utilisateur courant depuis la BDD
 */
function getCurrentUser(PDO $pdo): ?array
{
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// --- INSCRIPTION ---

/**
 * Enregistre un nouvel utilisateur avec validation
 * Retourne un tableau d'erreurs (vide si succès)
 */
function register(string $name, string $login, string $password, string $confirm, string $filiere, PDO $pdo): array
{
    $errors = [];

    if (empty($name))                                                $errors[] = 'Le nom est obligatoire.';
    if (empty($login))                                               $errors[] = 'Le login est obligatoire.';
    if (strlen($login) < 3)                                          $errors[] = 'Le login doit contenir au moins 3 caractères.';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $login))                   $errors[] = 'Le login ne peut contenir que des lettres, chiffres et _.';
    if (strlen($password) < 8)                                       $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($password !== $confirm)                                      $errors[] = 'Les mots de passe ne correspondent pas.';
    if (empty($filiere))                                             $errors[] = 'Veuillez choisir une filière.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE login = ?');
        $stmt->execute([$login]);
        if ($stmt->fetch()) $errors[] = 'Ce login est déjà utilisé.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, login, password_hash, filiere_code) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $login, $hash, $filiere]);
        
        // Auto-login après inscription
        if (!login($login, $password, $pdo)) {
            $errors[] = 'Compte créé mais erreur lors de la connexion automatique. Veuillez vous connecter manuellement.';
        }
    }

    return $errors;
}


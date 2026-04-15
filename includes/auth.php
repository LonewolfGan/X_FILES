<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function login($email, $password, $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['role']        = $user['role'];
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Enregistre un nouvel utilisateur.
 * Retourne un tableau d'erreurs (vide si succès).
 */
function register($name, $email, $password, $confirm, $filiere, $pdo) {
    $errors = [];
    
    if (empty($name))                                                $errors[] = 'Le nom est obligatoire.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($password) < 8)                                       $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($password !== $confirm)                                      $errors[] = 'Les mots de passe ne correspondent pas.';
    if (empty($filiere))                                             $errors[] = 'Veuillez choisir une filière.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'Cet email est déjà utilisé.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, filiere_code) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, $filiere]);
        
        // Auto-connexion
        login($email, $password, $pdo);
    }

    return $errors;
}
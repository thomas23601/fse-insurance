<?php
session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function login(PDO $pdo, string $email, string $password): bool {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        return true;
    }
    return false;
}

function logout(): void {
    session_destroy();
    header('Location: /login.php');
    exit;
}

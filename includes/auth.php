<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function ensureUsersTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        usuario VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        rol ENUM('admin', 'usuario') NOT NULL DEFAULT 'usuario',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        ultimo_acceso DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

function currentUser(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function isAdmin(): bool
{
    return (currentUser()['rol'] ?? null) === 'admin';
}

function requireLogin(): void
{
    if (!currentUser()) {
        $next = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        header('Location: login.php?next=' . rawurlencode($next));
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('Acceso denegado. Esta sección requiere rol administrador.');
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('La sesión del formulario expiró. Recargue la página e intente nuevamente.');
    }
}


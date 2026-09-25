<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

ensureUsersTable($pdo);

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'login';
    $usuario = trim($_POST['usuario'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($action === 'setup' && !$hasUsers) {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '' || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $usuario) || strlen($password) < 8) {
            $error = 'Complete el nombre, use un usuario válido y una contraseña de al menos 8 caracteres.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, 'admin')");
            try {
                $stmt->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT)]);
                $hasUsers = true;
            } catch (PDOException $e) {
                $error = 'No fue posible crear el administrador inicial.';
            }
        }
    }

    if ($action === 'login' && $hasUsers) {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['auth_user'] = [
                'id' => (int)$user['id'], 'nombre' => $user['nombre'],
                'usuario' => $user['usuario'], 'rol' => $user['rol'],
            ];
            $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$user['id']]);
            $next = $_GET['next'] ?? 'index.php';
            $allowed = ['index.php', 'empleados.php', 'prestaciones.php', 'catalogo.php', 'lotes.php', 'reportes.php', 'parametros.php', 'usuarios.php'];
            header('Location: ' . (in_array($next, $allowed, true) ? $next : 'index.php'));
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
        usleep(400000);
    }
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acceso - PRIME</title><script src="assets/js/tailwind.min.js"></script><link rel="stylesheet" href="assets/css/inter.css">
</head><body class="min-h-screen bg-slate-950 flex items-center justify-center p-6 font-sans">
<main class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden">
    <div class="bg-[#0f2b48] px-8 py-7 text-center"><img src="assets/img/logo_white_text.png" alt="PRIME" class="h-16 mx-auto object-contain"><p class="text-slate-300 text-xs mt-3 tracking-widest uppercase">Sistema de Planillas</p></div>
    <div class="p-8">
        <h1 class="text-2xl font-black text-slate-900"><?php echo $hasUsers ? 'Iniciar sesión' : 'Crear administrador'; ?></h1>
        <p class="text-sm text-slate-600 mt-2 mb-6"><?php echo $hasUsers ? 'Ingrese sus credenciales para continuar.' : 'Configure la primera cuenta con acceso total.'; ?></p>
        <?php if ($error): ?><div class="mb-5 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="<?php echo $hasUsers ? 'login' : 'setup'; ?>">
            <?php if (!$hasUsers): ?><label class="block text-sm font-bold text-slate-700">Nombre completo<input name="nombre" required maxlength="120" class="mt-1 w-full p-3 border rounded-xl"></label><?php endif; ?>
            <label class="block text-sm font-bold text-slate-700">Usuario<input name="usuario" required maxlength="80" autocomplete="username" class="mt-1 w-full p-3 border rounded-xl"></label>
            <label class="block text-sm font-bold text-slate-700">Contraseña<input type="password" name="password" required minlength="8" autocomplete="<?php echo $hasUsers ? 'current-password' : 'new-password'; ?>" class="mt-1 w-full p-3 border rounded-xl"></label>
            <button class="w-full p-3 rounded-xl bg-[#0f2b48] hover:bg-[#0b1f36] text-white font-black"><?php echo $hasUsers ? 'Entrar' : 'Crear administrador'; ?></button>
        </form>
    </div>
</main></body></html>


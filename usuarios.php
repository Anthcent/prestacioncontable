<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireAdmin();

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $rol = in_array($_POST['rol'] ?? '', ['admin', 'usuario'], true) ? $_POST['rol'] : 'usuario';
        if ($nombre === '' || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $usuario) || strlen($password) < 8) {
            $error = 'Revise los datos. La contraseña debe tener al menos 8 caracteres.';
        } else {
            try {
                $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES (?, ?, ?, ?)')
                    ->execute([$nombre, $usuario, password_hash($password, PASSWORD_DEFAULT), $rol]);
                $message = 'Usuario creado correctamente.';
            } catch (PDOException $e) { $error = 'El nombre de usuario ya existe o no pudo guardarse.'; }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)currentUser()['id']) $error = 'No puede desactivar su propia cuenta.';
        else { $pdo->prepare('UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = ?')->execute([$id]); $message = 'Estado actualizado.'; }
    } elseif ($action === 'password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
        else { $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]); $message = 'Contraseña actualizada.'; }
    }
}
$usuarios = $pdo->query('SELECT id, nombre, usuario, rol, activo, ultimo_acceso, created_at FROM usuarios ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
include 'includes/header.php';
?>
<div class="flex justify-between items-center mb-8"><div><h1 class="text-3xl font-black text-slate-900">Usuarios</h1><p class="text-slate-600">Cuentas y roles de acceso</p></div></div>
<?php if ($error): ?><div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($message): ?><div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<section class="bg-white border rounded-2xl p-6 mb-7 shadow-sm"><h2 class="font-black text-lg mb-4">Crear cuenta</h2>
<form method="post" class="grid md:grid-cols-5 gap-4 items-end"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><input type="hidden" name="action" value="create">
<label class="text-xs font-bold">Nombre<input name="nombre" required class="mt-1 w-full p-2.5 border rounded-xl"></label><label class="text-xs font-bold">Usuario<input name="usuario" required pattern="[A-Za-z0-9._-]{3,80}" class="mt-1 w-full p-2.5 border rounded-xl"></label><label class="text-xs font-bold">Contraseña<input type="password" name="password" required minlength="8" class="mt-1 w-full p-2.5 border rounded-xl"></label><label class="text-xs font-bold">Rol<select name="rol" class="mt-1 w-full p-2.5 border rounded-xl"><option value="usuario">Usuario</option><option value="admin">Administrador</option></select></label><button class="p-2.5 bg-brand-blue text-white font-bold rounded-xl">Crear</button></form></section>
<section class="bg-white border rounded-2xl overflow-hidden shadow-sm"><table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-4 text-left">Nombre</th><th class="p-4 text-left">Usuario</th><th class="p-4">Rol</th><th class="p-4">Estado</th><th class="p-4">Acciones</th></tr></thead><tbody>
<?php foreach ($usuarios as $u): ?><tr class="border-t"><td class="p-4 font-bold"><?php echo htmlspecialchars($u['nombre']); ?></td><td class="p-4"><?php echo htmlspecialchars($u['usuario']); ?></td><td class="p-4 text-center"><?php echo $u['rol'] === 'admin' ? 'Administrador' : 'Usuario'; ?></td><td class="p-4 text-center"><?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?></td><td class="p-4"><div class="flex gap-2 justify-center"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $u['id']; ?>"><button class="px-3 py-1.5 border rounded-lg" <?php echo $u['id'] == currentUser()['id'] ? 'disabled' : ''; ?>><?php echo $u['activo'] ? 'Desactivar' : 'Activar'; ?></button></form><form method="post" class="flex gap-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?php echo $u['id']; ?>"><input type="password" name="password" minlength="8" required placeholder="Nueva contraseña" class="w-36 px-2 border rounded-lg"><button class="px-3 py-1.5 bg-slate-800 text-white rounded-lg">Cambiar</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></section>
<?php include 'includes/footer.php'; ?>

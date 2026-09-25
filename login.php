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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#07182b">
    <title><?php echo $hasUsers ? 'Iniciar sesión' : 'Configuración inicial'; ?> | PRIME</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/inter.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <script src="assets/js/tailwind.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } } };
    </script>
    <style>
        .login-backdrop {
            background:
                radial-gradient(circle at 15% 15%, rgba(32, 91, 140, .45), transparent 32%),
                radial-gradient(circle at 88% 85%, rgba(197, 155, 39, .16), transparent 25%),
                linear-gradient(135deg, #061422 0%, #0b2742 52%, #06121f 100%);
        }
        .grid-pattern {
            background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="min-h-screen login-backdrop font-sans text-slate-900 antialiased">
    <div class="fixed inset-0 grid-pattern pointer-events-none"></div>
    <main class="relative min-h-screen flex items-center justify-center p-4 sm:p-8">
        <section class="w-full max-w-5xl min-h-[650px] grid lg:grid-cols-[1.05fr_.95fr] overflow-hidden rounded-[2rem] bg-white shadow-[0_35px_100px_rgba(0,0,0,.45)] border border-white/10">
            <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-[#0a2239] p-12 text-white">
                <div class="absolute -top-32 -right-28 w-80 h-80 rounded-full border-[60px] border-white/[.035]"></div>
                <div class="absolute -bottom-24 -left-20 w-72 h-72 rounded-full bg-amber-400/[.07] blur-2xl"></div>
                <div class="relative">
                    <img src="assets/img/logo_white_text.png" alt="PRIME Contadores Públicos" class="w-64 h-auto object-contain object-left">
                    <div class="w-12 h-1 rounded-full bg-amber-400 mt-7"></div>
                </div>
                <div class="relative max-w-md">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-white/10 bg-white/5 text-[11px] font-bold uppercase tracking-[.18em] text-amber-300">
                        <i class="fa-solid fa-shield-halved"></i> Acceso seguro
                    </span>
                    <h2 class="mt-6 text-4xl font-black leading-tight tracking-tight">Gestión contable con precisión y confianza.</h2>
                    <p class="mt-5 text-sm leading-7 text-slate-300">Administre empleados, prestaciones sociales, liquidaciones y reportes desde un entorno centralizado.</p>
                    <div class="grid grid-cols-2 gap-3 mt-9">
                        <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4"><i class="fa-solid fa-file-invoice-dollar text-amber-300"></i><p class="mt-3 text-xs font-semibold text-slate-200">Cálculos y liquidaciones</p></div>
                        <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4"><i class="fa-solid fa-chart-line text-amber-300"></i><p class="mt-3 text-xs font-semibold text-slate-200">Reportes consolidados</p></div>
                    </div>
                </div>
                <p class="relative text-[10px] uppercase tracking-[.2em] text-slate-500">PRIME Contadores Públicos</p>
            </div>

            <div class="flex flex-col justify-center px-6 py-10 sm:px-12 lg:px-14 bg-white">
                    <div class="lg:hidden mb-10 flex flex-col items-center"><img src="assets/img/logo_prime.png" alt="PRIME" class="w-52 h-auto"><p class="mt-3 text-[10px] font-bold uppercase tracking-[.16em] text-slate-500">Sistema de Cálculo de Prestaciones Sociales</p></div>
                <div class="max-w-md w-full mx-auto">
                    <div class="flex items-center justify-between mb-8">
                        <span class="inline-flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-[.16em] text-[#0f2b48]"><span class="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,.12)]"></span>Sistema disponible</span>
                        <span class="text-[11px] font-bold text-slate-400">v1.0</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-950"><?php echo $hasUsers ? 'Bienvenido de nuevo' : 'Configure el sistema'; ?></h1>
                    <p class="mt-3 mb-8 text-sm leading-6 text-slate-500"><?php echo $hasUsers ? 'Ingrese sus credenciales para acceder al panel administrativo.' : 'Cree la cuenta administradora inicial para comenzar a trabajar.'; ?></p>

                    <?php if ($error): ?>
                        <div role="alert" class="mb-6 flex gap-3 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm">
                            <i class="fa-solid fa-circle-exclamation mt-0.5 text-red-500"></i><span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                        <input type="hidden" name="action" value="<?php echo $hasUsers ? 'login' : 'setup'; ?>">
                        <?php if (!$hasUsers): ?>
                            <label class="block"><span class="block mb-2 text-xs font-extrabold text-slate-700">Nombre completo</span><div class="relative"><i class="fa-regular fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="nombre" required maxlength="120" autocomplete="name" placeholder="Nombre y apellido" class="w-full h-13 py-3.5 pl-11 pr-4 border border-slate-300 rounded-xl bg-slate-50/60 outline-none transition focus:bg-white focus:border-[#0f2b48] focus:ring-4 focus:ring-blue-950/10"></div></label>
                        <?php endif; ?>
                        <label class="block"><span class="block mb-2 text-xs font-extrabold text-slate-700">Usuario</span><div class="relative"><i class="fa-regular fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="usuario" required maxlength="80" autocomplete="username" autofocus placeholder="Ingrese su usuario" class="w-full py-3.5 pl-11 pr-4 border border-slate-300 rounded-xl bg-slate-50/60 outline-none transition focus:bg-white focus:border-[#0f2b48] focus:ring-4 focus:ring-blue-950/10"></div></label>
                        <label class="block"><span class="block mb-2 text-xs font-extrabold text-slate-700">Contraseña</span><div class="relative"><i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i><input id="password" type="password" name="password" required minlength="8" autocomplete="<?php echo $hasUsers ? 'current-password' : 'new-password'; ?>" placeholder="Mínimo 8 caracteres" class="w-full py-3.5 pl-11 pr-12 border border-slate-300 rounded-xl bg-slate-50/60 outline-none transition focus:bg-white focus:border-[#0f2b48] focus:ring-4 focus:ring-blue-950/10"><button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-slate-400 hover:text-[#0f2b48] hover:bg-slate-100" aria-label="Mostrar contraseña"><i class="fa-regular fa-eye"></i></button></div></label>
                        <button type="submit" class="group w-full mt-2 py-4 px-5 rounded-xl bg-[#0f2b48] hover:bg-[#0a2036] text-white font-extrabold shadow-lg shadow-blue-950/20 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-blue-950/20 flex items-center justify-center gap-3"><?php echo $hasUsers ? 'Ingresar al sistema' : 'Crear cuenta administradora'; ?><i class="fa-solid fa-arrow-right text-amber-300 transition-transform group-hover:translate-x-1"></i></button>
                    </form>
                    <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-center gap-2 text-xs text-slate-400"><i class="fa-solid fa-lock text-emerald-500"></i><span>Conexión protegida y acceso restringido</span></div>
                </div>
            </div>
        </section>
    </main>
    <script>
        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        toggle?.addEventListener('click', () => {
            const visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            toggle.innerHTML = `<i class="fa-regular ${visible ? 'fa-eye' : 'fa-eye-slash'}"></i>`;
            toggle.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    </script>
</body>
</html>

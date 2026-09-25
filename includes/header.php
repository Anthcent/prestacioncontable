<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cálculo de Prestaciones Sociales | PRIME</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/img/favicon.png">
    <link rel="shortcut icon" href="assets/img/favicon.ico">
    <!-- Tailwind CSS (local) -->
    <script src="assets/js/tailwind.min.js"></script>
    <!-- Google Fonts - Inter (local) -->
    <link href="assets/css/inter.css" rel="stylesheet">
    <!-- FontAwesome (local) -->
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            blue: '#0f2b48',
                            navy: '#0b1f36',
                            gold: '#c59b27',
                            yellow: '#ffcc00',
                            light: '#f8fafc',
                            dark: '#0f172a'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Estilos base personalizados */
        body {
            background-color: #f1f5f9;
            color: #0f172a;
        }
        html, body { width: 100%; max-width: 100%; }
        main {
            color: #1e293b;
            min-width: 0;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        /* MEJORA GLOBAL DE CONTRASTE Y LEGIBILIDAD (Sin textos en gris claro) */
        main .text-slate-400, 
        main .text-gray-400 {
            color: #334155 !important; /* Reemplaza gris tenue por slate-700 nítido y legible */
        }
        main .text-slate-500, 
        main .text-gray-500 {
            color: #1e293b !important; /* Reemplaza gris medio por slate-800 sólido */
        }
        main .text-slate-600, 
        main .text-gray-600 {
            color: #0f172a !important; /* Slate-900 profundo */
        }

        .overflow-x-auto {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #e2e8f0;
        }
        .overflow-x-auto > table:not(.excel-grid) { min-width: 720px; }

        @media (max-width: 1023px) {
            body { height: auto; min-height: 100vh; overflow-x: hidden; }
            main { min-height: 100vh; width: 100%; }
            body.nav-open { overflow: hidden; }
            body.nav-open #mobileNavOverlay { opacity: 1; pointer-events: auto; }
            body.nav-open #appSidebar { transform: translateX(0); }
        }
        @media (max-width: 639px) {
            .glass-card { border-radius: 1rem; }
            input, select, textarea, button { max-width: 100%; }
        }

        /* Ocultar barra lateral e imprimir limpio en modo impresión */
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; color: #000; }
            .glass-card { box-shadow: none; border: none; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body class="text-slate-800 antialiased h-screen flex overflow-hidden">
    <!-- Sidebar / Nav -->
    <?php include 'nav.php'; ?>

    <button id="mobileNavOverlay" type="button" aria-label="Cerrar menú" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity lg:hidden no-print" onclick="toggleMobileNav(false)"></button>
    
    <!-- Contenido Principal -->
    <main class="flex-1 overflow-y-auto bg-slate-50 relative">
        <header class="lg:hidden sticky top-0 z-30 h-16 px-4 bg-white/95 backdrop-blur-xl border-b border-slate-200 flex items-center justify-between no-print shadow-sm">
            <button type="button" onclick="toggleMobileNav(true)" class="w-10 h-10 rounded-xl border border-slate-200 bg-slate-50 text-brand-dark flex items-center justify-center" aria-label="Abrir menú"><i class="fa-solid fa-bars"></i></button>
            <a href="index.php" class="flex items-center"><img src="assets/img/logo_prime.png" alt="PRIME" class="h-9 w-auto"></a>
            <div class="w-10 h-10 rounded-xl bg-brand-dark text-brand-yellow flex items-center justify-center text-xs font-black"><?php echo htmlspecialchars(mb_strtoupper(mb_substr(currentUser()['nombre'] ?? 'U', 0, 1))); ?></div>
        </header>
        <div class="px-4 py-5 sm:p-6 lg:p-8 max-w-7xl mx-auto min-h-screen w-full">

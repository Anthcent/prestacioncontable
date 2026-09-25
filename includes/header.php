<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRIME CONTADORES PÚBLICOS - Sistema de Planillas</title>
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
        main {
            color: #1e293b;
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
    
    <!-- Contenido Principal -->
    <main class="flex-1 overflow-y-auto bg-slate-50 relative">
        <div class="p-8 max-w-7xl mx-auto min-h-screen">

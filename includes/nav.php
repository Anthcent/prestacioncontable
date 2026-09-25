<!-- Nav Sidebar -->
<aside id="appSidebar" class="fixed inset-y-0 left-0 w-[min(82vw,18rem)] lg:w-64 lg:relative bg-gradient-to-b from-brand-dark to-[#001a33] text-white flex flex-col h-full shadow-[4px_0_24px_rgba(0,0,0,0.15)] no-print z-50 lg:z-20 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-out border-r border-white/5">
    <!-- Overlay de patrón sutil -->
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wMykiLz48L3N2Zz4=')] opacity-100 pointer-events-none"></div>
    
    <!-- Logo Area -->
    <button type="button" onclick="toggleMobileNav(false)" class="lg:hidden absolute top-4 right-4 z-30 w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white" aria-label="Cerrar menú"><i class="fa-solid fa-xmark"></i></button>
    <div class="relative p-5 lg:p-6 flex flex-col items-center justify-center border-b border-white/10 z-10">
        <a href="index.php" class="flex flex-col items-center group">
            <div class="relative w-16 h-16 rounded-2xl bg-white/95 p-2 flex items-center justify-center mb-3 shadow-[0_0_20px_rgba(212,175,55,0.35)] border border-amber-400/30 group-hover:scale-105 group-hover:shadow-[0_0_25px_rgba(212,175,55,0.5)] transition-all duration-300">
                <img src="assets/img/logo_icon.png" alt="PRIME Emblem" class="w-full h-full object-contain filter drop-shadow-sm">
            </div>
            <div class="text-center">
                <div class="text-xl font-black tracking-wider text-white flex items-center justify-center gap-1 drop-shadow-md">
                    <span>PR<span class="text-amber-400">I</span>ME</span>
                </div>
                <p class="text-[9px] tracking-[0.2em] text-slate-300 uppercase font-semibold mt-0.5">CONTADORES PÚBLICOS</p>
            </div>
        </a>
        <div class="flex items-center gap-2 mt-3 bg-black/40 px-3 py-1 rounded-full border border-amber-400/20 backdrop-blur-sm shadow-inner">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
            </span>
            <p class="text-[9px] text-amber-200 uppercase tracking-wider font-semibold text-center">Cálculo de Prestaciones Sociales</p>
        </div>
    </div>
    
    <!-- Navigation Links -->
    <div class="relative flex-1 overflow-y-auto py-6 custom-scrollbar z-10">
        <!-- BOTÓN DESTACADO PRINCIPAL -->
        <div class="px-4 mb-5">
            <a href="prestaciones_form.php" class="w-full bg-gradient-to-r from-brand-yellow to-yellow-400 hover:from-yellow-400 hover:to-yellow-300 text-brand-dark font-extrabold py-3 px-4 rounded-xl shadow-lg hover:shadow-yellow-500/20 transition-all duration-300 flex items-center justify-center gap-2 text-xs tracking-wider uppercase">
                <i class="fa-solid fa-plus text-sm"></i> Generar Nueva Hoja
            </a>
        </div>

        <nav class="space-y-2 px-4">
            <a href="index.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-chart-pie text-sm"></i>
                </div>
                <span class="text-sm">Resumen General</span>
            </a>
            
            <div class="pt-6 pb-2">
                <p class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                    <span>Módulos</span>
                    <span class="h-px bg-slate-700/50 flex-1"></span>
                </p>
            </div>
            
            <a href="empleados.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'empleado') !== false) ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'empleado') !== false) ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-users text-sm"></i>
                </div>
                <span class="text-sm">Trabajadores</span>
            </a>
            
            <a href="prestaciones.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'prestaciones') !== false) ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'prestaciones') !== false) ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-file-invoice-dollar text-sm"></i>
                </div>
                <span class="text-sm">Cálculos / Liquidaciones</span>
            </a>

            <a href="catalogo.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) == 'catalogo.php') ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (basename($_SERVER['PHP_SELF']) == 'catalogo.php') ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-folder-open text-sm"></i>
                </div>
                <span class="text-sm">Historial de Liquidaciones</span>
            </a>

            <a href="lotes.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) == 'lotes.php') ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (basename($_SERVER['PHP_SELF']) == 'lotes.php') ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-layer-group text-sm"></i>
                </div>
                <span class="text-sm">Gestión por Lotes</span>
            </a>

            <a href="reportes.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) == 'reportes.php') ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (basename($_SERVER['PHP_SELF']) == 'reportes.php') ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-chart-line text-sm"></i>
                </div>
                <span class="text-sm">Reportes y Resúmenes</span>
            </a>
            
            <div class="pt-6 pb-2">
                <p class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                    <span>Configuración</span>
                    <span class="h-px bg-slate-700/50 flex-1"></span>
                </p>
            </div>
            
            <?php if (isAdmin()): ?>
            <a href="parametros.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) == 'parametros.php') ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo (basename($_SERVER['PHP_SELF']) == 'parametros.php') ? 'bg-brand-yellow text-brand-dark shadow-[0_0_12px_rgba(255,204,0,0.5)]' : 'bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors'; ?>">
                    <i class="fa-solid fa-cogs text-sm"></i>
                </div>
                <span class="text-sm">Parámetros Legales</span>
            </a>
            <a href="usuarios.php" class="group flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition-all duration-300 <?php echo (basename($_SERVER['PHP_SELF']) == 'usuarios.php') ? 'bg-gradient-to-r from-brand-blue/60 to-transparent text-white font-medium border-l-4 border-brand-yellow shadow-lg' : 'border-l-4 border-transparent hover:border-slate-500/50 hover:translate-x-1'; ?>">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-black/20 group-hover:bg-black/40 text-slate-400 group-hover:text-brand-yellow transition-colors"><i class="fa-solid fa-user-gear text-sm"></i></div>
                <span class="text-sm">Usuarios</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <!-- Footer User Info -->
    <div class="relative p-5 border-t border-white/5 bg-black/20 backdrop-blur-md z-10">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-blue to-brand-dark border border-white/10 flex items-center justify-center shadow-inner relative">
                    <i class="fa-solid fa-user-shield text-[10px] text-brand-yellow"></i>
                    <div class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-brand-dark rounded-full"></div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-white tracking-wide"><?php echo htmlspecialchars(currentUser()['nombre'] ?? 'Usuario'); ?></p>
                    <p class="text-[10px] text-slate-400"><?php echo isAdmin() ? 'Administrador' : 'Usuario'; ?></p>
                </div>
            </div>
            <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><button type="submit" class="w-8 h-8 rounded-lg bg-black/30 hover:bg-red-600 text-slate-300 hover:text-white transition" title="Cerrar sesión"><i class="fa-solid fa-right-from-bracket"></i></button></form>
        </div>
    </div>
</aside>

<style>
/* Custom scrollbar para el nav */
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.25);
}
</style>

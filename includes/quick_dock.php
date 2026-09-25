<div id="quickDockBackdrop" onclick="toggleQuickDock(false)" class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm z-40 hidden opacity-0 transition-opacity duration-300 no-print"></div>

<aside id="quickDockPanel" class="fixed top-0 right-0 h-full w-80 sm:w-96 bg-white shadow-[-8px_0_30px_rgba(0,0,0,.18)] border-l border-slate-200 z-50 translate-x-full transition-transform duration-300 ease-out flex flex-col no-print">
    <button id="quickDockTrigger" onclick="toggleQuickDock()" type="button" class="absolute -left-11 top-1/2 -translate-y-1/2 bg-brand-blue hover:bg-blue-950 text-white py-4 px-2 rounded-l-2xl shadow-lg border-l-2 border-y-2 border-amber-400/60 flex flex-col items-center gap-2" title="Abrir accesos rápidos" aria-label="Abrir accesos rápidos">
        <span class="w-7 h-7 rounded-lg bg-brand-yellow text-brand-dark flex items-center justify-center"><i class="fa-solid fa-bolt"></i></span>
        <span class="quick-dock-label text-[10px] font-black uppercase tracking-widest text-amber-300 [writing-mode:vertical-lr] rotate-180 py-1">Accesos Rápidos</span>
        <i id="quickDockArrow" class="fa-solid fa-chevron-left text-[10px]"></i>
    </button>

    <div class="p-4 sm:p-5 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-bolt"></i></span>
            <div><h3 class="font-black text-slate-900 text-sm">Accesos Rápidos</h3><p class="text-[11px] text-slate-600">Navegación directa del sistema</p></div>
        </div>
        <button type="button" onclick="toggleQuickDock(false)" class="w-9 h-9 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div class="flex-1 overflow-y-auto p-3 sm:p-5 grid grid-cols-1 gap-2.5 custom-scrollbar">
        <?php
        $quick_links = [
            ['prestaciones_form.php?regla_perfil=LOTTT_30', 'fa-file-circle-plus', 'Nueva liquidación LOTTT', 'Cálculo vigente de prestaciones', 'emerald'],
            ['prestaciones_form.php?regla_perfil=LEGADO_120_180', 'fa-clock-rotate-left', 'Liquidación histórica', 'Régimen especial 120/180', 'amber'],
            ['empleados.php', 'fa-users', 'Trabajadores', 'Consultar y administrar personal', 'blue'],
            ['catalogo.php', 'fa-folder-open', 'Historial de liquidaciones', 'Consultar documentos generados', 'blue'],
            ['lotes.php', 'fa-layer-group', 'Gestión por lotes', 'Procesamiento consolidado', 'slate'],
            ['reportes.php', 'fa-chart-line', 'Reportes', 'Indicadores y resúmenes', 'slate']
        ];
        foreach ($quick_links as [$url, $icon, $title, $description, $color]): ?>
            <a href="<?php echo $url; ?>" class="group flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:border-brand-blue hover:shadow-md transition-all">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-slate-100 text-brand-blue flex items-center justify-center group-hover:bg-brand-blue group-hover:text-white transition-colors"><i class="fa-solid <?php echo $icon; ?>"></i></span>
                <span class="min-w-0"><strong class="block text-xs text-slate-900 truncate"><?php echo $title; ?></strong><small class="block text-[11px] text-slate-600 truncate"><?php echo $description; ?></small></span>
                <i class="fa-solid fa-chevron-right ml-auto text-xs text-slate-400"></i>
            </a>
        <?php endforeach; ?>
    </div>
</aside>

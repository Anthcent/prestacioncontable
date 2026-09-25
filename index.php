<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireLogin();

// Filtro de búsqueda
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT 
        p.id as prestacion_id,
        p.fecha_calculo,
        p.fecha_egreso,
        e.fecha_ingreso,
        p.motivo,
        p.antig_anos as anos,
        p.antig_nro_meses as meses,
        p.antig_nro_dias as dias,
        p.sueldo_mensual_normal,
        p.total_asignaciones,
        p.total_deducciones,
        p.neto_a_cobrar as total_prestaciones,
        p.regla_perfil,
        p.estado,
        e.id as empleado_id,
        e.cedula,
        e.apellidos_nombres,
        e.cargo
    FROM prestaciones p
    JOIN empleados e ON e.id = p.empleado_id
";

if (!empty($search)) {
    $sql .= " WHERE (e.cedula LIKE :search OR e.apellidos_nombres LIKE :search OR e.cargo LIKE :search OR p.motivo LIKE :search) ";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
if (!empty($search)) {
    $stmt->execute([':search' => '%' . $search . '%']);
} else {
    $stmt->execute();
}
$resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales generales y métricas ejecutivas
$total_general = 0;
$total_asignaciones_gen = 0;
$total_deducciones_gen = 0;
$count_lottt = 0;
$count_legado = 0;

foreach($resumen as $row) {
    $total_general += (float)$row['total_prestaciones'];
    $total_asignaciones_gen += (float)$row['total_asignaciones'];
    $total_deducciones_gen += (float)$row['total_deducciones'];

    if (isset($row['regla_perfil']) && $row['regla_perfil'] === 'LEGADO_120_180') {
        $count_legado++;
    } else {
        $count_lottt++;
    }
}

$total_expedientes = count($resumen);
$promedio_asig = ($total_expedientes > 0) ? ($total_asignaciones_gen / $total_expedientes) : 0;
$promedio_neto = ($total_expedientes > 0) ? ($total_general / $total_expedientes) : 0;
$pct_deducciones = ($total_asignaciones_gen > 0) ? (($total_deducciones_gen / $total_asignaciones_gen) * 100) : 0;
$pct_efectividad = ($total_asignaciones_gen > 0) ? (($total_general / $total_asignaciones_gen) * 100) : 100;

include 'includes/header.php'; 
?>

<!-- CABECERA DEL MÓDULO (Limpia y Ejecutiva) -->
<div class="flex flex-wrap justify-between items-center mb-6 gap-4">
    <div>
        <div class="flex flex-wrap items-center gap-2 mb-1.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-black bg-blue-100 text-brand-blue border border-blue-200">
                <i class="fa-solid fa-calculator"></i> Módulo de Prestaciones Sociales
            </span>
            <span class="text-xs font-bold text-slate-700 bg-slate-200/80 px-2.5 py-0.5 rounded-full">
                <i class="fa-regular fa-calendar-check mr-1 text-slate-700"></i> <?php echo date('d/m/Y'); ?>
            </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
            <i class="fa-solid fa-chart-pie text-brand-blue"></i> Resumen General Consolidado
        </h1>
        <p class="text-sm font-semibold text-slate-700 mt-1">
            Panel de liquidaciones, control de pasivos laborales y exportación oficial
        </p>
    </div>

    <!-- Navegación contextual superior -->
    <div class="grid grid-cols-1 sm:flex sm:flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 no-print w-full xl:w-auto">
        <button onclick="toggleQuickDock()" type="button" class="bg-white hover:bg-slate-100 text-slate-800 font-extrabold px-3.5 py-2.5 rounded-xl border border-slate-300 shadow-sm hover:shadow transition-all flex items-center gap-2 text-sm cursor-pointer" title="Abrir panel lateral de accesos rápidos (Presiona Barra Espaciadora)">
            <i class="fa-solid fa-bolt text-brand-yellow"></i> Accesos Rápidos
            <span class="hidden sm:inline text-[10px] font-mono text-slate-400 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">Espacio</span>
        </button>
        <a href="catalogo.php" class="bg-white hover:bg-slate-100 text-slate-800 font-extrabold px-4 py-2.5 rounded-xl border border-slate-300 shadow-sm hover:shadow transition-all flex items-center gap-2 text-sm">
            <i class="fa-solid fa-folder-open text-brand-blue"></i> Explorar Catálogo
        </a>
        <a href="prestaciones_form.php" class="bg-brand-blue hover:bg-blue-900 text-white font-extrabold px-4 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm">
            <i class="fa-solid fa-plus text-brand-yellow"></i> Nueva Liquidación
        </a>
    </div>
</div>

<!-- TARJETAS DE MÉTRICAS FINANCIERAS Y CONTROL EJECUTIVO (KPIs Profesionales) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-7">

    <!-- Card 1: Expedientes de Liquidación -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.06)] hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Expedientes Procesados</span>
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-sm font-bold shadow-xs">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-3xl font-black text-slate-900 tracking-tight font-mono"><?php echo $total_expedientes; ?></span>
                <span class="text-xs font-bold text-slate-500 uppercase"><?php echo $total_expedientes === 1 ? 'liquidación' : 'liquidaciones'; ?></span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
            <?php if($total_expedientes > 0): ?>
                <div class="flex items-center gap-1.5 w-full justify-between">
                    <span class="inline-flex items-center gap-1 font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/70">
                        <i class="fa-solid fa-check text-[9px]"></i> <?php echo $count_lottt; ?> LOTTT
                    </span>
                    <span class="inline-flex items-center gap-1 font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200/70">
                        <i class="fa-solid fa-clock-rotate-left text-[9px]"></i> <?php echo $count_legado; ?> Legadas
                    </span>
                </div>
            <?php else: ?>
                <span class="text-slate-500 font-medium flex items-center gap-1.5">
                    <i class="fa-solid fa-folder-open text-slate-400"></i> Sin registros activos
                </span>
                <span class="text-[10px] font-bold text-slate-400 uppercase">En espera</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card 2: Total Asignaciones Brutas -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.06)] hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Total Asignaciones</span>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Bruto</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-sm font-bold shadow-xs">
                    <i class="fa-solid fa-circle-dollar-to-slot"></i>
                </div>
            </div>
            <div class="flex items-baseline font-mono mt-1">
                <span class="text-xs font-bold text-slate-400 mr-1">Bs.</span>
                <span class="text-2xl lg:text-[26px] font-black text-slate-900 tracking-tight">
                    <?php echo number_format($total_asignaciones_gen, 2, ',', '.'); ?>
                </span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
            <span class="text-slate-500 font-medium">Promedio p/trabajador:</span>
            <span class="font-bold text-emerald-800 font-mono bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200/70">
                Bs. <?php echo number_format($promedio_asig, 2, ',', '.'); ?>
            </span>
        </div>
    </div>

    <!-- Card 3: Total Deducciones y Retenciones -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-[0_2px_10px_-3px_rgba(6,24,44,0.06)] hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Retenciones de Ley</span>
                    <span class="text-[10px] font-bold text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200">Aportes</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center text-sm font-bold shadow-xs">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <div class="flex items-baseline font-mono mt-1">
                <span class="text-xs font-bold text-rose-400 mr-1">Bs.</span>
                <span class="text-2xl lg:text-[26px] font-black text-rose-700 tracking-tight">
                    <?php echo number_format($total_deducciones_gen, 2, ',', '.'); ?>
                </span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
            <span class="text-slate-500 font-medium">Tasa de retención:</span>
            <span class="font-bold text-rose-800 font-mono bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200/70">
                <?php echo number_format($pct_deducciones, 1, ',', '.'); ?>% del bruto
            </span>
        </div>
    </div>

    <!-- Card 4: Total Neto Consolidado (Tarjeta Ejecutiva Hero) -->
    <div class="bg-gradient-to-br from-[#0c243d] via-[#0f2b48] to-[#08192a] text-white rounded-2xl p-5 shadow-[0_4px_16px_rgba(15,43,72,0.25)] hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 relative overflow-hidden border border-blue-900/50 flex flex-col justify-between group">
        <!-- Resplandor sutil de fondo -->
        <div class="absolute -right-6 -bottom-6 w-28 h-28 bg-amber-400/10 rounded-full blur-xl pointer-events-none group-hover:scale-125 transition-transform duration-500"></div>

        <div class="relative z-10">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-blue-200">Total Neto a Pagar</span>
                    <span class="text-[10px] font-extrabold text-amber-300 bg-amber-400/15 border border-amber-400/30 px-1.5 py-0.5 rounded">Líquido</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-yellow-500 text-brand-dark flex items-center justify-center text-sm font-black shadow-sm">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="flex items-baseline font-mono mt-1">
                <span class="text-xs font-bold text-amber-400 mr-1.5">Bs.</span>
                <span class="text-2xl lg:text-[28px] font-black text-white tracking-tight">
                    <?php echo number_format($total_general, 2, ',', '.'); ?>
                </span>
            </div>
        </div>

        <div class="mt-4 pt-3 border-t border-white/10 flex items-center justify-between text-[11px] relative z-10">
            <span class="text-slate-300 font-medium">Promedio neto p/exp:</span>
            <span class="font-black text-amber-300 font-mono">
                Bs. <?php echo number_format($promedio_neto, 2, ',', '.'); ?>
            </span>
        </div>
    </div>

</div>



<!-- CONTENEDOR PRINCIPAL DE LA TABLA Y HERRAMIENTAS INTEGRADAS -->
<div class="glass-card rounded-2xl overflow-hidden shadow-sm border border-slate-300">
    <!-- BARRA SUPERIOR DE ACCIONES Y EXPORTACIÓN DE LA TABLA (Reorganizada) -->
    <div class="p-5 bg-gradient-to-r from-slate-50 via-white to-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-table-list text-brand-blue"></i> Consolidado por Empleado
                </h2>
                <span class="text-xs font-extrabold text-slate-800 bg-slate-200/90 border border-slate-300 px-2.5 py-0.5 rounded-full">
                    <?php echo count($resumen); ?> <?php echo count($resumen) === 1 ? 'registro' : 'registros'; ?>
                </span>
            </div>
            <p class="text-xs font-bold text-slate-700 mt-0.5">
                Hoja resumen oficial de prestaciones sociales con desglose de asignaciones, deducciones y neto a pagar
            </p>
        </div>

        <!-- BOTONES DE EXPORTACIÓN E IMPRESIÓN REUBICADOS ADECUADAMENTE JUNTO A LA TABLA -->
        <div class="flex flex-wrap items-center gap-2.5 no-print">
            <a href="export_excel.php?lote=all" class="bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white font-extrabold px-4 py-2 rounded-xl shadow-sm hover:shadow transition-all flex items-center gap-2 text-xs" title="Descargar libro Excel con todas las liquidaciones calculadas">
                <i class="fa-solid fa-file-excel text-sm text-white"></i> Exportar Lote Excel (.xls)
            </a>
            <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 active:bg-black text-white font-extrabold px-4 py-2 rounded-xl shadow-sm hover:shadow transition-all flex items-center gap-2 text-xs" title="Imprimir o exportar a PDF la hoja de resumen consolidado">
                <i class="fa-solid fa-print text-sm text-white"></i> Imprimir Resumen
            </button>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTRADO INTEGRADA DIRECTAMENTE SOBRE LA TABLA -->
    <div class="px-5 py-3 bg-white border-b border-slate-200 no-print flex flex-wrap justify-between items-center gap-3">
        <form method="GET" action="index.php" class="flex items-center gap-2 w-full sm:w-auto flex-1 max-w-lg">
            <div class="relative w-full">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por cédula, nombre, cargo o motivo..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-semibold text-slate-900 placeholder:text-slate-500 transition-all">
            </div>
            <button type="submit" class="bg-brand-blue hover:bg-blue-900 text-white px-4 py-2 rounded-xl font-extrabold text-xs transition-colors shrink-0 flex items-center gap-1.5 shadow-sm">
                <span>Buscar</span>
            </button>
            <?php if (!empty($search)): ?>
                <a href="index.php" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-3 py-2 rounded-xl font-bold text-xs transition-colors shrink-0 flex items-center gap-1">
                    <i class="fa-solid fa-xmark"></i> Limpiar
                </a>
            <?php endif; ?>
        </form>

        <?php if (!empty($search)): ?>
            <div class="text-xs font-bold text-slate-800 bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-xl flex items-center gap-2">
                <span>Filtrando por: <strong>"<?php echo htmlspecialchars($search); ?>"</strong></span>
                <span class="text-slate-600">(<?php echo count($resumen); ?> encontrados)</span>
            </div>
        <?php else: ?>
            <div class="text-xs font-semibold text-slate-600 hidden md:block">
                <i class="fa-solid fa-circle-info text-brand-blue mr-1"></i> Haga clic en <strong>Ver</strong> para revisar la liquidación completa
            </div>
        <?php endif; ?>
    </div>

    <!-- TABLA DE DATOS CONSOLIDADA -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-900 text-xs font-black uppercase tracking-wider border-b-2 border-slate-300">
                    <th class="px-4 py-3.5 text-center w-12">N°</th>
                    <th class="px-4 py-3.5">Empleado / Cédula</th>
                    <th class="px-4 py-3.5">Cargo & Motivo</th>
                    <th class="px-4 py-3.5 text-center">Régimen</th>
                    <th class="px-4 py-3.5 text-center">Fecha Egreso</th>
                    <th class="px-4 py-3.5 text-center">Antigüedad</th>
                    <th class="px-4 py-3.5 text-right">Total Asignaciones</th>
                    <th class="px-4 py-3.5 text-right">Deducciones</th>
                    <th class="px-4 py-3.5 text-right">Neto a Cobrar</th>
                    <th class="px-4 py-3.5 text-center no-print w-32">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-xs">
                <?php if(count($resumen) > 0): ?>
                    <?php $i = 1; foreach($resumen as $row): 
                        $es_legado = (isset($row['regla_perfil']) && $row['regla_perfil'] === 'LEGADO_120_180');
                    ?>
                    <tr class="hover:bg-blue-50/50 transition-colors">
                        <!-- N° -->
                        <td class="px-4 py-3.5 text-center font-bold text-slate-700">
                            <?php echo $i++; ?>
                        </td>

                        <!-- Empleado / Cédula -->
                        <td class="px-4 py-3.5">
                            <div class="font-black text-slate-900 text-sm leading-snug">
                                <?php echo htmlspecialchars($row['apellidos_nombres']); ?>
                            </div>
                            <div class="mt-0.5">
                                <span class="inline-block font-mono text-[11px] font-extrabold text-slate-800 bg-slate-100 border border-slate-300 px-2 py-0.5 rounded">
                                    CI: <?php echo htmlspecialchars($row['cedula']); ?>
                                </span>
                            </div>
                        </td>

                        <!-- Cargo & Motivo -->
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-800 text-xs">
                                <?php echo htmlspecialchars($row['cargo'] ?: 'No asignado'); ?>
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-0.5 flex items-center gap-1">
                                <i class="fa-solid fa-tag text-[10px] text-slate-500"></i>
                                <?php echo htmlspecialchars($row['motivo'] ?: 'Renuncia'); ?>
                            </div>
                        </td>

                        <!-- Régimen -->
                        <td class="px-4 py-3.5 text-center">
                            <?php if($es_legado): ?>
                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300">
                                    Legada (120/180)
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-900 border border-emerald-300">
                                    LOTTT (30d)
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Fecha Egreso -->
                        <td class="px-4 py-3.5 text-center font-bold text-slate-800 whitespace-nowrap">
                            <?php echo !empty($row['fecha_egreso']) ? date('d/m/Y', strtotime($row['fecha_egreso'])) : '-'; ?>
                        </td>

                        <!-- Antigüedad -->
                        <td class="px-4 py-3.5 text-center font-bold text-slate-800 whitespace-nowrap">
                            <span class="bg-slate-100 border border-slate-200 px-2 py-1 rounded-md font-semibold">
                                <?php echo "{$row['anos']}a, {$row['meses']}m, {$row['dias']}d"; ?>
                            </span>
                        </td>

                        <!-- Asignaciones -->
                        <td class="px-4 py-3.5 text-right font-mono font-extrabold text-slate-900 whitespace-nowrap">
                            Bs. <?php echo number_format($row['total_asignaciones'], 2, ',', '.'); ?>
                        </td>

                        <!-- Deducciones -->
                        <td class="px-4 py-3.5 text-right font-mono font-extrabold text-rose-700 whitespace-nowrap">
                            Bs. <?php echo number_format($row['total_deducciones'], 2, ',', '.'); ?>
                        </td>

                        <!-- Neto a Cobrar -->
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <span class="inline-block font-mono font-black text-brand-blue bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-lg text-sm shadow-xs">
                                Bs. <?php echo number_format($row['total_prestaciones'], 2, ',', '.'); ?>
                            </span>
                        </td>

                        <!-- Acciones -->
                        <td class="px-4 py-3.5 text-center no-print whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <a href="prestaciones_view.php?id=<?php echo $row['prestacion_id']; ?>" class="bg-brand-blue hover:bg-blue-900 text-white px-2.5 py-1.5 rounded-lg font-extrabold text-xs transition-colors inline-flex items-center gap-1 shadow-xs" title="Ver Liquidación Completa">
                                    <i class="fa-solid fa-eye text-xs"></i> Ver
                                </a>
                                <a href="export_excel.php?id=<?php echo $row['prestacion_id']; ?>" class="bg-emerald-100 hover:bg-emerald-200 text-emerald-800 border border-emerald-300 px-2 py-1.5 rounded-lg font-bold text-xs transition-colors inline-flex items-center" title="Descargar Excel de esta liquidación">
                                    <i class="fa-solid fa-file-excel text-xs"></i>
                                </a>
                                <a href="prestaciones_form.php?id=<?php echo $row['prestacion_id']; ?>&empleado_id=<?php echo $row['empleado_id']; ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 px-2 py-1.5 rounded-lg font-bold text-xs transition-colors inline-flex items-center" title="Editar cálculo">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- ESTADO VACÍO ELEGANTE -->
                    <tr>
                        <td colspan="10" class="px-6 py-14 text-center">
                            <div class="flex flex-col items-center justify-center gap-3 max-w-md mx-auto">
                                <div class="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 text-2xl">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <h4 class="text-base font-black text-slate-900">
                                    <?php echo !empty($search) ? 'Sin coincidencias de búsqueda' : 'No hay liquidaciones registradas'; ?>
                                </h4>
                                <p class="text-xs font-semibold text-slate-700 text-center leading-relaxed">
                                    <?php echo !empty($search) 
                                        ? 'No se encontraron cálculos que coincidan con el término buscado. Verifique la cédula o nombre ingresado.' 
                                        : 'Aún no se han generado liquidaciones o cálculos de prestaciones. Puede iniciar un nuevo cálculo utilizando los accesos directos superiores.'; ?>
                                </p>
                                <div class="flex items-center gap-2 mt-2">
                                    <?php if(!empty($search)): ?>
                                        <a href="index.php" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-4 py-2 rounded-xl font-bold text-xs transition-colors">
                                            Limpiar búsqueda
                                        </a>
                                    <?php endif; ?>
                                    <a href="prestaciones_form.php?regla_perfil=LOTTT_30" class="bg-brand-blue hover:bg-blue-900 text-white px-4 py-2 rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5 shadow-sm">
                                        <i class="fa-solid fa-plus"></i> Generar Primer Cálculo
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>

            <!-- TOTALES CONSOLIDADOS (FOOTER DE TABLA) -->
            <?php if(count($resumen) > 0): ?>
            <tfoot class="bg-slate-100 font-extrabold border-t-2 border-slate-300 text-xs">
                <tr>
                    <td colspan="6" class="px-4 py-4 text-right text-slate-900 uppercase tracking-wider font-black">
                        Totales Consolidados (<?php echo count($resumen); ?> liquidaciones):
                    </td>
                    <td class="px-4 py-4 text-right font-mono font-black text-slate-900 whitespace-nowrap">
                        Bs. <?php echo number_format($total_asignaciones_gen, 2, ',', '.'); ?>
                    </td>
                    <td class="px-4 py-4 text-right font-mono font-black text-rose-700 whitespace-nowrap">
                        Bs. <?php echo number_format($total_deducciones_gen, 2, ',', '.'); ?>
                    </td>
                    <td class="px-4 py-4 text-right whitespace-nowrap">
                        <span class="inline-block font-mono font-black text-brand-blue bg-blue-100/80 border border-blue-300 px-3 py-1 rounded-lg text-sm">
                            Bs. <?php echo number_format($total_general, 2, ',', '.'); ?>
                        </span>
                    </td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- OVERLAY BACKDROP (Transparente para cerrar al hacer clic fuera) -->
<div id="quickDockBackdrop" onclick="toggleQuickDock(false)" class="fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-40 hidden transition-opacity duration-300 opacity-0 no-print"></div>

<!-- BARRA LATERAL DERECHA DESLIZABLE DE ACCESOS RÁPIDOS (Sale parcialmente por la derecha) -->
<aside id="quickDockPanel" class="fixed top-0 right-0 h-full w-80 sm:w-96 bg-white shadow-[-8px_0_30px_rgba(0,0,0,0.15)] border-l border-slate-200 z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col no-print">
    
    <!-- PESTAÑA SALIENTE (Visible parcialmente en el borde derecho, exactamente como solicitó el usuario) -->
    <button id="quickDockTrigger" onclick="toggleQuickDock()" type="button" class="absolute -left-10 top-1/2 -translate-y-1/2 bg-brand-blue text-white py-4 px-2 rounded-l-2xl shadow-[-5px_2px_14px_rgba(15,43,72,0.3)] border-l-2 border-y-2 border-amber-400/50 hover:bg-blue-900 transition-all flex flex-col items-center gap-2 group cursor-pointer focus:outline-none" title="Abrir / Ocultar Accesos Rápidos">
        <div class="w-6 h-6 rounded-lg bg-amber-400 text-brand-dark flex items-center justify-center text-xs font-black group-hover:scale-110 transition-transform shadow-xs">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-amber-300 [writing-mode:vertical-lr] rotate-180 select-none py-1.5">
            Accesos Rápidos
        </span>
        <i id="quickDockArrow" class="fa-solid fa-chevron-left text-[10px] text-slate-300 group-hover:-translate-x-0.5 transition-transform"></i>
    </button>

    <!-- CABECERA DE LA BARRA LATERAL -->
    <div class="p-5 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-amber-400/20 text-amber-700 flex items-center justify-center font-bold text-sm shadow-xs">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <div>
                <h3 class="font-black text-slate-900 text-sm leading-tight">Accesos Rápidos</h3>
                <p class="text-[11px] font-semibold text-slate-500">Módulos directos de cálculo</p>
            </div>
        </div>
        <button onclick="toggleQuickDock(false)" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition-colors cursor-pointer" title="Cerrar panel">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <!-- LISTA DE ACCESOS DIRECTOS -->
    <div class="flex-1 overflow-y-auto p-5 space-y-3 custom-scrollbar">
        <!-- 1: Liquidación LOTTT vigente -->
        <a href="prestaciones_form.php?regla_perfil=LOTTT_30" class="block p-3.5 rounded-xl border border-emerald-200 bg-gradient-to-br from-white to-emerald-50/40 hover:border-emerald-500 hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded">
                    LOTTT Vigente (Art. 142)
                </span>
                <i class="fa-solid fa-file-circle-plus text-emerald-600 group-hover:scale-110 transition-transform"></i>
            </div>
            <h4 class="font-extrabold text-slate-900 text-xs group-hover:text-emerald-700 transition-colors">
                Liquidación LOTTT (30 Días)
            </h4>
            <p class="text-[11px] text-slate-600 font-medium mt-1 leading-snug">
                Cálculo estándar según Ley Orgánica con 30 días utilidades y 15 días vacaciones.
            </p>
            <div class="mt-2.5 pt-2 border-t border-emerald-100 text-[10px] font-black text-emerald-700 flex items-center justify-between">
                <span>Generar liquidación</span>
                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- 2: Regla Legada 120/180 -->
        <a href="prestaciones_form.php?regla_perfil=LEGADO_120_180" class="block p-3.5 rounded-xl border border-amber-200 bg-gradient-to-br from-white to-amber-50/40 hover:border-amber-500 hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900 bg-amber-100 px-2 py-0.5 rounded">
                    Régimen Legado 120/180
                </span>
                <i class="fa-solid fa-clock-rotate-left text-amber-600 group-hover:scale-110 transition-transform"></i>
            </div>
            <h4 class="font-extrabold text-slate-900 text-xs group-hover:text-amber-800 transition-colors">
                Liquidación Histórica (120/180)
            </h4>
            <p class="text-[11px] text-slate-600 font-medium mt-1 leading-snug">
                Cálculo retroactivo especial bajo esquema histórico de 120 utilidades y 180 vacaciones.
            </p>
            <div class="mt-2.5 pt-2 border-t border-amber-100 text-[10px] font-black text-amber-800 flex items-center justify-between">
                <span>Generar liquidación</span>
                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- 3: Historial de liquidaciones -->
        <a href="catalogo.php" class="block p-3.5 rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 hover:border-brand-blue hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-700 bg-slate-200 px-2 py-0.5 rounded">
                    Catálogo
                </span>
                <i class="fa-solid fa-folder-open text-brand-blue group-hover:scale-110 transition-transform"></i>
            </div>
            <h4 class="font-extrabold text-slate-900 text-xs group-hover:text-brand-blue transition-colors">
                Catálogo de Expedientes
            </h4>
            <p class="text-[11px] text-slate-600 font-medium mt-1 leading-snug">
                Visualice todas las liquidaciones en vista mosaico o fichas individuales.
            </p>
            <div class="mt-2.5 pt-2 border-t border-slate-100 text-[10px] font-black text-slate-800 flex items-center justify-between">
                <span>Abrir catálogo</span>
                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- 4: Gestión Masiva por Lotes -->
        <a href="lotes.php" class="block p-3.5 rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 hover:border-brand-blue hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-700 bg-slate-200 px-2 py-0.5 rounded">
                    Lotes
                </span>
                <i class="fa-solid fa-layer-group text-slate-700 group-hover:scale-110 transition-transform"></i>
            </div>
            <h4 class="font-extrabold text-slate-900 text-xs group-hover:text-brand-blue transition-colors">
                Gestión Masiva por Lotes
            </h4>
            <p class="text-[11px] text-slate-600 font-medium mt-1 leading-snug">
                Procese e importe cálculos masivos para nóminas completas.
            </p>
            <div class="mt-2.5 pt-2 border-t border-slate-100 text-[10px] font-black text-slate-800 flex items-center justify-between">
                <span>Abrir gestión por lotes</span>
                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- 5: Nuevo Empleado -->
        <a href="empleado_form.php" class="block p-3.5 rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 hover:border-brand-blue hover:shadow-md transition-all group">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 bg-blue-100 px-2 py-0.5 rounded">
                    Personal
                </span>
                <i class="fa-solid fa-user-plus text-brand-blue group-hover:scale-110 transition-transform"></i>
            </div>
            <h4 class="font-extrabold text-slate-900 text-xs group-hover:text-brand-blue transition-colors">
                Registrar Nuevo Empleado
            </h4>
            <p class="text-[11px] text-slate-600 font-medium mt-1 leading-snug">
                Crear ficha de trabajador con datos salariales y fecha de ingreso.
            </p>
            <div class="mt-2.5 pt-2 border-t border-slate-100 text-[10px] font-black text-brand-blue flex items-center justify-between">
                <span>Crear empleado</span>
                <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>
    </div>

    <!-- PIE DEL PANEL -->
    <div class="p-4 border-t border-slate-200 bg-slate-50 text-center">
        <button onclick="toggleQuickDock(false)" class="w-full py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition-colors cursor-pointer flex items-center justify-center gap-2">
            <i class="fa-solid fa-chevron-right text-xs"></i> Ocultar barra lateral
        </button>
    </div>
</aside>

<!-- SCRIPT DE CONTROL PARA LA BARRA LATERAL DESLIZABLE -->
<script>
function toggleQuickDock(forceState) {
    const panel = document.getElementById('quickDockPanel');
    const backdrop = document.getElementById('quickDockBackdrop');
    const arrow = document.getElementById('quickDockArrow');
    if (!panel) return;
    
    const isClosed = panel.classList.contains('translate-x-full');
    const shouldOpen = (typeof forceState === 'boolean') ? forceState : isClosed;
    
    if (shouldOpen) {
        panel.classList.remove('translate-x-full');
        if (backdrop) {
            backdrop.classList.remove('hidden');
            setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
        }
        if (arrow) {
            arrow.classList.remove('fa-chevron-left');
            arrow.classList.add('fa-chevron-right');
        }
    } else {
        panel.classList.add('translate-x-full');
        if (backdrop) {
            backdrop.classList.add('opacity-0');
            setTimeout(() => backdrop.classList.add('hidden'), 300);
        }
        if (arrow) {
            arrow.classList.remove('fa-chevron-right');
            arrow.classList.add('fa-chevron-left');
        }
    }
}

// Control por teclado: Barra espaciadora para alternar y Escape para cerrar
document.addEventListener('keydown', function(e) {
    // Si el usuario está escribiendo en un campo de texto o formulario, permitir escribir espacio normalmente
    const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
    const isEditable = document.activeElement ? document.activeElement.isContentEditable : false;
    const isInput = (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select' || isEditable);

    // Barra espaciadora: abrir / cerrar panel lateral
    if (e.code === 'Space' || e.key === ' ') {
        if (!isInput) {
            e.preventDefault(); // Prevenir el desplazamiento (scroll) default del navegador
            toggleQuickDock();
        }
    }

    // Tecla Escape: cerrar panel lateral
    if (e.key === 'Escape') {
        toggleQuickDock(false);
    }
});
</script>

<?php include 'includes/footer.php'; ?>

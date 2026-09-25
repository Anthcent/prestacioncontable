<?php
require_once 'config/database.php';

// ==========================================
// PROCESAMIENTO DE FILTROS (GET)
// ==========================================
$fecha_desde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fecha_hasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$periodo_rapido = isset($_GET['periodo_rapido']) ? trim($_GET['periodo_rapido']) : '';
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
$motivo = isset($_GET['motivo']) ? trim($_GET['motivo']) : '';
$regla_perfil = isset($_GET['regla_perfil']) ? trim($_GET['regla_perfil']) : '';
$filtro_deducciones = isset($_GET['filtro_deducciones']) ? trim($_GET['filtro_deducciones']) : 'todas';
$orden = isset($_GET['orden']) ? trim($_GET['orden']) : 'fecha_desc';

// Ajustar fechas según preset de período rápido si se seleccionó uno
if ($periodo_rapido === 'mes_actual') {
    $fecha_desde = date('Y-m-01');
    $fecha_hasta = date('Y-m-t');
} elseif ($periodo_rapido === 'ultimos_3_meses') {
    $fecha_desde = date('Y-m-d', strtotime('-3 months'));
    $fecha_hasta = date('Y-m-d');
} elseif ($periodo_rapido === 'ano_actual') {
    $fecha_desde = date('Y-01-01');
    $fecha_hasta = date('Y-12-31');
} elseif ($periodo_rapido === 'historico') {
    $fecha_desde = '';
    $fecha_hasta = '';
}

// Construir consulta SQL con filtros
$where = ["1=1"];
$params = [];

if (!empty($fecha_desde)) {
    $where[] = "p.fecha_calculo >= ?";
    $params[] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $where[] = "p.fecha_calculo <= ?";
    $params[] = $fecha_hasta;
}
if ($empleado_id > 0) {
    $where[] = "p.empleado_id = ?";
    $params[] = $empleado_id;
}
if (!empty($motivo)) {
    $where[] = "LOWER(TRIM(p.motivo)) = LOWER(TRIM(?))";
    $params[] = $motivo;
}
if (!empty($regla_perfil)) {
    $where[] = "p.regla_perfil = ?";
    $params[] = $regla_perfil;
}
if ($filtro_deducciones === 'con_deducciones') {
    $where[] = "p.aplicar_deducciones = 1";
} elseif ($filtro_deducciones === 'sin_deducciones') {
    $where[] = "(p.aplicar_deducciones = 0 OR p.aplicar_deducciones IS NULL)";
}

// Ordenamiento
$order_sql = "p.fecha_calculo DESC, p.id DESC";
if ($orden === 'fecha_asc') $order_sql = "p.fecha_calculo ASC, p.id ASC";
elseif ($orden === 'monto_desc') $order_sql = "p.neto_a_cobrar DESC";
elseif ($orden === 'monto_asc') $order_sql = "p.neto_a_cobrar ASC";
elseif ($orden === 'nombre_asc') $order_sql = "e.apellidos_nombres ASC";

$sql = "
    SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo, e.fecha_ingreso 
    FROM prestaciones p 
    JOIN empleados e ON p.empleado_id = e.id 
    WHERE " . implode(" AND ", $where) . " 
    ORDER BY {$order_sql}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// CÁLCULO DE TOTALES Y RESÚMENES (KPIS)
// ==========================================
$total_registros = count($reportes);
$total_neto = 0.00;
$total_asignaciones = 0.00;
$total_deducciones = 0.00;
$total_antiguedad = 0.00;
$total_vacaciones = 0.00;
$total_utilidades = 0.00;
$total_anos_servicio = 0.00;

foreach ($reportes as $r) {
    $total_neto += (float)$r['neto_a_cobrar'];
    $total_asignaciones += (float)$r['total_asignaciones'];
    $total_deducciones += (float)$r['total_deducciones'];
    $total_antiguedad += (float)$r['antig_monto_total'];
    $total_vacaciones += (float)$r['vac190_total'] + (float)$r['vac196_total'];
    $total_utilidades += (float)$r['util_total'];
    $total_anos_servicio += (float)$r['antig_anos'];
}

$promedio_neto = $total_registros > 0 ? ($total_neto / $total_registros) : 0.00;
$promedio_anos = $total_registros > 0 ? ($total_anos_servicio / $total_registros) : 0.00;

// ==========================================
// EXPORTACIÓN A CSV
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_liquidaciones_' . date('Ymd_His') . '.csv"');
    
    // Abrir flujo de salida y agregar BOM UTF-8 para compatibilidad perfecta con Excel
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeceras del CSV
    fputcsv($out, [
        'ID',
        'Cedula',
        'Empleado',
        'Cargo',
        'Fecha Ingreso',
        'Fecha Egreso',
        'Fecha Calculo',
        'Anos Servicio',
        'Motivo Egreso',
        'Regla Perfil',
        'Sueldo Normal Mensual (Bs)',
        'Sueldo Normal Diario (Bs)',
        'Salario Diario Integral (Bs)',
        'Salario Integral Mensual (Bs)',
        'Utilidades (Bs)',
        'Vacaciones Art. 190 (Bs)',
        'Vacaciones Art. 196 (Bs)',
        'Antiguedad Art. 142 (Bs)',
        'Otras Asignaciones (Bs)',
        'Total Asignaciones (Bs)',
        'Total Deducciones (Bs)',
        'Neto a Cobrar (Bs)'
    ], ';');

    foreach ($reportes as $r) {
        fputcsv($out, [
            $r['id'],
            $r['cedula'],
            $r['apellidos_nombres'],
            $r['cargo'],
            $r['fecha_ingreso'],
            $r['fecha_egreso'],
            $r['fecha_calculo'],
            number_format((float)$r['antig_anos'], 2, ',', '.'),
            $r['motivo'],
            $r['regla_perfil'],
            number_format((float)$r['sueldo_mensual_normal'], 2, ',', '.'),
            number_format((float)$r['sueldo_diario_normal'], 2, ',', '.'),
            number_format((float)$r['salario_diario_integral'], 2, ',', '.'),
            number_format((float)$r['salario_integral_mensual'], 2, ',', '.'),
            number_format((float)$r['util_total'], 2, ',', '.'),
            number_format((float)$r['vac190_total'], 2, ',', '.'),
            number_format((float)$r['vac196_total'], 2, ',', '.'),
            number_format((float)$r['antig_monto_total'], 2, ',', '.'),
            number_format((float)$r['otras_asignaciones'], 2, ',', '.'),
            number_format((float)$r['total_asignaciones'], 2, ',', '.'),
            number_format((float)$r['total_deducciones'], 2, ',', '.'),
            number_format((float)$r['neto_a_cobrar'], 2, ',', '.')
        ], ';');
    }

    // Fila de Totales Generales
    fputcsv($out, [
        'TOTALES', '', '', '', '', '', '', '', '', '',
        '', '', '', '',
        number_format($total_utilidades, 2, ',', '.'),
        '', '',
        number_format($total_antiguedad, 2, ',', '.'),
        '',
        number_format($total_asignaciones, 2, ',', '.'),
        number_format($total_deducciones, 2, ',', '.'),
        number_format($total_neto, 2, ',', '.')
    ], ';');

    fclose($out);
    exit;
}

// Cargar listas para los filtros
$empleados_list = $pdo->query("SELECT id, cedula, apellidos_nombres, cargo FROM empleados ORDER BY apellidos_nombres ASC")->fetchAll(PDO::FETCH_ASSOC);
$motivos_list = $pdo->query("SELECT DISTINCT motivo FROM prestaciones WHERE motivo IS NOT NULL AND motivo != '' ORDER BY motivo ASC")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<style>
@media print {
    body * { visibility: hidden; }
    #seccion-impresion-reporte, #seccion-impresion-reporte * { visibility: visible; }
    #seccion-impresion-reporte {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
        border: none;
        box-shadow: none;
    }
    .no-print { display: none !important; }
}
</style>

<div id="seccion-impresion-reporte">

    <!-- ENCABEZADO INSTITUCIONAL EXCLUSIVO PARA IMPRESIÓN -->
    <div class="hidden print:block mb-6 text-center border-b-2 border-slate-800 pb-4">
        <div class="flex justify-between items-center mb-2">
            <div class="text-left">
                <p class="font-extrabold text-sm uppercase tracking-wider text-slate-900">PRIME CONTADORES PÚBLICOS</p>
                <p class="text-xs text-slate-600">SISTEMA INTEGRAL DE NÓMINA Y PRESTACIONES SOCIALES</p>
                <p class="text-[10px] text-slate-500">República Bolivariana de Venezuela</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-slate-700">Fecha de Emisión: <?php echo date('d/m/Y h:i A'); ?></p>
                <p class="text-[10px] text-slate-500">Reporte Ejecutivo de Auditoría</p>
            </div>
        </div>
        <h1 class="text-lg font-black uppercase text-slate-900 tracking-wide mt-2">
            REPORTE RESUMEN CONSOLIDADO DE PRESTACIONES SOCIALES Y LIQUIDACIONES
        </h1>
        <p class="text-xs font-medium text-slate-700 mt-1">
            <?php 
                $txt_rango = "Período: Histórico General";
                if (!empty($fecha_desde) && !empty($fecha_hasta)) {
                    $txt_rango = "Período del " . date('d/m/Y', strtotime($fecha_desde)) . " al " . date('d/m/Y', strtotime($fecha_hasta));
                } elseif (!empty($fecha_desde)) {
                    $txt_rango = "A partir del " . date('d/m/Y', strtotime($fecha_desde));
                } elseif (!empty($fecha_hasta)) {
                    $txt_rango = "Hasta el " . date('d/m/Y', strtotime($fecha_hasta));
                }
                echo $txt_rango;
            ?>
        </p>
    </div>

    <!-- BANNER SUPERIOR CORPORATIVO -->
    <div class="bg-gradient-to-r from-brand-dark via-slate-900 to-brand-blue text-white rounded-2xl p-6 mb-6 shadow-xl relative overflow-hidden no-print">
        <div class="relative z-10 flex flex-wrap justify-between items-center gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="bg-brand-yellow text-brand-dark font-extrabold text-xs px-3 py-1 rounded-full uppercase tracking-wider">
                        MÓDULO DE REPORTES Y AUDITORÍA
                    </span>
                    <span class="text-xs text-amber-300 font-semibold flex items-center gap-1.5">
                        <img src="assets/img/logo_icon.png" alt="PRIME" class="w-4 h-4 object-contain inline"> PRIME CONTADORES PÚBLICOS
                    </span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight">
                    📊 Resúmenes Finales y Reportes de Liquidación
                </h2>
                <p class="text-xs text-slate-300 mt-1 max-w-2xl">
                    Consolidado de planillas procesadas, desglose analítico de asignaciones (LOTTT / Gobernación), retenciones y totales netos con filtros avanzados por tiempo y motivos.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <!-- Botón Imprimir -->
                <button type="button" onclick="window.print()" class="bg-white/10 hover:bg-white/20 text-white font-bold px-4 py-2.5 rounded-xl border border-white/20 shadow text-xs transition-all flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-print text-amber-400"></i> Imprimir Reporte
                </button>
                <!-- Botón Exportar CSV -->
                <?php 
                    $export_query = $_GET;
                    $export_query['export'] = 'csv';
                    $export_url = 'reportes.php?' . http_build_query($export_query);
                ?>
                <a href="<?php echo htmlspecialchars($export_url); ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 rounded-xl shadow text-xs transition-all flex items-center gap-2">
                    <i class="fa-solid fa-file-excel"></i> Exportar a Excel (CSV)
                </a>
            </div>
        </div>
    </div>

    <!-- TARJETAS RESUMEN / KPIS EJECUTIVOS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <!-- KPI 1: Total Liquidaciones -->
        <div class="glass-card rounded-2xl p-4 border border-slate-200 shadow-sm bg-white/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Planillas Filtradas</span>
                <span class="w-7 h-7 rounded-lg bg-blue-50 text-brand-blue flex items-center justify-center text-xs">
                    <i class="fa-solid fa-file-invoice"></i>
                </span>
            </div>
            <p class="text-2xl font-black text-slate-900 mt-2 tracking-tight"><?php echo number_format($total_registros, 0, ',', '.'); ?></p>
            <span class="text-[11px] text-slate-500 font-medium">Registros en el reporte</span>
        </div>

        <!-- KPI 2: Total Asignaciones -->
        <div class="glass-card rounded-2xl p-4 border border-slate-200 shadow-sm bg-white/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Asignaciones</span>
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-emerald-800 mt-2 truncate tracking-tight" title="Bs. <?php echo number_format($total_asignaciones, 2, ',', '.'); ?>">
                Bs. <?php echo number_format($total_asignaciones, 2, ',', '.'); ?>
            </p>
            <span class="text-[11px] text-slate-500 font-medium">Beneficios causados</span>
        </div>

        <!-- KPI 3: Total Deducciones -->
        <div class="glass-card rounded-2xl p-4 border border-slate-200 shadow-sm bg-white/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Deducciones</span>
                <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-rose-700 mt-2 truncate tracking-tight" title="Bs. <?php echo number_format($total_deducciones, 2, ',', '.'); ?>">
                Bs. <?php echo number_format($total_deducciones, 2, ',', '.'); ?>
            </p>
            <span class="text-[11px] text-slate-500 font-medium">Retenciones y fideicomiso</span>
        </div>

        <!-- KPI 4: Gran Total Neto -->
        <div class="glass-card rounded-2xl p-4 border border-brand-blue/30 shadow-md bg-gradient-to-br from-blue-50/70 to-white">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-brand-blue uppercase tracking-wider">Total Neto a Cobrar</span>
                <span class="w-7 h-7 rounded-lg bg-brand-blue text-amber-300 flex items-center justify-center text-xs shadow-xs">
                    <i class="fa-solid fa-sack-dollar"></i>
                </span>
            </div>
            <p class="text-xl sm:text-2xl font-black text-brand-blue mt-2 truncate tracking-tight" title="Bs. <?php echo number_format($total_neto, 2, ',', '.'); ?>">
                Bs. <?php echo number_format($total_neto, 2, ',', '.'); ?>
            </p>
            <span class="text-[11px] text-brand-blue/80 font-bold">Monto final desembolsado</span>
        </div>

        <!-- KPI 5: Promedio por Liquidación -->
        <div class="glass-card rounded-2xl p-4 border border-slate-200 shadow-sm bg-white/80">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Promedio / Caso</span>
                <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-calculator"></i>
                </span>
            </div>
            <p class="text-lg sm:text-xl font-black text-indigo-900 mt-2 truncate tracking-tight" title="Bs. <?php echo number_format($promedio_neto, 2, ',', '.'); ?>">
                Bs. <?php echo number_format($promedio_neto, 2, ',', '.'); ?>
            </p>
            <span class="text-[11px] text-slate-500 font-medium">Antig. prom: <?php echo number_format($promedio_anos, 1, ',', '.'); ?> años</span>
        </div>
    </div>

    <!-- PANEL DE FILTROS AVANZADOS (NO-PRINT) -->
    <div class="glass-card rounded-2xl p-5 mb-6 border border-slate-200/80 shadow-sm no-print">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-4">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-blue-50 text-brand-blue flex items-center justify-center text-xs">
                    <i class="fa-solid fa-filter"></i>
                </span>
                <h3 class="text-sm font-extrabold text-slate-800">Filtros y Parámetros del Reporte</h3>
            </div>

            <!-- Accesos Rápidos de Tiempo -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[11px] font-bold text-slate-500 mr-1">Tiempo Rápido:</span>
                <button type="button" onclick="aplicarRangoRapido('mes_actual')" class="px-2.5 py-1 text-xs font-bold rounded-lg border <?php echo ($periodo_rapido === 'mes_actual') ? 'bg-brand-blue text-white border-brand-blue' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'; ?> transition-colors cursor-pointer">
                    Este Mes
                </button>
                <button type="button" onclick="aplicarRangoRapido('ultimos_3_meses')" class="px-2.5 py-1 text-xs font-bold rounded-lg border <?php echo ($periodo_rapido === 'ultimos_3_meses') ? 'bg-brand-blue text-white border-brand-blue' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'; ?> transition-colors cursor-pointer">
                    Últimos 3 Meses
                </button>
                <button type="button" onclick="aplicarRangoRapido('ano_actual')" class="px-2.5 py-1 text-xs font-bold rounded-lg border <?php echo ($periodo_rapido === 'ano_actual') ? 'bg-brand-blue text-white border-brand-blue' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'; ?> transition-colors cursor-pointer">
                    Año <?php echo date('Y'); ?>
                </button>
                <button type="button" onclick="aplicarRangoRapido('historico')" class="px-2.5 py-1 text-xs font-bold rounded-lg border <?php echo ($periodo_rapido === 'historico' || (empty($fecha_desde) && empty($fecha_hasta) && empty($periodo_rapido))) ? 'bg-brand-blue text-white border-brand-blue' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'; ?> transition-colors cursor-pointer">
                    Histórico Todo
                </button>
            </div>
        </div>

        <form method="GET" action="reportes.php" id="form-filtros" class="space-y-4">
            <input type="hidden" name="periodo_rapido" id="periodo_rapido_input" value="<?php echo htmlspecialchars($periodo_rapido); ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
                <!-- Fecha Desde -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Fecha Desde:</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none">
                </div>

                <!-- Fecha Hasta -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Fecha Hasta:</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none">
                </div>

                <!-- Trabajador -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Trabajador:</label>
                    <select name="empleado_id" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none">
                        <option value="0">-- Todos los Trabajadores --</option>
                        <?php foreach ($empleados_list as $emp_item): ?>
                            <option value="<?php echo $emp_item['id']; ?>" <?php echo ($empleado_id == $emp_item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp_item['cedula'] . ' — ' . $emp_item['apellidos_nombres']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Motivo de Egreso -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Motivo:</label>
                    <select name="motivo" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none">
                        <option value="">-- Todos --</option>
                        <?php foreach ($motivos_list as $mot_item): ?>
                            <option value="<?php echo htmlspecialchars($mot_item); ?>" <?php echo (strcasecmp($motivo, $mot_item) === 0) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mot_item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Regla Legal -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Regla Legal:</label>
                    <select name="regla_perfil" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none">
                        <option value="">-- Todas --</option>
                        <option value="LOTTT_30" <?php echo ($regla_perfil === 'LOTTT_30') ? 'selected' : ''; ?>>LOTTT Vigente (30d)</option>
                        <option value="LEGADO_120_180" <?php echo ($regla_perfil === 'LEGADO_120_180') ? 'selected' : ''; ?>>Legado Gobernación (120/180)</option>
                    </select>
                </div>
            </div>

            <!-- Fila secundaria de filtros y acciones -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
                <div class="flex items-center gap-4 flex-wrap">
                    <!-- Deducciones -->
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold text-slate-600 uppercase">Deducciones:</span>
                        <select name="filtro_deducciones" class="p-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 outline-none">
                            <option value="todas" <?php echo ($filtro_deducciones === 'todas') ? 'selected' : ''; ?>>Todas</option>
                            <option value="con_deducciones" <?php echo ($filtro_deducciones === 'con_deducciones') ? 'selected' : ''; ?>>Solo con Deducciones Activas</option>
                            <option value="sin_deducciones" <?php echo ($filtro_deducciones === 'sin_deducciones') ? 'selected' : ''; ?>>Solo Deducciones Omitidas</option>
                        </select>
                    </div>

                    <!-- Orden -->
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold text-slate-600 uppercase">Ordenar por:</span>
                        <select name="orden" class="p-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 outline-none">
                            <option value="fecha_desc" <?php echo ($orden === 'fecha_desc') ? 'selected' : ''; ?>>Fecha Cálculo (Reciente primero)</option>
                            <option value="fecha_asc" <?php echo ($orden === 'fecha_asc') ? 'selected' : ''; ?>>Fecha Cálculo (Antigua primero)</option>
                            <option value="monto_desc" <?php echo ($orden === 'monto_desc') ? 'selected' : ''; ?>>Mayor Monto Neto</option>
                            <option value="monto_asc" <?php echo ($orden === 'monto_asc') ? 'selected' : ''; ?>>Menor Monto Neto</option>
                            <option value="nombre_asc" <?php echo ($orden === 'nombre_asc') ? 'selected' : ''; ?>>Nombre Empleado (A - Z)</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="reportes.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors cursor-pointer flex items-center gap-1.5">
                        <i class="fa-solid fa-rotate-left"></i> Limpiar
                    </a>
                    <button type="submit" class="px-5 py-2 bg-brand-blue hover:bg-blue-900 text-white font-extrabold text-xs rounded-xl shadow transition-colors flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-magnifying-glass text-brand-yellow"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TABLA DE RESÚMENES CONSOLIDADOS -->
    <div class="glass-card rounded-2xl overflow-hidden shadow-sm border border-slate-200/80 mb-8 bg-white">
        
        <!-- Header de la Tabla -->
        <div class="p-4 bg-slate-50/80 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-table-list text-brand-blue"></i>
                    Desglose Analítico de Liquidaciones
                </h3>
                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                    Mostrando <?php echo count($reportes); ?> liquidaciones según los criterios seleccionados
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="text-xs bg-blue-50 text-brand-blue font-bold px-3 py-1 rounded-full border border-blue-100">
                    Total Neto: Bs. <?php echo number_format($total_neto, 2, ',', '.'); ?>
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-100/90 text-slate-700 text-[11px] font-extrabold uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-3">N° / Fecha</th>
                        <th class="py-3 px-3">Trabajador</th>
                        <th class="py-3 px-2 text-center">Tiempo Serv.</th>
                        <th class="py-3 px-2">Motivo / Regla</th>
                        <th class="py-3 px-3 text-right">Sueldo Normal</th>
                        <th class="py-3 px-3 text-right" title="Art. 131 LOTTT">Utilidades</th>
                        <th class="py-3 px-3 text-right" title="Art. 190 y 196 LOTTT">Vacaciones</th>
                        <th class="py-3 px-3 text-right" title="Art. 142 LOTTT">Antigüedad</th>
                        <th class="py-3 px-3 text-right">Total Asig.</th>
                        <th class="py-3 px-3 text-right">Deducciones</th>
                        <th class="py-3 px-3 text-right">Neto a Cobrar</th>
                        <th class="py-3 px-3 text-center no-print">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (count($reportes) > 0): ?>
                        <?php foreach ($reportes as $idx => $r): ?>
                            <tr class="hover:bg-blue-50/40 transition-colors">
                                <!-- N° / Fecha -->
                                <td class="py-3 px-3 align-middle">
                                    <span class="font-extrabold text-slate-800 block">#<?php echo $r['id']; ?></span>
                                    <span class="text-[10px] text-slate-500 font-medium block">
                                        <?php echo !empty($r['fecha_calculo']) ? date('d/m/Y', strtotime($r['fecha_calculo'])) : '-'; ?>
                                    </span>
                                </td>

                                <!-- Trabajador -->
                                <td class="py-3 px-3 align-middle">
                                    <a href="prestaciones_view.php?id=<?php echo $r['id']; ?>" class="font-bold text-slate-900 hover:text-brand-blue block truncate max-w-[180px]">
                                        <?php echo htmlspecialchars($r['apellidos_nombres']); ?>
                                    </a>
                                    <span class="text-[10px] text-slate-500 block">
                                        CI: <?php echo htmlspecialchars($r['cedula']); ?> • <?php echo htmlspecialchars($r['cargo']); ?>
                                    </span>
                                </td>

                                <!-- Tiempo de Servicio -->
                                <td class="py-3 px-2 align-middle text-center">
                                    <span class="inline-block bg-slate-100 text-slate-800 font-bold px-2 py-0.5 rounded text-[11px]">
                                        <?php echo number_format((float)$r['antig_anos'], 0); ?>a
                                    </span>
                                    <span class="text-[10px] text-slate-500 block mt-0.5">
                                        Egreso: <?php echo !empty($r['fecha_egreso']) ? date('d/m/Y', strtotime($r['fecha_egreso'])) : '-'; ?>
                                    </span>
                                </td>

                                <!-- Motivo y Regla -->
                                <td class="py-3 px-2 align-middle">
                                    <span class="font-bold text-slate-800 block text-[11px] truncate max-w-[120px]">
                                        <?php echo htmlspecialchars($r['motivo'] ?: 'Renuncia'); ?>
                                    </span>
                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded <?php echo ($r['regla_perfil'] === 'LEGADO_120_180') ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-900'; ?> inline-block mt-0.5">
                                        <?php echo ($r['regla_perfil'] === 'LEGADO_120_180') ? 'Legado (120/180)' : 'LOTTT (30d)'; ?>
                                    </span>
                                </td>

                                <!-- Sueldo Normal -->
                                <td class="py-3 px-3 align-middle text-right">
                                    <span class="font-bold text-slate-800 block">
                                        Bs. <?php echo number_format((float)$r['sueldo_mensual_normal'], 2, ',', '.'); ?>
                                    </span>
                                    <span class="text-[10px] text-slate-500 block">
                                        D: Bs. <?php echo number_format((float)$r['sueldo_diario_normal'], 2, ',', '.'); ?>
                                    </span>
                                </td>

                                <!-- Utilidades -->
                                <td class="py-3 px-3 align-middle text-right font-medium text-slate-700">
                                    Bs. <?php echo number_format((float)$r['util_total'], 2, ',', '.'); ?>
                                </td>

                                <!-- Vacaciones -->
                                <td class="py-3 px-3 align-middle text-right font-medium text-slate-700">
                                    Bs. <?php echo number_format((float)$r['vac190_total'] + (float)$r['vac196_total'], 2, ',', '.'); ?>
                                </td>

                                <!-- Antigüedad -->
                                <td class="py-3 px-3 align-middle text-right font-medium text-slate-700">
                                    Bs. <?php echo number_format((float)$r['antig_monto_total'], 2, ',', '.'); ?>
                                </td>

                                <!-- Total Asignaciones -->
                                <td class="py-3 px-3 align-middle text-right font-bold text-slate-800">
                                    Bs. <?php echo number_format((float)$r['total_asignaciones'], 2, ',', '.'); ?>
                                </td>

                                <!-- Deducciones -->
                                <td class="py-3 px-3 align-middle text-right text-rose-700 font-semibold">
                                    <?php if ((float)$r['total_deducciones'] > 0 && ($r['aplicar_deducciones'] ?? 1)): ?>
                                        -Bs. <?php echo number_format((float)$r['total_deducciones'], 2, ',', '.'); ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-medium">0,00</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Neto a Cobrar -->
                                <td class="py-3 px-3 align-middle text-right font-black text-brand-blue text-sm">
                                    Bs. <?php echo number_format((float)$r['neto_a_cobrar'], 2, ',', '.'); ?>
                                </td>

                                <!-- Acciones (no-print) -->
                                <td class="py-3 px-3 align-middle text-center no-print">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="prestaciones_view.php?id=<?php echo $r['id']; ?>" class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-brand-blue flex items-center justify-center transition-colors" title="Ver Planilla Oficial">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </a>
                                        <a href="prestaciones_form.php?id=<?php echo $r['id']; ?>&empleado_id=<?php echo $r['empleado_id']; ?>" class="w-7 h-7 rounded-lg bg-yellow-50 hover:bg-yellow-100 text-amber-700 flex items-center justify-center transition-colors" title="Editar Cálculo">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="py-8 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl mb-2">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No se encontraron liquidaciones para los filtros aplicados.</p>
                                    <p class="text-xs text-slate-400 mt-1">Intente cambiando el rango de fechas o limpiando los filtros.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

                <!-- FILA DE TOTALES GENERALES -->
                <tfoot>
                    <tr class="bg-brand-dark text-white font-black text-xs border-t-2 border-amber-400/80">
                        <td colspan="5" class="py-3.5 px-3 text-left tracking-wider uppercase text-amber-300">
                            TOTALES CONSOLIDADOS (<?php echo $total_registros; ?> LIQUIDACIONES)
                        </td>
                        <td class="py-3.5 px-3 text-right text-slate-200">
                            Bs. <?php echo number_format($total_utilidades, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3.5 px-3 text-right text-slate-200">
                            Bs. <?php echo number_format($total_vacaciones, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3.5 px-3 text-right text-slate-200">
                            Bs. <?php echo number_format($total_antiguedad, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3.5 px-3 text-right text-emerald-300">
                            Bs. <?php echo number_format($total_asignaciones, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3.5 px-3 text-right text-rose-300">
                            -Bs. <?php echo number_format($total_deducciones, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3.5 px-3 text-right text-brand-yellow text-sm font-black">
                            Bs. <?php echo number_format($total_neto, 2, ',', '.'); ?>
                        </td>
                        <td class="py-3.5 px-3 text-center no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- FIRMAS PARA MODO IMPRESIÓN -->
    <div class="hidden print:block mt-12 pt-6">
        <table class="w-full text-center text-xs">
            <tr>
                <td style="width: 33.3%; padding: 0 20px;">
                    <div class="border-b border-slate-900 pb-1 mb-1.5 h-12"></div>
                    <p class="font-bold text-slate-900">ANALISTA DE NÓMINA</p>
                    <p class="text-[10px] text-slate-600">Elaborado y Verificado</p>
                </td>
                <td style="width: 33.3%; padding: 0 20px;">
                    <div class="border-b border-slate-900 pb-1 mb-1.5 h-12"></div>
                    <p class="font-bold text-slate-900">JEFE DE RELACIONES LABORALES</p>
                    <p class="text-[10px] text-slate-600">Revisado y Conforme</p>
                </td>
                <td style="width: 33.3%; padding: 0 20px;">
                    <div class="border-b border-slate-900 pb-1 mb-1.5 h-12"></div>
                    <p class="font-bold text-slate-900">DIRECTOR GENERAL DE RECURSOS HUMANOS</p>
                    <p class="text-[10px] text-slate-600">Aprobado para Pago</p>
                </td>
            </tr>
        </table>
    </div>

</div>

<script>
function aplicarRangoRapido(preset) {
    const input = document.getElementById('periodo_rapido_input');
    if (input) input.value = preset;

    const fDesde = document.getElementById('fecha_desde');
    const fHasta = document.getElementById('fecha_hasta');
    const today = new Date();

    if (preset === 'mes_actual') {
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const lastDay = new Date(y, today.getMonth() + 1, 0).getDate();
        if (fDesde) fDesde.value = `${y}-${m}-01`;
        if (fHasta) fHasta.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
    } else if (preset === 'ultimos_3_meses') {
        const dPast = new Date(today);
        dPast.setMonth(dPast.getMonth() - 3);
        const yP = dPast.getFullYear();
        const mP = String(dPast.getMonth() + 1).padStart(2, '0');
        const dP = String(dPast.getDate()).padStart(2, '0');
        const y = today.getFullYear();
        const m = String(today.getMonth() + 1).padStart(2, '0');
        const d = String(today.getDate()).padStart(2, '0');
        if (fDesde) fDesde.value = `${yP}-${mP}-${dP}`;
        if (fHasta) fHasta.value = `${y}-${m}-${d}`;
    } else if (preset === 'ano_actual') {
        const y = today.getFullYear();
        if (fDesde) fDesde.value = `${y}-01-01`;
        if (fHasta) fHasta.value = `${y}-12-31`;
    } else if (preset === 'historico') {
        if (fDesde) fDesde.value = '';
        if (fHasta) fHasta.value = '';
    }

    document.getElementById('form-filtros').submit();
}
</script>

<?php include 'includes/footer.php'; ?>

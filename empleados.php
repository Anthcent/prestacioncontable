<?php
require_once 'config/database.php';

// Manejar creación / edición mediante el Modal Wizard (o formulario)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_empleado'])) {
    $emp_id = (int)($_POST['id'] ?? 0);
    $cedula = trim($_POST['cedula'] ?? '');
    // Capitalización adecuada de nombres y apellidos
    $nombres = mb_convert_case(trim($_POST['apellidos_nombres'] ?? ''), MB_CASE_TITLE, "UTF-8");
    $cargo = trim($_POST['cargo'] ?? '');
    $clase_cargo = trim($_POST['clase_cargo'] ?? '');
    $nivel = trim($_POST['nivel'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $fecha_ingreso = !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : null;

    if (empty($cedula) || empty($nombres) || empty($cargo)) {
        $error = "Por favor complete los campos obligatorios: Cédula, Nombres y Cargo.";
    } else {
        try {
            if ($emp_id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE empleados 
                    SET cedula = ?, apellidos_nombres = ?, cargo = ?, clase_cargo = ?, nivel = ?, categoria = ?, fecha_ingreso = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$cedula, $nombres, $cargo, $clase_cargo, $nivel, $categoria, $fecha_ingreso, $emp_id]);
                header("Location: empleados.php?msg=updated");
                exit;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO empleados (cedula, apellidos_nombres, cargo, clase_cargo, nivel, categoria, fecha_ingreso) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$cedula, $nombres, $cargo, $clase_cargo, $nivel, $categoria, $fecha_ingreso]);
                header("Location: empleados.php?msg=saved");
                exit;
            }
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $error = "La cédula '{$cedula}' ya se encuentra registrada para otro trabajador.";
            } else {
                $error = "Error al procesar el empleado: " . $e->getMessage();
            }
        }
    }
}

// Manejar eliminación
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM empleados WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: empleados.php?msg=deleted");
        exit;
    } catch(PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Filtros y Búsqueda
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoria_filtro = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$estado_filtro = isset($_GET['estado']) ? trim($_GET['estado']) : '';

// Consulta base con conteo de liquidaciones
$sql = "
    SELECT 
        e.*,
        COUNT(p.id) as total_liquidaciones,
        MAX(p.id) as ultima_prestacion_id
    FROM empleados e
    LEFT JOIN prestaciones p ON p.empleado_id = e.id
";

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(e.cedula LIKE :search OR e.apellidos_nombres LIKE :search OR e.cargo LIKE :search OR e.clase_cargo LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($categoria_filtro)) {
    $where[] = "e.categoria = :categoria";
    $params[':categoria'] = $categoria_filtro;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " GROUP BY e.id ";

// Filtro de Having para estado de liquidación
if ($estado_filtro === 'con_liquidacion') {
    $sql .= " HAVING total_liquidaciones > 0 ";
} elseif ($estado_filtro === 'sin_liquidacion') {
    $sql .= " HAVING total_liquidaciones = 0 ";
}

$sql .= " ORDER BY e.apellidos_nombres ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Métricas globales para los KPIs
$stmt_metrics = $pdo->query("
    SELECT 
        COUNT(e.id) as total,
        SUM(CASE WHEN e.categoria = 'Obrero' THEN 1 ELSE 0 END) as obreros,
        SUM(CASE WHEN e.categoria = 'Empleado' THEN 1 ELSE 0 END) as empleados_tipo,
        COUNT(DISTINCT p.empleado_id) as con_prestaciones
    FROM empleados e
    LEFT JOIN prestaciones p ON p.empleado_id = e.id
");
$metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);
$kpi_total = (int)($metrics['total'] ?? 0);
$kpi_obreros = (int)($metrics['obreros'] ?? 0);
$kpi_empleados = (int)($metrics['empleados_tipo'] ?? 0);
$kpi_con_liq = (int)($metrics['con_prestaciones'] ?? 0);
$kpi_activos = $kpi_total - $kpi_con_liq;

include 'includes/header.php'; 
?>

<!-- CABECERA PRINCIPAL DEL MÓDULO -->
<div class="mb-5">
    <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-100 text-brand-blue border border-blue-200">
            <i class="fa-solid fa-users"></i> Módulo de Personal
        </span>
    </div>
    <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
        <i class="fa-solid fa-address-book text-brand-blue"></i> Directorio de Empleados
    </h1>
    <p class="text-sm font-semibold text-slate-700 mt-1">
        Fichas laborales, clasificación de cargos, cálculo directo de liquidaciones y control de antigüedad
    </p>
</div>

<!-- MENSAJES DEL SISTEMA -->
<?php if(isset($_GET['msg'])): ?>
    <?php if($_GET['msg'] == 'saved'): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 p-4 mb-5 rounded-r-xl shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center gap-3 font-bold text-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                <span>El trabajador ha sido registrado exitosamente en el sistema.</span>
            </div>
            <a href="empleados.php" class="text-emerald-700 hover:text-emerald-900 text-xs font-black uppercase">Descartar</a>
        </div>
    <?php elseif($_GET['msg'] == 'updated'): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-900 p-4 mb-5 rounded-r-xl shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center gap-3 font-bold text-sm">
                <i class="fa-solid fa-circle-check text-blue-600 text-lg"></i>
                <span>Los datos del trabajador se actualizaron correctamente.</span>
            </div>
            <a href="empleados.php" class="text-blue-700 hover:text-blue-900 text-xs font-black uppercase">Descartar</a>
        </div>
    <?php elseif($_GET['msg'] == 'deleted'): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-900 p-4 mb-5 rounded-r-xl shadow-sm flex items-center justify-between" role="alert">
            <div class="flex items-center gap-3 font-bold text-sm">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg"></i>
                <span>El registro del empleado y sus liquidaciones asociadas fueron eliminados.</span>
            </div>
            <a href="empleados.php" class="text-rose-700 hover:text-rose-900 text-xs font-black uppercase">Descartar</a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if(isset($error)): ?>
    <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-900 p-4 mb-5 rounded-r-xl shadow-sm flex items-center justify-between" role="alert">
        <div class="flex items-center gap-3 font-bold text-sm">
            <i class="fa-solid fa-circle-xmark text-rose-600 text-lg"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <a href="empleados.php" class="text-rose-700 hover:text-rose-900 text-xs font-black uppercase">Cerrar</a>
    </div>
<?php endif; ?>

<!-- RESUMEN VISUAL EQUILIBRADO DE NÓMINA (Mini-Cards Compactas) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
    <!-- Mini-Tile 1: Total Trabajadores -->
    <div class="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:border-slate-300 transition-colors flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-brand-blue flex items-center justify-center text-base shrink-0 border border-blue-100">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 truncate">Total Personal</p>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-xl font-black text-slate-900 font-mono"><?php echo $kpi_total; ?></span>
                <span class="text-[11px] font-bold text-slate-500">registrados</span>
            </div>
        </div>
    </div>

    <!-- Mini-Tile 2: Categorías de Nómina -->
    <div class="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:border-slate-300 transition-colors flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-base shrink-0 border border-amber-100">
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 truncate">Clasificación</p>
            <div class="flex items-center gap-1.5 mt-0.5 text-xs font-mono font-black">
                <span class="text-blue-900"><?php echo $kpi_empleados; ?> <span class="text-[10px] font-bold text-slate-500 font-sans">Emp</span></span>
                <span class="text-slate-300 font-normal">/</span>
                <span class="text-amber-800"><?php echo $kpi_obreros; ?> <span class="text-[10px] font-bold text-slate-500 font-sans">Obr</span></span>
            </div>
        </div>
    </div>

    <!-- Mini-Tile 3: Con Liquidación -->
    <div class="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:border-slate-300 transition-colors flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-base shrink-0 border border-emerald-100">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 truncate">Con Liquidación</p>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-xl font-black text-emerald-800 font-mono"><?php echo $kpi_con_liq; ?></span>
                <span class="text-[11px] font-bold text-emerald-700">expedientes</span>
            </div>
        </div>
    </div>

    <!-- Mini-Tile 4: En Nómina Activa -->
    <div class="bg-white rounded-xl p-3 border border-slate-200/90 shadow-xs hover:border-slate-300 transition-colors flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-base shrink-0 border border-slate-200">
            <i class="fa-solid fa-user-check"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 truncate">En Nómina Activa</p>
            <div class="flex items-baseline gap-1.5 mt-0.5">
                <span class="text-xl font-black text-slate-800 font-mono"><?php echo $kpi_activos; ?></span>
                <span class="text-[11px] font-bold text-slate-500">por liquidar</span>
            </div>
        </div>
    </div>
</div>

<!-- CONTENEDOR PRINCIPAL DE LA TABLA Y HERRAMIENTAS INTEGRADAS -->
<div class="glass-card rounded-2xl overflow-hidden shadow-sm border border-slate-300">
    
    <!-- CABECERA DE TABLA CON TÍTULO Y BOTONES DE ACCIÓN REUBICADOS -->
    <div class="p-4 bg-gradient-to-r from-slate-50 via-white to-slate-50 border-b border-slate-200 flex flex-wrap justify-between items-center gap-3">
        <div class="flex items-center gap-2.5">
            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-users text-brand-blue"></i> Directorio de Personal
            </h2>
            <span class="text-xs font-bold text-slate-700 bg-slate-200/90 border border-slate-300 px-2.5 py-0.5 rounded-full font-mono">
                <?php echo count($empleados); ?> <?php echo count($empleados) === 1 ? 'registro' : 'registros'; ?>
            </span>
        </div>

        <!-- BOTONES DE ACCIÓN PRINCIPALES REUBICADOS EN LA TABLA -->
        <div class="flex items-center gap-2.5 no-print">
            <a href="catalogo.php" class="bg-white hover:bg-slate-100 text-slate-800 border border-slate-300 px-3.5 py-2 rounded-xl font-bold text-xs transition-colors flex items-center gap-2 shadow-xs" title="Ver catálogo de planillas y reportes">
                <i class="fa-solid fa-folder-open text-brand-blue"></i>
                <span>Catálogo de Planillas</span>
            </a>
            <button onclick="openNewModal()" type="button" class="bg-brand-blue hover:bg-blue-900 text-white font-extrabold px-4 py-2 rounded-xl text-xs transition-all flex items-center gap-2 shadow-sm cursor-pointer" title="Registrar nuevo empleado">
                <i class="fa-solid fa-plus text-brand-yellow"></i>
                <span>+ Nuevo Empleado</span>
            </button>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTROS AVANZADOS -->
    <div class="p-4 bg-white border-b border-slate-200 no-print">
        <form method="GET" action="empleados.php" class="flex flex-wrap items-center gap-3">
            <!-- Buscador por texto -->
            <div class="relative flex-1 min-w-[240px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por cédula, nombre, cargo o clase..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-semibold text-slate-900 placeholder:text-slate-500 transition-all">
            </div>

            <!-- Filtro de Categoría -->
            <div class="w-auto min-w-[160px]">
                <select name="categoria" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-brand-blue transition-all cursor-pointer">
                    <option value="" <?php echo $categoria_filtro === '' ? 'selected' : ''; ?>>Todas las categorías</option>
                    <option value="Empleado" <?php echo $categoria_filtro === 'Empleado' ? 'selected' : ''; ?>>Solo Empleados</option>
                    <option value="Obrero" <?php echo $categoria_filtro === 'Obrero' ? 'selected' : ''; ?>>Solo Obreros</option>
                </select>
            </div>

            <!-- Filtro de Estado de Liquidación -->
            <div class="w-auto min-w-[170px]">
                <select name="estado" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-brand-blue transition-all cursor-pointer">
                    <option value="" <?php echo $estado_filtro === '' ? 'selected' : ''; ?>>Todos los estatus</option>
                    <option value="con_liquidacion" <?php echo $estado_filtro === 'con_liquidacion' ? 'selected' : ''; ?>>Con Liquidación</option>
                    <option value="sin_liquidacion" <?php echo $estado_filtro === 'sin_liquidacion' ? 'selected' : ''; ?>>Sin Liquidación (Activo)</option>
                </select>
            </div>

            <!-- Botones de Acción de Filtro -->
            <button type="submit" class="bg-brand-blue hover:bg-blue-900 text-white px-4 py-2 rounded-xl font-extrabold text-xs transition-colors shrink-0 flex items-center gap-1.5 shadow-sm cursor-pointer">
                <span>Filtrar</span>
            </button>

            <?php if (!empty($search) || !empty($categoria_filtro) || !empty($estado_filtro)): ?>
                <a href="empleados.php" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-3.5 py-2 rounded-xl font-bold text-xs transition-colors shrink-0 flex items-center gap-1.5">
                    <i class="fa-solid fa-xmark"></i> Limpiar Filtros
                </a>
            <?php endif; ?>
        </form>

        <?php if (!empty($search) || !empty($categoria_filtro) || !empty($estado_filtro)): ?>
            <div class="mt-2.5 flex items-center gap-2 text-xs font-bold text-slate-700">
                <span class="text-slate-500 font-medium">Filtro aplicado:</span>
                <?php if(!empty($search)): ?>
                    <span class="bg-blue-50 text-brand-blue border border-blue-200 px-2 py-0.5 rounded-md">Texto: "<?php echo htmlspecialchars($search); ?>"</span>
                <?php endif; ?>
                <?php if(!empty($categoria_filtro)): ?>
                    <span class="bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-md">Categoría: <?php echo htmlspecialchars($categoria_filtro); ?></span>
                <?php endif; ?>
                <?php if(!empty($estado_filtro)): ?>
                    <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded-md">Estatus: <?php echo $estado_filtro === 'con_liquidacion' ? 'Con Liquidación' : 'Sin Liquidación'; ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TABLA DE EMPLEADOS ESTRUCTURADA -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-900 text-xs font-black uppercase tracking-wider border-b-2 border-slate-300">
                    <th class="px-4 py-3.5 text-center w-12">N°</th>
                    <th class="px-4 py-3.5">Empleado & Cédula</th>
                    <th class="px-4 py-3.5">Cargo & Clasificación</th>
                    <th class="px-4 py-3.5 text-center">Categoría</th>
                    <th class="px-4 py-3.5 text-center">Fecha Ingreso & Antigüedad</th>
                    <th class="px-4 py-3.5 text-center">Liquidaciones</th>
                    <th class="px-4 py-3.5 text-center no-print w-48">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-xs">
                <?php if(count($empleados) > 0): ?>
                    <?php $i = 1; foreach($empleados as $emp): 
                        // Calcular antigüedad
                        $antig_texto = 'No registrada';
                        if (!empty($emp['fecha_ingreso'])) {
                            try {
                                $d_ingreso = new DateTime($emp['fecha_ingreso']);
                                $d_hoy = new DateTime();
                                $diff = $d_ingreso->diff($d_hoy);
                                $antig_texto = "{$diff->y}a, {$diff->m}m, {$diff->d}d";
                            } catch(Exception $e) {
                                $antig_texto = '-';
                            }
                        }
                    ?>
                    <tr class="hover:bg-blue-50/50 transition-colors emp-row">
                        <!-- N° -->
                        <td class="px-4 py-3.5 text-center font-bold text-slate-700">
                            <?php echo $i++; ?>
                        </td>

                        <!-- Empleado & Cédula -->
                        <td class="px-4 py-3.5">
                            <div class="font-black text-slate-900 text-sm leading-snug">
                                <?php echo htmlspecialchars($emp['apellidos_nombres']); ?>
                            </div>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span class="inline-block font-mono text-[11px] font-extrabold text-slate-800 bg-slate-100 border border-slate-300 px-2 py-0.5 rounded">
                                    CI: <?php echo htmlspecialchars($emp['cedula']); ?>
                                </span>
                            </div>
                        </td>

                        <!-- Cargo & Clasificación -->
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-800 text-xs">
                                <?php echo htmlspecialchars($emp['cargo']); ?>
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-0.5">
                                <?php 
                                $detalles = [];
                                if(!empty($emp['clase_cargo'])) $detalles[] = "Clase: " . htmlspecialchars($emp['clase_cargo']);
                                if(!empty($emp['nivel'])) $detalles[] = "Nivel: " . htmlspecialchars($emp['nivel']);
                                echo !empty($detalles) ? implode(' • ', $detalles) : '<span class="text-slate-400">Sin clasificación</span>';
                                ?>
                            </div>
                        </td>

                        <!-- Categoría -->
                        <td class="px-4 py-3.5 text-center">
                            <?php if($emp['categoria'] === 'Obrero'): ?>
                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300">
                                    Obrero
                                </span>
                            <?php elseif($emp['categoria'] === 'Empleado'): ?>
                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-900 border border-blue-300">
                                    Empleado
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold text-slate-500 bg-slate-100">
                                    No asignado
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Fecha Ingreso & Antigüedad -->
                        <td class="px-4 py-3.5 text-center font-bold text-slate-800 whitespace-nowrap">
                            <div><?php echo $emp['fecha_ingreso'] ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : '-'; ?></div>
                            <div class="text-[10px] font-mono text-slate-600 font-semibold mt-0.5">
                                <?php echo $antig_texto; ?>
                            </div>
                        </td>

                        <!-- Liquidaciones -->
                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                            <?php if($emp['total_liquidaciones'] > 0): ?>
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 px-2 py-1 rounded-lg text-xs">
                                    <i class="fa-solid fa-circle-check text-[10px]"></i> <?php echo $emp['total_liquidaciones']; ?> planilla(s)
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 font-semibold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-1 rounded-lg text-xs">
                                    <i class="fa-regular fa-clock text-[10px]"></i> Sin liquidar
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Botones de Acción Mejorados y Completos -->
                        <td class="px-4 py-3.5 text-center no-print whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <!-- Botón 1: Calcular Liquidación Directa -->
                                <a href="prestaciones_form.php?empleado_id=<?php echo $emp['id']; ?>" class="bg-emerald-700 hover:bg-emerald-800 text-white px-2.5 py-1.5 rounded-lg font-extrabold text-xs transition-colors inline-flex items-center gap-1 shadow-xs" title="Iniciar cálculo de prestaciones para este empleado">
                                    <i class="fa-solid fa-calculator text-xs"></i> Liquidar
                                </a>

                                <!-- Botón 2: Ver Planilla (si ya tiene cálculo) -->
                                <?php if($emp['total_liquidaciones'] > 0 && !empty($emp['ultima_prestacion_id'])): ?>
                                    <a href="prestaciones_view.php?id=<?php echo $emp['ultima_prestacion_id']; ?>" class="bg-blue-100 hover:bg-blue-200 text-brand-blue border border-blue-300 px-2 py-1.5 rounded-lg font-bold text-xs transition-colors inline-flex items-center" title="Ver última planilla oficial de prestaciones">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- Botón 3: Editar en Modal Wizard -->
                                <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($emp), ENT_QUOTES, "UTF-8"); ?>)' type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 px-2 py-1.5 rounded-lg font-bold text-xs transition-colors inline-flex items-center cursor-pointer" title="Editar empleado en Modal">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>

                                <!-- Botón 4: Eliminar -->
                                <a href="empleados.php?delete=<?php echo $emp['id']; ?>" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-2 py-1.5 rounded-lg font-bold text-xs transition-colors inline-flex items-center" title="Eliminar trabajador" onclick="return confirm('¿Está seguro que desea eliminar a <?php echo htmlspecialchars($emp['apellidos_nombres']); ?>? Esta acción borrará al trabajador y todo su historial de liquidaciones.');">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- ESTADO VACÍO -->
                    <tr>
                        <td colspan="7" class="px-6 py-14 text-center">
                            <div class="flex flex-col items-center justify-center gap-3 max-w-md mx-auto">
                                <div class="w-16 h-16 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 text-2xl">
                                    <i class="fa-solid fa-users-slash"></i>
                                </div>
                                <h4 class="text-base font-black text-slate-900">
                                    <?php echo (!empty($search) || !empty($categoria_filtro) || !empty($estado_filtro)) ? 'Sin coincidencias de búsqueda' : 'No hay empleados registrados'; ?>
                                </h4>
                                <p class="text-xs font-semibold text-slate-700 text-center leading-relaxed">
                                    <?php echo (!empty($search) || !empty($categoria_filtro) || !empty($estado_filtro))
                                        ? 'Ningún trabajador coincide con los criterios de filtro aplicados. Pruebe cambiando los filtros.' 
                                        : 'Aún no se han agregado empleados a la base de datos de la empresa. Puede registrar el primero con el asistente guiado.'; ?>
                                </p>
                                <div class="flex items-center gap-2 mt-2">
                                    <?php if(!empty($search) || !empty($categoria_filtro) || !empty($estado_filtro)): ?>
                                        <a href="empleados.php" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-4 py-2 rounded-xl font-bold text-xs transition-colors">
                                            Limpiar Filtros
                                        </a>
                                    <?php endif; ?>
                                    <button onclick="openNewModal()" type="button" class="bg-brand-blue hover:bg-blue-900 text-white px-4 py-2 rounded-xl font-bold text-xs transition-colors flex items-center gap-1.5 shadow-sm cursor-pointer">
                                        <i class="fa-solid fa-plus"></i> Registrar Primer Empleado
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL WIZARD DE 2 PASOS PARA NUEVO EMPLEADO / EDICIÓN  -->
<!-- ======================================================= -->
<div id="empModalBackdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4 transition-opacity duration-300 opacity-0 no-print">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-slate-300 overflow-hidden transform scale-95 transition-transform duration-300" id="empModalCard">
        
        <!-- CABECERA DEL MODAL -->
        <div class="p-5 border-b border-slate-200 bg-gradient-to-r from-slate-50 via-white to-slate-50 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-brand-blue text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i id="modalHeaderIcon" class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 id="modalTitle" class="font-black text-slate-900 text-base leading-tight">Registrar Nuevo Empleado</h3>
                    <p class="text-[11px] font-semibold text-slate-500">Asistente guiado en 2 pasos rápidos</p>
                </div>
            </div>
            <button onclick="closeEmpModal()" type="button" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition-colors cursor-pointer" title="Cerrar modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- BARRA DE PASOS DEL WIZARD -->
        <div class="px-6 py-3 bg-slate-100/80 border-b border-slate-200 flex items-center justify-between">
            <button type="button" onclick="goToStep(1)" id="stepTab1" class="flex items-center gap-2 text-xs font-black text-brand-blue cursor-pointer group">
                <span id="stepBadge1" class="w-6 h-6 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold">1</span>
                <span>Datos Personales</span>
            </button>
            <div class="h-0.5 flex-1 mx-3 bg-slate-300 rounded" id="stepConnector"></div>
            <button type="button" onclick="goToStep(2)" id="stepTab2" class="flex items-center gap-2 text-xs font-bold text-slate-400 cursor-pointer group">
                <span id="stepBadge2" class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold">2</span>
                <span>Cargo y Clasificación</span>
            </button>
        </div>

        <!-- FORMULARIO WIZARD -->
        <form id="empForm" method="POST" action="empleados.php">
            <input type="hidden" name="action_empleado" value="1">
            <input type="hidden" name="id" id="emp_id" value="0">

            <!-- PASO 1: DATOS PERSONALES & IDENTIFICACIÓN -->
            <div id="stepView1" class="p-6 space-y-4">
                <div class="bg-blue-50/70 border border-blue-200 p-3 rounded-xl flex items-center gap-2 text-xs font-semibold text-brand-blue">
                    <i class="fa-solid fa-circle-info text-sm"></i>
                    <span>Paso 1: Ingrese la identificación legal y fecha de ingreso del trabajador.</span>
                </div>

                <!-- Cédula -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                        Cédula de Identidad <span class="text-rose-600">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" name="cedula" id="emp_cedula" required placeholder="Ej: 24123456" 
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all font-mono">
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Cédula única sin puntos ni comas.</p>
                </div>

                <!-- Apellidos y Nombres -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                        Apellidos y Nombres <span class="text-rose-600">*</span>
                    </label>
                    <input type="text" name="apellidos_nombres" id="emp_apellidos_nombres" required placeholder="Ej: Pérez Rodríguez, Juan Carlos" 
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all">
                    <p class="text-[10px] text-slate-500 mt-1">Nombre completo en orden Apellidos, Nombres.</p>
                </div>

                <!-- Fecha de Ingreso -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                        Fecha de Ingreso a la Empresa
                    </label>
                    <input type="date" name="fecha_ingreso" id="emp_fecha_ingreso" 
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all">
                    <p class="text-[10px] text-slate-500 mt-1">Base oficial para el cálculo cronológico de antigüedad laboral.</p>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <button type="button" onclick="closeEmpModal()" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 transition-colors cursor-pointer">
                        Cancelar
                    </button>
                    <button type="button" onclick="goToStep(2)" class="bg-brand-blue hover:bg-blue-900 text-white font-extrabold px-6 py-2.5 rounded-xl text-xs transition-colors flex items-center gap-2 shadow-sm cursor-pointer">
                        <span>Siguiente: Datos Laborales</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- PASO 2: CARGO Y CLASIFICACIÓN LABORAL -->
            <div id="stepView2" class="p-6 space-y-4 hidden">
                <div class="bg-emerald-50/70 border border-emerald-200 p-3 rounded-xl flex items-center gap-2 text-xs font-semibold text-emerald-900">
                    <i class="fa-solid fa-briefcase text-sm text-emerald-700"></i>
                    <span>Paso 2: Especifique el cargo, nivel jerárquico y categoría de nómina.</span>
                </div>

                <!-- Cargo Principal -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                        Cargo Principal <span class="text-rose-600">*</span>
                    </label>
                    <input type="text" name="cargo" id="emp_cargo" required placeholder="Ej: Asistente Administrativo, Chofer, etc." 
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Categoría -->
                    <div>
                        <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                            Categoría de Nómina
                        </label>
                        <select name="categoria" id="emp_categoria" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all cursor-pointer">
                            <option value="">-- No asignada --</option>
                            <option value="Empleado">Empleado</option>
                            <option value="Obrero">Obrero</option>
                        </select>
                    </div>

                    <!-- Nivel -->
                    <div>
                        <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                            Nivel Jerárquico
                        </label>
                        <input type="text" name="nivel" id="emp_nivel" placeholder="Ej: I, II, Senior, Bachiller..." 
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all">
                    </div>
                </div>

                <!-- Clase de Cargo -->
                <div>
                    <label class="block text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1">
                        Clase de Cargo
                    </label>
                    <input type="text" name="clase_cargo" id="emp_clase_cargo" placeholder="Ej: Administrativo, Operativo, Directivo..." 
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none text-xs font-bold text-slate-900 transition-all">
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <button type="button" onclick="goToStep(1)" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 transition-colors flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Volver al Paso 1</span>
                    </button>
                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-black px-6 py-2.5 rounded-xl text-xs transition-colors flex items-center gap-2 shadow-sm cursor-pointer">
                        <i class="fa-solid fa-floppy-disk text-xs"></i>
                        <span id="btnSaveText">Guardar Empleado</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPT DEL MODAL WIZARD Y ACCIONES -->
<script>
let currentWizardStep = 1;

function openNewModal() {
    document.getElementById('emp_id').value = '0';
    document.getElementById('emp_cedula').value = '';
    document.getElementById('emp_apellidos_nombres').value = '';
    document.getElementById('emp_fecha_ingreso').value = '';
    document.getElementById('emp_cargo').value = '';
    document.getElementById('emp_categoria').value = '';
    document.getElementById('emp_clase_cargo').value = '';
    document.getElementById('emp_nivel').value = '';
    
    document.getElementById('modalTitle').innerText = 'Registrar Nuevo Empleado';
    document.getElementById('modalHeaderIcon').className = 'fa-solid fa-user-plus';
    document.getElementById('btnSaveText').innerText = 'Guardar Empleado';
    
    goToStep(1);
    showModal();
}

function openEditModal(emp) {
    if (!emp) return;
    document.getElementById('emp_id').value = emp.id;
    document.getElementById('emp_cedula').value = emp.cedula || '';
    document.getElementById('emp_apellidos_nombres').value = emp.apellidos_nombres || '';
    document.getElementById('emp_fecha_ingreso').value = emp.fecha_ingreso || '';
    document.getElementById('emp_cargo').value = emp.cargo || '';
    document.getElementById('emp_categoria').value = emp.categoria || '';
    document.getElementById('emp_clase_cargo').value = emp.clase_cargo || '';
    document.getElementById('emp_nivel').value = emp.nivel || '';
    
    document.getElementById('modalTitle').innerText = 'Editar Empleado: ' + (emp.apellidos_nombres || '');
    document.getElementById('modalHeaderIcon').className = 'fa-solid fa-user-pen';
    document.getElementById('btnSaveText').innerText = 'Actualizar Datos';
    
    goToStep(1);
    showModal();
}

function showModal() {
    const backdrop = document.getElementById('empModalBackdrop');
    const card = document.getElementById('empModalCard');
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        card.classList.remove('scale-95');
        card.classList.add('scale-100');
    }, 10);
}

function closeEmpModal() {
    const backdrop = document.getElementById('empModalBackdrop');
    const card = document.getElementById('empModalCard');
    backdrop.classList.add('opacity-0');
    card.classList.remove('scale-100');
    card.classList.add('scale-95');
    setTimeout(() => {
        backdrop.classList.add('hidden');
    }, 250);
}

function goToStep(step) {
    if (step === 2) {
        // Validar campos obligatorios del paso 1
        const cedula = document.getElementById('emp_cedula').value.trim();
        const nombres = document.getElementById('emp_apellidos_nombres').value.trim();
        if (!cedula || !nombres) {
            alert('Por favor complete la Cédula y los Apellidos/Nombres antes de continuar al Paso 2.');
            if (!cedula) document.getElementById('emp_cedula').focus();
            else document.getElementById('emp_apellidos_nombres').focus();
            return;
        }
    }

    currentWizardStep = step;
    const view1 = document.getElementById('stepView1');
    const view2 = document.getElementById('stepView2');
    const tab1 = document.getElementById('stepTab1');
    const tab2 = document.getElementById('stepTab2');
    const badge1 = document.getElementById('stepBadge1');
    const badge2 = document.getElementById('stepBadge2');
    const connector = document.getElementById('stepConnector');

    if (step === 1) {
        view1.classList.remove('hidden');
        view2.classList.add('hidden');
        
        tab1.className = "flex items-center gap-2 text-xs font-black text-brand-blue cursor-pointer";
        badge1.className = "w-6 h-6 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold";
        
        tab2.className = "flex items-center gap-2 text-xs font-bold text-slate-400 cursor-pointer";
        badge2.className = "w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold";
        
        connector.className = "h-0.5 flex-1 mx-3 bg-slate-300 rounded";
    } else {
        view1.classList.add('hidden');
        view2.classList.remove('hidden');
        
        tab1.className = "flex items-center gap-2 text-xs font-bold text-emerald-700 cursor-pointer";
        badge1.className = "w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold";
        
        tab2.className = "flex items-center gap-2 text-xs font-black text-brand-blue cursor-pointer";
        badge2.className = "w-6 h-6 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold";
        
        connector.className = "h-0.5 flex-1 mx-3 bg-emerald-500 rounded";
    }
}

// Cerrar con Escape o clic fuera
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const backdrop = document.getElementById('empModalBackdrop');
        if (!backdrop.classList.contains('hidden')) {
            closeEmpModal();
        }
    }
});

document.getElementById('empModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmpModal();
    }
});

// Filtro instantáneo al escribir en el buscador
const searchInputEl = document.getElementById('searchInput');
if (searchInputEl) {
    searchInputEl.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.emp-row');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>


<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireLogin();
require_once 'includes/PrestacionesCalculator.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT 
        p.*,
        e.cedula,
        e.apellidos_nombres,
        e.cargo,
        e.clase_cargo,
        e.nivel,
        e.categoria,
        e.fecha_ingreso
    FROM prestaciones p
    JOIN empleados e ON e.id = p.empleado_id
";

if (!empty($search)) {
    $sql .= " WHERE e.cedula LIKE :search OR e.apellidos_nombres LIKE :search OR e.cargo LIKE :search ";
}

$sql .= " ORDER BY p.id ASC";

$stmt = $pdo->prepare($sql);
if (!empty($search)) {
    $stmt->execute([':search' => '%' . $search . '%']);
} else {
    $stmt->execute();
}
$planillas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="mb-6 flex flex-wrap justify-between items-center gap-4 no-print">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-folder-open text-brand-blue"></i> Historial de Liquidaciones
        </h2>
        <p class="text-xs text-slate-500">Gestión unificada de liquidaciones de prestaciones sociales de PRIME CONTADORES PÚBLICOS</p>
    </div>

    <div class="grid grid-cols-1 sm:flex gap-2 sm:gap-3 w-full lg:w-auto">
        <a href="export_excel.php?lote=all" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl shadow transition-colors flex items-center gap-2 text-sm">
            <i class="fa-solid fa-file-excel"></i> Exportar Libro Excel Consolidado
        </a>
        <a href="prestaciones_form.php" class="bg-brand-blue hover:bg-blue-800 text-white font-bold px-4 py-2 rounded-xl shadow transition-colors flex items-center gap-2 text-sm">
            <i class="fa-solid fa-plus"></i> + Generar Nueva Liquidación
        </a>
    </div>
</div>

<!-- BARRA DE BÚSQUEDA Y CONMUTADOR DE VISTAS -->
<div class="glass-card rounded-2xl p-4 mb-6 shadow-sm flex flex-wrap justify-between items-center gap-4 no-print border border-slate-200">
    <form method="GET" action="catalogo.php" class="flex flex-wrap sm:flex-nowrap gap-2 flex-1 w-full lg:max-w-md">
        <div class="relative w-full min-w-0 sm:flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por Cédula, Empleado o Cargo..." class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm">
        </div>
        <button type="submit" class="bg-brand-blue text-white px-4 py-2 rounded-xl font-bold text-sm hover:bg-blue-800 transition-colors">Buscar</button>
        <?php if (!empty($search)): ?>
            <a href="catalogo.php" class="bg-slate-200 text-slate-700 px-3 py-2 rounded-xl font-bold text-sm hover:bg-slate-300 flex items-center">Limpiar</a>
        <?php endif; ?>
    </form>

    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
        <span class="text-xs text-slate-500 font-bold uppercase">Vista:</span>
        <div class="bg-slate-200 p-1 rounded-xl flex gap-1">
            <button type="button" id="btn-view-cards" onclick="setView('cards')" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-white text-brand-blue shadow">
                <i class="fa-solid fa-border-all mr-1"></i> Catálogo Mosaico
            </button>
            <button type="button" id="btn-view-list" onclick="setView('list')" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:text-slate-900">
                <i class="fa-solid fa-list mr-1"></i> Lista Resumen
            </button>
        </div>
    </div>
</div>

<!-- VISTA 1: CATÁLOGO EN MOSAICO (CARDS POR TRABAJADOR) -->
<div id="view-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (count($planillas) > 0): ?>
        <?php $num_item = 1; foreach ($planillas as $p): ?>
        <?php $es_legado = ($p['regla_perfil'] == 'LEGADO_120_180'); ?>
        <div class="glass-card rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200/80 flex flex-col group bg-white">
            <!-- Header Card -->
            <div class="bg-gradient-to-r from-brand-dark to-slate-900 text-white p-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="bg-brand-yellow text-brand-dark font-extrabold px-3 py-1 rounded-lg text-xs tracking-wide shadow-sm">
                        LIQUIDACIÓN #<?php echo $num_item++; ?>
                    </span>
                    <span class="text-[11px] text-amber-300 font-semibold uppercase tracking-wider flex items-center gap-1">
                        <img src="assets/img/logo_icon.png" alt="PRIME" class="w-3.5 h-3.5 object-contain inline"> PRIME
                    </span>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo $es_legado ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'; ?>">
                    <?php echo $es_legado ? 'Regla Legada' : 'LOTTT Vigente'; ?>
                </span>
            </div>

            <!-- Body Card -->
            <div class="p-5 flex-1 space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-blue/10 flex items-center justify-center text-brand-blue font-bold text-lg group-hover:bg-brand-blue group-hover:text-white transition-colors">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-slate-800 text-sm leading-tight group-hover:text-brand-blue transition-colors">
                            <?php echo htmlspecialchars($p['apellidos_nombres']); ?>
                        </h4>
                        <p class="text-xs text-slate-500 font-medium">C.I: <?php echo htmlspecialchars($p['cedula']); ?></p>
                        <p class="text-xs font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($p['cargo']); ?></p>
                    </div>
                </div>

                <hr class="border-slate-100">

                <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Ingreso</p>
                        <p class="font-semibold text-slate-700"><?php echo date('d/m/Y', strtotime($p['fecha_ingreso'])); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Egreso</p>
                        <p class="font-semibold text-slate-700"><?php echo date('d/m/Y', strtotime($p['fecha_egreso'])); ?></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Tiempo Servido</p>
                        <p class="font-bold text-slate-800">
                            <?php echo "{$p['antig_anos']} Años, {$p['antig_nro_meses']} Meses, {$p['antig_nro_dias']} Días"; ?>
                        </p>
                    </div>
                </div>

                <div class="flex justify-between items-center text-xs px-1">
                    <span class="text-slate-500 font-medium">Sueldo Normal:</span>
                    <span class="font-bold text-slate-800">Bs. <?php echo number_format($p['sueldo_mensual_normal'], 2, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Footer Card con Monto Neto y Acciones -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-500 uppercase">Total a Cobrar:</span>
                    <span class="text-lg font-extrabold text-brand-blue">Bs. <?php echo number_format($p['neto_a_cobrar'], 2, ',', '.'); ?></span>
                </div>

                <div class="grid grid-cols-3 gap-1.5 pt-1">
                    <a href="prestaciones_view.php?id=<?php echo $p['id']; ?>" class="bg-brand-blue hover:bg-blue-800 text-white font-bold py-2 rounded-lg text-xs text-center transition-colors shadow-sm flex items-center justify-center gap-1 col-span-2" title="Ver Liquidación de Prestaciones">
                        <i class="fa-solid fa-eye"></i> Ver Liquidación
                    </a>
                    <a href="prestaciones_form.php?id=<?php echo $p['id']; ?>&empleado_id=<?php echo $p['empleado_id']; ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2 rounded-lg text-xs text-center transition-colors flex items-center justify-center gap-1" title="Editar Liquidación">
                        <i class="fa-solid fa-pen"></i> Editar
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-span-3 text-center py-12 text-slate-500 glass-card rounded-2xl">
            <i class="fa-solid fa-folder-open text-4xl text-slate-300 mb-3"></i>
            <p class="font-bold">No se encontraron liquidaciones registradas.</p>
        </div>
    <?php endif; ?>
</div>

<!-- VISTA 2: LISTA DE TABLA -->
<div id="view-list" class="hidden glass-card rounded-2xl overflow-hidden shadow-sm border border-slate-200">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <th class="px-5 py-3.5 font-bold">N° Liquidación</th>
                    <th class="px-5 py-3.5 font-bold">Cédula</th>
                    <th class="px-5 py-3.5 font-bold">Empleado</th>
                    <th class="px-5 py-3.5 font-bold">Cargo</th>
                    <th class="px-5 py-3.5 font-bold text-center">Regla</th>
                    <th class="px-5 py-3.5 font-bold text-center">Tiempo Servido</th>
                    <th class="px-5 py-3.5 font-bold text-right">Neto a Cobrar</th>
                    <th class="px-5 py-3.5 font-bold text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php $num_item2 = 1; foreach ($planillas as $p): ?>
                <?php $es_legado2 = ($p['regla_perfil'] == 'LEGADO_120_180'); ?>
                <tr class="hover:bg-slate-50/70 transition-colors">
                    <td class="px-5 py-3.5 font-extrabold text-brand-blue">LIQUIDACIÓN #<?php echo $num_item2++; ?></td>
                    <td class="px-5 py-3.5 font-bold text-slate-800"><?php echo htmlspecialchars($p['cedula']); ?></td>
                    <td class="px-5 py-3.5 font-semibold text-slate-800"><?php echo htmlspecialchars($p['apellidos_nombres']); ?></td>
                    <td class="px-5 py-3.5 text-xs text-slate-600"><?php echo htmlspecialchars($p['cargo']); ?></td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo $es_legado2 ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300'; ?>">
                            <?php echo $es_legado2 ? 'Legado' : 'LOTTT'; ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center text-xs font-semibold text-slate-600">
                        <?php echo "{$p['antig_anos']}a, {$p['antig_nro_meses']}m"; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right font-extrabold text-brand-blue">
                        Bs. <?php echo number_format($p['neto_a_cobrar'], 2, ',', '.'); ?>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <div class="flex justify-center gap-1.5">
                            <a href="prestaciones_view.php?id=<?php echo $p['id']; ?>" class="bg-brand-blue hover:bg-blue-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1">
                                <i class="fa-solid fa-eye"></i> Ver Liquidación
                            </a>
                            <a href="prestaciones_form.php?id=<?php echo $p['id']; ?>&empleado_id=<?php echo $p['empleado_id']; ?>" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="export_excel.php?id=<?php echo $p['id']; ?>" class="bg-emerald-100 hover:bg-emerald-200 text-emerald-800 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors">
                                <i class="fa-solid fa-file-excel"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function setView(view) {
    const cards = document.getElementById('view-cards');
    const list = document.getElementById('view-list');
    const btnCards = document.getElementById('btn-view-cards');
    const btnList = document.getElementById('btn-view-list');

    if (view === 'cards') {
        cards.classList.remove('hidden');
        list.classList.add('hidden');
        btnCards.className = "px-3 py-1.5 text-xs font-bold rounded-lg bg-white text-brand-blue shadow";
        btnList.className = "px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:text-slate-900";
    } else {
        cards.classList.add('hidden');
        list.classList.remove('hidden');
        btnList.className = "px-3 py-1.5 text-xs font-bold rounded-lg bg-white text-brand-blue shadow";
        btnCards.className = "px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:text-slate-900";
    }
}
</script>

<?php include 'includes/footer.php'; ?>

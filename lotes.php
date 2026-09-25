<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireLogin();

// Obtener todas las prestaciones
$stmt = $pdo->query("
    SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo 
    FROM prestaciones p 
    JOIN empleados e ON p.empleado_id = e.id 
    ORDER BY p.created_at DESC
");
$prestaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 gap-4">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-brand-blue"></i> Gestión de Lotes de Liquidación
        </h2>
        <p class="text-slate-500 text-xs">Seleccione planillas específicas para generar un informe consolidado o exportar</p>
    </div>
    <div>
        <a href="export_excel.php?lote=all" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl shadow transition-colors flex items-center gap-2 text-sm">
            <i class="fa-solid fa-file-excel"></i> Exportar Todo el Lote (Excel)
        </a>
    </div>
</div>

<form method="GET" action="export_excel.php" id="form-lote">
    <div class="glass-card rounded-2xl overflow-hidden shadow-sm border border-slate-200">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-white/50 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
            <div class="flex items-center gap-3">
                <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)" class="w-4 h-4 text-brand-blue rounded">
                <label for="select-all" class="font-bold text-sm text-slate-700 cursor-pointer">Seleccionar Todos</label>
            </div>
            <button type="submit" class="bg-brand-blue hover:bg-blue-800 text-white font-bold px-4 py-1.5 rounded-lg text-xs transition-colors flex items-center gap-1">
                <i class="fa-solid fa-download"></i> Exportar Selección
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="px-5 py-3.5 font-bold text-center">Sel.</th>
                        <th class="px-5 py-3.5 font-bold">Cédula</th>
                        <th class="px-5 py-3.5 font-bold">Apellidos y Nombres</th>
                        <th class="px-5 py-3.5 font-bold">Fecha Cálculo</th>
                        <th class="px-5 py-3.5 font-bold">Motivo</th>
                        <th class="px-5 py-3.5 font-bold text-right">Neto a Cobrar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($prestaciones) > 0): ?>
                        <?php foreach($prestaciones as $p): ?>
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-3.5 text-center">
                                <input type="checkbox" name="ids[]" value="<?php echo $p['id']; ?>" class="item-checkbox w-4 h-4 text-brand-blue rounded">
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-800"><?php echo htmlspecialchars($p['cedula']); ?></td>
                            <td class="px-5 py-3.5 font-semibold text-slate-800"><?php echo htmlspecialchars($p['apellidos_nombres']); ?></td>
                            <td class="px-5 py-3.5 text-xs text-slate-600"><?php echo date('d/m/Y', strtotime($p['fecha_calculo'])); ?></td>
                            <td class="px-5 py-3.5 text-xs text-slate-600"><?php echo htmlspecialchars($p['motivo'] ?: 'RENUNCIA'); ?></td>
                            <td class="px-5 py-3.5 text-right font-bold text-brand-blue">
                                Bs. <?php echo number_format($p['neto_a_cobrar'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">No hay liquidaciones registradas para gestionar lotes.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
}
</script>

<?php include 'includes/footer.php'; ?>

<?php
require_once 'config/database.php';

// Manejar eliminación de cálculo
if(isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM prestaciones WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: prestaciones.php?msg=deleted");
        exit;
    } catch(PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Obtener todas las prestaciones con los datos del empleado
$stmt = $pdo->query("
    SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo 
    FROM prestaciones p 
    JOIN empleados e ON p.empleado_id = e.id 
    ORDER BY p.created_at DESC
");
$prestaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php'; 
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Cálculos de Prestaciones</h2>
        <p class="text-slate-500">Historial de planillas generadas</p>
    </div>
    <div class="flex gap-2">
        <a href="catalogo.php" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg shadow transition-colors flex items-center gap-2 text-sm font-bold">
            <i class="fa-solid fa-folder-open"></i> Catálogo Mosaico
        </a>
        <a href="prestaciones_form.php" class="bg-brand-blue hover:bg-blue-900 text-white px-4 py-2 rounded-lg shadow transition-colors flex items-center gap-2 text-sm font-bold">
            <i class="fa-solid fa-plus"></i> Nuevo Cálculo
        </a>
    </div>
</div>

<?php if(isset($_GET['msg'])): ?>
    <?php if($_GET['msg'] == 'saved'): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r shadow-sm" role="alert">
            <p>La planilla se guardó exitosamente.</p>
        </div>
    <?php elseif($_GET['msg'] == 'deleted'): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r shadow-sm" role="alert">
            <p>La planilla de liquidación fue eliminada del sistema.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="glass-card rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <th class="px-6 py-4 font-medium">Empleado</th>
                    <th class="px-6 py-4 font-medium">Fecha Cálculo</th>
                    <th class="px-6 py-4 font-medium">Motivo</th>
                    <th class="px-6 py-4 font-medium text-right">Total Asignaciones</th>
                    <th class="px-6 py-4 font-medium text-right">Neto a Cobrar</th>
                    <th class="px-6 py-4 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if(count($prestaciones) > 0): ?>
                    <?php foreach($prestaciones as $p): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['apellidos_nombres']); ?></div>
                            <div class="text-xs text-slate-500">CI: <?php echo htmlspecialchars($p['cedula']); ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <?php echo date('d/m/Y', strtotime($p['fecha_calculo'])); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <?php echo htmlspecialchars($p['motivo'] ?: 'No especificado'); ?>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-700 text-right">
                            Bs. <?php echo number_format($p['total_asignaciones'], 2, ',', '.'); ?>
                        </td>
                        <td class="px-6 py-4 text-sm font-bold text-brand-blue text-right">
                            Bs. <?php echo number_format($p['neto_a_cobrar'], 2, ',', '.'); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <div class="flex justify-end gap-2">
                                <a href="prestaciones_view.php?id=<?php echo $p['id']; ?>" class="text-brand-blue bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded transition-colors" title="Ver Planilla">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="prestaciones_form.php?id=<?php echo $p['id']; ?>&empleado_id=<?php echo $p['empleado_id']; ?>" class="text-brand-yellow bg-yellow-50 hover:bg-yellow-100 px-3 py-1 rounded transition-colors" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="prestaciones.php?delete=<?php echo $p['id']; ?>" onclick="return confirm('¿Está seguro de eliminar ESTE cálculo de prestaciones de forma permanente?');" class="text-brand-red bg-red-50 hover:bg-red-100 px-3 py-1 rounded transition-colors" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i class="fa-solid fa-file-invoice text-4xl text-slate-300"></i>
                                <p>No hay cálculos de prestaciones generados.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

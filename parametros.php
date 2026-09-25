<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireAdmin();

// Actualizar parámetros si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_params'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE parametros_legales SET valor = :valor WHERE nombre = :nombre");
        
        foreach ($_POST['parametros'] as $nombre => $valor) {
            // Convertir tasas (porcentajes) a decimal antes de guardar
            if (strpos($nombre, 'tasa') !== false) {
                $valor = floatval($valor) / 100;
            }
            
            $stmt->execute([
                ':valor' => floatval($valor),
                ':nombre' => $nombre
            ]);
        }
        
        $pdo->commit();
        $success_msg = "Parámetros actualizados exitosamente.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_msg = "Error al actualizar: " . $e->getMessage();
    }
}

// Obtener parámetros actuales

$stmt = $pdo->query("SELECT * FROM parametros_legales ORDER BY id");
$parametros = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php'; 
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Parámetros Legales</h2>
        <p class="text-slate-500">Configuración global para el cálculo de prestaciones</p>
    </div>
</div>

<?php if (isset($success_msg)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm" role="alert">
        <p class="font-bold">¡Éxito!</p>
        <p><?php echo $success_msg; ?></p>
    </div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm" role="alert">
        <p class="font-bold">Error</p>
        <p><?php echo $error_msg; ?></p>
    </div>
<?php endif; ?>

<div class="glass-card rounded-xl overflow-hidden shadow-sm">
    <div class="px-6 py-4 border-b border-slate-200 bg-white/50 flex items-center justify-between">
        <h3 class="font-semibold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-scale-balanced text-brand-blue"></i> Valores de Referencia
        </h3>
        <span class="text-xs font-medium bg-slate-100 text-slate-600 px-2 py-1 rounded-full">Actualiza según gaceta</span>
    </div>
    
    <div class="p-6">
        <form method="POST" action="parametros.php">
            <input type="hidden" name="update_params" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($parametros as $param): ?>
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-200 hover:border-brand-blue transition-colors group">
                        <label class="block text-sm font-semibold text-slate-700 mb-1" for="param_<?php echo $param['nombre']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $param['nombre'])); ?>
                        </label>
                        <p class="text-xs text-slate-500 mb-3 h-8"><?php echo htmlspecialchars($param['descripcion']); ?></p>
                        <div class="relative">
                            <?php if (strpos($param['nombre'], 'tasa') !== false): ?>
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">%</span>
                                <input type="number" step="0.0001" name="parametros[<?php echo $param['nombre']; ?>]" id="param_<?php echo $param['nombre']; ?>" value="<?php echo htmlspecialchars($param['valor'] * (strpos($param['nombre'], 'tasa') !== false ? 100 : 1)); ?>" class="w-full pl-8 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-shadow text-slate-800 font-medium bg-white" required>
                            <?php else: ?>
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                                    <?php echo ($param['nombre'] == 'salario_minimo') ? 'Bs.' : '<i class="fa-regular fa-calendar"></i>'; ?>
                                </span>
                                <input type="number" step="0.01" name="parametros[<?php echo $param['nombre']; ?>]" id="param_<?php echo $param['nombre']; ?>" value="<?php echo htmlspecialchars($param['valor']); ?>" class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-shadow text-slate-800 font-medium bg-white" required>
                            <?php endif; ?>
                        </div>
                        <?php if (strpos($param['nombre'], 'tasa') !== false): ?>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Ingresa el valor porcentual (ej. 4 para 4%)</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-8 flex justify-end pt-4 border-t border-slate-200">
                <button type="submit" class="bg-brand-blue hover:bg-blue-800 text-white font-medium py-2.5 px-6 rounded-lg shadow-md transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

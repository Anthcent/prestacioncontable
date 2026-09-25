<?php
require_once 'config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$emp = [
    'cedula' => '', 'apellidos_nombres' => '', 'cargo' => '', 
    'clase_cargo' => '', 'nivel' => '', 'categoria' => '', 'fecha_ingreso' => ''
];

if($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if($result) $emp = $result;
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitización estricta de datos
    $cedula = trim($_POST['cedula']);
    // Capitalizar adecuadamente Nombres y Apellidos
    $nombres = mb_convert_case(trim($_POST['apellidos_nombres']), MB_CASE_TITLE, "UTF-8");
    $cargo = trim($_POST['cargo']);
    $clase_cargo = trim($_POST['clase_cargo']);
    $nivel = trim($_POST['nivel']);
    $categoria = trim($_POST['categoria']);
    $fecha_ingreso = empty($_POST['fecha_ingreso']) ? null : $_POST['fecha_ingreso'];
    
    try {
        if($id > 0) {
            $stmt = $pdo->prepare("UPDATE empleados SET cedula=?, apellidos_nombres=?, cargo=?, clase_cargo=?, nivel=?, categoria=?, fecha_ingreso=? WHERE id=?");
            $stmt->execute([$cedula, $nombres, $cargo, $clase_cargo, $nivel, $categoria, $fecha_ingreso, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO empleados (cedula, apellidos_nombres, cargo, clase_cargo, nivel, categoria, fecha_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cedula, $nombres, $cargo, $clase_cargo, $nivel, $categoria, $fecha_ingreso]);
            $id = $pdo->lastInsertId();
        }
        header("Location: empleados.php?msg=saved");
        exit;
    } catch(PDOException $e) {
        // Manejo Amigable de Errores (Anti-Crash)
        if ($e->errorInfo[1] == 1062) {
            $error = "Error: La cédula '{$cedula}' ya se encuentra registrada para otro trabajador.";
        } else {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

include 'includes/header.php'; 
?>

<div class="mb-6 flex items-center gap-4">
    <a href="empleados.php" class="text-slate-500 hover:text-brand-blue transition-colors">
        <i class="fa-solid fa-arrow-left text-xl"></i>
    </a>
    <div>
        <h2 class="text-2xl font-bold text-slate-800"><?php echo $id > 0 ? 'Editar Empleado' : 'Nuevo Empleado'; ?></h2>
        <p class="text-slate-500">Ingrese los datos laborales básicos</p>
    </div>
</div>

<?php if(isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r shadow-sm" role="alert">
        <p class="font-bold">Error</p>
        <p><?php echo $error; ?></p>
    </div>
<?php endif; ?>

<form method="POST" action="" class="glass-card rounded-xl p-8 max-w-4xl mx-auto shadow-sm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Fila 1 -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Cédula de Identidad *</label>
            <input type="text" name="cedula" value="<?php echo htmlspecialchars($emp['cedula']); ?>" required 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Apellidos y Nombres *</label>
            <input type="text" name="apellidos_nombres" value="<?php echo htmlspecialchars($emp['apellidos_nombres']); ?>" required 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
        </div>
        
        <!-- Fila 2 -->
        <div class="space-y-2 md:col-span-2">
            <label class="block text-sm font-medium text-slate-700">Cargo *</label>
            <input type="text" name="cargo" value="<?php echo htmlspecialchars($emp['cargo']); ?>" required 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
        </div>
        
        <!-- Fila 3 -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Clase de Cargo</label>
            <input type="text" name="clase_cargo" value="<?php echo htmlspecialchars($emp['clase_cargo']); ?>" 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Nivel</label>
            <input type="text" name="nivel" value="<?php echo htmlspecialchars($emp['nivel']); ?>" 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
        </div>
        
        <!-- Fila 4 -->
        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Categoría</label>
            <select name="categoria" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all bg-white">
                <option value="" <?php echo $emp['categoria'] == '' ? 'selected' : ''; ?>>-- Seleccione --</option>
                <option value="Obrero" <?php echo $emp['categoria'] == 'Obrero' ? 'selected' : ''; ?>>Obrero</option>
                <option value="Empleado" <?php echo $emp['categoria'] == 'Empleado' ? 'selected' : ''; ?>>Empleado</option>
            </select>
        </div>
        
        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-700">Fecha de Ingreso</label>
            <input type="date" name="fecha_ingreso" value="<?php echo htmlspecialchars($emp['fecha_ingreso']); ?>" 
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
        </div>
    </div>
    
    <div class="mt-8 flex justify-end gap-4 border-t border-slate-100 pt-6">
        <a href="empleados.php" class="px-6 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors">Cancelar</a>
        <button type="submit" class="bg-brand-blue hover:bg-blue-900 text-white px-8 py-2 rounded-lg shadow transition-colors font-medium">
            <i class="fa-solid fa-save mr-2"></i> Guardar Empleado
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>

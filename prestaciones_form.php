<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireLogin();
require_once 'includes/PrestacionesCalculator.php';

// Asegurar que todas las columnas requeridas existan en la BD automáticamente
$required_columns = [
    'bono_nocturno' => 'DECIMAL(15,2) DEFAULT 0.00',
    'regla_perfil' => "VARCHAR(50) DEFAULT 'LOTTT_30'",
    'inputs_json' => 'LONGTEXT DEFAULT NULL',
    'results_json' => 'LONGTEXT DEFAULT NULL',
    'deposito_fideicomiso' => 'DECIMAL(15,2) DEFAULT 0.00',
    'deduccion_faov' => 'DECIMAL(15,2) DEFAULT 0.00',
    'deduccion_ivss' => 'DECIMAL(15,2) DEFAULT 0.00',
    'deduccion_inces' => 'DECIMAL(15,2) DEFAULT 0.00',
    'deduccion_sso' => 'DECIMAL(15,2) DEFAULT 0.00',
    'deduccion_lph' => 'DECIMAL(15,2) DEFAULT 0.00',
    'deduccion_ince' => 'DECIMAL(15,2) DEFAULT 0.00',
    'otras_asignaciones_dias' => 'DECIMAL(10,2) DEFAULT 0.00',
    'otras_asignaciones_salario' => 'DECIMAL(15,2) DEFAULT 0.00',
    'aplicar_deducciones' => 'TINYINT(1) DEFAULT 1',
    'estado' => "VARCHAR(20) DEFAULT 'finalizado'",
    'parent_id' => 'INT DEFAULT NULL'
];
foreach ($required_columns as $col => $def) {
    try { $pdo->exec("ALTER TABLE prestaciones ADD COLUMN {$col} {$def}"); } catch(PDOException $e) {}
}

$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$regla_perfil_query = isset($_GET['regla_perfil']) ? $_GET['regla_perfil'] : 'LOTTT_30';

// Obtener lista completa de empleados para el selector rápido
$stmt_emp_list = $pdo->query("SELECT id, cedula, apellidos_nombres, cargo FROM empleados ORDER BY apellidos_nombres ASC");
$empleados_list = $stmt_emp_list->fetchAll(PDO::FETCH_ASSOC);

// Si se pasa id de prestacion y no empleado_id, obtener empleado_id de la prestación
if ($id > 0 && $empleado_id == 0) {
    $stmt_p = $pdo->prepare("SELECT empleado_id, regla_perfil FROM prestaciones WHERE id = ?");
    $stmt_p->execute([$id]);
    $row_p = $stmt_p->fetch(PDO::FETCH_ASSOC);
    if ($row_p) {
        $empleado_id = (int)$row_p['empleado_id'];
        $regla_perfil_query = $row_p['regla_perfil'];
    }
}

// Si no hay empleado seleccionado pero hay empleados en la base de datos, tomar el primero
if ($empleado_id == 0 && count($empleados_list) > 0 && $id == 0) {
    $empleado_id = (int)$empleados_list[0]['id'];
}

$emp = null;
if ($empleado_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
    $stmt->execute([$empleado_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Valores por defecto
$p = [
    'regla_perfil' => $regla_perfil_query,
    'fecha_calculo' => date('Y-m-d'),
    'fecha_egreso' => date('Y-m-d'),
    'motivo' => 'RENUNCIA',
    'sueldo_base_mensual' => 130.00,
    'salario_base_diario' => 4.33,
    'prima_antiguedad' => 0.00,
    'prima_hijos' => 0.00,
    'prima_transporte' => 0.00,
    'bono_nocturno' => 0.00,
    'prima_profesionalizacion' => 0.00,
    'sueldo_mensual_normal' => 130.00,
    'sueldo_diario_normal' => 4.33,
    'salario_diario_integral' => 0.00,
    'salario_integral_mensual' => 0.00,
    'dias_utilidades' => ($regla_perfil_query == 'LEGADO_120_180') ? 120 : 30,
    'dias_vacaciones_alicuota' => ($regla_perfil_query == 'LEGADO_120_180') ? 180 : 15,
    'alicuota_utilidades' => 0.00,
    'alicuota_vacaciones' => 0.00,
    'util_alicuota' => 0.00, 'util_dias' => 0.00, 'util_salario_normal_vac' => 0.00, 'util_total' => 0.00,
    'vac190_alicuota' => 0.00, 'vac190_dias' => 0.00, 'vac190_salario' => 0.00, 'vac190_total' => 0.00,
    'vac195_alicuota' => 0.00, 'vac195_dias' => 0.00, 'vac195_salario' => 0.00, 'vac195_total' => 0.00,
    'vac196_alicuota' => 0.00, 'vac196_dias' => 0.00, 'vac196_salario' => 0.00, 'vac196_total' => 0.00,
    'antig_nro_dias' => 0.00, 'antig_nro_meses' => 0.00, 'antig_anos' => 0.00,
    'antig_salario_integral_mensual' => 0.00, 'antig_monto_total' => 0.00,
    'otras_asignaciones_dias' => 0.00, 'otras_asignaciones_salario' => 0.00, 'otras_asignaciones' => 0.00,
    'intereses_antiguedad' => 0.00,
    'deposito_fideicomiso' => 0.00, 'deduccion_faov' => 0.00, 'deduccion_ivss' => 0.00, 'deduccion_inces' => 0.00,
    'deduccion_sso' => 0.00, 'deduccion_lph' => 0.00, 'deduccion_ince' => 0.00, 'otras_deducciones' => 0.00,
    'total_deducciones' => 0.00, 'aplicar_deducciones' => 1, 'total_asignaciones' => 0.00, 'neto_a_cobrar' => 0.00
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM prestaciones WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $p = array_merge($p, $result);
    }
} elseif ($empleado_id > 0) {
    // Si es un nuevo cálculo ($id == 0), buscar si el empleado ya tiene un historial registrado en prestaciones
    $stmt_prev = $pdo->prepare("SELECT sueldo_base_mensual, prima_antiguedad, prima_hijos, prima_transporte, bono_nocturno, prima_profesionalizacion, deposito_fideicomiso, motivo, aplicar_deducciones FROM prestaciones WHERE empleado_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_prev->execute([$empleado_id]);
    $prev = $stmt_prev->fetch(PDO::FETCH_ASSOC);
    if ($prev) {
        $p['sueldo_base_mensual'] = (float)$prev['sueldo_base_mensual'];
        $p['prima_antiguedad'] = (float)$prev['prima_antiguedad'];
        $p['prima_hijos'] = (float)$prev['prima_hijos'];
        $p['prima_transporte'] = (float)$prev['prima_transporte'];
        $p['bono_nocturno'] = (float)$prev['bono_nocturno'];
        $p['prima_profesionalizacion'] = (float)$prev['prima_profesionalizacion'];
        $p['deposito_fideicomiso'] = (float)$prev['deposito_fideicomiso'];
        if (!empty($prev['motivo'])) $p['motivo'] = $prev['motivo'];
        if (isset($prev['aplicar_deducciones'])) $p['aplicar_deducciones'] = (int)$prev['aplicar_deducciones'];
    } else {
        // Si no tiene historial previo, precargar salario mínimo de parámetros legales y sugerir primas iniciales
        try {
            $stmt_min = $pdo->query("SELECT valor FROM parametros_legales WHERE nombre = 'salario_minimo' LIMIT 1");
            $min_val = $stmt_min ? $stmt_min->fetchColumn() : 130.00;
            if ($min_val > 0) $p['sueldo_base_mensual'] = (float)$min_val;
        } catch(PDOException $e) {}

        if ($emp && !empty($emp['fecha_ingreso'])) {
            $diff_init = PrestacionesCalculator::diffDate($emp['fecha_ingreso'], $p['fecha_egreso']);
            $sug_init = PrestacionesCalculator::suggestPrimas($p['sueldo_base_mensual'], $diff_init['y'], $emp['clase_cargo'] ?? '', $emp['cargo'] ?? '');
            $p['prima_antiguedad'] = $sug_init['prima_antiguedad'];
            $p['prima_profesionalizacion'] = $sug_init['prima_profesionalizacion'];

            // Días de vacaciones según tiempo de servicio (Art. 190 y 192 LOTTT)
            $dias_adic_init = min(15, max(0, $diff_init['y']));
            $dias_vac_legales_calc = min(30, 15 + $dias_adic_init);
            if ($p['regla_perfil'] !== 'LEGADO_120_180') {
                $p['dias_vacaciones_alicuota'] = $dias_vac_legales_calc;
            }
        }
    }
}
$aplicar_deducciones = isset($p['aplicar_deducciones']) ? (int)$p['aplicar_deducciones'] : 1;

// Días de vacaciones legales sugeridos según antigüedad (para guía del usuario)
$dias_vac_legales_sug = 15;
if ($emp && !empty($emp['fecha_ingreso'])) {
    $diff_sug = PrestacionesCalculator::diffDate($emp['fecha_ingreso'], $p['fecha_egreso']);
    $dias_adic_sug = min(15, max(0, $diff_sug['y']));
    $dias_vac_legales_sug = min(30, 15 + $dias_adic_sug);
}
if ($id == 0 && $emp && !empty($emp['fecha_ingreso']) && $p['regla_perfil'] !== 'LEGADO_120_180') {
    $p['dias_vacaciones_alicuota'] = $dias_vac_legales_sug;
}

// Obtener parámetros legales institucionales para Primas y Alícuotas
$monto_prima_hijo_param = 12.50;
$monto_prima_transporte_param = 40.00;
try {
    $stmt_ph = $pdo->query("SELECT valor FROM parametros_legales WHERE nombre = 'monto_prima_hijo' LIMIT 1");
    if ($stmt_ph) {
        $val_ph = $stmt_ph->fetchColumn();
        if ($val_ph !== false && (float)$val_ph > 0) $monto_prima_hijo_param = (float)$val_ph;
    }
    $stmt_pt = $pdo->query("SELECT valor FROM parametros_legales WHERE nombre = 'monto_prima_transporte' LIMIT 1");
    if ($stmt_pt) {
        $val_pt = $stmt_pt->fetchColumn();
        if ($val_pt !== false && (float)$val_pt > 0) $monto_prima_transporte_param = (float)$val_pt;
    }
} catch(PDOException $e) {}

// Obtener lista de motivos de egreso de la base de datos
try {
    $stmt_mot = $pdo->query("SELECT id, nombre FROM motivos_egreso ORDER BY nombre ASC");
    $motivos_list = $stmt_mot ? $stmt_mot->fetchAll(PDO::FETCH_ASSOC) : [];
} catch(PDOException $e) {
    $motivos_list = [];
}
if (empty($motivos_list)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS motivos_egreso (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $defaults = ['Renuncia', 'Despido', 'Jubilación', 'Obrero', 'Empleado'];
        $stmt_seed = $pdo->prepare("INSERT IGNORE INTO motivos_egreso (nombre) VALUES (?)");
        foreach ($defaults as $d) { $stmt_seed->execute([$d]); }
        $stmt_mot = $pdo->query("SELECT id, nombre FROM motivos_egreso ORDER BY nombre ASC");
        $motivos_list = $stmt_mot ? $stmt_mot->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch(PDOException $e) {}
}

// Determinar motivo seleccionado actual (manteniendo compatibilidad)
$current_motivo = !empty($p['motivo']) ? trim($p['motivo']) : 'Renuncia';
$found_motivo = false;
foreach ($motivos_list as $m) {
    if (strcasecmp($m['nombre'], $current_motivo) === 0) {
        $current_motivo = $m['nombre'];
        $found_motivo = true;
        break;
    }
}
if (!$found_motivo && !empty($current_motivo)) {
    $motivos_list[] = ['id' => 0, 'nombre' => $current_motivo];
}

// Procesar formulario al guardar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_planilla'])) {
    $empleado_id = (int)$_POST['empleado_id'];
    $motivo = trim($_POST['motivo']);
    $fecha_calculo = $_POST['fecha_calculo'];
    $fecha_egreso = $_POST['fecha_egreso'];
    $regla_perfil = $_POST['regla_perfil'] ?? 'LOTTT_30';

    if (!$emp) {
        $error = "Debe seleccionar un empleado válido.";
    } elseif (!empty($emp['fecha_ingreso']) && !empty($fecha_egreso) && strtotime($fecha_egreso) < strtotime($emp['fecha_ingreso'])) {
        $error = "Error: La fecha de egreso no puede ser anterior a la fecha de ingreso (" . date('d/m/Y', strtotime($emp['fecha_ingreso'])) . ").";
    } else {
        $inputs = $_POST;
        $inputs['fecha_ingreso'] = $emp['fecha_ingreso'];
        $inputs['aplicar_deducciones'] = isset($_POST['aplicar_deducciones']) ? 1 : 0;
        $calc = PrestacionesCalculator::calculate($inputs);

        $inputs_json = json_encode($inputs, JSON_UNESCAPED_UNICODE);
        $results_json = json_encode($calc, JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            $sql = "UPDATE prestaciones SET 
                empleado_id=?, motivo=?, fecha_calculo=?, fecha_egreso=?, regla_perfil=?,
                sueldo_base_mensual=?, prima_antiguedad=?, prima_hijos=?, prima_transporte=?, bono_nocturno=?, prima_profesionalizacion=?,
                sueldo_mensual_normal=?, sueldo_diario_normal=?, salario_base_diario=?, salario_diario_integral=?, salario_integral_mensual=?,
                dias_utilidades=?, alicuota_utilidades=?, dias_vacaciones_alicuota=?, alicuota_vacaciones=?,
                util_alicuota=?, util_dias=?, util_salario_normal_vac=?, util_total=?,
                vac190_alicuota=?, vac190_dias=?, vac190_salario=?, vac190_total=?,
                vac195_alicuota=?, vac195_dias=?, vac195_salario=?, vac195_total=?,
                vac196_alicuota=?, vac196_dias=?, vac196_salario=?, vac196_total=?,
                antig_nro_dias=?, antig_nro_meses=?, antig_anos=?, antig_salario_integral_mensual=?, antig_monto_total=?,
                otras_asignaciones=?, intereses_antiguedad=?, total_asignaciones=?,
                deposito_fideicomiso=?, deduccion_faov=?, deduccion_ivss=?, deduccion_inces=?, deduccion_sso=?, deduccion_lph=?, deduccion_ince=?, otras_deducciones=?, total_deducciones=?, aplicar_deducciones=?,
                neto_a_cobrar=?, inputs_json=?, results_json=?
                WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empleado_id, $motivo, $fecha_calculo, $fecha_egreso, $regla_perfil,
                $calc['sueldo_base_mensual'], $calc['prima_antiguedad'], $calc['prima_hijos'], $calc['prima_transporte'], $calc['bono_nocturno'], $calc['prima_profesionalizacion'],
                $calc['sueldo_mensual_normal'], $calc['sueldo_diario_normal'], $calc['salario_base_diario'], $calc['salario_diario_integral'], $calc['salario_integral_mensual'],
                $calc['dias_utilidades'], $calc['alicuota_utilidades'], $calc['dias_vacaciones_alicuota'], $calc['alicuota_vacaciones'],
                $calc['util_alicuota'], $calc['util_dias'], $calc['util_salario_normal_vac'], $calc['util_total'],
                $calc['vac190_alicuota'], $calc['vac190_dias'], $calc['vac190_salario'], $calc['vac190_total'],
                $calc['vac195_alicuota'], $calc['vac195_dias'], $calc['vac195_salario'], $calc['vac195_total'],
                $calc['vac196_alicuota'], $calc['vac196_dias'], $calc['vac196_salario'], $calc['vac196_total'],
                $calc['antig_nro_dias'], $calc['antig_nro_meses'], $calc['antig_anos'], $calc['antig_salario_integral_mensual'], $calc['antig_monto_total'],
                $calc['otras_asignaciones'], $calc['intereses_antiguedad'], $calc['total_asignaciones'],
                $calc['deposito_fideicomiso'], $calc['deduccion_faov'], $calc['deduccion_ivss'], $calc['deduccion_inces'], $calc['deduccion_ivss'], $calc['deduccion_faov'], $calc['deduccion_inces'], $calc['otras_deducciones'], $calc['total_deducciones'], $calc['aplicar_deducciones'],
                $calc['neto_a_cobrar'], $inputs_json, $results_json, $id
            ]);
        } else {
            $sql = "INSERT INTO prestaciones (
                empleado_id, motivo, fecha_calculo, fecha_egreso, regla_perfil,
                sueldo_base_mensual, prima_antiguedad, prima_hijos, prima_transporte, bono_nocturno, prima_profesionalizacion,
                sueldo_mensual_normal, sueldo_diario_normal, salario_base_diario, salario_diario_integral, salario_integral_mensual,
                dias_utilidades, alicuota_utilidades, dias_vacaciones_alicuota, alicuota_vacaciones,
                util_alicuota, util_dias, util_salario_normal_vac, util_total,
                vac190_alicuota, vac190_dias, vac190_salario, vac190_total,
                vac195_alicuota, vac195_dias, vac195_salario, vac195_total,
                vac196_alicuota, vac196_dias, vac196_salario, vac196_total,
                antig_nro_dias, antig_nro_meses, antig_anos, antig_salario_integral_mensual, antig_monto_total,
                otras_asignaciones, intereses_antiguedad, total_asignaciones,
                deposito_fideicomiso, deduccion_faov, deduccion_ivss, deduccion_inces, deduccion_sso, deduccion_lph, deduccion_ince, otras_deducciones, total_deducciones, aplicar_deducciones,
                neto_a_cobrar, inputs_json, results_json
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empleado_id, $motivo, $fecha_calculo, $fecha_egreso, $regla_perfil,
                $calc['sueldo_base_mensual'], $calc['prima_antiguedad'], $calc['prima_hijos'], $calc['prima_transporte'], $calc['bono_nocturno'], $calc['prima_profesionalizacion'],
                $calc['sueldo_mensual_normal'], $calc['sueldo_diario_normal'], $calc['salario_base_diario'], $calc['salario_diario_integral'], $calc['salario_integral_mensual'],
                $calc['dias_utilidades'], $calc['alicuota_utilidades'], $calc['dias_vacaciones_alicuota'], $calc['alicuota_vacaciones'],
                $calc['util_alicuota'], $calc['util_dias'], $calc['util_salario_normal_vac'], $calc['util_total'],
                $calc['vac190_alicuota'], $calc['vac190_dias'], $calc['vac190_salario'], $calc['vac190_total'],
                $calc['vac195_alicuota'], $calc['vac195_dias'], $calc['vac195_salario'], $calc['vac195_total'],
                $calc['vac196_alicuota'], $calc['vac196_dias'], $calc['vac196_salario'], $calc['vac196_total'],
                $calc['antig_nro_dias'], $calc['antig_nro_meses'], $calc['antig_anos'], $calc['antig_salario_integral_mensual'], $calc['antig_monto_total'],
                $calc['otras_asignaciones'], $calc['intereses_antiguedad'], $calc['total_asignaciones'],
                $calc['deposito_fideicomiso'], $calc['deduccion_faov'], $calc['deduccion_ivss'], $calc['deduccion_inces'], $calc['deduccion_ivss'], $calc['deduccion_faov'], $calc['deduccion_inces'], $calc['otras_deducciones'], $calc['total_deducciones'], $calc['aplicar_deducciones'],
                $calc['neto_a_cobrar'], $inputs_json, $results_json
            ]);
            $id = $pdo->lastInsertId();
        }

        header("Location: prestaciones_view.php?id=" . $id);
        exit;
    }
}

include 'includes/header.php';
?>

<style>
.excel-grid {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
}
.excel-grid td, .excel-grid th {
    border: 1px solid #000;
    padding: 3.5px 5px;
    vertical-align: middle;
}
.excel-grid .bg-gray-200 { background-color: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.excel-grid .bg-gray-100 { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.excel-grid .text-right { text-align: right; }
.excel-grid .text-center { text-align: center; }
.excel-grid .font-bold { font-weight: bold; }
.excel-grid .border-0 { border: none !important; }
.excel-grid .border-b-0 { border-bottom: none !important; }
.excel-grid .border-t-0 { border-top: none !important; }
.excel-grid .text-red-600 { color: #dc2626 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

@media print {
    body * { visibility: hidden; }
    #tab-preview, #tab-preview * { visibility: visible; }
    #tab-preview {
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


<!-- BANNER SUPERIOR DE CÁLCULO DE PRESTACIONES -->
<div class="bg-gradient-to-r from-brand-dark via-slate-900 to-brand-blue text-white rounded-2xl p-6 mb-6 shadow-xl relative overflow-hidden no-print">
    <div class="relative z-10 flex flex-wrap justify-between items-center gap-4">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="bg-brand-yellow text-brand-dark font-extrabold text-xs px-3 py-1 rounded-full uppercase tracking-wider">
                    LIQUIDACIÓN DE PRESTACIONES SOCIALES
                </span>
                <span class="text-xs text-amber-300 font-semibold flex items-center gap-1.5">
                    <img src="assets/img/logo_icon.png" alt="PRIME" class="w-4 h-4 object-contain inline"> PRIME CONTADORES PÚBLICOS
                </span>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight">
                <?php echo ($p['regla_perfil'] == 'LEGADO_120_180') ? '📄 Planilla en Regla Legada (120 Util / 180 Vac)' : '📄 Planilla Oficial (Regla LOTTT 30 Días)'; ?>
            </h2>
            <p class="text-xs text-slate-300 mt-1 max-w-xl">
                Seleccione el trabajador y la regla de cálculo deseada. Los datos permanentes se recargan automáticamente.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="empleado_form.php" class="bg-brand-yellow hover:bg-yellow-400 text-brand-dark font-bold px-4 py-2.5 rounded-xl shadow text-xs transition-all flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> + Registrar Nuevo Empleado
            </a>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r shadow-sm flex items-center justify-between no-print">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-xl text-red-600"></i>
            <p class="font-medium text-sm"><?php echo $error; ?></p>
        </div>
    </div>
<?php endif; ?>

<form method="POST" id="form-prestaciones" action="prestaciones_form.php?id=<?php echo $id; ?>&empleado_id=<?php echo $empleado_id; ?>">
    <input type="hidden" name="empleado_id" value="<?php echo $empleado_id; ?>">
    <input type="hidden" id="fecha_ingreso" value="<?php echo htmlspecialchars($emp['fecha_ingreso'] ?? ''); ?>">
    <input type="hidden" id="emp_cargo" value="<?php echo htmlspecialchars($emp['cargo'] ?? ''); ?>">
    <input type="hidden" id="emp_clase_cargo" value="<?php echo htmlspecialchars($emp['clase_cargo'] ?? ''); ?>">
    <input type="hidden" id="emp_categoria" value="<?php echo htmlspecialchars($emp['categoria'] ?? ''); ?>">

    <!-- ACCIONES PRINCIPALES Y PESTAÑAS DE VISTA -->
    <div class="flex flex-wrap justify-between items-center mb-6 gap-4 no-print">
        <div class="bg-slate-200 p-1.5 rounded-2xl flex gap-1 shadow-inner">
            <button type="button" id="btn-tab-form" onclick="switchTab('form')" class="px-5 py-2 text-xs font-extrabold rounded-xl transition-all bg-white text-brand-blue shadow">
                <i class="fa-solid fa-pen-to-square mr-1"></i> Formulario de Datos (Edición)
            </button>
            <button type="button" id="btn-tab-preview" onclick="switchTab('preview')" class="px-5 py-2 text-xs font-extrabold rounded-xl transition-all text-slate-600 hover:text-slate-900">
                <i class="fa-solid fa-file-invoice mr-1"></i> Vista Previa Planilla Institucional
            </button>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" form="form-prestaciones" name="guardar_planilla" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-2.5 rounded-xl shadow-lg hover:shadow-emerald-600/30 transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-floppy-disk"></i> Guardar y Calcular Planilla
            </button>
        </div>
    </div>

    <!-- CONTENEDOR TAB 1: FORMULARIO INTERACTIVO TAILWIND -->
    <div id="tab-form" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- COLUMNA PRINCIPAL (CARDS DE CAPTURA) -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- CARD 1: SELECCIÓN DE EMPLEADO Y REGLA LEGAL -->
            <div id="card_empleado_regla" class="glass-card rounded-2xl p-6 shadow-sm border border-slate-200/80 relative z-30">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-user-check text-brand-blue"></i> 1. Empleado y Regla de Cálculo
                    </h3>
                    <span class="text-xs bg-blue-50 text-brand-blue font-bold px-3 py-1 rounded-full">Reutilización Automática</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Seleccionar Trabajador:</label>
                        <select onchange="window.location.href='prestaciones_form.php?empleado_id='+this.value+'&regla_perfil=<?php echo $p['regla_perfil']; ?>';" class="w-full p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none bg-slate-50 font-bold text-slate-800 text-sm">
                            <?php foreach ($empleados_list as $e_item): ?>
                                <option value="<?php echo $e_item['id']; ?>" <?php echo ($e_item['id'] == $empleado_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e_item['cedula']) . " — " . htmlspecialchars($e_item['apellidos_nombres']) . " (" . htmlspecialchars($e_item['cargo']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($emp): ?>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Cédula de Identidad</p>
                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($emp['cedula']); ?></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-[10px] text-slate-400 font-bold uppercase">Cargo y Nivel</p>
                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($emp['cargo']) . " (" . htmlspecialchars($emp['nivel'] ?: 'N/A') . ")"; ?></p>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Regla de Cálculo Legal:</label>
                        <select name="regla_perfil" id="regla_perfil" onchange="onReglaChange(this.value)" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none bg-white text-sm font-bold">
                            <option value="LOTTT_30" <?php echo ($p['regla_perfil'] == 'LOTTT_30') ? 'selected' : ''; ?>>Regla LOTTT Vigente (Utilidades 30d, Vacaciones 15-30d)</option>
                            <option value="LEGADO_120_180" <?php echo ($p['regla_perfil'] == 'LEGADO_120_180') ? 'selected' : ''; ?>>Regla Legada Histórica (120 Util / 180 Vac)</option>
                        </select>
                    </div>

                    <div class="relative z-40" id="motivo_combobox_container">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">
                            Motivo del Egreso: <span class="text-rose-600">*</span>
                        </label>

                        <!-- Hidden input para el envío del formulario -->
                        <input type="hidden" name="motivo" id="motivo_input" value="<?php echo htmlspecialchars($current_motivo); ?>">

                        <!-- Botón / Trigger que muestra la selección actual -->
                        <button type="button" id="motivo_trigger_btn" onclick="toggleMotivoDropdown()" 
                            class="w-full p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none flex items-center justify-between shadow-xs hover:border-slate-400 transition-all text-left cursor-pointer">
                            <div class="flex items-center gap-2 truncate">
                                <span class="w-6 h-6 rounded-lg bg-blue-50 text-brand-blue flex items-center justify-center text-xs font-bold shrink-0 border border-blue-100">
                                    <i class="fa-solid fa-tag"></i>
                                </span>
                                <span id="motivo_display_text" class="text-sm font-bold text-slate-800 truncate">
                                    <?php echo htmlspecialchars($current_motivo); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-1 text-slate-400 shrink-0">
                                <i id="motivo_chevron" class="fa-solid fa-chevron-down text-xs transition-transform duration-200"></i>
                            </div>
                        </button>

                        <!-- Panel flotante del Select Buscable y Gestionable -->
                        <div id="motivo_dropdown" class="absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-300 p-3 z-50 hidden transition-all duration-200 min-w-[290px]">
                            
                            <!-- Buscador interno en tiempo real -->
                            <div class="relative mb-2">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                                <input type="text" id="motivo_search_input" oninput="filterMotivos(this.value)" placeholder="Buscar motivo en la lista..." 
                                    class="w-full pl-8 pr-8 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none transition-all">
                                <button type="button" onclick="clearMotivoSearch()" id="motivo_clear_search_btn" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs hidden cursor-pointer">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <!-- Lista de opciones dinámicas con selección, edición y eliminación -->
                            <div id="motivos_items_list" class="max-h-48 overflow-y-auto space-y-1 custom-scrollbar pr-0.5">
                                <!-- Renderizado dinámicamente por JavaScript -->
                            </div>

                            <!-- Mensaje cuando no hay resultados de búsqueda -->
                            <div id="motivo_no_results" class="py-3 px-2 text-center text-xs font-bold text-slate-500 bg-slate-50 rounded-xl border border-slate-200 hidden">
                                <p>No se encontraron coincidencias.</p>
                            </div>

                            <!-- Sección inferior para agregar nuevo elemento directamente desde aquí -->
                            <div class="mt-2.5 pt-2.5 border-t border-slate-200">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center gap-1">
                                        <i class="fa-solid fa-plus-circle text-brand-blue"></i> Agregar nuevo motivo
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <input type="text" id="new_motivo_name" placeholder="Escribir nuevo motivo..." 
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();addNewMotivo();}"
                                        class="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-brand-blue focus:border-brand-blue outline-none">
                                    <button type="button" onclick="addNewMotivo()" class="bg-brand-blue hover:bg-blue-900 text-white font-extrabold px-3 py-1.5 rounded-lg text-xs transition-colors flex items-center gap-1 shadow-xs shrink-0 cursor-pointer">
                                        <i class="fa-solid fa-plus text-[10px] text-brand-yellow"></i>
                                        <span>Crear</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 2: RELACIÓN LABORAL Y TIEMPO TRABAJADO -->
            <div class="glass-card rounded-2xl p-6 shadow-sm border border-slate-200/80 relative z-10">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-5">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days text-brand-blue"></i> 2. Fechas y Tiempo de Servicio
                    </h3>
                    <div class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-800 border border-emerald-200/90 px-3.5 py-1.5 rounded-xl text-xs font-bold shadow-2xs">
                        <i class="fa-solid fa-clock-rotate-left text-emerald-600"></i>
                        <span class="text-slate-500 font-medium">Tiempo de Servicio:</span>
                        <span id="lbl_tiempo_servicio" class="text-emerald-900 font-black tracking-tight">0 Años, 0 Meses, 0 Días</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Fecha de Ingreso:</label>
                        <input type="date" value="<?php echo htmlspecialchars($emp['fecha_ingreso'] ?? ''); ?>" id="fecha_ingreso" class="w-full p-2.5 border border-slate-200 rounded-xl bg-slate-100 text-slate-700 text-sm font-bold" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Fecha de Egreso:</label>
                        <input type="date" name="fecha_egreso" id="fecha_egreso" value="<?php echo htmlspecialchars($p['fecha_egreso']); ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-bold text-slate-900 bg-white shadow-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Fecha de Cálculo:</label>
                        <input type="date" name="fecha_calculo" id="fecha_calculo" value="<?php echo htmlspecialchars($p['fecha_calculo']); ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-semibold text-slate-800">
                    </div>
                </div>

                <!-- Desglose de Tiempo Calculado Automáticamente (Refinado y Proporcionado) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-4">
                    <div class="bg-slate-50/80 p-3 rounded-xl text-center border border-slate-200 shadow-2xs">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider flex items-center justify-center gap-1">
                            <i class="fa-solid fa-calendar-check text-brand-blue text-xs"></i> Años Servicio
                        </p>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5 tracking-tight" id="t_anos">0</p>
                        <span class="text-[10px] text-slate-400 font-medium block">Años Cumplidos</span>
                    </div>
                    <div class="bg-slate-50/80 p-3 rounded-xl text-center border border-slate-200 shadow-2xs">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider flex items-center justify-center gap-1">
                            <i class="fa-solid fa-calendar text-slate-400 text-xs"></i> Meses Exced.
                        </p>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5 tracking-tight" id="t_meses">0</p>
                        <span class="text-[10px] text-slate-400 font-medium block">Fracción Meses</span>
                    </div>
                    <div class="bg-slate-50/80 p-3 rounded-xl text-center border border-slate-200 shadow-2xs">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider flex items-center justify-center gap-1">
                            <i class="fa-solid fa-sun text-amber-500 text-xs"></i> Días Exced.
                        </p>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5 tracking-tight" id="t_dias">0</p>
                        <span class="text-[10px] text-slate-400 font-medium block">Días Adicionales</span>
                    </div>
                    <div class="bg-indigo-50/50 p-3 rounded-xl text-center border border-indigo-200/80 shadow-2xs">
                        <p class="text-[10px] text-indigo-700 font-bold uppercase tracking-wider flex items-center justify-center gap-1">
                            <i class="fa-solid fa-calculator text-indigo-500 text-xs"></i> Meses Totales
                        </p>
                        <p class="text-xl sm:text-2xl font-black text-indigo-900 mt-0.5 tracking-tight" id="t_meses_total">0</p>
                        <span class="text-[10px] text-indigo-600/80 font-medium block">Base de Cálculo</span>
                    </div>
                </div>
            </div>

            <!-- CARD 3: SALARIOS Y PRIMAS -->
            <div class="glass-card rounded-2xl p-6 shadow-sm border border-slate-200/80">
                <div class="flex flex-wrap items-center justify-between border-b border-slate-100 pb-3.5 mb-4 gap-2">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-coins text-brand-blue"></i> 3. Salarios Base, Primas y Sueldo Normal
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Valores editables manualmente o asistidos automáticamente según normativa y cargo</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="autoSugerirPrimas(true)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-brand-blue font-bold text-xs rounded-lg border border-slate-300 shadow-2xs transition-all hover:border-brand-blue/50 cursor-pointer">
                            <i class="fa-solid fa-wand-magic-sparkles text-amber-500 text-xs"></i>
                            <span>Auto-sugerir Primas</span>
                        </button>
                    </div>
                </div>

                <!-- Toast Feedback for Card 3 -->
                <div id="toast_auto_primas" class="hidden mb-4 p-2.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center gap-2 shadow-2xs transition-all">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <span id="toast_auto_primas_text">Primas calculadas automáticamente según el cargo y tiempo de servicio.</span>
                </div>

                <!-- Grid de Inputs (2 filas de 3 columnas limpias y alineadas) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- 1. Sueldo Base Mensual -->
                    <div class="min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Sueldo Base Mensual:</label>
                            <span class="text-[10px] text-slate-400 font-medium">Editable</span>
                        </div>
                        <input type="number" step="0.01" name="sueldo_base_mensual" id="sueldo_base_mensual" value="<?php echo $p['sueldo_base_mensual']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-bold text-slate-900 text-right bg-white shadow-2xs min-w-0">
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500 min-w-0">
                            <span class="text-slate-400 font-medium">Diario (Base / 30):</span>
                            <input type="hidden" name="salario_base_diario" id="salario_base_diario" value="<?php echo $p['salario_base_diario']; ?>">
                            <span class="font-bold text-slate-700 truncate">Bs. <span id="disp_salario_base_diario"><?php echo number_format((float)$p['salario_base_diario'], 2, ',', '.'); ?></span></span>
                        </div>
                    </div>

                    <!-- 2. Prima de Antigüedad -->
                    <div class="min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Prima de Antigüedad:</label>
                        </div>
                        <input type="number" step="0.01" name="prima_antiguedad" id="prima_antiguedad" value="<?php echo $p['prima_antiguedad']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-semibold text-right text-slate-800 bg-white shadow-2xs min-w-0">
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500 min-w-0 gap-1">
                            <span id="lbl_sug_antig_desc" class="text-slate-400 font-medium truncate">0%</span>
                            <button type="button" id="badge_sug_antig" onclick="aplicarSugerenciaPrima('antiguedad')" class="text-brand-blue hover:text-blue-800 font-bold hover:underline cursor-pointer flex items-center gap-1 shrink-0" title="Click para aplicar sugerencia">
                                <span>Sugerido:</span> <b id="lbl_sug_antig_monto">Bs. 0,00</b>
                                <i class="fa-solid fa-arrow-down text-[9px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 3. Prima Profesionalización -->
                    <div class="min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Prima Profesionalización:</label>
                        </div>
                        <input type="number" step="0.01" name="prima_profesionalizacion" id="prima_profesionalizacion" value="<?php echo $p['prima_profesionalizacion']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-semibold text-right text-slate-800 bg-white shadow-2xs min-w-0">
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500 min-w-0 gap-1">
                            <span id="lbl_sug_prof_desc" class="text-slate-400 font-medium truncate max-w-[110px]">Sin título (0%)</span>
                            <button type="button" id="badge_sug_prof" onclick="aplicarSugerenciaPrima('profesionalizacion')" class="text-brand-blue hover:text-blue-800 font-bold hover:underline cursor-pointer flex items-center gap-1 shrink-0" title="Click para aplicar sugerencia">
                                <span>Sugerido:</span> <b id="lbl_sug_prof_monto">Bs. 0,00</b>
                                <i class="fa-solid fa-arrow-down text-[9px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 4. Prima Hijos -->
                    <div class="min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Prima por Hijos:</label>
                            <span class="text-[10px] text-slate-400 font-semibold" title="Parámetro institucional en BD: Bs. <?php echo number_format($monto_prima_hijo_param, 2, ',', '.'); ?> por hijo">Bs. <?php echo number_format($monto_prima_hijo_param, 2, ',', '.'); ?> c/u</span>
                        </div>
                        <input type="number" step="0.01" name="prima_hijos" id="prima_hijos" value="<?php echo $p['prima_hijos']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-semibold text-right text-slate-800 bg-white shadow-2xs min-w-0">
                        <div class="flex items-center justify-between mt-1 text-[11px] min-w-0">
                            <span class="text-slate-400 font-medium">Hijos:</span>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="aplicarHijos(1, <?php echo $monto_prima_hijo_param; ?>)" class="text-[10px] bg-slate-100 hover:bg-blue-50 hover:text-brand-blue text-slate-600 px-2 py-0.5 rounded font-bold border border-slate-200 cursor-pointer transition-colors" title="1 Hijo: Bs. <?php echo number_format($monto_prima_hijo_param * 1, 2, ',', '.'); ?> (Normativa)">1h</button>
                                <button type="button" onclick="aplicarHijos(2, <?php echo $monto_prima_hijo_param; ?>)" class="text-[10px] bg-slate-100 hover:bg-blue-50 hover:text-brand-blue text-slate-600 px-2 py-0.5 rounded font-bold border border-slate-200 cursor-pointer transition-colors" title="2 Hijos: Bs. <?php echo number_format($monto_prima_hijo_param * 2, 2, ',', '.'); ?> (Normativa)">2h</button>
                                <button type="button" onclick="aplicarHijos(3, <?php echo $monto_prima_hijo_param; ?>)" class="text-[10px] bg-slate-100 hover:bg-blue-50 hover:text-brand-blue text-slate-600 px-2 py-0.5 rounded font-bold border border-slate-200 cursor-pointer transition-colors" title="3 Hijos: Bs. <?php echo number_format($monto_prima_hijo_param * 3, 2, ',', '.'); ?> (Normativa)">3h</button>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Prima Transporte -->
                    <div class="min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Prima Transporte:</label>
                            <span class="text-[10px] text-slate-400 font-semibold" title="Parámetro institucional en BD: Bs. <?php echo number_format($monto_prima_transporte_param, 2, ',', '.'); ?>">Bs. <?php echo number_format($monto_prima_transporte_param, 2, ',', '.'); ?></span>
                        </div>
                        <input type="number" step="0.01" name="prima_transporte" id="prima_transporte" value="<?php echo $p['prima_transporte']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-semibold text-right text-slate-800 bg-white shadow-2xs min-w-0">
                        <div class="flex items-center justify-between mt-1 text-[11px] min-w-0">
                            <span class="text-slate-400 font-medium">Presets:</span>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="aplicarPresetTransporte(<?php echo $monto_prima_transporte_param; ?>)" class="text-[10px] bg-slate-100 hover:bg-blue-50 hover:text-brand-blue text-slate-600 px-2 py-0.5 rounded font-bold border border-slate-200 cursor-pointer transition-colors" title="Base Normativa: Bs. <?php echo number_format($monto_prima_transporte_param, 2, ',', '.'); ?>"><?php echo (int)$monto_prima_transporte_param; ?> Bs.</button>
                                <button type="button" onclick="aplicarPresetTransporte(100.00)" class="text-[10px] bg-slate-100 hover:bg-blue-50 hover:text-brand-blue text-slate-600 px-2 py-0.5 rounded font-bold border border-slate-200 cursor-pointer transition-colors" title="Preset Institucional Alternativo: Bs. 100,00">100 Bs.</button>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Bono Nocturno -->
                    <div class="min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider truncate">Bono Nocturno:</label>
                        </div>
                        <input type="number" step="0.01" name="bono_nocturno" id="bono_nocturno" value="<?php echo $p['bono_nocturno']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-blue outline-none text-sm font-semibold text-right text-slate-800 bg-white shadow-2xs min-w-0">
                        <div class="flex items-center justify-end mt-1 text-[11px]">
                            <button type="button" onclick="document.getElementById('bono_nocturno').value='0.00'; calculateAll();" class="text-slate-400 hover:text-rose-600 text-[10px] font-semibold cursor-pointer">Limpiar a 0,00</button>
                        </div>
                    </div>
                </div>

                <!-- Consolidación Ejecutiva del Sueldo Normal (Barra Inferior Equilibrada) -->
                <div class="mt-5 pt-3.5 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3 bg-slate-50/90 border border-slate-200 rounded-xl flex items-center justify-between min-w-0 shadow-2xs">
                        <div class="min-w-0 mr-2">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-brand-blue shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 tracking-wide truncate">Sueldo Normal Mensual</span>
                            </div>
                            <span class="text-[10px] text-slate-500 font-medium ml-3.5 block">Base + Primas recurrentes</span>
                        </div>
                        <div class="text-right shrink-0">
                            <input type="hidden" name="sueldo_mensual_normal" id="sueldo_mensual_normal" value="<?php echo $p['sueldo_mensual_normal']; ?>">
                            <span class="text-base sm:text-lg font-black text-brand-blue break-all" id="disp_sueldo_mensual_normal">
                                Bs. <?php echo number_format((float)$p['sueldo_mensual_normal'], 2, ',', '.'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50/90 border border-slate-200 rounded-xl flex items-center justify-between min-w-0 shadow-2xs">
                        <div class="min-w-0 mr-2">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-brand-blue shrink-0"></span>
                                <span class="text-xs font-bold text-slate-800 tracking-wide truncate">Sueldo Normal Diario</span>
                            </div>
                            <span class="text-[10px] text-slate-500 font-medium ml-3.5 block">Sueldo Normal Mensual / 30</span>
                        </div>
                        <div class="text-right shrink-0">
                            <input type="hidden" name="sueldo_diario_normal" id="sueldo_diario_normal" value="<?php echo $p['sueldo_diario_normal']; ?>">
                            <span class="text-base sm:text-lg font-black text-brand-blue break-all" id="disp_sueldo_diario_normal">
                                Bs. <?php echo number_format((float)$p['sueldo_diario_normal'], 2, ',', '.'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 4: VARIABLES DEL CÁLCULO, ALÍCUOTAS Y DÍAS -->
            <div class="glass-card rounded-2xl p-6 shadow-sm border border-slate-200/80">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-brand-blue"></i> 4. Alícuotas, Asignaciones y Días
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ALÍCUOTAS -->
                    <div class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-xs text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-calculator text-brand-blue"></i> Parámetros de Alícuota
                            </h4>
                            <button type="button" onclick="abrirModalAlicuotas()" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-brand-blue hover:text-blue-900 bg-white hover:bg-blue-50 border border-slate-300 hover:border-brand-blue rounded-lg shadow-2xs transition-all cursor-pointer" title="Configurar y simular alícuotas con fórmulas">
                                <i class="fa-solid fa-sliders text-slate-500"></i>
                                <span>Ajustar y Simular</span>
                            </button>
                        </div>
                        <div id="toast_modal_alicuotas" class="hidden p-2 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-2xs">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i>
                            <span>Alícuotas actualizadas y recalculadas con éxito.</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <!-- Días Utilidades (Alícuota) -->
                            <div>
                                <div class="flex items-center justify-between mb-0.5">
                                    <label class="block text-[11px] font-bold text-slate-700 truncate">Días Utilidades:</label>
                                    <span class="text-[10px] text-slate-400 font-medium">Anual</span>
                                </div>
                                <input type="number" name="dias_utilidades" id="dias_utilidades" value="<?php echo $p['dias_utilidades']; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm text-center font-bold">
                                <div class="flex items-center justify-between mt-1 text-[10px] text-slate-400">
                                    <span>LOTTT: 30d</span>
                                    <span>Gob: 120d</span>
                                </div>
                            </div>

                            <!-- Alícuota Diaria Utilidades -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Alícuota Diaria Util.:</label>
                                <input type="number" step="0.01" name="alicuota_utilidades" id="alicuota_utilidades" value="<?php echo number_format((float)$p['alicuota_utilidades'], 2, '.', ''); ?>" class="w-full p-2 border border-slate-200 bg-slate-200 text-slate-700 text-sm text-center font-mono font-bold" readonly>
                                <div class="mt-1 text-[10px] text-slate-400 text-right">
                                    <span>(Base + Vac) / 360</span>
                                </div>
                            </div>

                            <!-- Días Vacaciones (Alícuota) -->
                            <div>
                                <div class="flex items-center justify-between mb-0.5">
                                    <label class="block text-[11px] font-bold text-slate-700 truncate">Días Vac. (Alícuota):</label>
                                    <button type="button" onclick="aplicarDiasVacLegales()" id="btn_sug_vac_dias" class="text-[10px] text-brand-blue hover:text-blue-800 font-bold hover:underline cursor-pointer" title="Click para aplicar días legales calculados según la antigüedad">
                                        LOTTT: <b id="lbl_dias_vac_sug"><?php echo $dias_vac_legales_sug; ?>d</b>
                                    </button>
                                </div>
                                <input type="number" name="dias_vacaciones_alicuota" id="dias_vacaciones_alicuota" value="<?php echo $p['dias_vacaciones_alicuota']; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm text-center font-bold">
                                <div class="flex items-center justify-between mt-1 text-[10px] text-slate-400">
                                    <span id="lbl_antig_vac_desc">Art. 190 y 192 LOTTT</span>
                                </div>
                            </div>

                            <!-- Alícuota Diaria Vacaciones -->
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-0.5">Alícuota Diaria Vac.:</label>
                                <input type="number" step="0.01" name="alicuota_vacaciones" id="alicuota_vacaciones" value="<?php echo number_format((float)$p['alicuota_vacaciones'], 2, '.', ''); ?>" class="w-full p-2 border border-slate-200 bg-slate-200 text-slate-700 text-sm text-center font-mono font-bold" readonly>
                                <div class="mt-1 text-[10px] text-slate-400 text-right">
                                    <span>SDN × Días / 360</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SALARIOS INTEGRALES -->
                    <div class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-200 min-w-0 overflow-hidden">
                        <h4 class="font-bold text-xs text-slate-700 uppercase tracking-wider">Salario Integral Calculado</h4>
                        <div class="min-w-0">
                            <label class="block text-[11px] font-semibold text-slate-500">Salario Diario Integral (Bs.):</label>
                            <input type="hidden" name="salario_diario_integral" id="salario_diario_integral" value="<?php echo $p['salario_diario_integral']; ?>">
                            <div class="p-2 border border-slate-200 bg-slate-200/80 rounded-lg text-slate-800 text-sm font-black text-right min-w-0 break-all">
                                Bs. <span id="disp_salario_diario_integral"><?php echo number_format((float)$p['salario_diario_integral'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <label class="block text-[11px] font-semibold text-slate-500">Salario Integral Mensual (Bs.):</label>
                            <input type="hidden" name="salario_integral_mensual" id="salario_integral_mensual" value="<?php echo $p['salario_integral_mensual']; ?>">
                            <div class="p-2 border border-slate-200 bg-slate-200/80 rounded-lg text-slate-800 text-sm font-black text-right min-w-0 break-all">
                                Bs. <span id="disp_salario_integral_mensual"><?php echo number_format((float)$p['salario_integral_mensual'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DÍAS FRACCIONADOS Y CONCEPTOS -->
                <div class="mt-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-2">
                        <h4 class="font-bold text-xs text-slate-700 uppercase tracking-wider">Días a Pagar por Concepto</h4>
                        <div class="flex items-center gap-2">
                            <span id="toast_auto_dias" class="hidden text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">
                                <i class="fa-solid fa-check me-1"></i> Días calculados según LOTTT
                            </span>
                            <button type="button" id="btn-auto-dias" onclick="autoCalcularDiasLegales(true)" class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 hover:bg-blue-100 text-brand-blue border border-blue-200 rounded-lg text-xs font-bold transition shadow-sm cursor-pointer" title="Auto-calcular días según LOTTT conservando la opción de editar manualmente">
                                <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i> Auto-calcular Días Sugeridos (LOTTT)
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="vac195_dias" id="vac195_dias" value="0">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 min-w-0 overflow-hidden">
                            <label class="block text-[11px] font-bold text-slate-600 truncate mb-1" title="Días Utilidades (Art. 131, 132 y 136 LOTTT)">Días Utilidades (Art. 131, 132 y 136):</label>
                            <input type="number" step="0.01" name="util_dias" id="util_dias" value="<?php echo $p['util_dias']; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm text-center font-bold min-w-0">
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 min-w-0 overflow-hidden">
                            <label class="block text-[11px] font-bold text-slate-600 truncate mb-1" title="Días Bono Vacacional (Art 190 y 192 LOTTT)">Días Bono Vac. (Art 190 y 192):</label>
                            <input type="number" step="0.01" name="vac190_dias" id="vac190_dias" value="<?php echo $p['vac190_dias']; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm text-center font-bold min-w-0">
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 min-w-0 overflow-hidden">
                            <label class="block text-[11px] font-bold text-slate-600 truncate mb-1" title="Días Disfrute Vacaciones (Art 196 LOTTT)">Días Disfrute Vac. (Art 196):</label>
                            <input type="number" step="0.01" name="vac196_dias" id="vac196_dias" value="<?php echo $p['vac196_dias']; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm text-center font-bold min-w-0">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 min-w-0 overflow-hidden shadow-2xs">
                            <div class="flex items-center justify-between mb-1 gap-1">
                                <label class="text-[11px] font-bold text-slate-700 uppercase tracking-wide truncate">Años Antigüedad:</label>
                                <span class="text-[9px] bg-brand-blue/10 text-brand-blue px-1.5 py-0.5 rounded font-bold shrink-0 border border-brand-blue/20">30 d/año</span>
                            </div>
                            <input type="number" step="0.01" name="antig_anos" id="antig_anos" value="<?php echo $p['antig_anos']; ?>" class="w-full p-2 border border-slate-200 rounded-lg text-sm text-center font-bold text-slate-900 bg-slate-100 min-w-0" readonly>
                            <span class="text-[10px] text-slate-500 font-medium block mt-1 leading-tight">30 días por cada año completo</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 min-w-0 overflow-hidden">
                            <label class="block text-[11px] font-bold text-slate-600 truncate mb-1">Días Antigüedad Adicionales:</label>
                            <input type="number" step="0.01" name="antig_nro_dias" id="antig_nro_dias" value="<?php echo $p['antig_nro_dias']; ?>" class="w-full p-2 border border-slate-200 rounded-lg text-sm text-center font-bold bg-slate-100 min-w-0" readonly>
                            <span class="text-[10px] text-slate-500 block mt-1 leading-tight">5 días por mes; máximo 30 días</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 min-w-0 overflow-hidden">
                            <label class="block text-[11px] font-bold text-slate-600 truncate mb-1">Intereses por Antigüedad (Bs.):</label>
                            <input type="number" step="0.01" name="intereses_antiguedad" id="intereses_antiguedad" value="<?php echo $p['intereses_antiguedad']; ?>" class="w-full p-2 border border-slate-300 rounded-lg text-sm text-right font-bold min-w-0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD 5: DEDUCCIONES (OPCIONALES) -->
            <div class="glass-card rounded-2xl p-6 shadow-sm border border-slate-200/80 transition-all duration-300">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-5">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-hand-holding-dollar text-red-500"></i> 5. Retenciones y Deducciones
                    </h3>
                    <!-- SWITCH INTERACTIVO PARA HACER DEDUCCIONES OPCIONALES -->
                    <div class="flex items-center gap-3 bg-slate-50 py-1.5 px-3.5 rounded-xl border border-slate-200 shadow-sm">
                        <span class="text-xs font-bold text-slate-700 select-none">¿Aplicar Deducciones?</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="aplicar_deducciones" id="aplicar_deducciones" value="1" class="sr-only peer" <?php echo ($aplicar_deducciones ? 'checked' : ''); ?> onchange="toggleDeducciones(this.checked)">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            <span id="lbl_deducciones_status" class="ml-2 text-xs font-bold <?php echo $aplicar_deducciones ? 'text-emerald-700' : 'text-slate-400'; ?>">
                                <?php echo $aplicar_deducciones ? 'SÍ (Activas)' : 'NO (Omitidas)'; ?>
                            </span>
                        </label>
                    </div>
                </div>

                <div id="contenedor_deducciones" class="<?php echo $aplicar_deducciones ? '' : 'hidden'; ?> transition-all duration-300">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="min-w-0">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1 truncate">FIDEICOMISO / Depósito Bancario:</label>
                            <input type="number" step="0.01" name="deposito_fideicomiso" id="deposito_fideicomiso" value="<?php echo $p['deposito_fideicomiso'] ?: $p['otras_deducciones']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm text-right text-red-600 font-bold min-w-0">
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1 truncate">F.A.O.V. / L.P.H.:</label>
                            <input type="number" step="0.01" name="deduccion_lph" id="deduccion_lph" value="<?php echo $p['deduccion_lph'] ?: $p['deduccion_faov']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm text-right text-red-600 font-bold min-w-0">
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1 truncate">I.V.S.S. / S.S.O.:</label>
                            <input type="number" step="0.01" name="deduccion_sso" id="deduccion_sso" value="<?php echo $p['deduccion_sso'] ?: $p['deduccion_ivss']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm text-right text-red-600 font-bold min-w-0">
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1 truncate">INCES:</label>
                            <input type="number" step="0.01" name="deduccion_ince" id="deduccion_ince" value="<?php echo $p['deduccion_ince'] ?: $p['deduccion_inces']; ?>" class="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm text-right text-red-600 font-bold min-w-0">
                        </div>
                    </div>
                </div>

                <div id="aviso_deducciones_omitidas" class="<?php echo $aplicar_deducciones ? 'hidden' : ''; ?> p-4 bg-amber-50 rounded-xl border border-amber-200 text-amber-800 text-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-amber-600 text-lg"></i>
                        <div>
                            <strong>Deducciones Omitidas:</strong> No se restará ningún concepto al total de asignaciones. En la planilla no se imprimirá la sección de retenciones.
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('aplicar_deducciones').click()" class="px-3 py-1.5 bg-amber-200 hover:bg-amber-300 text-amber-900 rounded-lg font-bold text-xs transition shrink-0 cursor-pointer">
                        <i class="fa-solid fa-plus-circle me-1"></i> Activar Deducciones
                    </button>
                </div>
            </div>

        </div>

        <!-- PANEL LATERAL DERECHO: RESUMEN VIVO EN TIEMPO REAL -->
        <div class="space-y-6">
            <div class="glass-card rounded-2xl p-6 shadow-xl border-2 border-brand-blue/20 sticky top-6 bg-gradient-to-b from-white to-slate-50">
                <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider border-b border-slate-200 pb-3 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-calculator text-brand-yellow"></i> Resumen del Cálculo
                </h3>

                <div class="space-y-4">
                    <div class="flex flex-wrap justify-between items-baseline gap-1 text-xs py-1 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold shrink-0">Sueldo Normal Mensual:</span>
                        <span class="font-bold text-slate-800 break-all text-right ml-auto">Bs. <span id="sidebar_smn"><?php echo number_format((float)$p['sueldo_mensual_normal'], 2, ',', '.'); ?></span></span>
                    </div>
                    <div class="flex flex-wrap justify-between items-baseline gap-1 text-xs py-1 border-b border-slate-100">
                        <span class="text-slate-500 font-semibold shrink-0">Salario Diario Integral:</span>
                        <span class="font-bold text-slate-800 break-all text-right ml-auto">Bs. <span id="sidebar_sdi"><?php echo number_format((float)$p['salario_diario_integral'], 2, ',', '.'); ?></span></span>
                    </div>

                    <div class="flex flex-wrap justify-between items-baseline gap-1 text-sm py-1 border-b border-slate-100">
                        <span class="text-slate-700 font-bold shrink-0">Total Asignaciones:</span>
                        <span class="font-black text-emerald-600 break-all text-right ml-auto">Bs. <span id="sidebar_asig"><?php echo number_format((float)$p['total_asignaciones'], 2, ',', '.'); ?></span></span>
                    </div>

                    <div class="flex flex-wrap justify-between items-baseline gap-1 text-sm py-1 border-b border-slate-100">
                        <span class="text-slate-700 font-bold shrink-0">Total Deducciones:</span>
                        <span class="font-black text-rose-600 break-all text-right ml-auto">Bs. <span id="sidebar_deduc"><?php echo number_format((float)$p['total_deducciones'], 2, ',', '.'); ?></span><span id="sidebar_deduc_badge" class="text-[10px] text-amber-600 font-bold ml-1 <?php echo $aplicar_deducciones ? 'hidden' : ''; ?>">(Omitidas)</span></span>
                    </div>

                    <div class="bg-gradient-to-tr from-brand-dark via-slate-900 to-brand-blue p-4 sm:p-5 rounded-2xl text-white text-center shadow-xl mt-4 overflow-hidden border-2 border-brand-yellow/30">
                        <p class="text-[11px] font-extrabold text-brand-yellow uppercase tracking-widest mb-1 flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-coins text-brand-yellow text-xs"></i> Monto Neto a Cobrar
                        </p>
                        <div class="mt-2 min-h-[48px] flex flex-col items-center justify-center px-1">
                            <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider">Bolívares (Bs.)</span>
                            <p id="sidebar_neto_container" class="w-full text-center mt-0.5">
                                <span id="sidebar_neto" class="text-2xl sm:text-3xl font-black tracking-tight text-white break-all inline-block max-w-full"><?php echo number_format((float)$p['neto_a_cobrar'], 2, ',', '.'); ?></span>
                            </p>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="pt-4 space-y-2">
                        <button type="submit" name="guardar_planilla" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3 rounded-xl shadow-lg hover:shadow-emerald-600/30 transition-all flex items-center justify-center gap-2 text-sm">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar Planilla
                        </button>
                        <?php if ($id > 0): ?>
                            <a href="prestaciones_view.php?id=<?php echo $id; ?>" target="_blank" class="w-full bg-brand-blue hover:bg-blue-800 text-white font-bold py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 text-xs">
                                <i class="fa-solid fa-print"></i> Ver Planilla Oficial Impresa
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- CONTENEDOR TAB 2: VISTA PREVIA DE LA PLANILLA INSTITUCIONAL -->
    <div id="tab-preview" class="hidden bg-white p-6 rounded-2xl shadow-md border border-slate-200">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200 no-print">
            <div>
                <h4 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-brand-blue"></i> Vista Previa de la Planilla Oficial
                </h4>
                <p class="text-xs text-slate-500">Planilla institucional PRIME CONTADORES PÚBLICOS conforme a la LOTTT.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm cursor-pointer">
                    <i class="fa-solid fa-print"></i> Imprimir Planilla
                </button>
                <?php if ($id > 0): ?>
                    <a href="prestaciones_view.php?id=<?php echo $id; ?>" target="_blank" class="px-4 py-2 bg-brand-blue hover:bg-blue-800 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver Hoja Completa
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="overflow-x-auto bg-slate-50 p-2 sm:p-4 rounded-xl border border-slate-200">
            <table class="excel-grid w-full bg-white shadow-sm">
                <colgroup>
                    <col style="width: 14%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 23%;">
                    <col style="width: 15%;">
                </colgroup>
                <!-- Cabecera Institucional y Logos Centrados -->
                <tr>
                    <td colspan="7" class="border-0" style="border: none !important; padding: 8px 0;">
                        <table style="width: 100%; border-collapse: collapse; border: none; margin: 0;">
                            <tr>
                                <td style="width: 25%; text-align: left; vertical-align: middle; border: none; padding: 0;">
                                    <img src="assets/img/logo_prime.png" alt="PRIME Contadores Públicos" style="height: 48px; max-width: 100%; object-fit: contain; display: inline-block;">
                                </td>
                                <td style="width: 50%; text-align: center; vertical-align: middle; border: none; padding: 0 5px; line-height: 1.35;">
                                    <div style="font-weight: 800; font-size: 13px; color: #000; letter-spacing: 0.5px; text-transform: uppercase;">PRIME CONTADORES PÚBLICOS</div>
                                    <div style="font-weight: 800; font-size: 11.5px; color: #000; letter-spacing: 0.5px; text-transform: uppercase;">SISTEMA INTEGRAL DE NÓMINA Y PRESTACIONES</div>
                                    <div style="font-weight: 700; font-size: 10.5px; color: #2563eb; letter-spacing: 0.5px; text-transform: uppercase;">LIQUIDACIÓN DE PRESTACIONES SOCIALES - LOTTT</div>
                                </td>
                                <td style="width: 25%; text-align: right; vertical-align: middle; border: none; padding: 0;">
                                    <img src="assets/img/logo_icon.png" alt="PRIME" style="height: 48px; max-width: 100%; object-fit: contain; margin-left: auto; display: inline-block;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td colspan="7" class="border-0" style="height: 4px;"></td></tr>
                
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">CALCULO  DE  PRESTACIONES  SOCIALES</th></tr>
                
                <tr>
                    <td colspan="3" class="font-bold text-right">FECHA:</td>
                    <td colspan="4" class="text-center font-bold" id="pv_fecha_calculo"><?php echo date('d/m/Y', strtotime($p['fecha_calculo'] ?: date('Y-m-d'))); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold text-right">CARGO:</td>
                    <td colspan="2" class="text-center font-bold" id="pv_cargo"><?php echo htmlspecialchars($emp['cargo'] ?? ''); ?></td>
                    <td class="font-bold text-right">CLASE DE CARGO:</td>
                    <td class="text-center font-bold" id="pv_clase_cargo"><?php echo htmlspecialchars($emp['clase_cargo'] ?? 'BACHILLER'); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold text-right">Apellidos  y  Nombres :</td>
                    <td colspan="2" class="text-center font-bold" id="pv_apellidos_nombres"><?php echo htmlspecialchars($emp['apellidos_nombres'] ?? ''); ?></td>
                    <td class="font-bold text-right">NIVEL:</td>
                    <td class="text-center font-bold" id="pv_nivel"><?php echo htmlspecialchars($emp['nivel'] ?? '99'); ?></td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold text-right">Cedula de Identidad :</td>
                    <td colspan="2" class="text-center font-bold" id="pv_cedula"><?php echo htmlspecialchars($emp['cedula'] ?? ''); ?></td>
                    <td class="font-bold text-right">CATEGORIA:</td>
                    <td class="text-center font-bold" id="pv_categoria"><?php echo htmlspecialchars($emp['categoria'] ?? 'EMPLEADO'); ?></td>
                </tr>
                <tr><td colspan="7" class="border-0" style="height: 4px;"></td></tr>
                <tr>
                    <td colspan="3" class="font-bold text-right">MOTIVO:</td>
                    <td colspan="4" class="text-left font-bold" style="padding-left: 12px;" id="pv_motivo"><?php echo htmlspecialchars($current_motivo ?: 'RENUNCIA'); ?></td>
                </tr>
                
                <!-- TIEMPO NETO TRABAJADO -->
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px; letter-spacing: 0.5px;">TIEMPO  NETO  TRABAJADO</th></tr>
                
                <tr>
                    <td colspan="2" class="border-b-0"></td>
                    <td colspan="3" class="text-center font-bold bg-gray-100">DÍA / MES / AÑO</td>
                    <td class="font-bold text-right">Sueldo Base Mensual</td>
                    <td class="text-right font-bold" id="pv_sbm">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold text-center">FECHA DE INGRESO</td>
                    <td colspan="3" class="text-center font-bold" id="pv_fecha_ingreso"><?php echo !empty($emp['fecha_ingreso']) ? date('d/m/Y', strtotime($emp['fecha_ingreso'])) : ''; ?></td>
                    <td class="font-bold text-right">Salario Base Diario</td>
                    <td class="text-right font-bold bg-gray-50" id="pv_sbd">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold text-center">FECHA DE EGRESO</td>
                    <td colspan="3" class="text-center font-bold" id="pv_fecha_egreso"><?php echo !empty($p['fecha_egreso']) ? date('d/m/Y', strtotime($p['fecha_egreso'])) : ''; ?></td>
                    <td class="font-bold text-right">Prima de Antigüedad</td>
                    <td class="text-right font-bold" id="pv_prima_antig">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="border-b-0"></td>
                    <td class="text-center font-bold bg-gray-100">Año</td>
                    <td class="text-center font-bold bg-gray-100">Mes</td>
                    <td class="text-center font-bold bg-gray-100">Dia</td>
                    <td class="font-bold text-right">Prima Hijos</td>
                    <td class="text-right font-bold" id="pv_prima_hijos">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="border-b-0 border-t-0"></td>
                    <td colspan="3" class="border-b-0 border-t-0"></td>
                    <td class="font-bold text-right">Prima por Transporte</td>
                    <td class="text-right font-bold" id="pv_prima_transp">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="border-b-0 border-t-0"></td>
                    <td colspan="3" class="border-b-0 border-t-0"></td>
                    <td class="font-bold text-right">Bono Nocturno</td>
                    <td class="text-right font-bold" id="pv_bono_noct">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="border-b-0 border-t-0"></td>
                    <td colspan="3" class="border-b-0 border-t-0"></td>
                    <td class="font-bold text-right">Prima Profesionalización</td>
                    <td class="text-right font-bold" id="pv_prima_prof">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold text-left">Tiempo de servicio</td>
                    <td class="text-center font-bold" id="pv_t_anos">0</td>
                    <td class="text-center font-bold" id="pv_t_meses">0</td>
                    <td class="text-center font-bold" id="pv_t_dias">0</td>
                    <td class="font-bold text-right bg-gray-100">Sueldo Normal Mensual</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_smn">0,00</td>
                </tr>
                <tr>
                    <td colspan="5" class="border-b-0 border-t-0"></td>
                    <td class="font-bold text-right bg-gray-100">Sueldo Normal Diario</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_sdn">0,00</td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold text-left">Tiempo de servicio en meses</td>
                    <td class="text-center font-bold" id="pv_t_meses_total">0</td>
                    <td class="border-b-0 border-t-0"></td>
                    <td class="font-bold text-right bg-gray-100">Salario Integral Diario</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_sdi">0,00</td>
                </tr>
                
                <tr><td colspan="7" class="border-0" style="height: 4px;"></td></tr>
                
                <!-- PARÁMETROS Y ALÍCUOTAS -->
                <tr>
                    <td colspan="2" class="font-bold">Dias de utilidad</td>
                    <td class="text-center font-bold bg-gray-100">Dias</td>
                    <td colspan="2" class="text-center font-bold bg-gray-100">Alicuota</td>
                    <td class="font-bold text-right bg-gray-100">Salario Normal Mensual</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_smn_2">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold">( Alicuota )</td>
                    <td class="text-center font-bold" id="pv_dias_util">0</td>
                    <td colspan="2" class="text-center font-bold" id="pv_alic_util">0,0000</td>
                    <td class="font-bold text-right bg-gray-100">Salario Integral Mensual</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_sim">0,00</td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold">Dias de vacaciones</td>
                    <td class="text-center font-bold bg-gray-100">Dias</td>
                    <td colspan="2" class="text-center font-bold bg-gray-100">Alicuota</td>
                    <td colspan="2" class="border-0"></td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold">( Alicuota )</td>
                    <td class="text-center font-bold" id="pv_dias_vac">0</td>
                    <td colspan="2" class="text-center font-bold" id="pv_alic_vac">0,0000</td>
                    <td colspan="2" class="border-0"></td>
                </tr>
                
                <!-- UTILIDADES O BONO DE FIN DE AÑO FRACCIONADAS -->
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">UTILIDADES O BONO DE FIN DE AÑO FRACCIONADAS</th></tr>
                <tr>
                    <td colspan="3"></td>
                    <td class="font-bold text-center bg-gray-100">Alicuota</td>
                    <td class="font-bold text-center bg-gray-100">Dias</td>
                    <td class="font-bold text-center bg-gray-100" style="font-size: 9.5px;">Salario Normal Diario + Alicota de Vacaciones</td>
                    <td class="font-bold text-center bg-gray-100">Total</td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold">Articulo 131, 132 y 136 LOTTT</td>
                    <td class="text-center font-bold" id="pv_util_alic">0,0000</td>
                    <td class="text-center font-bold" id="pv_util_dias">0,00</td>
                    <td class="text-right font-bold" id="pv_util_snv">0,00</td>
                    <td class="text-right font-bold" id="pv_util_total">0,00</td>
                </tr>
                
                <!-- FRACCION DE BONO VACACIONAL -->
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">FRACCION DE BONO VACACIONAL</th></tr>
                <tr>
                    <td colspan="3"></td>
                    <td class="font-bold text-center bg-gray-100">Alicuota</td>
                    <td class="font-bold text-center bg-gray-100">Dias</td>
                    <td class="font-bold text-center bg-gray-100" style="font-size: 9.5px;">Salario Normal Diario + Alicota de Aguinaldos</td>
                    <td class="font-bold text-center bg-gray-100">Total</td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold">Articulo 190 y 192</td>
                    <td class="text-center font-bold" id="pv_vac190_alic">0,0000</td>
                    <td class="text-center font-bold" id="pv_vac190_dias">0,00</td>
                    <td class="text-right font-bold" id="pv_vac190_sal">0,00</td>
                    <td class="text-right font-bold" id="pv_vac190_total">0,00</td>
                </tr>

                <!-- FRACCION DE DISFRUTE DE VACACIONES -->
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">FRACCION DE DISFRUTE DE VACACIONES</th></tr>
                <tr>
                    <td colspan="2" class="font-bold">Articulo 196</td>
                    <td class="text-center font-bold" id="pv_vac196_dias">0,00</td>
                    <td class="text-center font-bold" id="pv_vac196_alic">0,0000</td>
                    <td class="text-center font-bold" id="pv_vac196_dias_2">0,00</td>
                    <td class="text-right font-bold" id="pv_vac196_sal">0,00</td>
                    <td class="text-right font-bold" id="pv_vac196_total">0,00</td>
                </tr>
                
                <!-- ANTIGÜEDAD -->
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">ANTIGÜEDAD</th></tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="font-bold text-center bg-gray-100">Nro. Dias</td>
                    <td class="font-bold text-center bg-gray-100">Nro. MESES</td>
                    <td class="font-bold text-center bg-gray-100">Años</td>
                    <td class="font-bold text-center bg-gray-100">Salario Integral Diario</td>
                    <td class="font-bold text-center bg-gray-100">Monto Total</td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold">Articulo 142 literal C LOTTT</td>
                    <td class="text-center font-bold" id="pv_antig_dias">0,00</td>
                    <td class="text-center font-bold" id="pv_antig_meses">0</td>
                    <td class="text-center font-bold" id="pv_antig_anos">0,00</td>
                    <td class="text-right font-bold" id="pv_antig_sdi">0,00</td>
                    <td class="text-right font-bold" id="pv_antig_total">0,00</td>
                </tr>
                <tr>
                    <td colspan="6" class="font-bold text-right bg-gray-100">SUB - TOTAL  ASIGNACIONES :</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_subtotal_asig">0,00</td>
                </tr>
                
                <!-- OTRAS ASIGNACIONES Y DEDUCCIONES -->
                <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">OTRAS ASIGNACIONES Y DEDUCCIONES</th></tr>
                <tr>
                    <td></td><td></td><td></td><td></td>
                    <td class="font-bold text-center bg-gray-100">Dias</td>
                    <td class="font-bold text-center bg-gray-100">Salario Normal Diario</td>
                    <td class="font-bold text-center bg-gray-100">Total</td>
                </tr>
                <tr>
                    <td></td><td></td><td></td><td></td>
                    <td class="text-center font-bold" id="pv_otras_asig_dias">-</td>
                    <td class="text-right font-bold" id="pv_otras_asig_sdn">0,00</td>
                    <td class="text-right font-bold" id="pv_otras_asig_tot">-</td>
                </tr>
                <tr><td colspan="7" style="height: 14px;"></td></tr>
                <tr>
                    <td colspan="4" class="font-bold text-center">INTERESES POR ANTIGÜEDAD</td>
                    <td></td><td></td>
                    <td class="text-right font-bold" id="pv_intereses">-</td>
                </tr>
                <tr><td colspan="7" style="height: 14px;"></td></tr>
                
                <!-- DEDUCCIONES Y TOTALES (CON ÁREA EN BLANCO A LA IZQUIERDA) -->
                <tr>
                    <td colspan="3" rowspan="<?php echo $aplicar_deducciones ? '11' : '3'; ?>" id="pv_left_spacer" class="align-top" style="border-top: none; border-left: none; border-bottom: none; border-right: 1px solid #000; background: #fff;"></td>
                    <td colspan="3" class="font-bold text-right bg-gray-100">TOTAL  ASIGNACIONES :</td>
                    <td class="text-right font-bold bg-gray-100" id="pv_total_asig">0,00</td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="font-bold text-center bg-gray-100 tracking-widest">M E N O S  :</td>
                    <td class="bg-gray-100"></td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="text-right font-bold">DEPÓSITO BANCARIO FIDEICOMISO</td>
                    <td class="text-right font-bold text-red-600" id="pv_fideicomiso">0,00</td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="text-right font-bold">F.A.O.V</td>
                    <td class="text-right font-bold text-red-600" id="pv_faov">0,00</td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="text-right font-bold">I.V.S.S</td>
                    <td class="text-right font-bold text-red-600" id="pv_ivss">0,00</td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="text-right font-bold">INCES</td>
                    <td class="text-right font-bold text-red-600" id="pv_inces">0,00</td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="font-bold text-right bg-gray-100">TOTAL DEDUCCIONES</td>
                    <td class="text-right font-bold text-red-600 bg-gray-100" id="pv_total_deduc">0,00</td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" style="height: 14px;"></td>
                    <td></td>
                </tr>
                <tr class="pv-deduccion-row <?php echo $aplicar_deducciones ? '' : 'hidden'; ?>">
                    <td colspan="3" class="font-bold text-right" style="letter-spacing: 1px;">S U B - T O T A L  A  P A G A R</td>
                    <td class="text-right font-bold" id="pv_subtotal_pagar">0,00</td>
                </tr>
                <tr>
                    <td colspan="3" style="height: 14px;"></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="3" class="font-bold text-right" style="letter-spacing: 1px;">T O T A L  A  P A G A R</td>
                    <td class="text-right font-bold bg-gray-200" id="pv_total_pagar">0,00</td>
                </tr>
                
                <!-- RECIBI CONFORME Y FIRMAS -->
                <tr><td colspan="7" class="border-0" style="height: 4px;"></td></tr>
                <tr>
                    <th colspan="7" class="bg-gray-200 text-center font-bold tracking-wider" style="font-size: 11px; padding: 4px;">RECIBI CONFORME:</th>
                </tr>
                <tr>
                    <td colspan="7" style="height: 48px;" class="bg-white"></td>
                </tr>
                <tr>
                    <th colspan="7" class="bg-gray-200 text-center font-bold tracking-wider" style="font-size: 11px; padding: 4px;">FIRMAS:</th>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold text-left" style="padding-left: 10px;">Elaborado Por:</td>
                    <td colspan="3" class="font-bold text-center">Revisado por:</td>
                    <td colspan="2" class="font-bold text-center">Autorizado Por:</td>
                </tr>
                <tr>
                    <td colspan="2" style="height: 52px;" class="bg-white"></td>
                    <td colspan="3" style="height: 52px;" class="bg-white"></td>
                    <td colspan="2" style="height: 52px;" class="bg-white"></td>
                </tr>
                <tr>
                    <td colspan="2" style="height: 14px;"></td>
                    <td colspan="3" style="height: 14px;"></td>
                    <td colspan="2" style="height: 14px;"></td>
                </tr>
                <tr>
                    <td colspan="2" class="font-bold text-center text-[10px]" style="padding: 5px 2px;">ANALISTA</td>
                    <td colspan="3" class="font-bold text-center text-[10px]" style="padding: 5px 2px;">JEFE ( E ) DPTO. DE RELACIONES</td>
                    <td colspan="2" class="font-bold text-center text-[10px]" style="padding: 5px 2px;">DIRECTOR GENERAL DE RECURSOS HUMANOS</td>
                </tr>
            </table>
        </div>
    </div>

</form>

<!-- MODAL INTERACTIVO: AJUSTE Y SIMULACIÓN DE ALÍCUOTAS -->
<div id="modalAlicuotasBackdrop" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto hidden no-print" onclick="if(event.target===this) cerrarModalAlicuotas();">
    <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
        
        <!-- Header del Modal -->
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-blue-50/40 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-blue-100/80 text-brand-blue flex items-center justify-center text-base shadow-xs">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <div>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-800">
                        Configuración y Simulación de Alícuotas
                    </h3>
                    <p class="text-[11px] text-slate-500 font-medium">
                        Fórmulas oficiales de la Gobernación del Estado Trujillo y LOTTT
                    </p>
                </div>
            </div>
            <button type="button" onclick="cerrarModalAlicuotas()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors cursor-pointer" title="Cerrar modal">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Contenido scrolleable del Modal -->
        <div class="p-5 overflow-y-auto space-y-4 custom-scrollbar">
            
            <!-- Bloque 1: Fórmulas Oficiales Desglosadas -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                        <i class="fa-solid fa-square-root-variable text-brand-blue text-xs"></i>
                        Fórmulas Matemáticas Oficiales (Excel Gobernación)
                    </span>
                    <span class="text-[10px] bg-blue-100/70 text-brand-blue px-2 py-0.5 rounded-full font-bold">
                        Año Base: 360 días
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                    <!-- Fórmula Vacaciones -->
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">1. Alícuota Diaria Vacacional (D29):</p>
                        <div class="font-mono text-slate-800 font-bold mt-1 text-[11px] bg-slate-50 p-2 rounded-lg border border-slate-100">
                            (Sueldo Normal Diario × Días Vac.) ÷ 360
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1.5">Alícuota diaria derivada del bono vacacional anual.</p>
                    </div>

                    <!-- Fórmula Utilidades -->
                    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-tight">2. Alícuota Diaria Utilidades (D27):</p>
                        <div class="font-mono text-slate-800 font-bold mt-1 text-[11px] bg-slate-50 p-2 rounded-lg border border-slate-100">
                            [(Sueldo Normal Diario + <span class="text-brand-blue font-black">Alíc. Vac.</span>) × Días Util.] ÷ 360
                        </div>
                        <p class="text-[10px] text-amber-700 font-medium mt-1.5">Criterio Gobernación: La alíc. vacacional integra la base.</p>
                    </div>
                </div>

                <!-- Fórmula Salario Integral -->
                <div class="bg-white p-3 rounded-xl border border-slate-200 text-xs shadow-2xs grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase block">3. Salario Diario Integral (G24):</span>
                        <code class="text-[11px] font-bold text-slate-800 font-mono block mt-0.5">Sueldo Normal Diario + Alíc. Vac. + Alíc. Util.</code>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase block">4. Salario Integral Mensual (G27):</span>
                        <code class="text-[11px] font-bold text-brand-blue font-mono block mt-0.5">Salario Diario Integral × 30 días</code>
                    </div>
                </div>
            </div>

            <!-- Bloque 2: Parámetros Modificables y Presets -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 space-y-3 shadow-2xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Modificar Días de Alícuota:
                    </label>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-slate-400 font-medium">Presets:</span>
                        <button type="button" onclick="setModalPreset(30, window.diasVacLegalesActuales || <?php echo $dias_vac_legales_sug; ?>)" class="text-[10px] bg-blue-50 hover:bg-blue-100 text-brand-blue font-bold px-2.5 py-1 rounded-lg border border-blue-200 transition-colors cursor-pointer" title="LOTTT Art. 131 y 192 (Días legales según tiempo de servicio)">
                            <span id="modal_btn_lottt_text">LOTTT (30u / <span id="modal_lbl_btn_dv"><?php echo $dias_vac_legales_sug; ?></span>v)</span>
                        </button>
                        <button type="button" onclick="setModalPreset(120, 180)" class="text-[10px] bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold px-2.5 py-1 rounded-lg border border-slate-200 transition-colors cursor-pointer" title="Regla Histórica Legada Gobernación">
                            Gobernación (120u / 180v)
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-bold text-slate-700">
                                Días para Alícuota de Utilidades:
                            </label>
                            <span class="text-[10px] text-slate-400 font-medium">Base Anual</span>
                        </div>
                        <input type="number" id="modal_dias_utilidades" min="0" max="360" step="1" oninput="simularAlicuotasEnModal()" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-center font-black text-sm text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none transition-all">
                        <div class="flex items-center justify-between mt-1.5 text-[10px] text-slate-400">
                            <span>Presets:</span>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="document.getElementById('modal_dias_utilidades').value=30; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="LOTTT Mínimo Legal">30d</button>
                                <button type="button" onclick="document.getElementById('modal_dias_utilidades').value=60; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="2 meses">60d</button>
                                <button type="button" onclick="document.getElementById('modal_dias_utilidades').value=90; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="3 meses">90d</button>
                                <button type="button" onclick="document.getElementById('modal_dias_utilidades').value=120; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="Gobernación / 4 meses">120d</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-bold text-slate-700">
                                Días para Alícuota Vacacional:
                            </label>
                            <span class="text-[10px] text-slate-400 font-medium">Bono Vacacional</span>
                        </div>
                        <input type="number" id="modal_dias_vacaciones" min="0" max="360" step="1" oninput="simularAlicuotasEnModal()" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-center font-black text-sm text-slate-800 focus:bg-white focus:ring-2 focus:ring-brand-blue outline-none transition-all">
                        <div class="flex items-center justify-between mt-1.5 text-[10px] text-slate-400">
                            <span>Presets:</span>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick="document.getElementById('modal_dias_vacaciones').value = (window.diasVacLegalesActuales || <?php echo $dias_vac_legales_sug; ?>); simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-blue-50 hover:bg-blue-100 text-brand-blue font-bold rounded border border-blue-200 cursor-pointer" title="Días legales según antigüedad calculada">Sugerido (<span id="modal_sug_dv_btn"><?php echo $dias_vac_legales_sug; ?></span>d)</button>
                                <button type="button" onclick="document.getElementById('modal_dias_vacaciones').value=15; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="15 días (Base LOTTT)">15d</button>
                                <button type="button" onclick="document.getElementById('modal_dias_vacaciones').value=30; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="30 días (Tope LOTTT)">30d</button>
                                <button type="button" onclick="document.getElementById('modal_dias_vacaciones').value=180; simularAlicuotasEnModal();" class="px-1.5 py-0.5 bg-slate-100 hover:bg-blue-50 hover:text-brand-blue font-bold rounded border border-slate-200 cursor-pointer" title="180 días (Gobernación)">180d</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloque 3: Simulador en Tiempo Real y Comparativa -->
            <div class="bg-gradient-to-br from-blue-50/60 to-indigo-50/40 p-4 rounded-xl border border-blue-200/80 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-brand-blue flex items-center gap-1.5">
                        <i class="fa-solid fa-chart-line text-blue-600"></i> Simulación en Tiempo Real con Sueldo Actual
                    </span>
                    <span id="modal_diff_badge" class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700">
                        Calculando...
                    </span>
                </div>

                <!-- Grid de Métricas de la Simulación -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                    <div class="bg-white p-2.5 rounded-xl border border-blue-100 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Sueldo Normal Diario</p>
                        <p class="text-xs sm:text-sm font-black text-slate-800 mt-0.5">Bs. <span id="modal_sim_sdn">0,00</span></p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-blue-100 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Alícuota Vacacional</p>
                        <p class="text-xs sm:text-sm font-black text-brand-blue mt-0.5">Bs. <span id="modal_sim_alic_v">0,0000</span></p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-blue-100 shadow-2xs">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Alícuota Utilidades</p>
                        <p class="text-xs sm:text-sm font-black text-indigo-700 mt-0.5">Bs. <span id="modal_sim_alic_u">0,0000</span></p>
                    </div>
                    <div class="bg-white p-2.5 rounded-xl border border-blue-200 shadow-2xs">
                        <p class="text-[10px] font-bold text-emerald-700 uppercase">Salario Integral Diario</p>
                        <p class="text-xs sm:text-sm font-black text-emerald-900 mt-0.5">Bs. <span id="modal_sim_sdi">0,00</span></p>
                    </div>
                </div>

                <!-- Resumen Mensual Integral -->
                <div class="p-2.5 bg-white/90 rounded-xl border border-blue-200/70 flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-coins text-amber-500"></i> Salario Integral Mensual Resultante:
                    </span>
                    <span class="text-sm font-black text-brand-blue font-mono">
                        Bs. <span id="modal_sim_sim">0,00</span>
                    </span>
                </div>

                <!-- Ejemplo Práctico Redactado en Vivo -->
                <div class="bg-white/80 p-3 rounded-xl border border-slate-200 text-slate-700 text-xs space-y-1">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide flex items-center gap-1">
                        <i class="fa-solid fa-circle-info text-blue-500"></i> Ejemplo práctico con las cifras actuales:
                    </p>
                    <p id="modal_ejemplo_texto" class="text-slate-600 leading-relaxed font-medium">
                        <!-- Redactado dinámicamente -->
                    </p>
                </div>
            </div>

        </div>

        <!-- Footer de Acciones del Modal -->
        <div class="px-5 py-3.5 border-t border-slate-100 bg-slate-50 flex items-center justify-between shrink-0">
            <button type="button" onclick="cerrarModalAlicuotas()" class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl border border-slate-300 transition-all cursor-pointer">
                Cancelar
            </button>
            <button type="button" onclick="aplicarModalAlicuotas()" class="px-5 py-2 bg-brand-blue hover:bg-blue-900 text-white font-bold text-xs rounded-xl shadow transition-all flex items-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-check text-brand-yellow"></i>
                <span>Aplicar al Cálculo</span>
            </button>
        </div>

    </div>
</div>

<script class="no-print">
// ==========================================
// GESTIÓN DEL SELECT INTERACTIVO DE MOTIVOS
// ==========================================
let motivosData = <?php echo json_encode($motivos_list, JSON_UNESCAPED_UNICODE); ?>;
let selectedMotivo = <?php echo json_encode($current_motivo, JSON_UNESCAPED_UNICODE); ?>;
let editingMotivoId = null;

function renderMotivosList(itemsToRender = null) {
    const listEl = document.getElementById('motivos_items_list');
    const noResultsEl = document.getElementById('motivo_no_results');
    if (!listEl) return;

    const items = itemsToRender !== null ? itemsToRender : motivosData;

    if (items.length === 0) {
        listEl.innerHTML = '';
        noResultsEl.classList.remove('hidden');
        return;
    }

    noResultsEl.classList.add('hidden');
    listEl.innerHTML = items.map(item => {
        const isSelected = selectedMotivo.trim().toLowerCase() === item.nombre.trim().toLowerCase();
        const isEditing = editingMotivoId === item.id;

        if (isEditing) {
            return `
                <div class="flex items-center gap-1.5 p-1.5 bg-blue-50/90 rounded-xl border border-blue-200" onclick="event.stopPropagation()">
                    <input type="text" id="edit_input_${item.id}" value="${escapeHtml(item.nombre)}" 
                        onkeydown="if(event.key==='Enter'){event.preventDefault();saveEditMotivo(${item.id});}else if(event.key==='Escape'){cancelEditMotivo();}"
                        class="flex-1 px-2 py-1 bg-white border border-blue-300 rounded-lg text-xs font-bold text-slate-900 outline-none focus:ring-1 focus:ring-brand-blue">
                    <button type="button" onclick="saveEditMotivo(${item.id})" class="w-6 h-6 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center text-xs shadow-xs cursor-pointer" title="Guardar cambios">
                        <i class="fa-solid fa-check text-[10px]"></i>
                    </button>
                    <button type="button" onclick="cancelEditMotivo()" class="w-6 h-6 rounded-md bg-slate-200 hover:bg-slate-300 text-slate-700 flex items-center justify-center text-xs cursor-pointer" title="Cancelar">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                    </button>
                </div>
            `;
        }

        return `
            <div class="group flex items-center justify-between p-1 rounded-xl hover:bg-slate-100 transition-colors ${isSelected ? 'bg-blue-50/80 border border-blue-200/90 font-black text-brand-blue' : 'text-slate-800 font-bold'}">
                <button type="button" onclick="selectMotivo('${escapeHtml(item.nombre)}')" class="flex-1 text-left px-2 py-1.5 text-xs truncate flex items-center gap-2 cursor-pointer">
                    ${isSelected ? '<i class="fa-solid fa-circle-check text-emerald-600 text-xs shrink-0"></i>' : '<i class="fa-regular fa-circle text-slate-300 text-xs shrink-0 group-hover:text-slate-400"></i>'}
                    <span class="truncate">${escapeHtml(item.nombre)}</span>
                </button>
                <div class="flex items-center gap-1 opacity-70 group-hover:opacity-100 shrink-0 pr-1">
                    <button type="button" onclick="startEditMotivo(${item.id}, event)" class="w-6 h-6 rounded-md hover:bg-white text-slate-400 hover:text-brand-blue flex items-center justify-center text-[10px] transition-colors cursor-pointer" title="Editar nombre de este motivo">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" onclick="deleteMotivo(${item.id}, '${escapeHtml(item.nombre)}', event)" class="w-6 h-6 rounded-md hover:bg-rose-50 text-slate-400 hover:text-rose-600 flex items-center justify-center text-[10px] transition-colors cursor-pointer" title="Eliminar de la lista">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function toggleMotivoDropdown() {
    const dropdown = document.getElementById('motivo_dropdown');
    const chevron = document.getElementById('motivo_chevron');
    const card1 = document.getElementById('card_empleado_regla');
    if (!dropdown) return;

    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
        if (card1) card1.style.zIndex = '50';
        editingMotivoId = null;
        renderMotivosList();
        const searchInput = document.getElementById('motivo_search_input');
        if (searchInput) {
            searchInput.value = '';
            document.getElementById('motivo_clear_search_btn').classList.add('hidden');
            setTimeout(() => searchInput.focus(), 50);
        }
    } else {
        closeMotivoDropdown();
    }
}

function closeMotivoDropdown() {
    const dropdown = document.getElementById('motivo_dropdown');
    const chevron = document.getElementById('motivo_chevron');
    const card1 = document.getElementById('card_empleado_regla');
    if (!dropdown) return;
    dropdown.classList.add('hidden');
    chevron.style.transform = 'rotate(0deg)';
    if (card1) card1.style.zIndex = '30';
    editingMotivoId = null;
}

function selectMotivo(nombre) {
    selectedMotivo = nombre;
    const input = document.getElementById('motivo_input');
    const display = document.getElementById('motivo_display_text');
    if (input) input.value = nombre;
    if (display) display.innerText = nombre;
    closeMotivoDropdown();
}

function filterMotivos(query) {
    const clearBtn = document.getElementById('motivo_clear_search_btn');
    if (clearBtn) {
        if (query.trim()) clearBtn.classList.remove('hidden');
        else clearBtn.classList.add('hidden');
    }

    const q = query.toLowerCase().trim();
    if (!q) {
        renderMotivosList();
        return;
    }

    const filtered = motivosData.filter(item => item.nombre.toLowerCase().includes(q));
    renderMotivosList(filtered);
}

function clearMotivoSearch() {
    const searchInput = document.getElementById('motivo_search_input');
    if (searchInput) {
        searchInput.value = '';
        document.getElementById('motivo_clear_search_btn').classList.add('hidden');
        searchInput.focus();
    }
    renderMotivosList();
}

function addNewMotivo() {
    const input = document.getElementById('new_motivo_name');
    const nombre = input.value.trim();
    if (!nombre) {
        alert('Por favor escriba el nombre del motivo a agregar.');
        input.focus();
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('nombre', nombre);

    fetch('ajax_motivos.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data) {
            const exists = motivosData.find(m => m.id === res.data.id || m.nombre.toLowerCase() === res.data.nombre.toLowerCase());
            if (!exists) {
                motivosData.push(res.data);
                motivosData.sort((a, b) => a.nombre.localeCompare(b.nombre));
            }
            input.value = '';
            selectMotivo(res.data.nombre);
        } else {
            alert(res.error || 'No se pudo agregar el motivo.');
        }
    })
    .catch(err => {
        alert('Error de conexión al agregar el motivo.');
    });
}

function startEditMotivo(id, event) {
    if (event) event.stopPropagation();
    editingMotivoId = id;
    renderMotivosList();
    setTimeout(() => {
        const editInp = document.getElementById('edit_input_' + id);
        if (editInp) {
            editInp.focus();
            editInp.select();
        }
    }, 50);
}

function cancelEditMotivo() {
    editingMotivoId = null;
    renderMotivosList();
}

function saveEditMotivo(id) {
    const editInp = document.getElementById('edit_input_' + id);
    if (!editInp) return;
    const nuevoNombre = editInp.value.trim();
    if (!nuevoNombre) {
        alert('El nombre no puede quedar vacío.');
        editInp.focus();
        return;
    }

    const formData = new FormData();
    formData.append('action', 'edit');
    formData.append('id', id);
    formData.append('nombre', nuevoNombre);

    fetch('ajax_motivos.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data) {
            const item = motivosData.find(m => m.id === id);
            if (item) {
                if (selectedMotivo.toLowerCase() === item.nombre.toLowerCase()) {
                    selectMotivo(res.data.nombre);
                }
                item.nombre = res.data.nombre;
                motivosData.sort((a, b) => a.nombre.localeCompare(b.nombre));
            }
            editingMotivoId = null;
            renderMotivosList();
        } else {
            alert(res.error || 'Error al actualizar motivo.');
        }
    })
    .catch(err => {
        alert('Error de conexión al actualizar motivo.');
    });
}

function deleteMotivo(id, nombre, event) {
    if (event) event.stopPropagation();
    if (!confirm('¿Está seguro de eliminar el motivo "' + nombre + '" de la lista?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    fetch('ajax_motivos.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            motivosData = motivosData.filter(m => m.id !== id);
            if (selectedMotivo.toLowerCase() === nombre.toLowerCase()) {
                if (motivosData.length > 0) {
                    selectMotivo(motivosData[0].nombre);
                } else {
                    selectMotivo('');
                }
            }
            renderMotivosList();
        } else {
            alert(res.error || 'Error al eliminar motivo.');
        }
    })
    .catch(err => {
        alert('Error de conexión al eliminar motivo.');
    });
}

// Clic fuera del combobox para cerrarlo
document.addEventListener('click', function(e) {
    const container = document.getElementById('motivo_combobox_container');
    const dropdown = document.getElementById('motivo_dropdown');
    if (container && !container.contains(e.target) && dropdown && !dropdown.classList.contains('hidden')) {
        closeMotivoDropdown();
    }
});

// Cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const dropdown = document.getElementById('motivo_dropdown');
        if (dropdown && !dropdown.classList.contains('hidden')) {
            closeMotivoDropdown();
        }
    }
});

// Renderizar al cargar DOM
document.addEventListener('DOMContentLoaded', function() {
    renderMotivosList();
});

function onReglaChange(regla) {
    if (regla === 'LEGADO_120_180') {
        document.getElementById('dias_utilidades').value = 120;
        document.getElementById('dias_vacaciones_alicuota').value = 180;
    } else {
        document.getElementById('dias_utilidades').value = 30;
        document.getElementById('dias_vacaciones_alicuota').value = 15;
    }
    if(typeof calculateAll === 'function') calculateAll();
}

function formatVE(num) {
    let val = parseFloat(num) || 0;
    return val.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatVE4(num) {
    let val = parseFloat(num) || 0;
    return val.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function syncPreviewTab() {
    const getV = (id) => (typeof window.getVal === 'function' ? window.getVal(id) : (parseFloat(document.getElementById(id)?.value) || 0));
    const setT = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };

    // Fechas y motivo
    const fCalculo = document.getElementById('fecha_calculo')?.value || '';
    if (fCalculo) {
        const p = fCalculo.split('-');
        if (p.length === 3) setT('pv_fecha_calculo', `${p[2]}/${p[1]}/${p[0]}`);
    }
    const fEgreso = document.getElementById('fecha_egreso')?.value || '';
    if (fEgreso) {
        const parts = fEgreso.split('-');
        if (parts.length === 3) setT('pv_fecha_egreso', `${parts[2]}/${parts[1]}/${parts[0]}`);
    }
    setT('pv_motivo', document.getElementById('motivo_input')?.value || 'RENUNCIA');

    // Tiempo de servicio
    setT('pv_t_anos', document.getElementById('t_anos')?.innerText || '0');
    setT('pv_t_meses', document.getElementById('t_meses')?.innerText || '0');
    setT('pv_t_dias', document.getElementById('t_dias')?.innerText || '0');
    setT('pv_t_meses_total', document.getElementById('t_meses_total')?.innerText || '0');

    // Sueldos y Primas
    setT('pv_sbm', formatVE(getV('sueldo_base_mensual')));
    setT('pv_sbd', formatVE(getV('salario_base_diario')));
    setT('pv_prima_antig', formatVE(getV('prima_antiguedad')));
    setT('pv_prima_hijos', formatVE(getV('prima_hijos')));
    setT('pv_prima_transp', formatVE(getV('prima_transporte')));
    setT('pv_bono_noct', formatVE(getV('bono_nocturno')));
    setT('pv_prima_prof', formatVE(getV('prima_profesionalizacion')));
    setT('pv_smn', formatVE(getV('sueldo_mensual_normal')));
    setT('pv_smn_2', formatVE(getV('sueldo_mensual_normal')));
    setT('pv_sdn', formatVE(getV('sueldo_diario_normal')));
    setT('pv_sdi', formatVE(getV('salario_diario_integral')));
    setT('pv_sim', formatVE(getV('salario_integral_mensual')));

    // Parámetros y Alícuotas
    setT('pv_dias_util', getV('dias_utilidades'));
    setT('pv_alic_util', formatVE4(getV('alicuota_utilidades')));
    setT('pv_dias_vac', getV('dias_vacaciones_alicuota'));
    setT('pv_alic_vac', formatVE4(getV('alicuota_vacaciones')));

    // Utilidades (Art. 131, 132 y 136)
    setT('pv_util_alic', formatVE4(getV('util_alicuota')));
    setT('pv_util_dias', formatVE(getV('util_dias')));
    setT('pv_util_snv', formatVE(getV('util_salario_normal_vac')));
    setT('pv_util_total', formatVE(getV('util_total')));

    // Bono Vacacional (Art. 190 y 192)
    setT('pv_vac190_alic', formatVE4(getV('vac190_alicuota')));
    setT('pv_vac190_dias', formatVE(getV('vac190_dias')));
    setT('pv_vac190_sal', formatVE(getV('vac190_salario')));
    setT('pv_vac190_total', formatVE(getV('vac190_total')));

    // Disfrute de Vacaciones (Art. 196)
    setT('pv_vac196_dias', formatVE(getV('vac196_dias')));
    setT('pv_vac196_alic', formatVE4(getV('vac196_alicuota')));
    setT('pv_vac196_dias_2', formatVE(getV('vac196_dias')));
    setT('pv_vac196_sal', formatVE(getV('vac196_salario')));
    setT('pv_vac196_total', formatVE(getV('vac196_total')));

    // Antigüedad (Art. 142 literal C)
    const antigDias = getV('antig_nro_dias') > 0 ? getV('antig_nro_dias') : (getV('antig_anos') * 30);
    setT('pv_antig_dias', formatVE(antigDias));
    setT('pv_antig_meses', document.getElementById('t_meses_total')?.innerText || '0');
    setT('pv_antig_anos', formatVE(getV('antig_anos')));
    setT('pv_antig_sdi', formatVE(getV('salario_diario_integral')));
    setT('pv_antig_total', formatVE(getV('antig_monto_total')));

    // Sub-Total Asignaciones
    const subTot = getV('util_total') + getV('vac190_total') + getV('vac196_total') + getV('antig_monto_total');
    setT('pv_subtotal_asig', formatVE(subTot));

    // Otras Asignaciones
    const oAsigDias = getV('o_asig_d');
    const oAsigTot = getV('otras_asignaciones');
    setT('pv_otras_asig_dias', oAsigDias > 0 ? formatVE(oAsigDias) : '-');
    setT('pv_otras_asig_sdn', formatVE(getV('sueldo_diario_normal')));
    setT('pv_otras_asig_tot', oAsigTot > 0 ? formatVE(oAsigTot) : '-');

    // Intereses
    const inter = getV('intereses_antiguedad');
    setT('pv_intereses', inter > 0 ? formatVE(inter) : '-');

    // Total Asignaciones
    setT('pv_total_asig', formatVE(getV('total_asignaciones')));

    // Deducciones y visibilidad condicional en la planilla
    const aplicarDeduc = document.getElementById('aplicar_deducciones')?.checked;
    const deducRows = document.querySelectorAll('.pv-deduccion-row');
    const leftSpacer = document.getElementById('pv_left_spacer');
    
    if (aplicarDeduc) {
        deducRows.forEach(r => r.classList.remove('hidden'));
        if (leftSpacer) leftSpacer.setAttribute('rowspan', '11');
        setT('pv_fideicomiso', formatVE(getV('deposito_fideicomiso')));
        setT('pv_faov', formatVE(getV('deduccion_lph') || getV('deduccion_faov')));
        setT('pv_ivss', formatVE(getV('deduccion_sso') || getV('deduccion_ivss')));
        setT('pv_inces', formatVE(getV('deduccion_ince') || getV('deduccion_inces')));
        setT('pv_total_deduc', formatVE(getV('total_deducciones')));
    } else {
        deducRows.forEach(r => r.classList.add('hidden'));
        if (leftSpacer) leftSpacer.setAttribute('rowspan', '3');
        setT('pv_total_deduc', '0,00');
    }

    // Totales a Pagar
    setT('pv_subtotal_pagar', formatVE(getV('neto_a_cobrar')));
    setT('pv_total_pagar', formatVE(getV('neto_a_cobrar')));
}

function toggleDeducciones(checked) {
    const contenedor = document.getElementById('contenedor_deducciones');
    const aviso = document.getElementById('aviso_deducciones_omitidas');
    const lbl = document.getElementById('lbl_deducciones_status');
    const badge = document.getElementById('sidebar_deduc_badge');
    
    if (checked) {
        if (contenedor) contenedor.classList.remove('hidden');
        if (aviso) aviso.classList.add('hidden');
        if (lbl) {
            lbl.innerText = 'SÍ (Activas)';
            lbl.className = 'ml-2 text-xs font-bold text-emerald-700';
        }
        if (badge) badge.classList.add('hidden');
    } else {
        if (contenedor) contenedor.classList.add('hidden');
        if (aviso) aviso.classList.remove('hidden');
        if (lbl) {
            lbl.innerText = 'NO (Omitidas)';
            lbl.className = 'ml-2 text-xs font-bold text-slate-400';
        }
        if (badge) badge.classList.remove('hidden');
    }
    if (typeof calculateAll === 'function') {
        calculateAll();
    }
}

function switchTab(tab) {
    const tabForm = document.getElementById('tab-form');
    const tabPreview = document.getElementById('tab-preview');
    const btnForm = document.getElementById('btn-tab-form');
    const btnPreview = document.getElementById('btn-tab-preview');

    if (tab === 'form') {
        tabForm.classList.remove('hidden');
        tabPreview.classList.add('hidden');
        btnForm.className = "px-5 py-2 text-xs font-extrabold rounded-xl transition-all bg-white text-brand-blue shadow";
        btnPreview.className = "px-5 py-2 text-xs font-extrabold rounded-xl transition-all text-slate-600 hover:text-slate-900";
    } else {
        tabForm.classList.add('hidden');
        tabPreview.classList.remove('hidden');
        btnPreview.className = "px-5 py-2 text-xs font-extrabold rounded-xl transition-all bg-white text-brand-blue shadow";
        btnForm.className = "px-5 py-2 text-xs font-extrabold rounded-xl transition-all text-slate-600 hover:text-slate-900";
        
        syncPreviewTab();
    }
}

// ==========================================
// GESTIÓN DEL MODAL INTERACTIVO DE ALÍCUOTAS
// ==========================================
function abrirModalAlicuotas() {
    const du = document.getElementById('dias_utilidades')?.value || 30;
    const dv = document.getElementById('dias_vacaciones_alicuota')?.value || 15;
    const inputDu = document.getElementById('modal_dias_utilidades');
    const inputDv = document.getElementById('modal_dias_vacaciones');
    if (inputDu) inputDu.value = du;
    if (inputDv) inputDv.value = dv;

    const modal = document.getElementById('modalAlicuotasBackdrop');
    if (modal) modal.classList.remove('hidden');

    simularAlicuotasEnModal();
}

function cerrarModalAlicuotas() {
    const modal = document.getElementById('modalAlicuotasBackdrop');
    if (modal) modal.classList.add('hidden');
}

function setModalPreset(du, dv) {
    const inputDu = document.getElementById('modal_dias_utilidades');
    const inputDv = document.getElementById('modal_dias_vacaciones');
    if (inputDu) inputDu.value = du;
    if (inputDv) inputDv.value = dv;
    simularAlicuotasEnModal();
}

function simularAlicuotasEnModal() {
    const du = parseFloat(document.getElementById('modal_dias_utilidades')?.value) || 0;
    const dv = parseFloat(document.getElementById('modal_dias_vacaciones')?.value) || 0;

    // Sueldo normal mensual actual del formulario
    const getV = (id) => (typeof window.getVal === 'function' ? window.getVal(id) : (parseFloat(document.getElementById(id)?.value) || 0));
    let smn = getV('sueldo_mensual_normal');
    if (!smn || smn <= 0) {
        smn = getV('sueldo_base_mensual') + getV('prima_antiguedad') + getV('prima_hijos') + getV('prima_transporte') + getV('bono_nocturno') + getV('prima_profesionalizacion');
    }
    if (!smn || smn <= 0) smn = 130.00;

    const sdn_exact = smn / 30;

    // Fórmulas oficiales Excel Gobernación (D29 y D27)
    // 1. Alícuota Vacacional: (Sueldo Normal Diario * Días Vac) / 360
    const alic_v = Math.round(((sdn_exact * dv) / 360) * 100) / 100;
    // 2. Alícuota Utilidades: ((Sueldo Normal Diario + Alícuota Vacacional) * Días Util) / 360
    const alic_u = Math.round((((sdn_exact + alic_v) * du) / 360) * 100) / 100;
    const total_alic = alic_v + alic_u;

    const sim = smn + (alic_u * 30) + (alic_v * 30);
    const sdi = sim / 30;

    // Valores actuales en el formulario para calcular el impacto (delta)
    const curr_sim = getV('salario_integral_mensual') || smn;
    const diff_sim = sim - curr_sim;

    // Actualizar elementos visuales del modal
    const setT = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
    setT('modal_sim_sdn', formatVE(sdn_exact));
    setT('modal_sim_alic_v', formatVE4(alic_v));
    setT('modal_sim_alic_u', formatVE4(alic_u));
    setT('modal_sim_sdi', formatVE(sdi));
    setT('modal_sim_sim', formatVE(sim));

    // Badge comparativo de impacto
    const badge = document.getElementById('modal_diff_badge');
    if (badge) {
        if (Math.abs(diff_sim) < 0.01) {
            badge.className = "text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700";
            badge.innerText = "Sin cambios (Igual al formulario)";
        } else if (diff_sim > 0) {
            const pct = curr_sim > 0 ? ((diff_sim / curr_sim) * 100).toFixed(1) : '0';
            badge.className = "text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200";
            badge.innerText = `+Bs. ${formatVE(diff_sim)} (+${pct}%)`;
        } else {
            const pct = curr_sim > 0 ? ((Math.abs(diff_sim) / curr_sim) * 100).toFixed(1) : '0';
            badge.className = "text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200";
            badge.innerText = `-Bs. ${formatVE(Math.abs(diff_sim))} (-${pct}%)`;
        }
    }

    // Ejemplo explicativo en vivo
    const ejEl = document.getElementById('modal_ejemplo_texto');
    if (ejEl) {
        ejEl.innerHTML = `Para este trabajador con <b>Sueldo Normal Diario de Bs. ${formatVE(sdn_exact)}</b> (Bs. ${formatVE(smn)} mensual): fijar <b>${du} días</b> de utilidades y <b>${dv} días</b> de vacaciones genera una alícuota vacacional de <b>Bs. ${formatVE4(alic_v)}</b> y una alícuota de utilidades de <b>Bs. ${formatVE4(alic_u)}</b> (sumando <b>Bs. ${formatVE(total_alic)}/día</b> de alícuotas). Esto fija el Salario Diario Integral en <b>Bs. ${formatVE(sdi)}</b> (equivalente a <b>Bs. ${formatVE(sim)}</b> mensuales), base para el cálculo de la antigüedad (Art. 142 LOTTT).`;
    }
}

function aplicarModalAlicuotas() {
    const du = parseFloat(document.getElementById('modal_dias_utilidades')?.value) || 30;
    const dv = parseFloat(document.getElementById('modal_dias_vacaciones')?.value) || 15;

    const inputDu = document.getElementById('dias_utilidades');
    const inputDv = document.getElementById('dias_vacaciones_alicuota');
    if (inputDu) inputDu.value = du;
    if (inputDv) inputDv.value = dv;

    cerrarModalAlicuotas();

    if (typeof calculateAll === 'function') {
        calculateAll();
    }

    const toast = document.getElementById('toast_modal_alicuotas');
    if (toast) {
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 4000);
    }
}

// Cerrar modal con la tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalAlicuotas();
    }
});
</script>

<script src="assets/js/calculos.js"></script>
<?php include 'includes/footer.php'; ?>

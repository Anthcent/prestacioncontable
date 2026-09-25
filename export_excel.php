<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireLogin();
require_once 'includes/PrestacionesCalculator.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lote = isset($_GET['lote']) ? $_GET['lote'] : '';
$ids = isset($_GET['ids']) && is_array($_GET['ids']) ? array_map('intval', $_GET['ids']) : [];

if ($id == 0 && empty($lote) && empty($ids)) {
    die("No se especificó ninguna liquidación o lote para exportar.");
}

$records = [];

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo, e.clase_cargo, e.nivel, e.categoria, e.fecha_ingreso 
        FROM prestaciones p 
        JOIN empleados e ON p.empleado_id = e.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $records[] = $row;
    $filename = "liquidacion_prestaciones_" . ($row['cedula'] ?? 'export') . ".xls";
} elseif ($lote === 'all') {
    $stmt = $pdo->query("
        SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo, e.clase_cargo, e.nivel, e.categoria, e.fecha_ingreso 
        FROM prestaciones p 
        JOIN empleados e ON p.empleado_id = e.id 
        ORDER BY p.created_at DESC
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filename = "resumen_prestaciones_consolidado_" . date('Y-m-d') . ".xls";
} elseif (!empty($ids)) {
    $in_clause = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo, e.clase_cargo, e.nivel, e.categoria, e.fecha_ingreso 
        FROM prestaciones p 
        JOIN empleados e ON p.empleado_id = e.id 
        WHERE p.id IN ($in_clause)
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($ids);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filename = "lote_prestaciones_seleccionados_" . date('Y-m-d') . ".xls";
}

if (count($records) == 0) {
    die("No se encontraron registros para exportar.");
}

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Imprimir BOM para que Excel reconozca UTF-8 (tildes y ñ)
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid #000000; padding: 5px; }
        th { background-color: #003366; color: #ffffff; font-weight: bold; text-align: center; }
        .bg-title { background-color: #e5e7eb; font-weight: bold; text-align: center; font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .subtotal { background-color: #f3f4f6; font-weight: bold; }
        .total { background-color: #dcfce7; font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>

<?php if (count($records) == 1): ?>
    <?php 
    $p = $records[0];
    $calc = PrestacionesCalculator::calculate(array_merge($p, ['fecha_ingreso' => $p['fecha_ingreso']]));
    $t_y = $calc['tiempo']['y'] ?? 0;
    $t_m = $calc['tiempo']['m'] ?? 0;
    $t_d = $calc['tiempo']['d'] ?? 0;
    $t_meses = $calc['tiempo']['total_months'] ?? 0;
    function xf($num) { return number_format((float)$num, 2, ',', '.'); }
    function xf4($num) { return number_format((float)$num, 2, ',', '.'); }
    ?>
    <table>
        <!-- Cabecera Institucional -->
        <tr>
            <td colspan="7" class="text-center font-bold" style="font-size: 12px;">
                PRIME CONTADORES PÚBLICOS<br>
                SISTEMA INTEGRAL DE NÓMINA Y PRESTACIONES<br>
                <span style="font-size: 10px; font-weight: normal; color: #475569;">LIQUIDACIÓN DE PRESTACIONES SOCIALES - LOTTT</span>
            </td>
        </tr>
        <tr><td colspan="7"></td></tr>
        
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">CALCULO  DE  PRESTACIONES  SOCIALES</th></tr>
        
        <tr>
            <td colspan="3" class="font-bold text-right">FECHA:</td>
            <td colspan="4" class="text-center font-bold"><?php echo date('d/m/Y', strtotime($p['fecha_calculo'])); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-right">CARGO:</td>
            <td colspan="2" class="text-center font-bold"><?php echo htmlspecialchars($p['cargo']); ?></td>
            <td class="font-bold text-right">CLASE DE CARGO:</td>
            <td class="text-center font-bold"><?php echo htmlspecialchars($p['clase_cargo'] ?: 'BACHILLER'); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-right">Apellidos  y  Nombres :</td>
            <td colspan="2" class="text-center font-bold"><?php echo htmlspecialchars($p['apellidos_nombres']); ?></td>
            <td class="font-bold text-right">NIVEL:</td>
            <td class="text-center font-bold"><?php echo htmlspecialchars($p['nivel'] ?: '99'); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-right">Cedula de Identidad :</td>
            <td colspan="2" class="text-center font-bold"><?php echo htmlspecialchars($p['cedula']); ?></td>
            <td class="font-bold text-right">CATEGORIA:</td>
            <td class="text-center font-bold"><?php echo htmlspecialchars(($p['categoria'] ?: 'Empleado') === 'Empleado' ? 'TRABAJADOR' : $p['categoria']); ?></td>
        </tr>
        <tr><td colspan="7"></td></tr>
        <tr>
            <td colspan="3" class="font-bold text-right">MOTIVO:</td>
            <td colspan="4" class="font-bold text-left"><?php echo htmlspecialchars($p['motivo'] ?: 'REMOCION'); ?></td>
        </tr>
        
        <!-- TIEMPO NETO TRABAJADO -->
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">TIEMPO  NETO  TRABAJADO</th></tr>
        
        <tr>
            <td colspan="2"></td>
            <td colspan="3" class="text-center font-bold" style="background-color: #f1f5f9;">DÍA / MES / AÑO</td>
            <td class="font-bold text-right">Sueldo Base Mensual</td>
            <td class="text-right font-bold"><?php echo xf($p['sueldo_base_mensual']); ?></td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold text-center">FECHA DE INGRESO</td>
            <td colspan="3" class="text-center font-bold"><?php echo date('d/m/Y', strtotime($p['fecha_ingreso'])); ?></td>
            <td class="font-bold text-right">Salario Base Diario</td>
            <td class="text-right font-bold"><?php echo xf($calc['salario_base_diario']); ?></td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold text-center">FECHA DE EGRESO</td>
            <td colspan="3" class="text-center font-bold"><?php echo date('d/m/Y', strtotime($p['fecha_egreso'])); ?></td>
            <td class="font-bold text-right">Prima de Antigüedad</td>
            <td class="text-right font-bold"><?php echo xf($p['prima_antiguedad']); ?></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td class="text-center font-bold" style="background-color: #f1f5f9;">Año</td>
            <td class="text-center font-bold" style="background-color: #f1f5f9;">Mes</td>
            <td class="text-center font-bold" style="background-color: #f1f5f9;">Dia</td>
            <td class="font-bold text-right">Prima Hijos</td>
            <td class="text-right font-bold"><?php echo xf($p['prima_hijos']); ?></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td colspan="3"></td>
            <td class="font-bold text-right">Prima por Transporte</td>
            <td class="text-right font-bold"><?php echo xf($p['prima_transporte']); ?></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td colspan="3"></td>
            <td class="font-bold text-right">Bono Nocturno</td>
            <td class="text-right font-bold"><?php echo xf($p['bono_nocturno'] ?? 0); ?></td>
        </tr>
        <?php if (!empty($p['prima_profesionalizacion']) && (float)$p['prima_profesionalizacion'] > 0): ?>
        <tr>
            <td colspan="2"></td>
            <td colspan="3"></td>
            <td class="font-bold text-right">Prima Profesionalización</td>
            <td class="text-right font-bold"><?php echo xf($p['prima_profesionalizacion']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="2" class="font-bold text-left">Tiempo de servicio</td>
            <td class="text-center font-bold"><?php echo $t_y; ?></td>
            <td class="text-center font-bold"><?php echo $t_m; ?></td>
            <td class="text-center font-bold"><?php echo $t_d; ?></td>
            <td class="font-bold text-right" style="background-color: #f1f5f9;">Sueldo Normal Mensual</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['sueldo_mensual_normal']); ?></td>
        </tr>
        <tr>
            <td colspan="5"></td>
            <td class="font-bold text-right" style="background-color: #f1f5f9;">Sueldo Normal Diario</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['sueldo_diario_normal']); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-left">Tiempo de servicio en meses</td>
            <td class="text-center font-bold"><?php echo $t_meses; ?></td>
            <td></td>
            <td class="font-bold text-right" style="background-color: #f1f5f9;">Salario Integral Diario</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['salario_diario_integral']); ?></td>
        </tr>
        
        <tr><td colspan="7"></td></tr>
        
        <!-- PARÁMETROS Y ALÍCUOTAS -->
        <tr>
            <td colspan="2" class="font-bold">Dias de utilidad</td>
            <td class="text-center font-bold" style="background-color: #f1f5f9;">Dias</td>
            <td colspan="2" class="text-center font-bold" style="background-color: #f1f5f9;">Alicuota</td>
            <td class="font-bold text-right" style="background-color: #f1f5f9;">Salario Normal Mensual</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['sueldo_mensual_normal']); ?></td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold">( Alicuota )</td>
            <td class="text-center font-bold"><?php echo $p['dias_utilidades']; ?></td>
            <td colspan="2" class="text-center font-bold"><?php echo xf4($calc['alicuota_utilidades']); ?></td>
            <td class="font-bold text-right" style="background-color: #f1f5f9;">Salario Integral Mensual</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['salario_integral_mensual']); ?></td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold">Dias de vacaciones</td>
            <td class="text-center font-bold" style="background-color: #f1f5f9;">Dias</td>
            <td colspan="2" class="text-center font-bold" style="background-color: #f1f5f9;">Alicuota</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold">( Alicuota )</td>
            <td class="text-center font-bold"><?php echo $p['dias_vacaciones_alicuota']; ?></td>
            <td colspan="2" class="text-center font-bold"><?php echo xf4($calc['alicuota_vacaciones']); ?></td>
            <td colspan="2"></td>
        </tr>
        
        <!-- UTILIDADES O BONO DE FIN DE AÑO FRACCIONADAS -->
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">UTILIDADES O BONO DE FIN DE AÑO FRACCIONADAS</th></tr>
        <tr>
            <td colspan="3"></td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Alicuota</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Dias</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Salario Normal Diario + Alicota de Vacaciones</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Total</td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold">Articulo 131, 132 y 136 LOTTT</td>
            <td class="text-center font-bold"><?php echo xf4($calc['util_alicuota']); ?></td>
            <td class="text-center font-bold"><?php echo xf($p['util_dias']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['util_salario_normal_vac']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['util_total']); ?></td>
        </tr>
        
        <!-- FRACCION DE BONO VACACIONAL -->
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">FRACCION DE BONO VACACIONAL</th></tr>
        <tr>
            <td colspan="3"></td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Alicuota</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Dias</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Salario Normal Diario + Alicota de Aguinaldos</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Total</td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold">Articulo 190 y 192</td>
            <td class="text-center font-bold"><?php echo xf4($calc['vac190_alicuota']); ?></td>
            <td class="text-center font-bold"><?php echo xf($p['vac190_dias']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['vac190_salario']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['vac190_total']); ?></td>
        </tr>

        <!-- FRACCION DE DISFRUTE DE VACACIONES -->
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">FRACCION DE DISFRUTE DE VACACIONES</th></tr>
        <tr>
            <td colspan="2" class="font-bold">Articulo 196</td>
            <td class="text-center font-bold"><?php echo xf($p['vac196_dias']); ?></td>
            <td class="text-center font-bold"><?php echo xf4($calc['vac196_alicuota']); ?></td>
            <td class="text-center font-bold"><?php echo xf($p['vac196_dias']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['vac196_salario']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['vac196_total']); ?></td>
        </tr>
        
        <!-- ANTIGÜEDAD -->
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">ANTIGÜEDAD</th></tr>
        <tr>
            <td colspan="2"></td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Nro. Dias</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Nro. MESES</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Años</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Salario Integral Diario</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Monto Total</td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold">Articulo 142 literal C LOTTT</td>
            <td class="text-center font-bold"><?php echo $p['antig_nro_dias'] > 0 ? xf($p['antig_nro_dias']) : xf($p['antig_anos'] * 30); ?></td>
            <td class="text-center font-bold"><?php echo $t_meses; ?></td>
            <td class="text-center font-bold"><?php echo xf($p['antig_anos']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['salario_diario_integral']); ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['antig_monto_total']); ?></td>
        </tr>
        <tr>
            <td colspan="6" class="font-bold text-right" style="background-color: #f1f5f9;">SUB - TOTAL  ASIGNACIONES :</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['subtotal_asignaciones']); ?></td>
        </tr>
        
        <!-- OTRAS ASIGNACIONES Y DEDUCCIONES -->
        <tr><th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">OTRAS ASIGNACIONES Y DEDUCCIONES</th></tr>
        <tr>
            <td></td><td></td><td></td><td></td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Dias</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Salario Normal Diario</td>
            <td class="font-bold text-center" style="background-color: #f1f5f9;">Total</td>
        </tr>
        <tr>
            <td></td><td></td><td></td><td></td>
            <td class="text-center font-bold"><?php echo ($p['otras_asignaciones_dias'] ?? 0) > 0 ? xf($p['otras_asignaciones_dias']) : '-'; ?></td>
            <td class="text-right font-bold"><?php echo xf($calc['sueldo_diario_normal']); ?></td>
            <td class="text-right font-bold"><?php echo ($calc['otras_asignaciones'] ?? 0) > 0 ? xf($calc['otras_asignaciones']) : '-'; ?></td>
        </tr>
        <tr><td colspan="7"></td></tr>
        <tr>
            <td colspan="4" class="font-bold text-center">INTERESES POR ANTIGÜEDAD</td>
            <td></td><td></td>
            <td class="text-right font-bold"><?php echo ($p['intereses_antiguedad'] ?? 0) > 0 ? xf($p['intereses_antiguedad']) : '-'; ?></td>
        </tr>
        <tr><td colspan="7"></td></tr>
        
        <!-- DEDUCCIONES Y TOTALES -->
        <?php 
        $mostrar_deducciones = (!isset($p['aplicar_deducciones']) || (int)$p['aplicar_deducciones'] === 1) && ((float)$calc['total_deducciones'] > 0 || (isset($p['aplicar_deducciones']) && (int)$p['aplicar_deducciones'] === 1));
        if (isset($p['aplicar_deducciones']) && (int)$p['aplicar_deducciones'] === 0) {
            $mostrar_deducciones = false;
        }
        $rowspan_spacer = $mostrar_deducciones ? 11 : 3;
        ?>
        <tr>
            <td colspan="3" rowspan="<?php echo $rowspan_spacer; ?>"></td>
            <td colspan="3" class="font-bold text-right" style="background-color: #f1f5f9;">TOTAL  ASIGNACIONES :</td>
            <td class="text-right font-bold" style="background-color: #f1f5f9;"><?php echo xf($calc['total_asignaciones']); ?></td>
        </tr>
        <?php if ($mostrar_deducciones): ?>
        <tr>
            <td colspan="3" class="font-bold text-center" style="background-color: #f1f5f9;">M E N O S  :</td>
            <td style="background-color: #f1f5f9;"></td>
        </tr>
        <tr>
            <td colspan="3" class="text-right font-bold">DEPÓSITO BANCARIO FIDEICOMISO</td>
            <td class="text-right font-bold" style="color: #dc2626;"><?php echo xf($calc['deposito_fideicomiso']); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="text-right font-bold">F.A.O.V</td>
            <td class="text-right font-bold" style="color: #dc2626;"><?php echo xf($calc['deduccion_faov']); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="text-right font-bold">I.V.S.S</td>
            <td class="text-right font-bold" style="color: #dc2626;"><?php echo xf($calc['deduccion_ivss']); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="text-right font-bold">INCES</td>
            <td class="text-right font-bold" style="color: #dc2626;"><?php echo xf($calc['deduccion_inces']); ?></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-right" style="background-color: #f1f5f9;">TOTAL DEDUCCIONES</td>
            <td class="text-right font-bold" style="color: #dc2626; background-color: #f1f5f9;"><?php echo xf($calc['total_deducciones']); ?></td>
        </tr>
        <tr>
            <td colspan="3"></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-right">S U B - T O T A L  A  P A G A R</td>
            <td class="text-right font-bold"><?php echo xf($calc['neto_a_cobrar']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td colspan="3"></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="3" class="font-bold text-right">T O T A L  A  P A G A R</td>
            <td class="text-right font-bold" style="background-color: #d9d9d9;"><?php echo xf($calc['neto_a_cobrar']); ?></td>
        </tr>
        
        <!-- RECIBI CONFORME Y FIRMAS -->
        <tr><td colspan="7"></td></tr>
        <tr>
            <th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">RECIBI CONFORME:</th>
        </tr>
        <tr>
            <td colspan="7" style="height: 48px;"></td>
        </tr>
        <tr>
            <th colspan="7" class="bg-title" style="background-color: #d9d9d9; color: #000; font-size: 11px;">FIRMAS:</th>
        </tr>
        <tr>
            <td colspan="2" class="font-bold text-left">Elaborado Por:</td>
            <td colspan="3" class="font-bold text-center">Revisado por:</td>
            <td colspan="2" class="font-bold text-center">Autorizado Por:</td>
        </tr>
        <tr>
            <td colspan="2" style="height: 52px;"></td>
            <td colspan="3" style="height: 52px;"></td>
            <td colspan="2" style="height: 52px;"></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td colspan="3"></td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td colspan="2" class="font-bold text-center" style="font-size: 10px;">ANALISTA</td>
            <td colspan="3" class="font-bold text-center" style="font-size: 10px;">JEFE ( E ) DPTO. DE RELACIONES</td>
            <td colspan="2" class="font-bold text-center" style="font-size: 10px;">DIRECTOR GENERAL DE RECURSOS HUMANOS</td>
        </tr>
    </table>

<?php else: ?>
    <!-- EXPORTACIÓN DE RESUMEN / LOTE CONSOLIDADO -->
    <table>
        <thead>
            <tr>
                <th colspan="9" class="bg-title">PRIME CONTADORES PÚBLICOS — HOJA RESUMEN DE PRESTACIONES</th>
            </tr>
            <tr>
                <th>N°</th>
                <th>Cédula</th>
                <th>Apellidos y Nombres</th>
                <th>Cargo</th>
                <th>Fecha Ingreso</th>
                <th>Fecha Egreso</th>
                <th>Total Asignaciones (Bs.)</th>
                <th>Total Deducciones (Bs.)</th>
                <th>Neto a Cobrar (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            $t_asig = 0;
            $t_ded = 0;
            $t_neto = 0;
            foreach ($records as $r):
                $t_asig += $r['total_asignaciones'];
                $t_ded += $r['total_deducciones'];
                $t_neto += $r['neto_a_cobrar'];
            ?>
            <tr>
                <td class="text-center"><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($r['cedula']); ?></td>
                <td><?php echo htmlspecialchars($r['apellidos_nombres']); ?></td>
                <td><?php echo htmlspecialchars($r['cargo']); ?></td>
                <td class="text-center"><?php echo date('d/m/Y', strtotime($r['fecha_ingreso'])); ?></td>
                <td class="text-center"><?php echo date('d/m/Y', strtotime($r['fecha_egreso'])); ?></td>
                <td class="text-right"><?php echo number_format($r['total_asignaciones'], 2, '.', ''); ?></td>
                <td class="text-right"><?php echo number_format($r['total_deducciones'], 2, '.', ''); ?></td>
                <td class="text-right font-bold"><?php echo number_format($r['neto_a_cobrar'], 2, '.', ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="6" class="text-right">TOTALES GENERALES CONSOLIDADOS:</td>
                <td class="text-right"><?php echo number_format($t_asig, 2, '.', ''); ?></td>
                <td class="text-right"><?php echo number_format($t_ded, 2, '.', ''); ?></td>
                <td class="text-right"><?php echo number_format($t_neto, 2, '.', ''); ?></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

</body>
</html>

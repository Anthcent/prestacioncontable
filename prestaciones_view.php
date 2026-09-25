<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
ensureUsersTable($pdo);
requireLogin();
require_once 'includes/PrestacionesCalculator.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id == 0) {
    header("Location: prestaciones.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, e.cedula, e.apellidos_nombres, e.cargo, e.clase_cargo, e.nivel, e.categoria, e.fecha_ingreso 
    FROM prestaciones p 
    JOIN empleados e ON p.empleado_id = e.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$p) die("Liquidación no encontrada");

// Recalcular en vivo con la calculadora PHP para garantizar conformidad matemática estricta
$calc_results = PrestacionesCalculator::calculate(array_merge($p, ['fecha_ingreso' => $p['fecha_ingreso']]));


function fb($num) {
    return number_format((float)$num, 2, ',', '.');
}
function f6($num) {
    return number_format((float)$num, 6, ',', '.');
}
function f4($num) {
    return number_format((float)$num, 2, ',', '.');
}

$t_y = $calc_results['tiempo']['y'] ?? 0;
$t_m = $calc_results['tiempo']['m'] ?? 0;
$t_d = $calc_results['tiempo']['d'] ?? 0;
$t_meses = $calc_results['tiempo']['total_months'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación de Prestaciones Sociales - <?php echo htmlspecialchars($p['apellidos_nombres']); ?> - PRIME CONTADORES PÚBLICOS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="shortcut icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <style>
        @page {
            size: letter portrait;
            margin: 10mm;
        }
        body { 
            font-family: 'Arial', sans-serif; 
            font-size: 11px; 
            margin: 0; 
            padding: 20px;
            color: #000;
            background-color: #f8fafc;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .excel-grid { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px; 
        }
        .excel-grid td, .excel-grid th { 
            border: 1px solid #000; 
            padding: 3.5px 5px; 
            vertical-align: middle; 
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bg-gray-200 { background-color: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .bg-gray-100 { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .font-bold { font-weight: bold; }
        .border-0 { border: none !important; }
        .border-b { border-bottom: 1px solid #000 !important; }
        .text-red-600 { color: #dc2626 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        
        .no-print { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-family: sans-serif; font-size: 12px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-blue { background-color: #2563eb; color: white; }
        .btn-green { background-color: #16a34a; color: white; }
        .btn-gray { background-color: #64748b; color: white; }
        .btn-amber { background-color: #d97706; color: white; }
        
        @media print {
            body { padding: 0; background: white; }
            .container { max-width: 100%; padding: 0; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- BARRA DE ACCIONES (SOLO EN PANTALLA) -->
        <div class="no-print">
            <div style="display: flex; gap: 10px;">
                <a href="prestaciones.php" class="btn btn-gray">
                    <i class="fa-solid fa-arrow-left"></i> Volver al Listado
                </a>
                <a href="prestaciones_form.php?id=<?php echo $p['id']; ?>&empleado_id=<?php echo $p['empleado_id']; ?>" class="btn btn-amber">
                    <i class="fa-solid fa-pen"></i> Editar Cálculo
                </a>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="export_excel.php?id=<?php echo $p['id']; ?>" class="btn btn-green">
                    <i class="fa-solid fa-file-excel"></i> Exportar a Excel
                </a>
                <button class="btn btn-blue" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Imprimir Liquidación
                </button>
            </div>
        </div>

        <!-- DOCUMENTO INSTITUCIONAL DISTAL S.A. / GOBIERNO BOLIVARIANO DE TRUJILLO -->
        <table class="excel-grid">
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
                <td class="text-center font-bold"><?php echo htmlspecialchars($p['categoria'] ?: 'EMPLEADO'); ?></td>
            </tr>
            <tr><td colspan="7" class="border-0" style="height: 2px;"></td></tr>
            <tr>
                <td colspan="3" class="font-bold text-right">MOTIVO:</td>
                <td colspan="4" class="text-left font-bold" style="padding-left: 12px;"><?php echo htmlspecialchars($p['motivo'] ?: 'REMOCION'); ?></td>
            </tr>
            
            <!-- TIEMPO NETO TRABAJADO -->
            <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px; letter-spacing: 0.5px;">TIEMPO  NETO  TRABAJADO</th></tr>
            
            <tr>
                <td colspan="2" class="border-b-0"></td>
                <td colspan="3" class="text-center font-bold bg-gray-100">DÍA / MES / AÑO</td>
                <td class="font-bold text-right">Sueldo Base Mensual</td>
                <td class="text-right font-bold"><?php echo fb($p['sueldo_base_mensual']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="font-bold text-center">FECHA DE INGRESO</td>
                <td colspan="3" class="text-center font-bold"><?php echo date('d/m/Y', strtotime($p['fecha_ingreso'])); ?></td>
                <td class="font-bold text-right">Salario Base Diario</td>
                <td class="text-right font-bold bg-gray-50"><?php echo fb($calc_results['salario_base_diario']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="font-bold text-center">FECHA DE EGRESO</td>
                <td colspan="3" class="text-center font-bold"><?php echo date('d/m/Y', strtotime($p['fecha_egreso'])); ?></td>
                <td class="font-bold text-right">Prima de Antigüedad</td>
                <td class="text-right font-bold"><?php echo fb($p['prima_antiguedad']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="border-b-0"></td>
                <td class="text-center font-bold bg-gray-100">Año</td>
                <td class="text-center font-bold bg-gray-100">Mes</td>
                <td class="text-center font-bold bg-gray-100">Dia</td>
                <td class="font-bold text-right">Prima Hijos</td>
                <td class="text-right font-bold"><?php echo fb($p['prima_hijos']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="border-b-0 border-t-0"></td>
                <td colspan="3" class="border-b-0 border-t-0"></td>
                <td class="font-bold text-right">Prima por Transporte</td>
                <td class="text-right font-bold"><?php echo fb($p['prima_transporte']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="border-b-0 border-t-0"></td>
                <td colspan="3" class="border-b-0 border-t-0"></td>
                <td class="font-bold text-right">Bono Nocturno</td>
                <td class="text-right font-bold"><?php echo fb($p['bono_nocturno'] ?? 0); ?></td>
            </tr>
            <?php if (!empty($p['prima_profesionalizacion']) && (float)$p['prima_profesionalizacion'] > 0): ?>
            <tr>
                <td colspan="2" class="border-b-0 border-t-0"></td>
                <td colspan="3" class="border-b-0 border-t-0"></td>
                <td class="font-bold text-right">Prima Profesionalización</td>
                <td class="text-right font-bold"><?php echo fb($p['prima_profesionalizacion']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="2" class="font-bold text-left">Tiempo de servicio</td>
                <td class="text-center font-bold"><?php echo $t_y; ?></td>
                <td class="text-center font-bold"><?php echo $t_m; ?></td>
                <td class="text-center font-bold"><?php echo $t_d; ?></td>
                <td class="font-bold text-right bg-gray-100">Sueldo Normal Mensual</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['sueldo_mensual_normal']); ?></td>
            </tr>
            <tr>
                <td colspan="5" class="border-b-0 border-t-0"></td>
                <td class="font-bold text-right bg-gray-100">Sueldo Normal Diario</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['sueldo_diario_normal']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="font-bold text-left">Tiempo de servicio en meses</td>
                <td class="text-center font-bold"><?php echo $t_meses; ?></td>
                <td class="border-b-0 border-t-0"></td>
                <td class="font-bold text-right bg-gray-100">Salario Integral Diario</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['salario_diario_integral']); ?></td>
            </tr>
            
            <tr><td colspan="7" class="border-0" style="height: 4px;"></td></tr>
            
            <!-- PARÁMETROS Y ALÍCUOTAS -->
            <tr>
                <td colspan="2" class="font-bold">Dias de utilidad</td>
                <td class="text-center font-bold bg-gray-100">Dias</td>
                <td colspan="2" class="text-center font-bold bg-gray-100">Alicuota</td>
                <td class="font-bold text-right bg-gray-100">Salario Normal Mensual</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['sueldo_mensual_normal']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="font-bold">( Alicuota )</td>
                <td class="text-center font-bold"><?php echo $p['dias_utilidades']; ?></td>
                <td colspan="2" class="text-center font-bold"><?php echo f4($calc_results['alicuota_utilidades']); ?></td>
                <td class="font-bold text-right bg-gray-100">Salario Integral Mensual</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['salario_integral_mensual']); ?></td>
            </tr>
            <tr>
                <td colspan="2" class="font-bold">Dias de vacaciones</td>
                <td class="text-center font-bold bg-gray-100">Dias</td>
                <td colspan="2" class="text-center font-bold bg-gray-100">Alicuota</td>
                <td colspan="2" class="border-0"></td>
            </tr>
            <tr>
                <td colspan="2" class="font-bold">( Alicuota )</td>
                <td class="text-center font-bold"><?php echo $p['dias_vacaciones_alicuota']; ?></td>
                <td colspan="2" class="text-center font-bold"><?php echo f4($calc_results['alicuota_vacaciones']); ?></td>
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
                <td class="text-center font-bold"><?php echo f4($calc_results['util_alicuota']); ?></td>
                <td class="text-center font-bold"><?php echo fb($p['util_dias']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['util_salario_normal_vac']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['util_total']); ?></td>
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
                <td class="text-center font-bold"><?php echo f4($calc_results['vac190_alicuota']); ?></td>
                <td class="text-center font-bold"><?php echo fb($p['vac190_dias']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['vac190_salario']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['vac190_total']); ?></td>
            </tr>

            <!-- FRACCION DE DISFRUTE DE VACACIONES -->
            <tr><th colspan="7" class="bg-gray-200 text-center font-bold" style="font-size: 11px; padding: 4px;">FRACCION DE DISFRUTE DE VACACIONES</th></tr>
            <tr>
                <td colspan="2" class="font-bold">Articulo 196</td>
                <td class="text-center font-bold"><?php echo fb($p['vac196_dias']); ?></td>
                <td class="text-center font-bold"><?php echo f4($calc_results['vac196_alicuota']); ?></td>
                <td class="text-center font-bold"><?php echo fb($p['vac196_dias']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['vac196_salario']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['vac196_total']); ?></td>
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
                <td class="text-center font-bold"><?php echo $p['antig_nro_dias'] > 0 ? fb($p['antig_nro_dias']) : fb($p['antig_anos'] * 30); ?></td>
                <td class="text-center font-bold"><?php echo $t_meses; ?></td>
                <td class="text-center font-bold"><?php echo fb($p['antig_anos']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['salario_diario_integral']); ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['antig_monto_total']); ?></td>
            </tr>
            <tr>
                <td colspan="6" class="font-bold text-right bg-gray-100">SUB - TOTAL  ASIGNACIONES :</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['subtotal_asignaciones']); ?></td>
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
                <td class="text-center font-bold"><?php echo ($p['otras_asignaciones_dias'] ?? 0) > 0 ? fb($p['otras_asignaciones_dias']) : '-'; ?></td>
                <td class="text-right font-bold"><?php echo fb($calc_results['sueldo_diario_normal']); ?></td>
                <td class="text-right font-bold"><?php echo ($calc_results['otras_asignaciones'] ?? 0) > 0 ? fb($calc_results['otras_asignaciones']) : '-'; ?></td>
            </tr>
            <tr><td colspan="7" style="height: 14px;"></td></tr>
            <tr>
                <td colspan="4" class="font-bold text-center">INTERESES POR ANTIGÜEDAD</td>
                <td></td><td></td>
                <td class="text-right font-bold"><?php echo ($p['intereses_antiguedad'] ?? 0) > 0 ? fb($p['intereses_antiguedad']) : '-'; ?></td>
            </tr>
            <tr><td colspan="7" style="height: 14px;"></td></tr>
            
            <!-- DEDUCCIONES Y TOTALES (CON ÁREA EN BLANCO A LA IZQUIERDA) -->
            <?php 
            $mostrar_deducciones = (!isset($p['aplicar_deducciones']) || (int)$p['aplicar_deducciones'] === 1) && ((float)$calc_results['total_deducciones'] > 0 || (isset($p['aplicar_deducciones']) && (int)$p['aplicar_deducciones'] === 1));
            if (isset($p['aplicar_deducciones']) && (int)$p['aplicar_deducciones'] === 0) {
                $mostrar_deducciones = false;
            }
            $rowspan_spacer = $mostrar_deducciones ? 11 : 3;
            ?>
            <tr>
                <td colspan="3" rowspan="<?php echo $rowspan_spacer; ?>" class="align-top" style="border-top: none; border-left: none; border-bottom: none; border-right: 1px solid #000; background: #fff;"></td>
                <td colspan="3" class="font-bold text-right bg-gray-100">TOTAL  ASIGNACIONES :</td>
                <td class="text-right font-bold bg-gray-100"><?php echo fb($calc_results['total_asignaciones']); ?></td>
            </tr>
            <?php if ($mostrar_deducciones): ?>
            <tr>
                <td colspan="3" class="font-bold text-center bg-gray-100 tracking-widest">M E N O S  :</td>
                <td class="bg-gray-100"></td>
            </tr>
            <tr>
                <td colspan="3" class="text-right font-bold">DEPÓSITO BANCARIO FIDEICOMISO</td>
                <td class="text-right font-bold text-red-600"><?php echo fb($calc_results['deposito_fideicomiso']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-right font-bold">F.A.O.V</td>
                <td class="text-right font-bold text-red-600"><?php echo fb($calc_results['deduccion_faov']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-right font-bold">I.V.S.S</td>
                <td class="text-right font-bold text-red-600"><?php echo fb($calc_results['deduccion_ivss']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-right font-bold">INCES</td>
                <td class="text-right font-bold text-red-600"><?php echo fb($calc_results['deduccion_inces']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="font-bold text-right bg-gray-100">TOTAL DEDUCCIONES</td>
                <td class="text-right font-bold text-red-600 bg-gray-100"><?php echo fb($calc_results['total_deducciones']); ?></td>
            </tr>
            <tr>
                <td colspan="3" style="height: 14px;"></td>
                <td></td>
            </tr>
            <tr>
                <td colspan="3" class="font-bold text-right" style="letter-spacing: 1px;">S U B - T O T A L  A  P A G A R</td>
                <td class="text-right font-bold"><?php echo fb($calc_results['neto_a_cobrar']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="3" style="height: 14px;"></td>
                <td></td>
            </tr>
            <tr>
                <td colspan="3" class="font-bold text-right" style="letter-spacing: 1px;">T O T A L  A  P A G A R</td>
                <td class="text-right font-bold bg-gray-200"><?php echo fb($calc_results['neto_a_cobrar']); ?></td>
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

</body>
</html>

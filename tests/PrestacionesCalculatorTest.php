<?php
require_once __DIR__ . '/../includes/PrestacionesCalculator.php';

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FALLO: {$message}. Esperado " . var_export($expected, true) . ', obtenido ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function calculateCase(string $ingreso, string $egreso): array
{
    return PrestacionesCalculator::calculate([
        'fecha_ingreso' => $ingreso,
        'fecha_egreso' => $egreso,
        'sueldo_base_mensual' => 300,
    ]);
}

$ochoAnosCuatroMeses = calculateCase('2017-06-01', '2025-09-30');
assertSameValue(23, $ochoAnosCuatroMeses['dias_vacaciones_legales'], '8 años y 4 meses deben producir 23 días de vacaciones');
assertSameValue(8.0, $ochoAnosCuatroMeses['antig_anos'], 'Deben conservarse 8 años completos');
assertSameValue(4.0, $ochoAnosCuatroMeses['antig_nro_meses'], 'Deben mostrarse 4 meses de fracción');
assertSameValue(20.0, $ochoAnosCuatroMeses['antig_nro_dias'], '4 meses equivalen a 20 días');

$dieciseisAnos = calculateCase('2009-10-01', '2025-09-30');
assertSameValue(30, $dieciseisAnos['dias_vacaciones_legales'], 'Las vacaciones deben limitarse a 30 días');
assertSameValue(16.0, $dieciseisAnos['antig_anos'], '16 años deben conservarse como 16 años completos');

$tresMeses = calculateCase('2025-06-01', '2025-08-31');
assertSameValue(3.0, $tresMeses['antig_nro_meses'], 'La fracción debe mostrar 3 meses');
assertSameValue(15.0, $tresMeses['antig_nro_dias'], '3 meses equivalen a 15 días');

$seisMeses = calculateCase('2025-03-01', '2025-08-31');
assertSameValue(0.0, $seisMeses['antig_nro_meses'], 'Al llegar a 6 meses, el campo meses debe volver a cero');
assertSameValue(30.0, $seisMeses['antig_nro_dias'], '6 meses equivalen a 30 días');

assertSameValue(round($ochoAnosCuatroMeses['alicuota_vacaciones'], 2), $ochoAnosCuatroMeses['alicuota_vacaciones'], 'La alícuota vacacional debe tener dos decimales');
assertSameValue(round($ochoAnosCuatroMeses['alicuota_utilidades'], 2), $ochoAnosCuatroMeses['alicuota_utilidades'], 'La alícuota de utilidades debe tener dos decimales');

echo "OK: correcciones de prestaciones verificadas." . PHP_EOL;

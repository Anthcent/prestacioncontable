<?php
// includes/PrestacionesCalculator.php

class PrestacionesCalculator {

    /**
     * Calcula la diferencia entre dos fechas emulando la paridad DATEDIF de Excel / Nómina Venezolana
     */
    public static function diffDate($fecha_ingreso, $fecha_egreso) {
        if (empty($fecha_ingreso) || empty($fecha_egreso)) {
            return ['y' => 0, 'm' => 0, 'd' => 0, 'total_months' => 0];
        }

        $d1 = new DateTime($fecha_ingreso);
        $d2 = new DateTime($fecha_egreso);

        if ($d1 > $d2) {
            return ['y' => 0, 'm' => 0, 'd' => 0, 'total_months' => 0];
        }

        // Agregar 1 día para incluir el último día laborado (estándar de liquidación)
        $d2_inclusive = clone $d2;
        $d2_inclusive->modify('+1 day');

        $diff = $d1->diff($d2_inclusive);

        $y = $diff->y;
        $m = $diff->m;
        $d = $diff->d;
        $total_months = ($y * 12) + $m;

        return [
            'y' => $y,
            'm' => $m,
            'd' => $d,
            'total_months' => $total_months
        ];
    }

    /**
     * Redondeo a 2 decimales estándar monetario
     */
    public static function round2($val) {
        return round((float)$val, 2);
    }

    /**
     * Redondeo a 6 decimales para alícuotas
     */
    public static function round6($val) {
        return round((float)$val, 6);
    }

    /**
     * Ejecuta el cálculo completo recibiendo una estructura de entradas (inputs)
     */
    public static function calculate(array $inputs) {
        $fecha_ingreso = $inputs['fecha_ingreso'] ?? '';
        $fecha_egreso = $inputs['fecha_egreso'] ?? '';
        $regla_perfil = $inputs['regla_perfil'] ?? 'LOTTT_30';

        // Tiempo de servicio
        $t = self::diffDate($fecha_ingreso, $fecha_egreso);

        // Sueldo base
        $sb = (float)($inputs['sueldo_base_mensual'] ?? 0);
        $sbd = self::round2($sb / 30);

        // Primas y bonos
        $pa = (float)($inputs['prima_antiguedad'] ?? 0);
        $ph = (float)($inputs['prima_hijos'] ?? 0);
        $pt = (float)($inputs['prima_transporte'] ?? 0);
        $bn = (float)($inputs['bono_nocturno'] ?? 0);
        $pp = (float)($inputs['prima_profesionalizacion'] ?? 0);

        // Sueldo normal
        $smn = self::round2($sb + $pa + $ph + $pt + $bn + $pp);
        $sdn = self::round2($smn / 30);

        // Días de vacaciones del año de servicio en curso (Art. 190 y 196 LOTTT):
        // 15 días base + 1 por cada año cumplido. Ej.: 8 años y 4 meses cursan el 9.º año = 23 días. Máximo: 30.
        $dias_adicionales_vac = min(15, max(0, $t['y']));
        $dias_vac_legales = 15 + $dias_adicionales_vac;
        if ($dias_vac_legales > 30) {
            $dias_vac_legales = 30;
        }

        $du = isset($inputs['dias_utilidades']) && $inputs['dias_utilidades'] !== '' ? (int)$inputs['dias_utilidades'] : 30;
        $dv = isset($inputs['dias_vacaciones_alicuota']) && $inputs['dias_vacaciones_alicuota'] !== '' ? (int)$inputs['dias_vacaciones_alicuota'] : $dias_vac_legales;

        // Sueldo diario exacto sin truncar para bases
        $sdn_exact = $smn / 30;

        // Cálculo de Alícuotas según fórmula oficial del Excel (Gobernación / LOTTT)
        // Alícuota Diaria de Vacaciones: D29 = G23 * C29 / 360
        $alic_v = self::round2(($sdn_exact * $dv) / 360);

        // Alícuota Diaria de Utilidades: D27 = (G23 + D29) * C27 / 360
        // (Sueldo Normal Diario + Alícuota de Vacaciones) * Días Utilidades / 360
        $alic_u = self::round2((($sdn_exact + $alic_v) * $du) / 360);

        // Salario Integral (Fórmula oficial Excel: G24 = G23 + D27 + D29, G27 = G24 * 30)
        $sim = self::round2($smn + ($alic_u * 30) + ($alic_v * 30));
        $sdi = self::round2($sim / 30);

        // Meses de fracción para utilidades y vacaciones
        $meses_fraccion = $t['m'];
        if ($t['y'] === 0 && $t['m'] === 0 && $t['d'] > 0) {
            $meses_fraccion = 1; // Mínimo 1 mes por fracción si trabajó días
        }

        // Auto-días si no vienen forzados manualmente
        // Utilidades (Art. 131, 132 y 136 LOTTT)
        $u_dias = isset($inputs['util_dias']) && (float)$inputs['util_dias'] > 0 
            ? (float)$inputs['util_dias'] 
            : self::round2(($du / 12) * $meses_fraccion);

        // Bono Vacacional (Art. 190 y 192)
        $v190_dias = isset($inputs['vac190_dias']) && (float)$inputs['vac190_dias'] > 0 
            ? (float)$inputs['vac190_dias'] 
            : self::round2(($dv / 12) * $meses_fraccion);

        // Art. 195 ELIMINADO de la LOTTT 2012 (siempre 0)
        $v195_dias = 0.00;

        // Fracción de Disfrute de Vacaciones (Art. 196)
        $v196_dias = isset($inputs['vac196_dias']) && (float)$inputs['vac196_dias'] > 0 
            ? (float)$inputs['vac196_dias'] 
            : self::round2(($dias_vac_legales / 12) * $meses_fraccion);

        // Antigüedad (Art. 142 literal C LOTTT):
        // 30 días de salario integral por cada año de servicio o fracción SUPERIOR a 6 meses.
        // Si tiene 6 meses o menos (<= 6 meses), no redondea.
        // Si tiene fracción superior a 6 meses (> 6 meses o 6 meses con días excedentes), suma 1 año (+30 días).
        $meses_fraccion_antig = min(6, max(0, $t['m']));
        $antig_anos = (float)$t['y'];
        $antig_nro_dias = (float)($meses_fraccion_antig * 5);
        $antig_nro_meses = (float)($t['m'] >= 6 ? 0 : $t['m']);

        // Montos de asignaciones con precisión interna Excel
        $util_snv_exact = $sdn_exact + $alic_v;
        $util_snv = self::round2($util_snv_exact);
        $util_total = self::round2($u_dias * $util_snv_exact);

        $vac190_salario_exact = $sdn_exact + $alic_u;
        $vac190_salario = self::round2($vac190_salario_exact);
        $vac190_total = self::round2($v190_dias * $vac190_salario_exact);

        // Art. 195 eliminado
        $vac195_salario = 0.00;
        $vac195_total = 0.00;

        $vac196_salario_exact = $sdn_exact;
        $vac196_salario = self::round2($vac196_salario_exact);
        $vac196_total = self::round2($v196_dias * $vac196_salario_exact);

        $antig_salario_integral_mensual = $sim;
        $antig_monto_total = self::round2(($antig_nro_dias * $sdi) + ($antig_anos * $sim));

        // Subtotal de asignaciones (sin Art. 195)
        $subtotal_asignaciones = self::round2($util_total + $vac190_total + $vac196_total + $antig_monto_total);

        // Otras asignaciones e intereses
        $o_asig_d = (float)($inputs['otras_asignaciones_dias'] ?? 0);
        if ($o_asig_d > 0) {
            $o_asig = self::round2($o_asig_d * $sdn);
        } else {
            $o_asig = (float)($inputs['otras_asignaciones'] ?? 0);
        }

        $i_antig = (float)($inputs['intereses_antiguedad'] ?? 0);
        $total_asignaciones = self::round2($subtotal_asignaciones + $o_asig + $i_antig);

        // Deducciones (Opcionales con interruptor)
        $aplicar_deducciones = isset($inputs['aplicar_deducciones']) ? (int)$inputs['aplicar_deducciones'] : 1;

        $d_fideicomiso = (float)($inputs['deposito_fideicomiso'] ?? $inputs['otras_deducciones'] ?? 0);
        $d_faov = (float)($inputs['deduccion_faov'] ?? $inputs['deduccion_lph'] ?? 0);
        $d_ivss = (float)($inputs['deduccion_ivss'] ?? $inputs['deduccion_sso'] ?? 0);
        $d_inces = (float)($inputs['deduccion_inces'] ?? $inputs['deduccion_ince'] ?? 0);
        $o_ded = (float)($inputs['otras_deducciones'] ?? 0);

        if ($aplicar_deducciones === 1) {
            $total_deducciones = self::round2($d_fideicomiso + $d_faov + $d_ivss + $d_inces + ($d_fideicomiso > 0 ? 0 : $o_ded));
        } else {
            $total_deducciones = 0.00;
        }
        $neto_a_cobrar = self::round2($total_asignaciones - $total_deducciones);

        return [
            'tiempo' => $t,
            'sueldo_base_mensual' => $sb,
            'salario_base_diario' => $sbd,
            'prima_antiguedad' => $pa,
            'prima_hijos' => $ph,
            'prima_transporte' => $pt,
            'bono_nocturno' => $bn,
            'prima_profesionalizacion' => $pp,
            'sueldo_mensual_normal' => $smn,
            'sueldo_diario_normal' => $sdn,
            'dias_utilidades' => $du,
            'dias_vacaciones_alicuota' => $dv,
            'dias_vacaciones_legales' => $dias_vac_legales,
            'alicuota_utilidades' => $alic_u,
            'alicuota_vacaciones' => $alic_v,
            'salario_diario_integral' => $sdi,
            'salario_integral_mensual' => $sim,
            'util_alicuota' => $alic_u,
            'util_dias' => $u_dias,
            'util_salario_normal_vac' => $util_snv,
            'util_total' => $util_total,
            'vac190_alicuota' => $alic_v,
            'vac190_dias' => $v190_dias,
            'vac190_salario' => $vac190_salario,
            'vac190_total' => $vac190_total,
            'vac195_alicuota' => $alic_v,
            'vac195_dias' => $v195_dias,
            'vac195_salario' => $vac195_salario,
            'vac195_total' => $vac195_total,
            'vac196_alicuota' => $alic_v,
            'vac196_dias' => $v196_dias,
            'vac196_salario' => $vac196_salario,
            'vac196_total' => $vac196_total,
            'antig_nro_dias' => $antig_nro_dias,
            'antig_nro_meses' => $antig_nro_meses,
            'antig_anos' => $antig_anos,
            'antig_salario_integral_mensual' => $antig_salario_integral_mensual,
            'antig_monto_total' => $antig_monto_total,
            'subtotal_asignaciones' => $subtotal_asignaciones,
            'otras_asignaciones_dias' => $o_asig_d,
            'otras_asignaciones' => $o_asig,
            'intereses_antiguedad' => $i_antig,
            'total_asignaciones' => $total_asignaciones,
            'aplicar_deducciones' => $aplicar_deducciones,
            'deposito_fideicomiso' => ($aplicar_deducciones ? $d_fideicomiso : 0.00),
            'deduccion_faov' => ($aplicar_deducciones ? $d_faov : 0.00),
            'deduccion_ivss' => ($aplicar_deducciones ? $d_ivss : 0.00),
            'deduccion_inces' => ($aplicar_deducciones ? $d_inces : 0.00),
            'otras_deducciones' => ($aplicar_deducciones ? $o_ded : 0.00),
            'total_deducciones' => $total_deducciones,
            'subtotal_a_pagar' => $neto_a_cobrar,
            'neto_a_cobrar' => $neto_a_cobrar
        ];
    }

    /**
     * Sugiere automáticamente los montos de Primas según el Sueldo Base, la Antigüedad y el Cargo/Clase
     * basado en la normativa estándar de la Administración Pública Venezolana / Convenciones Colectivas.
     */
    public static function suggestPrimas($sueldo_base, $antiguedad_anos, $clase_cargo = '', $cargo = '') {
        $sb = (float)$sueldo_base;
        $anos = (int)$antiguedad_anos;

        // 1. Prima de Antigüedad (Escala estándar AP / Convención Marco)
        $pct_antig = 0.00;
        if ($anos >= 23) {
            $pct_antig = 0.30;
        } elseif ($anos >= 21) {
            $pct_antig = 0.22;
        } elseif ($anos >= 19) {
            $pct_antig = 0.20;
        } elseif ($anos >= 17) {
            $pct_antig = 0.18;
        } elseif ($anos >= 15) {
            $pct_antig = 0.16;
        } elseif ($anos >= 13) {
            $pct_antig = 0.14;
        } elseif ($anos >= 11) {
            $pct_antig = 0.12;
        } elseif ($anos >= 9) {
            $pct_antig = 0.10;
        } elseif ($anos >= 7) {
            $pct_antig = 0.08;
        } elseif ($anos >= 5) {
            $pct_antig = 0.06;
        } elseif ($anos >= 3) {
            $pct_antig = 0.04;
        } elseif ($anos >= 1) {
            $pct_antig = 0.02;
        }
        $prima_antiguedad = self::round2($sb * $pct_antig);

        // 2. Prima de Profesionalización (Según nivel académico / clase de cargo)
        $clase_norm = mb_strtoupper(trim($clase_cargo . ' ' . $cargo), 'UTF-8');
        $pct_prof = 0.00;
        $tipo_prof = 'Ninguna (0%)';

        if (strpos($clase_norm, 'DOCTOR') !== false || strpos($clase_norm, 'PHD') !== false) {
            $pct_prof = 0.40;
            $tipo_prof = 'Doctorado (40%)';
        } elseif (strpos($clase_norm, 'MAESTR') !== false || strpos($clase_norm, 'MAGISTER') !== false || strpos($clase_norm, 'MASTER') !== false) {
            $pct_prof = 0.35;
            $tipo_prof = 'Maestría (35%)';
        } elseif (strpos($clase_norm, 'ESPECIAL') !== false) {
            $pct_prof = 0.30;
            $tipo_prof = 'Especialista (30%)';
        } elseif (
            strpos($clase_norm, 'PROFESIONAL') !== false || 
            strpos($clase_norm, 'LICENCIAD') !== false || 
            strpos($clase_norm, 'INGENIER') !== false || 
            strpos($clase_norm, 'ABOGAD') !== false || 
            strpos($clase_norm, 'MEDIC') !== false || 
            strpos($clase_norm, 'ARQUITECT') !== false || 
            strpos($clase_norm, 'ECONOMIST') !== false || 
            strpos($clase_norm, 'CONTADOR') !== false ||
            strpos($clase_norm, 'AUDITOR') !== false
        ) {
            $pct_prof = 0.25;
            $tipo_prof = 'Profesional Universitario (25%)';
        } elseif (strpos($clase_norm, 'TSU') !== false || strpos($clase_norm, 'TECNICO') !== false) {
            $pct_prof = 0.12;
            $tipo_prof = 'Técnico Superior (12%)';
        }

        $prima_profesionalizacion = self::round2($sb * $pct_prof);

        return [
            'pct_antig' => $pct_antig,
            'pct_antig_display' => ($pct_antig * 100) . '%',
            'prima_antiguedad' => $prima_antiguedad,
            'pct_prof' => $pct_prof,
            'tipo_prof' => $tipo_prof,
            'prima_profesionalizacion' => $prima_profesionalizacion,
            'sueldo_base' => $sb,
            'anos' => $anos
        ];
    }
}

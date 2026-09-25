document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('form-prestaciones');
    if(!form) return;

    // Listen to changes on all inputs and select fields
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('input', calculateAll);
        input.addEventListener('change', calculateAll);
    });

    function formatVE(num) {
        let val = parseFloat(num);
        if (isNaN(val) || !isFinite(val)) return '0,00';
        try {
            return val.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch(e) {
            let parts = val.toFixed(2).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            return parts.join(',');
        }
    }

    function formatVE4(num) {
        let val = parseFloat(num);
        if (isNaN(val) || !isFinite(val)) return '0,00';
        try {
            return val.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch(e) {
            let parts = val.toFixed(2).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            return parts.join(',');
        }
    }

    function getVal(id) {
        const el = document.getElementById(id);
        if(!el) return 0;
        let raw = (el.value || '').toString().trim().replace(/\s+/g, '');
        if (raw.indexOf(',') !== -1 && raw.indexOf('.') !== -1) {
            raw = raw.replace(/\./g, '').replace(',', '.');
        } else if (raw.indexOf(',') !== -1) {
            raw = raw.replace(',', '.');
        }
        let val = parseFloat(raw);
        return isNaN(val) || !isFinite(val) ? 0 : val;
    }

    function setVal(id, val) {
        const el = document.getElementById(id);
        if(el) el.value = (isNaN(val) || !isFinite(val) ? 0 : val).toFixed(2);
    }
    
    function setVal6(id, val) {
        const el = document.getElementById(id);
        if(el) el.value = (isNaN(val) || !isFinite(val) ? 0 : val).toFixed(2);
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if(el) el.innerText = text;
    }

    function diffDate(d1, d2) {
        if (!d1 || !d2) return { y: 0, m: 0, d: 0, total_months: 0 };
        
        let p1 = d1.split('-');
        let p2 = d2.split('-');
        if(p1.length !== 3 || p2.length !== 3) return { y: 0, m: 0, d: 0, total_months: 0 };
        
        let date1 = new Date(parseInt(p1[0]), parseInt(p1[1])-1, parseInt(p1[2]));
        let date2 = new Date(parseInt(p2[0]), parseInt(p2[1])-1, parseInt(p2[2]));
        
        if (isNaN(date1) || isNaN(date2) || date1 > date2) return { y: 0, m: 0, d: 0, total_months: 0 };
        
        // Add 1 day for inclusive calculation
        date2.setDate(date2.getDate() + 1);

        let y = date2.getFullYear() - date1.getFullYear();
        let m = date2.getMonth() - date1.getMonth();
        let d = date2.getDate() - date1.getDate();

        if (d < 0) {
            m--;
            let temp = new Date(date2.getFullYear(), date2.getMonth(), 0);
            d += temp.getDate();
        }
        if (m < 0) {
            y--;
            m += 12;
        }

        let total_months = (y * 12) + m;
        return { y, m, d, total_months };
    }

    function round2(num) {
        return Math.round(num * 100) / 100;
    }

    function round6(num) {
        return Math.round(num * 1000000) / 1000000;
    }

    function calculateAll() {
        const fechaIngresoEl = document.getElementById('fecha_ingreso');
        const fechaEgresoEl = document.getElementById('fecha_egreso');
        if(!fechaIngresoEl || !fechaEgresoEl) return;

        const fechaIngreso = fechaIngresoEl.value;
        const fechaEgreso = fechaEgresoEl.value;
        const reglaPerfil = (document.getElementById('regla_perfil') ? document.getElementById('regla_perfil').value : 'LOTTT_30');
        
        let t = diffDate(fechaIngreso, fechaEgreso);
        setText('t_anos', t.y);
        setText('t_meses', t.m);
        setText('t_dias', t.d);
        setText('lbl_tiempo_servicio', `${t.y} Años, ${t.m} Meses, ${t.d} Días`);
        
        setText('t_meses_total', t.total_months);
        const mesesAntig = Math.min(6, Math.max(0, t.m));
        if(document.getElementById('antig_nro_meses')) document.getElementById('antig_nro_meses').value = t.m >= 6 ? 0 : t.m;
        if(document.getElementById('antig_nro_dias')) document.getElementById('antig_nro_dias').value = mesesAntig * 5;
        if(document.getElementById('antig_anos')) document.getElementById('antig_anos').value = t.y;
        setText('lbl_antig_meses', t.m >= 6 ? 0 : t.m);

        // Sueldos Normales
        let sbm = getVal('sueldo_base_mensual');
        let sbd = round2(sbm / 30);
        setVal('salario_base_diario', sbd);
        setText('disp_salario_base_diario', formatVE(sbd));

        let pa = getVal('prima_antiguedad');
        let ph = getVal('prima_hijos');
        let pt = getVal('prima_transporte');
        let bn = getVal('bono_nocturno');
        let pp = getVal('prima_profesionalizacion');

        let smn = round2(sbm + pa + ph + pt + bn + pp);
        setVal('sueldo_mensual_normal', smn);
        setText('disp_sueldo_mensual_normal', 'Bs. ' + formatVE(smn));
        
        let sdn = round2(smn / 30);
        setVal('sueldo_diario_normal', sdn);
        setText('disp_sueldo_diario_normal', 'Bs. ' + formatVE(sdn));

        actualizarBadgesSugerenciasPrimas(t.y, sbm);

        // Días base anual de vacaciones (Art. 190 y 196 LOTTT):
        // 15 días base. A partir de 1 año y 6 meses (+1 día adicional = 16), a 2 años y 6 meses (+2 días = 17), hasta máx 15 adicionales (30 días).
        let dias_adicionales_vac = Math.min(15, Math.max(0, t.y));
        let dias_vac_legales = 15 + dias_adicionales_vac;
        if (dias_vac_legales > 30) dias_vac_legales = 30;
        window.diasVacLegalesActuales = dias_vac_legales;

        // Actualizar badges e indicadores de días legales en la interfaz
        let lblVacSug = document.getElementById('lbl_dias_vac_sug');
        if (lblVacSug) lblVacSug.innerText = dias_vac_legales + 'd';
        let modalBtnDv = document.getElementById('modal_lbl_btn_dv');
        if (modalBtnDv) modalBtnDv.innerText = dias_vac_legales;
        let modalSugDvBtn = document.getElementById('modal_sug_dv_btn');
        if (modalSugDvBtn) modalSugDvBtn.innerText = dias_vac_legales;

        // Auto fill fractional days if editing dates
        let isDateEvent = (document.activeElement && (document.activeElement.id === 'fecha_egreso' || document.activeElement.id === 'fecha_ingreso' || document.activeElement.id === 'regla_perfil'));
        
        // Si el usuario cambia las fechas y está en modo LOTTT, actualizar automáticamente los días de vacaciones para alícuota
        if (isDateEvent && reglaPerfil !== 'LEGADO_120_180') {
            let dvEl = document.getElementById('dias_vacaciones_alicuota');
            if (dvEl) dvEl.value = dias_vac_legales;
        }

        let du = getVal('dias_utilidades') || (reglaPerfil === 'LEGADO_120_180' ? 120 : 30);
        let dv = getVal('dias_vacaciones_alicuota') || (reglaPerfil === 'LEGADO_120_180' ? 180 : dias_vac_legales);

        if (isDateEvent) {
            let meses_utilidades = t.m;
            if (t.y === 0 && t.m === 0 && t.d > 0) meses_utilidades = 1;

            let util_fraccion_dias = round2((du / 12) * meses_utilidades);
            if(document.getElementById('util_dias')) document.getElementById('util_dias').value = util_fraccion_dias;
            
            let vac190_fraccion_dias = round2((dv / 12) * meses_utilidades);
            if(document.getElementById('vac190_dias')) document.getElementById('vac190_dias').value = vac190_fraccion_dias;
            if(document.getElementById('vac195_dias')) document.getElementById('vac195_dias').value = 0;

            let vac196_fraccion_dias = round2((dias_vac_legales / 12) * meses_utilidades);
            if(document.getElementById('vac196_dias')) document.getElementById('vac196_dias').value = vac196_fraccion_dias;
            
            // Antigüedad (Art. 142 literal C LOTTT):
            // 30 días por año de servicio o fracción SUPERIOR a 6 meses.
            // 6 meses o menos no redondea; más de 6 meses (ej. 7 meses) suma +1 año (+30 días).
            let mesesAntigFecha = Math.min(6, Math.max(0, t.m));
            if(document.getElementById('antig_anos')) document.getElementById('antig_anos').value = t.y;
            if(document.getElementById('antig_nro_meses')) document.getElementById('antig_nro_meses').value = t.m >= 6 ? 0 : t.m;
            if(document.getElementById('antig_nro_dias')) document.getElementById('antig_nro_dias').value = mesesAntigFecha * 5;
        }

        let sdn_exact = smn / 30;

        // Alicuotas (Conforme a fórmula oficial Excel Gobernación: D29 = G23*C29/360 y D27 = (G23+D29)*C27/360)
        let alic_v = round2((sdn_exact * dv) / 360);
        let alic_u = round2(((sdn_exact + alic_v) * du) / 360);
        
        setVal6('alicuota_utilidades', alic_u);
        setVal6('alicuota_vacaciones', alic_v);

        // Integral (Fórmula oficial Excel: Sueldo Normal Mensual + Alícuota Mensual Utilidades + Alícuota Mensual Vacaciones)
        let sim = round2(smn + (alic_u * 30) + (alic_v * 30));
        let sdi = round2(sim / 30);
        setVal('salario_diario_integral', sdi);
        setText('disp_salario_diario_integral', formatVE(sdi));
        setVal('salario_integral_mensual', sim);
        setText('disp_salario_integral_mensual', formatVE(sim));
        setVal('antig_salario_integral_mensual', sim);

        // Utilidades (Art. 131, 132 y 136 LOTTT)
        setVal6('util_alicuota', alic_u);
        let util_snv_exact = sdn_exact + alic_v;
        let util_snv = round2(util_snv_exact);
        setVal('util_salario_normal_vac', util_snv);
        setText('lbl_util_snv', formatVE(util_snv));
        
        let util_total = round2(getVal('util_dias') * util_snv_exact);
        setVal('util_total', util_total);

        // Bono Vacacional (Art. 190 y 192)
        setVal6('vac190_alicuota', alic_v);
        let vac190_base_exact = sdn_exact + alic_u; 
        let vac190_base = round2(vac190_base_exact);
        setVal('vac190_salario', vac190_base);
        let vac190_tot = round2(getVal('vac190_dias') * vac190_base_exact);
        setVal('vac190_total', vac190_tot);

        // Art. 195 Eliminado
        setVal6('vac195_alicuota', 0);
        setVal('vac195_salario', 0);
        setVal('vac195_total', 0);
        let vac195_tot = 0;

        // Disfrute de Vacaciones (Art. 196)
        setVal6('vac196_alicuota', alic_v);
        let vac196_salario_exact = sdn_exact;
        setVal('vac196_salario', sdn);
        let vac196_tot = round2(getVal('vac196_dias') * vac196_salario_exact);
        setVal('vac196_total', vac196_tot);

        // Antigüedad (Art 142 literal C LOTTT)
        let antig_anos_val = getVal('antig_anos');
        let antig_dias_val = getVal('antig_nro_dias');
        let antig_tot = round2((antig_dias_val * sdi) + (antig_anos_val * sim));
        setVal('antig_monto_total', antig_tot);

        // Sub Total Asignaciones (Sin Art. 195)
        let sub_asig = round2(util_total + vac190_tot + vac196_tot + antig_tot);
        setText('lbl_subtotal_asig', formatVE(sub_asig));

        // Otras Asig
        let o_asig_d = getVal('o_asig_d');
        setVal('o_asig_s', sdn);
        
        let o_asig = getVal('otras_asignaciones');
        if (o_asig_d > 0) {
            o_asig = round2(o_asig_d * sdn);
            setVal('otras_asignaciones', o_asig);
        }

        let i_antig = getVal('intereses_antiguedad');
        let tot_asig = round2(sub_asig + o_asig + i_antig);
        setVal('total_asignaciones', tot_asig);

        // Deducciones (Opcionales con Switch)
        const checkDeduc = document.getElementById('aplicar_deducciones');
        const aplicarDeduc = checkDeduc ? checkDeduc.checked : true;

        let tot_deduc = 0;
        if (aplicarDeduc) {
            let d_fid = getVal('deposito_fideicomiso');
            let d_faov = getVal('deduccion_lph') || getVal('deduccion_faov');
            let d_ivss = getVal('deduccion_sso') || getVal('deduccion_ivss');
            let d_inces = getVal('deduccion_ince') || getVal('deduccion_inces');
            let o_ded = getVal('otras_deducciones');
            tot_deduc = round2(d_fid + d_faov + d_ivss + d_inces + (d_fid > 0 ? 0 : o_ded));
        }
        setVal('total_deducciones', tot_deduc);

        // Pagar
        let neto = round2(tot_asig - tot_deduc);
        setText('lbl_subtotal_pagar', formatVE(neto));
        setVal('neto_a_cobrar', neto);

        // Sidebar live summary con formato venezolano (puntos para miles, coma para decimales)
        setText('sidebar_smn', formatVE(smn));
        setText('sidebar_sdi', formatVE(sdi));
        setText('sidebar_asig', formatVE(tot_asig));
        
        const badgeDeduc = document.getElementById('sidebar_deduc_badge');
        if (aplicarDeduc) {
            setText('sidebar_deduc', formatVE(tot_deduc));
            if (badgeDeduc) badgeDeduc.classList.add('hidden');
        } else {
            setText('sidebar_deduc', '0,00');
            if (badgeDeduc) badgeDeduc.classList.remove('hidden');
        }

        // Ajuste tipográfico dinámico en Monto Neto para prevenir cualquier desbordamiento
        const elSidebarNeto = document.getElementById('sidebar_neto');
        if (elSidebarNeto) {
            let formattedNeto = formatVE(neto);
            elSidebarNeto.innerText = formattedNeto;
            if (formattedNeto.length > 20) {
                elSidebarNeto.className = 'text-sm sm:text-base font-black tracking-tight text-white break-all inline-block max-w-full';
            } else if (formattedNeto.length > 14) {
                elSidebarNeto.className = 'text-base sm:text-lg font-black tracking-tight text-white break-all inline-block max-w-full';
            } else if (formattedNeto.length > 10) {
                elSidebarNeto.className = 'text-xl sm:text-2xl font-black tracking-tight text-white break-all inline-block max-w-full';
            } else {
                elSidebarNeto.className = 'text-2xl sm:text-3xl font-black tracking-tight text-white break-all inline-block max-w-full';
            }
        }

        if (typeof syncPreviewTab === 'function') {
            syncPreviewTab();
        }
    }

    // Función explícita para auto-calcular días sugeridos según la ley
    function autoCalcularDiasLegales(notify) {
        const fechaIngresoEl = document.getElementById('fecha_ingreso');
        const fechaEgresoEl = document.getElementById('fecha_egreso');
        if(!fechaIngresoEl || !fechaEgresoEl) return;

        let t = diffDate(fechaIngresoEl.value, fechaEgresoEl.value);
        let reglaPerfil = (document.getElementById('regla_perfil') ? document.getElementById('regla_perfil').value : 'LOTTT_30');

        let dias_adicionales_vac = Math.min(15, Math.max(0, t.y));
        let dias_vac_legales = 15 + dias_adicionales_vac;
        if (dias_vac_legales > 30) dias_vac_legales = 30;

        let dvEl = document.getElementById('dias_vacaciones_alicuota');
        if (dvEl) {
            if (reglaPerfil === 'LEGADO_120_180') dvEl.value = 180;
            else dvEl.value = dias_vac_legales;
        }
        let duEl = document.getElementById('dias_utilidades');
        if (duEl) {
            if (reglaPerfil === 'LEGADO_120_180') duEl.value = 120;
            else duEl.value = 30;
        }

        let du = getVal('dias_utilidades') || (reglaPerfil === 'LEGADO_120_180' ? 120 : 30);
        let dv = getVal('dias_vacaciones_alicuota') || (reglaPerfil === 'LEGADO_120_180' ? 180 : dias_vac_legales);

        let meses_utilidades = t.m;
        if (t.y === 0 && t.m === 0 && t.d > 0) meses_utilidades = 1;

        let util_fraccion_dias = round2((du / 12) * meses_utilidades);
        if(document.getElementById('util_dias')) document.getElementById('util_dias').value = util_fraccion_dias;
        
        let vac190_fraccion_dias = round2((dv / 12) * meses_utilidades);
        if(document.getElementById('vac190_dias')) document.getElementById('vac190_dias').value = vac190_fraccion_dias;
        if(document.getElementById('vac195_dias')) document.getElementById('vac195_dias').value = 0;

        let vac196_fraccion_dias = round2((dias_vac_legales / 12) * meses_utilidades);
        if(document.getElementById('vac196_dias')) document.getElementById('vac196_dias').value = vac196_fraccion_dias;
        
        let mesesAntigAuto = Math.min(6, Math.max(0, t.m));
        if(document.getElementById('antig_anos')) document.getElementById('antig_anos').value = t.y;
        if(document.getElementById('antig_nro_meses')) document.getElementById('antig_nro_meses').value = t.m >= 6 ? 0 : t.m;
        if(document.getElementById('antig_nro_dias')) document.getElementById('antig_nro_dias').value = mesesAntigAuto * 5;

        calculateAll();

        if (notify) {
            let toast = document.getElementById('toast_auto_dias');
            if (toast) {
                toast.classList.remove('hidden');
                setTimeout(() => { if (toast) toast.classList.add('hidden'); }, 3000);
            }
        }
    }

    // Cálculo y sugerencia inteligente de primas según normativa venezolana (Administración Pública / LOTTT)
    function getPrimasSugeridas(anos, sbm) {
        anos = parseInt(anos) || 0;
        sbm = parseFloat(sbm) || 0;

        // 1. Prima de Antigüedad (Escala estándar AP / Convención Marco)
        let pct_antig = 0;
        if (anos >= 23) pct_antig = 0.30;
        else if (anos >= 21) pct_antig = 0.22;
        else if (anos >= 19) pct_antig = 0.20;
        else if (anos >= 17) pct_antig = 0.18;
        else if (anos >= 15) pct_antig = 0.16;
        else if (anos >= 13) pct_antig = 0.14;
        else if (anos >= 11) pct_antig = 0.12;
        else if (anos >= 9) pct_antig = 0.10;
        else if (anos >= 7) pct_antig = 0.08;
        else if (anos >= 5) pct_antig = 0.06;
        else if (anos >= 3) pct_antig = 0.04;
        else if (anos >= 1) pct_antig = 0.02;

        let prima_antig = round2(sbm * pct_antig);

        // 2. Prima de Profesionalización (Detección por grado académico / clase de cargo)
        let cargo = (document.getElementById('emp_cargo')?.value || '').toUpperCase();
        let clase = (document.getElementById('emp_clase_cargo')?.value || '').toUpperCase();
        let fullTxt = clase + ' ' + cargo;

        let pct_prof = 0;
        let tipo_prof = 'Sin título (0%)';
        if (fullTxt.includes('DOCTOR') || fullTxt.includes('PHD')) {
            pct_prof = 0.40;
            tipo_prof = 'Doctorado (40%)';
        } else if (fullTxt.includes('MAESTR') || fullTxt.includes('MAGISTER') || fullTxt.includes('MASTER')) {
            pct_prof = 0.35;
            tipo_prof = 'Maestría (35%)';
        } else if (fullTxt.includes('ESPECIAL')) {
            pct_prof = 0.30;
            tipo_prof = 'Especialista (30%)';
        } else if (
            fullTxt.includes('PROFESIONAL') || fullTxt.includes('LICENCIAD') ||
            fullTxt.includes('INGENIER') || fullTxt.includes('ABOGAD') ||
            fullTxt.includes('MEDIC') || fullTxt.includes('ARQUITECT') ||
            fullTxt.includes('ECONOMIST') || fullTxt.includes('CONTADOR') ||
            fullTxt.includes('AUDITOR')
        ) {
            pct_prof = 0.25;
            tipo_prof = 'Profesional Univ. (25%)';
        } else if (fullTxt.includes('TSU') || fullTxt.includes('TECNICO')) {
            pct_prof = 0.12;
            tipo_prof = 'TSU / Técnico (12%)';
        }

        let prima_prof = round2(sbm * pct_prof);

        return {
            anos,
            pct_antig,
            pct_antig_lbl: (pct_antig * 100) + '%',
            prima_antig,
            pct_prof,
            tipo_prof,
            prima_prof
        };
    }

    function actualizarBadgesSugerenciasPrimas(anos, sbm) {
        let sug = getPrimasSugeridas(anos, sbm);
        
        // Badge Antigüedad
        let lblAntigMonto = document.getElementById('lbl_sug_antig_monto');
        let lblAntigDesc = document.getElementById('lbl_sug_antig_desc');
        if (lblAntigMonto) lblAntigMonto.innerText = 'Bs. ' + formatVE(sug.prima_antig);
        if (lblAntigDesc) lblAntigDesc.innerText = sug.pct_antig_lbl + ' (' + sug.anos + ' ' + (sug.anos === 1 ? 'año' : 'años') + ')';

        // Badge Profesionalización
        let lblProfMonto = document.getElementById('lbl_sug_prof_monto');
        let lblProfDesc = document.getElementById('lbl_sug_prof_desc');
        if (lblProfMonto) lblProfMonto.innerText = 'Bs. ' + formatVE(sug.prima_prof);
        if (lblProfDesc) lblProfDesc.innerText = sug.tipo_prof;
    }

    // Auto-sugerir todas las primas aplicables
    function autoSugerirPrimas(notify = false) {
        const fechaIngresoEl = document.getElementById('fecha_ingreso');
        const fechaEgresoEl = document.getElementById('fecha_egreso');
        if(!fechaIngresoEl || !fechaEgresoEl) return;

        let t = diffDate(fechaIngresoEl.value, fechaEgresoEl.value);
        let sbm = getVal('sueldo_base_mensual');
        if (sbm <= 0) {
            sbm = 130.00;
            setVal('sueldo_base_mensual', sbm);
        }

        let sug = getPrimasSugeridas(t.y, sbm);
        
        let elPa = document.getElementById('prima_antiguedad');
        if (elPa) elPa.value = sug.prima_antig.toFixed(2);

        let elPp = document.getElementById('prima_profesionalizacion');
        if (elPp) elPp.value = sug.prima_prof.toFixed(2);

        calculateAll();

        if (notify) {
            let toast = document.getElementById('toast_auto_primas');
            if (toast) {
                let toastTxt = document.getElementById('toast_auto_primas_text');
                if (toastTxt) {
                    toastTxt.innerText = `Primas sugeridas aplicadas: Antigüedad (${sug.pct_antig_lbl}) y Profesionalización (${sug.tipo_prof}).`;
                }
                toast.classList.remove('hidden');
                setTimeout(() => { if (toast) toast.classList.add('hidden'); }, 3500);
            }
        }
    }

    // Aplicar sugerencia a un campo individual (clic en el badge)
    function aplicarSugerenciaPrima(tipo) {
        const fechaIngresoEl = document.getElementById('fecha_ingreso');
        const fechaEgresoEl = document.getElementById('fecha_egreso');
        let t = diffDate(fechaIngresoEl?.value, fechaEgresoEl?.value);
        let sbm = getVal('sueldo_base_mensual');
        let sug = getPrimasSugeridas(t.y, sbm);

        if (tipo === 'antiguedad') {
            let el = document.getElementById('prima_antiguedad');
            if (el) el.value = sug.prima_antig.toFixed(2);
        } else if (tipo === 'profesionalizacion') {
            let el = document.getElementById('prima_profesionalizacion');
            if (el) el.value = sug.prima_prof.toFixed(2);
        }
        calculateAll();
    }

    function aplicarPresetTransporte(monto) {
        let el = document.getElementById('prima_transporte');
        if (el) el.value = parseFloat(monto).toFixed(2);
        calculateAll();
    }

    function aplicarHijos(cantidad, montoPorHijo = 12.50) {
        let el = document.getElementById('prima_hijos');
        if (el) el.value = (parseInt(cantidad) * parseFloat(montoPorHijo)).toFixed(2);
        calculateAll();
    }

    function aplicarDiasVacLegales() {
        let dvEl = document.getElementById('dias_vacaciones_alicuota');
        if (dvEl && window.diasVacLegalesActuales) {
            dvEl.value = window.diasVacLegalesActuales;
            calculateAll();
        }
    }

    // Exponer globalmente
    window.formatVE = formatVE;
    window.formatVE4 = formatVE4;
    window.getVal = getVal;
    window.autoCalcularDiasLegales = autoCalcularDiasLegales;
    window.autoSugerirPrimas = autoSugerirPrimas;
    window.aplicarSugerenciaPrima = aplicarSugerenciaPrima;
    window.aplicarPresetTransporte = aplicarPresetTransporte;
    window.aplicarHijos = aplicarHijos;
    window.aplicarDiasVacLegales = aplicarDiasVacLegales;
    window.calculateAll = calculateAll;

    calculateAll();
});

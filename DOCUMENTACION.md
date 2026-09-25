# Sistema de Cálculo de Prestaciones Sociales - DISTAL S.A.

## 1. Visión General del Proyecto
Este sistema es una aplicación web diseñada para digitalizar y automatizar la nómina y el cálculo de liquidaciones (Prestaciones Sociales) de la **Distribuidora Trujillana de Alimentos (DISTAL) S.A.**, Empresa de Propiedad Social del Gobierno Bolivariano de Trujillo (Venezuela).

El sistema fue construido a partir de la ingeniería inversa de una plantilla de Excel heredada ("PLANILLAS FIJOS VIEJO FORMATO 2023"), asegurando que la lógica matemática, legal y contable sea idéntica, pero con la robustez de una aplicación web real.

---

## 2. Stack Tecnológico
* **Backend:** PHP 8.x (Nativo, sin frameworks pesados para maximizar rendimiento y facilidad de despliegue).
* **Base de Datos:** MySQL / MariaDB (Conexión vía PDO con sentencias preparadas para prevenir Inyección SQL).
* **Frontend UI:** HTML5 + Tailwind CSS (vía CDN) + FontAwesome (Iconografía).
* **Frontend Lógica:** Vanilla JavaScript (ES6) para el cálculo matemático reactivo y validaciones en tiempo real en el DOM.

---

## 3. Estructura de Directorios

```text
sistema 2/
├── config/
│   └── database.php             # Credenciales y conexión a la base de datos (PDO)
├── includes/
│   ├── header.php               # Head HTML, importación de Tailwind CSS, scripts globales
│   ├── footer.php               # Pie de página y cierre de etiquetas
│   └── nav.php                  # Menú lateral de navegación (Sidebar)
├── assets/
│   ├── css/                     # (Carpeta lista para estilos personalizados locales)
│   └── js/
│       └── calculos.js          # Motor matemático de prestaciones sociales
├── sql/
│   └── setup.sql                # Script DDL para construir la estructura de la base de datos
├── index.php                    # Dashboard Principal: Consolidado de todos los empleados
├── empleados.php                # CRUD: Lista de empleados registrados
├── empleado_form.php            # CRUD: Formulario para crear/editar perfil del empleado
├── prestaciones.php             # Lista del historial de cálculos de liquidaciones generados
├── prestaciones_form.php        # Formulario interactivo de cálculo de prestaciones
├── prestaciones_view.php        # Plantilla de Impresión / PDF (Idéntica al Excel original)
└── DOCUMENTACION.md             # Este archivo
```

---

## 4. Lógica de Negocio y Reglas Legales (LOTTT)

El sistema está estrictamente sujeto a la Ley Orgánica del Trabajo, los Trabajadores y las Trabajadoras (LOTTT) de Venezuela. Las fórmulas matemáticas aplicadas en `assets/js/calculos.js` son las siguientes:

### 4.1. Tiempo de Servicio
* Se calcula utilizando la **regla de tiempo comercial**: 1 mes = 30 días, 1 año = 360 días.
* Si el sistema detecta que la `fecha_egreso` es anterior a la `fecha_ingreso`, bloquea el cálculo para evitar corrupciones de datos en base al continuo del espacio-tiempo laboral.

### 4.2. Salarios Base y Normales
* **Sueldo Base Diario:** `Sueldo Base Mensual / 30`
* **Sueldo Mensual Normal:** `Sueldo Base Mensual + Primas (Antigüedad, Hijos, Transporte, Profesionalización)`
* **Sueldo Diario Normal:** `Sueldo Mensual Normal / 30`

### 4.3. Alícuotas
* **Utilidades:** `(Días Utilidades / 360) * Sueldo Diario Normal`
  * *Validación Legal:* Si el valor ingresado es menor a 30, el sistema lo forza a 30 (Mínimo legal). Valor estándar DISTAL: **120 días**.
* **Vacaciones:** `(Días Vacaciones / 360) * Sueldo Diario Normal`
  * *Validación Legal:* Si el valor ingresado es menor a 15, el sistema lo forza a 15 (Mínimo legal). Valor estándar DISTAL: **180 días**.

### 4.4. Salario Integral
El salario base de impacto para las prestaciones es el Integral:
* **Salario Diario Integral:** `Sueldo Diario Normal + Alícuota Utilidades + Alícuota Vacaciones`
* **Salario Integral Mensual:** `Salario Diario Integral * 30`

### 4.5. Prestaciones Sociales
* **Utilidades Fraccionadas (Arts. 131 y 136):** `Días a pagar * (Sueldo Diario Normal + Alícuota Vacaciones)`
* **Vacaciones (Arts. 190, 192, 195, 196):** `Días a pagar * Sueldo Diario Normal`
* **Antigüedad (Art. 142 literal C):** `Años de servicio * Salario Integral Mensual`

### 4.6. Liquidación Neta
* **Asignaciones:** Sumatoria de (Utilidades + Vacaciones + Antigüedad).
* **Deducciones:** Sumatoria de S.S.O, R.P.V.C, L.P.H, I.N.C.E, y otros préstamos.
* **Neto a Cobrar:** `Total Asignaciones - Total Deducciones`.
  * *Validación Financiera:* El sistema no permite Netos Negativos. Si las deducciones superan las asignaciones, el total se frena en `0.00`.

---

## 5. Esquema de Base de Datos (MySQL)

La DB `sistema_distal` está normalizada en dos tablas principales, unidas por llaves foráneas (`FOREIGN KEY`) con acción `ON DELETE CASCADE`, para garantizar la integridad referencial.

### 5.1. Tabla: `empleados`
Almacena la data dura del personal.
* `id`: (INT, PK, Auto Increment)
* `cedula`: (VARCHAR, UNIQUE) Identificador único nacional.
* `apellidos_nombres`: (VARCHAR)
* `cargo`, `clase_cargo`, `nivel`, `categoria`: (VARCHAR) Definiciones escalafonarias.
* `fecha_ingreso`: (DATE) Fecha inicial del vínculo laboral.

### 5.2. Tabla: `prestaciones`
Guarda el estado estático de la liquidación al momento de generarse, a modo de auditoría inmutable (Snapshot data).
* `empleado_id`: (INT, FK) Apunta al empleado.
* `fecha_calculo`, `fecha_egreso`, `motivo`.
* Campos monetarios y numéricos desglosados en: Salarios (`DECIMAL 15,2`), Alícuotas (`DECIMAL 10,6`), Variables de Días (`DECIMAL 10,2`) y Totales.

---

## 6. Hoja de Ruta y Próximos Pasos (Fase 2)

El sistema ha sido estructurado para ser fácilmente escalable. Las futuras implementaciones sugeridas son:

1. **Módulo de Seguridad (Autenticación):** 
   - Pantalla de Login.
   - Roles y Permisos (Ej. Analista RRHH vs. Administrador / Gerente).
2. **Generación Automática de PDF:** 
   - Migrar la impresión directa del navegador usando librerías como `Dompdf` o `TCPDF` para forzar descargas encriptadas/firmadas.
3. **Dashboards Avanzados:** 
   - Gráficos de tendencias de pagos mediante librerías como Chart.js.
4. **Respaldo de Base de Datos:** 
   - Script CRON que ejecute respaldos (dumps) automáticos a nivel servidor.

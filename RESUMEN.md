# Resumen Ejecutivo del Sistema - DISTAL S.A.

## 1. ¿Qué es el Sistema?
Es una aplicación web empresarial diseñada para digitalizar, centralizar y automatizar el cálculo de liquidaciones y prestaciones sociales del personal de la **Distribuidora Trujillana de Alimentos (DISTAL) S.A.**, sustituyendo plantillas manuales de Excel por un sistema estandarizado y seguro adaptado a la legislación laboral venezolana (**LOTTT**).

---

## 2. Stack Tecnológico

| Capa | Tecnologías |
| :--- | :--- |
| **Backend** | **PHP 8.x** (Nativo / Modular, orientado a objetos, sin dependencias pesadas). |
| **Base de Datos** | **MySQL / MariaDB** (Conexión PDO con sentencias preparadas contra inyección SQL). |
| **Frontend UI** | **HTML5** + **Tailwind CSS** + **FontAwesome** (Diseño moderno, responsivo y adaptado para impresión). |
| **Lógica Frontend** | **JavaScript (ES6)** (Cálculos reactivos y validaciones instantáneas en pantalla). |
| **Integración & Salida** | Exportador nativo a **Excel**, vistas de impresión/PDF oficial y scripts de automatización en batch (`.bat`). |

---

## 3. ¿Qué hace el Sistema? (Funcionalidades Clave)

1. **Gestión de Personal (CRUD Empleados):**
   * Registro y administración del expediente laboral (cédula, nombres, cargos, escalafones y fecha de ingreso).

2. **Motor de Cálculo Legal (LOTTT):**
   * **Tiempo de servicio:** Años, meses y días bajo base comercial (360 días).
   * **Salarios & Alícuotas:** Cálculo automático de sueldo normal, alícuotas de utilidades/vacaciones y salario integral.
   * **Liquidación de Prestaciones:** Determinación de utilidades fraccionadas (Arts. 131/136), vacaciones y bono vacacional (Arts. 190/192/195/196) e indemnización de antigüedad (Art. 142c).
   * **Deducciones & Neto:** Retenciones parafiscales (SSO, RPE, LPH, etc.) y total neto a liquidar con validación antifallo (sin saldo negativo).

3. **Catálogo e Impresión de Planillas:**
   * Generación de hojas de liquidación individuales idénticas al formato oficial impreso, listas para exportar a PDF o firmar.

4. **Gestión de Lotes y Exportación:**
   * Selección múltiple y consolidada para exportar liquidaciones masivas directamente a archivos de Excel.

5. **Configuración de Parámetros Legales:**
   * Panel para ajustar días base de utilidades, vacaciones y tasas de retención sin tocar el código fuente.

# Planning completo — Sistema de generación y cálculo de planillas

**Proyecto:** Migración funcional de planillas Excel a sistema web existente  
**Stack actual:** PHP + MySQL + Tailwind CSS  
**Fecha del análisis:** 2026-08-14  
**Fuentes analizadas:** libro Excel legado `.xls`, imagen de una planilla de prestaciones sociales y captura de WhatsApp con correcciones funcionales.

---

## 1. Objetivo del proyecto

El objetivo **no es crear un clon de Excel**.

La plataforma debe convertir la lógica que hoy está distribuida entre hojas de Excel, fórmulas, valores manuales y conocimiento operativo de los usuarios en un flujo de trabajo web simple:

1. Seleccionar qué hoja o cálculo se desea generar.
2. Seleccionar una persona existente o registrar una nueva.
3. Reutilizar automáticamente todos los datos que ya existan de esa persona.
4. Solicitar únicamente los datos faltantes o variables necesarios para ese cálculo.
5. Ejecutar los cálculos automáticamente.
6. Mostrar una vista previa con un formato similar a la planilla institucional.
7. Permitir guardar, imprimir, generar PDF y, cuando sea necesario, exportar nuevamente a Excel.
8. Conservar un historial de todas las hojas generadas para cada persona.
9. Permitir corregir/recalcular sin perder la trazabilidad de la versión anterior.
10. Permitir generar varias hojas y un resumen consolidado sin tener que repetir datos.

El sistema debe priorizar en esta primera fase:

- funcionalidad;
- rapidez de captura;
- exactitud de cálculos;
- reutilización de datos;
- visualización e impresión;
- trazabilidad.

La seguridad avanzada, permisos muy detallados, auditoría criptográfica, colas, microservicios u otra orquestación compleja pueden quedar para una fase posterior.

---

# 2. Resultado del análisis del Excel

## 2.1 Hojas encontradas

El archivo contiene **15 hojas**:

- `1`
- `2`
- `3`
- `4`
- `5`
- `6`
- `7`
- `8`
- `9`
- `10`
- `11`
- `12`
- `13`
- `14`
- `resumen`

### Conclusión importante

Las hojas `1` a `14` **no son catorce tipos de cálculo diferentes**.

Son esencialmente catorce instancias/copias de la misma planilla de:

> **Cálculo de Prestaciones Sociales**

La hoja `resumen` toma datos de esas catorce hojas y construye un consolidado con:

- N.º
- Cédula
- Nombres y apellidos
- Cargo
- Monto
- Total de prestaciones

Por lo tanto, en el sistema **no conviene crear catorce pantallas diferentes**.

Se debe implementar:

1. una plantilla lógica `Prestaciones Sociales`;
2. tantas instancias de cálculo como personas sean necesarias;
3. un módulo `Resumen / Lote` que pueda consolidar cualquier cantidad de cálculos.

De esta forma no se conserva artificialmente el límite de catorce personas del Excel.

---

# 3. Estructura funcional detectada en las hojas 1–14

La planilla contiene las siguientes secciones.

## 3.1 Identificación

- Fecha del cálculo
- Cargo
- Clase de cargo
- Apellidos y nombres
- Nivel
- Cédula de identidad
- Categoría
- Motivo

## 3.2 Datos laborales

- Fecha de ingreso
- Fecha de egreso
- Años de servicio
- Meses de servicio
- Días de servicio
- Tiempo de servicio en meses

## 3.3 Componentes salariales

- Sueldo base mensual
- Salario base diario
- Prima de antigüedad
- Prima de hijos
- Prima por transporte
- Prima de profesionalización
- En una de las hojas aparece `Bono Nocturno` en lugar de prima de profesionalización
- Sueldo mensual normal
- Sueldo diario normal
- Salario diario integral
- Salario integral mensual

## 3.4 Alícuotas

- Días de utilidad
- Alícuota de utilidad
- Días de vacaciones
- Alícuota de vacaciones

## 3.5 Asignaciones principales

- Utilidades o bono de fin de año fraccionadas
- Fracción de bono vacacional / vacaciones
- Disfrute de vacaciones
- Antigüedad
- Otras asignaciones

## 3.6 Deducciones

- Depósito bancario / fideicomiso
- F.A.O.V.
- I.V.S.S.
- INCES
- Total deducciones

## 3.7 Resultado

- Subtotal de asignaciones
- Total asignaciones
- Total deducciones
- Subtotal a pagar
- Total a pagar
- Recibí conforme
- Firmas

---

# 4. Fórmulas detectadas en el Excel legado

El libro utiliza principalmente cuatro funciones de Excel:

- `TODAY`
- `DATEDIF`
- `SUM`
- `ROUND`

El motor del sistema deberá reproducir el comportamiento de esas operaciones sin depender de Excel.

---

## 4.1 Fecha del cálculo

Excel:

```text
=TODAY()
```

### Implementación recomendada

En el sistema no debe conservarse como una fecha dinámica.

Al crear el cálculo se almacenará:

```text
fecha_calculo
```

como un valor fijo dentro del cálculo.

Esto es importante porque si una hoja se vuelve a abrir seis meses después, su fecha histórica no debe cambiar.

---

## 4.2 Salario base diario

Excel:

```text
salario_base_diario = sueldo_base_mensual / 30
```

---

## 4.3 Tiempo de servicio

Excel:

```text
años = DATEDIF(fecha_ingreso, fecha_egreso, "y")
meses = DATEDIF(fecha_ingreso, fecha_egreso, "ym")
dias = DATEDIF(fecha_ingreso, fecha_egreso, "md")
```

Y:

```text
tiempo_servicio_meses = años * 12 + meses
```

### Atención técnica

No se debe asumir que `DateTime::diff()` de PHP será idéntico a `DATEDIF()` de Excel en todos los casos.

Debe implementarse una capa de compatibilidad y probar:

- meses con 28, 29, 30 y 31 días;
- años bisiestos;
- ingresos en fin de mes;
- egresos en fin de mes.

---

## 4.4 Sueldo mensual normal

En la mayoría de las hojas:

```text
sueldo_normal_mensual =
    sueldo_base_mensual
    + prima_antiguedad
    + prima_hijos
    + prima_transporte
    + prima_profesionalizacion_o_bono
```

### Inconsistencia encontrada

En la hoja `1` existe una fórmula que no incorpora el campo equivalente a `G21`, mientras que las hojas `2` a `14` sí lo incorporan.

Esto demuestra que **no se debe copiar una sola fórmula de una sola hoja y asumir que es correcta**.

Se necesita una regla oficial única para el sistema.

---

## 4.5 Sueldo normal diario

```text
sueldo_normal_diario = sueldo_normal_mensual / 30
```

---

## 4.6 Alícuota de utilidad — fórmula encontrada en el Excel legado

El Excel viejo contiene:

```text
alicuota_utilidad_mensual =
    sueldo_normal_mensual * 120 / 360
```

Y posteriormente:

```text
alicuota_utilidad_diaria =
    alicuota_utilidad_mensual / 30
```

**Este valor `120` está marcado como incorrecto para el caso señalado en las imágenes/mensajes.**

No se debe dejar fijo en código.

---

## 4.7 Alícuota de vacaciones — fórmula encontrada en el Excel legado

El Excel viejo contiene:

```text
alicuota_vacaciones_mensual =
    (sueldo_normal_mensual / 30) * 180 / 12
```

Y:

```text
alicuota_vacaciones_diaria =
    alicuota_vacaciones_mensual / 30
```

**El valor `180` también está marcado como incorrecto para el caso señalado en los mensajes.**

---

## 4.8 Salario diario integral

La lógica del libro viejo equivale a:

```text
salario_diario_integral =
    (
        sueldo_normal_mensual
        + alicuota_utilidad_mensual
        + alicuota_vacaciones_mensual
    ) / 30
```

---

## 4.9 Utilidades o bono de fin de año fraccionadas

En el libro se calcula una base similar a:

```text
base_utilidades_fraccionadas =
    (sueldo_normal_mensual + alicuota_vacaciones_mensual) / 30
```

Y luego:

```text
total_utilidades_fraccionadas =
    base_utilidades_fraccionadas * dias_utilidades_fraccionadas
```

Los días utilizados deben salir de una regla de negocio o de un dato aprobado para el cálculo, no de una celda escondida.

---

## 4.10 Vacaciones / bono vacacional

El Excel contiene distintas filas para los artículos 190/192, 195 y 196.

Las fórmulas detectadas utilizan:

```text
base_bono_vacacional =
    (sueldo_normal_mensual + alicuota_utilidad_mensual) / 30
```

Y:

```text
total_bono_vacacional =
    base_bono_vacacional * dias_correspondientes
```

Para otras filas:

```text
total_disfrute_vacaciones =
    sueldo_normal_diario * dias_correspondientes
```

Los días aparecen como datos variables/manuales en el archivo y deben convertirse en reglas explícitas en el sistema.

---

## 4.11 Antigüedad

En el Excel viejo:

```text
salario_integral_mensual =
    sueldo_normal_mensual
    + alicuota_utilidad_mensual
    + alicuota_vacaciones_mensual
```

La hoja utiliza también el tiempo de servicio en meses y un valor de años/días para obtener el monto.

En la planilla impresa de la imagen la sección aparece conceptualmente como:

- Nro. días
- Nro. meses
- Años
- Salario integral diario
- Monto total

Por lo tanto, el sistema debe modelar estos conceptos de manera explícita y no conservar una fórmula confusa únicamente porque matemáticamente produzca el mismo resultado en determinados ejemplos.

---

## 4.12 Totales

El Excel tiene fórmulas equivalentes a:

```text
subtotal_asignaciones =
    utilidades_fraccionadas
    + bono_vacacional
    + vacaciones_195
    + vacaciones_196
    + antiguedad
```

Luego:

```text
total_asignaciones =
    subtotal_asignaciones
    + otras_asignaciones
    + otros_conceptos
    + intereses_antiguedad
```

Deducciones:

```text
total_deducciones =
    deposito_fideicomiso
    + faov
    + ivss
    + inces
```

Resultado:

```text
subtotal_pagar =
    ROUND(total_asignaciones - total_deducciones, 2)

total_pagar = subtotal_pagar
```

También existe un `ROUND(..., 2)` en el subtotal de asignaciones.

---

# 5. Correcciones funcionales detectadas en las imágenes/mensajes

La captura de WhatsApp introduce reglas que contradicen los valores fijos del Excel viejo.

Estas correcciones deben tratarse como **reglas de negocio**, no como simples cambios visuales.

---

## 5.1 Utilidades / aguinaldos

Mensaje recibido:

> Los días de utilidad son 30 y no 120.

También se indica:

> Los aguinaldos o utilidades son constantes en 30.

### Regla propuesta

```text
dias_utilidad = 30
```

para el perfil/regla correspondiente.

No debe sustituirse `120` por `30` directamente en todas las fórmulas sin comprobar primero cómo participa ese valor en cada alícuota.

Se debe homologar el resultado esperado mediante casos reales aprobados.

---

## 5.2 Vacaciones según antigüedad

Mensaje recibido:

- 1 año = 15 días
- aumenta sucesivamente
- 16 años = 30 días
- no utilizar 180
- para 1 año y 8 meses se toma como 2 años
- después de 6 meses se toma el año siguiente
- en el ejemplo corresponderían 16 días

### Regla funcional propuesta

```text
años_base = años_completos

si meses_excedentes > 6:
    años_vacaciones = años_base + 1
si no:
    años_vacaciones = años_base
```

Luego:

```text
dias_vacaciones =
    15 + (años_vacaciones - 1)
```

con límites:

```text
mínimo: 15
máximo: 30
```

Ejemplos:

| Antigüedad utilizada | Días |
|---:|---:|
| 1 año | 15 |
| 2 años | 16 |
| 3 años | 17 |
| 4 años | 18 |
| ... | ... |
| 16 años o más | 30 |

### Punto que debe quedar configurable

El mensaje dice **“después de 6 meses”**.

Por ello hay que confirmar si:

```text
meses > 6
```

o:

```text
meses >= 6
```

es la regla definitiva.

No conviene enterrar esta decisión dentro del código.

---

## 5.3 Plantillas de 120 / 180

También se aclara en el mensaje que:

> la plantilla de 120 y 180 se usa en empresa pública.

Esto sugiere que el sistema debe admitir **perfiles de cálculo/versiones de reglas**.

Ejemplo:

```text
Perfil A:
LEGADO_PUBLICA_120_180

Perfil B:
UTILIDAD_30_VACACIONES_POR_ANTIGUEDAD
```

El nombre definitivo puede cambiar, pero la arquitectura debe permitir esta separación.

---

# 6. Diferencias entre el Excel y la planilla de la imagen

La imagen impresa no coincide completamente con las fórmulas del `.xls`.

Se observan diferencias como:

- encabezado institucional distinto;
- valores de alícuotas que no parecen corresponder exactamente a las fórmulas del libro viejo;
- sección de antigüedad presentada con salario integral diario;
- estructura visual actualizada;
- correcciones posteriores sobre los valores 120/180.

### Consecuencia

El `.xls` debe utilizarse como:

> **base de ingeniería inversa**

pero no como única fuente incuestionable de verdad.

---

# 7. Orden recomendado para resolver contradicciones

Cuando Excel, una planilla impresa y una corrección del responsable no coincidan, utilizar el siguiente proceso:

1. registrar la discrepancia;
2. construir un caso de prueba real;
3. obtener el resultado que el responsable considera correcto;
4. documentar la fórmula final;
5. crear una versión de regla;
6. bloquear esa versión una vez aprobada.

Para la implementación inicial recomiendo considerar como fuentes:

1. **regla/corrección aprobada por el responsable funcional**;
2. **casos reales impresos ya validados**;
3. **Excel legado** como referencia para completar la lógica no documentada.

---

# 8. Arquitectura funcional propuesta

La plataforma puede continuar dentro del proyecto PHP/MySQL/Tailwind existente.

No hace falta cambiar el stack.

Se agregará una capa funcional clara.

```text
Personas
   ↓
Selección de cálculo
   ↓
Carga / reutilización de datos
   ↓
Perfil de reglas
   ↓
Motor de cálculo
   ↓
Snapshot de resultados
   ↓
Vista de planilla
   ↓
Impresión / PDF / Excel
   ↓
Historial y resumen
```

---

# 9. Módulos que debe tener el sistema

## 9.1 Personas

Debe existir una ficha única por persona.

Datos sugeridos:

- cédula;
- nombres;
- apellidos;
- nombre completo;
- cargo;
- clase de cargo;
- nivel;
- categoría;
- fecha de ingreso;
- estado;
- observaciones;
- última información salarial conocida.

Acciones principales:

- buscar;
- registrar;
- editar;
- abrir historial;
- generar nueva hoja.

---

## 9.2 Tipos de hoja / cálculos

Pantalla de selección rápida:

```text
¿Qué desea generar?
```

Inicialmente:

- Prestaciones Sociales
- Resumen de Prestaciones

Y debe quedar preparada para incorporar posteriormente cualquier otra plantilla/hoja que aparezca en otros Excel.

---

## 9.3 Parámetros / variables

Pantalla simple para mantener valores que no deberían modificarse en código.

Ejemplos:

- divisor salario mensual;
- días de utilidad;
- tabla de días de vacaciones;
- límite de vacaciones;
- regla de redondeo por meses;
- conceptos salariales habilitados;
- días por antigüedad;
- porcentaje o fórmula de conceptos futuros;
- etiquetas institucionales;
- datos para firmas.

El usuario autorizado debería poder actualizar estos valores desde una interfaz sencilla.

---

## 9.4 Motor de cálculo

Debe ser independiente de las vistas.

Ejemplo conceptual:

```text
PrestacionesCalculator
```

Recibe:

```text
persona
datos_laborales
datos_salariales
datos_variables
perfil_reglas
fecha_calculo
```

Devuelve:

```text
tiempo_servicio
salarios
alicuotas
vacaciones
utilidades
antiguedad
asignaciones
deducciones
totales
```

---

## 9.5 Historial / trazabilidad

Dentro de cada persona:

```text
Cálculos generados
```

Ejemplo:

| Fecha | Tipo | Motivo | Regla | Total | Estado |
|---|---|---|---|---:|---|
| 27/04/2026 | Prestaciones | Renuncia | v1 | 3.751,14 | Finalizado |
| 14/08/2026 | Prestaciones | Corrección | v2 | ... | Borrador |

Cada cálculo debe poder abrirse nuevamente exactamente como se generó.

---

## 9.6 Resumen / lotes

El `resumen` del Excel debe convertirse en una función dinámica.

Flujo:

1. Crear lote.
2. Seleccionar personas/cálculos.
3. Mostrar tabla.
4. Calcular total.
5. Imprimir/exportar.

No debe existir un límite de 14 filas.

---

## 9.7 Impresión y documentos

Acciones:

- Ver hoja
- Imprimir
- Descargar PDF
- Exportar Excel
- Duplicar/Recalcular

La hoja imprimible debe parecerse a la planilla institucional, pero la pantalla de captura **no** debe parecer una cuadrícula de Excel.

---

# 10. Flujo de usuario recomendado

## 10.1 Camino A — iniciar desde “Generar hoja”

### Paso 1

Botón:

```text
+ Generar hoja
```

### Paso 2

Seleccionar:

```text
Prestaciones Sociales
```

### Paso 3

Buscar persona por:

- cédula;
- nombre;
- apellido.

Opciones:

```text
Seleccionar existente
```

o:

```text
Registrar nueva persona
```

### Paso 4

El sistema precarga los datos existentes.

### Paso 5

Mostrar exclusivamente:

- datos faltantes;
- datos que pueden variar para este cálculo;
- parámetros que el operador deba confirmar.

### Paso 6

Botón:

```text
Calcular
```

### Paso 7

Mostrar:

- resumen de resultado;
- desglose;
- planilla completa.

### Paso 8

Acciones:

```text
Guardar borrador
Finalizar
Imprimir
PDF
Excel
```

---

# 11. Flujo alternativo desde la persona

Dentro de la ficha:

```text
PINFA QUEVEDO ANTHONY
```

mostrar:

```text
[ Generar nueva hoja ]
```

Al pulsarlo:

1. elegir tipo de cálculo;
2. reutilizar automáticamente persona/cédula/cargo/nivel/categoría/fecha de ingreso;
3. mostrar solamente información faltante;
4. generar.

Esto cumple el objetivo de no volver a escribir la misma información cada vez.

---

# 12. Datos permanentes vs. datos del cálculo

Esta separación es fundamental.

## 12.1 Datos permanentes o reutilizables de persona

Ejemplos:

- cédula;
- nombres;
- apellidos;
- cargo;
- clase;
- nivel;
- categoría;
- fecha de ingreso.

## 12.2 Datos que pertenecen al cálculo

Ejemplos:

- fecha de egreso;
- motivo;
- fecha de cálculo;
- sueldo base usado;
- primas usadas;
- otras asignaciones;
- deducciones;
- perfil de reglas;
- días particulares;
- observaciones.

Aunque algunos valores ya estén en la ficha de la persona, el cálculo debe guardar una **copia histórica**.

Si mañana cambia el cargo o salario de la persona, el cálculo viejo no puede cambiar.

---

# 13. Modelo de datos MySQL propuesto

Los nombres pueden adaptarse a las convenciones existentes.

---

## 13.1 `persons`

```text
id
cedula
first_names
last_names
full_name
position
position_class
level
category
hire_date
status
notes
created_at
updated_at
```

---

## 13.2 `calculation_templates`

```text
id
code
name
description
active
created_at
updated_at
```

Ejemplo:

```text
PRESTACIONES_SOCIALES
```

---

## 13.3 `calculation_rule_profiles`

```text
id
code
name
version
description
active
valid_from
valid_to
config_json
created_at
updated_at
```

Ejemplos:

```text
LEGADO_PUBLICA_120_180
PRESTACIONES_30_VACACIONES_ANTIGUEDAD
```

---

## 13.4 `calculations`

```text
id
person_id
template_id
rule_profile_id
parent_calculation_id
calculation_number
calculation_date
status
reason
inputs_json
results_json
total_assignments
total_deductions
total_payable
created_by
created_at
updated_at
finalized_at
```

Estados iniciales:

```text
draft
finalized
cancelled
superseded
```

---

## 13.5 `calculation_documents`

```text
id
calculation_id
type
version
path
created_at
```

Tipos:

```text
pdf
xlsx
html_snapshot
```

---

## 13.6 `calculation_batches`

```text
id
name
description
status
created_at
```

---

## 13.7 `calculation_batch_items`

```text
id
batch_id
calculation_id
position
```

---

## 13.8 Parámetros opcionales

Si se necesita una administración más estructurada:

```text
calculation_parameters
calculation_parameter_values
```

Pero para la primera versión puede utilizarse:

```text
config_json
```

versionado por perfil.

---

# 14. Por qué guardar `inputs_json` y `results_json`

No se recomienda recalcular una hoja histórica usando los datos actuales de las tablas.

Cada cálculo debe almacenar una fotografía completa.

Ejemplo:

```json
{
  "employee": {
    "cedula": "29.825.522",
    "name": "PINFA QUEVEDO ANTHONY",
    "position": "ASIST. ADMINISTRATIVO I"
  },
  "employment": {
    "hire_date": "2024-01-01",
    "termination_date": "2025-09-09",
    "reason": "RENUNCIA"
  },
  "salary": {
    "base_monthly": "130.00",
    "seniority_bonus": "1.30",
    "children_bonus": "0.00",
    "transport_bonus": "130.00",
    "professional_bonus": "0.00"
  }
}
```

Los resultados también se congelan.

Esto permite reproducir exactamente una hoja aunque después:

- cambie el salario;
- cambie el cargo;
- cambie la regla;
- cambie la plantilla.

---

# 15. Precisión numérica

Para lograr paridad con Excel:

## MySQL

Usar:

```text
DECIMAL
```

y no `FLOAT`.

Ejemplo:

```text
DECIMAL(18,6)
```

para cálculos internos.

## PHP

Evitar depender únicamente de `float` si se desea equivalencia centavo a centavo.

Opciones:

- BCMath;
- una clase Decimal;
- cálculo con enteros escalados cuando aplique.

### Regla importante

No redondear todos los pasos a 2 decimales.

El Excel puede:

- mostrar `4,36`;
- mantener internamente más decimales;
- redondear solamente en determinadas fórmulas.

Se debe:

1. calcular con precisión interna;
2. aplicar `ROUND` únicamente en los puntos aprobados;
3. formatear a 2 decimales en pantalla/impresión.

---

# 16. Diseño de clases PHP sugerido

```text
app/
  Calculations/
    Contracts/
      CalculatorInterface.php

    DTO/
      PrestacionesInput.php
      PrestacionesResult.php

    Engines/
      PrestacionesCalculator.php

    Rules/
      RuleProfileResolver.php
      VacationDaysRule.php
      UtilityDaysRule.php
      SeniorityRule.php

    Support/
      ExcelDateDiffCompat.php
      Decimal.php
      CalculationFormatter.php

    Services/
      CalculationService.php
      CalculationSnapshotService.php
      CalculationBatchService.php
      CalculationExportService.php
```

La ubicación puede adaptarse al framework o estructura que ya tenga el proyecto.

---

# 17. Ejemplo de contrato del motor

Conceptualmente:

```php
$result = $prestacionesCalculator->calculate(
    input: $input,
    rules: $ruleProfile
);
```

El resultado no debería depender directamente de:

- `$_POST`;
- HTML;
- sesión;
- base de datos;
- Tailwind.

Eso facilita probar el motor de cálculo de forma aislada.

---

# 18. Campos que el formulario debería solicitar

## Identificación

- Cédula
- Apellidos
- Nombres
- Cargo
- Clase de cargo
- Nivel
- Categoría

## Relación laboral

- Motivo
- Fecha de ingreso
- Fecha de egreso
- Fecha del cálculo

## Salario

- Sueldo base mensual
- Prima de antigüedad
- Prima de hijos
- Prima de transporte
- Prima de profesionalización
- Bono nocturno, cuando aplique
- Otros conceptos salariales habilitados

## Variables del cálculo

Solo si la regla no las obtiene automáticamente:

- días de utilidades fraccionadas;
- días de bono vacacional;
- días de disfrute;
- días de antigüedad;
- otras asignaciones;
- intereses por antigüedad.

## Deducciones

- fideicomiso;
- FAOV;
- IVSS;
- INCES;
- otras deducciones futuras.

---

# 19. Campos que NO debería pedir el formulario

No solicitar manualmente datos que puedan calcularse:

- salario base diario;
- años de servicio;
- meses de servicio;
- días de servicio;
- tiempo de servicio en meses;
- sueldo normal mensual;
- sueldo normal diario;
- alícuotas;
- salario integral;
- subtotales;
- totales.

Deben mostrarse como resultados calculados.

---

# 20. UX recomendada con Tailwind

Mantener el estilo visual actual del sistema.

No intentar representar celdas de Excel durante la captura.

## Pantalla de formulario

Secciones mediante cards:

```text
[ Persona ]
[ Relación laboral ]
[ Componentes salariales ]
[ Variables del cálculo ]
[ Deducciones ]
```

## Lateral o parte superior

Resumen vivo:

```text
Sueldo normal
Salario integral
Tiempo de servicio
Asignaciones
Deducciones
Total a pagar
```

## Acciones

```text
Guardar borrador
Calcular
Finalizar
Ver planilla
```

---

# 21. Vista de planilla

La vista final sí debe reproducir el diseño institucional.

Debe ser una plantilla HTML de impresión independiente del formulario.

Ejemplo:

```text
resources/views/calculations/prestaciones/print.php
```

o equivalente.

Debe contener:

- encabezado;
- identificación;
- datos laborales;
- cálculo;
- asignaciones;
- deducciones;
- total;
- firmas.

Con CSS específico:

```css
@media print { ... }
```

---

# 22. Exportación a Excel

Aunque el sistema no sea un Excel, conviene permitir exportación cuando sea necesaria.

El exportador debe generar:

```text
Hoja 1
Hoja 2
...
Resumen
```

si se exporta un lote.

Pero internamente esas hojas serán generadas a partir de cálculos almacenados, no de archivos Excel modificados manualmente.

---

# 23. Trazabilidad mínima obligatoria

Aunque la seguridad no sea la prioridad actual, la trazabilidad sí forma parte del funcionamiento.

Cada cálculo debe saber:

- quién es la persona;
- qué plantilla se utilizó;
- qué versión de reglas se utilizó;
- cuáles fueron los datos de entrada;
- qué resultados produjo;
- cuándo fue creado;
- cuándo fue finalizado;
- si proviene de un cálculo anterior;
- si fue reemplazado por otro.

---

# 24. Recalcular sin perder la versión anterior

No editar destructivamente un cálculo finalizado.

Botón:

```text
Recalcular / Crear corrección
```

Proceso:

```text
Cálculo #125
      ↓
Duplicar datos
      ↓
Cálculo #132
parent_calculation_id = 125
```

Así se conserva la historia completa.

---

# 25. Anomalías encontradas en el Excel que deben convertirse en casos de prueba

## 25.1 Hoja 1

La fórmula del sueldo normal usada en una celda no incluye uno de los conceptos que sí está incluido en las hojas 2–14.

## 25.2 Hoja 4

Las celdas correspondientes a años/meses de servicio contienen valores manuales en lugar de las fórmulas `DATEDIF` usadas en otras hojas.

## 25.3 Hojas 3 y 5

Existen celdas de la sección de antigüedad/subtotal que no tienen las mismas fórmulas que el resto de las hojas.

## 25.4 Hoja 1

Existe una fórmula aislada fuera de la estructura principal (`H75`), que no debe trasladarse automáticamente al sistema.

### Conclusión

La migración debe hacerse por **conceptos y reglas**, no copiando literalmente cada celda.

---

# 26. Hoja `resumen`

Actualmente contiene referencias directas como:

```text
'1'!D12
'1'!D11
'1'!D10
'1'!G58
```

y lo mismo para las hojas 2–14.

En el sistema será una consulta:

```sql
SELECT
    person.cedula,
    person.full_name,
    calculation.snapshot_position,
    calculation.total_payable
FROM calculations
...
```

La lógica ya no dependerá de posiciones de celdas.

---

# 27. Plan de implementación por fases

---

## Fase 0 — Homologación del cálculo

### Objetivo

Definir qué resultado es considerado correcto antes de programar.

### Tareas

- [ ] Crear inventario de fórmulas del Excel.
- [ ] Marcar fórmulas que contienen `120`.
- [ ] Marcar fórmulas que contienen `180`.
- [ ] Documentar la regla de utilidad de 30 días.
- [ ] Documentar la tabla de vacaciones 15–30.
- [ ] Confirmar regla exacta para fracciones mayores a 6 meses.
- [ ] Confirmar fórmula de alícuota con los días corregidos.
- [ ] Confirmar forma exacta de antigüedad.
- [ ] Seleccionar 5–10 cálculos reales aprobados como casos patrón.
- [ ] Registrar resultado esperado de cada concepto y total.

### Entregable

```text
Matriz de reglas v1
+
Casos patrón / golden cases
```

---

## Fase 1 — Modelo de personas y datos reutilizables

### Tareas

- [ ] Revisar tabla de personas existente.
- [ ] Agregar únicamente campos faltantes.
- [ ] Crear buscador por cédula/nombre.
- [ ] Implementar ficha de persona.
- [ ] Botón `Generar hoja`.
- [ ] Reutilizar cargo/nivel/categoría/fecha de ingreso.
- [ ] Permitir actualización rápida de datos.

### Resultado

Ya no se vuelve a escribir la misma información en cada cálculo.

---

## Fase 2 — Modelo de cálculos y snapshots

### Tareas

- [ ] Crear `calculation_templates`.
- [ ] Crear `calculation_rule_profiles`.
- [ ] Crear `calculations`.
- [ ] Guardar `inputs_json`.
- [ ] Guardar `results_json`.
- [ ] Implementar estados.
- [ ] Implementar duplicación/revisión.
- [ ] Historial por persona.

---

## Fase 3 — Motor `PrestacionesCalculator`

### Tareas

- [ ] Implementar cálculo de fechas compatible con Excel.
- [ ] Implementar salarios.
- [ ] Implementar primas.
- [ ] Implementar salario normal.
- [ ] Implementar reglas de utilidades.
- [ ] Implementar vacaciones por antigüedad.
- [ ] Implementar bono/disfrute.
- [ ] Implementar antigüedad.
- [ ] Implementar otras asignaciones.
- [ ] Implementar deducciones.
- [ ] Implementar subtotales.
- [ ] Implementar total.
- [ ] Centralizar redondeos.
- [ ] Pruebas unitarias.

---

## Fase 4 — Formulario rápido / wizard

### Tareas

- [ ] Selección de tipo de hoja.
- [ ] Selección/registro de persona.
- [ ] Autocompletado.
- [ ] Mostrar datos faltantes.
- [ ] Secciones visuales Tailwind.
- [ ] Validaciones básicas.
- [ ] Botón calcular.
- [ ] Resumen en tiempo real o al enviar.
- [ ] Guardar borrador.

---

## Fase 5 — Vista de planilla e impresión

### Tareas

- [ ] Crear plantilla visual.
- [ ] Adaptar encabezado institucional actual.
- [ ] Replicar estructura de la hoja impresa.
- [ ] Formatear montos con 2 decimales.
- [ ] Formatear fechas.
- [ ] CSS de impresión.
- [ ] PDF.
- [ ] Botón imprimir.
- [ ] Verificar saltos de página.

---

## Fase 6 — Historial y trazabilidad

### Tareas

- [ ] Listado por persona.
- [ ] Filtros por fecha/tipo/estado.
- [ ] Abrir cálculo histórico.
- [ ] Recalcular mediante nueva versión.
- [ ] Comparar versión anterior/nueva.
- [ ] Mostrar regla usada.

---

## Fase 7 — Lotes y `resumen`

### Tareas

- [ ] Crear lote.
- [ ] Seleccionar cálculos.
- [ ] Ordenar.
- [ ] Generar tabla resumen.
- [ ] Total general.
- [ ] Impresión.
- [ ] PDF.
- [ ] Excel.
- [ ] Cantidad ilimitada de personas.

---

## Fase 8 — Exportación Excel

### Tareas

- [ ] Generar libro desde el sistema.
- [ ] Una hoja por cálculo.
- [ ] Hoja final de resumen.
- [ ] Mantener campos y presentación necesarios.
- [ ] No depender del Excel original para funcionar.

---

## Fase 9 — Pruebas de paridad

Comparar:

```text
Excel / cálculo aprobado
vs.
Sistema
```

Concepto por concepto.

No limitar la prueba al total final.

Verificar:

```text
salario base diario
tiempo de servicio
salario normal
alícuota utilidad
alícuota vacaciones
utilidades
bono vacacional
disfrute
antigüedad
asignaciones
deducciones
total
```

---

# 28. Casos de prueba mínimos

## Fechas

- [ ] 1 año exacto.
- [ ] 1 año y 5 meses.
- [ ] 1 año y 6 meses.
- [ ] 1 año, 6 meses y 1 día.
- [ ] 1 año y 7 meses.
- [ ] 1 año y 8 meses.
- [ ] 15 años y 7 meses.
- [ ] 16 años.
- [ ] Más de 16 años.
- [ ] Febrero no bisiesto.
- [ ] Febrero bisiesto.
- [ ] Inicio/fin de mes.

## Salarios

- [ ] Solo sueldo base.
- [ ] Con prima de antigüedad.
- [ ] Con prima hijos.
- [ ] Con transporte.
- [ ] Con profesionalización.
- [ ] Con bono nocturno.
- [ ] Todos los conceptos.

## Reglas

- [ ] Perfil 120/180 legado.
- [ ] Perfil utilidad 30.
- [ ] Vacaciones de 15 días.
- [ ] Vacaciones de 16 días.
- [ ] Tope de 30 días.

## Deducciones

- [ ] Sin deducciones.
- [ ] Una deducción.
- [ ] Todas.
- [ ] Otras deducciones futuras.

---

# 29. Caso patrón de la imagen

La imagen suministrada debe convertirse en uno de los casos de homologación.

Datos observados, entre otros:

```text
Motivo: RENUNCIA
Fecha ingreso: 01/01/2024
Fecha egreso: 09/09/2025
Tiempo mostrado: 1 año, 8 meses, 8 días
Tiempo en meses: 20
Sueldo base mensual: 130,00
Prima antigüedad: 1,30
Prima transporte: 130,00
Sueldo normal mensual: 261,30
Sueldo normal diario: 8,71
```

La misma imagen muestra un total de:

```text
3.751,14
```

pero contiene la lógica 120/180 que posteriormente fue cuestionada.

### Uso correcto del caso

Se deben guardar dos expectativas cuando corresponda:

```text
resultado legado
```

y:

```text
resultado corregido
```

De esa manera se puede demostrar exactamente qué cambió al aplicar la nueva regla.

---

# 30. Estrategia para no romper el sistema existente

No realizar una reescritura completa.

Implementar de forma incremental.

## Paso 1

Mantener:

- layout;
- navbar;
- sidebar;
- colores;
- componentes;
- autenticación existente;
- estructura general.

## Paso 2

Agregar rutas y pantallas nuevas.

Ejemplo:

```text
/personas
/personas/{id}
/calculos/nuevo
/calculos/{id}
/calculos/{id}/imprimir
/lotes
/parametros/calculos
```

## Paso 3

Conectar al modelo actual de usuarios/personas cuando sea posible.

## Paso 4

Evitar migraciones destructivas.

---

# 31. Prioridad de desarrollo

## P0 — indispensable

- Motor de cálculo
- Personas
- Reutilización de datos
- Crear cálculo
- Guardar cálculo
- Historial
- Planilla
- Impresión
- Total correcto
- Reglas configurables
- Resumen

## P1 — muy conveniente

- PDF
- Exportación Excel
- Comparación de revisiones
- Duplicar cálculo
- Lotes
- Filtros

## P2 — después

- permisos finos;
- auditoría avanzada;
- firmas digitales;
- notificaciones;
- colas;
- APIs;
- automatizaciones externas;
- dashboards complejos.

---

# 32. Criterios de aceptación

La primera versión funcional se considerará lista cuando:

- [ ] Se pueda registrar una persona una sola vez.
- [ ] Se pueda seleccionar una persona ya registrada.
- [ ] Se pueda seleccionar el tipo de hoja antes o después de entrar en la ficha de la persona.
- [ ] El sistema precargue automáticamente los datos conocidos.
- [ ] Solo solicite datos faltantes o variables.
- [ ] El cálculo se realice sin utilizar Excel.
- [ ] Cada resultado pueda explicarse por concepto.
- [ ] Los casos patrón coincidan con el resultado aprobado al centavo.
- [ ] Sea posible guardar un borrador.
- [ ] Sea posible finalizar un cálculo.
- [ ] Un cálculo finalizado conserve sus datos históricos.
- [ ] Se pueda crear una corrección sin borrar el anterior.
- [ ] Se pueda visualizar la planilla.
- [ ] Se pueda imprimir correctamente.
- [ ] Se pueda generar PDF.
- [ ] Se pueda generar un resumen con varias personas.
- [ ] El resumen no tenga límite de 14 registros.
- [ ] Se pueda exportar un libro con las hojas cuando sea necesario.
- [ ] La interfaz mantenga el aspecto del sistema actual.
- [ ] La captura no se convierta en una cuadrícula tipo Excel.

---

# 33. Estimación orientativa por bloques

Esta estimación depende de cuánto del CRUD de personas, layouts, PDF y autenticación ya exista.

| Bloque | Esfuerzo orientativo |
|---|---:|
| Homologación y casos patrón | 1–3 días |
| Modelo de cálculos y perfiles | 2–3 días |
| Motor de prestaciones | 4–7 días |
| Wizard/formulario | 3–5 días |
| Historial/revisiones | 2–3 días |
| Vista imprimible/PDF | 2–4 días |
| Lotes/resumen | 2–4 días |
| Exportación Excel | 2–4 días |
| QA/paridad | 3–6 días |

Rango razonable:

```text
21–39 días de desarrollo
```

para una implementación completa y homologada, dependiendo del estado actual del proyecto.

Se puede tener una primera versión utilizable antes, trabajando por entregas.

---

# 34. Orden concreto recomendado para comenzar a programar

## Entrega 1

```text
Persona
+
Prestaciones
+
Motor de cálculo
+
Vista previa
```

## Entrega 2

```text
Guardar
+
Historial
+
Imprimir/PDF
```

## Entrega 3

```text
Reglas configurables
+
Revisión/corrección
```

## Entrega 4

```text
Lotes
+
Resumen
+
Excel
```

---

# 35. Decisiones que deben cerrarse durante la Fase 0

Estas decisiones no impiden comenzar la estructura del sistema, pero deben cerrarse antes de declarar el motor definitivo.

1. ¿Cuál perfil será el predeterminado?
2. ¿Cómo se transforma exactamente el valor de utilidad `30` dentro de la alícuota?
3. ¿Cómo se utiliza el valor de vacaciones `15–30` dentro de la alícuota?
4. ¿La fracción de más de 6 meses se redondea solo para vacaciones o para otros conceptos?
5. ¿Exactamente 6 meses redondea o solamente más de 6?
6. ¿Cuál es la regla definitiva de antigüedad: días × salario integral diario, años × salario integral mensual u otra equivalencia formal?
7. ¿Qué conceptos salariales forman parte del salario normal en todos los casos?
8. ¿Bono nocturno sustituye a profesionalización o pueden coexistir?
9. ¿Cuáles campos podrán forzarse manualmente en casos excepcionales?
10. ¿Qué campos deben aparecer en la impresión aunque no participen en la fórmula?

---

# 36. Regla para excepciones manuales

Debe existir la posibilidad de corregir determinados datos, pero nunca silenciosamente.

Ejemplo:

```text
Días de vacaciones calculados: 16
```

Botón:

```text
Usar valor manual
```

Si el operador escribe:

```text
17
```

guardar:

```text
valor_calculado = 16
valor_usado = 17
override = true
motivo_override = "..."
```

Esto evita repetir el problema del Excel donde algunas hojas contienen valores manuales y otras fórmulas sin que sea evidente.

---

# 37. Estructura del resultado del motor

Ejemplo conceptual:

```json
{
  "service_time": {
    "years": 1,
    "months": 8,
    "days": 8,
    "total_months": 20,
    "vacation_years": 2
  },
  "salary": {
    "base_monthly": "130.000000",
    "base_daily": "4.333333",
    "normal_monthly": "261.300000",
    "normal_daily": "8.710000"
  },
  "rules": {
    "utility_days": 30,
    "vacation_days": 16
  },
  "assignments": {},
  "deductions": {},
  "totals": {
    "assignments": "...",
    "deductions": "...",
    "payable": "..."
  }
}
```

La impresión solo presenta los campos necesarios, pero el sistema conserva el desglose completo.

---

# 38. Beneficio de esta arquitectura

Con este diseño, incorporar una nueva hoja deja de significar:

> copiar otro Excel.

Pasará a significar:

1. definir sus inputs;
2. definir sus reglas;
3. crear su calculador;
4. crear su plantilla visual;
5. agregar sus casos patrón.

Así la plataforma puede crecer de forma ordenada.

---

# 39. Resultado final esperado

La experiencia del usuario debe sentirse así:

```text
1. Selecciono "Prestaciones Sociales".
2. Busco la cédula.
3. El sistema ya conoce los datos de la persona.
4. Completo solamente egreso, sueldo/primas y variables que falten.
5. Pulso "Calcular".
6. Veo el total y el desglose.
7. Reviso la planilla.
8. Finalizo.
9. Imprimo o genero PDF.
10. El cálculo queda guardado en la ficha de la persona.
```

Si después necesita otra hoja:

```text
Persona → Generar hoja → Seleccionar hoja
```

sin volver a cargar todos los datos.

---

# 40. Conclusión

El Excel analizado debe migrarse como un **motor de cálculo versionado**, no como una cuadrícula.

La estructura correcta para el sistema es:

```text
Personas
+ Plantillas
+ Perfiles de reglas
+ Motor de cálculo
+ Snapshots
+ Historial
+ Vista imprimible
+ Lotes/Resumen
+ Exportadores
```

El archivo demuestra además que ya existen inconsistencias entre hojas y que las reglas 120/180 han sido corregidas verbalmente.

Por esto, el primer paso técnico debe ser crear una matriz de reglas y casos patrón aprobados; inmediatamente después se puede implementar `PrestacionesCalculator` y conectar el flujo a la interfaz PHP/MySQL/Tailwind existente.

La prioridad debe ser que el sistema:

- calcule igual que la regla aprobada;
- reduzca la captura manual;
- reutilice información;
- mantenga historial;
- imprima correctamente;
- permita crecer hacia las demás planillas sin repetir el problema del Excel.

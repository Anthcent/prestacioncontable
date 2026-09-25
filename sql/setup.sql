CREATE DATABASE IF NOT EXISTS sistema_distal;
USE sistema_distal;

-- Empleados
CREATE TABLE IF NOT EXISTS empleados (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cedula VARCHAR(20) UNIQUE NOT NULL,
  apellidos_nombres VARCHAR(200) NOT NULL,
  cargo VARCHAR(100),
  clase_cargo VARCHAR(50),
  nivel VARCHAR(50),
  categoria VARCHAR(50),
  fecha_ingreso DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cálculos de Prestaciones Sociales
CREATE TABLE IF NOT EXISTS prestaciones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  empleado_id INT,
  fecha_calculo DATE,
  fecha_egreso DATE,
  motivo VARCHAR(200),
  
  -- Salarios
  sueldo_base_mensual DECIMAL(15,2) DEFAULT 0.00,
  prima_antiguedad DECIMAL(15,2) DEFAULT 0.00,
  prima_hijos DECIMAL(15,2) DEFAULT 0.00,
  prima_transporte DECIMAL(15,2) DEFAULT 0.00,
  prima_profesionalizacion DECIMAL(15,2) DEFAULT 0.00,
  
  -- Calculados
  sueldo_mensual_normal DECIMAL(15,2) DEFAULT 0.00,
  sueldo_diario_normal DECIMAL(15,2) DEFAULT 0.00,
  salario_base_diario DECIMAL(15,2) DEFAULT 0.00,
  salario_diario_integral DECIMAL(15,2) DEFAULT 0.00,
  salario_integral_mensual DECIMAL(15,2) DEFAULT 0.00,
  
  -- Alícuotas
  dias_utilidades INT DEFAULT 30,
  alicuota_utilidades DECIMAL(10,6) DEFAULT 0.000000,
  dias_vacaciones_alicuota INT DEFAULT 15,
  alicuota_vacaciones DECIMAL(10,6) DEFAULT 0.000000,
  
  -- Utilidades
  util_alicuota DECIMAL(15,2) DEFAULT 0.00,
  util_dias DECIMAL(10,2) DEFAULT 0.00,
  util_salario_normal_vac DECIMAL(15,2) DEFAULT 0.00,
  util_total DECIMAL(15,2) DEFAULT 0.00,
  
  -- Vacaciones Art. 190/192
  vac190_alicuota DECIMAL(10,4) DEFAULT 0.0000,
  vac190_dias DECIMAL(10,2) DEFAULT 0.00,
  vac190_salario DECIMAL(15,2) DEFAULT 0.00,
  vac190_total DECIMAL(15,2) DEFAULT 0.00,
  
  -- Vacaciones Art. 195
  vac195_alicuota DECIMAL(10,4) DEFAULT 0.0000,
  vac195_dias DECIMAL(10,2) DEFAULT 0.00,
  vac195_salario DECIMAL(15,2) DEFAULT 0.00,
  vac195_total DECIMAL(15,2) DEFAULT 0.00,
  
  -- Vacaciones Art. 196
  vac196_alicuota DECIMAL(10,4) DEFAULT 0.0000,
  vac196_dias DECIMAL(10,2) DEFAULT 0.00,
  vac196_salario DECIMAL(15,2) DEFAULT 0.00,
  vac196_total DECIMAL(15,2) DEFAULT 0.00,
  
  -- Antigüedad Art. 142
  antig_nro_dias DECIMAL(10,2) DEFAULT 0.00,
  antig_nro_meses DECIMAL(10,2) DEFAULT 0.00,
  antig_anos DECIMAL(10,2) DEFAULT 0.00,
  antig_salario_integral_mensual DECIMAL(15,2) DEFAULT 0.00,
  antig_monto_total DECIMAL(15,2) DEFAULT 0.00,
  
  -- Otras Asignaciones e Intereses
  otras_asignaciones DECIMAL(15,2) DEFAULT 0.00,
  intereses_antiguedad DECIMAL(15,2) DEFAULT 0.00,
  
  -- Deducciones
  deduccion_sso DECIMAL(15,2) DEFAULT 0.00,
  deduccion_rpvc DECIMAL(15,2) DEFAULT 0.00,
  deduccion_lph DECIMAL(15,2) DEFAULT 0.00,
  deduccion_ince DECIMAL(15,2) DEFAULT 0.00,
  otras_deducciones DECIMAL(15,2) DEFAULT 0.00,
  total_deducciones DECIMAL(15,2) DEFAULT 0.00,
  
  -- Totales
  total_asignaciones DECIMAL(15,2) DEFAULT 0.00,
  neto_a_cobrar DECIMAL(15,2) DEFAULT 0.00,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
);

-- Parámetros Legales
CREATE TABLE IF NOT EXISTS parametros_legales (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) UNIQUE NOT NULL,
  valor DECIMAL(15,4) NOT NULL,
  descripcion TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar parámetros por defecto si no existen
INSERT IGNORE INTO parametros_legales (id, nombre, valor, descripcion) VALUES
(1, 'salario_minimo', 130.00, 'Salario Mínimo Nacional (Bs)'),
(2, 'dias_utilidades_base', 30.00, 'Días base para cálculo de utilidades'),
(3, 'dias_vacaciones_base', 15.00, 'Días base para cálculo de vacaciones (Art. 190)'),
(4, 'tasa_sso', 0.04, 'Tasa de retención del Seguro Social Obligatorio (4%)'),
(5, 'tasa_rpvc', 0.005, 'Tasa de retención del Régimen Prestacional de Vivienda y Hábitat (0.5%)'),
(6, 'tasa_lph', 0.01, 'Tasa de Ley de Política Habitacional (1%)'),
(7, 'tasa_ince', 0.005, 'Tasa de retención del INCE (0.5%)');

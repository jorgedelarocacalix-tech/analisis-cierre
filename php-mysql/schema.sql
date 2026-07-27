-- Análisis de Cierre — esquema MySQL (reemplazo de Supabase/Postgres)
-- Importar en phpMyAdmin (cPanel) sobre una base de datos vacía.

CREATE TABLE IF NOT EXISTS config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(50) NOT NULL UNIQUE,
  valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO config (clave, valor) VALUES ('pin', '1234')
  ON DUPLICATE KEY UPDATE valor = valor;

-- ─────────────────────────────────────────────────────────────
-- arqueos: registros diarios de caja por sucursal.
-- Se alimenta normalmente desde el sistema de arqueos/caja (otra app
-- de la suite). Esta app solo LEE de esta tabla para armar el cierre
-- de junio y el seguimiento de julio. Si esa otra app todavía no está
-- migrada a este mismo servidor, los datos se pueden cargar aquí a
-- mano (phpMyAdmin) o vía el endpoint POST /api/arqueos.php.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS arqueos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sucursal VARCHAR(10) NOT NULL,       -- R1..R5
  fecha DATE NOT NULL,
  cobrado DECIMAL(14,2) NOT NULL DEFAULT 0,
  gastos DECIMAL(14,2) NOT NULL DEFAULT 0,
  depositado DECIMAL(14,2) NOT NULL DEFAULT 0,
  diferencia DECIMAL(14,2) NOT NULL DEFAULT 0,   -- C-G=D ya calculado, NO recalcular
  estado VARCHAR(30) DEFAULT NULL,
  analisis_json JSON DEFAULT NULL,     -- {ventas:[{total_erp,...}], cobros:[{tipo,monto}], depositos:[{banco,tipo,monto}]}
  alertas JSON DEFAULT NULL,
  version INT NOT NULL DEFAULT 1,      -- se toma la versión más alta por sucursal+fecha
  creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_suc_fecha (sucursal, fecha),
  INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- carteras: cartera de cobro con sus clientes (JSON array).
-- Normalmente alimentada por el CRM/sistema de cobranza.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carteras (
  id VARCHAR(60) PRIMARY KEY,          -- ej. LA_ROCA_COMERCIAL_1
  clientes JSON NOT NULL,              -- [{nombre,saldo,cuota,dias_mora,tramo,ultimo_pago,...}]
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- cierre_proyeccion: snapshot mensual de proyección de cobro por cartera.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cierre_proyeccion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(60) NOT NULL,
  mes_key VARCHAR(7) NOT NULL,          -- '2026-07'
  datos JSON NOT NULL,                  -- [{nombre,saldo,saldoEsperado,montoAsesor,diaAsesor,mora,moraVal,cuota,cuotasTrans}]
  cerrado_por VARCHAR(100) DEFAULT NULL,
  cerrado_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_cartera_mes (cartera_id, mes_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- cierre_ventas / cierre_inventario: archivos Excel de ventas e
-- inventario por sucursal, subidos desde la propia app (tab Ventas
-- e Inventario). `filas` guarda el equivalente a la columna `rows`
-- de Supabase (se renombró porque ROWS es palabra reservada en MySQL 8).
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cierre_ventas (
  mes_key VARCHAR(7) NOT NULL,
  sucursal VARCHAR(10) NOT NULL,
  headers JSON DEFAULT NULL,
  filas JSON DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (mes_key, sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cierre_inventario (
  mes_key VARCHAR(7) NOT NULL,
  sucursal VARCHAR(10) NOT NULL,
  headers JSON DEFAULT NULL,
  filas JSON DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (mes_key, sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- proyecciones: tabla antigua/legada mencionada en CONTEXT.md,
-- no la usa ya el index.html actual (sustituida por cierre_proyeccion).
-- Se crea vacía por compatibilidad, no tiene endpoint propio.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proyecciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cartera_id VARCHAR(60) DEFAULT NULL,
  cliente_nombre VARCHAR(150) DEFAULT NULL,
  fecha_proyectada DATE DEFAULT NULL,
  monto_proyectado DECIMAL(14,2) DEFAULT NULL,
  gestor VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

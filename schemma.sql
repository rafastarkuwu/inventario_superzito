-- Base de datos para Inventario SuperZito
-- Ejecutar este script en Railway MySQL

-- Tabla Persona (base para usuarios)
CREATE TABLE IF NOT EXISTS Persona (
    id_persona INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Encargado (administradores)
CREATE TABLE IF NOT EXISTS Encargado (
    id_encargado INT AUTO_INCREMENT PRIMARY KEY,
    id_persona INT NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Trabajadores
CREATE TABLE IF NOT EXISTS Trabajadores (
    id_trabajador INT AUTO_INCREMENT PRIMARY KEY,
    id_persona INT NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fecha_contratacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Inventario
CREATE TABLE IF NOT EXISTS Inventario (
    id_inventario INT AUTO_INCREMENT PRIMARY KEY,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 10,
    id_encargado INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_encargado) REFERENCES Encargado(id_encargado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Productos
CREATE TABLE IF NOT EXISTS Productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(200) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    codigo_barras VARCHAR(50) UNIQUE,
    id_inventario INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inventario) REFERENCES Inventario(id_inventario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Clientes
CREATE TABLE IF NOT EXISTS Clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    id_persona INT NOT NULL,
    puntos_acumulados INT DEFAULT 0,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_persona) REFERENCES Persona(id_persona) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla Ventas
CREATE TABLE IF NOT EXISTS Ventas (
    id_venta INT AUTO_INCREMENT PRIMARY KEY,
    cantidad INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    id_encargado INT NOT NULL,
    fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_encargado) REFERENCES Encargado(id_encargado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para mejorar rendimiento
CREATE INDEX idx_codigo_barras ON Productos(codigo_barras);
CREATE INDEX idx_stock ON Inventario(stock_actual, stock_minimo);
CREATE INDEX idx_fecha_venta ON Ventas(fecha_venta);

-- ============================================
-- INSERTAR 2 ENCARGADOS Y 2 TRABAJADORES
-- ============================================

-- ENCARGADO 1: Alfredo Mendoza
INSERT INTO Persona (nombre, apellido, telefono, email) 
VALUES ('Alfredo', 'Mendoza', '5551111111', 'alfredo.mendoza@superzito.com');

INSERT INTO Encargado (id_persona, usuario, password) 
VALUES (LAST_INSERT_ID(), 'alfredo', 'alfredo123');

-- ENCARGADO 2: Daniel Mendoza
INSERT INTO Persona (nombre, apellido, telefono, email) 
VALUES ('Daniel', 'Mendoza', '5552222222', 'daniel.mendoza@superzito.com');

INSERT INTO Encargado (id_persona, usuario, password) 
VALUES (LAST_INSERT_ID(), 'daniel', 'daniel123');

-- TRABAJADOR 1: Luis Mendoza
INSERT INTO Persona (nombre, apellido, telefono, email) 
VALUES ('Luis', 'Mendoza', '5553333333', 'luis.mendoza@superzito.com');

INSERT INTO Trabajadores (id_persona, usuario, password) 
VALUES (LAST_INSERT_ID(), 'luis', 'luis123');

-- TRABAJADOR 2: Blanca García
INSERT INTO Persona (nombre, apellido, telefono, email) 
VALUES ('Blanca', 'García', '5554444444', 'blanca.garcia@superzito.com');

INSERT INTO Trabajadores (id_persona, usuario, password) 
VALUES (LAST_INSERT_ID(), 'blanca', 'blanca123');

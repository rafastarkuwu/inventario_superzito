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

-- Insertar datos de ejemplo (ENCARGADO DE PRUEBA)
INSERT INTO Persona (nombre, apellido, telefono, email) 
VALUES ('Admin', 'Sistema', '5551234567', 'admin@superzito.com');

INSERT INTO Encargado (id_persona, usuario, password) 
VALUES (1, 'admin', 'admin123');

-- Insertar un trabajador de prueba
INSERT INTO Persona (nombre, apellido, telefono, email) 
VALUES ('Juan', 'Pérez', '5559876543', 'juan@superzito.com');

INSERT INTO Trabajadores (id_persona, usuario, password) 
VALUES (2, 'trabajador1', 'trabajador123');

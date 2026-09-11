-- =====================================================
-- ESQUEMA DE BASE DE DATOS - SISTEMA DE INVENTARIOS Y FACTURACIÓN
-- TECNOXPERT - XAMPP Debian 12
-- =====================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `inventario_facturacion` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `inventario_facturacion`;

-- =====================================================
-- TABLA DE ROLES
-- =====================================================
CREATE TABLE `roles` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(50) NOT NULL UNIQUE,
    `descripcion` TEXT,
    `permisos` JSON,
    `estado` BOOLEAN DEFAULT TRUE,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE USUARIOS
-- =====================================================
CREATE TABLE `usuarios` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `rol_id` INT NOT NULL,
    `estado` BOOLEAN DEFAULT TRUE,
    `ultimo_login` TIMESTAMP NULL,
    `intentos_login` INT DEFAULT 0,
    `bloqueado_hasta` TIMESTAMP NULL,
    `token_recuperacion` VARCHAR(255) NULL,
    `token_expiracion` TIMESTAMP NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE CONFIGURACIÓN
-- =====================================================
CREATE TABLE `config` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `clave` VARCHAR(100) NOT NULL UNIQUE,
    `valor` TEXT,
    `descripcion` TEXT,
    `tipo` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE CATEGORÍAS
-- =====================================================
CREATE TABLE `categorias` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `estado` BOOLEAN DEFAULT TRUE,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE MARCAS
-- =====================================================
CREATE TABLE `marcas` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT,
    `estado` BOOLEAN DEFAULT TRUE,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE PROVEEDORES
-- =====================================================
CREATE TABLE `proveedores` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nit` VARCHAR(20) NOT NULL UNIQUE,
    `nombre` VARCHAR(200) NOT NULL,
    `contacto` VARCHAR(100),
    `telefono` VARCHAR(20),
    `email` VARCHAR(100),
    `direccion` TEXT,
    `ciudad` VARCHAR(100),
    `pais` VARCHAR(100) DEFAULT 'Colombia',
    `estado` BOOLEAN DEFAULT TRUE,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE CLIENTES
-- =====================================================
CREATE TABLE `clientes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `tipo_persona` ENUM('natural', 'juridica') NOT NULL,
    `tipo_documento` ENUM('CC', 'CE', 'NIT', 'RUT', 'TI', 'PP') NOT NULL,
    `numero_documento` VARCHAR(20) NOT NULL,
    `dv` VARCHAR(2),
    `nombre` VARCHAR(200) NOT NULL,
    `regimen` ENUM('comun', 'simplificado', 'gran_contribuyente', 'autorretenedor') DEFAULT 'comun',
    `telefono` VARCHAR(20),
    `email` VARCHAR(100),
    `direccion` TEXT,
    `ciudad` VARCHAR(100),
    `activo` BOOLEAN DEFAULT TRUE,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_documento` (`tipo_documento`, `numero_documento`)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE PRODUCTOS
-- =====================================================
CREATE TABLE `productos` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nombre` VARCHAR(200) NOT NULL,
    `referencia` VARCHAR(50) UNIQUE,
    `codigo_barras` VARCHAR(50) UNIQUE,
    `descripcion` TEXT,
    `categoria_id` INT,
    `marca_id` INT,
    `unidad_medida` VARCHAR(20) DEFAULT 'UN',
    `costo` DECIMAL(15,2) DEFAULT 0.00,
    `precio` DECIMAL(15,2) DEFAULT 0.00,
    `iva_porcentaje` DECIMAL(5,2) DEFAULT 19.00,
    `stock` DECIMAL(10,2) DEFAULT 0.00,
    `stock_minimo` DECIMAL(10,2) DEFAULT 0.00,
    `proveedor_id` INT,
    `ubicacion` VARCHAR(100),
    `estado` ENUM('activo', 'inactivo', 'agotado') DEFAULT 'activo',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`marca_id`) REFERENCES `marcas`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE MOVIMIENTOS DE INVENTARIO
-- =====================================================
CREATE TABLE `inventario_movimientos` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `producto_id` INT NOT NULL,
    `tipo_movimiento` ENUM('entrada', 'salida', 'ajuste') NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL,
    `costo_unitario` DECIMAL(15,2),
    `motivo` VARCHAR(200),
    `referencia` VARCHAR(100),
    `usuario_id` INT NOT NULL,
    `fecha_movimiento` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE VENTAS
-- =====================================================
CREATE TABLE `ventas` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `numero_factura` VARCHAR(20) NOT NULL UNIQUE,
    `cliente_id` INT NOT NULL,
    `usuario_id` INT NOT NULL,
    `fecha_venta` DATE NOT NULL,
    `subtotal` DECIMAL(15,2) DEFAULT 0.00,
    `iva` DECIMAL(15,2) DEFAULT 0.00,
    `descuento` DECIMAL(15,2) DEFAULT 0.00,
    `retefuente` DECIMAL(15,2) DEFAULT 0.00,
    `reteiva` DECIMAL(15,2) DEFAULT 0.00,
    `reteica` DECIMAL(15,2) DEFAULT 0.00,
    `total` DECIMAL(15,2) DEFAULT 0.00,
    `estado` ENUM('pendiente', 'pagada', 'anulada') DEFAULT 'pendiente',
    `observaciones` TEXT,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE ITEMS DE VENTA
-- =====================================================
CREATE TABLE `venta_items` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `venta_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL,
    `precio_unitario` DECIMAL(15,2) NOT NULL,
    `descuento` DECIMAL(15,2) DEFAULT 0.00,
    `iva_porcentaje` DECIMAL(5,2) DEFAULT 19.00,
    `iva_valor` DECIMAL(15,2) DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `total` DECIMAL(15,2) NOT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE PAGOS
-- =====================================================
CREATE TABLE `pagos` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `venta_id` INT NOT NULL,
    `forma_pago` ENUM('efectivo', 'transferencia', 'tarjeta', 'mixto') NOT NULL,
    `monto` DECIMAL(15,2) NOT NULL,
    `referencia` VARCHAR(100),
    `usuario_id` INT NOT NULL,
    `fecha_pago` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE COMPRAS
-- =====================================================
CREATE TABLE `compras` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `numero_compra` VARCHAR(20) NOT NULL UNIQUE,
    `proveedor_id` INT NOT NULL,
    `usuario_id` INT NOT NULL,
    `fecha_compra` DATE NOT NULL,
    `subtotal` DECIMAL(15,2) DEFAULT 0.00,
    `iva` DECIMAL(15,2) DEFAULT 0.00,
    `descuento` DECIMAL(15,2) DEFAULT 0.00,
    `total` DECIMAL(15,2) DEFAULT 0.00,
    `estado` ENUM('pendiente', 'recibida', 'anulada') DEFAULT 'pendiente',
    `observaciones` TEXT,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE ITEMS DE COMPRA
-- =====================================================
CREATE TABLE `compra_items` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `compra_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL,
    `costo_unitario` DECIMAL(15,2) NOT NULL,
    `descuento` DECIMAL(15,2) DEFAULT 0.00,
    `iva_porcentaje` DECIMAL(5,2) DEFAULT 19.00,
    `iva_valor` DECIMAL(15,2) DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `total` DECIMAL(15,2) NOT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`compra_id`) REFERENCES `compras`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE NOTAS CRÉDITO
-- =====================================================
CREATE TABLE `notas_credito` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `numero_nota` VARCHAR(20) NOT NULL UNIQUE,
    `venta_id` INT NOT NULL,
    `usuario_id` INT NOT NULL,
    `fecha_nota` DATE NOT NULL,
    `motivo` ENUM('devolucion', 'descuento', 'error_facturacion', 'otro') NOT NULL,
    `subtotal` DECIMAL(15,2) DEFAULT 0.00,
    `iva` DECIMAL(15,2) DEFAULT 0.00,
    `total` DECIMAL(15,2) DEFAULT 0.00,
    `estado` ENUM('pendiente', 'aplicada', 'anulada') DEFAULT 'pendiente',
    `observaciones` TEXT,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE ITEMS DE NOTA CRÉDITO
-- =====================================================
CREATE TABLE `nota_credito_items` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `nota_credito_id` INT NOT NULL,
    `producto_id` INT NOT NULL,
    `cantidad` DECIMAL(10,2) NOT NULL,
    `precio_unitario` DECIMAL(15,2) NOT NULL,
    `iva_porcentaje` DECIMAL(5,2) DEFAULT 19.00,
    `iva_valor` DECIMAL(15,2) DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL,
    `total` DECIMAL(15,2) NOT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`nota_credito_id`) REFERENCES `notas_credito`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE PARÁMETROS DE FACTURACIÓN ELECTRÓNICA
-- =====================================================
CREATE TABLE `fe_parametros` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `software_id` VARCHAR(100),
    `pin` VARCHAR(100),
    `proveedor_tecnologico` VARCHAR(200),
    `ambiente` ENUM('test', 'production') DEFAULT 'test',
    `resolucion_dian` VARCHAR(50),
    `rango_inicial` VARCHAR(20),
    `rango_final` VARCHAR(20),
    `certificado_p12` TEXT,
    `clave_certificado` VARCHAR(100),
    `activo` BOOLEAN DEFAULT FALSE,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE LOGS DE FACTURACIÓN ELECTRÓNICA
-- =====================================================
CREATE TABLE `fe_log` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `venta_id` INT,
    `tipo_documento` ENUM('factura', 'nota_credito', 'nota_debito') NOT NULL,
    `numero_documento` VARCHAR(20) NOT NULL,
    `estado` ENUM('pendiente', 'enviado', 'aceptado', 'rechazado', 'error') NOT NULL,
    `cufe` VARCHAR(100),
    `cude` VARCHAR(100),
    `qr_code` TEXT,
    `respuesta_dian` JSON,
    `error_mensaje` TEXT,
    `usuario_id` INT NOT NULL,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`venta_id`) REFERENCES `ventas`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA DE AUDITORÍA
-- =====================================================
CREATE TABLE `auditoria` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `usuario_id` INT,
    `accion` VARCHAR(100) NOT NULL,
    `tabla` VARCHAR(100) NOT NULL,
    `registro_id` INT,
    `datos_anteriores` JSON,
    `datos_nuevos` JSON,
    `ip` VARCHAR(45),
    `user_agent` TEXT,
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- ÍNDICES PARA OPTIMIZACIÓN
-- =====================================================

-- Índices para productos
CREATE INDEX `idx_productos_referencia` ON `productos`(`referencia`);
CREATE INDEX `idx_productos_codigo_barras` ON `productos`(`codigo_barras`);
CREATE INDEX `idx_productos_categoria` ON `productos`(`categoria_id`);
CREATE INDEX `idx_productos_marca` ON `productos`(`marca_id`);
CREATE INDEX `idx_productos_proveedor` ON `productos`(`proveedor_id`);
CREATE INDEX `idx_productos_estado` ON `productos`(`estado`);

-- Índices para ventas
CREATE INDEX `idx_ventas_numero_factura` ON `ventas`(`numero_factura`);
CREATE INDEX `idx_ventas_cliente` ON `ventas`(`cliente_id`);
CREATE INDEX `idx_ventas_usuario` ON `ventas`(`usuario_id`);
CREATE INDEX `idx_ventas_fecha` ON `ventas`(`fecha_venta`);
CREATE INDEX `idx_ventas_estado` ON `ventas`(`estado`);

-- Índices para compras
CREATE INDEX `idx_compras_numero_compra` ON `compras`(`numero_compra`);
CREATE INDEX `idx_compras_proveedor` ON `compras`(`proveedor_id`);
CREATE INDEX `idx_compras_usuario` ON `compras`(`usuario_id`);
CREATE INDEX `idx_compras_fecha` ON `compras`(`fecha_compra`);
CREATE INDEX `idx_compras_estado` ON `compras`(`estado`);

-- Índices para movimientos de inventario
CREATE INDEX `idx_inventario_producto` ON `inventario_movimientos`(`producto_id`);
CREATE INDEX `idx_inventario_tipo` ON `inventario_movimientos`(`tipo_movimiento`);
CREATE INDEX `idx_inventario_fecha` ON `inventario_movimientos`(`fecha_movimiento`);
CREATE INDEX `idx_inventario_usuario` ON `inventario_movimientos`(`usuario_id`);

-- Índices para clientes
CREATE INDEX `idx_clientes_documento` ON `clientes`(`tipo_documento`, `numero_documento`);
CREATE INDEX `idx_clientes_nombre` ON `clientes`(`nombre`);

-- Índices para proveedores
CREATE INDEX `idx_proveedores_nit` ON `proveedores`(`nit`);
CREATE INDEX `idx_proveedores_nombre` ON `proveedores`(`nombre`);

-- Índices para auditoría
CREATE INDEX `idx_auditoria_usuario` ON `auditoria`(`usuario_id`);
CREATE INDEX `idx_auditoria_tabla` ON `auditoria`(`tabla`);
CREATE INDEX `idx_auditoria_fecha` ON `auditoria`(`creado_en`);

-- =====================================================
-- VISTAS PARA REPORTES
-- =====================================================

-- Vista de inventario valorizado
CREATE VIEW `vw_inventario_valorizado` AS
SELECT 
    p.id,
    p.nombre,
    p.referencia,
    p.codigo_barras,
    c.nombre as categoria,
    m.nombre as marca,
    p.unidad_medida,
    p.costo,
    p.precio,
    p.stock,
    p.stock_minimo,
    (p.stock * p.costo) as valor_costo,
    (p.stock * p.precio) as valor_venta,
    p.estado,
    p.creado_en
FROM productos p
LEFT JOIN categorias c ON p.categoria_id = c.id
LEFT JOIN marcas m ON p.marca_id = m.id
WHERE p.estado = TRUE;

-- Vista de ventas resumidas
CREATE VIEW `vw_ventas_resumen` AS
SELECT 
    v.id,
    v.numero_factura,
    v.fecha_venta,
    c.nombre as cliente,
    c.tipo_documento,
    c.numero_documento,
    u.nombre as vendedor,
    v.subtotal,
    v.iva,
    v.descuento,
    v.total,
    v.estado,
    COUNT(vi.id) as total_items
FROM ventas v
LEFT JOIN clientes c ON v.cliente_id = c.id
LEFT JOIN usuarios u ON v.usuario_id = u.id
LEFT JOIN venta_items vi ON v.id = vi.venta_id
GROUP BY v.id;

-- Vista de movimientos de inventario
CREATE VIEW `vw_movimientos_inventario` AS
SELECT 
    im.id,
    p.nombre as producto,
    p.referencia,
    im.tipo_movimiento,
    im.cantidad,
    im.costo_unitario,
    im.motivo,
    im.referencia,
    u.nombre as usuario,
    im.fecha_movimiento
FROM inventario_movimientos im
LEFT JOIN productos p ON im.producto_id = p.id
LEFT JOIN usuarios u ON im.usuario_id = u.id
ORDER BY im.fecha_movimiento DESC;

-- =====================================================
-- TRIGGERS PARA AUDITORÍA
-- =====================================================

DELIMITER //

-- Trigger para auditoría de productos
CREATE TRIGGER `tr_productos_audit_update` 
AFTER UPDATE ON `productos`
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, datos_anteriores, datos_nuevos, ip, user_agent)
    VALUES (
        IFNULL(@current_user_id, 1),
        'UPDATE',
        'productos',
        NEW.id,
        JSON_OBJECT(
            'nombre', OLD.nombre,
            'precio', OLD.precio,
            'stock', OLD.stock,
            'estado', OLD.estado
        ),
        JSON_OBJECT(
            'nombre', NEW.nombre,
            'precio', NEW.precio,
            'stock', NEW.stock,
            'estado', NEW.estado
        ),
        IFNULL(@current_ip, 'unknown'),
        IFNULL(@current_user_agent, 'unknown')
    );
END//

-- Trigger para auditoría de ventas
CREATE TRIGGER `tr_ventas_audit_insert` 
AFTER INSERT ON `ventas`
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, datos_nuevos, ip, user_agent)
    VALUES (
        NEW.usuario_id,
        'INSERT',
        'ventas',
        NEW.id,
        JSON_OBJECT(
            'numero_factura', NEW.numero_factura,
            'cliente_id', NEW.cliente_id,
            'total', NEW.total,
            'estado', NEW.estado
        ),
        IFNULL(@current_ip, 'unknown'),
        IFNULL(@current_user_agent, 'unknown')
    );
END//

-- Trigger para actualizar stock al vender
CREATE TRIGGER `tr_venta_items_stock_update` 
AFTER INSERT ON `venta_items`
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET stock = stock - NEW.cantidad,
        actualizado_en = CURRENT_TIMESTAMP
    WHERE id = NEW.producto_id;
    
    -- Registrar movimiento de inventario
    INSERT INTO inventario_movimientos (producto_id, tipo_movimiento, cantidad, costo_unitario, motivo, referencia, usuario_id)
    VALUES (
        NEW.producto_id,
        'salida',
        NEW.cantidad,
        (SELECT costo FROM productos WHERE id = NEW.producto_id),
        'Venta',
        (SELECT numero_factura FROM ventas WHERE id = NEW.venta_id),
        (SELECT usuario_id FROM ventas WHERE id = NEW.venta_id)
    );
END//

-- Trigger para actualizar stock al comprar
CREATE TRIGGER `tr_compra_items_stock_update` 
AFTER INSERT ON `compra_items`
FOR EACH ROW
BEGIN
    UPDATE productos 
    SET stock = stock + NEW.cantidad,
        costo = NEW.costo_unitario,
        actualizado_en = CURRENT_TIMESTAMP
    WHERE id = NEW.producto_id;
    
    -- Registrar movimiento de inventario
    INSERT INTO inventario_movimientos (producto_id, tipo_movimiento, cantidad, costo_unitario, motivo, referencia, usuario_id)
    VALUES (
        NEW.producto_id,
        'entrada',
        NEW.cantidad,
        NEW.costo_unitario,
        'Compra',
        (SELECT numero_compra FROM compras WHERE id = NEW.compra_id),
        (SELECT usuario_id FROM compras WHERE id = NEW.compra_id)
    );
END//

DELIMITER ;

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =====================================================

DELIMITER //

-- Procedimiento para generar número de factura
CREATE PROCEDURE `sp_generar_numero_factura`(OUT numero_factura VARCHAR(20))
BEGIN
    DECLARE ultimo_numero INT DEFAULT 0;
    DECLARE prefijo VARCHAR(10) DEFAULT 'FAC';
    DECLARE anio VARCHAR(4);
    
    SET anio = YEAR(CURRENT_DATE);
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_factura, 12) AS UNSIGNED)), 0)
    INTO ultimo_numero
    FROM ventas 
    WHERE numero_factura LIKE CONCAT(prefijo, anio, '%');
    
    SET numero_factura = CONCAT(prefijo, anio, LPAD(ultimo_numero + 1, 8, '0'));
END//

-- Procedimiento para generar número de compra
CREATE PROCEDURE `sp_generar_numero_compra`(OUT numero_compra VARCHAR(20))
BEGIN
    DECLARE ultimo_numero INT DEFAULT 0;
    DECLARE prefijo VARCHAR(10) DEFAULT 'COM';
    DECLARE anio VARCHAR(4);
    
    SET anio = YEAR(CURRENT_DATE);
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_compra, 12) AS UNSIGNED)), 0)
    INTO ultimo_numero
    FROM compras 
    WHERE numero_compra LIKE CONCAT(prefijo, anio, '%');
    
    SET numero_compra = CONCAT(prefijo, anio, LPAD(ultimo_numero + 1, 8, '0'));
END//

-- Procedimiento para calcular totales de venta
CREATE PROCEDURE `sp_calcular_totales_venta`(IN venta_id INT)
BEGIN
    DECLARE subtotal DECIMAL(15,2);
    DECLARE iva_total DECIMAL(15,2);
    DECLARE total_final DECIMAL(15,2);
    
    -- Calcular subtotal
    SELECT COALESCE(SUM(subtotal), 0) INTO subtotal
    FROM venta_items WHERE venta_id = venta_id;
    
    -- Calcular IVA
    SELECT COALESCE(SUM(iva_valor), 0) INTO iva_total
    FROM venta_items WHERE venta_id = venta_id;
    
    -- Calcular total
    SET total_final = subtotal + iva_total;
    
    -- Actualizar venta
    UPDATE ventas 
    SET subtotal = subtotal,
        iva = iva_total,
        total = total_final,
        actualizado_en = CURRENT_TIMESTAMP
    WHERE id = venta_id;
END//

DELIMITER ;

-- =====================================================
-- FIN DEL ESQUEMA
-- =====================================================

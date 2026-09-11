<?php
session_start();

// Verificar si está logueado
if (!isset($_SESSION['user_id'])) {
    die('No autorizado');
}

$factura_id = $_GET['id'] ?? 0;

if (!$factura_id) {
    die('ID de factura no válido');
}

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener datos de la factura
    $sql = "SELECT v.*, c.nombre as cliente_nombre, c.tipo_documento, c.numero_documento, c.direccion as cliente_direccion,
                   u.nombre as vendedor_nombre
            FROM ventas v 
            LEFT JOIN clientes c ON v.cliente_id = c.id 
            LEFT JOIN usuarios u ON v.usuario_id = u.id 
            WHERE v.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$factura) {
        die('Factura no encontrada');
    }
    
    // Obtener items de la factura
    $sql = "SELECT vi.*, p.nombre as producto_nombre, p.referencia 
            FROM venta_items vi 
            LEFT JOIN productos p ON vi.producto_id = p.id 
            WHERE vi.venta_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$factura_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener datos de la empresa
    $sql = "SELECT clave, valor FROM config WHERE clave IN ('nombre_empresa', 'nit', 'direccion', 'ciudad', 'telefono', 'email')";
    $stmt = $pdo->query($sql);
    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $config[$row['clave']] = $row['valor'];
    }
    
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo $factura['numero_factura']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
        }
        .factura {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .empresa {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .nit {
            font-size: 14px;
            color: #666;
        }
        .info-factura {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .cliente-info, .factura-info {
            width: 48%;
        }
        .cliente-info h3, .factura-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .items-table .cantidad, .items-table .precio, .items-table .total {
            text-align: right;
        }
        .totales {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .total-final {
            font-weight: bold;
            font-size: 18px;
            color: #333;
            border-top: 2px solid #333;
            padding-top: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        @media print {
            body { margin: 0; }
            .factura { border: none; }
        }
    </style>
</head>
<body>
    <div class="factura">
        <!-- Header -->
        <div class="header">
            <div class="empresa"><?php echo htmlspecialchars($config['nombre_empresa'] ?? 'TECNOXPERT'); ?></div>
            <div class="nit">NIT: <?php echo htmlspecialchars($config['nit'] ?? '900.000.000-1'); ?></div>
            <div style="margin-top: 10px;">
                <?php echo htmlspecialchars($config['direccion'] ?? 'Calle Principal #123'); ?><br>
                <?php echo htmlspecialchars($config['ciudad'] ?? 'Bogotá'); ?> | 
                <?php echo htmlspecialchars($config['telefono'] ?? ''); ?>
            </div>
        </div>
        
        <!-- Información de Factura y Cliente -->
        <div class="info-factura">
            <div class="cliente-info">
                <h3>FACTURAR A:</h3>
                <strong><?php echo htmlspecialchars($factura['cliente_nombre'] ?? 'CLIENTE GENERAL'); ?></strong><br>
                <?php if ($factura['tipo_documento'] && $factura['numero_documento']): ?>
                    <?php echo htmlspecialchars($factura['tipo_documento']); ?>: <?php echo htmlspecialchars($factura['numero_documento']); ?><br>
                <?php endif; ?>
                <?php if ($factura['cliente_direccion']): ?>
                    <?php echo htmlspecialchars($factura['cliente_direccion']); ?><br>
                <?php endif; ?>
            </div>
            
            <div class="factura-info">
                <h3>FACTURA</h3>
                <strong>No. <?php echo htmlspecialchars($factura['numero_factura']); ?></strong><br>
                <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($factura['fecha_venta'])); ?><br>
                <strong>Vendedor:</strong> <?php echo htmlspecialchars($factura['vendedor_nombre'] ?? 'Sistema'); ?>
            </div>
        </div>
        
        <!-- Tabla de Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th class="cantidad">Cantidad</th>
                    <th class="precio">Precio Unit.</th>
                    <th class="total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['referencia'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($item['producto_nombre'] ?? ''); ?></td>
                    <td class="cantidad"><?php echo number_format($item['cantidad'], 0); ?></td>
                    <td class="precio">$<?php echo number_format($item['precio_unitario'], 0, ',', '.'); ?></td>
                    <td class="total">$<?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Totales -->
        <div class="totales">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($factura['subtotal'], 0, ',', '.'); ?></span>
            </div>
            <?php if ($factura['iva'] > 0): ?>
            <div class="total-row">
                <span>IVA (19%):</span>
                <span>$<?php echo number_format($factura['iva'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($factura['descuento'] > 0): ?>
            <div class="total-row">
                <span>Descuento:</span>
                <span>-$<?php echo number_format($factura['descuento'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row total-final">
                <span>TOTAL:</span>
                <span>$<?php echo number_format($factura['total'], 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>¡Gracias por su compra!</p>
            <p>Esta factura fue generada electrónicamente el <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    
    <script>
        // Auto-imprimir cuando se carga la página
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>

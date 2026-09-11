<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../app/Lib/Permissions.php';
Permissions::requirePermission('read', 'productos');

try {
    $pdo = new PDO("mysql:host=" . (getenv('LOCAL_DB_HOST') ?: '') . ";dbname=" . (getenv('LOCAL_DB_NAME') ?: '') . ";charset=utf8mb4", getenv('LOCAL_DB_USER') ?: '', getenv('LOCAL_DB_PASS') ?: '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT nombre, referencia, stock FROM productos WHERE estado = 'activo' ORDER BY nombre");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalUnidades = $pdo->query("SELECT COALESCE(SUM(stock), 0) FROM productos WHERE estado = 'activo'")->fetchColumn();
} catch (PDOException $e) {
    $error = "Error de conexion: " . $e->getMessage();
    $productos = [];
    $totalUnidades = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Arial, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 12px;
            background: #111827;
            z-index: 10;
        }
        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 6px;
            padding: 10px 14px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .toolbar .print { background: #16a34a; }
        .toolbar .back { background: #4b5563; }
        .ticket {
            width: 80mm;
            min-height: 100vh;
            margin: 16px auto;
            padding: 8px 6px;
            background: #fff;
            font-family: "Courier New", monospace;
            font-size: 11px;
            line-height: 1.25;
        }
        .center { text-align: center; }
        .title { font-size: 14px; font-weight: bold; }
        .muted { color: #4b5563; }
        .line { border-top: 1px dashed #111; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; vertical-align: top; }
        th { border-bottom: 1px dashed #111; text-align: left; }
        .qty { width: 12mm; text-align: right; font-weight: bold; }
        .ref { font-size: 10px; color: #374151; }
        .total { font-weight: bold; font-size: 12px; }
        .error { color: #b91c1c; font-weight: bold; text-align: center; }
        @page { size: 80mm auto; margin: 2mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .ticket {
                width: 76mm;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="print" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <a class="back" href="productos.php"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <main class="ticket">
        <div class="center">
            <div class="title">TECNOXPERT</div>
            <div>Listado de Inventario</div>
            <div class="muted"><?php echo date('Y-m-d H:i'); ?></div>
        </div>
        <div class="line"></div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="qty">Cant.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($producto['nombre']); ?>
                            <?php if (!empty($producto['referencia'])): ?>
                                <br><span class="ref">Ref: <?php echo htmlspecialchars($producto['referencia']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="qty"><?php echo number_format((float)$producto['stock'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="line"></div>
            <div>Productos: <?php echo number_format(count($productos), 0, ',', '.'); ?></div>
            <div class="total">Total unidades: <?php echo number_format((float)$totalUnidades, 0, ',', '.'); ?></div>
        <?php endif; ?>
        <div class="line"></div>
        <div class="center muted">Fin del listado</div>
    </main>
</body>
</html>

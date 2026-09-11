<?php
/**
 * Controlador de Reportes
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class ReporteController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function index() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        return [
            'view' => 'reportes/index',
            'data' => [
                'title' => 'Reportes - ' . APP_NAME,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function inventario() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $categoria = $_GET['categoria'] ?? '';
        $marca = $_GET['marca'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        $conditions = ['productos.estado' => 'activo'];
        $joins = [
            [
                'type' => 'LEFT',
                'table' => 'categorias',
                'condition' => 'productos.categoria_id = categorias.id'
            ],
            [
                'type' => 'LEFT',
                'table' => 'marcas',
                'condition' => 'productos.marca_id = marcas.id'
            ]
        ];
        
        $select = 'productos.*, categorias.nombre as categoria_nombre, marcas.nombre as marca_nombre,
                   (productos.stock * productos.costo) as valor_costo,
                   (productos.stock * productos.precio) as valor_venta';
        $orderBy = 'productos.nombre ASC';
        
        if (!empty($categoria)) {
            $conditions['productos.categoria_id'] = $categoria;
        }
        
        if (!empty($marca)) {
            $conditions['productos.marca_id'] = $marca;
        }
        
        if (!empty($estado)) {
            $conditions['productos.estado'] = $estado;
        }
        
        $productos = $this->db->join('productos', $joins, $select, $conditions, $orderBy);
        $categorias = $this->db->findAll('categorias', ['activo' => 1], 'nombre ASC');
        $marcas = $this->db->findAll('marcas', ['activo' => 1], 'nombre ASC');
        
        return [
            'view' => 'reportes/inventario',
            'data' => [
                'title' => 'Reporte de Inventario - ' . APP_NAME,
                'productos' => $productos,
                'categorias' => $categorias,
                'marcas' => $marcas,
                'categoria' => $categoria,
                'marca' => $marca,
                'estado' => $estado,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function ventas() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
        $cliente = $_GET['cliente'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        $conditions = [];
        $joins = [
            [
                'type' => 'LEFT',
                'table' => 'clientes',
                'condition' => 'ventas.cliente_id = clientes.id'
            ]
        ];
        
        $select = 'ventas.*, clientes.nombre as cliente_nombre';
        $orderBy = 'ventas.fecha_venta DESC';
        
        if (!empty($fecha_inicio)) {
            $conditions['ventas.fecha_venta >='] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $conditions['ventas.fecha_venta <='] = $fecha_fin;
        }
        
        if (!empty($cliente)) {
            $conditions['ventas.cliente_id'] = $cliente;
        }
        
        if (!empty($estado)) {
            $conditions['ventas.estado'] = $estado;
        }
        
        $ventas = $this->db->join('ventas', $joins, $select, $conditions, $orderBy);
        $clientes = $this->db->findAll('clientes', ['activo' => 1], 'nombre ASC');
        
        // Calcular totales
        $totales = [
            'total_ventas' => count($ventas),
            'subtotal' => array_sum(array_column($ventas, 'subtotal')),
            'iva' => array_sum(array_column($ventas, 'iva')),
            'descuentos' => array_sum(array_column($ventas, 'descuento')),
            'total' => array_sum(array_column($ventas, 'total'))
        ];
        
        return [
            'view' => 'reportes/ventas',
            'data' => [
                'title' => 'Reporte de Ventas - ' . APP_NAME,
                'ventas' => $ventas,
                'clientes' => $clientes,
                'totales' => $totales,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'cliente' => $cliente,
                'estado' => $estado,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function compras() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
        $proveedor = $_GET['proveedor'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        $conditions = [];
        $joins = [
            [
                'type' => 'LEFT',
                'table' => 'proveedores',
                'condition' => 'compras.proveedor_id = proveedores.id'
            ]
        ];
        
        $select = 'compras.*, proveedores.nombre as proveedor_nombre';
        $orderBy = 'compras.fecha_compra DESC';
        
        if (!empty($fecha_inicio)) {
            $conditions['compras.fecha_compra >='] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $conditions['compras.fecha_compra <='] = $fecha_fin;
        }
        
        if (!empty($proveedor)) {
            $conditions['compras.proveedor_id'] = $proveedor;
        }
        
        if (!empty($estado)) {
            $conditions['compras.estado'] = $estado;
        }
        
        $compras = $this->db->join('compras', $joins, $select, $conditions, $orderBy);
        $proveedores = $this->db->findAll('proveedores', ['activo' => 1], 'nombre ASC');
        
        // Calcular totales
        $totales = [
            'total_compras' => count($compras),
            'subtotal' => array_sum(array_column($compras, 'subtotal')),
            'iva' => array_sum(array_column($compras, 'iva')),
            'descuentos' => array_sum(array_column($compras, 'descuento')),
            'total' => array_sum(array_column($compras, 'total'))
        ];
        
        return [
            'view' => 'reportes/compras',
            'data' => [
                'title' => 'Reporte de Compras - ' . APP_NAME,
                'compras' => $compras,
                'proveedores' => $proveedores,
                'totales' => $totales,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'proveedor' => $proveedor,
                'estado' => $estado,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function kardex($producto_id = null) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!$producto_id) {
            $producto_id = $_GET['producto_id'] ?? '';
        }
        
        if (empty($producto_id)) {
            redirect('reportes?error=producto_required');
        }
        
        $producto = $this->db->findById('productos', $producto_id);
        
        if (!$producto) {
            redirect('reportes?error=producto_not_found');
        }
        
        // Obtener movimientos del producto
        $movimientos = $this->db->query(
            "SELECT im.*, u.nombre as usuario_nombre
             FROM inventario_movimientos im
             LEFT JOIN usuarios u ON im.usuario_id = u.id
             WHERE im.producto_id = ?
             ORDER BY im.fecha_movimiento DESC",
            [$producto_id]
        );
        
        return [
            'view' => 'reportes/kardex',
            'data' => [
                'title' => "Kardex - {$producto['nombre']} - " . APP_NAME,
                'producto' => $producto,
                'movimientos' => $movimientos,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function export() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $tipo = $_GET['tipo'] ?? '';
        $formato = $_GET['formato'] ?? 'csv';
        
        switch ($tipo) {
            case 'inventario':
                $this->exportInventario($formato);
                break;
            case 'ventas':
                $this->exportVentas($formato);
                break;
            case 'compras':
                $this->exportCompras($formato);
                break;
            default:
                redirect('reportes?error=invalid_type');
        }
    }
    
    private function exportInventario($formato) {
        $productos = $this->db->query(
            "SELECT p.nombre, p.referencia, p.codigo_barras, c.nombre as categoria,
                    m.nombre as marca, p.stock, p.costo, p.precio,
                    (p.stock * p.costo) as valor_costo,
                    (p.stock * p.precio) as valor_venta
             FROM productos p
             LEFT JOIN categorias c ON p.categoria_id = c.id
             LEFT JOIN marcas m ON p.marca_id = m.id
             WHERE p.estado = 'activo'
             ORDER BY p.nombre ASC"
        );
        
        $filename = 'inventario_' . date('Y-m-d_H-i-s') . '.' . $formato;
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'Nombre', 'Referencia', 'Código de Barras', 'Categoría', 'Marca',
            'Stock', 'Costo', 'Precio', 'Valor Costo', 'Valor Venta'
        ]);
        
        // Data
        foreach ($productos as $producto) {
            fputcsv($output, [
                $producto['nombre'],
                $producto['referencia'],
                $producto['codigo_barras'],
                $producto['categoria'],
                $producto['marca'],
                $producto['stock'],
                $producto['costo'],
                $producto['precio'],
                $producto['valor_costo'],
                $producto['valor_venta']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    private function exportVentas($formato) {
        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
        
        $ventas = $this->db->query(
            "SELECT v.numero_factura, v.fecha_venta, c.nombre as cliente,
                    v.subtotal, v.iva, v.descuento, v.total, v.estado
             FROM ventas v
             LEFT JOIN clientes c ON v.cliente_id = c.id
             WHERE v.fecha_venta BETWEEN ? AND ?
             ORDER BY v.fecha_venta DESC",
            [$fecha_inicio, $fecha_fin]
        );
        
        $filename = 'ventas_' . date('Y-m-d_H-i-s') . '.' . $formato;
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'Número Factura', 'Fecha', 'Cliente', 'Subtotal', 'IVA', 'Descuento', 'Total', 'Estado'
        ]);
        
        // Data
        foreach ($ventas as $venta) {
            fputcsv($output, [
                $venta['numero_factura'],
                $venta['fecha_venta'],
                $venta['cliente'],
                $venta['subtotal'],
                $venta['iva'],
                $venta['descuento'],
                $venta['total'],
                $venta['estado']
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    private function exportCompras($formato) {
        $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
        
        $compras = $this->db->query(
            "SELECT c.numero_compra, c.fecha_compra, p.nombre as proveedor,
                    c.subtotal, c.iva, c.descuento, c.total, c.estado
             FROM compras c
             LEFT JOIN proveedores p ON c.proveedor_id = p.id
             WHERE c.fecha_compra BETWEEN ? AND ?
             ORDER BY c.fecha_compra DESC",
            [$fecha_inicio, $fecha_fin]
        );
        
        $filename = 'compras_' . date('Y-m-d_H-i-s') . '.' . $formato;
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'Número Compra', 'Fecha', 'Proveedor', 'Subtotal', 'IVA', 'Descuento', 'Total', 'Estado'
        ]);
        
        // Data
        foreach ($compras as $compra) {
            fputcsv($output, [
                $compra['numero_compra'],
                $compra['fecha_compra'],
                $compra['proveedor'],
                $compra['subtotal'],
                $compra['iva'],
                $compra['descuento'],
                $compra['total'],
                $compra['estado']
            ]);
        }
        
        fclose($output);
        exit();
    }
}
?>


<?php
/**
 * Controlador de Ventas
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class VentaController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function index() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $search = $_GET['search'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
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
        
        if (!empty($search)) {
            $conditions['ventas.numero_factura LIKE'] = "%{$search}%";
        }
        
        if (!empty($estado)) {
            $conditions['ventas.estado'] = $estado;
        }
        
        if (!empty($fecha_inicio)) {
            $conditions['ventas.fecha_venta >='] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $conditions['ventas.fecha_venta <='] = $fecha_fin;
        }
        
        $ventas = $this->db->join('ventas', $joins, $select, $conditions, $orderBy);
        
        return [
            'view' => 'ventas/index',
            'data' => [
                'title' => 'Ventas - ' . APP_NAME,
                'ventas' => $ventas,
                'search' => $search,
                'estado' => $estado,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function create() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $clientes = $this->db->findAll('clientes', ['activo' => 1], 'nombre ASC');
        $productos = $this->db->findAll('productos', ['estado' => 'activo'], 'nombre ASC');
        
        return [
            'view' => 'ventas/create',
            'data' => [
                'title' => 'Nueva Venta - ' . APP_NAME,
                'clientes' => $clientes,
                'productos' => $productos,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function store() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('ventas');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Generar número de factura
            $numeroFactura = $this->generateInvoiceNumber();
            
            $ventaData = [
                'numero_factura' => $numeroFactura,
                'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
                'usuario_id' => getCurrentUserId(),
                'fecha_venta' => date('Y-m-d'),
                'subtotal' => 0,
                'iva' => 0,
                'descuento' => (float)($_POST['descuento'] ?? 0),
                'retefuente' => 0,
                'reteiva' => 0,
                'reteica' => 0,
                'total' => 0,
                'estado' => 'pendiente',
                'observaciones' => sanitizeInput($_POST['observaciones'] ?? '')
            ];
            
            $ventaId = $this->db->insert('ventas', $ventaData);
            
            // Procesar items
            $items = json_decode($_POST['items'] ?? '[]', true);
            $subtotal = 0;
            $iva = 0;
            
            foreach ($items as $item) {
                $producto = $this->db->findById('productos', $item['producto_id']);
                
                if (!$producto || $producto['stock'] < $item['cantidad']) {
                    throw new Exception("Stock insuficiente para {$producto['nombre']}");
                }
                
                $precioUnitario = (float)$item['precio'];
                $cantidad = (float)$item['cantidad'];
                $descuento = (float)($item['descuento'] ?? 0);
                $ivaPorcentaje = (float)($producto['iva_porcentaje']);
                
                $subtotalItem = ($precioUnitario * $cantidad) - $descuento;
                $ivaItem = $subtotalItem * ($ivaPorcentaje / 100);
                $totalItem = $subtotalItem + $ivaItem;
                
                $itemData = [
                    'venta_id' => $ventaId,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'descuento' => $descuento,
                    'iva_porcentaje' => $ivaPorcentaje,
                    'iva_valor' => $ivaItem,
                    'subtotal' => $subtotalItem,
                    'total' => $totalItem
                ];
                
                $this->db->insert('venta_items', $itemData);
                
                $subtotal += $subtotalItem;
                $iva += $ivaItem;
            }
            
            // Actualizar totales de la venta
            $total = $subtotal + $iva - $ventaData['descuento'];
            
            $this->db->update('ventas', [
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total
            ], 'id = ?', [$ventaId]);
            
            $this->db->commit();
            
            logActivity("Venta creada: {$numeroFactura}", 'INFO', getCurrentUserId());
            redirect("ventas/show/{$ventaId}?success=created");
            
        } catch (Exception $e) {
            $this->db->rollback();
            logActivity("Error creando venta: " . $e->getMessage(), 'ERROR');
            redirect('ventas/create?error=creation_failed');
        }
    }
    
    public function show($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $venta = $this->db->findById('ventas', $id);
        
        if (!$venta) {
            redirect('ventas?error=not_found');
        }
        
        // Obtener información del cliente
        $cliente = $this->db->findById('clientes', $venta['cliente_id']);
        
        // Obtener items de la venta
        $items = $this->db->query(
            "SELECT vi.*, p.nombre as producto_nombre, p.referencia 
             FROM venta_items vi 
             JOIN productos p ON vi.producto_id = p.id 
             WHERE vi.venta_id = ?",
            [$id]
        );
        
        return [
            'view' => 'ventas/show',
            'data' => [
                'title' => "Factura {$venta['numero_factura']} - " . APP_NAME,
                'venta' => $venta,
                'cliente' => $cliente,
                'items' => $items,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    private function generateInvoiceNumber() {
        $year = date('Y');
        $sql = "SELECT MAX(CAST(SUBSTRING(numero_factura, 12) AS UNSIGNED)) as ultimo 
                FROM ventas 
                WHERE numero_factura LIKE 'FAC{$year}%'";
        
        $result = $this->db->query($sql);
        $ultimo = $result[0]['ultimo'] ?? 0;
        
        return 'FAC' . $year . str_pad($ultimo + 1, 8, '0', STR_PAD_LEFT);
    }
    
    public function pdf($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $venta = $this->db->findById('ventas', $id);
        
        if (!$venta) {
            redirect('ventas?error=not_found');
        }
        
        // Aquí se generaría el PDF
        // Por ahora solo redirigimos
        redirect("ventas/show/{$id}");
    }
}
?>


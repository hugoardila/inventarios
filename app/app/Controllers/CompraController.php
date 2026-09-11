<?php
/**
 * Controlador de Compras
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class CompraController {
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
                'table' => 'proveedores',
                'condition' => 'compras.proveedor_id = proveedores.id'
            ]
        ];
        
        $select = 'compras.*, proveedores.nombre as proveedor_nombre';
        $orderBy = 'compras.fecha_compra DESC';
        
        if (!empty($search)) {
            $conditions['compras.numero_compra LIKE'] = "%{$search}%";
        }
        
        if (!empty($estado)) {
            $conditions['compras.estado'] = $estado;
        }
        
        if (!empty($fecha_inicio)) {
            $conditions['compras.fecha_compra >='] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $conditions['compras.fecha_compra <='] = $fecha_fin;
        }
        
        $compras = $this->db->join('compras', $joins, $select, $conditions, $orderBy);
        
        return [
            'view' => 'compras/index',
            'data' => [
                'title' => 'Compras - ' . APP_NAME,
                'compras' => $compras,
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
        
        $proveedores = $this->db->findAll('proveedores', ['activo' => 1], 'nombre ASC');
        $productos = $this->db->findAll('productos', ['estado' => 'activo'], 'nombre ASC');
        
        return [
            'view' => 'compras/create',
            'data' => [
                'title' => 'Nueva Compra - ' . APP_NAME,
                'proveedores' => $proveedores,
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
            redirect('compras');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Generar número de compra
            $numeroCompra = $this->generatePurchaseNumber();
            
            $compraData = [
                'numero_compra' => $numeroCompra,
                'proveedor_id' => (int)($_POST['proveedor_id'] ?? 0),
                'usuario_id' => getCurrentUserId(),
                'fecha_compra' => date('Y-m-d'),
                'subtotal' => 0,
                'iva' => 0,
                'descuento' => (float)($_POST['descuento'] ?? 0),
                'total' => 0,
                'estado' => 'pendiente',
                'observaciones' => sanitizeInput($_POST['observaciones'] ?? '')
            ];
            
            $compraId = $this->db->insert('compras', $compraData);
            
            // Procesar items
            $items = json_decode($_POST['items'] ?? '[]', true);
            $subtotal = 0;
            $iva = 0;
            
            foreach ($items as $item) {
                $producto = $this->db->findById('productos', $item['producto_id']);
                
                if (!$producto) {
                    throw new Exception("Producto no encontrado");
                }
                
                $costoUnitario = (float)$item['costo'];
                $cantidad = (float)$item['cantidad'];
                $descuento = (float)($item['descuento'] ?? 0);
                $ivaPorcentaje = (float)($producto['iva_porcentaje']);
                
                $subtotalItem = ($costoUnitario * $cantidad) - $descuento;
                $ivaItem = $subtotalItem * ($ivaPorcentaje / 100);
                $totalItem = $subtotalItem + $ivaItem;
                
                $itemData = [
                    'compra_id' => $compraId,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'descuento' => $descuento,
                    'iva_porcentaje' => $ivaPorcentaje,
                    'iva_valor' => $ivaItem,
                    'subtotal' => $subtotalItem,
                    'total' => $totalItem
                ];
                
                $this->db->insert('compra_items', $itemData);
                
                $subtotal += $subtotalItem;
                $iva += $ivaItem;
            }
            
            // Actualizar totales de la compra
            $total = $subtotal + $iva - $compraData['descuento'];
            
            $this->db->update('compras', [
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total
            ], 'id = ?', [$compraId]);
            
            $this->db->commit();
            
            logActivity("Compra creada: {$numeroCompra}", 'INFO', getCurrentUserId());
            redirect("compras/show/{$compraId}?success=created");
            
        } catch (Exception $e) {
            $this->db->rollback();
            logActivity("Error creando compra: " . $e->getMessage(), 'ERROR');
            redirect('compras/create?error=creation_failed');
        }
    }
    
    public function show($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $compra = $this->db->findById('compras', $id);
        
        if (!$compra) {
            redirect('compras?error=not_found');
        }
        
        // Obtener información del proveedor
        $proveedor = $this->db->findById('proveedores', $compra['proveedor_id']);
        
        // Obtener items de la compra
        $items = $this->db->query(
            "SELECT ci.*, p.nombre as producto_nombre, p.referencia 
             FROM compra_items ci 
             JOIN productos p ON ci.producto_id = p.id 
             WHERE ci.compra_id = ?",
            [$id]
        );
        
        return [
            'view' => 'compras/show',
            'data' => [
                'title' => "Compra {$compra['numero_compra']} - " . APP_NAME,
                'compra' => $compra,
                'proveedor' => $proveedor,
                'items' => $items,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    private function generatePurchaseNumber() {
        $year = date('Y');
        $sql = "SELECT MAX(CAST(SUBSTRING(numero_compra, 12) AS UNSIGNED)) as ultimo 
                FROM compras 
                WHERE numero_compra LIKE 'COM{$year}%'";
        
        $result = $this->db->query($sql);
        $ultimo = $result[0]['ultimo'] ?? 0;
        
        return 'COM' . $year . str_pad($ultimo + 1, 8, '0', STR_PAD_LEFT);
    }
    
    public function recibir($id) {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        $compra = $this->db->findById('compras', $id);
        
        if (!$compra) {
            redirect('compras?error=not_found');
        }
        
        if ($compra['estado'] !== 'pendiente') {
            redirect('compras?error=invalid_status');
        }
        
        try {
            $this->db->update('compras', ['estado' => 'recibida'], 'id = ?', [$id]);
            logActivity("Compra recibida: {$compra['numero_compra']}", 'INFO', getCurrentUserId());
            redirect("compras/show/{$id}?success=received");
        } catch (Exception $e) {
            logActivity("Error recibiendo compra: " . $e->getMessage(), 'ERROR');
            redirect('compras?error=receive_failed');
        }
    }
}
?>


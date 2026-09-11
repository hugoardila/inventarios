<?php
/**
 * Controlador del Dashboard
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';

class DashboardController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Mostrar dashboard principal
     */
    public function index() {
        // Verificar autenticación
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        // Obtener estadísticas según el rol
        $stats = $this->getStats();
        
        // Obtener productos con stock bajo
        $lowStockProducts = $this->getLowStockProducts();
        
        // Obtener últimas ventas
        $recentSales = $this->getRecentSales();
        
        // Obtener productos más vendidos
        $topProducts = $this->getTopProducts();
        
        return [
            'view' => 'dashboard/index',
            'data' => [
                'title' => 'Dashboard - ' . APP_NAME,
                'stats' => $stats,
                'lowStockProducts' => $lowStockProducts,
                'recentSales' => $recentSales,
                'topProducts' => $topProducts,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    /**
     * Obtener estadísticas del dashboard
     */
    private function getStats() {
        $stats = [];
        
        try {
            // Total de productos
            $stats['total_productos'] = $this->db->count('productos', ['estado' => 'activo']);
            
            // Productos con stock bajo
            $sql = "SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo AND estado = 'activo'";
            $result = $this->db->query($sql);
            $stats['productos_stock_bajo'] = $result[0]['total'] ?? 0;
            
            // Total de clientes
            $stats['total_clientes'] = $this->db->count('clientes', ['estado' => 1]);
            
            // Total de proveedores
            $stats['total_proveedores'] = $this->db->count('proveedores', ['estado' => 1]);
            
            // Ventas del mes actual
            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
                    FROM ventas 
                    WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE) 
                    AND YEAR(fecha_venta) = YEAR(CURRENT_DATE)
                    AND estado = 'pagada'";
            $result = $this->db->query($sql);
            $stats['ventas_mes'] = $result[0]['total'] ?? 0;
            $stats['monto_ventas_mes'] = $result[0]['monto'] ?? 0;
            
            // Ventas del día
            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as monto 
                    FROM ventas 
                    WHERE DATE(fecha_venta) = CURRENT_DATE
                    AND estado = 'pagada'";
            $result = $this->db->query($sql);
            $stats['ventas_hoy'] = $result[0]['total'] ?? 0;
            $stats['monto_ventas_hoy'] = $result[0]['monto'] ?? 0;
            
            // Valor del inventario
            $sql = "SELECT COALESCE(SUM(stock * costo), 0) as valor_costo,
                           COALESCE(SUM(stock * precio), 0) as valor_venta
                    FROM productos 
                    WHERE estado = 'activo'";
            $result = $this->db->query($sql);
            $stats['valor_inventario_costo'] = $result[0]['valor_costo'] ?? 0;
            $stats['valor_inventario_venta'] = $result[0]['valor_venta'] ?? 0;
            
        } catch (Exception $e) {
            logActivity("Error obteniendo estadísticas: " . $e->getMessage(), 'ERROR');
        }
        
        return $stats;
    }
    
    /**
     * Obtener productos con stock bajo
     */
    private function getLowStockProducts() {
        try {
            $sql = "SELECT p.nombre, p.stock, p.stock_minimo, p.referencia,
                           c.nombre as categoria
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id = c.id
                    WHERE p.stock <= p.stock_minimo 
                    AND p.estado = 'activo'
                    ORDER BY p.stock ASC
                    LIMIT 10";
            
            return $this->db->query($sql);
        } catch (Exception $e) {
            logActivity("Error obteniendo productos con stock bajo: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener últimas ventas
     */
    private function getRecentSales() {
        try {
            $sql = "SELECT v.numero_factura, v.fecha_venta, v.total, v.estado,
                           c.nombre as cliente, c.tipo_documento, c.numero_documento
                    FROM ventas v
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    ORDER BY v.creado_en DESC
                    LIMIT 10";
            
            return $this->db->query($sql);
        } catch (Exception $e) {
            logActivity("Error obteniendo últimas ventas: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener productos más vendidos
     */
    private function getTopProducts() {
        try {
            $sql = "SELECT p.nombre, p.referencia,
                           SUM(vi.cantidad) as total_vendido,
                           SUM(vi.total) as monto_total
                    FROM venta_items vi
                    JOIN productos p ON vi.producto_id = p.id
                    JOIN ventas v ON vi.venta_id = v.id
                    WHERE v.estado = 'pagada'
                    AND v.fecha_venta >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                    GROUP BY p.id, p.nombre, p.referencia
                    ORDER BY total_vendido DESC
                    LIMIT 10";
            
            return $this->db->query($sql);
        } catch (Exception $e) {
            logActivity("Error obteniendo productos más vendidos: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Obtener datos para gráficos (AJAX)
     */
    public function getChartData() {
        if (!isAuthenticated()) {
            jsonResponse(['error' => 'No autorizado'], 401);
        }
        
        try {
            $type = $_GET['type'] ?? 'ventas_mensuales';
            
            switch ($type) {
                case 'ventas_mensuales':
                    $data = $this->getVentasMensuales();
                    break;
                case 'productos_categoria':
                    $data = $this->getProductosPorCategoria();
                    break;
                case 'ventas_diarias':
                    $data = $this->getVentasDiarias();
                    break;
                default:
                    $data = [];
            }
            
            jsonResponse(['success' => true, 'data' => $data]);
            
        } catch (Exception $e) {
            logActivity("Error obteniendo datos de gráficos: " . $e->getMessage(), 'ERROR');
            jsonResponse(['error' => 'Error interno del sistema'], 500);
        }
    }
    
    /**
     * Obtener ventas mensuales para gráfico
     */
    private function getVentasMensuales() {
        $sql = "SELECT DATE_FORMAT(fecha_venta, '%Y-%m') as mes,
                       COUNT(*) as total_ventas,
                       COALESCE(SUM(total), 0) as monto_total
                FROM ventas
                WHERE estado = 'pagada'
                AND fecha_venta >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(fecha_venta, '%Y-%m')
                ORDER BY mes ASC";
        
        return $this->db->query($sql);
    }
    
    /**
     * Obtener productos por categoría
     */
    private function getProductosPorCategoria() {
        $sql = "SELECT c.nombre as categoria,
                       COUNT(p.id) as total_productos,
                       COALESCE(SUM(p.stock * p.costo), 0) as valor_costo
                FROM categorias c
                LEFT JOIN productos p ON c.id = p.categoria_id AND p.estado = 'activo'
                WHERE c.estado = 1
                GROUP BY c.id, c.nombre
                ORDER BY total_productos DESC";
        
        return $this->db->query($sql);
    }
    
    /**
     * Obtener ventas diarias del mes actual
     */
    private function getVentasDiarias() {
        $sql = "SELECT DATE(fecha_venta) as fecha,
                       COUNT(*) as total_ventas,
                       COALESCE(SUM(total), 0) as monto_total
                FROM ventas
                WHERE estado = 'pagada'
                AND MONTH(fecha_venta) = MONTH(CURRENT_DATE)
                AND YEAR(fecha_venta) = YEAR(CURRENT_DATE)
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha ASC";
        
        return $this->db->query($sql);
    }
    
    /**
     * Obtener notificaciones del sistema
     */
    public function getNotifications() {
        if (!isAuthenticated()) {
            jsonResponse(['error' => 'No autorizado'], 401);
        }
        
        try {
            $notifications = [];
            
            // Productos con stock bajo
            $lowStockCount = $this->db->count('productos', [
                'estado' => 'activo'
            ], "stock <= stock_minimo");
            
            if ($lowStockCount > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'message' => "{$lowStockCount} producto(s) con stock bajo",
                    'url' => 'productos?filter=low_stock'
                ];
            }
            
            // Ventas pendientes de pago
            $pendingSales = $this->db->count('ventas', ['estado' => 'pendiente']);
            
            if ($pendingSales > 0) {
                $notifications[] = [
                    'type' => 'info',
                    'message' => "{$pendingSales} venta(s) pendientes de pago",
                    'url' => 'ventas?filter=pending'
                ];
            }
            
            jsonResponse(['success' => true, 'notifications' => $notifications]);
            
        } catch (Exception $e) {
            logActivity("Error obteniendo notificaciones: " . $e->getMessage(), 'ERROR');
            jsonResponse(['error' => 'Error interno del sistema'], 500);
        }
    }
}
?>

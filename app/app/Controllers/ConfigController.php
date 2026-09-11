<?php
/**
 * Controlador de Configuración
 * TECNOXPERT - Sistema de Inventarios y Facturación
 */

require_once __DIR__ . '/../Lib/Database.php';
require_once __DIR__ . '/../Lib/Auth.php';

class ConfigController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    public function index() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        return [
            'view' => 'configuracion/index',
            'data' => [
                'title' => 'Configuración - ' . APP_NAME,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function empresa() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        $config = $this->getConfig();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'empresa_nombre' => sanitizeInput($_POST['empresa_nombre'] ?? ''),
                'empresa_nit' => sanitizeInput($_POST['empresa_nit'] ?? ''),
                'empresa_direccion' => sanitizeInput($_POST['empresa_direccion'] ?? ''),
                'empresa_ciudad' => sanitizeInput($_POST['empresa_ciudad'] ?? ''),
                'empresa_telefono' => sanitizeInput($_POST['empresa_telefono'] ?? ''),
                'empresa_email' => sanitizeInput($_POST['empresa_email'] ?? ''),
                'empresa_website' => sanitizeInput($_POST['empresa_website'] ?? '')
            ];
            
            try {
                foreach ($data as $clave => $valor) {
                    $this->db->update('config', ['valor' => $valor], 'clave = ?', [$clave]);
                }
                
                logActivity("Configuración de empresa actualizada", 'INFO', getCurrentUserId());
                redirect('configuracion/empresa?success=updated');
                
            } catch (Exception $e) {
                logActivity("Error actualizando configuración: " . $e->getMessage(), 'ERROR');
                redirect('configuracion/empresa?error=update_failed');
            }
        }
        
        return [
            'view' => 'configuracion/empresa',
            'data' => [
                'title' => 'Configuración de Empresa - ' . APP_NAME,
                'config' => $config,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function impuestos() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        $config = $this->getConfig();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'iva_porcentaje' => (float)($_POST['iva_porcentaje'] ?? 19),
                'retefuente_porcentaje' => (float)($_POST['retefuente_porcentaje'] ?? 2.5),
                'reteiva_porcentaje' => (float)($_POST['reteiva_porcentaje'] ?? 15),
                'reteica_porcentaje' => (float)($_POST['reteica_porcentaje'] ?? 9)
            ];
            
            try {
                foreach ($data as $clave => $valor) {
                    $this->db->update('config', ['valor' => $valor], 'clave = ?', [$clave]);
                }
                
                logActivity("Configuración de impuestos actualizada", 'INFO', getCurrentUserId());
                redirect('configuracion/impuestos?success=updated');
                
            } catch (Exception $e) {
                logActivity("Error actualizando impuestos: " . $e->getMessage(), 'ERROR');
                redirect('configuracion/impuestos?error=update_failed');
            }
        }
        
        return [
            'view' => 'configuracion/impuestos',
            'data' => [
                'title' => 'Configuración de Impuestos - ' . APP_NAME,
                'config' => $config,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function consecutivos() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        $config = $this->getConfig();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'factura_prefix' => sanitizeInput($_POST['factura_prefix'] ?? 'FAC'),
                'compra_prefix' => sanitizeInput($_POST['compra_prefix'] ?? 'COM'),
                'nota_credito_prefix' => sanitizeInput($_POST['nota_credito_prefix'] ?? 'NC'),
                'nota_debito_prefix' => sanitizeInput($_POST['nota_debito_prefix'] ?? 'ND')
            ];
            
            try {
                foreach ($data as $clave => $valor) {
                    $this->db->update('config', ['valor' => $valor], 'clave = ?', [$clave]);
                }
                
                logActivity("Configuración de consecutivos actualizada", 'INFO', getCurrentUserId());
                redirect('configuracion/consecutivos?success=updated');
                
            } catch (Exception $e) {
                logActivity("Error actualizando consecutivos: " . $e->getMessage(), 'ERROR');
                redirect('configuracion/consecutivos?error=update_failed');
            }
        }
        
        return [
            'view' => 'configuracion/consecutivos',
            'data' => [
                'title' => 'Configuración de Consecutivos - ' . APP_NAME,
                'config' => $config,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function usuarios() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        $page = $_GET['page'] ?? 1;
        $search = $_GET['search'] ?? '';
        
        $usersData = $this->auth->getAllUsers($page);
        $roles = $this->auth->getRoles();
        
        return [
            'view' => 'configuracion/usuarios',
            'data' => [
                'title' => 'Gestión de Usuarios - ' . APP_NAME,
                'users' => $usersData['users'],
                'pagination' => $usersData,
                'roles' => $roles,
                'search' => $search,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function crearUsuario() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('configuracion/usuarios');
        }
        
        $data = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'nombre' => sanitizeInput($_POST['nombre'] ?? ''),
            'apellido' => sanitizeInput($_POST['apellido'] ?? ''),
            'rol_id' => (int)($_POST['rol_id'] ?? 0),
            'activo' => 1
        ];
        
        $result = $this->auth->createUser($data);
        
        if ($result['success']) {
            redirect('configuracion/usuarios?success=user_created');
        } else {
            redirect('configuracion/usuarios?error=' . urlencode($result['message']));
        }
    }
    
    public function facturacionElectronica() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        $parametros = $this->db->findAll('fe_parametros', [], 'id DESC', '1');
        $parametro = $parametros[0] ?? null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'software_id' => sanitizeInput($_POST['software_id'] ?? ''),
                'pin' => sanitizeInput($_POST['pin'] ?? ''),
                'proveedor_tecnologico' => sanitizeInput($_POST['proveedor_tecnologico'] ?? ''),
                'ambiente' => sanitizeInput($_POST['ambiente'] ?? 'test'),
                'resolucion_dian' => sanitizeInput($_POST['resolucion_dian'] ?? ''),
                'rango_inicial' => sanitizeInput($_POST['rango_inicial'] ?? ''),
                'rango_final' => sanitizeInput($_POST['rango_final'] ?? ''),
                'activo' => isset($_POST['activo']) ? 1 : 0
            ];
            
            try {
                if ($parametro) {
                    $this->db->update('fe_parametros', $data, 'id = ?', [$parametro['id']]);
                } else {
                    $this->db->insert('fe_parametros', $data);
                }
                
                logActivity("Configuración de facturación electrónica actualizada", 'INFO', getCurrentUserId());
                redirect('configuracion/facturacion-electronica?success=updated');
                
            } catch (Exception $e) {
                logActivity("Error actualizando FE: " . $e->getMessage(), 'ERROR');
                redirect('configuracion/facturacion-electronica?error=update_failed');
            }
        }
        
        return [
            'view' => 'configuracion/facturacion-electronica',
            'data' => [
                'title' => 'Facturación Electrónica - ' . APP_NAME,
                'parametro' => $parametro,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    public function backup() {
        if (!isAuthenticated()) {
            redirect('auth/login');
        }
        
        if (!isAdmin()) {
            redirect('dashboard?error=access_denied');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = REPORTS_PATH . $filename;
                
                // Generar backup
                $command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " " . 
                          (DB_PASS ? "-p" . DB_PASS : "") . " " . DB_NAME . " > " . $filepath;
                
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0) {
                    logActivity("Backup generado: {$filename}", 'INFO', getCurrentUserId());
                    redirect('configuracion/backup?success=backup_created&file=' . $filename);
                } else {
                    throw new Exception("Error ejecutando mysqldump");
                }
                
            } catch (Exception $e) {
                logActivity("Error generando backup: " . $e->getMessage(), 'ERROR');
                redirect('configuracion/backup?error=backup_failed');
            }
        }
        
        return [
            'view' => 'configuracion/backup',
            'data' => [
                'title' => 'Backup del Sistema - ' . APP_NAME,
                'menuItems' => getMenuItems()
            ]
        ];
    }
    
    private function getConfig() {
        $config = [];
        $result = $this->db->findAll('config');
        
        foreach ($result as $item) {
            $config[$item['clave']] = $item['valor'];
        }
        
        return $config;
    }
}
?>


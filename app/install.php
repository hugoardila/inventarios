<?php
/**
 * Script de Instalación - Sistema de Inventarios y Facturación
 * TECNOXPERT - XAMPP Debian 12
 */

// Configuración de la base de datos
$dbConfig = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'Fiddle72*',
    'name' => 'inventario_facturacion'
];

echo "==========================================\n";
echo "INSTALADOR - SISTEMA DE INVENTARIOS Y FACTURACIÓN\n";
echo "TECNOXPERT - XAMPP Debian 12\n";
echo "==========================================\n\n";

// Verificar PHP
echo "1. Verificando versión de PHP...\n";
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die("ERROR: Se requiere PHP 8.0 o superior. Versión actual: " . PHP_VERSION . "\n");
}
echo "✓ PHP " . PHP_VERSION . " - OK\n\n";

// Verificar extensiones
echo "2. Verificando extensiones PHP...\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        die("ERROR: Extensión PHP requerida no encontrada: {$ext}\n");
    }
    echo "✓ {$ext} - OK\n";
}
echo "\n";

// Conectar a MySQL
echo "3. Conectando a MySQL...\n";
try {
    $pdo = new PDO("mysql:host={$dbConfig['host']}", $dbConfig['user'], $dbConfig['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Conexión a MySQL exitosa\n\n";
} catch (PDOException $e) {
    die("ERROR: No se pudo conectar a MySQL: " . $e->getMessage() . "\n");
}

// Crear base de datos
echo "4. Creando base de datos...\n";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Base de datos '{$dbConfig['name']}' creada/verificada\n\n";
} catch (PDOException $e) {
    die("ERROR: No se pudo crear la base de datos: " . $e->getMessage() . "\n");
}

// Seleccionar base de datos
$pdo->exec("USE `{$dbConfig['name']}`");

// Importar esquema
echo "5. Importando esquema de base de datos...\n";
$schemaFile = __DIR__ . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    die("ERROR: Archivo schema.sql no encontrado\n");
}

try {
    $schema = file_get_contents($schemaFile);
    
    // Remover DELIMITER statements y procesar triggers por separado
    $schema = preg_replace('/DELIMITER \/\/.*?DELIMITER ;/s', '', $schema);
    
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !str_starts_with($statement, 'CREATE DATABASE') && !str_starts_with($statement, 'USE')) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignorar errores de tablas que ya existen o índices duplicados
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
            }
        }
    }
    echo "✓ Esquema importado correctamente\n\n";
} catch (PDOException $e) {
    die("ERROR: Error importando esquema: " . $e->getMessage() . "\n");
}

// Importar datos iniciales
echo "6. Importando datos iniciales...\n";
$seedFile = __DIR__ . '/database/seed.sql';
if (!file_exists($seedFile)) {
    die("ERROR: Archivo seed.sql no encontrado\n");
}

try {
    $seed = file_get_contents($seedFile);
    $statements = explode(';', $seed);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignorar errores de datos duplicados
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }
    echo "✓ Datos iniciales importados correctamente\n\n";
} catch (PDOException $e) {
    die("ERROR: Error importando datos iniciales: " . $e->getMessage() . "\n");
}

// Crear directorios necesarios
echo "7. Creando directorios necesarios...\n";
$directories = [
    'public/uploads',
    'public/reports',
    'logs'
];

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            echo "✓ Directorio creado: {$dir}\n";
        } else {
            echo "⚠ ADVERTENCIA: No se pudo crear el directorio: {$dir}\n";
        }
    } else {
        echo "✓ Directorio ya existe: {$dir}\n";
    }
}
echo "\n";

// Verificar permisos
echo "8. Verificando permisos...\n";
foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_writable($path)) {
        echo "✓ Permisos correctos: {$dir}\n";
    } else {
        echo "⚠ ADVERTENCIA: Directorio no escribible: {$dir}\n";
        echo "   Ejecute: chmod 755 " . $path . "\n";
    }
}
echo "\n";

// Verificar archivos críticos
echo "9. Verificando archivos críticos...\n";
$criticalFiles = [
    'config/config.php',
    'public/index.php',
    'public/.htaccess',
    'app/Lib/Database.php',
    'app/Lib/Auth.php'
];

foreach ($criticalFiles as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✓ Archivo encontrado: {$file}\n";
    } else {
        echo "⚠ ADVERTENCIA: Archivo no encontrado: {$file}\n";
    }
}
echo "\n";

// Verificar tablas creadas
echo "10. Verificando tablas de base de datos...\n";
$requiredTables = [
    'usuarios', 'roles', 'productos', 'categorias', 'marcas',
    'clientes', 'proveedores', 'ventas', 'venta_items',
    'compras', 'compra_items', 'inventario_movimientos', 'config'
];

foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Tabla encontrada: {$table}\n";
        } else {
            echo "⚠ ADVERTENCIA: Tabla no encontrada: {$table}\n";
        }
    } catch (PDOException $e) {
        echo "⚠ ERROR verificando tabla {$table}: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Verificar usuarios iniciales
echo "11. Verificando usuarios iniciales...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✓ Usuarios en la base de datos: {$count}\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT username, email FROM usuarios LIMIT 3");
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$user['username']} ({$user['email']})\n";
        }
    }
} catch (PDOException $e) {
    echo "⚠ ERROR verificando usuarios: " . $e->getMessage() . "\n";
}
echo "\n";

// Información de acceso
echo "==========================================\n";
echo "INSTALACIÓN COMPLETADA\n";
echo "==========================================\n\n";

echo "🎉 ¡El sistema ha sido instalado correctamente!\n\n";

echo "📋 INFORMACIÓN DE ACCESO:\n";
echo "URL: http://localhost/local/public/\n";
echo "Usuario Admin: admin@local\n";
echo "Contraseña: admin123\n\n";

echo "📋 USUARIOS POR DEFECTO:\n";
echo "- Admin: admin@local / admin123\n";
echo "- Vendedor: vendedor@local / admin123\n";
echo "- Consulta: consulta@local / admin123\n\n";

echo "🔧 PRÓXIMOS PASOS:\n";
echo "1. Acceda al sistema con las credenciales de admin\n";
echo "2. Configure los datos de su empresa\n";
echo "3. Configure impuestos y consecutivos\n";
echo "4. Cambie las contraseñas por defecto\n";
echo "5. Comience a usar el sistema\n\n";

echo "📞 SOPORTE:\n";
echo "Ingeniero: Hugo Alberto Ardila Molina\n";
echo "Email: contacto@tecnoxpert.com\n";
echo "Teléfono: \n\n";

echo "⚠ IMPORTANTE:\n";
echo "- Cambie las contraseñas por defecto\n";
echo "- Configure HTTPS en producción\n";
echo "- Haga backups regulares\n";
echo "- Monitoree los logs del sistema\n\n";

echo "==========================================\n";
echo "¡Gracias por usar TECNOXPERT!\n";
echo "==========================================\n";
?>

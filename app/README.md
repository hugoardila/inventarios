# Sistema de Inventarios y Facturación - TECNOXPERT

Sistema completo de gestión de inventarios y facturación desarrollado para XAMPP en Debian 12.

## 🚀 Características

### ✅ Módulos Principales
- **Dashboard** con KPIs y estadísticas
- **Gestión de Productos** con categorías y marcas
- **Gestión de Clientes** con tipos de documento
- **Gestión de Proveedores** con información completa
- **Facturación** con cálculo automático de impuestos
- **Compras** con entrada automática de inventario
- **Reportes** exportables a Excel/CSV
- **Configuración** avanzada del sistema

### 🔐 Sistema de Autenticación
- **3 Roles**: Admin, Empleado, Consulta
- **Permisos granulares** por módulo
- **Auditoría** de acciones críticas
- **Bloqueo** por intentos fallidos
- **Recuperación** de contraseñas

### 💰 Facturación Completa
- **IVA automático** (19% por defecto)
- **Retenciones**: Retefuente, ReteIVA, ReteICA
- **Múltiples formas de pago**
- **Notas crédito/débito**
- **PDF** de facturas
- **Consecutivos automáticos**

### 📊 Reportes Avanzados
- **Inventario valorizado**
- **Ventas por período**
- **Impuestos y retenciones**
- **Kardex de productos**
- **Exportación a Excel/CSV**

### 🔮 Facturación Electrónica (Pendiente)
- **Módulo preparado** para DIAN
- **Parámetros configurables**
- **Logs de envío**
- **Espacio para CUFE/QR**

## 🛠️ Requisitos del Sistema

- **Sistema Operativo**: Debian 12
- **Servidor Web**: XAMPP (Apache + MySQL + PHP)
- **PHP**: 8.0 o superior
- **MySQL/MariaDB**: 10.5 o superior
- **Navegador**: Chrome, Firefox, Safari, Edge

## 📦 Instalación

### 1. Preparar XAMPP
```bash
# Asegurarse de que XAMPP esté funcionando
sudo /opt/lampp/lampp start
```

### 2. Crear Base de Datos
```bash
# Acceder a MySQL
mysql -u root -p

# Crear base de datos
CREATE DATABASE inventario_facturacion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Importar Esquema
```bash
# Importar estructura de tablas
mysql -u root -p inventario_facturacion < database/schema.sql

# Importar datos iniciales
mysql -u root -p inventario_facturacion < database/seed.sql
```

### 4. Configurar Permisos
```bash
# Crear directorios necesarios
mkdir -p public/uploads
mkdir -p public/reports
mkdir -p logs

# Asignar permisos
chmod 755 public/uploads
chmod 755 public/reports
chmod 755 logs
```

### 5. Verificar Configuración
Editar `config/config.php` si es necesario:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventario_facturacion');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 🔑 Acceso Inicial

### Acceso al Sistema
El sistema utiliza autenticación segura a través de la base de datos.

### URL de Acceso
```
http://localhost/local/public/
```

## 📋 Estructura del Proyecto

```
local/
├── app/
│   ├── Controllers/     # Controladores MVC
│   ├── Models/         # Modelos de datos
│   ├── Views/          # Vistas y templates
│   └── Lib/            # Librerías (DB, Auth, etc.)
├── config/
│   └── config.php      # Configuración principal
├── database/
│   ├── schema.sql      # Estructura de BD
│   └── seed.sql        # Datos iniciales
├── public/
│   ├── css/            # Estilos CSS
│   ├── js/             # JavaScript
│   ├── img/            # Imágenes
│   ├── uploads/        # Archivos subidos
│   ├── reports/        # Reportes generados
│   ├── index.php       # Front controller
│   └── .htaccess       # URLs limpias
└── logs/               # Logs del sistema
```

## 🔧 Configuración Avanzada

### Configuración de Empresa
1. Acceder como **Admin**
2. Ir a **Configuración > Datos de Empresa**
3. Actualizar información:
   - Nombre de la empresa
   - NIT/RUT
   - Dirección
   - Teléfono
   - Email

### Configuración de Impuestos
1. Ir a **Configuración > Impuestos**
2. Configurar porcentajes:
   - IVA (por defecto 19%)
   - Retefuente (por defecto 2.5%)
   - ReteIVA (por defecto 15%)
   - ReteICA (por defecto 9%)

### Configuración de Consecutivos
1. Ir a **Configuración > Consecutivos**
2. Configurar prefijos:
   - Facturas: FAC
   - Compras: COM
   - Notas Crédito: NC
   - Notas Débito: ND

## 📊 Roles y Permisos

### 👑 Administrador
- **Acceso total** a todos los módulos
- **Gestión de usuarios** y roles
- **Configuración** del sistema
- **Backups** y mantenimiento

### 👨‍💼 Empleado/Vendedor
- **Facturación** completa
- **Gestión de inventario**
- **Clientes** y proveedores
- **Reportes** básicos

### 👁️ Consulta
- **Solo lectura** en todos los módulos
- **Reportes** y consultas
- **Sin modificación** de datos

## 🔒 Seguridad

### Características Implementadas
- **Contraseñas hasheadas** con bcrypt
- **Sesiones seguras** con timeout
- **CSRF tokens** en formularios
- **Sanitización** de entradas
- **Prepared statements** en BD
- **Auditoría** de acciones críticas
- **Bloqueo** por intentos fallidos

### Recomendaciones de Seguridad
1. **Cambiar contraseñas** por defecto
2. **Configurar HTTPS** en producción
3. **Hacer backups** regulares
4. **Monitorear logs** del sistema
5. **Actualizar** regularmente

## 📈 Uso del Sistema

### Gestión de Productos
1. **Crear categorías** y marcas
2. **Registrar productos** con códigos de barras
3. **Configurar precios** y costos
4. **Establecer stock mínimo**

### Facturación
1. **Seleccionar cliente**
2. **Agregar productos** al carrito
3. **Aplicar descuentos** si es necesario
4. **Generar factura** con PDF
5. **Registrar pagos**

### Compras
1. **Seleccionar proveedor**
2. **Agregar productos** comprados
3. **Registrar costos** y cantidades
4. **Generar comprobante**
5. **Actualizar inventario** automáticamente

### Reportes
1. **Seleccionar tipo** de reporte
2. **Configurar filtros** (fechas, productos, etc.)
3. **Generar reporte**
4. **Exportar** a Excel/CSV

## 🐛 Solución de Problemas

### Error de Conexión a BD
```bash
# Verificar que MySQL esté corriendo
sudo /opt/lampp/lampp status

# Verificar credenciales en config.php
# Verificar que la BD existe
mysql -u root -p -e "SHOW DATABASES;"
```

### Error de Permisos
```bash
# Verificar permisos de directorios
ls -la public/uploads/
ls -la public/reports/
ls -la logs/

# Corregir permisos si es necesario
chmod 755 public/uploads/
chmod 755 public/reports/
chmod 755 logs/
```

### Error de Sesión
```bash
# Verificar configuración de PHP
php -i | grep session

# Limpiar sesiones si es necesario
rm -rf /tmp/sess_*
```

## 📞 Soporte

### Información de Contacto
- **Empresa**: TECNOXPERT
- **Ingeniero**: Hugo Alberto Ardila Molina
- **Email**: contacto@tecnoxpert.com
- **Teléfono**: 
- **LinkedIn**: [Hugo Ardila](https://www.linkedin.com/in/hugo-ardila-80b6bb170/)

### Logs del Sistema
Los logs se encuentran en:
- **Actividad**: `logs/activity.log`
- **Errores PHP**: `logs/php_errors.log`
- **Errores del sistema**: `logs/system.log`

## 🔄 Actualizaciones

### Versión Actual
- **Versión**: 1.0.0
- **Fecha**: Enero 2025
- **Compatibilidad**: XAMPP Debian 12

### Próximas Funcionalidades
- [ ] **Facturación Electrónica** (DIAN)
- [ ] **App móvil** para inventario
- [ ] **Integración** con pasarelas de pago
- [ ] **Dashboard** con gráficos avanzados
- [ ] **Notificaciones** por email

## 📄 Licencia

Este sistema fue desarrollado específicamente para TECNOXPERT.
Todos los derechos reservados.

---

**Desarrollado con ❤️ para TECNOXPERT**


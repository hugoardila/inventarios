# Sistema de Inventarios

Aplicación PHP para administrar inventario, compras, ventas, proveedores, clientes y reportes operativos.

## Módulos

- Dashboard y control de existencias.
- Productos, categorías y marcas.
- Compras y proveedores.
- Ventas, clientes y movimientos de inventario.
- Gastos, reportes y exportaciones.
- Usuarios, roles, permisos y auditoría.
- Facturación y documentos electrónicos configurables.
- Impresión de inventario y comprobantes.

## Tecnologías

- PHP y Apache.
- MySQL/MariaDB con PDO.
- Bootstrap, JavaScript y CSS.
- Docker Compose.

## Inicio local

1. Copia `.env.example` como `.env`.
2. Crea la base de datos y aplica `app/database/schema.sql`.
3. Configura usuario, contraseña, URL base y `mysqldump`.
4. Ejecuta `docker compose up --build`.

El esquema se incluye para desarrollo; no se incluyen semillas, clientes, proveedores, productos reales, documentos tributarios ni reportes generados.

# 🔧 Configuración de Facturación Electrónica

## 📋 Información Necesaria para Configurar

### 1. **Software ID**
- **¿Qué es?** Identificador único otorgado por la DIAN al registrar tu software
- **Formato:** 10-20 caracteres alfanuméricos (solo letras mayúsculas y números)
- **Ejemplo:** `TECNOXPERT2024` o `SOFTWARE123456`
- **¿Dónde obtenerlo?** En el portal de la DIAN después de registrar tu software

### 2. **PIN**
- **¿Qué es?** Código de autorización numérico de la DIAN
- **Formato:** 4-8 dígitos numéricos
- **Ejemplo:** `123456` o `87654321`
- **¿Dónde obtenerlo?** Te lo proporciona la DIAN al autorizar tu software

### 3. **Resolución DIAN**
- **¿Qué es?** Número de resolución que autoriza la facturación electrónica
- **Formato:** Número numérico
- **Ejemplo:** `18764000012345`
- **¿Dónde obtenerlo?** En la resolución oficial de la DIAN

### 4. **Fecha de Resolución**
- **¿Qué es?** Fecha de emisión de la resolución DIAN
- **Formato:** AAAA-MM-DD
- **Ejemplo:** `2024-01-15`
- **¿Dónde obtenerlo?** En la resolución oficial de la DIAN

### 5. **Rangos de Numeración**
- **Rango Inicial:** Número desde el cual puedes empezar a facturar
- **Rango Final:** Número hasta el cual puedes facturar
- **Ejemplo:** Inicial: `1`, Final: `1000`
- **¿Dónde obtenerlo?** En la resolución DIAN se especifica el rango autorizado

### 6. **Certificado Digital (.p12)**
- **¿Qué es?** Archivo de certificado digital emitido por la DIAN
- **Formato:** Archivo .p12
- **¿Dónde obtenerlo?** Debes solicitarlo a la DIAN con tu información fiscal

### 7. **Contraseña del Certificado**
- **¿Qué es?** Contraseña para acceder al certificado digital
- **Formato:** Texto alfanumérico
- **¿Dónde obtenerlo?** Te la proporciona la DIAN junto con el certificado

## 🏢 Proveedores Tecnológicos Soportados

### **DIAN (Oficial)**
- **Ventajas:** Gratuito, oficial
- **Desventajas:** Requiere más configuración técnica
- **Recomendado para:** Empresas con recursos técnicos

### **FACTURADOR**
- **Ventajas:** Fácil configuración, soporte técnico
- **Desventajas:** Costo mensual
- **Recomendado para:** Empresas que prefieren facilidad

### **OTRO**
- **Ventajas:** Flexibilidad
- **Desventajas:** Requiere configuración personalizada
- **Recomendado para:** Empresas con proveedor específico

## 🌐 Ambientes

### **Pruebas (Test)**
- **Propósito:** Para probar la configuración sin afectar facturas reales
- **URL:** Servidores de prueba de la DIAN
- **Recomendado:** Siempre empezar aquí

### **Producción (Prod)**
- **Propósito:** Para facturación real con la DIAN
- **URL:** Servidores oficiales de la DIAN
- **Recomendado:** Solo después de probar en test

## 📝 Pasos para Configurar

1. **Obtener Software ID y PIN** de la DIAN
2. **Obtener Resolución DIAN** con número y fecha
3. **Obtener Certificado Digital** (.p12) de la DIAN
4. **Configurar en el sistema** con los datos obtenidos
5. **Probar conexión** en ambiente de pruebas
6. **Validar configuración** antes de usar en producción

## ⚠️ Consideraciones Importantes

- **Siempre probar primero** en ambiente de pruebas
- **Verificar rangos de numeración** antes de facturar
- **Mantener certificados actualizados**
- **Respaldo de configuración** antes de cambios
- **Monitorear logs** de envío a la DIAN

## 🆘 Soporte

Si necesitas ayuda con la configuración:
1. Revisa los logs del sistema
2. Verifica que todos los campos estén completos
3. Prueba la conexión paso a paso
4. Contacta al soporte técnico si persisten los errores


# Changelog

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-es/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [9.0.1] - 2026-11-19

### Fixed

- La opción de no reducir el stock en pagos pendientes también controla la reducción automática de WooCommerce.
- La sincronización desde el administrador consulta el último ID de pago y valida el tipo y estado de la orden.
- La eliminación de la tabla `epayco_order` utiliza la API de base de datos de WordPress.
- Se evita crear registros de stock heredados; el manejo de inventario usa los niveles nativos de WooCommerce.

## [9.0.0] - 2026-11-18

### Changed

- Cambio visual de marca a Davipay; se mantienen intactos los flujos de pago.
- Compatibilidad de lectura para metadatos de órdenes existentes de ePayco (`refPayco` y `epayco_meta_data`).

## [Unreleased]

### Changed

- **23 de octubre de 2025** - Actualización para versión 2 del checkout de ePayco
  - Actualizado el payload de configuración del checkout para incluir `checkout_version: "2"`
  - Implementada integración con el nuevo endpoint `payment/session/create` para crear sesiones de pago
  - Mejorada la configuración del checkout con soporte para `sessionId` dinámico
  - Actualizada la configuración JavaScript del checkout para usar el nuevo sistema de sesiones
  - Corregida la configuración de `autoClick` a `false` para mejor control del flujo de pago

### Added

- **Nuevos campos en el payload del checkout:**
  - `checkout_version`: "2" - Especifica la versión del checkout a utilizar
  - `sessionId`: Campo dinámico obtenido de la respuesta de `payment/session/create`
  - `autoClick`: false - Control mejorado del flujo de apertura del checkout

### Technical Details

- Modificado `classes/class-wc-gateway-epayco.php`:
  - Actualizada la función `generate_epayco_form()` para incluir llamada a `getEpaycoSessionId()`
  - Implementada lógica para obtener `sessionId` desde la respuesta de la API
  - Mejorada la configuración del objeto checkout JavaScript con parámetros de sesión
  - Actualizada la estructura del payload para compatibilidad con checkout v2

### Security

- Implementado manejo seguro de tokens Bearer para autenticación con API ePayco
- Mejorada la validación de respuestas de la API antes de procesar datos de sesión

## [Previous Versions]

### [1.1.0] - Versiones anteriores

#### Funcionalidades Base

- Funcionalidades base del plugin ePayco para WooCommerce
- Integración con API de ePayco para procesamiento de pagos
- Gestión automática de órdenes y estados de pago
- Sistema de validación de firmas para seguridad de transacciones

#### Características Principales

- Soporte para múltiples métodos de pago (tarjetas de crédito, débito, PSE, etc.)
- Manejo automático de stock y restauración en caso de fallos
- Sistema de cron jobs para sincronización de estados
- Configuración de URLs de confirmación y respuesta personalizables
- Soporte para modo de pruebas y producción
- Validación de llaves públicas y privadas
- Manejo de múltiples monedas (COP, USD)

#### Implementación Técnica

- Clase principal `WC_Gateway_Epayco` extendiendo `WC_Payment_Gateway`
- Sistema de logs integrado para debugging
- Manejo de excepciones y errores robusto
- Validación de firmas SHA256 para seguridad
- API REST endpoints para confirmación y validación de pagos
- Sistema de metadata para tracking de transacciones

#### Características de Seguridad

- Validación de firmas digitales
- Manejo seguro de credenciales
- Sanitización de datos de entrada
- Protección contra acceso directo a archivos
- Validación de IPs y datos de transacción

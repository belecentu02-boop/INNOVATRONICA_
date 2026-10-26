# Fase 4 — Stock y ubicaciones

## Alcance
Consulta para ADMIN, SUPERVISOR_COMERCIAL y VENDEDOR_INTERNO. Búsqueda por SKU, nombre, categoría o ubicación; filtros por categoría y disponibilidad; paginación de ocho productos; totales de todos los resultados filtrados. Diseño industrial con fichas móviles.

Disponible = físico − comprometido. Comprometido suma reservas ACTIVA, PENDIENTE_AUTORIZACION y CONFIRMADA. Disponible cero es QUIEBRE; positivo hasta el mínimo inclusive es CRITICO; superior es NORMAL. Un saldo negativo se presenta como INCONSISTENTE para no ocultar errores de datos.

Antes de consultar se vencen reservas ACTIVA y PENDIENTE_AUTORIZACION con fecha_vencimiento <= NOW(). Cada transición se registra con actor NULL en log_acciones dentro de la misma transacción; si falla la auditoría se revierte. CONFIRMADA no vence. Esta consulta efectúa el mantenimiento requerido por decisiones.md sección 7.1 aunque no esté activo el evento MySQL. No modifica stock físico.

Actualización cada 15 segundos con la pestaña visible. Un error conserva los últimos datos con advertencia; una sesión vencida detiene la actualización. Los filtros invalidan respuestas anteriores.

## Instalación
Conservar la implementación de Catálogo ya instalada. Extraer el paquete en la raíz del proyecto manteniendo carpetas. Reemplaza api.php y stock.php; agrega includes/stock.php, assets/css/stock.css, assets/js/stock.js y documentación/prueba. No requiere ejecutar SQL ni reemplazar auth.php, configuración o estilos compartidos.

## Validación en WAMP
1. Abrir Stock con ADMIN y recargar con Ctrl+F5. Confirmar que carga sin errores de consola o PHP.
2. Buscar por SKU/nombre/ubicación, cambiar categoría/estado y limpiar filtros. Confirmar resultados vacíos y paginación con más de ocho productos.
3. Comparar SF, SC y SD contra los datos de prueba. Un producto con SF=0 y mínimo=7 debe aparecer en QUIEBRE.
4. Probar Stock con SUPERVISOR_COMERCIAL y VENDEDOR_INTERNO. Verificar que Catálogo y Gestión de usuarios continúan restringidos a ADMIN.
5. Comprobar visualmente escritorio y teléfono, incluida paginación y lectura de ubicaciones.
6. En una copia de la base, verificar reservas activas/pendientes vencidas: se vuelven EXPIRADA una vez y generan un log con actor NULL, sin cambiar SF; una CONFIRMADA vencida permanece comprometida. No generar reservas de prueba en producción.
7. Comprobar actualización periódica y recuperación ante pérdida de conexión.

## Pruebas automatizadas y límites
La prueba tests/stock.php usa PDO SQLite en memoria, sin conectarse a la base del proyecto. Sustituye FOR UPDATE y NOW() únicamente en su adaptador. Cubre 28 comprobaciones de cálculos, estados, vencimientos, idempotencia, auditoría y rollback, filtros y paginación. Ejecutar con PHP que tenga pdo_sqlite y mbstring: php tests/stock.php.

La prueba aislada no valida bloqueos ni concurrencia MySQL. La integración real y la presentación visual requieren validación en WAMP. No se implementan ajustes físicos, reservas nuevas, facturación ni la pantalla de alertas; son bloques posteriores.

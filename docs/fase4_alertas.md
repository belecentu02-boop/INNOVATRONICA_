# Fase 4 — Alertas de reabastecimiento

## Instalación
Aplicar sobre fase-4/catalogo-stock-alertas con Catálogo y Stock ya instalados y probados. Extraer en la raíz manteniendo carpetas. Reemplaza api.php, includes/stock.php, reabastecimiento.php, tests/stock.php y docs/seguridad.md. Agrega assets/js/reabastecimiento.js y este documento. Reutiliza assets/css/stock.css existente. No requiere SQL ni cambios de configuración.

## Comportamiento
ADMIN y SUPERVISOR_COMERCIAL acceden. VENDEDOR_INTERNO no tiene acceso. El backend siempre restringe resultados a disponible <= mínimo, independientemente de filtros. Disponible = físico − comprometido; aplica las mismas reglas de vencimiento y auditoría ya verificadas en Stock.

Orden de prioridad: inconsistencias negativas, quiebre total (cero), crítico (positivo hasta el mínimo inclusive). Sugerido a reponer = (mínimo × 2) − disponible. Un mínimo cero y disponible cero genera alerta de quiebre con sugerencia cero conforme a la fórmula; revisar el mínimo configurado. Una inconsistencia negativa debe investigarse antes de ejecutar una compra.

Búsqueda por SKU, nombre, categoría o ubicación; filtro por categoría/estado; ocho componentes por página. Totales de todos los resultados filtrados. Actualización cada 15 segundos cuando la pestaña está visible. No se registran ingresos físicos ni compras.

## Prueba final en WAMP
1. ADMIN: abrir Alertas de reabastecimiento y recargar Ctrl+F5. No deben aparecer productos con disponible mayor al mínimo.
2. Componente de prueba con físico=0, comprometido=0 y mínimo=7: QUIEBRE TOTAL, sugerido a reponer=14. Un componente con disponible=5 y mínimo=5: CRITICO, sugerido=5.
3. Probar búsqueda por SKU/ubicación, categoría, nivel, limpiar filtros, resultados vacíos y paginación si hay más de ocho alertas.
4. SUPERVISOR: la pantalla debe cargar. VENDEDOR: abrir directamente reabastecimiento.php y api.php?accion=listar_alertas debe denegar el acceso.
5. Confirmar que Stock aún carga para los tres roles y Catálogo sigue restringido a ADMIN.
6. Verificar teléfono y computadora.

Pruebas automatizadas ejecutadas: sintaxis PHP/JS; 38 comprobaciones de cálculos, vencimientos, auditoría y filtros/paginación; 12 casos de permisos/API; DOM para columna sugerida y renderizado seguro. SQLite usa adaptador que elimina FOR UPDATE y fija NOW; no prueba bloqueos MySQL. No se afirma verificación visual automatizada.

## Cierre
Catálogo y Stock ya fueron probados por el usuario. Cuando estas pruebas finales de Alertas pasen, registrar commit/push del bloque y dejar cerrada funcionalmente la fase 4. No se inicia fase 5 en este paquete. No fusionar automáticamente con main.

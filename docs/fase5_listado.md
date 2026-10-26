# Fase 5 — Primer bloque: consulta de reservas

## Instalación
Aplicar sobre main en c4e8788 o una rama creada desde esa versión, con la fase 4 instalada. Crear una rama de trabajo antes de copiar. Extraer en la raíz del proyecto conservando las carpetas: reemplaza reservas.php y docs/seguridad.md; agrega reservas_api.php, includes/reservas.php, assets/js/reservas.js, assets/css/reservas.css, tests/reservas_listado.php y este documento. Requiere includes/stock.php y assets/css/stock.css de fase 4. No requiere SQL ni modifica esquema.

## Alcance
Listado y búsqueda por identificador, SKU o componente; filtro por estado; ocho reservas por página. Vendedor ve solo las propias. Supervisor y administrador ven todas. Las activas y pendientes muestran tiempo restante calculado desde hora del servidor y sincronizado cada 15 segundos con la pestaña visible. Las confirmadas no vencen. Ante errores se conservan los datos anteriores con un aviso. Se emplea el mantenimiento de vencimientos auditado existente en Stock.

## Prueba en WAMP
1. Ingresar como cada rol y abrir Reservas. Una lista vacía es correcta si aún no se crearon reservas.
2. Comprobar filtros, búsqueda, estado vacío, recarga, escritorio y teléfono.
3. Solo si hay reservas de prueba reales: verificar que cada vendedor vea únicamente las propias; supervisor y admin ven todas. Comprobar reloj, vencimiento y que confirmadas indiquen que no vencen.
4. Comprobar que Stock y Alertas sigan funcionando. Las pruebas de creación, autorización y concurrencia quedan para los siguientes bloques.

La prueba automatizada tests/reservas_listado.php requiere PHP con pdo_sqlite y mbstring. Crea únicamente una base SQLite en memoria y utiliza adaptador para NOW y FOR UPDATE. No valida concurrencia MySQL. El propio listado no crea reservas; para verificar permisos con datos deben existir registros de prueba en una base descartable.

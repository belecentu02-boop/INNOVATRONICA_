# Fase 4 — primer bloque: catálogo

## Alcance entregado

- Catálogo exclusivo de ADMIN, reutilizando api.php, sesión activa, cambio obligatorio y CSRF.
- Búsqueda por SKU, nombre, categoría o ubicación; filtro por categoría y paginación de ocho productos en el servidor.
- Alta y edición de nombre, categoría, ficha técnica, ubicación y stock mínimo.
- SKU normalizado a mayúsculas (máximo 30 caracteres) y fijo después del alta para preservar sus referencias.
- Ubicación alfanumérica con guiones opcionales, máximo 30 caracteres (por ejemplo A1-01).
- Stock mínimo entero entre 0 y 2147483647. La ficha técnica es texto descriptivo, no un enlace ni HTML.
- Alta atómica de producto e inventario, con stock físico cero, y auditoría transaccional.
- Edición atómica; nunca altera stock físico. No hay borrado de productos.
- Interfaz industrial con formulario modal accesible, fichas móviles y estados de envío.

No requiere cambios de esquema: se usan productos, inventario y log_acciones existentes.
No importar database.sql sobre una base en uso. No se incluyen datos ni contraseñas.
Stock disponible, reservas y alertas se implementan en los siguientes bloques de la fase 4.
No se declara la fase terminada.

## Instalación en la rama fase-4/catalogo-stock-alertas

1. Comprobar git status limpio y conservar una exportación de la base de trabajo antes de las pruebas.
2. Copiar api.php y catalogo.php a la raíz de C:\wamp64\www\INNOVATRONICA_\.
3. Agregar includes/catalogo.php, assets/css/catalogo.css y assets/js/catalogo.js.
4. Copiar docs/fase4_catalogo.md, docs/seguridad.md y tests/catalogo.php en sus carpetas.
5. No reemplazar auth.php, style.css, fuentes, configuración ni database.sql.
6. Abrir http://localhost/INNOVATRONICA_/catalogo.php y recargar con Ctrl+F5.

## Comprobaciones en WAMP — pendientes

Usar cuentas de prueba. No modificar productos reales para probar errores.

1. ADMIN: ver listado, buscar SKU/nombre/ubicación y filtrar categoría; cambiar páginas.
2. Crear TEST-CATALOGO-01: nombre con tildes/ñ, categoría Pruebas, ubicación Z1-01, mínimo 5.
3. Revisar productos e inventario: un registro por SKU, stock físico 0 y auditoría ALTA_PRODUCTO.
4. Intentar el mismo SKU: debe responder 409 y no duplicar inventario ni auditoría.
5. Editar nombre, ficha, ubicación y mínimo; comprobar EDICION_PRODUCTO y stock físico sin cambios.
6. Enviar mínimo negativo, decimal, enorme o campos vacíos: debe rechazar, aunque se manipule la petición fuera de HTML.
7. Consultar o modificar por API como supervisor/vendedor: 403; sin sesión: 401; con cambio obligatorio: 403 PASSWORD_CHANGE_REQUIRED.
8. POST sin CSRF válido: 403. GET de crear_producto/editar_producto: 405.
9. Buscar un texto inexistente: estado vacío; usar Limpiar filtros y Actualizar lista.
10. Nombre/ficha con texto <b>Prueba</b>: debe verse literalmente, nunca interpretarse como HTML.
11. Probar modal con teclado (Tab/Escape), teléfono y escritorio; guardar no debe permitir doble envío ni cierre durante la solicitud.
12. Simular error de red usando DevTools después de abrir el formulario: se conserva la entrada y se restablece Guardar. Si no se sabe si el servidor guardó, actualizar la lista antes de reintentar.
13. Confirmar que Gestión de Personal, login, menú móvil y cambio de contraseña siguen funcionando.
14. Ejecutar dos altas simultáneas del mismo SKU: exactamente una debe crear; la otra debe responder conflicto. Verificar bloqueo/rollback en MySQL.

Ejemplos de API (solo especificación; usar la sesión de prueba):
- GET api.php?accion=listar_productos&q=ESP32&pagina=1
- POST api.php: accion=crear_producto, csrf_token, sku, nombre, categoria, ficha_tecnica, ubicacion_deposito, umbral_minimo
- POST api.php: mismos campos, accion=editar_producto (el SKU identifica el registro, no se renombra)

## Pruebas ejecutadas en el entorno de preparación

- Lint PHP real mediante PHP-WASM de api.php, catalogo.php e includes/catalogo.php.
- 33 comprobaciones del servicio: límites, normalización, entradas inválidas, stock cero, conservación de stock al editar, auditoría, rollback, duplicado MySQL simulado, filtros, LIKE literal y paginación.
- 12 comprobaciones de API con autenticación simulada: sesión ausente/inactiva, cambio obligatorio, supervisor/vendedor, CSRF, método, validación, listado y creación 201.
- Pruebas DOM: no interpreta HTML de productos, modos alta/edición, doble envío, errores de red/API y recuperación de controles.

El servicio se probó con SQLite en memoria. El adaptador elimina FOR UPDATE solo en tests.
No se ha ejecutado el SQL contra MySQL ni se ha renderizado la interfaz en un navegador real.
Pruebas reproducibles sin tocar la base real: php tests/catalogo.php (PHP 8 y pdo_sqlite).

## Decisiones de implementación

Se sigue decisiones.md: ADMIN administra productos, stock comprometido es calculado y los movimientos de stock físico corresponden a los flujos de inventario/facturación.
Se conserva el sistema de diseño industrial aprobado (fuentes locales y SVG).
La modificación de api.php únicamente agrega tres acciones nuevas dentro del switch existente.
No se alteran los casos de Gestión de Personal ni sus comprobaciones comunes.

La skill visual referencia un generador situado en una ruta Windows no disponible aquí.
Se reutiliza el sistema de diseño ya aprobado, sin introducir dependencias ni reemplazarlo.

Solicitud base: comenzar fase 4 desde listado, alta y edición de componentes, conservando
seguridad, reglas de negocio y presentación industrial del proyecto existente.

# Auditoría de Seguridad PHP - Innovatrónica S.A.

> **Fecha de revisión:** 2026-10-21  
> **Alcance:** Fase 1 (Configuración de base de datos, módulos de autenticación, control de acceso y vistas)  
> **Skill aplicada:** `seguridad-php`  
> **Fuentes de referencia:** `docs/decisiones.md` (Secciones 3, 7 y 8) y `rules.md`  

---

## 1. Tabla de Hallazgos y Estado de Mitigación

| Archivo | Problema / Vulnerabilidad Analizada | Gravedad | Estado | Mitigación Aplicada |
| :--- | :--- | :---: | :---: | :--- |
| `config/db.php` | Exposición de detalles sensibles ante fallo de conexión a BD | **Alta** | **Corregido** | Bloque `try-catch` que envía el detalle técnico a `error_log` del servidor y despliega un mensaje genérico sin credenciales ni volcados de error. |
| `config/db.php` | Acceso web directo al archivo de configuración desde el navegador | **Media** | **Corregido** | Guardia `if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__))` que retorna HTTP 403 Forbidden si se invoca directamente. |
| `config/db.php` | Configuración insegura de PDO (posible emulación de prepares) | **Alta** | **Corregido** | Configurado explícitamente con `PDO::ATTR_EMULATE_PREPARES => false` y `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`. |
| `includes/auth.php` | Acceso web directo al módulo de funciones de autenticación | **Media** | **Corregido** | Guardia que bloquea la ejecución directa retornando HTTP 403 Forbidden. |
| `includes/auth.php` | Ausencia de encabezados HTTP de protección en el navegador | **Media** | **Corregido** | Incorporados encabezados de seguridad: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` y `X-XSS-Protection: 1; mode=block`. |
| `includes/auth.php` | Riesgo de secuestro de sesión (Session Hijacking / Fixation) | **Alta** | **Corregido** | Parámetros de cookies de sesión con `httponly => true` y `samesite => Lax`. Uso mandatorio de `session_regenerate_id(true)` tras autenticar credenciales. |
| `includes/auth.php` | Escape insuficiente en salidas HTML (riesgo de XSS) | **Alta** | **Corregido** | Función centralizada `h($s)` que aplica `htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8')` sobre todas las salidas dinámicas. |
| `login.php` | Ataques de falsificación de petición en sitios cruzados (CSRF) | **Alta** | **Corregido** | Generación de token criptográfico aleatorio por sesión (`random_bytes(32)`) y validación estricta con `hash_equals()` en cada envío POST. |
| `login.php` | Ataques de fuerza bruta sobre contraseñas de usuarios | **Alta** | **Corregido** | Control en base de datos (`intentos_fallidos` y `bloqueado_hasta`). Al quinto fallo consecutivo, la cuenta queda inhabilitada temporalmente por 15 minutos. |
| `login.php` | Acceso a cuentas de usuarios inactivos / desactivados | **Alta** | **Corregido** | Verificación de `activo === 1` tras autenticar credenciales; denegación con registro de auditoría `LOGIN_BLOQUEADO` en `log_acciones`. |
| `includes/auth.php` | Persistencia de sesiones abiertas tras desactivar un usuario | **Alta** | **Corregido** | Función `verificar_sesion_activa()` consulta en BD en cada petición. Si la cuenta fue desactivada, destruye inmediatamente la sesión/cookie y redirige a `login.php?error=desactivado`. |
| `usuarios.php` | Acceso a panel de gestión por usuarios no administradores | **Alta** | **Corregido** | `requerir_rol(['ADMIN'])` en el backend deniega el acceso con HTTP 403 Forbidden a vendedores y supervisores. |
| `usuarios.php` | Inyección de scripts maliciosos (XSS reflejado o persistente) | **Alta** | **Mitigación aplicada; prueba pendiente** | El modal de activación/desactivación construye la descripción con nodos DOM y `textContent`, sin interpolar datos del empleado en `innerHTML`. Falta prueba dinámica con valores de prueba que contengan etiquetas y eventos HTML. |
| `usuarios.php` | Confirmaciones con diálogos nativos vulnerables (`confirm`, `prompt`) | **Baja** | **Corregido** | Eliminado cualquier uso de diálogos nativos del navegador; implementados modales propios accesibles con control de Escape y clic fuera. |
| `api.php` | Invocación de endpoints sin autenticación o por rol indebido | **Alta** | **Corregido** | Verificación estricta en tiempo real con `esta_autenticado()`, `verificar_sesion_activa()` y `es_admin()`, retornando 401 y 403 Forbidden. |
| `api.php` | Falsificación de peticiones en endpoints de gestión (CSRF) | **Alta** | **Corregido** | Verificación mandatoria de token CSRF (`X-CSRF-Token` o payload) en todas las operaciones POST con `verificar_csrf()`. |
| `api.php` | Escalada de privilegios creando administradores desde la interfaz | **Alta** | **Corregido** | Validación en backend que limita la creación exclusivamente a `VENDEDOR_INTERNO` y `SUPERVISOR_COMERCIAL` (decisiones.md secc. 15). |
| `api.php` | Auto-desactivación o auto-degradación de rol del Administrador | **Alta** | **Corregido** | Comprobación defensiva contra `$_SESSION['u']['id_empleado']` que rechaza cualquier intento de auto-bloqueo o pérdida de rol ADMIN. |
| `api.php` | Reseteo inseguro de contraseñas o contraseñas débiles | **Alta** | **Corregido** | Validación de longitud mínima (8 caracteres), cifrado con `password_hash(..., PASSWORD_DEFAULT)` y registro en `log_acciones` sin almacenar claves en texto plano. |
| `includes/auth.php` | Evasión de cambio obligatorio de contraseña en vistas web | **Alta** | **Corregido** | Lectura en tiempo real de `debe_cambiar_password` desde BD en `requerir_login()`; forzar redirección a `cambiar_password.php` permitiendo únicamente `logout.php` (decisiones.md secc. 16). |
| `api.php` | Evasión de cambio obligatorio de contraseña consumiendo la API interna | **Alta** | **Corregido** | Guardia previa a chequeos de rol que retorna HTTP 403 con código JSON `PASSWORD_CHANGE_REQUIRED` si `debe_cambiar_password == 1`. |
| `api.php` | Creación y reseteo de usuarios sin forzar cambio de credencial temporal | **Alta** | **Corregido** | Parámetro `debe_cambiar_password = 1` grabado mandatoriamente en `crear_usuario` y `reset_password`. |
| `cambiar_password.php` | Ataques de fuerza bruta sobre la contraseña actual en el formulario de cambio | **Alta** | **Corregido** | Límite de 5 intentos fallidos consecutivos en `password_actual`; al quinto fallo se bloquea temporalmente por 15 minutos en BD (`bloqueado_hasta`). |
| `cambiar_password.php` | Reutilización de contraseña actual o contraseñas débiles | **Media** | **Corregido** | Validación de longitud mínima (8 caracteres) y rechazo si la nueva contraseña es idéntica a la actual (`password_verify`). |
| `cambiar_password.php` | Falsificación de petición (CSRF) y fijación de sesión tras cambio de clave | **Alta** | **Corregido** | Token CSRF mandatorio verificado con `hash_equals()`; regeneración de sesión obligatoria con `session_regenerate_id(true)` tras el cambio exitoso. |
| `api.php` | Respuestas de excepción que revelan detalles internos | **Media** | **Mitigación aplicada; prueba pendiente** | Las excepciones generales se registran con `error_log`; la respuesta JSON al cliente usa un mensaje genérico. Falta comprobar el comportamiento en ejecución. |
| `login.php` | Credenciales de demostración visibles en la interfaz | **Alta** | **Mitigación aplicada; revisar despliegue** | Se retiraron los botones y el JavaScript que rellenaban usuarios y contraseñas de prueba. Las cuentas semilla siguen siendo solo para desarrollo y deben cambiar sus credenciales antes de producción. |
| `migracion_fase3_1_debe_cambiar_password.sql` | Fallo si la columna ya existe y riesgo de conservar claves semilla conocidas | **Alta** | **Mitigación aplicada; prueba SQL pendiente** | La migración comprueba la existencia de la columna antes de alterar el esquema y marca para cambio obligatorio las cuentas que conservan los hashes semilla conocidos. Debe probarse en una base desechable con y sin la columna. |
| `api.php` | Colisión de códigos de empleado en altas concurrentes | **Media** | **Mitigación aplicada; prueba concurrente pendiente** | La generación se serializa con un bloqueo nominal MySQL y se libera en `finally`; el índice `UNIQUE` de `codigo_empleado` se mantiene como defensa final. Probar altas simultáneas en el entorno local. |
| `database.sql` | Las reservas vencidas no dejaban un evento individual de auditoría | **Media** | **Mitigación aplicada; prueba SQL pendiente** | El evento llama a un procedimiento que cambia cada reserva vencida y registra `RESERVA_EXPIRADA` en la misma transacción. Verificarlo con `event_scheduler` habilitado en una base desechable. |
| `cambiar_password.php` | La excepción general podía revelar detalles internos al usuario | **Media** | **Mitigación aplicada; prueba pendiente** | El mensaje visible ahora es genérico y el detalle técnico queda solo en `error_log`. |
| `api.php` | Las acciones administrativas podían quedar atribuidas al usuario objetivo en vez del administrador que las ejecutó | **Media** | **Mitigación aplicada; prueba de auditoría pendiente** | Los eventos de alta, edición, activación/desactivación y reseteo de contraseña usan el ID del administrador autenticado como actor; el objetivo queda descrito en el detalle. |
| `includes/auth.php` | El rol guardado en sesión podía quedar desactualizado después de que un administrador cambiara el rol de una cuenta | **Alta** | **Mitigación aplicada; prueba pendiente** | En cada petición autenticada se sincronizan rol e identidad desde la base de datos; verificar que una degradación de rol surta efecto inmediatamente. |
| `login.php` / `cambiar_password.php` | El token CSRF previo podía sobrevivir a la regeneración del ID de sesión | **Media** | **Mitigación aplicada; prueba pendiente** | El token CSRF se invalida al completar el login y el cambio de contraseña; el token nuevo se genera cuando se renderiza el siguiente formulario. |
| `logout.php` | El cierre de sesión aceptaba GET sin token CSRF | **Baja** | **Mitigación aplicada; prueba pendiente** | El cierre requiere POST y token CSRF; la navegación ahora usa formularios con token. |

---

## 2. Verificación de Búsquedas Rápidas (Checklist de la Skill)

1. **SQL Concatenado:**
   - Se auditó cada consulta en `api.php`, `usuarios.php`, `includes/auth.php`, `login.php` y `cambiar_password.php`.
   - **100% de las consultas utilizan sentencias preparadas con PDO** (`$pdo->prepare(...)` con parámetros posicionales `?`).
   - Ninguna variable se concatena directamente en sentencias SQL.
2. **Salidas sin escapar (Prevención XSS):**
   - Las vistas PHP revisadas usan `h()` o casteo a entero en las salidas dinámicas visibles.
   - Se corrigió la interpolación de nombre y código en el modal de `usuarios.php`. Esta corrección aún requiere una prueba dinámica; la presencia de otros usos de `innerHTML` debe evaluarse caso por caso, comprobando que los datos variables estén escapados o se construyan con nodos DOM.
3. **Endpoints y vistas sin chequeo de rol o estado:**
   - `usuarios.php` ejecuta `requerir_rol(['ADMIN'])` en el backend.
   - `api.php` comprueba `debe_cambiar_password_actual()` (403 PASSWORD_CHANGE_REQUIRED) y `es_admin()`.
   - `includes/auth.php` redirige de inmediato a `cambiar_password.php` si el cambio es obligatorio.
4. **Registro de Auditoría (Trazabilidad):**
   - Cada operación de alta (`ALTA_USUARIO`), edición (`EDICION_USUARIO`), activación/desactivación (`ACTIVAR_USUARIO`/`DESACTIVAR_USUARIO`), reseteo de clave (`RESETEO_PASSWORD`), cambio de clave (`CAMBIO_PASSWORD`), fallo de cambio (`PASSWORD_CAMBIO_FALLIDO`) y bloqueo temporal (`CUENTA_BLOQUEADA`) queda registrada en `log_acciones` con IP y timestamp.

---

## 3. Estado actual y limitaciones

Esta revisión estática identificó problemas que contradicen la conclusión anterior de cobertura completa. En la rama `fase-3-5/seguridad-estabilizacion` se aplicaron mitigaciones para el XSS del modal de `usuarios.php`, la exposición de excepciones generales en `api.php`, la visualización de credenciales de prueba en `login.php`, la migración de cambio obligatorio de contraseña, la generación concurrente de códigos de empleados y la auditoría de expiración de reservas.

**Estas mitigaciones todavía no se consideran verificadas en ejecución.** No se ejecutó la aplicación contra una base de datos ni se realizaron pruebas dinámicas automatizadas. Antes de cerrar la fase deben comprobarse, como mínimo:

- XSS: nombre y código con cadenas como `<img src=x onerror=alert(1)>`, que deben mostrarse como texto sin ejecutar código.
- API: una excepción simulada debe generar respuesta genérica para el cliente y conservar detalles únicamente en el log del servidor.
- Login: no deben aparecer credenciales en la interfaz; las cuentas semilla que aún conservan hashes conocidos deben exigir cambio de contraseña.
- Migración: probar en una base descartable tanto con la columna ausente como con la columna existente.
- Base de datos: se retiraron las instrucciones `DROP TABLE` de `database.sql`; falta probar la instalación en una base vacía y comprobar que ante tablas existentes se detenga sin borrar datos.
- Concurrencia: probar altas simultáneas y confirmar que los códigos generados son únicos.
- Expiración: en una base descartable, forzar el vencimiento de una reserva y comprobar tanto el cambio de estado como la fila `RESERVA_EXPIRADA` en `log_acciones`.
- Auditoría de usuarios: ejecutar altas, ediciones, activaciones/desactivaciones y reseteos con un administrador de prueba; confirmar que `log_acciones.id_empleado` identifica al administrador actor y no al usuario objetivo.
- Cambio de contraseña: provocar un error controlado y confirmar que la interfaz muestra un mensaje genérico, sin detalles de la excepción.
- Cambio de rol: con dos sesiones de prueba, degradar un administrador a vendedor y confirmar que su siguiente petición recibe permisos de vendedor.
- Sesión/CSRF: confirmar que el token previo al login y el previo al cambio de contraseña dejan de validarse después de esas operaciones.
- Logout: confirmar que GET devuelve 405, POST sin token devuelve 403 y POST con token válido cierra la sesión.

El estado correcto es **mitigaciones parciales aplicadas; validación y otros hallazgos pendientes**, no una auditoría de seguridad cerrada.



## Fase 4 — primer bloque de catálogo (2026-10-25)

| Archivo | Problema / control | Gravedad | Estado |
| :--- | :--- | :--- | :--- |
| api.php | Alta, edición y listado exclusivos de ADMIN, tras sesión activa y cambio obligatorio | alta | Conservados; verificados con entorno de prueba simulado |
| api.php | POST sin CSRF o método incorrecto | alta | Rechazados; verificados 403/405 en entorno aislado |
| includes/catalogo.php | SKU duplicado, entradas no escalares y límites de campos | alta | Validados; conflicto MySQL 1062 traducido a 409 |
| includes/catalogo.php | Alta parcial de producto/inventario o auditoría fallida | alta | Transacción y rollback; probados con SQLite aislado |
| includes/catalogo.php | Alteración de stock físico desde catálogo | alta | Rechazada; alta con cero y edición solo de umbral |
| assets/js/catalogo.js | Interpretación de nombres/fichas como HTML | alta | Se usa textContent y creación de nodos; prueba DOM superada |
| catalogo.php | Acceso directo de supervisor/vendedor | alta | requerir_rol(['ADMIN']) conservado; verificar página real en WAMP |
| includes/catalogo.php | Concurrencia y SQL específico de MySQL | alta | Pendiente de prueba en WAMP; SQLite no verifica FOR UPDATE |
| assets/css/catalogo.css | Presentación, modal y adaptación móvil | baja | Pendiente de comprobación visual en WAMP |

Validación ejecutada: lint PHP mediante PHP-WASM; 33 comprobaciones de servicio con
SQLite en memoria, 12 casos de API con autenticación simulada y pruebas DOM de alta,
edición, escape de HTML, doble envío y errores de red/API.
Estas pruebas no reemplazan la integración con MySQL ni sesiones reales.
No se modificaron login, auth.php, base de datos, reservas ni reglas de stock.
El bloque de catálogo no completa las pantallas de Stock ni Reabastecimiento.


## Fase 4 — Stock y ubicaciones

- Sesión activa y cambio obligatorio de contraseña verificados en API y acceso por roles a la pantalla.
- Solo la acción listar_stock admite los tres roles; las acciones existentes de Catálogo y usuarios siguen restringidas a ADMIN.
- Endpoint GET de consulta sin caché; se conserva control CSRF de peticiones POST.
- Filtros tipados, límites de longitud, estados permitidos y parámetros PDO preparados; comodines de búsqueda escapados.
- Renderizado con textContent y SVG fijo, sin incorporar HTML procedente de productos.
- Vencimientos y auditoría atómicos, actor de sistema NULL e idempotencia; rollback ante error. No se altera inventario físico.
- Los saldos negativos se señalan como inconsistencias.
- Pruebas aisladas SQLite: 28 comprobaciones. La simulación no acredita bloqueos MySQL ni sustituye prueba de integración WAMP. Ver plan en fase4_stock.md.


## Fase 4 — Alertas de reabastecimiento

- Pantalla y endpoint listar_alertas restringidos a ADMIN y SUPERVISOR_COMERCIAL. VENDEDOR_INTERNO recibe 403 desde el backend aunque conozca la URL.
- Solo GET; sesión activa y cambio obligatorio de contraseña; se preserva CSRF de POST y permisos administrativos existentes.
- Reutiliza cálculo transaccional, expiración y auditoría de Stock. La selección disponible <= mínimo se impone en servidor y no puede desactivarse por parámetros.
- Fórmula por componente: (mínimo × 2) − disponible. Los saldos negativos son inconsistencias explícitas que requieren revisión.
- Consultas preparadas; datos mostrados con textContent; SVG de ubicación fijo; sin HTML de productos.
- Verificado: sintaxis PHP y JavaScript, 38 comprobaciones aisladas SQLite, 12 casos API con autenticación simulada y prueba DOM de columna sugerida, XSS, filtros y paginación.
- No se acreditan bloqueos/concurrencia MySQL con SQLite. Integración y presentación en WAMP pendientes de confirmación del usuario.

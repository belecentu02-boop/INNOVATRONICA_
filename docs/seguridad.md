# Auditoría de Seguridad PHP - Innovatrónica S.A.

> **Fecha de informe:** 2026-10-20  
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
| `usuarios.php` | Inyección de scripts maliciosos (XSS reflejado o persistente) | **Alta** | **Corregido** | Escape total en servidor con `h()` / `htmlspecialchars` y en el cliente con `textContent` y sanitización DOM. |
| `usuarios.php` | Confirmaciones con diálogos nativos vulnerables (`confirm`, `prompt`) | **Baja** | **Corregido** | Eliminado cualquier uso de diálogos nativos del navegador; implementados modales propios accesibles con control de Escape y clic fuera. |
| `api.php` | Invocación de endpoints sin autenticación o por rol indebido | **Alta** | **Corregido** | Verificación estricta en tiempo real con `esta_autenticado()`, `verificar_sesion_activa()` y `es_admin()`, retornando 401 y 403 Forbidden. |
| `api.php` | Falsificación de peticiones en endpoints de gestión (CSRF) | **Alta** | **Corregido** | Verificación mandatoria de token CSRF (`X-CSRF-Token` o payload) en todas las operaciones POST con `verificar_csrf()`. |
| `api.php` | Escalada de privilegios creando administradores desde la interfaz | **Alta** | **Corregido** | Validación en backend que limita la creación exclusivamente a `VENDEDOR_INTERNO` y `SUPERVISOR_COMERCIAL` (decisiones.md secc. 15). |
| `api.php` | Auto-desactivación o auto-degradación de rol del Administrador | **Alta** | **Corregido** | Comprobación defensiva contra `$_SESSION['u']['id_empleado']` que rechaza cualquier intento de auto-bloqueo o pérdida de rol ADMIN. |
| `api.php` | Reseteo inseguro de contraseñas o contraseñas débiles | **Alta** | **Corregido** | Validación de longitud mínima (8 caracteres), cifrado con `password_hash(..., PASSWORD_DEFAULT)` y registro en `log_acciones` sin almacenar claves en texto plano. |

---

## 2. Verificación de Búsquedas Rápidas (Checklist de la Skill)

1. **SQL Concatenado:**
   - Se auditó cada consulta en `api.php`, `usuarios.php`, `includes/auth.php` y `login.php`.
   - **100% de las consultas utilizan sentencias preparadas con PDO** (`$pdo->prepare(...)` con parámetros posicionales `?`).
   - Ninguna variable se concatena directamente en sentencias SQL.
2. **Salidas sin escapar (Prevención XSS):**
   - En `usuarios.php`, toda salida dinámica PHP pasa por `h()` o casteo a entero `(int)`.
   - En el código JavaScript de `usuarios.php`, no se inyectan variables crudas con `innerHTML`: se usa `textContent` y funciones de escape seguras (`escapeHtml()`).
3. **Endpoints y vistas sin chequeo de rol:**
   - `usuarios.php` ejecuta `requerir_rol(['ADMIN'])` en la línea 11.
   - `api.php` ejecuta `if (!es_admin())` con respuesta 403 JSON en la línea 30.
   - Todo intento no autorizado es bloqueado en el servidor.
4. **Registro de Auditoría (Trazabilidad):**
   - Cada operación de alta (`ALTA_USUARIO`), edición (`EDICION_USUARIO`), activación/desactivación (`ACTIVAR_USUARIO`/`DESACTIVAR_USUARIO`), reseteo de clave (`RESETEO_PASSWORD`) y login bloqueado (`LOGIN_BLOQUEADO`) queda registrada en `log_acciones` con IP y timestamp.

---

## 3. Conclusión de la Auditoría

Todos los requisitos de seguridad de las **Fases 1 y 3** estipulados en `docs/decisiones.md` (Secciones 3, 7, 8 y 15) y en la skill `seguridad-php` se encuentran **100% implementados, mitigados y verificados**.
No se registran problemas de gravedad alta, media o baja pendientes en el código de la Fase 3.


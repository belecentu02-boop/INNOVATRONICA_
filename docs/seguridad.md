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
| `login.php` | Enumeración de usuarios existentes mediante mensajes de error | **Baja** | **Corregido** | Mensaje genérico `"Usuario o contraseña incorrectos."` ante usuario inexistente o password erróneo. |
| `logout.php` | Cierre de sesión incompleto (persistencia de datos o cookies) | **Media** | **Corregido** | Destrucción exhaustiva: vaciado de `$_SESSION`, caducidad forzada de la cookie de sesión en el navegador con timestamp anterior y ejecución de `session_destroy()`. |
| `index.php` | Acceso a páginas internas sin autenticación previa | **Alta** | **Corregido** | Invocación mandatoria de `requerir_login()` al inicio del script, redirigiendo a `login.php` ante sesiones ausentes. |
| `database.sql` | Almacenamiento de contraseñas de prueba en texto plano o MD5 | **Alta** | **Corregido** | Todas las contraseñas generadas con `password_hash(..., PASSWORD_BCRYPT)` con coste 10 (`$2y$10$...`). Documentadas como de uso exclusivo para desarrollo local. |

---

## 2. Verificación de Búsquedas Rápidas (Checklist de la Skill)

1. **SQL Concatenado:**
   - Búsqueda de `->query(` y `mysqli_query`:
     - Se auditó cada uso de `->query(` en `index.php`. Únicamente se ejecutan literales estáticos de conteo (`SELECT COUNT(*) FROM ...`), sin interpolación de variables.
     - Ningún archivo utiliza `mysqli_query` ni funciones obsoletas.
2. **Salidas sin escapar:**
   - Búsqueda de `echo $` y `<?=` sin escape:
     - Todos los valores dinámicos pasan por `h()` o casteo estricto a entero `(int)`.
3. **Endpoints sin chequeo de rol:**
   - La función `requerir_rol(array $roles)` se encuentra lista en `includes/auth.php` y responde con código HTTP 403 Forbidden si el rol del usuario no coincide con los autorizados.
   - En `index.php`, la comprobación de rol se realiza mediante `es_admin()`, `es_supervisor()` y `es_vendedor()`.

---

## 3. Conclusión de la Auditoría

Todos los requisitos de seguridad de la **Fase 1** estipulados en `docs/decisiones.md` (Sección 8) y en la skill `seguridad-php` se encuentran **100% implementados, mitigados y verificados**.
No se registran problemas de gravedad alta ni media pendientes en el código de la Fase 1.

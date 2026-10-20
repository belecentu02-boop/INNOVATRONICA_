---
name: seguridad-php
description: Revisa y aplica buenas prácticas de seguridad en el código PHP/MySQL del sistema de Control de Stock. Usar al crear o modificar login, sesiones, endpoints de api.php, formularios, consultas SQL o cualquier verificación de rol.
---

# Seguridad PHP: Innovatrónica

## Objetivo
Que ninguna fase se cierre con vulnerabilidades básicas. Fuente de reglas: `docs/decisiones.md` (secciones 3, 7 y 8).

## Checklist (revisar todo archivo .php tocado)

### 1. Base de datos
- Todo acceso con PDO y consultas preparadas. Prohibido concatenar variables en SQL.
- PDO con `ERRMODE_EXCEPTION` y `emulate_prepares = false`.
- Credenciales solo en `config/db.php`, nunca repetidas en otros archivos.

### 2. Autenticación y sesiones
- Contraseñas con `password_hash` y `password_verify`; nunca texto plano ni MD5/SHA1.
- `session_regenerate_id(true)` al iniciar sesión; cierre de sesión que destruye la sesión y la cookie.
- Cookies de sesión con `httponly` y `samesite`.
- Límite de intentos fallidos de login.
- Mensaje de error genérico ("Usuario o contraseña incorrectos").

### 3. Autorización
- Cada endpoint de `api.php` y cada página protegida verifica sesión y rol **en el backend**, al inicio.
- Un rol sin permiso recibe 403, no solo un botón oculto.
- Un vendedor solo puede ver y modificar **sus propias** reservas (validar el dueño en la consulta, no solo en la interfaz).
- Las acciones exclusivas (usuarios, productos, ajuste de stock) exigen `es_admin()`.

### 4. CSRF
- Token por sesión en todos los formularios y peticiones que modifican datos.
- El backend rechaza peticiones POST sin token válido.

### 5. XSS
- Toda salida a HTML pasa por `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')`.
- En JavaScript, usar `textContent` y no `innerHTML` con datos de usuario.

### 6. Validación de entradas
- Validar tipo, rango y longitud en el servidor: cantidades enteras positivas, SKU con formato válido, estados dentro de la lista permitida.
- Las transiciones de estado de reservas deben seguir la tabla de `docs/decisiones.md` (sección 5).

### 7. Integridad del stock
- Crear, aprobar y facturar reservas dentro de una transacción con `SELECT ... FOR UPDATE`.
- Recalcular el stock disponible dentro de la transacción.
- Registrar el cambio en `movimientos_stock` y `log_acciones`.

### 8. Configuración
- No mostrar errores PHP en pantalla (`display_errors` apagado fuera de desarrollo).
- Usuarios de prueba documentados como "solo desarrollo".

## Búsquedas rápidas (ejecutar y justificar cada resultado)
- Posible SQL concatenado: buscar `->query(` y `mysqli_query` con variables.
- Salida sin escape: buscar `echo $` y `<?=` sin `htmlspecialchars`.
- Endpoints sin chequeo de rol: listar los archivos en los que no aparezca `es_admin`, `es_supervisor` o `es_vendedor`.

## Formato del informe (al terminar)
Crear o actualizar `docs/seguridad.md` con una tabla:

| Archivo | Problema | Gravedad (alta/media/baja) | Estado (corregido/pendiente) |

Corregir los problemas de gravedad alta antes de dar la fase por terminada. No hacer cambios fuera del alcance de la fase actual sin avisar.
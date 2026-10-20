# Decisiones de diseño: Sistema de Control de Stock (Innovatrónica S.A.)

> **Precedencia:** este documento es la fuente de verdad. Si `implementation_plan.md` o el PDF de Innovatrónica lo contradicen, **prevalece `decisiones.md`**. Cualquier cambio se anota en la sección "Registro de cambios" al final.

---

## 1. Alcance

Sistema web interno del subdepartamento **Verificación y Disponibilidad (Control de Stock)**, dentro de Ventas Internas del Área Comercial. Objetivo: garantizar la precisión del inventario comercializable en tiempo real, evitando comprometer productos sin stock físico disponible.

## 2. Stack técnico

| Elemento | Decisión |
| :--- | :--- |
| Backend | PHP (sin framework) |
| Base de datos | MySQL, nombre `innovatronica`, acceso solo con **PDO y consultas preparadas** |
| Frontend | HTML, CSS y JavaScript sin framework |
| Entorno | WAMP local (`c:\wamp64\www\innovatronica`) |
| Zona horaria | `America/Argentina/Buenos_Aires` (PHP y MySQL) |
| Contraseñas | `password_hash` / `password_verify` |

## 3. Roles y permisos

Roles: `VENDEDOR_INTERNO`, `SUPERVISOR_COMERCIAL`, `ADMIN`. Cada usuario tiene un **ID de Empleado** único.

| Funcionalidad | Vendedor | Supervisor | Admin |
| :--- | :---: | :---: | :---: |
| Buscar stock y ubicación en depósito | ✅ | ✅ | ✅ |
| Crear reserva normal | ✅ | ✅ | ✅ |
| Solicitar reserva en stock crítico | ✅ (queda pendiente) | ✅ (directo) | ✅ (directo) |
| Aprobar / rechazar reservas pendientes | ❌ | ✅ | ✅ |
| Confirmar reservas propias | ✅ | ✅ | ✅ |
| Extender vigencia / liberar reservas | ❌ | ✅ | ✅ |
| Ver reservas | Solo las propias | Todas | Todas |
| Ver alertas de reabastecimiento | Solo lectura | ✅ | ✅ |
| Facturar / despachar | ❌ | ✅ | ✅ |
| Alta y edición de usuarios, reseteo de contraseñas | ❌ | ❌ | ✅ |
| Alta y edición de productos, umbrales y ubicación | ❌ | ❌ | ✅ |
| Ajustar stock físico (ingresos por compras) | ❌ | ❌ | ✅ |

**Regla técnica:** el rol se verifica **en el backend en cada endpoint**. Ocultar botones en la interfaz no es una medida de seguridad. Un acceso no autorizado devuelve error 403.

**Justificación del rol ADMIN** (no está en el PDF): separación de funciones. Quien administra usuarios y catálogo no es quien aprueba ventas.

## 4. Modelo de inventario

| Concepto | Definición |
| :--- | :--- |
| **Stock Físico (S_F)** | Unidades reales registradas en el almacén. Solo cambia por facturación (baja) o ajuste del Admin (alta/corrección). |
| **Stock Comprometido (S_C)** | Suma de las cantidades de reservas en estado `ACTIVA`, `PENDIENTE_AUTORIZACION` o `CONFIRMADA`. Se **calcula** a partir de las reservas, no se edita a mano. |
| **Stock Disponible (S_D)** | `S_D = S_F - S_C`. Es el valor que usa el sistema para validar solicitudes. |
| **Umbral Mínimo (U_min)** | Valor de seguridad por producto, editable solo por Admin. |
| **Alerta de reabastecimiento** | Se dispara si `S_D <= U_min`. Es **quiebre total** si `S_D = 0`. |
| **Cantidad sugerida a reponer** | `(U_min × 2) - S_D` |

Las reservas `FACTURADA` **no** cuentan como comprometidas: al facturarse ya descontaron el stock físico, y contarlas duplicaría el descuento.

## 5. Estados de la reserva

Cada reserva tiene un **ID de Reserva** único generado automáticamente (por ejemplo `RES-20261020-0001`).

| Estado | Significado | ¿Cuenta como comprometido? |
| :--- | :--- | :---: |
| `ACTIVA` | Congelada, con temporizador en marcha | Sí |
| `PENDIENTE_AUTORIZACION` | Reserva en stock crítico a la espera del supervisor/admin | Sí |
| `CONFIRMADA` | Derivada a facturación | Sí |
| `FACTURADA` | Despachada, stock físico descontado (estado final) | No |
| `EXPIRADA` | Venció por tiempo y devolvió el stock disponible (estado final) | No |
| `RECHAZADA` | El supervisor/admin no autorizó (estado final) | No |
| `LIBERADA` | Liberada manualmente por supervisor/admin (estado final) | No |

### Transiciones permitidas

Cualquier transición que no figure aquí **debe ser rechazada por el backend**.

| Desde | Hacia | Quién | Condición |
| :--- | :--- | :--- | :--- |
| (nueva) | `ACTIVA` | Cualquier rol | Stock no crítico, o crea Supervisor/Admin |
| (nueva) | `PENDIENTE_AUTORIZACION` | Vendedor | `S_D - cantidad <= U_min` |
| `PENDIENTE_AUTORIZACION` | `ACTIVA` | Supervisor, Admin | Aprueba; el temporizador se reinicia |
| `PENDIENTE_AUTORIZACION` | `RECHAZADA` | Supervisor, Admin | Rechaza |
| `PENDIENTE_AUTORIZACION` | `EXPIRADA` | Sistema | Vence sin respuesta |
| `ACTIVA` | `CONFIRMADA` | Dueño de la reserva, Supervisor, Admin | Reserva vigente |
| `ACTIVA` | `LIBERADA` | Supervisor, Admin | Liberación manual |
| `ACTIVA` | `EXPIRADA` | Sistema | Vence el temporizador |
| `CONFIRMADA` | `FACTURADA` | Supervisor, Admin | Descuenta `S_F` |
| `CONFIRMADA` | `LIBERADA` | Supervisor, Admin | Anulación antes de facturar |

Un estado final (`FACTURADA`, `EXPIRADA`, `RECHAZADA`, `LIBERADA`) no puede volver a modificarse.

## 6. Reglas de negocio de las reservas

1. **Vigencia por defecto:** 24 horas. Solo Supervisor y Admin pueden cambiarla o extenderla.
2. **Temporizador:** el vencimiento se guarda como fecha y hora (`fecha_vencimiento`); la interfaz muestra una cuenta regresiva `HH:MM:SS` calculada contra la hora del servidor.
3. **Reserva crítica:** se considera crítica cuando `S_D - cantidad <= U_min`. Un vendedor la crea como `PENDIENTE_AUTORIZACION`; Supervisor y Admin la crean directamente `ACTIVA`.
4. **Validación de cantidad:** nunca se puede reservar más que `S_D`. Si `cantidad > S_D`, se rechaza la solicitud.
5. **Pendientes con vencimiento:** una reserva pendiente también vence a las 24 h de creada, para no bloquear stock indefinidamente.
6. **`CONFIRMADA` no vence:** ya fue derivada a facturación.
7. **Facturación:** al pasar a `FACTURADA` se hace `S_F = S_F - cantidad` y se registra el movimiento.
8. **Sin diálogos nativos:** nada de `prompt()` ni `confirm()`; se usan modales propios.

## 7. Concurrencia (crítico)

Dos vendedores no pueden reservar la última unidad a la vez. Crear, aprobar y facturar reservas se hace dentro de una **transacción** que bloquea la fila del producto (`SELECT ... FOR UPDATE`), recalcula `S_D` **dentro** de la transacción y recién entonces inserta o modifica. Si algo falla, `ROLLBACK`.

## 7.1 Expiración automática

- Un `EVENT` de MySQL se ejecuta cada minuto y pasa a `EXPIRADA` las reservas `ACTIVA` y `PENDIENTE_AUTORIZACION` con `fecha_vencimiento` vencida.
- Además, el sistema ejecuta el mismo chequeo al consultar stock, para que el dato sea correcto aunque el evento no esté activo.
- Requiere `event_scheduler = ON` en MySQL (documentar cómo activarlo en WAMP).

## 8. Seguridad mínima obligatoria

- Consultas preparadas con PDO en todo acceso a la base.
- `password_hash` para contraseñas; nunca texto plano.
- Token CSRF en todos los formularios y peticiones que modifican datos.
- `session_regenerate_id(true)` al iniciar sesión y cierre de sesión completo.
- Escape de toda salida en HTML (`htmlspecialchars`) para evitar XSS.
- Límite de intentos fallidos de login.
- Mensajes de error genéricos ("usuario o contraseña incorrectos").
- Usuarios iniciales de prueba (`admin`, `supervisor`, `vendedor`) solo para desarrollo; documentar que se deben cambiar en producción.

## 9. Auditoría y trazabilidad

Desde la Fase 1 existen dos registros:

- **`movimientos_stock`:** cada cambio de `S_F` (facturación, ajuste, ingreso), con producto, cantidad, tipo, usuario, fecha y motivo.
- **`log_acciones`:** reserva creada, aprobada, rechazada, extendida, liberada, confirmada, facturada o expirada; alta/edición de usuarios y productos; con usuario y fecha.

## 10. Datos de prueba

El `database.sql` debe incluir:

- Tres usuarios iniciales (admin, supervisor, vendedor).
- Entre 15 y 20 productos de electrónica con SKU realista (por ejemplo `ESP32-CAM-01`), categoría, ficha técnica resumida y ubicación (`A1-01`).
- Al menos un producto en stock crítico, uno en quiebre total y otros con stock normal, para poder probar todos los flujos.

## 11. Convenciones de trabajo con la IA

- Se trabaja **una fase por vez**. Al terminar, el agente se detiene, lista los archivos modificados, explica cómo probarlo y espera confirmación.
- Un commit de Git por fase probada. Mensaje: `Fase N: descripción`.
- Respaldo de la base (`mysqldump`) antes de cada fase que cambie el esquema.
- Los cambios de esquema se hacen en archivos versionados, no editando a mano.
- Se documentan los prompts usados y las decisiones tomadas.

## 12. Fases (resumen)

1. Base de datos, configuración y login con 3 roles
2. Dashboard y ambiente de trabajo diferenciado por rol
3. Gestión de usuarios (exclusivo Admin)
4. Catálogo, stock en tiempo real y alertas de reabastecimiento
5. Reservas, temporizador y aprobación de stock crítico
6. Facturación/despacho, ajustes de stock y expiración automática

El detalle de cada fase está en `implementation_plan.md`, salvo donde este documento lo contradiga.

## 13. Pendientes por definir

- [ ] Formato definitivo del ID de Reserva.
- [ ] Si el Admin puede crear reservas a nombre de otro vendedor.
- [ ] Si se exportan reportes (CSV/PDF) en esta entrega o en una futura.

## 14. Registro de cambios

| Fecha | Cambio | Motivo |
| :--- | :--- | :--- |
| 2026-10-20 | Versión inicial | Cerrar decisiones antes de la Fase 1 |

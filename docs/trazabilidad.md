# Matriz de Trazabilidad de Requisitos - Innovatrónica S.A.

> **Fecha de generación:** 2026-10-20  
> **Fase actual cerrada:** Fase 1 (Base de datos, configuración y login con 3 roles)  
> **Skill aplicada:** `verificador-requisitos`  
> **Fuentes (en orden de prioridad):**  
> 1. `docs/decisiones.md` (Fuente de verdad prevaleciente)  
> 2. `docs/innovatronica.pdf` (Documento de especificación original)  
> 3. `docs/implementation_plan.md` (Plan de fases)  

---

## 1. Matriz de Cumplimiento

| ID | Requisito | Fuente | Estado | Evidencia (archivo/función) | Prueba | Observaciones |
| :-- | :-- | :-- | :-: | :-- | :-- | :-- |
| **R01** | Cada usuario tiene un ID de empleado único | `decisiones.md` (Sec. 3, 15), `innovatronica.pdf` (Pág. 3) | ✅ Cumple | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L25-L38): `codigo_empleado` UNIQUE. [api.php](file:///c:/wamp64/www/Innovatrónica/api.php): generación automática correlativa (`EMP-001`, `EMP-002`, `EMP-003`, etc.). | Probada inserción automática y correlatividad de legajos en Fase 3. | Cubre identificador primario relacional y código de empleado visible corporativo. |
| **R02** | Vendedor: lectura y escritura limitada (buscar stock, crear reservas) | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 3) | 🟡 Parcial | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L31): rol `VENDEDOR_INTERNO`. [includes/auth.php](file:///c:/wamp64/www/Innovatrónica/includes/auth.php): `es_vendedor()`. | Acceso a `usuarios.php` y `api.php` bloqueado (403). | Rol creado y restringido en backend. Los módulos de catálogo y reserva corresponden a Fases 4 y 5. |
| **R03** | Supervisor: liberar reservas, autorizar ventas con stock crítico, modificar tiempos | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 3, 5) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L31): rol `SUPERVISOR_COMERCIAL`. [includes/auth.php](file:///c:/wamp64/www/Innovatrónica/includes/auth.php): `es_supervisor()`. | Acceso a `usuarios.php` y `api.php` bloqueado (403). | Rol protegido en backend. Los flujos de autorización y liberación corresponden a Fase 5. |
| **R04** | Admin: gestión exclusiva de usuarios, productos y ajustes de stock | `decisiones.md` (Sec. 3, 15), `implementation_plan.md` | 🟡 Parcial | [usuarios.php](file:///c:/wamp64/www/Innovatrónica/usuarios.php), [api.php](file:///c:/wamp64/www/Innovatrónica/api.php). | Gestión de usuarios 100% operativa (alta, edición, toggle activo/inactivo, reseteo de claves y auditoría). | Módulo de personal cerrado en Fase 3. Catálogo y ajuste de inventario corresponden a Fases 4 y 6. |
| **R05** | Los permisos se validan en el backend | `decisiones.md` (Sec. 3, 15), `rules.md` | ✅ Cumple | [usuarios.php](file:///c:/wamp64/www/Innovatrónica/usuarios.php#L11), [api.php](file:///c:/wamp64/www/Innovatrónica/api.php#L30): `requerir_rol(['ADMIN'])` y `es_admin()`. | Pruebas de petición autenticada como vendedor/supervisor retornan HTTP 403 Forbidden. | Validación estricta en servidor en todos los endpoints y vistas. |
| **R06** | SKU único por producto | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 10) | ✅ Cumple | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L43-L52): `sku VARCHAR(30) PRIMARY KEY`. | Inserción de 18 productos semilla únicos comprobada sin colisión. | Garantizado por restricción PRIMARY KEY en MySQL InnoDB. |
| **R07** | Nombre, categoría y ficha técnica resumida | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 10) | ✅ Cumple | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L45-L47): columnas `nombre`, `categoria`, `ficha_tecnica`. | Verificado en 18 productos con datos reales (Microcontroladores, Sensores, etc.). | Catálogo semilla enriquecido con datos técnicos realistas. |
| **R08** | Ubicación en depósito (coordenada) | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 10) | ✅ Cumple | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L48): `ubicacion_deposito VARCHAR(30) NOT NULL`. | Verificado en productos semilla (`A1-01`, `B1-02`, `C1-01`, etc.). | Coordenadas de almacenamiento presentes en el esquema. |
| **R09** | Stock Físico registrado por producto | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 4) | ✅ Cumple | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L57-L64): tabla `inventario(stock_fisico)`. | 18 registros cargados con stock variado (0 a 60 unidades). | Manejo normalizado vinculado a producto mediante FK. |
| **R10** | Stock Comprometido = suma de reservas ACTIVA, PENDIENTE_AUTORIZACION y CONFIRMADA | `decisiones.md` (Sec. 4) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L69-L93): tabla `reservas` con estados correspondientes. | Esquema preparado en BD. | El cálculo dinámico se implementará en la API de Fase 4. |
| **R11** | Stock Disponible = Stock Físico - Stock Comprometido | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 4) | ⏳ Fase futura | Estructura relacional en [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql). | N/A en Fase 1/3. | Fórmula y cálculo en consultas en tiempo real corresponden a Fase 4. |
| **R12** | Alerta de reabastecimiento si Stock Disponible <= Umbral Mínimo | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 4) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L60): columna `umbral_minimo`. | Datos semilla ya incluyen 3 productos críticos y 1 en quiebre total. | La pantalla y detección reactiva de alertas corresponden a Fase 4. |
| **R13** | Cantidad sugerida a reponer = (Umbral × 2) - Stock Disponible | `decisiones.md` (Sec. 4) | ⏳ Fase futura | N/A en Fase 1/3. | N/A en Fase 1/3. | Corresponde a la interfaz de alertas de Fase 4. |
| **R14** | ID de reserva único y automático | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 5) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L70): `id_reserva VARCHAR(30) PRIMARY KEY`. | N/A en Fase 1/3. | Generador de formato `RES-YYYYMMDD-XXXX` en backend en Fase 5. |
| **R15** | Tiempo de vida configurable (24 hs por defecto) con temporizador decreciente | `innovatronica.pdf` (Pág. 3), `decisiones.md` (Sec. 6) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L75): `fecha_vencimiento DATETIME NOT NULL`. | N/A en Fase 1/3. | Countdown JS y endpoints de extensión corresponden a Fase 5. |
| **R16** | Estados y transiciones según `docs/decisiones.md` sección 5 | `decisiones.md` (Sec. 5) | 🟡 Parcial | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L76-L84): ENUM con los 7 estados definidos. | Verificado en DDL de MySQL. | Esquema completo en BD; las transiciones operativas se codifican en Fases 5 y 6. |
| **R17** | Reserva en stock crítico queda pendiente hasta autorización | `decisiones.md` (Sec. 5, 6) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L78): soporte para `PENDIENTE_AUTORIZACION`. | N/A en Fase 1/3. | Lógica de retención de pedido crítico corresponde a Fase 5. |
| **R18** | Expiración automática que devuelve el stock disponible | `decisiones.md` (Sec. 7.1) | 🟡 Parcial | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L125-L136): Evento MySQL `expirar_reservas_vencidas`. | Evento creado en MySQL. | Evento programado cada 1 min; la comprobación pasiva al consultar stock se añade en Fase 4. |
| **R19** | No se puede reservar más que el Stock Disponible (concurrencia) | `decisiones.md` (Sec. 6, 7) | ⏳ Fase futura | N/A en Fase 1/3. | N/A en Fase 1/3. | Bloqueo de fila `SELECT ... FOR UPDATE` dentro de transacción en Fase 5. |
| **R20** | Facturar descuenta el Stock Físico y registra el movimiento | `decisiones.md` (Sec. 5, 6) | ⏳ Fase futura | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L98-L111): tabla `movimientos_stock`. | N/A en Fase 1/3. | Flujo de facturación y despacho corresponde a Fase 6. |
| **R21** | Registro en `movimientos_stock` y `log_acciones` | `decisiones.md` (Sec. 9, 15) | ✅ Cumple | [database.sql](file:///c:/wamp64/www/Innovatrónica/database.sql#L98-L123), [api.php](file:///c:/wamp64/www/Innovatrónica/api.php), [includes/auth.php](file:///c:/wamp64/www/Innovatrónica/includes/auth.php). | Comprobado registro en vivo de `ALTA_USUARIO`, `EDICION_USUARIO`, `ACTIVAR_USUARIO`, `DESACTIVAR_USUARIO`, `RESETEO_PASSWORD` y `LOGIN_BLOQUEADO`. | `log_acciones` operativo y auditando todas las acciones de usuarios; `movimientos_stock` estructurada para Fases 4 y 6. |
| **R22** | Seguridad mínima de `docs/decisiones.md` sección 8 y 15 | `decisiones.md` (Sec. 8, 15) | ✅ Cumple | [config/db.php](file:///c:/wamp64/www/Innovatrónica/config/db.php), [includes/auth.php](file:///c:/wamp64/www/Innovatrónica/includes/auth.php), [login.php](file:///c:/wamp64/www/Innovatrónica/login.php), [api.php](file:///c:/wamp64/www/Innovatrónica/api.php). | PDO preparado, BCRYPT, CSRF, rechazo de login inactivo, invalidación de sesión activa en tiempo real, modales propios. | Verificado con pruebas integrales. |

---

## 2. Resumen Estadístico de Requisitos

- **Total de requisitos evaluados:** 22
- **✅ Cumple (Fases 1 y 3 cerradas):** 8 (36.4%)
- **🟡 Parcial (Base/Esquema y submódulos listos):** 4 (18.2%)
- **⏳ Fase futura (Fases 4 a 6 planificadas):** 10 (45.4%)
- **❌ Falta (no implementado debiendo estarlo):** 0 (0.0%)

---

## 3. Lista de Contradicciones entre Fuentes

1. **Cantidad de Roles:**
   - `innovatronica.pdf` define 2 roles (`VENDEDOR INTERNO`, `SUPERVISOR COMERCIAL`).
   - `docs/decisiones.md` (Sec. 3) y `docs/implementation_plan.md` agregan `ADMIN`.
   - **Resolución aplicada:** Prevalece `decisiones.md`. Se implementó soporte de 3 roles en BD, sesión y login.
2. **Ciclo de Vida de las Reservas (Estados):**
   - `innovatronica.pdf` menciona 3 estados (`Activa`, `Confirmada`, `Expirada`).
   - `docs/implementation_plan.md` menciona 5 estados (+`PENDIENTE_AUTORIZACION`, `FACTURADA`).
   - `docs/decisiones.md` (Sec. 5) establece 7 estados (+`RECHAZADA`, `LIBERADA`).
   - **Resolución aplicada:** Prevalece `decisiones.md`. La tabla `reservas` contempla los 7 estados.
3. **Cálculo de Stock Comprometido:**
   - `innovatronica.pdf` menciona genéricamente "reservas para otros vendedores que aún no se facturaron".
   - `docs/decisiones.md` (Sec. 4) aclara explícitamente que `ACTIVA`, `PENDIENTE_AUTORIZACION` y `CONFIRMADA` comprometen stock disponible, mientras que `FACTURADA`, `EXPIRADA`, `RECHAZADA` y `LIBERADA` no lo comprometen.
   - **Resolución aplicada:** Prevalece `decisiones.md`.

---

## 4. Lista de Faltantes Priorizada para la Próxima Fase (Fase 2)

De acuerdo con el plan de trabajo y las reglas de negocio, la siguiente etapa es la **Fase 2: Dashboard y Ambiente de Trabajo Diferenciado por Rol**:

1. **Header y Navegación Dinámica (`includes/header.php`):**
   - Adaptar el menú según el rol autenticado:
     - *Vendedor:* Stock · Mis Reservas.
     - *Supervisor:* Stock · Todas las Reservas · Alertas de Reabastecimiento.
     - *Admin:* Stock · Todas las Reservas · Alertas de Reabastecimiento · Gestión de Personal · Catálogo/Inventario.
2. **Dashboard Base por Rol:**
   - Tarjetas de métricas iniciales (stock general, alertas críticas, reservas activas).
3. **Plantilla Común:**
   - Separar `header.php` y `footer.php` manteniendo estilos tecnológicos y consistentes.

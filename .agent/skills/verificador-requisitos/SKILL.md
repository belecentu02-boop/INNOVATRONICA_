---
name: verificador-requisitos
description: Compara el código del proyecto contra los requisitos del PDF de Innovatrónica y docs/decisiones.md, y genera una matriz de trazabilidad. Usar al cerrar una fase, antes de la entrega o cuando se pida verificar el cumplimiento.
---

# Verificador de requisitos

## Objetivo
Comprobar, con evidencia del código, qué requisitos están cumplidos, cuáles parcialmente y cuáles faltan.

## Fuentes (en orden de prioridad)
1. `docs/decisiones.md`
2. `docs/innovatronica.pdf`
3. `docs/implementation_plan.md`

Si hay contradicción, prevalece la fuente de mayor prioridad. Señalar la contradicción en el informe.

## Procedimiento
1. Extraer los requisitos y numerarlos (R01, R02...) en `docs/trazabilidad.md`. Cada uno debe ser verificable.
2. Para cada requisito buscar la evidencia: archivo y función/línea donde se implementa, y la prueba que lo comprueba.
3. Clasificar: ✅ Cumple · 🟡 Parcial · ❌ Falta · ⏳ Fase futura.
4. **No marcar ✅ sin evidencia concreta.** Si solo se infiere o no se puede probar, es 🟡.
5. Cuando sea posible, probarlo realmente (consulta SQL, petición, navegador) en lugar de solo leer el código.

## Requisitos base a verificar

**Usuarios y permisos**
- R01 Cada usuario tiene un ID de empleado único.
- R02 Vendedor: lectura y escritura limitada (buscar stock, crear reservas).
- R03 Supervisor: liberar reservas, autorizar ventas con stock crítico, modificar tiempos.
- R04 Admin: gestión exclusiva de usuarios, productos y ajustes de stock.
- R05 Los permisos se validan en el backend.

**Productos**
- R06 SKU único por producto.
- R07 Nombre, categoría y ficha técnica resumida.
- R08 Ubicación en depósito (coordenada).

**Inventario**
- R09 Stock Físico registrado por producto.
- R10 Stock Comprometido = suma de reservas ACTIVA, PENDIENTE_AUTORIZACION y CONFIRMADA.
- R11 Stock Disponible = Stock Físico - Stock Comprometido.
- R12 Alerta de reabastecimiento si Stock Disponible <= Umbral Mínimo.
- R13 Cantidad sugerida a reponer = (Umbral × 2) - Stock Disponible.

**Reservas y reglas de negocio**
- R14 ID de reserva único y automático.
- R15 Tiempo de vida configurable (24 hs por defecto) con temporizador decreciente.
- R16 Estados y transiciones según `docs/decisiones.md` sección 5.
- R17 Reserva en stock crítico queda pendiente hasta autorización.
- R18 Expiración automática que devuelve el stock disponible.
- R19 No se puede reservar más que el Stock Disponible (probar con concurrencia).
- R20 Facturar descuenta el Stock Físico y registra el movimiento.

**Transversales**
- R21 Registro en `movimientos_stock` y `log_acciones`.
- R22 Seguridad mínima de `docs/decisiones.md` sección 8.

## Formato de salida (`docs/trazabilidad.md`)

| ID | Requisito | Fuente | Estado | Evidencia (archivo/función) | Prueba | Observaciones |
| :-- | :-- | :-- | :-: | :-- | :-- | :-- |

Al final incluir:
- Resumen: cantidad de requisitos por estado.
- Lista de contradicciones entre fuentes.
- Lista de faltantes priorizada para la próxima fase.

## Reglas
- Solo verificar y reportar; **no modificar el código** salvo que se pida expresamente.
- No dar por cumplido lo que pertenece a una fase aún no implementada: marcarlo ⏳.
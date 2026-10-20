# Proyecto: sistema web interno de Control de Stock de Innovatrónica S.A.

> *Procedencia* Fuentes de verdad: docs/innovatronica.pdf y docs/implementation_plan.md

## Reglas de negocio:
- Stock Disponible = Stock Físico - Stock Comprometido
- Alerta de reabastecimiento si Stock Disponible <= Umbral Mínimo
- Roles: VENDEDOR_INTERNO, SUPERVISOR_COMERCIAL, ADMIN
- Estados de reserva: ACTIVA, PENDIENTE_AUTORIZACION, CONFIRMADA,
  FACTURADA, EXPIRADA, RECHAZADA, LIBERADA

## Reglas técnicas:
- PHP con PDO y consultas preparadas, nunca SQL concatenado
- Verificar el rol en el backend de cada endpoint, no solo ocultar botones
- Crear reservas dentro de una transacción con bloqueo de fila
- Zona horaria America/Argentina/Buenos_Aires

## Flujo de trabajo:
- Una fase por vez. Al terminar, detenete, listá los archivos tocados,
  explicá cómo probarlo y esperá mi confirmación.
- No avances a la fase siguiente sin que yo lo indique.
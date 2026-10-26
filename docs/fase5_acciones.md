# Fase 5 — Confirmar, extender y liberar

## Instalación
Aplicar en fase-5/reservas después de los bloques de listado, creación y aprobaciones. Extraer en la raíz y reemplazar archivos. Sin SQL ni cambios de esquema. Modifica reservas.php, reservas_api.php, includes/reservas.php, assets/js/reservas.js y docs/seguridad.md. Agrega tests/reservas_acciones.php y este documento.

## Reglas
- ACTIVA vigente: el vendedor dueño, supervisor o admin puede confirmar. Pasa a CONFIRMADA, sigue comprometida y no vence. Facturación está fuera de esta fase.
- ACTIVA vigente: supervisor o admin puede extender sumando de 1 a 720 horas al vencimiento previo.
- ACTIVA vigente o CONFIRMADA: supervisor o admin puede liberar. Pasa a LIBERADA, devuelve disponibilidad y conserva físico. Sin acceso para vendedor.
- No se procesan pendientes, expiradas o estados finales con estas acciones. Se comprueba estado y tiempo en backend y en UPDATE. Se bloquea inventario y reserva, y se audita la transición con el actor en la misma transacción.

## Probar en WAMP, con base de prueba
1. Vendedor: confirmar una ACTIVA propia; pasa a CONFIRMADA, no vence, mantiene el comprometido. No debe poder confirmar una ajena mediante API.
2. Supervisor: extender una ACTIVA 2 horas; su vencimiento aumenta exactamente 2 horas.
3. Admin o supervisor: liberar una ACTIVA y otra CONFIRMADA; pasan a LIBERADA, el disponible aumenta por la cantidad y el físico no cambia.
4. Vendedor no ve botones para extender o liberar; peticiones a esas acciones reciben 403. Reserva expirada o final no puede modificarse.
5. Comprobar log_acciones: RESERVA_CONFIRMADA, RESERVA_EXTENDIDA y RESERVA_LIBERADA con el actor correcto. Probar móvil, listado, creación y aprobaciones.
6. Probar concurrencia MySQL real en base descartable: dos reservas simultáneas por la última unidad; confirmación contra expiración/liberación; segunda acción sobre una reserva ya modificada. Solo una transición válida debe concretarse.

Las pruebas SQLite no validan FOR UPDATE ni la concurrencia MySQL. Si el resultado de una acción falla por red, actualizar el listado antes de repetir. Se debe evitar repetir una extensión incierta: sumaría las horas nuevamente.

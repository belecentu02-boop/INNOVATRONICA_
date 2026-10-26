# Fase 5 — Aprobar y rechazar reservas

## Instalación
Aplicar sobre fase-5/reservas con los bloques de listado y creación ya instalados. Extraer en la raíz del proyecto conservando carpetas. No requiere SQL ni cambia el esquema. Modifica reservas.php, reservas_api.php, includes/reservas.php, assets/js/reservas.js, assets/css/reservas.css, docs/seguridad.md y tests/reservas_listado.php; agrega tests/reservas_aprobaciones.php y este documento.

## Reglas
Supervisor y admin ven botones de aprobación/rechazo solo para pendientes; el vendedor no. La API controla sesión, rol y CSRF. La solicitud debe seguir pendiente y vigente al procesarse.

Aprobar cambia PENDIENTE_AUTORIZACION a ACTIVA y reinicia el plazo a 24 horas. Se mantiene comprometida y se registra autorizado_por. Rechazar exige un motivo de hasta 255 caracteres, cambia a RECHAZADA, guarda el motivo y actor; la cantidad deja de estar comprometida. No se modifica stock físico.

Inventario y reserva se bloquean dentro de la transacción. Si comprometido supera físico se bloquea la aprobación; auditoría y transición se confirman juntas. Si el resultado es incierto por red, actualizar el listado antes de reintentar.

## Pruebas WAMP en base de prueba
1. Crear dos reservas pendientes vigentes desde un vendedor, con productos o cantidades que dejen el disponible al mínimo o por debajo. Las pendientes comprometen stock.
2. Como supervisor aprobar una: pasa a ACTIVA, reloj vuelve a 24 h, el stock disponible permanece igual y físico no cambia.
3. Como administrador rechazar la otra con motivo: pasa a RECHAZADA, el motivo aparece al vendedor, el disponible sube por la cantidad rechazada y físico no cambia.
4. Como vendedor no deben aparecer botones; también probar desde dos cuentas o sesiones para detectar acciones repetidas. Un segundo intento de resolución debe rechazarse.
5. Comprobar que no se puede aprobar una reserva expirada y que log_acciones registra actor y acción.
6. Comprobar móvil, búsqueda, filtrado y creación aún operativos. Probar concurrencia en MySQL real con una base descartable: creación simultánea de la última unidad y aprobación frente a expiración o rechazo.

SQLite usa adaptador de FOR UPDATE y DATE_ADD y no prueba bloqueos reales. La integración MySQL/WAMP y la concurrencia deben verificarse antes de dar por validada la fase. Las funciones de confirmación, extensión y liberación pertenecen a bloques posteriores.

# Fase 6 — Facturación y despacho

## Instalación
Crear rama desde main posterior a fase 5. Instalar el paquete respetando carpetas: reemplaza reservas_api.php, includes/reservas.php, assets/js/reservas.js y docs/seguridad.md; agrega tests/reservas_facturar.php y este documento. No requiere SQL ni cambia el esquema. Probar en copia descartable de la base.

## Reglas
Supervisor y admin pueden facturar una reserva CONFIRMADA. Vendedor no tiene permiso. Se bloquea inventario y reserva, se comprueba que el comprometido no supere el físico y se descuenta cantidad del físico. Reserva pasa a FACTURADA, deja de contarse en comprometido; se registran movimientos_stock (tipo FACTURACION, antes/después, actor, reserva) y log_acciones. Todo es atómico con rollback. Si el stock es inconsistente se rechaza. No hay facturas fiscales; la operación es el registro interno de despacho.

## Pruebas en WAMP con copia de la base
1. Registrar físico, comprometido y disponible de un producto y confirmar una reserva de prueba. Facturar desde supervisor: estado FACTURADA; físico y comprometido bajan por la cantidad; disponible permanece igual.
2. Confirmar el movimiento FACTURACION y actor correcto en movimientos_stock y RESERVA_FACTURADA en log_acciones.
3. Intentar repetir: no debe descontar de nuevo. Vendedor no ve el botón ni puede facturar por API.
4. Comprobar otra reserva confirmada con admin y verificar móvil. Crear una reserva activa o pendiente no permite facturarla.
5. Prueba de concurrencia MySQL real pendiente: solicitudes simultáneas para facturar una misma reserva, y facturar frente a liberar o crear. Debe aplicarse una sola transición y conservar invariantes de stock. La prueba SQLite no verifica bloqueos reales.

Las acciones de ajuste de stock y revisión de expiración automática son bloques posteriores. Las reservas confirmadas no vencen.

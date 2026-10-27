# Fase 6 — Ingresos y correcciones de stock

## Instalación
En fase-6/facturacion-stock con el bloque de facturación instalado, extraer el paquete en la raíz respetando carpetas. Agrega ajustar_inventario.php, inventario_api.php, includes/ajustes.php, assets/css/ajustes.css, assets/js/ajustes.js, tests/ajustes.php y este documento; modifica catalogo.php y docs/seguridad.md. No requiere SQL ni cambios de esquema. Probar en una copia descartable de la base.

## Reglas
ADMIN consulta el SKU y visualiza stock físico y comprometido. Ingresa el nuevo total físico y motivo. Ingreso por compra debe aumentar el físico; corrección puede subir o bajar. La diferencia absoluta se registra en movimientos_stock con tipo, físico anterior/nuevo, actor y motivo. Cambios iguales se rechazan.

Se controlan permisos y CSRF en el backend. Se bloquea inventario, se comprueba que físico siga igual al consultado, se recalcula comprometido y se prohíbe un nuevo físico menor. Cambio, movimiento y log son transaccionales. Si la respuesta falla por red, consultar stock y movimientos antes de repetir. Vencimientos se procesan con la rutina existente antes de consultar/ajustar.

## Pruebas WAMP con base descartable
1. Como ADMIN entrar a Catálogo > Ajustar stock; consultar SKU, anotar físico y comprometido.
2. Ingreso: nuevo total físico = anterior + 2. Confirmar; físico sube 2 y movimientos_stock indica INGRESO con antes/después, actor y motivo.
3. Corrección: reducir 1 sin bajar de comprometido. Confirmar movimiento AJUSTE y nuevo total.
4. Si hay unidades comprometidas, intentar un total inferior: debe rechazar y no crear movimiento. Probar que no admite iguales, negativos, motivos vacíos ni SKU inexistente.
5. Consulta y modificación con un cambio intermedio de físico en otra sesión debe rechazar el valor desactualizado. Supervisor y vendedor deben recibir 403 en página y endpoint, incluso usando URL directa.
6. Probar escritorio y móvil. Verificar Catálogo, Stock y Reservas. Prueba real de concurrencia MySQL entre ajustes, creación y facturación pendiente; SQLite no valida FOR UPDATE.

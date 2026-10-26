# Fase 5 — Creación de reservas

## Instalación
Sobre la rama fase-5/reservas con el bloque de listado ya instalado, extraer el paquete en la raíz del proyecto, reemplazando los archivos indicados. No hay cambios de esquema ni se debe ejecutar SQL. Archivos: reservas.php, reservas_api.php, includes/reservas.php, assets/css/reservas.css, assets/js/reservas.js, docs/seguridad.md; se agregan tests/reservas_crear.php y este documento.

## Comportamiento
El botón Nueva reserva abre un formulario integrado. Se ingresa SKU existente y cantidad entera positiva. El dueño es el usuario de la sesión. Vendedor usa 24 horas; supervisor y administrador pueden elegir 1 a 720 horas. Los vendedores crean solicitudes PENDIENTE_AUTORIZACION si el disponible resultante queda en el mínimo o por debajo; otro caso ACTIVA. Supervisor y administrador crean directamente ACTIVA. La aprobación, rechazo, liberación y confirmación se implementarán después.

Antes de crear se vencen y auditan reservas vencidas según la rutina existente. En transacción se bloquea la fila de inventario, se recalcula disponible físico menos comprometido, se rechaza si no alcanza, y se insertan reserva y auditoría juntas. No cambia el stock físico. ID generado aleatoriamente; la base mantiene su clave primaria como última defensa.

## Pruebas en WAMP (base descartable)
Las solicitudes de prueba comprometen stock; no hay interfaz para liberarlas todavía. Respaldar y usar una copia de base, con configuración de conexión apropiada para esa copia.
1. Como vendedor, anotar el stock disponible y mínimo de un SKU desde Stock. Crear una reserva pequeña que deje disponible por encima del mínimo: aparece ACTIVA y el stock disponible baja. El físico sigue igual.
2. Crear otra que deje el disponible igual al mínimo: PENDIENTE_AUTORIZACION y compromete unidades. Supervisor la ve; otro vendedor no la ve.
3. Intentar cantidad superior al disponible o SKU inexistente: muestra error y no crea registro.
4. Como supervisor o admin crear reserva con vigencia 1 hora que entre en crítico: ACTIVA y temporizador. Probar también dispositivo móvil.
5. Verificar log_acciones con RESERVA_CREADA y el empleado correcto; ningún movimiento de stock físico.
6. Comprobar concurrencia MySQL con dos sesiones independientes que intenten reservar simultáneamente la última unidad: solo una debe quedar creada. SQLite de las pruebas no implementa FOR UPDATE y no sustituye esta validación.

Si falla la red al enviar, revisar el listado antes de repetir: el servidor pudo haber guardado la solicitud. Las pruebas automáticas usan SQLite aislada, sin conectarse a MySQL. Se verificaron 18 casos de creación, 19 de listado, 10 HTTP simulados y DOM; MySQL real y aspecto visual quedan para WAMP.

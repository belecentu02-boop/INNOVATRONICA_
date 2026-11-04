# Prueba de portal de empleados

Propuesta sobre `main` en `08d0977`, aislada en `prueba/portal-empleados` para evaluación visual y funcional. No requiere migraciones ni cambios de configuración de MySQL.

## Recorrido

- `login.php` presenta la identidad de Innovatrónica y el formulario existente con autocompletado de usuario y contraseña.
- Un ingreso válido dirige a `portal.php`. Si existe una sesión vigente, abrir el login también lleva al portal.
- El cambio obligatorio de contraseña sigue teniendo prioridad. Al completarlo, se vuelve al portal con el mensaje de confirmación.
- El portal verifica la sesión, el estado activo y los permisos actuales mediante `requerir_login()` antes de mostrar datos.
- Stock e inventario es el único módulo disponible. Su botón abre el dashboard existente en `index.php`.
- Los accesos rápidos se ajustan al rol: vendedor (stock y sus reservas), supervisor (stock, todas las reservas y reabastecimiento), administrador (los anteriores, catálogo y gestión de personal).
- Ventas, compras y administración son tarjetas informativas de futuras áreas; no tienen enlaces ni procesos implementados. No reemplazan la gestión de reservas ni de personal ya existente dentro del módulo de stock.
- La marca, la opción «Portal» de la navegación y el menú de cuenta permiten regresar a la selección de módulos.
- `index.php` conserva su función de dashboard de stock y el resto de las rutas de negocio siguen funcionando. Abrir la raíz con una sesión ya iniciada continúa entrando a ese dashboard; para evaluar el nuevo recorrido completo, abrir `login.php` tras cerrar sesión o abrir `portal.php`.

## Identidad y alcance

Se usan los dos PNG proporcionados por el equipo en `assets/img/marca/`, sin redibujar el logo. El icono se usa en cabecera y favicon; el logo completo se muestra sobre el fondo claro del ingreso. Las tipografías locales existentes se conservan. El CSS nuevo se concentra en `assets/css/portal.css`; los estilos de operaciones de stock no se reescriben.

En móvil se conserva el icono de marca y el avatar. El portal no muestra hamburguesa porque no tiene un menú de módulos operativo que desplegar; dentro de Stock continúa el menú móvil existente. Las acciones de contraseña y cierre de sesión siguen en el desplegable de cuenta.

Los PNG originales tienen márgenes transparentes y pesan aproximadamente 1,2 MiB en conjunto. Una optimización posterior de recursos de marca puede reducir el peso inicial; esta prueba utiliza los originales aportados.

## Cómo probar en WAMP

Primero comprobar que `git status` esté limpio. Luego:

```powershell
git fetch origin
git switch --track origin/prueba/portal-empleados
```

Si la rama local ya existe, utilizar `git switch prueba/portal-empleados`.

1. Abrir la URL habitual del proyecto terminada en `/login.php` y recargar con Ctrl + F5.
2. Ingresar con una cuenta existente: debe aparecer la selección de módulos.
3. Probar «Ingresar al módulo» y los accesos rápidos autorizados. Deben abrir las pantallas actuales de stock y reservas.
4. Desde Stock, volver mediante el logo o «Portal»; probar el menú de cuenta, cambio de contraseña y cierre de sesión.
5. Repetir con administrador, supervisor y vendedor. Una cuenta con cambio obligatorio debe seguir pasando primero por `cambiar_password.php`.
6. Probar en móvil y escritorio. Las tarjetas futuras no son botones.
7. Cerrar sesión e intentar abrir `portal.php` directamente: debe volver al login.

Para descartar la propuesta, con el árbol de trabajo limpio:

```powershell
git switch main
```

No se debe fusionar la rama de prueba hasta que el equipo decida conservar el portal. Cambiar de rama revierte los archivos de esta propuesta; las acciones reales realizadas en WAMP sobre usuarios o inventario no se revierten con Git.

## Verificación ejecutada

- PHP 8.3.6: sintaxis válida en todos los archivos PHP de la copia de trabajo.
- `php tests/portal_http.php`: 32 comprobaciones HTTP sobre un servidor PHP hijo y una SQLite temporal. Incluye acceso anónimo, credenciales, CSRF, regeneración de sesión, escape HTML, permisos y reducción de rol, cambio obligatorio de contraseña, logout y desactivación de cuenta. Nunca carga el `config/db.php` real. Requiere `pdo_sqlite`, `mbstring` y `proc_open` en PHP CLI.
- Pasaron las 170 comprobaciones existentes de `ajustes.php`, `catalogo.php`, `reservas_acciones.php`, `reservas_aprobaciones.php`, `reservas_crear.php`, `reservas_facturar.php`, `reservas_listado.php` y `stock.php` en `tests/`.
- Chromium: ingreso y portal sin desbordamiento horizontal a 320, 390, 768, 1024 y 1440 px; menú de cuenta, Escape, logout, error de login y preferencia de movimiento reducido; sin errores JavaScript.
- Los tests de concurrencia MySQL no se repitieron: esta propuesta no cambia consultas ni transacciones de inventario/reservas. La aceptación visual y la integración con las cuentas del WAMP del equipo quedan para su prueba local.

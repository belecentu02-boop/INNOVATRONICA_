<?php
/** Pruebas HTTP reales de ingreso y portal. SQLite temporal; nunca carga config/db.php del proyecto. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo CLI.'); }
if (!extension_loaded('pdo_sqlite') || !extension_loaded('mbstring') || !function_exists('proc_open')) {
    fwrite(STDERR, "INCONCLUSA: se necesitan pdo_sqlite, mbstring y proc_open en PHP CLI.\n"); exit(1);
}
$origen = dirname(__DIR__);
$temporal = sys_get_temp_dir() . '/innov_portal_test_' . bin2hex(random_bytes(6));
$proceso = null; $db = null; $pruebas = 0; $error = null;
function comprobar_portal(bool $condicion, string $mensaje): void {
    global $pruebas;
    if (!$condicion) { throw new RuntimeException($mensaje); }
    ++$pruebas;
}
function borrar_temporal_portal(string $ruta): void {
    if (!is_dir($ruta)) { return; }
    foreach (new FilesystemIterator($ruta) as $archivo) {
        if ($archivo->isDir() && !$archivo->isLink()) { borrar_temporal_portal($archivo->getPathname()); }
        else { unlink($archivo->getPathname()); }
    }
    rmdir($ruta);
}
function pedir_portal(string $ruta, array &$cookies, ?array $datos = null): array {
    global $base;
    $cabeceras = ['Connection: close'];
    if ($cookies) {
        $pares = [];
        foreach ($cookies as $nombre => $valor) { $pares[] = $nombre . '=' . $valor; }
        $cabeceras[] = 'Cookie: ' . implode('; ', $pares);
    }
    $opciones = ['method' => $datos === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5];
    if ($datos !== null) { $cabeceras[] = 'Content-Type: application/x-www-form-urlencoded'; $opciones['content'] = http_build_query($datos); }
    $opciones['header'] = implode("\r\n", $cabeceras);
    $cuerpo = file_get_contents($base . '/' . $ruta, false, stream_context_create(['http' => $opciones]));
    $recibidas = $http_response_header ?? [];
    $estado = 0; $destino = '';
    foreach ($recibidas as $cabecera) {
        if (preg_match('~^HTTP/\S+ (\d+)~', $cabecera, $m)) { $estado = (int)$m[1]; }
        if (stripos($cabecera, 'Location:') === 0) { $destino = trim(substr($cabecera, 9)); }
        if (preg_match('/^Set-Cookie: ([^=;]+)=([^;]*)/i', $cabecera, $m)) { $cookies[$m[1]] = $m[2]; }
    }
    return ['estado' => $estado, 'destino' => $destino, 'cuerpo' => (string)$cuerpo, 'cabeceras' => implode("\n", $recibidas)];
}
function token_portal(array $respuesta): string {
    if (!preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $respuesta['cuerpo'], $m)) { throw new RuntimeException('Falta token CSRF en el formulario.'); }
    return $m[1];
}
try {
    mkdir($temporal, 0700, true); mkdir($temporal . '/includes'); mkdir($temporal . '/config'); mkdir($temporal . '/sessions');
    foreach (['login.php', 'portal.php', 'logout.php', 'cambiar_password.php', 'includes/auth.php', 'includes/header.php', 'includes/footer.php'] as $archivo) {
        if (!copy($origen . '/' . $archivo, $temporal . '/' . $archivo)) { throw new RuntimeException('No se pudo copiar ' . $archivo); }
    }
    // Los assets solo se necesitan para filemtime en las plantillas; la prueba visual usa los originales.
    mkdir($temporal . '/assets'); mkdir($temporal . '/assets/css');
    foreach (['style.css', 'portal.css'] as $archivo) { copy($origen . '/assets/css/' . $archivo, $temporal . '/assets/css/' . $archivo); }
    $rutaDb = $temporal . '/fixture.sqlite';
    $config = "<?php\ndate_default_timezone_set('America/Argentina/Buenos_Aires');\nfunction db(): PDO { static \$pdo; if (!\$pdo) { \$pdo = new PDO('sqlite:' . " . var_export($rutaDb, true) . "); \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); } return \$pdo; }\n";
    file_put_contents($temporal . '/config/db.php', $config);
    $db = new PDO('sqlite:' . $rutaDb); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE usuarios (id_empleado INTEGER PRIMARY KEY, codigo_empleado TEXT, nombre TEXT, usuario TEXT, password_hash TEXT, rol TEXT, activo INT, debe_cambiar_password INT, intentos_fallidos INT DEFAULT 0, bloqueado_hasta TEXT); CREATE TABLE log_acciones (id_empleado INT, accion TEXT, detalles TEXT, ip TEXT)');
    $password = bin2hex(random_bytes(12)); $nueva = bin2hex(random_bytes(12));
    $insertar = $db->prepare('INSERT INTO usuarios VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)');
    $cuentas = [[1,'admin','ADMIN',1,0],[2,'supervisor','SUPERVISOR_COMERCIAL',1,0],[3,'vendedor','VENDEDOR_INTERNO',1,0],[4,'obligatorio','VENDEDOR_INTERNO',1,1],[5,'inactivo','ADMIN',0,0]];
    foreach ($cuentas as [$id,$usuario,$rol,$activo,$obligatorio]) {
        $insertar->execute([$id, 'TEST-' . $id, $usuario === 'admin' ? '<img src=x onerror=alert(1)>' : 'Empleado de prueba', $usuario, password_hash($password, PASSWORD_DEFAULT), $rol, $activo, $obligatorio]);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$socket) { throw new RuntimeException('No se pudo reservar puerto de prueba.'); }
    $direccion = stream_socket_get_name($socket, false); fclose($socket); $base = 'http://' . $direccion;
    $comando = [PHP_BINARY];
    if (php_ini_loaded_file()) { $comando[] = '-c'; $comando[] = php_ini_loaded_file(); }
    array_push($comando, '-d', 'session.save_path=' . $temporal . '/sessions', '-d', 'display_errors=0', '-S', $direccion, '-t', $temporal);
    $proceso = proc_open($comando, [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $temporal . '/server.log', 'a'], 2 => ['file', $temporal . '/server.log', 'a']], $pipes);
    if (!is_resource($proceso)) { throw new RuntimeException('No se pudo iniciar PHP de prueba.'); }
    $listo = false;
    for ($i=0; $i<50; ++$i) { $s = @stream_socket_client('tcp://' . $direccion, $errno, $errstr, .1); if ($s) { fclose($s); $listo=true; break; } usleep(100000); }
    comprobar_portal($listo, 'El servidor de prueba no inició.');
    $cookies = []; $r = pedir_portal('portal.php', $cookies); $cabeceraSesion = $r['cabeceras'];
    comprobar_portal($r['estado'] === 302 && $r['destino'] === 'login.php', 'Portal público sin autenticación.');
    $r = pedir_portal('login.php', $cookies); $token = token_portal($r);
    comprobar_portal($r['estado'] === 200 && str_contains($r['cuerpo'], 'autocomplete="current-password"'), 'Ingreso o autocompletado inválidos.');
    comprobar_portal(stripos($cabeceraSesion, 'HttpOnly') !== false && stripos($cabeceraSesion, 'SameSite=Lax') !== false, 'Cookie de sesión sin protección.');
    $r = pedir_portal('login.php', $cookies, ['usuario'=>'admin','password'=>$password,'csrf_token'=>'invalido']);
    comprobar_portal($r['estado'] === 200 && !esta_redirigido_portal($r), 'Se aceptó CSRF inválido.');
    $r = pedir_portal('portal.php', $cookies); comprobar_portal($r['destino'] === 'login.php', 'CSRF inválido creó sesión autenticada.');
    $r = pedir_portal('login.php', $cookies, ['usuario'=>'admin','password'=>'incorrecta','csrf_token'=>$token]);
    comprobar_portal(str_contains($r['cuerpo'], 'Usuario o contraseña incorrectos.'), 'Credenciales inválidas aceptadas.');
    $antes = $cookies['PHPSESSID'] ?? '';
    $r = pedir_portal('login.php', $cookies, ['usuario'=>'admin','password'=>$password,'csrf_token'=>$token]);
    comprobar_portal($r['destino'] === 'portal.php' && $r['estado'] === 302, 'Login no llega al portal.');
    comprobar_portal(($cookies['PHPSESSID'] ?? '') !== $antes, 'No se regeneró la sesión.');
    $admin = $cookies; $r = pedir_portal('portal.php', $admin); $tokenAdmin = token_portal($r);
    comprobar_portal($r['estado'] === 200 && str_contains($r['cuerpo'], 'href="index.php"') && str_contains($r['cuerpo'], 'href="usuarios.php"'), 'Accesos admin incompletos.');
    comprobar_portal(str_contains($r['cuerpo'], '&lt;img src=x onerror=alert(1)&gt;') && !str_contains($r['cuerpo'], '<img src=x'), 'Nombre sin escape HTML.');
    comprobar_portal(!str_contains($r['cuerpo'], 'id="navToggle"') && !str_contains($r['cuerpo'], 'mandatory-pwd-banner'), 'El portal muestra navegación o bloqueo incorrecto.');
    comprobar_portal(substr_count($r['cuerpo'], 'class="portal-future-module"') === 3 && !preg_match('/<a\s[^>]*class="portal-future-module"/', $r['cuerpo']), 'Áreas futuras navegables: ' . substr_count($r['cuerpo'], 'class="portal-future-module"'));
    comprobar_portal(str_contains(strtolower($r['cabeceras']), 'cache-control: no-store'), 'Portal permite caché privada persistente.');
    $r = pedir_portal('login.php', $admin); comprobar_portal($r['destino'] === 'portal.php', 'Sesión vigente vuelve a pedir login.');
    $db->exec("UPDATE usuarios SET rol='VENDEDOR_INTERNO' WHERE id_empleado=1");
    $r=pedir_portal('portal.php',$admin);
    comprobar_portal(!str_contains($r['cuerpo'],'href="usuarios.php"') && !str_contains($r['cuerpo'],'href="reabastecimiento.php"'), 'El portal mantiene permisos después de reducir el rol.');
    $db->exec("UPDATE usuarios SET rol='ADMIN' WHERE id_empleado=1");
    foreach (['supervisor' => true, 'vendedor' => false] as $usuario => $alertas) {
        $c=[]; $r=pedir_portal('login.php',$c); $t=token_portal($r);
        $r=pedir_portal('login.php',$c,['usuario'=>$usuario,'password'=>$password,'csrf_token'=>$t]);
        comprobar_portal($r['destino']==='portal.php', 'Login de rol falló: '.$usuario);
        $r=pedir_portal('portal.php',$c);
        comprobar_portal(!str_contains($r['cuerpo'],'href="usuarios.php"') && !str_contains($r['cuerpo'],'href="catalogo.php"'), 'Se muestran accesos admin a '.$usuario);
        comprobar_portal(str_contains($r['cuerpo'],'href="reabastecimiento.php"')===$alertas, 'Acceso de alertas incorrecto para '.$usuario);
        if ($usuario==='vendedor') { comprobar_portal(str_contains($r['cuerpo'],'Mis reservas'), 'Vendedor sin acceso a sus reservas.'); }
    }
    $c=[]; $r=pedir_portal('login.php',$c); $t=token_portal($r);
    $r=pedir_portal('login.php',$c,['usuario'=>'obligatorio','password'=>$password,'csrf_token'=>$t]);
    comprobar_portal($r['destino']==='cambiar_password.php', 'Se omitió el cambio obligatorio.');
    $r=pedir_portal('portal.php',$c); comprobar_portal($r['destino']==='cambiar_password.php', 'Acceso directo evita cambio obligatorio.');
    $r=pedir_portal('cambiar_password.php',$c); $t=token_portal($r);
    $r=pedir_portal('cambiar_password.php',$c,['csrf_token'=>$t,'password_actual'=>$password,'password_nueva'=>$nueva,'password_confirmar'=>$nueva]);
    comprobar_portal($r['destino']==='portal.php?msg=password_actualizada', 'Cambio de contraseña no vuelve al portal.');
    $r=pedir_portal('portal.php?msg=password_actualizada',$c); comprobar_portal($r['estado']===200 && str_contains($r['cuerpo'],'Tu contraseña se actualizó correctamente.'), 'Falta confirmación de contraseña.');
    $r=pedir_portal('logout.php',$admin,['csrf_token'=>'invalido']); comprobar_portal($r['estado']===403, 'Logout acepta CSRF inválido.');
    $r=pedir_portal('portal.php',$admin); comprobar_portal($r['estado']===200, 'Logout inválido cerró sesión.');
    $r=pedir_portal('logout.php',$admin,['csrf_token'=>$tokenAdmin]); comprobar_portal($r['destino']==='login.php', 'Logout no vuelve a login.');
    $r=pedir_portal('portal.php',$admin); comprobar_portal($r['destino']==='login.php', 'Portal accesible después de logout.');
    $c=[]; $r=pedir_portal('login.php',$c); $t=token_portal($r);
    pedir_portal('login.php',$c,['usuario'=>'supervisor','password'=>$password,'csrf_token'=>$t]);
    $db->exec('UPDATE usuarios SET activo=0 WHERE id_empleado=2');
    $r=pedir_portal('portal.php',$c); comprobar_portal($r['destino']==='login.php?error=desactivado', 'Cuenta desactivada mantiene acceso al portal.');
    echo "OK: $pruebas verificaciones HTTP de ingreso, roles, CSRF, cambio obligatorio y cierre de sesión.\n";
} catch (Throwable $e) { $error=$e->getMessage(); }
finally {
    if (is_resource($proceso)) { proc_terminate($proceso); proc_close($proceso); }
    $insertar=null; $db=null;
    if (is_dir($temporal)) { borrar_temporal_portal($temporal); }
}
if ($error !== null) { fwrite(STDERR, "ERROR / INCONCLUSA: $error\n"); exit(1); }
function esta_redirigido_portal(array $respuesta): bool { return $respuesta['destino'] === 'portal.php'; }

<?php
/**
 * Prueba controlada de dos facturaciones concurrentes, en una base temporal aislada.
 * Ejecutar desde CLI en el proyecto. No utiliza la base configurada como destino.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo se permite por CLI.\n"); }
require_once __DIR__ . '/../config/db.php';
define('INNOVATRONICA_STOCK_API', true); require_once __DIR__ . '/../includes/stock.php';
define('INNOVATRONICA_RESERVAS_API', true); require_once __DIR__ . '/../includes/reservas.php';
function conexion_prueba(string $base = ''): PDO {
    $dsn='mysql:host='.DB_HOST.';port='.DB_PORT.($base!==''?';dbname='.$base:'').';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::MYSQL_ATTR_INIT_COMMAND=>"SET NAMES utf8mb4, time_zone = '-03:00'"]);
}
if (($argv[1] ?? '') === '--worker') {
    $base=$argv[2]??''; $actor=$argv[3]??'';
    if (!preg_match('/^innov_test_[0-9a-f]{12}$/D',$base) || !in_array($actor,['1','2'],true)) exit(2);
    try {
        $pdo=conexion_prueba($base); $id=(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        echo 'READY:'.$id."\n";flush();
        try {$result=reservas_facturar($pdo,['id_reserva'=>'TEST-FACTURA'],(int)$actor,'ADMIN');echo json_encode(['resultado'=>'facturada','id'=>$result['id_reserva']])."\n";}
        catch (ReservasError $e) {echo json_encode(['resultado'=>'rechazada','mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE)."\n";}
    } catch (Throwable $e) {fwrite(STDERR, "Error del proceso: ".$e->getMessage()."\n");exit(1);}
    exit;
}
if (!function_exists('proc_open')) {fwrite(STDERR,"INCONCLUSA: PHP CLI necesita proc_open habilitado.\n");exit(1);}
$nombre='innov_test_'.bin2hex(random_bytes(6));$server=null;$db=null;$procesos=[];$creada=false;$fallo=null;
try {
    $server=conexion_prueba();$server->exec('CREATE DATABASE `'.$nombre.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$creada=true;
    $db=conexion_prueba($nombre);
    $db->exec('CREATE TABLE inventario (sku VARCHAR(30) PRIMARY KEY, stock_fisico INT NOT NULL, umbral_minimo INT NOT NULL) ENGINE=InnoDB');
    $db->exec("CREATE TABLE reservas (id_reserva VARCHAR(30) PRIMARY KEY, sku VARCHAR(30) NOT NULL, cantidad INT NOT NULL, id_empleado INT NOT NULL, creada_en DATETIME NOT NULL, fecha_vencimiento DATETIME NOT NULL, estado ENUM('ACTIVA','PENDIENTE_AUTORIZACION','CONFIRMADA','FACTURADA','EXPIRADA','RECHAZADA','LIBERADA') NOT NULL) ENGINE=InnoDB");
    $db->exec("CREATE TABLE movimientos_stock (id INT AUTO_INCREMENT PRIMARY KEY, sku VARCHAR(30) NOT NULL, tipo VARCHAR(30) NOT NULL, cantidad INT NOT NULL, stock_anterior INT NOT NULL, stock_nuevo INT NOT NULL, id_empleado INT NOT NULL, motivo TEXT) ENGINE=InnoDB");
    $db->exec('CREATE TABLE log_acciones (id INT AUTO_INCREMENT PRIMARY KEY, id_empleado INT NULL, accion VARCHAR(80) NOT NULL, detalles TEXT NULL, ip VARCHAR(45) NULL) ENGINE=InnoDB');
    $st=$db->prepare('INSERT INTO inventario (sku,stock_fisico,umbral_minimo) VALUES (?,1,0)');$st->execute(['TEST-CONCURRENCIA']);
    $db->exec("INSERT INTO reservas (id_reserva,sku,cantidad,id_empleado,creada_en,fecha_vencimiento,estado) VALUES ('TEST-FACTURA','TEST-CONCURRENCIA',1,1,NOW(),DATE_ADD(NOW(), INTERVAL 1 DAY),'CONFIRMADA')");
    $db->beginTransaction();$st=$db->prepare('SELECT stock_fisico FROM inventario WHERE sku=? FOR UPDATE');$st->execute(['TEST-CONCURRENCIA']);$st->fetchColumn();$st->closeCursor();
    // Archivos temporales: los pipes no bloqueantes pueden bloquear en Windows.
    foreach (['1','2'] as $actor) {
        $out=tempnam(sys_get_temp_dir(),'innout_');$err=tempnam(sys_get_temp_dir(),'innerr_');
        if ($out===false || $err===false) { if ($out!==false) @unlink($out); if ($err!==false) @unlink($err); throw new RuntimeException('No se pudieron crear archivos temporales.'); }
        $spec=[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['file',$out,'w'],2=>['file',$err,'w']];
        $p=proc_open([PHP_BINARY,__FILE__,'--worker',$nombre,$actor],$spec,$pipes);
        if (!is_resource($p)) { @unlink($out); @unlink($err); throw new RuntimeException('No se pudo iniciar un proceso de prueba.'); }
        $procesos[]=['p'=>$p,'stdout'=>$out,'stderr'=>$err,'salida'=>'','error'=>'','id'=>0];
    }
    $hasta=microtime(true)+15;$esperando=[];
    while (microtime(true)<$hasta) {
        foreach ($procesos as &$p) {$p['salida']=file_get_contents($p['stdout']);$p['error']=file_get_contents($p['stderr']);if (!$p['id'] && preg_match('/READY:(\d+)/',$p['salida'],$m))$p['id']=(int)$m[1];}unset($p);
        if ($procesos[0]['id'] && $procesos[1]['id']) {
            $st=$server->prepare('SELECT ID, INFO FROM information_schema.PROCESSLIST WHERE ID IN (?, ?)');$st->execute([$procesos[0]['id'],$procesos[1]['id']]);$esperando=[];
            foreach ($st->fetchAll() as $row) {if (is_string($row['INFO']) && stripos($row['INFO'],'SELECT stock_fisico FROM inventario')!==false && stripos($row['INFO'],'FOR UPDATE')!==false)$esperando[(int)$row['ID']]=true;}
            if (count($esperando)===2) break;
        }
        usleep(100000);
    }
    if(count($esperando)!==2) { $errores=trim($procesos[0]['error'].' '.$procesos[1]['error']); throw new RuntimeException('No se observaron ambos procesos esperando el bloqueo; la prueba es inconclusa.'.($errores!==''?' '.$errores:'')); }
    echo "Dos solicitudes independientes esperaban el mismo bloqueo de inventario.\n";
    $db->commit();
    $hasta=microtime(true)+15;
    do {
        $activos=0;foreach ($procesos as &$p) {$p['salida']=file_get_contents($p['stdout']);$p['error']=file_get_contents($p['stderr']);if(proc_get_status($p['p'])['running'])++$activos;}unset($p);
        if(!$activos)break;usleep(100000);
    } while(microtime(true)<$hasta);
    if($activos)throw new RuntimeException('Los procesos no terminaron a tiempo; prueba inconclusa.');
    $resultados=[];
    foreach ($procesos as &$p) {$p['salida']=file_get_contents($p['stdout']);$p['error']=file_get_contents($p['stderr']);foreach(explode("\n",$p['salida']) as $linea){$r=json_decode($linea,true);if(is_array($r))$resultados[]=$r;}if($p['error']!=='')throw new RuntimeException('Error de proceso: '.$p['error']);}unset($p);
    $tipos=array_column($resultados,'resultado');sort($tipos);
    $fisico=(int)$db->query("SELECT stock_fisico FROM inventario WHERE sku='TEST-CONCURRENCIA'")->fetchColumn();
    $estado=$db->query("SELECT estado FROM reservas WHERE id_reserva='TEST-FACTURA'")->fetchColumn();
    $movimientos=(int)$db->query("SELECT COUNT(*) FROM movimientos_stock WHERE sku='TEST-CONCURRENCIA' AND tipo='FACTURACION' AND cantidad=1 AND stock_anterior=1 AND stock_nuevo=0")->fetchColumn();
    $total=(int)$db->query("SELECT COUNT(*) FROM movimientos_stock")->fetchColumn();
    $logs=(int)$db->query("SELECT COUNT(*) FROM log_acciones WHERE accion='RESERVA_FACTURADA'")->fetchColumn();
    if($tipos!==['facturada','rechazada'] || $fisico!==0 || $estado!=='FACTURADA' || $movimientos!==1 || $total!==1 || $logs!==1)throw new RuntimeException('Resultado inesperado: '.json_encode(['resultados'=>$resultados,'stock'=>$fisico,'estado'=>$estado,'movimientos'=>$total,'auditoria'=>$logs],JSON_UNESCAPED_UNICODE));
    echo "OK: una facturacion, un rechazo, stock 0, un movimiento y una auditoria.\n";
} catch (Throwable $e) { $fallo=$e->getMessage(); }
finally {
    if($db && $db->inTransaction())$db->rollBack();
    foreach($procesos as &$p){if(proc_get_status($p['p'])['running'])proc_terminate($p['p']);proc_close($p['p']);@unlink($p['stdout']);@unlink($p['stderr']);}unset($p);
    $db=null;
    if($creada && $server)try{$server->exec('DROP DATABASE `'.$nombre.'`');echo "Base temporal de prueba eliminada.\n";}catch(Throwable $e){$fallo=($fallo?$fallo.' ':'').'No se pudo eliminar la base temporal '.$nombre.'.';}
}
if($fallo!==null){fwrite(STDERR,"ERROR / INCONCLUSA: ".$fallo."\n");exit(1);}

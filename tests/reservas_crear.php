<?php
/** Prueba aislada SQLite; FOR UPDATE se sustituye en adaptador y no prueba concurrencia real. */
define('INNOVATRONICA_STOCK_API', true);require __DIR__.'/../includes/stock.php';
define('INNOVATRONICA_RESERVAS_API', true);require __DIR__.'/../includes/reservas.php';
class TestCreacionPDO extends PDO {
 public bool $fallarLog=false;
 public function prepare(string $sql,array $options=[]):PDOStatement|false {
  if($this->fallarLog && strpos($sql,'INSERT INTO log_acciones')===0)throw new PDOException('Auditoría simulada');
  return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options);
 }
}
$db=new TestCreacionPDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);@$db->sqliteCreateFunction('NOW',fn()=>'2026-10-26 12:00:00');
$db->exec("CREATE TABLE inventario(sku TEXT PRIMARY KEY,stock_fisico INTEGER,umbral_minimo INTEGER); CREATE TABLE reservas(id_reserva TEXT PRIMARY KEY,sku TEXT,cantidad INTEGER,id_empleado INTEGER,creada_en TEXT,fecha_vencimiento TEXT,estado TEXT); CREATE TABLE log_acciones(id_empleado INTEGER,accion TEXT,detalles TEXT,ip TEXT); INSERT INTO inventario VALUES('A',10,5),('B',0,2),('C',10,5);");
$n=0;function check($c,$m){global $n;if(!$c)throw new RuntimeException($m);++$n;}
$a=reservas_crear($db,['sku'=>'A','cantidad'=>'3','horas'=>'24'],1,'VENDEDOR_INTERNO');check($a['estado']==='ACTIVA','Normal activa');check($a['fecha_vencimiento']==='2026-10-27 12:00:00','Vigencia 24 horas');check((int)$db->query("SELECT COUNT(*) FROM log_acciones WHERE id_empleado=1 AND accion='RESERVA_CREADA'")->fetchColumn()===1,'Auditoría');
$a=reservas_crear($db,['sku'=>'A','cantidad'=>'2'],1,'VENDEDOR_INTERNO');check($a['estado']==='PENDIENTE_AUTORIZACION','Umbral inclusivo crítico');
$a=reservas_crear($db,['sku'=>'A','cantidad'=>'1','horas'=>'48'],2,'SUPERVISOR_COMERCIAL');check($a['estado']==='ACTIVA' && $a['fecha_vencimiento']==='2026-10-28 12:00:00','Supervisor directo y vigencia');
check((int)$db->query("SELECT stock_fisico FROM inventario WHERE sku='A'")->fetchColumn()===10,'No cambia stock físico');
foreach([[['sku'=>'A','cantidad'=>'5'],1,'VENDEDOR_INTERNO'],[['sku'=>'B','cantidad'=>'1'],1,'ADMIN'],[['sku'=>'NO','cantidad'=>'1'],1,'ADMIN'],[['sku'=>'A','cantidad'=>'0'],1,'ADMIN'],[['sku'=>'A','cantidad'=>'1.5'],1,'ADMIN'],[['sku'=>'A','cantidad'=>[]],1,'ADMIN'],[['sku'=>'A','cantidad'=>'1','horas'=>'25'],1,'VENDEDOR_INTERNO'],[['sku'=>'A','cantidad'=>'1','horas'=>'721'],1,'ADMIN'],[['sku'=>'A','cantidad'=>'1'],1,'OTRO']] as [$p,$actor,$rol]){try{reservas_crear($db,$p,$actor,$rol);throw new RuntimeException('Aceptó solicitud inválida');}catch(ReservasError $e){check(true,'Solicitud rechazada');}}
$db->fallarLog=true;try{reservas_crear($db,['sku'=>'C','cantidad'=>'1'],1,'ADMIN');throw new RuntimeException('No falló auditoría');}catch(PDOException $e){}
check((int)$db->query("SELECT COUNT(*) FROM reservas WHERE sku='C'")->fetchColumn()===0,'Rollback sin auditoría');check(!$db->inTransaction(),'Transacción cerrada');
check((int)$db->query('SELECT COUNT(*) FROM reservas')->fetchColumn()===3,'Solo creaciones válidas');echo "OK: $n verificaciones de creación y auditoría.\n";

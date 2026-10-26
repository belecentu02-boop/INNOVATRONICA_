<?php
/** Prueba SQLite aislada: FOR UPDATE y DATE_ADD adaptados; no prueba concurrencia MySQL. */
define('INNOVATRONICA_STOCK_API',true);require __DIR__.'/../includes/stock.php';
define('INNOVATRONICA_RESERVAS_API',true);require __DIR__.'/../includes/reservas.php';
class TestResolverPDO extends PDO {public bool $fallarLog=false;public function prepare(string $sql,array $options=[]):PDOStatement|false {
 if($this->fallarLog && strpos($sql,'INSERT INTO log_acciones')===0)throw new PDOException('Auditoría simulada');
 return parent::prepare(str_replace([' FOR UPDATE','DATE_ADD(NOW(), INTERVAL 24 HOUR)'],['',"datetime(NOW(), '+24 hours')"],$sql),$options);
}}
$db=new TestResolverPDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);@$db->sqliteCreateFunction('NOW',fn()=>'2026-10-26 12:00:00');
$db->exec("CREATE TABLE inventario(sku TEXT PRIMARY KEY,stock_fisico INTEGER,umbral_minimo INTEGER); CREATE TABLE reservas(id_reserva TEXT PRIMARY KEY,sku TEXT,cantidad INTEGER,id_empleado INTEGER,creada_en TEXT,fecha_vencimiento TEXT,estado TEXT,motivo_rechazo TEXT,autorizado_por INTEGER); CREATE TABLE log_acciones(id_empleado INTEGER,accion TEXT,detalles TEXT,ip TEXT); INSERT INTO inventario VALUES('A',10,5),('B',1,0);");
$st=$db->prepare('INSERT INTO reservas VALUES(?,?,?,?,?,?,?,?,?)');
foreach([['P1','A',2,'PENDIENTE_AUTORIZACION','2026-10-27 10:00:00'],['P2','A',3,'PENDIENTE_AUTORIZACION','2026-10-27 10:00:00'],['P3','A',1,'PENDIENTE_AUTORIZACION','2026-10-26 12:00:00'],['P4','B',2,'PENDIENTE_AUTORIZACION','2026-10-27 10:00:00'],['P5','A',1,'PENDIENTE_AUTORIZACION','2026-10-27 10:00:00']] as $r)$st->execute([$r[0],$r[1],$r[2],1,'2026-10-25 12:00:00',$r[4],$r[3],null,null]);
$n=0;function check($x,$m){global $n;if(!$x)throw new RuntimeException($m);++$n;}
try{reservas_resolver($db,['id_reserva'=>'P1','accion'=>'aprobar'],1,'VENDEDOR_INTERNO');throw new Exception('Aceptó vendedor');}catch(ReservasError $e){check(true,'Prohíbe vendedor');}
$a=reservas_resolver($db,['id_reserva'=>'P1','accion'=>'aprobar'],2,'SUPERVISOR_COMERCIAL');$r=$db->query("SELECT * FROM reservas WHERE id_reserva='P1'")->fetch(PDO::FETCH_ASSOC);
check($a['estado']==='ACTIVA' && $r['estado']==='ACTIVA','Aprueba pendiente');check($r['fecha_vencimiento']==='2026-10-27 12:00:00','Reinicia vigencia');check((int)$r['autorizado_por']===2,'Guarda autorizante');
$a=reservas_resolver($db,['id_reserva'=>'P2','accion'=>'rechazar','motivo'=>'Sin disponibilidad comercial'],3,'ADMIN');$r=$db->query("SELECT * FROM reservas WHERE id_reserva='P2'")->fetch(PDO::FETCH_ASSOC);check($r['estado']==='RECHAZADA' && $r['motivo_rechazo']==='Sin disponibilidad comercial','Rechaza con motivo');check((int)$r['autorizado_por']===3,'Registra actor');
check((int)$db->query("SELECT SUM(cantidad) FROM reservas WHERE sku='A' AND estado IN ('ACTIVA','PENDIENTE_AUTORIZACION','CONFIRMADA')")->fetchColumn()===3,'Rechazo devuelve disponibilidad');
check((int)$db->query("SELECT COUNT(*) FROM log_acciones WHERE accion IN ('RESERVA_APROBADA','RESERVA_RECHAZADA')")->fetchColumn()===2,'Auditoría de cambios');
check($db->query("SELECT estado FROM reservas WHERE id_reserva='P3'")->fetchColumn()==='EXPIRADA','Expiración en límite exacto');
foreach([['P1','aprobar',''],['P2','rechazar','otro'],['P3','aprobar',''],['P4','aprobar',''],['P5','rechazar','   '],['NO','aprobar','']] as $c){try{reservas_resolver($db,['id_reserva'=>$c[0],'accion'=>$c[1],'motivo'=>$c[2]],2,'ADMIN');throw new Exception('Aceptó acción inválida');}catch(ReservasError $e){check(true,'Acción inválida rechazada');}}
$db->fallarLog=true;try{reservas_resolver($db,['id_reserva'=>'P5','accion'=>'rechazar','motivo'=>'prueba'],2,'ADMIN');throw new Exception('Sin error');}catch(PDOException $e){}
check($db->query("SELECT estado FROM reservas WHERE id_reserva='P5'")->fetchColumn()==='PENDIENTE_AUTORIZACION','Rollback si falla log');check(!$db->inTransaction(),'No deja transacción abierta');check((int)$db->query("SELECT stock_fisico FROM inventario WHERE sku='A'")->fetchColumn()===10,'No cambia físico');echo "OK: $n comprobaciones de autorizaciones y auditoría.\n";

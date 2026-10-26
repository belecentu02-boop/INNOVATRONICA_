<?php
/** SQLite en memoria; no valida bloqueo MySQL. */
define('INNOVATRONICA_STOCK_API',true);require __DIR__.'/../includes/stock.php';define('INNOVATRONICA_RESERVAS_API',true);require __DIR__.'/../includes/reservas.php';
class AccionPDO extends PDO {public bool $fallar=false;public function prepare(string $s,array $o=[]):PDOStatement|false {if($this->fallar&&strpos($s,'INSERT INTO log_acciones')===0)throw new PDOException('log');return parent::prepare(str_replace(' FOR UPDATE','',$s),$o);}}
$db=new AccionPDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);@$db->sqliteCreateFunction('NOW',fn()=>'2026-10-26 12:00:00');
$db->exec("CREATE TABLE inventario(sku TEXT,stock_fisico INTEGER,umbral_minimo INTEGER);CREATE TABLE reservas(id_reserva TEXT,sku TEXT,cantidad INTEGER,id_empleado INTEGER,creada_en TEXT,fecha_vencimiento TEXT,estado TEXT,motivo_rechazo TEXT,autorizado_por INTEGER);CREATE TABLE log_acciones(id_empleado INTEGER,accion TEXT,detalles TEXT,ip TEXT);INSERT INTO inventario VALUES('A',10,5);");
$st=$db->prepare('INSERT INTO reservas VALUES(?,?,?,?,?,?,?,?,?)');foreach([['R1',1,'ACTIVA','2026-10-27 12:00:00'],['R2',2,'ACTIVA','2026-10-27 12:00:00'],['R3',1,'CONFIRMADA','2026-10-25 12:00:00'],['R4',1,'ACTIVA','2026-10-26 12:00:00'],['R5',1,'PENDIENTE_AUTORIZACION','2026-10-27 12:00:00'],['R6',1,'ACTIVA','2026-10-27 12:00:00']] as $r)$st->execute([$r[0],'A',1,$r[1],'2026-10-25 12:00:00',$r[3],$r[2],null,null]);
$n=0;function check($a,$m){global $n;if(!$a)throw new Exception($m);$n++;}
try{reservas_accionar($db,['id_reserva'=>'R2','accion'=>'confirmar'],1,'VENDEDOR_INTERNO');throw new Exception('Acceso indebido');}catch(ReservasForbidden $e){check(true,'Dueño');}
$a=reservas_accionar($db,['id_reserva'=>'R1','accion'=>'confirmar'],1,'VENDEDOR_INTERNO');check($a['estado']==='CONFIRMADA','Confirmar propia');
$a=reservas_accionar($db,['id_reserva'=>'R2','accion'=>'extender','horas'=>'3'],3,'SUPERVISOR_COMERCIAL');check($db->query("SELECT fecha_vencimiento FROM reservas WHERE id_reserva='R2'")->fetchColumn()==='2026-10-27 15:00:00','Extensión sobre vencimiento');
$a=reservas_accionar($db,['id_reserva'=>'R2','accion'=>'liberar'],3,'ADMIN');check($a['estado']==='LIBERADA','Liberar activa');
$a=reservas_accionar($db,['id_reserva'=>'R3','accion'=>'liberar'],3,'ADMIN');check($a['estado']==='LIBERADA','Liberar confirmada');
foreach([['R1','confirmar','24','ADMIN'],['R4','confirmar','24','ADMIN'],['R5','confirmar','24','ADMIN'],['R6','extender','0','ADMIN'],['R6','extender','721','ADMIN'],['R6','liberar','24','VENDEDOR_INTERNO']] as $v){try{reservas_accionar($db,['id_reserva'=>$v[0],'accion'=>$v[1],'horas'=>$v[2]],1,$v[3]);throw new Exception('Aceptó acción');}catch(ReservasError $e){check(true,'Rechazar acción inválida');}}
check($db->query("SELECT estado FROM reservas WHERE id_reserva='R4'")->fetchColumn()==='EXPIRADA','Expiración exacta');
$db->fallar=true;try{reservas_accionar($db,['id_reserva'=>'R6','accion'=>'confirmar'],1,'ADMIN');throw new Exception('No falló');}catch(PDOException $e){}
check($db->query("SELECT estado FROM reservas WHERE id_reserva='R6'")->fetchColumn()==='ACTIVA','Rollback');check(!$db->inTransaction(),'Transacción cerrada');check((int)$db->query("SELECT stock_fisico FROM inventario WHERE sku='A'")->fetchColumn()===10,'Físico intacto');
check((int)$db->query("SELECT COUNT(*) FROM log_acciones WHERE accion IN ('RESERVA_CONFIRMADA','RESERVA_EXTENDIDA','RESERVA_LIBERADA')")->fetchColumn()===4,'Auditoría');echo "OK: $n verificaciones de acciones de reservas.\n";

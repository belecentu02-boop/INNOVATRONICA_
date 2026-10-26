<?php
/** Prueba aislada: requiere pdo_sqlite y mbstring, no usa la base del proyecto. */
define('INNOVATRONICA_STOCK_API', true); require __DIR__ . '/../includes/stock.php';
define('INNOVATRONICA_RESERVAS_API', true); require __DIR__ . '/../includes/reservas.php';
class TestPDO extends PDO {public function prepare(string $sql, array $options=[]): PDOStatement|false {return parent::prepare(str_replace(' FOR UPDATE','',$sql),$options);}}
$db=new TestPDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);@$db->sqliteCreateFunction('NOW',fn()=>'2026-10-26 12:00:00');
$db->exec("CREATE TABLE productos(sku TEXT PRIMARY KEY,nombre TEXT);CREATE TABLE usuarios(id_empleado INTEGER PRIMARY KEY,nombre TEXT);CREATE TABLE reservas(id_reserva TEXT PRIMARY KEY,sku TEXT,cantidad INTEGER,id_empleado INTEGER,creada_en TEXT,fecha_vencimiento TEXT,estado TEXT,motivo_rechazo TEXT);CREATE TABLE log_acciones(id_empleado INTEGER,accion TEXT,detalles TEXT,ip TEXT); INSERT INTO productos VALUES('P1','Sensor 100%'),('P2','Placa'); INSERT INTO usuarios VALUES(1,'Vendedor A'),(2,'Vendedor B'),(3,'Supervisor');");
$st=$db->prepare('INSERT INTO reservas(id_reserva,sku,cantidad,id_empleado,creada_en,fecha_vencimiento,estado) VALUES(?,?,?,?,?,?,?)');
$st->execute(['RA','P1',2,1,'2026-10-20 10:00:00','2026-10-27 12:00:00','ACTIVA']);
$st->execute(['RB','P2',1,2,'2026-10-20 11:00:00','2026-10-27 12:00:00','ACTIVA']);
$st->execute(['RC','P1',1,1,'2026-10-20 12:00:00','2026-10-25 12:00:00','PENDIENTE_AUTORIZACION']);
$st->execute(['RD','P1',1,2,'2026-10-20 13:00:00','2026-10-25 12:00:00','CONFIRMADA']);
$n=0;function check($v,$m){global $n;if(!$v)throw new RuntimeException($m);++$n;}
$a=reservas_listar($db,[],1,'VENDEDOR_INTERNO');check($a['total']===2 && count($a['reservas'])===2,'Vendedor ve solo propias');check(count(array_filter($a['reservas'],fn($r)=>$r['id_empleado']!==1))===0,'No filtra datos ajenos');
check($db->query("SELECT estado FROM reservas WHERE id_reserva='RC'")->fetchColumn()==='EXPIRADA','Vence pendiente');check($db->query("SELECT estado FROM reservas WHERE id_reserva='RD'")->fetchColumn()==='CONFIRMADA','Confirmada no vence');check((int)$db->query('SELECT COUNT(*) FROM log_acciones WHERE id_empleado IS NULL')->fetchColumn()===1,'Auditoría de expiración');
$b=reservas_listar($db,['id_empleado'=>2],1,'VENDEDOR_INTERNO');check($b['total']===2,'Parámetro externo no altera el propietario');
$b=reservas_listar($db,[],3,'SUPERVISOR_COMERCIAL');check($b['total']===4,'Supervisor ve todas');$b=reservas_listar($db,[],3,'ADMIN');check($b['total']===4,'Admin ve todas');
$b=reservas_listar($db,['estado'=>'ACTIVA'],1,'VENDEDOR_INTERNO');check($b['total']===1 && $b['reservas'][0]['id_reserva']==='RA','Filtro de estado y propietario');
$b=reservas_listar($db,['q'=>'%'],3,'ADMIN');check($b['total']===3,'Búsqueda escapa comodines');$b=reservas_listar($db,['q'=>'RB'],1,'VENDEDOR_INTERNO');check($b['total']===0,'Buscar ID ajeno no filtra información');
for($i=1;$i<=9;$i++)$st->execute(['X'.$i,'P1',1,1,'2026-10-21 10:00:00','2026-10-27 12:00:00','ACTIVA']);
$b=reservas_listar($db,['pagina'=>'99'],1,'VENDEDOR_INTERNO');check($b['total']===11 && $b['pagina']===2 && count($b['reservas'])===3,'Paginación');
foreach([['estado'=>'INEXISTENTE'],['q'=>[]],['pagina'=>'0'],['pagina'=>[]],['q'=>str_repeat('a',121)]] as $params){try{reservas_listar($db,$params,1,'VENDEDOR_INTERNO');throw new Exception('Filtro aceptado');}catch(ReservasError $e){check(true,'Filtro rechazado');}}
try{reservas_listar($db,[],1,'OTRO');throw new Exception('Rol aceptado');}catch(ReservasError $e){check(true,'Rol rechazado');}
check((int)$db->query('SELECT COUNT(*) FROM log_acciones')->fetchColumn()===1,'Expiración idempotente');
echo "OK: $n verificaciones de permisos, vencimientos, filtros y paginación.\n";

<?php
/** Prueba aislada en SQLite. No accede a la base configurada del proyecto. */
define('INNOVATRONICA_STOCK_API', true);
require __DIR__ . '/../includes/stock.php';
$n=0;
function verificar($valor, string $mensaje): void {global $n;if(!$valor)throw new RuntimeException($mensaje);++$n;}
class StockPDOPrueba extends PDO {
    public $fallarLog=false;
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        if($this->fallarLog && strpos($query,'INSERT INTO log_acciones')===0)throw new PDOException('Fallo de auditoría simulado');
        return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);
    }
}
$db=new StockPDOPrueba('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
@$db->sqliteCreateFunction('NOW',function(){return '2026-10-25 22:00:00';});
$db->exec('CREATE TABLE productos(sku TEXT PRIMARY KEY,nombre TEXT,categoria TEXT,ficha_tecnica TEXT,ubicacion_deposito TEXT);CREATE TABLE inventario(sku TEXT PRIMARY KEY,stock_fisico INTEGER,umbral_minimo INTEGER);CREATE TABLE reservas(id_reserva TEXT PRIMARY KEY,sku TEXT,cantidad INTEGER,estado TEXT,fecha_vencimiento TEXT);CREATE TABLE log_acciones(id_empleado INTEGER NULL,accion TEXT,detalles TEXT,ip TEXT);');
$st=$db->prepare('INSERT INTO productos VALUES(?,?,?,?,?)');$inv=$db->prepare('INSERT INTO inventario VALUES(?,?,?)');
foreach([['A','Sensor 100%_literal','Sensores',10,5],['B','Componente B','Displays',5,5],['C','Componente C','Displays',0,0],['D','Componente D','Sensores',1,0]] as $p){$st->execute([$p[0],$p[1],$p[2],'Ficha','A1-01']);$inv->execute([$p[0],$p[3],$p[4]]);}
$r=$db->prepare('INSERT INTO reservas VALUES(?,?,?,?,?)');
$r->execute(['R1','A',2,'ACTIVA','2026-10-26 00:00:00']);
$r->execute(['R2','A',1,'PENDIENTE_AUTORIZACION','2026-10-26 00:00:00']);
$r->execute(['R3','A',1,'CONFIRMADA','2026-10-20 00:00:00']);
$r->execute(['R4','A',3,'ACTIVA','2026-10-20 00:00:00']);
$r->execute(['R5','A',4,'PENDIENTE_AUTORIZACION','2026-10-25 22:00:00']);
$r->execute(['R6','D',3,'ACTIVA','2026-10-26 00:00:00']);
foreach(['FACTURADA','LIBERADA','RECHAZADA','EXPIRADA'] as $estado)$r->execute([$estado,'A',100,$estado,'2026-10-20 00:00:00']);
$d=stock_listar($db,[]);$p=array_column($d['productos'],null,'sku');
verificar($p['A']['stock_comprometido']===4 && $p['A']['stock_disponible']===6,'Cómputo de estados comprometidos');
verificar($p['A']['estado_stock']==='NORMAL','Estado normal');verificar($p['B']['estado_stock']==='CRITICO','Límite mínimo inclusivo');
verificar($p['C']['estado_stock']==='QUIEBRE','Cero es quiebre incluso con umbral cero');verificar($p['D']['stock_disponible']===-2 && $p['D']['estado_stock']==='INCONSISTENTE','No ocultar stock negativo');
verificar($d['resumen']===['productos'=>4,'fisico'=>16,'comprometido'=>7,'disponible'=>9],'Totales sin duplicación por reservas');
verificar((int)$db->query("SELECT COUNT(*) FROM reservas WHERE id_reserva IN ('R4','R5') AND estado='EXPIRADA'")->fetchColumn()===2,'Expiración de activas y pendientes vencidas');
verificar($db->query("SELECT estado FROM reservas WHERE id_reserva='R3'")->fetchColumn()==='CONFIRMADA','Confirmada no vence');
verificar((int)$db->query('SELECT COUNT(*) FROM log_acciones WHERE id_empleado IS NULL')->fetchColumn()===2,'Auditoría automática con actor NULL');
stock_listar($db,[]);verificar((int)$db->query('SELECT COUNT(*) FROM log_acciones')->fetchColumn()===2,'Expiración idempotente');
verificar((int)$db->query("SELECT stock_fisico FROM inventario WHERE sku='A'")->fetchColumn()===10,'No cambia stock físico');
foreach(['NORMAL','CRITICO','QUIEBRE','INCONSISTENTE'] as $estado){$x=stock_listar($db,['estado'=>$estado]);verificar($x['total']===1 && $x['productos'][0]['estado_stock']===$estado,'Filtro estado');}
$x=stock_listar($db,['q'=>'%_']);verificar($x['total']===1,'Escape LIKE literal');
$x=stock_listar($db,['categoria'=>'Displays']);verificar($x['total']===2 && $x['resumen']['fisico']===5,'Categoría y totales filtrados');
$x=stock_listar($db,['q'=>'no-existe']);verificar($x['total']===0 && $x['pagina']===1 && $x['resumen']['fisico']===0,'Estado vacío');
$a=stock_listar($db,[],true);$alertas=array_column($a['productos'],null,'sku');
verificar($a['total']===3 && !isset($alertas['A']),'Solo disponible <= mínimo');
verificar(array_column($a['productos'],'sku')===['D','C','B'],'Prioridad inconsistencia, quiebre, crítico');
verificar($alertas['B']['cantidad_sugerida']===5,'Reposición: mínimo por dos menos disponible');
verificar($alertas['C']['cantidad_sugerida']===0,'Mínimo cero y quiebre no inventan reposición');
verificar($alertas['D']['cantidad_sugerida']===2,'Saldo negativo visible y reposición según fórmula');
verificar($a['resumen']===['productos'=>3,'fisico'=>6,'comprometido'=>3,'disponible'=>3],'Resumen solo alertas');
$x=stock_listar($db,['estado'=>'NORMAL'],true);verificar($x['total']===0,'Filtro no permite escapar a productos normales');
$x=stock_listar($db,['q'=>'B'],true);verificar($x['total']===1 && $x['productos'][0]['sku']==='B','Búsqueda de alertas');
$x=stock_listar($db,['categoria'=>'Sensores'],true);verificar($x['total']===1 && $x['productos'][0]['sku']==='D','Categoría de alertas');
for($i=1;$i<=9;$i++){$st->execute(['X'.$i,'Extra '.$i,'Pruebas',null,'B1-01']);$inv->execute(['X'.$i,2,0]);}
$x=stock_listar($db,['pagina'=>'99']);verificar($x['total']===13 && $x['pagina']===2 && count($x['productos'])===5 && $x['resumen']['fisico']===34,'Paginación y resumen de todas las páginas');
foreach([['pagina'=>0],['pagina'=>[]],['pagina'=>'2.1'],['pagina'=>'2147483648'],['q'=>[]],['categoria'=>str_repeat('a',61)],['estado'=>'OTRO']] as $params){try{stock_listar($db,$params);throw new RuntimeException('Aceptó filtro inválido');}catch(StockError $e){verificar(true,'Filtro rechazado');}}
$db->exec("UPDATE inventario SET umbral_minimo=2 WHERE sku LIKE 'X%'");
$x=stock_listar($db,['pagina'=>'99'],true);verificar($x['total']===12 && $x['pagina']===2 && count($x['productos'])===4,'Paginación de alertas más de ocho productos');
$db->exec("UPDATE inventario SET umbral_minimo=0 WHERE sku LIKE 'X%'");
$r->execute(['ROLLBACK','A',1,'ACTIVA','2026-10-20 00:00:00']);$db->fallarLog=true;
try{stock_listar($db,[]);throw new RuntimeException('No falló auditoría');}catch(PDOException $e){}
verificar($db->query("SELECT estado FROM reservas WHERE id_reserva='ROLLBACK'")->fetchColumn()==='ACTIVA','Rollback de expiración si falla log');
verificar(!$db->inTransaction(),'Transacción abierta tras error');
echo "OK: $n comprobaciones de disponibilidad, vencimientos, auditoría, filtros y paginación.\n";

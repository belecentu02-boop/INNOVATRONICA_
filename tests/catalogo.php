<?php
/** Pruebas aisladas: SQLite en memoria; nunca usa la base de WAMP. */
define('INNOVATRONICA_CATALOGO_API', true);
require __DIR__ . '/../includes/catalogo.php';
$checks = 0;
function comprobar($condicion, string $detalle): void {
    global $checks;
    if (!$condicion) throw new RuntimeException($detalle);
    ++$checks;
}
function invalido(callable $fn, int $status = 422): void {
    try { $fn(); } catch (CatalogoError $e) { comprobar($e->estado === $status, 'Estado HTTP incorrecto'); return; }
    throw new RuntimeException('Se aceptó una entrada inválida.');
}
$base = ['sku'=>'esp32-test-01','nombre'=>'Módulo de prueba','categoria'=>'Sensores','ubicacion_deposito'=>'a1-01','ficha_tecnica'=>'WiFi y señales','umbral_minimo'=>'5'];
$p = catalogo_validar($base);
comprobar($p['sku'] === 'ESP32-TEST-01' && $p['ubicacion_deposito'] === 'A1-01', 'Normalización');
foreach (['-1','1.2','1e2','',true,[], '2147483648'] as $valor) invalido(function() use($valor) {catalogo_entero($valor);});
foreach ([['sku'=>'a b'],['sku'=>str_repeat('A',31)],['nombre'=>'  '],['categoria'=>[]],['ficha_tecnica'=>str_repeat('á',256)],['ubicacion_deposito'=>'a 1'],['umbral_minimo'=>'-1']] as $cambio) {
    invalido(function() use($base,$cambio) {catalogo_validar(array_merge($base,$cambio));});
}
comprobar(catalogo_entero('0') === 0 && catalogo_entero('2147483647') === 2147483647, 'Límites numéricos');
class CatalogoPDOPrueba extends PDO {
    public $fallarAuditoria = false;
    public $duplicadoMySQL = false;
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($this->fallarAuditoria && strpos($query, 'INSERT INTO log_acciones') === 0) throw new PDOException('Auditoría simulada');
        if ($this->duplicadoMySQL && strpos($query, 'INSERT INTO productos') === 0) {
            $e = new PDOException('Duplicado simulado'); $e->errorInfo = ['23000',1062,'simulado']; throw $e;
        }
        // SQLite no tiene FOR UPDATE; la concurrencia real requiere probar MySQL/WAMP.
        return parent::prepare(str_replace(' FOR UPDATE','',$query), $options);
    }
}
$pdo = new CatalogoPDOPrueba('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE productos (sku TEXT PRIMARY KEY, nombre TEXT, categoria TEXT, ficha_tecnica TEXT, ubicacion_deposito TEXT); CREATE TABLE inventario (sku TEXT PRIMARY KEY, stock_fisico INTEGER, umbral_minimo INTEGER); CREATE TABLE log_acciones (id_empleado INTEGER, accion TEXT, detalles TEXT, ip TEXT);');
$r = catalogo_guardar($pdo,$base,1,true);
comprobar($r['ok'] && (int)$pdo->query('SELECT stock_fisico FROM inventario')->fetchColumn()===0,'Alta con stock cero');
comprobar((int)$pdo->query('SELECT COUNT(*) FROM log_acciones')->fetchColumn()===1,'Auditoría alta');
$pdo->exec('UPDATE inventario SET stock_fisico=19');
$edit = array_merge($base,['nombre'=>'Nombre editado','umbral_minimo'=>'7']);
catalogo_guardar($pdo,$edit,1,false);
comprobar((int)$pdo->query('SELECT stock_fisico FROM inventario')->fetchColumn()===19,'La edición modificó stock físico');
comprobar((int)$pdo->query('SELECT umbral_minimo FROM inventario')->fetchColumn()===7,'Edición umbral');
invalido(function() use($pdo,$base){catalogo_guardar($pdo,array_merge($base,['stock_fisico'=>'999']),1,false);});
invalido(function() use($pdo,$base){catalogo_guardar($pdo,array_merge($base,['sku'=>'NO-EXISTE']),1,false);},404);
$pdo->duplicadoMySQL=true;
invalido(function() use($pdo,$base){catalogo_guardar($pdo,$base,1,true);},409);
comprobar(!$pdo->inTransaction(),'Transacción abierta tras duplicado'); $pdo->duplicadoMySQL=false;
$pdo->fallarAuditoria=true;
try { catalogo_guardar($pdo,array_merge($base,['sku'=>'ROLLBACK-01']),1,true); } catch(PDOException $e) {}
comprobar((int)$pdo->query("SELECT COUNT(*) FROM productos WHERE sku='ROLLBACK-01'")->fetchColumn()===0,'No revirtió producto');
comprobar((int)$pdo->query("SELECT COUNT(*) FROM inventario WHERE sku='ROLLBACK-01'")->fetchColumn()===0,'No revirtió inventario');
try { catalogo_guardar($pdo,array_merge($base,['nombre'=>'No persistir']),1,false); } catch(PDOException $e) {}
comprobar($pdo->query('SELECT nombre FROM productos')->fetchColumn()==='Nombre editado','No revirtió edición');
$pdo->fallarAuditoria=false;
for($i=1;$i<=18;$i++) catalogo_guardar($pdo,array_merge($base,['sku'=>'TEST-'.$i,'nombre'=>$i===1?'Sensor 100%_literal':'Sensor '.$i]),1,true);
$r=catalogo_listar($pdo,[]);comprobar($r['total']===19 && count($r['productos'])===8 && $r['paginas']===3,'Paginación inicial');
$r=catalogo_listar($pdo,['pagina'=>'999']);comprobar($r['pagina']===3 && count($r['productos'])===3,'Última página');
$r=catalogo_listar($pdo,['q'=>'%_']);comprobar($r['total']===1,'Escape de comodines LIKE');
$r=catalogo_listar($pdo,['q'=>'inexistente']);comprobar($r['total']===0 && $r['pagina']===1,'Sin resultados');
$r=catalogo_listar($pdo,['categoria'=>'No existe']);comprobar($r['total']===0,'Filtro categoría');
invalido(function() use($pdo){catalogo_listar($pdo,['q'=>[]]);});
echo "OK: $checks comprobaciones de validación, transacciones, auditoría y paginación.\n";

<?php
if (!defined('INNOVATRONICA_AJUSTES_API')) {http_response_code(403);exit('Acceso directo prohibido.');}
class AjusteError extends RuntimeException {}
function ajuste_sku(array $p): string {$sku=$p['sku']??'';if(!is_string($sku)||trim($sku)===''||mb_strlen($sku,'UTF-8')>30||preg_match('/[\x00-\x1F\x7F]/',$sku))throw new AjusteError('Ingresá un SKU válido.');return trim($sku);}
function ajuste_numero($valor,string $nombre): int {if((!is_string($valor)&&!is_int($valor))||!preg_match('/^[0-9]{1,10}$/D',(string)$valor)||(float)$valor>2147483647)throw new AjusteError($nombre.' debe ser un número entero de 0 a 2147483647.');return (int)$valor;}
function ajuste_consultar(PDO $pdo,array $p): array {
 $sku=ajuste_sku($p);stock_expirar($pdo);
 $st=$pdo->prepare("SELECT p.sku,p.nombre,i.stock_fisico,COALESCE((SELECT SUM(cantidad) FROM reservas r WHERE r.sku=i.sku AND r.estado IN ('ACTIVA','PENDIENTE_AUTORIZACION','CONFIRMADA')),0) AS comprometido FROM productos p JOIN inventario i ON i.sku=p.sku WHERE p.sku=?");$st->execute([$sku]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!$r)throw new AjusteError('No se encontró el SKU.');$r['stock_fisico']=(int)$r['stock_fisico'];$r['comprometido']=(int)$r['comprometido'];return ['ok'=>true,'producto'=>$r];
}
function ajuste_guardar(PDO $pdo,array $p,int $actor): array {
 if($actor<1)throw new AjusteError('Sesión inválida.');$sku=ajuste_sku($p);$nuevo=ajuste_numero($p['stock_nuevo']??null,'El stock nuevo');$esperado=ajuste_numero($p['stock_esperado']??null,'El stock consultado');$motivo=$p['motivo']??'';$tipo=$p['tipo']??'';
 if(!is_string($tipo)||!in_array($tipo,['INGRESO','AJUSTE'],true))throw new AjusteError('Seleccioná el tipo de movimiento.');
 if(!is_string($motivo)||mb_strlen($motivo,'UTF-8')>255||preg_match('/[\x00-\x1F\x7F]/',$motivo)||trim($motivo)==='')throw new AjusteError('Ingresá un motivo de hasta 255 caracteres.');$motivo=trim($motivo);
 stock_expirar($pdo);$pdo->beginTransaction();try {
  $st=$pdo->prepare('SELECT stock_fisico FROM inventario WHERE sku=? FOR UPDATE');$st->execute([$sku]);$actual=$st->fetchColumn();if($actual===false)throw new AjusteError('No se encontró el SKU.');$actual=(int)$actual;
  if($tipo==='INGRESO' && $nuevo<=$actual)throw new AjusteError('Un ingreso debe aumentar el stock físico.');
  if($actual!==$esperado)throw new AjusteError('El stock cambió desde la consulta. Volvé a consultar antes de ajustar.');if($nuevo===$actual)throw new AjusteError('El nuevo stock es igual al actual.');
  $st=$pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM reservas WHERE sku=? AND estado IN ('ACTIVA','PENDIENTE_AUTORIZACION','CONFIRMADA')");$st->execute([$sku]);$comprometido=(int)$st->fetchColumn();
  if($nuevo<$comprometido)throw new AjusteError('El stock físico no puede quedar por debajo de las unidades comprometidas.');
  $st=$pdo->prepare('UPDATE inventario SET stock_fisico=? WHERE sku=?');$st->execute([$nuevo,$sku]);if($st->rowCount()!==1)throw new RuntimeException('No se actualizó inventario.');
  $cantidad=abs($nuevo-$actual);
  $st=$pdo->prepare('INSERT INTO movimientos_stock (sku,tipo,cantidad,stock_anterior,stock_nuevo,id_empleado,motivo) VALUES (?,?,?,?,?,?,?)');$st->execute([$sku,$tipo,$cantidad,$actual,$nuevo,$actor,$motivo]);
  $st=$pdo->prepare('INSERT INTO log_acciones (id_empleado,accion,detalles,ip) VALUES (?,?,?,?)');$st->execute([$actor,'AJUSTE_INVENTARIO','SKU '.$sku.'; físico '.$actual.' → '.$nuevo.'; motivo: '.$motivo,$_SERVER['REMOTE_ADDR']??'127.0.0.1']);
  $pdo->commit();return ['ok'=>true,'sku'=>$sku,'anterior'=>$actual,'nuevo'=>$nuevo,'comprometido'=>$comprometido,'mensaje'=>'Stock actualizado correctamente.'];
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

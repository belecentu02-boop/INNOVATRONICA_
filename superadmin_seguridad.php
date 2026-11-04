<?php
if (!defined('SA_PANEL_ALLOWED') || !isset($pdo) || !es_superadmin()) {
    http_response_code(403); exit('Acceso denegado');
}

$sa_security=[];
try {
 $sa_security['total']=(int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
 $sa_security['inactive']=(int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo=0')->fetchColumn();
 $sa_security['locked']=(int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE bloqueado_hasta>NOW()')->fetchColumn();
 $sa_security['pending']=(int)$pdo->query('SELECT COUNT(*) FROM usuarios WHERE debe_cambiar_password=1')->fetchColumn();
 $sa_security['failed']=(int)$pdo->query("SELECT COUNT(*) FROM log_acciones WHERE accion LIKE 'LOGIN_FALLIDO%' AND fecha>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
 $sa_security['users']=$pdo->query('SELECT codigo_empleado,usuario,rol,activo,intentos_fallidos,bloqueado_hasta,debe_cambiar_password FROM usuarios WHERE activo=0 OR intentos_fallidos>0 OR bloqueado_hasta>NOW() OR debe_cambiar_password=1 ORDER BY id_empleado')->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e){error_log('SA seguridad: '.$e->getMessage());$sa_security=[];}
?>
<section class="sa-monitor"><div class="portal-section-heading"><h2>Centro de seguridad</h2><p>Estado de cuentas y controles de acceso</p></div>
<?php if(!$sa_security): ?><div class="sa-panel">No se pudo cargar el estado de seguridad.</div><?php else: ?>
<div class="sa-monitor-stats">
<?php foreach([['Cuentas',$sa_security['total']],['Inactivas',$sa_security['inactive']],['Bloqueadas ahora',$sa_security['locked']],['Cambio de clave pendiente',$sa_security['pending']],['Fallos registrados (24 h)',$sa_security['failed']]] as $m): ?><div class="sa-stat"><span class="portal-eyebrow"><?= h($m[0]) ?></span><strong><?= (int)$m[1] ?></strong></div><?php endforeach; ?></div>
<div class="sa-panel"><h2>Cuentas que requieren atención</h2>
<p class="sa-meta">Los bloqueos se calculan con el campo bloqueado_hasta. Los fallos solo incluyen los eventos que el login haya registrado.</p>
<?php if(!$sa_security['users']): ?><p>No hay cuentas con alertas.</p><?php else: ?><div class="sa-personal-scroll"><table class="sa-personal-table"><thead><tr><th>Legajo</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Fallos</th><th>Bloqueo hasta</th><th>Clave pendiente</th></tr></thead><tbody>
<?php foreach($sa_security['users'] as $e): ?><tr><td><?= h($e['codigo_empleado']) ?></td><td><?= h($e['usuario']) ?></td><td><?= h(rol_label($e['rol'])) ?></td><td><?= $e['activo']?'Activo':'Inactivo' ?></td><td><?= (int)$e['intentos_fallidos'] ?></td><td><?= h($e['bloqueado_hasta'] ?? '—') ?></td><td><?= $e['debe_cambiar_password']?'Sí':'No' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<p class="sa-meta">Este panel no muestra sesiones activas: el esquema actual no dispone de un registro persistente y verificable de sesiones.</p>
</div><?php endif; ?></section>

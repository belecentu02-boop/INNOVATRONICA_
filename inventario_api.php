<?php
header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');require_once __DIR__.'/includes/auth.php';
if(!esta_autenticado()||!verificar_sesion_activa()){if(esta_autenticado())destruir_sesion();http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Sesión no iniciada o usuario desactivado.']);exit;}
if(debe_cambiar_password_actual()){http_response_code(403);echo json_encode(['ok'=>false,'codigo'=>'PASSWORD_CHANGE_REQUIRED','error'=>'Debe cambiar su contraseña.']);exit;}
if(!es_admin()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Acceso denegado.']);exit;}
$metodo=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($metodo,['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método no permitido.']);exit;}
if($metodo==='POST'){$token=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf_token']??'');if(!is_string($token)||!verificar_csrf($token)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Token CSRF inválido.']);exit;}}
try{define('INNOVATRONICA_STOCK_API',true);require_once __DIR__.'/includes/stock.php';define('INNOVATRONICA_AJUSTES_API',true);require_once __DIR__.'/includes/ajustes.php';$r=$metodo==='GET'?ajuste_consultar(db(),$_GET):ajuste_guardar(db(),$_POST,(int)$_SESSION['u']['id_empleado']);echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
catch(AjusteError $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
catch(Throwable $e){error_log('Error en ajuste de inventario: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'No se pudo completar la operación.']);}

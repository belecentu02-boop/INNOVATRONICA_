(function(){'use strict';
 const el=id=>document.getElementById(id),consulta=el('consulta-form'),sku=el('consulta-sku'),form=el('ajuste-form'),dialog=el('ajuste-dialogo');let producto=null,enviando=false,consultando=false,secuencia=0;
 function mensaje(texto,error){const m=el('ajuste-mensaje');m.textContent=texto;m.className='alert '+(error?'alert-danger':'alert-success');m.hidden=!texto;}
 function invalidar(){producto=null;el('ajuste-datos').hidden=true;}
 sku.addEventListener('input',()=>{++secuencia;invalidar();mensaje('',false);});
 consulta.addEventListener('submit',async e=>{e.preventDefault();if(consultando||enviando)return;if(!consulta.reportValidity())return;const turno=++secuencia;consultando=true;el('consulta-boton').disabled=true;invalidar();mensaje('',false);
  try{const resp=await fetch('inventario_api.php?'+new URLSearchParams({sku:sku.value.trim()}),{cache:'no-store'});const data=await resp.json();if(turno!==secuencia)return;if(!resp.ok||!data.ok)throw new Error(data.error||'No se pudo consultar.');producto=data.producto;el('ajuste-nombre').textContent=producto.nombre;el('ajuste-sku').textContent=producto.sku;el('ajuste-fisico').textContent=producto.stock_fisico;el('ajuste-comprometido').textContent=producto.comprometido;form.elements.sku.value=producto.sku;form.elements.stock_esperado.value=producto.stock_fisico;form.elements.stock_nuevo.value=producto.stock_fisico;form.elements.motivo.value='';el('ajuste-datos').hidden=false;}
  catch(e){if(turno===secuencia)mensaje(e instanceof SyntaxError?'Respuesta inválida del servidor.':e.message,true);}finally{consultando=false;el('consulta-boton').disabled=false;}
 });
 form.addEventListener('submit',e=>{e.preventDefault();if(!producto||enviando||!form.reportValidity())return;const nuevo=Number(form.elements.stock_nuevo.value),tipo=form.elements.tipo.value,motivo=form.elements.motivo.value.trim();
  if(!Number.isInteger(nuevo)||nuevo<0||nuevo>2147483647||!motivo){mensaje('Ingresá un stock entero y un motivo válido.',true);return;}if(nuevo<producto.comprometido){mensaje('El nuevo físico no puede ser menor al stock comprometido.',true);return;}if(nuevo===producto.stock_fisico||(tipo==='INGRESO'&&nuevo<=producto.stock_fisico)){mensaje('Para un ingreso, el nuevo total debe ser mayor; la corrección debe cambiar el stock.',true);return;}
  el('ajuste-confirmacion').textContent=`${producto.nombre} (${producto.sku})\nFísico: ${producto.stock_fisico} → ${nuevo}\nTipo: ${tipo==='INGRESO'?'Ingreso por compra':'Corrección'}\nMotivo: ${motivo}`;dialog.showModal();
 });
 el('ajuste-cancelar').addEventListener('click',()=>{if(!enviando)dialog.close();});dialog.addEventListener('cancel',e=>{if(enviando)e.preventDefault();});
 el('ajuste-confirmar').addEventListener('click',async()=>{if(enviando||!producto)return;const body=new URLSearchParams(new FormData(form));enviando=true;el('ajuste-confirmar').disabled=true;el('ajuste-cancelar').disabled=true;el('consulta-boton').disabled=true;
  try{const resp=await fetch('inventario_api.php',{method:'POST',body,cache:'no-store'});const data=await resp.json();if(!resp.ok||!data.ok)throw new Error(data.error||'No se pudo ajustar.');dialog.close();mensaje(data.mensaje+` Físico: ${data.anterior} → ${data.nuevo}.`,false);invalidar();}
  catch(e){dialog.close();mensaje((e instanceof TypeError||e instanceof SyntaxError)?'No se pudo confirmar el resultado. Consultá de nuevo el stock y los movimientos antes de repetir.':e.message,true);invalidar();}
  finally{enviando=false;el('ajuste-confirmar').disabled=false;el('ajuste-cancelar').disabled=false;el('consulta-boton').disabled=false;}
 });
})();

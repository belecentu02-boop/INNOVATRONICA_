(function () {
  'use strict';
  const el = id => document.getElementById(id), busqueda = el('reservas-busqueda'), filtro = el('reservas-estado');
  const estados = {ACTIVA:['Activa','stock-normal'],PENDIENTE_AUTORIZACION:['Pendiente de autorización','stock-critico'],CONFIRMADA:['Confirmada','stock-normal'],FACTURADA:['Facturada','stock-normal'],EXPIRADA:['Expirada','stock-inconsistente'],RECHAZADA:['Rechazada','stock-inconsistente'],LIBERADA:['Liberada','stock-inconsistente']};
  let pagina=1,paginas=1,secuencia=0,cargando=false,detenido=false,controller,debounce,hayDatos=false,relojes=[],baseTiempo=0,baseMonotono=0;
  function controles() { el('reservas-anterior').disabled=cargando||pagina<=1; el('reservas-siguiente').disabled=cargando||pagina>=paginas; el('reservas-recargar').disabled=cargando; el('reservas-lista').setAttribute('aria-busy',String(cargando)); }
  function nodo(tag,texto,clase) { const n=document.createElement(tag); if(texto!==null)n.textContent=texto; if(clase)n.className=clase; return n; }
  function fecha(valor) { const d=new Date(valor.replace(' ','T')+'-03:00'); return Number.isNaN(d.getTime()) ? 'Fecha no disponible' : d.toLocaleString('es-AR',{timeZone:'America/Argentina/Buenos_Aires'}); }
  function agregarCampo(dl,nombre,valor,clase) { const div=nodo('div',null);div.append(nodo('dt',nombre),nodo('dd',valor,clase));dl.appendChild(div);return div.lastChild; }
  function actualizarRelojes() {
    const ahora=baseTiempo+(performance.now()-baseMonotono);
    for(const r of relojes) {
      const segundos=Math.max(0,Math.ceil((r.vence-ahora)/1000));
      if(!Number.isFinite(segundos)){r.nodo.textContent='Fecha no disponible';continue;}
      if(segundos===0){r.nodo.textContent='Vencida · actualizando';continue;}
      const horas=String(Math.floor(segundos/3600)).padStart(2,'0'),minutos=String(Math.floor(segundos%3600/60)).padStart(2,'0'),seg=String(segundos%60).padStart(2,'0');
      r.nodo.textContent=`${horas}:${minutos}:${seg}`;
    }
  }
  function render(data) {
    const fragment=document.createDocumentFragment(); relojes=[];baseTiempo=Date.parse(data.hora_servidor);baseMonotono=performance.now();
    for(const r of data.reservas) {
      const card=nodo('article',null,'reserva-card'),head=nodo('div',null,'reserva-head'),titulo=nodo('div',null),estado=estados[r.estado]||['Estado desconocido','stock-inconsistente'];
      titulo.append(nodo('h2',r.producto),nodo('code',r.id_reserva+' · '+r.sku));
      head.append(titulo,nodo('span',estado[0],'stock-badge '+estado[1]));card.appendChild(head);
      const dl=nodo('dl',null,'reserva-detalles'); agregarCampo(dl,'Cantidad',new Intl.NumberFormat('es-AR').format(r.cantidad));agregarCampo(dl,'Responsable',r.empleado);agregarCampo(dl,'Creada',fecha(r.creada_en));
      if(r.estado==='ACTIVA'||r.estado==='PENDIENTE_AUTORIZACION') {
        const tiempo=agregarCampo(dl,'Tiempo restante','Calculando…','reserva-tiempo');const vence=Date.parse(r.fecha_vencimiento.replace(' ','T')+'-03:00');relojes.push({nodo:tiempo,vence});
        agregarCampo(dl,'Vence',fecha(r.fecha_vencimiento));
      } else agregarCampo(dl,'Vencimiento',r.estado==='CONFIRMADA'?'No vence':fecha(r.fecha_vencimiento));
      card.appendChild(dl);fragment.appendChild(card);
    }
    el('reservas-lista').replaceChildren(fragment);pagina=data.pagina;paginas=data.paginas;
    const inicio=data.total?(pagina-1)*data.por_pagina+1:0;
    el('reservas-mensaje').textContent=data.total?`Mostrando ${inicio}–${inicio+data.reservas.length-1} de ${data.total} reservas`:'No se encontraron reservas con estos filtros.';
    el('reservas-pagina').textContent=`${pagina} / ${paginas}`;
    el('reservas-actualizacion').textContent='Última consulta: '+new Date(data.hora_servidor).toLocaleString('es-AR',{timeZone:'America/Argentina/Buenos_Aires'})+' ART';
    hayDatos=true;actualizarRelojes();
  }
  async function cargar() {
    if(detenido)return;const turno=++secuencia;if(controller)controller.abort();controller=new AbortController();cargando=true;controles();
    try {
      const params=new URLSearchParams({q:busqueda.value.trim(),estado:filtro.value,pagina:String(pagina)});
      const respuesta=await fetch('reservas_api.php?'+params,{signal:controller.signal,cache:'no-store'});const data=await respuesta.json();if(turno!==secuencia)return;
      if(!respuesta.ok||!data.ok){if(respuesta.status===401||data.codigo==='PASSWORD_CHANGE_REQUIRED'){detenido=true;const link=el('reservas-sesion');link.hidden=false;link.href=data.codigo==='PASSWORD_CHANGE_REQUIRED'?'cambiar_password.php':'login.php';link.textContent=data.codigo==='PASSWORD_CHANGE_REQUIRED'?'Cambiar contraseña':'Volver a ingresar';}throw new Error(data.error||'No se pudo consultar reservas.');}
      render(data);el('reservas-error').hidden=true;
    } catch(error) {if(error.name!=='AbortError'&&turno===secuencia){el('reservas-error').textContent=error instanceof SyntaxError?'El servidor no devolvió datos válidos.':error.message;el('reservas-error').hidden=false;el('reservas-mensaje').textContent=hayDatos?'No se pudo actualizar. Se muestran los últimos datos recibidos.':'No se pudieron cargar las reservas. Usá Actualizar para reintentar.';}}
    finally {if(turno===secuencia){cargando=false;controles();}}
  }
  function filtrar(){clearTimeout(debounce);pagina=1;cargar();}
  busqueda.addEventListener('input',()=>{++secuencia;if(controller)controller.abort();clearTimeout(debounce);pagina=1;debounce=setTimeout(cargar,250);});
  filtro.addEventListener('change',filtrar);el('reservas-limpiar').addEventListener('click',()=>{busqueda.value='';filtro.value='';filtrar();});
  el('reservas-recargar').addEventListener('click',()=>{clearTimeout(debounce);cargar();});
  el('reservas-anterior').addEventListener('click',()=>{if(!cargando&&pagina>1){--pagina;cargar();}});el('reservas-siguiente').addEventListener('click',()=>{if(!cargando&&pagina<paginas){++pagina;cargar();}});
  setInterval(actualizarRelojes,1000);setInterval(()=>{if(!document.hidden&&!cargando&&!detenido)cargar();},15000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!cargando)cargar();});cargar();
})();

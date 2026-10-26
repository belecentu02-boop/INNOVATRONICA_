(function () {
  'use strict';
  const el = id => document.getElementById(id);
  const busqueda = el('stock-busqueda'), categoria = el('stock-categoria'), nivel = el('stock-nivel');
  const formato = new Intl.NumberFormat('es-AR');
  let pagina = 1, paginas = 1, secuencia = 0, cargando = false, detenido = false, controller, debounce, hayDatos = false;
  function controles() {
    el('stock-anterior').disabled = cargando || pagina <= 1;
    el('stock-siguiente').disabled = cargando || pagina >= paginas;
    el('stock-recargar').disabled = cargando;
    el('stock-tabla').setAttribute('aria-busy', String(cargando));
  }
  function celda(texto, label, clase) {
    const td = document.createElement('td'); td.textContent = texto;
    if (label) td.dataset.label = label;
    if (clase) td.className = clase;
    return td;
  }
  function ubicacion(codigo) {
    const contenedor = document.createElement('span'); contenedor.className = 'stock-ubicacion';
    const ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg');
    for (const [k,v] of Object.entries({width:'16',height:'16',viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':'2','aria-hidden':'true',focusable:'false'})) svg.setAttribute(k,v);
    const path = document.createElementNS(ns,'path'); path.setAttribute('d','M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z');
    const circle = document.createElementNS(ns,'circle');circle.setAttribute('cx','12');circle.setAttribute('cy','10');circle.setAttribute('r','2');
    svg.append(path,circle); const code = document.createElement('code');code.textContent = codigo;
    contenedor.append(svg,code); return contenedor;
  }
  function render(data) {
    const fragment = document.createDocumentFragment();
    const estados = {NORMAL:['Normal','stock-normal'],CRITICO:['Crítico','stock-critico'],QUIEBRE:['Quiebre total','stock-quiebre'],INCONSISTENTE:['Revisar inconsistencia','stock-inconsistente']};
    for (const p of data.productos) {
      const tr = document.createElement('tr'), td = document.createElement('td'), info = document.createElement('div');info.className = 'stock-componente';
      for (const [tag,texto] of [['strong',p.nombre],['code',p.sku],['small',p.ficha_tecnica || 'Sin ficha técnica']]) {
        const nodo = document.createElement(tag);nodo.textContent = texto;info.appendChild(nodo);
      }
      td.appendChild(info);tr.appendChild(td);tr.appendChild(celda(p.categoria,'Categoría'));
      const pos = celda('','Ubicación');pos.appendChild(ubicacion(p.ubicacion_deposito));tr.appendChild(pos);
      for (const [key,label] of [['stock_fisico','Físico'],['stock_comprometido','Comprometido'],['stock_disponible','Disponible'],['umbral_minimo','Mínimo'],['cantidad_sugerida','Sugerido a reponer']]) tr.appendChild(celda(formato.format(p[key]),label,'stock-numero'+(key==='stock_disponible'?' stock-disponible':'')));
      const estado = estados[p.estado_stock] || estados.INCONSISTENTE;
      const badge = document.createElement('span');badge.className = 'stock-badge '+estado[1];badge.textContent = estado[0];
      const fin = celda('','Estado');fin.appendChild(badge);tr.appendChild(fin);fragment.appendChild(tr);
    }
    el('stock-filas').replaceChildren(fragment);
    for (const key of ['productos','fisico','comprometido','disponible']) el('stock-total-'+key).textContent = formato.format(data.resumen[key]);
    const selected = categoria.value;categoria.replaceChildren(new Option('Todas las categorías',''));
    for (const nombre of data.categorias) categoria.add(new Option(nombre,nombre));
    categoria.value = selected;
    pagina = data.pagina;paginas = data.paginas;
    const inicio = data.total ? (pagina - 1) * data.por_pagina + 1 : 0;
    el('stock-estado').textContent = data.total ? `Mostrando ${inicio}–${inicio + data.productos.length - 1} de ${data.total} componentes` : 'No hay alertas para estos filtros.';
    el('stock-pagina').textContent = `${pagina} / ${paginas}`;
    el('stock-actualizacion').textContent = 'Última consulta: '+new Date(data.actualizado_en).toLocaleString('es-AR',{timeZone:'America/Argentina/Buenos_Aires'})+' ART';
    hayDatos = true;
  }
  async function cargar() {
    if (detenido) return;
    const turno = ++secuencia;if (controller) controller.abort();controller = new AbortController();cargando = true;controles();
    const params = new URLSearchParams({accion:'listar_alertas',q:busqueda.value.trim(),categoria:categoria.value,estado:nivel.value,pagina:String(pagina)});
    try {
      const respuesta = await fetch('api.php?'+params,{signal:controller.signal,cache:'no-store'});
      const data = await respuesta.json();if (turno !== secuencia) return;
      if (!respuesta.ok || !data.ok) {
        if (respuesta.status===401 || data.codigo==='PASSWORD_CHANGE_REQUIRED') {
          detenido = true;
          const link = el('stock-sesion');link.hidden = false;
          link.href = data.codigo==='PASSWORD_CHANGE_REQUIRED' ? 'cambiar_password.php':'login.php';
          link.textContent = data.codigo==='PASSWORD_CHANGE_REQUIRED' ? 'Cambiar contraseña':'Volver a ingresar';
        }
        throw new Error(data.error || 'No se pudieron consultar las alertas.');
      }
      render(data);el('stock-error').hidden = true;
    } catch (error) {
      if (error.name!=='AbortError' && turno===secuencia) {
        const nodo = el('stock-error');nodo.textContent = error instanceof SyntaxError ? 'El servidor no devolvió datos válidos. Recargá la página y verificá tu sesión.' : error.message;nodo.hidden = false;
        el('stock-estado').textContent = hayDatos ? 'No se pudo actualizar. Los datos visibles corresponden a la última consulta exitosa.' : 'No se pudieron cargar las alertas. Usá Actualizar alertas para reintentar.';
      }
    } finally { if (turno===secuencia) {cargando = false;controles();} }
  }
  function filtrar() {clearTimeout(debounce);pagina = 1;cargar();}
  busqueda.addEventListener('input',()=>{++secuencia;if(controller)controller.abort();clearTimeout(debounce);pagina = 1;debounce = setTimeout(cargar,250);});
  categoria.addEventListener('change',filtrar);nivel.addEventListener('change',filtrar);
  el('stock-limpiar').addEventListener('click',()=>{busqueda.value = '';categoria.value = '';nivel.value = '';filtrar();});
  el('stock-recargar').addEventListener('click',()=>{clearTimeout(debounce);cargar();});
  el('stock-anterior').addEventListener('click',()=>{if(!cargando && pagina>1){--pagina;cargar();}});
  el('stock-siguiente').addEventListener('click',()=>{if(!cargando && pagina<paginas){++pagina;cargar();}});
  setInterval(()=>{if(!document.hidden && !cargando && !detenido)cargar();},15000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden && !cargando)cargar();});
  cargar();
})();

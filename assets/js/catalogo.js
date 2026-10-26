(function () {
  'use strict';
  const el = id => document.getElementById(id);
  const form = el('catalogo-form'), dialog = el('catalogo-dialogo');
  const campos = el('catalogo-campos'), guardar = el('catalogo-guardar');
  const busqueda = el('catalogo-busqueda'), categoria = el('catalogo-categoria');
  let pagina = 1, paginas = 1, secuencia = 0, controller, debounce, guardando = false, cargando = false;
  let productos = new Map(), focoPrevio;

  function mensaje(texto, error) {
    const nodo = el('catalogo-mensaje');
    nodo.textContent = texto;
    nodo.className = 'alert ' + (error ? 'alert-danger' : 'alert-success');
    nodo.hidden = !texto;
  }
  function controles() {
    el('catalogo-anterior').disabled = cargando || pagina <= 1;
    el('catalogo-siguiente').disabled = cargando || pagina >= paginas;
    el('catalogo-recargar').disabled = cargando;
    el('catalogo-tabla').setAttribute('aria-busy', String(cargando));
  }
  function opciones(categorias) {
    const seleccion = categoria.value;
    categoria.replaceChildren(new Option('Todas las categorías', ''));
    el('catalogo-categorias').replaceChildren();
    for (const nombre of categorias) {
      categoria.add(new Option(nombre, nombre));
      const opcion = document.createElement('option'); opcion.value = nombre;
      el('catalogo-categorias').appendChild(opcion);
    }
    categoria.value = seleccion;
  }
  function celda(texto, etiqueta, clase) {
    const td = document.createElement('td'); td.textContent = texto;
    if (etiqueta) td.dataset.label = etiqueta;
    if (clase) td.className = clase;
    return td;
  }
  function filas(lista) {
    productos = new Map(lista.map(p => [p.sku, p]));
    const fragment = document.createDocumentFragment();
    for (const p of lista) {
      const tr = document.createElement('tr'), td = document.createElement('td');
      const info = document.createElement('div'); info.className = 'catalogo-componente';
      for (const [tag, texto] of [['strong', p.nombre], ['code', p.sku], ['small', p.ficha_tecnica || 'Sin ficha técnica']]) {
        const nodo = document.createElement(tag); nodo.textContent = texto; info.appendChild(nodo);
      }
      td.appendChild(info); tr.appendChild(td);
      tr.appendChild(celda(p.categoria, 'Categoría'));
      tr.appendChild(celda(p.ubicacion_deposito, 'Ubicación'));
      tr.appendChild(celda(String(p.stock_fisico), 'Stock físico', 'catalogo-numero'));
      tr.appendChild(celda(String(p.umbral_minimo), 'Mínimo', 'catalogo-numero'));
      const acciones = document.createElement('td'), boton = document.createElement('button');
      boton.type = 'button'; boton.className = 'btn btn-outline'; boton.textContent = 'Editar';
      boton.setAttribute('aria-label', 'Editar ' + p.sku);
      boton.addEventListener('click', () => abrir(p.sku));
      acciones.appendChild(boton); tr.appendChild(acciones); fragment.appendChild(tr);
    }
    el('catalogo-filas').replaceChildren(fragment);
  }
  async function cargar() {
    const turno = ++secuencia;
    if (controller) controller.abort();
    controller = new AbortController(); cargando = true; controles();
    el('catalogo-estado').textContent = 'Cargando catálogo…';
    const params = new URLSearchParams({ accion: 'listar_productos', q: busqueda.value.trim(), categoria: categoria.value, pagina: String(pagina) });
    try {
      const respuesta = await fetch('api.php?' + params, { signal: controller.signal, cache: 'no-store' });
      const data = await respuesta.json();
      if (turno !== secuencia) return;
      if (!respuesta.ok || !data.ok) throw new Error(data.error || 'No se pudo consultar el catálogo.');
      pagina = data.pagina; paginas = data.paginas;
      filas(data.productos); opciones(data.categorias);
      const inicio = data.total ? (pagina - 1) * data.por_pagina + 1 : 0;
      el('catalogo-estado').textContent = data.total
        ? `Mostrando ${inicio}–${inicio + data.productos.length - 1} de ${data.total} productos`
        : 'No se encontraron productos. Cambiá los filtros o creá un nuevo componente.';
      el('catalogo-pagina').textContent = `${pagina} / ${paginas}`;
    } catch (error) {
      if (error.name !== 'AbortError' && turno === secuencia) {
        mensaje(error instanceof SyntaxError ? 'El servidor no devolvió una respuesta válida. Recargá la página y verificá tu sesión.' : (error.message || 'No se pudo conectar con el servidor.'), true);
        el('catalogo-estado').textContent = 'Consulta fallida. Usá Actualizar lista para reintentar.';
      }
    } finally {
      if (turno === secuencia) { cargando = false; controles(); }
    }
  }
  function abrir(sku) {
    if (guardando) return;
    const p = sku ? productos.get(sku) : null;
    if (sku && !p) return;
    focoPrevio = document.activeElement;
    form.reset(); el('catalogo-form-error').hidden = true;
    el('catalogo-accion').value = p ? 'editar_producto' : 'crear_producto';
    el('catalogo-form-titulo').textContent = p ? 'Editar componente' : 'Nuevo producto';
    for (const [id, key] of [['producto-sku','sku'],['producto-nombre','nombre'],['producto-categoria','categoria'],['producto-ubicacion','ubicacion_deposito'],['producto-ficha','ficha_tecnica'],['producto-minimo','umbral_minimo']]) {
      el(id).value = p ? (p[key] ?? '') : (key === 'umbral_minimo' ? '5' : '');
    }
    el('producto-sku').readOnly = !!p;
    dialog.showModal(); el(p ? 'producto-nombre' : 'producto-sku').focus();
  }
  function cerrar() { if (!guardando) dialog.close(); }
  dialog.addEventListener('cancel', event => { if (guardando) event.preventDefault(); });
  dialog.addEventListener('close', () => {
    const destino = focoPrevio && focoPrevio.isConnected ? focoPrevio : el('catalogo-nuevo');
    destino.focus();
  });
  el('catalogo-nuevo').addEventListener('click', () => abrir());
  el('catalogo-cerrar').addEventListener('click', cerrar);
  el('catalogo-cancelar').addEventListener('click', cerrar);
  busqueda.addEventListener('input', () => {
    // Invalidate in-flight responses immediately, even during the debounce delay.
    ++secuencia; if (controller) controller.abort();
    clearTimeout(debounce); pagina = 1;
    debounce = setTimeout(cargar, 250);
  });
  categoria.addEventListener('change', () => { clearTimeout(debounce); pagina = 1; cargar(); });
  el('catalogo-limpiar').addEventListener('click', () => { clearTimeout(debounce); busqueda.value = ''; categoria.value = ''; pagina = 1; cargar(); });
  el('catalogo-recargar').addEventListener('click', () => { clearTimeout(debounce); cargar(); });
  el('catalogo-anterior').addEventListener('click', () => { if (!cargando && pagina > 1) { --pagina; cargar(); } });
  el('catalogo-siguiente').addEventListener('click', () => { if (!cargando && pagina < paginas) { ++pagina; cargar(); } });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (guardando) return;
    const datos = new FormData(form);
    guardando = true; campos.disabled = true;
    el('catalogo-form-error').hidden = true;
    if (window.InnovatronicaUI) window.InnovatronicaUI.setBusy(guardar, true, 'Guardando…');
    else { guardar.disabled = true; guardar.textContent = 'Guardando…'; }
    let exito = false;
    try {
      const respuesta = await fetch('api.php', { method: 'POST', body: datos });
      const data = await respuesta.json();
      if (!respuesta.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar el producto.');
      exito = true; mensaje(data.mensaje, false);
    } catch (error) {
      const nodo = el('catalogo-form-error'); nodo.textContent = error instanceof SyntaxError ? 'No se pudo confirmar el resultado. Cerrá el formulario y actualizá el catálogo antes de reintentar.' : (error.message || 'Error de conexión. Revisá el catálogo antes de reintentar.'); nodo.hidden = false;
    } finally {
      guardando = false; campos.disabled = false;
      if (window.InnovatronicaUI) window.InnovatronicaUI.setBusy(guardar, false);
      else { guardar.disabled = false; guardar.textContent = 'Guardar producto'; }
    }
    if (exito) { dialog.close(); await cargar(); }
  });
  cargar();
})();

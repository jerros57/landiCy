// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors.
/* LandiCy, editor de bloques accesible, sin dependencias y sin HTML ejecutable. */
(() => {
  'use strict';
  const form = document.getElementById('editor-form');
  if (!form) return;
  const target = document.getElementById('blocks');
  const output = document.getElementById('blocks-json');
  const choices = document.getElementById('new-type');
  let blocks;
  try { blocks = JSON.parse(document.getElementById('initial-blocks').textContent); }
  catch { blocks = []; }
  const labels = {hero:'Portada / Hero',text:'Sección de texto',features:'Tarjetas de características',cta:'Llamada a la acción',footer:'Pie de página'};
  const fieldLabels = {eyebrow:'Etiqueta',title:'Título',subtitle:'Subtítulo',body:'Contenido',button_text:'Texto del botón',button_url:'Enlace del botón',copyright:'Texto de copyright'};
  const fields = {
    hero:['eyebrow','title','subtitle','button_text','button_url'],
    text:['title','body'],features:['title'],
    cta:['title','body','button_text','button_url'],footer:['copyright']
  };
  function element(tag, attrs = {}, text = null) {
    const node = document.createElement(tag);
    for (const [key, val] of Object.entries(attrs)) {
      if (key === 'className') node.className = val;
      else node.setAttribute(key, val);
    }
    if (text !== null) node.textContent = text;
    return node;
  }
  function button(text, title, handler) {
    const el = element('button', {type:'button', title}, text);
    el.addEventListener('click', handler);
    return el;
  }
  function render() {
    target.replaceChildren();
    blocks.forEach((block, index) => {
      const card = element('section', {className:'block-edit'});
      const bar = element('div', {className:'block-bar'});
      const name = element('h3', {}, `${index + 1}. ${labels[block.type] || block.type}`);
      const actions = element('div', {className:'actions'});
      actions.append(button('↑','Subir bloque', () => {if(index){[blocks[index-1],blocks[index]]=[blocks[index],blocks[index-1]];render();}}));
      actions.append(button('↓','Bajar bloque', () => {if(index<blocks.length-1){[blocks[index],blocks[index+1]]=[blocks[index+1],blocks[index]];render();}}));
      actions.append(button('×','Eliminar bloque', () => {if(confirm('¿Eliminar este bloque del borrador?')){blocks.splice(index,1);render();}}));
      bar.append(name,actions);card.append(bar);
      (fields[block.type] || []).forEach(key => {
        const label = element('label', {}, fieldLabels[key] || key);
        const input = (key === 'body' || key === 'subtitle') ? element('textarea',{rows:'3'}) : element('input',{type:'text'});
        input.value = block[key] || '';
        input.addEventListener('input', () => block[key] = input.value);
        label.append(input);card.append(label);
      });
      if(block.type === 'features') {
        const label = element('label', {}, 'Tarjetas (una por línea: título | descripción)');
        const input = element('textarea', {rows:'6'});
        input.value = (block.items || []).map(item => `${item.title} | ${item.text}`).join('\n');
        input.addEventListener('input', () => {
          block.items = input.value.split('\n').filter(line => line.trim()).slice(0,12).map(line => {
            const separator = line.indexOf('|');
            return separator < 0 ? {title:line.trim(), text:''} : {title:line.slice(0,separator).trim(),text:line.slice(separator+1).trim()};
          });
        });
        label.append(input);card.append(label);
      }
      target.append(card);
    });
  }
  document.getElementById('add-block').addEventListener('click', () => {
    if(blocks.length >= 40){alert('Máximo 40 bloques');return;}
    const type = choices.value;
    const block = {type};
    (fields[type] || []).forEach(f => block[f]='');
    if (type === 'features') block.items=[{title:'Nuevo elemento',text:'Descripción'}];
    blocks.push(block);render();
  });
  form.addEventListener('submit', event => {
    if(!Array.isArray(blocks)){event.preventDefault();return;}
    output.value = JSON.stringify(blocks);
  });
  render();
})();

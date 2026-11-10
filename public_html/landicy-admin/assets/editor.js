// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors.
// LandiCy 0.4.2: enlaces internos, arrastre en el lienzo, cuadros de texto e incorporación segura.
(() => {
  'use strict';
  const form = document.getElementById('editor-form');
  if (!form) return;
  const frame = document.getElementById('live-frame');
  const holder = document.getElementById('frame-holder');
  const inspector = document.getElementById('blocks');
  const list = document.getElementById('section-list');
  const stateLabel = document.getElementById('editor-state');
  const output = document.getElementById('blocks-json');
  let blocks;
  try { blocks = JSON.parse(document.getElementById('initial-blocks').textContent); }
  catch { blocks = []; }
  if (!Array.isArray(blocks)) blocks = [];
  blocks.forEach(b=>{if(b&&!b.id)b.id='b-'+crypto.randomUUID();});
  blocks = blocks.filter(b => b && b.type !== 'footer'); // Los pies antiguos se sustituyen por el pie global.
  let canvasTools=null,saver=null;
  let selected = blocks.length ? 0 : -1;
  let dirty = false;
  let submitted = false;
  let currentTab = 'insert';
  let dragging = -1;
  const MAX = 40;
  const undoStack=[], redoStack=[];
  let lastSnapshot='';
  const snapshot=()=>JSON.stringify({blocks,fields:Array.from(form.querySelectorAll('input[id],select[id],textarea[id]')).filter(el=>el.id!=='blocks-json'&&!el.closest('.canvas-controls')).map(el=>[el.id,el.value])});
  function remember(){const now=snapshot();if(lastSnapshot && now!==lastSnapshot){undoStack.push(lastSnapshot);if(undoStack.length>60)undoStack.shift();redoStack.length=0;}lastSnapshot=now;}
  function restore(from,to){if(!from.length)return;to.push(snapshot());const state=JSON.parse(from.pop());blocks=state.blocks;state.fields.forEach(([id,value])=>{const el=document.getElementById(id);if(el)el.value=value;});lastSnapshot=snapshot();selected=Math.min(selected,blocks.length-1);markDirty();renderList();renderInspector();renderPreview();renderMenuControls();}
  let media = [];
  try { media = JSON.parse(document.getElementById('site-media').textContent); } catch { media = []; }
  const siteId = Number(document.getElementById('site-id')?.textContent || 0);
  const pages = Array.from(document.querySelectorAll('.editor-page-link')).map(link => {
    const href = new URL(link.href, location.href);
    return {slug: link.querySelector('small')?.textContent?.replace(/^\//,'') || 'index',id:href.searchParams.get('page'),name:link.querySelector('strong')?.textContent||'Página',href:link.href};
  });
  const pageBySlug = slug => pages.find(p=>p.slug===slug);


  function embedUrl(value){
    try{
      let raw=String(value||'').trim();
      if(raw.startsWith('<')){const parsed=new DOMParser().parseFromString(raw,'text/html');const frames=parsed.querySelectorAll('iframe');raw=frames.length===1?frames[0].getAttribute('src')||'':'';}
      raw=raw.replace(/&amp;/g,'&');const u=new URL(raw);if(u.protocol!=='https:'||u.username||u.password||u.port)return '';
      let id='';const hosts=['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'];
      if(u.hostname==='youtu.be')id=u.pathname.split('/')[1];
      if(hosts.includes(u.hostname)){if(u.pathname==='/watch')id=u.searchParams.get('v');else id=u.pathname.match(/^\/(?:embed|shorts)\/([A-Za-z0-9_-]{11})\/?$/)?.[1]||'';}
      if(/^[A-Za-z0-9_-]{11}$/.test(id||'')){const target=new URL('https://www.youtube-nocookie.com/embed/'+id);for(const k of ['controls','rel','autoplay','mute','loop'])if(['0','1'].includes(u.searchParams.get(k)))target.searchParams.set(k,u.searchParams.get(k));for(const k of ['start','end'])if(/^\d{1,6}$/.test(u.searchParams.get(k)||''))target.searchParams.set(k,String(Number(u.searchParams.get(k))));if(!target.searchParams.has('start')&&/^\d{1,6}s?$/.test(u.searchParams.get('t')||''))target.searchParams.set('start',String(parseInt(u.searchParams.get('t'),10)));return target.href;}
      if(u.hostname==='www.google.com'&&/^\/maps\/embed(?:\/|$)/.test(u.pathname))return u.href;
      if(u.hostname==='docs.google.com'&&/^\/forms\/d\/e\/[A-Za-z0-9_-]+\/viewform$/.test(u.pathname))return u.href;
    }catch{}return '';
  }

  const defs = {
    heading:{label:'Título',icon:'T',fields:['title','align','size']},button:{label:'Botón',icon:'↗',fields:['button_text','button_kind','button_page','button_url']},form:{label:'Formulario por correo',icon:'✉',fields:['title','recipient','button_text']},
    hero: {label:'Portada', icon:'▣', fields:['eyebrow','title','subtitle','button_text','button_kind','button_page','button_url','image_url']},
    text: {label:'Texto', icon:'≡', fields:['title','body','align','size']},
    text_box: {label:'Cuadro de texto',icon:'▤',fields:['body','align','size']},
    features: {label:'Tarjetas', icon:'▦', fields:['title','items']},
    cta: {label:'Botón y llamada', icon:'↗', fields:['title','body','button_text','button_kind','button_page','button_url']},
    image: {label:'Imagen', icon:'▧', fields:['title','src','alt','caption','fit','link_kind','link_page','link_url']},
    columns: {label:'Dos columnas', icon:'◫', fields:['title','left','right']},
    quote: {label:'Cita', icon:'❝', fields:['body','author']},
    divider: {label:'Separador', icon:'—', fields:[]},
    imported:{label:'HTML importado',icon:'⌘',fields:['path']},
    spacer:{label:'Espacio',icon:'↕',fields:[]},
    gallery:{label:'Galería',icon:'▧',fields:['images']},
    embed: {label:'Incorporar',icon:'⌘',fields:['title','embed_mode','url']}
  };
  const fieldLabels = {
    recipient:'Correo destinatario (abre la aplicación del visitante)',eyebrow:'Etiqueta superior', title:'Título', subtitle:'Subtítulo', body:'Contenido',
    button_text:'Texto del botón', button_url:'Enlace del botón', button_kind:'Destino del botón', button_page:'Página de destino', align:'Alineación',size:'Tamaño del texto',embed_mode:'Modo de incorporación',url:'URL o iframe seguro',
    image_url:'Imagen de portada', src:'Imagen',
    link_kind:'Destino de la imagen',link_page:'Página de destino',link_url:'URL de la imagen',fit:'Ajuste de imagen',images:'Una ruta de imagen por línea',path:'Archivo importado (solo lectura)',alt:'Descripción accesible', caption:'Pie de foto', left:'Columna izquierda',
    right:'Columna derecha', author:'Autor', items:'Tarjetas'
  };
  const fontMap = {
    system:'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
    arial:'Arial, Helvetica, sans-serif', verdana:'Verdana, Geneva, sans-serif',
    trebuchet:'"Trebuchet MS", Arial, sans-serif', georgia:'Georgia, "Times New Roman", serif',
    times:'"Times New Roman", Times, serif', mono:'ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace'
  };
  const themeIds = ['text_color','muted_color','background_alt','border_color','theme-primary','theme-accent','theme-background','font_body','font_heading','base_size','title_scale'];
  const byId = id => document.getElementById(id);
  const create = (tag, cls, text) => {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    if (text !== undefined) el.textContent = String(text);
    return el;
  };
  function button(label, description, onClick, cls='') {
    const el = create('button', cls, label);
    el.type = 'button'; el.title = description; el.setAttribute('aria-label',description);
    el.addEventListener('click',onClick);
    return el;
  }
  const historyBar=document.createElement('div');historyBar.className='actions';
  historyBar.append(button('↶ Deshacer','Deshacer cambio',()=>restore(undoStack,redoStack)),button('↷ Rehacer','Rehacer cambio',()=>restore(redoStack,undoStack)));
  form.querySelector('.editor-toolbar')?.append(historyBar);

  function markDirty() {
    saver?.schedule();
    dirty = true;
    stateLabel.textContent = '● Sin guardar';
    stateLabel.classList.add('unsaved');
    persistRecovery();
  }
  function changed(affectsList=false) {
    remember();
    markDirty();
    renderPreview();
    if (affectsList) renderList();
  }
  function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.editor-tab').forEach(el => {
      const active = el.dataset.tab === tab;
      el.classList.toggle('active',active);
      el.setAttribute('aria-selected',String(active));
    });
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.toggle('active',el.dataset.pane === tab));
  }
  document.querySelectorAll('.editor-tab').forEach(el => el.addEventListener('click',() => switchTab(el.dataset.tab)));

  function defaultBlock(type) {
    const values = {
      heading:{title:'Nuevo título',align:'left',size:'normal'},button:{button_text:'Abrir enlace',button_url:'#',button_kind:'link',button_page:'index'},form:{title:'Contacto',recipient:'',button_text:'Abrir correo'},
      hero:{eyebrow:'NUEVO',title:'Título impactante',subtitle:'Escribe algo que invite a seguir explorando.',button_text:'Conoce más',button_url:'#informacion',button_kind:'link',button_page:'index',image_url:''},
      text:{title:'Nueva sección',body:'Haz doble clic en este texto para modificarlo.',align:'left',size:'normal'},
      text_box:{body:'Haz doble clic para editar este cuadro de texto.',align:'left',size:'normal'},
      features:{title:'Nuestros beneficios',items:[{title:'Primera tarjeta',text:'Descripción breve'},{title:'Segunda tarjeta',text:'Descripción breve'},{title:'Tercera tarjeta',text:'Descripción breve'}]},
      cta:{title:'¿Empezamos?',body:'Invita al visitante a dar el siguiente paso.',button_text:'Contáctanos',button_url:'mailto:contacto@ejemplo.com',button_kind:'link',button_page:'index'},
      image:{title:'',src:'',alt:'Descripción de la imagen',caption:''},
      columns:{title:'Dos ideas lado a lado',left:'Contenido de la columna izquierda.',right:'Contenido de la columna derecha.'},
      quote:{body:'Una frase memorable para destacar.',author:'Autor'},
      embed:{title:'Contenido incorporado',url:''},
      gallery:{images:''},spacer:{},divider:{}
    };
    return {id:'b-'+crypto.randomUUID(),type,...values[type]};
  }
  function addBlock(type) {
    if (blocks.length >= MAX) { alert('Límite de 40 secciones por página.'); return; }
    blocks.push(defaultBlock(type));
    selected = blocks.length - 1;
    renderInspector();renderList();changed();switchTab('edit');
    scrollSelectedIntoView();
  }
  function renderInsert() {
    const grid = byId('insert-grid');
    grid.replaceChildren();
    for (const [type,def] of Object.entries(defs)) {
      if(type==='imported')continue;
      const card = button('',`Añadir ${def.label}`,()=>addBlock(type),'insert-card');
      card.append(create('span','insert-icon',def.icon),create('span','insert-label',def.label));
      grid.append(card);
    }
  }
  function select(index, focus=false) {
    if (index < 0 || index >= blocks.length) return;
    selected = index;
    renderInspector();renderList();paintSelection();switchTab('edit');
    if (focus) scrollSelectedIntoView();
  }
  function renderList() {
    list.replaceChildren();
    blocks.forEach((b,index) => {
      const def = defs[b.type] || {label:b.type,icon:'▫'};
      const item = create('button','section-item'+(index===selected?' active':''));
      item.type='button'; item.draggable = true;
      item.append(create('span','section-drag','⠿'), create('span','section-type',def.icon), create('span','section-name',`${index + 1}. ${String(b.title || def.label).slice(0,55)}`));
      item.addEventListener('click',()=>select(index,true));
      item.addEventListener('dragstart',event=>{dragging=index;event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',String(index));});
      item.addEventListener('dragover',event=>{event.preventDefault();event.dataTransfer.dropEffect='move';});
      item.addEventListener('drop',event=>{
        event.preventDefault();
        const from = dragging; dragging=-1;
        if (from<0 || from>=blocks.length || from===index) return;
        const [moved]=blocks.splice(from,1);blocks.splice(index,0,moved);
        selected=index;renderList();renderInspector();changed();
      });
      list.append(item);
    });
    if (!blocks.length) list.append(create('p','pane-intro','Esta página está vacía. Inserta un bloque.'));
  }
  function move(direction) {
    const target=selected+direction;
    if (selected<0 || target<0 || target>=blocks.length) return;
    [blocks[selected],blocks[target]]=[blocks[target],blocks[selected]];
    selected=target;renderInspector();renderList();changed();scrollSelectedIntoView();
  }
  function duplicate() {
    if(selected<0) return;
    if(blocks.length>=MAX) {alert('Límite de 40 secciones.');return;}
    const block=JSON.parse(JSON.stringify(blocks[selected]));block.id="b-"+crypto.randomUUID();
    blocks.splice(selected+1,0,block);selected++;
    renderInspector();renderList();changed();scrollSelectedIntoView();
  }
  function remove() {
    if(selected<0 || !confirm('¿Eliminar esta sección del borrador?')) return;
    blocks.splice(selected,1);
    selected=Math.min(selected,blocks.length-1);
    renderInspector();renderList();changed();
  }
  function renderInspector() {
    inspector.replaceChildren();
    if (selected<0 || !blocks[selected]) {inspector.append(create('p','pane-intro','Selecciona una sección en el lienzo o añade un bloque nuevo.'));return;}
    const block=blocks[selected];
    const def=defs[block.type];
    const heading=create('div','inspector-heading');
    heading.append(create('span','inspector-symbol',def.icon),create('div','',def.label));
    inspector.append(heading);
    const actions=create('div','inspector-actions');
    actions.append(button('↑','Mover hacia arriba',()=>move(-1)),button('↓','Mover hacia abajo',()=>move(1)),button('⧉','Duplicar sección',duplicate),button('🗑','Eliminar sección',remove));
    inspector.append(actions);
    def.fields.forEach(key=>{
      const label=create('label','inspector-label',fieldLabels[key]||key);
      if(['embed_mode','button_kind','button_page','link_kind','link_page','fit','align','size'].includes(key)) {
        const select=create('select','inspector-select');
        const options=key==='embed_mode'?[['url','URL del proveedor'],['html','HTML iframe (se extrae solo src)']]:key==='fit'?[['cover','Recortar para llenar'],['contain','Mostrar completa']]:['button_kind','link_kind'].includes(key)?[['link','Enlace / URL'],['page','Página de este sitio']]:
          ['button_page','link_page'].includes(key)?pages.map(p=>[p.slug,p.name]):
          key==='align'?[['left','Izquierda'],['center','Centro'],['right','Derecha']]:
          [['small','Pequeño'],['normal','Normal'],['large','Grande'],['huge','Muy grande']];
        options.forEach(([value,name])=>select.append(new Option(name,value)));
        select.value=String(block[key]||options[0]?.[0]||'');
        select.addEventListener('change',()=>{block[key]=select.value;changed();renderInspector();});
        label.append(select);
        if((key==='button_page' && block.button_kind!=='page')||(key==='link_page' && block.link_kind!=='page')) label.hidden=true;
        if(key==='button_kind') label.append(create('small','field-hint','Elige una página del sitio o introduce una URL externa.'));
        inspector.append(label);return;
      }
      if((key==='button_url' && block.button_kind==='page')||(key==='link_url' && block.link_kind==='page')) return;
      const multiline=(['body','subtitle','left','right','images'].includes(key)||(key==='url'&&block.embed_mode==='html'));
      let input;
      if(key==='items') {
        input=create('textarea');input.rows=7;
        input.value=(block.items||[]).map(item=>`${item.title||''} | ${item.text||''}`).join('\n');
      } else {
        input=create(multiline?'textarea':'input');
        if(multiline) input.rows=4; else input.type='text';
        input.value=String(block[key]||'');
      }
      input.maxLength=key==='items'?12000:(['src','image_url'].includes(key)?2048:12000);
      input.setAttribute('autocomplete','off');
      if(['src','image_url'].includes(key)) input.placeholder='https://ejemplo.com/imagen.jpg';
      if(key==='button_url') input.placeholder='https://... o #informacion';
      if(key==='path')input.readOnly=true;
      if(key==='url') input.placeholder='https://www.youtube-nocookie.com/embed/... (Maps o Forms)';
      input.addEventListener('input',()=>{
        if(key==='items') {
          block.items=input.value.split('\n').filter(Boolean).slice(0,12).map(line=>{
            const pos=line.indexOf('|');
            return pos<0?{title:line.trim().slice(0,300),text:''}:{title:line.slice(0,pos).trim().slice(0,300),text:line.slice(pos+1).trim().slice(0,1200)};
          });
        } else {block[key]=input.value;if(block.rich)delete block.rich[key];}
        changed(key==='title');
      });
      label.append(input);
      if(['src','image_url'].includes(key)) {
        const picker=create('select','image-picker');
        picker.append(new Option('— Elegir imagen subida —',''));
        media.forEach(item=>picker.append(new Option(item.name,item.path)));
        picker.addEventListener('change',()=>{
          if(picker.value){block[key]=picker.value;input.value=picker.value;changed();}
        });
        label.append(picker);
        const helper=create('small','field-hint');
        helper.append('Usa una URL HTTPS o una ruta img/ de tu biblioteca. ');
        const link=create('a','','Subir nueva imagen →');link.href=`?action=media-library&id=${siteId}`;
        helper.append(link);label.append(helper);
      }
      if(key==='url')label.append(create('small','field-hint','YouTube /feed/yot no es un vídeo: usa un botón de enlace. Para Maps/Forms usa la URL de inserción HTTPS.'));
      inspector.append(label);
    });
    layoutInspector(block);
    inspector.append(create('p','field-hint','También puedes hacer doble clic sobre los títulos o textos del lienzo para editarlos directamente.'));
  }

  const safeLink=value=>{
    const text=String(value||'').trim();
    if (/^(https?:\/\/|mailto:|tel:)/i.test(text)) return text;
    if (/^#[a-z0-9_-]*$/i.test(text)) return text;
    if (/^\/(?!\/)[^\\\x00-\x1f]*$/.test(text)) return text;
    return '#';
  };
  const safeImage=value=>{
    const text=String(value||'').trim();
    const local=text.match(/^img\/([a-f0-9]{24}\.(?:jpg|png|webp|gif))$/);
    if(local && media.some(item=>item.path===text)) return `?action=media&id=${siteId}&file=${encodeURIComponent(local[1])}`;
    if(text.length>2048 || /[\x00-\x20\x7f]/.test(text)) return '';
    try { const url=new URL(text);return url.protocol==='https:' && !url.username && !url.password && url.hostname ? text:''; }
    catch {return '';}
  };
  const fieldNode=(doc,tag,cls,value,field)=>{
    const el=doc.createElement(tag);
    if(cls)el.className=cls;
    el.textContent=String(value||'');
    if(field)el.dataset.field=field;
    return el;
  };
  function layoutInspector(block) {
    const group=create('div','layout-inspector');group.append(create('h3','','Diseño y formato'));
    const selectControl=(label,options,value,onChange)=>{const el=create('select');options.forEach(([v,t])=>el.append(new Option(t,v)));el.value=String(value);el.addEventListener('change',()=>{onChange(el.value);changed();renderInspector();});const wrap=create('label','',label);wrap.append(el);group.append(wrap);};
    const rowOptions=[['','Ancho completo']];const seen=new Set();
    blocks.forEach(b=>{if(b.row&&!seen.has(b.row)){seen.add(b.row);rowOptions.push([b.row,`Fila ${seen.size} · ${b.columns||2} columnas`]);}});
    selectControl('Fila',rowOptions,block.row||'',value=>{block.row=value;if(value){const row=blocks.find(b=>b!==block&&b.row===value);block.columns=row?.columns||2;block.column=1;}});
    const actions=create('div','actions');
    for(const columns of [2,3,4])actions.append(button(`Nueva fila ${columns} columnas`,'Crear fila flexible',()=>{block.row='r-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,7);block.columns=columns;block.column=1;changed();renderInspector();}));
    group.append(actions);
    if(block.row){
      selectControl('Columna',[1,2,3,4].slice(0,block.columns||2).map(n=>[n,String(n)]),block.column||1,value=>block.column=Number(value));
      selectControl('Columnas de la fila',[1,2,3,4].map(n=>[n,String(n)]),block.columns||2,value=>{blocks.filter(b=>b.row===block.row).forEach(b=>{b.columns=Number(value);b.column=Math.min(b.column||1,b.columns);});});
    }
    for(const key of ['bold','italic','underline']){const label=create('label','checkline',{bold:'Negrita',italic:'Cursiva',underline:'Subrayado'}[key]);const input=create('input');input.type='checkbox';input.checked=!!block[key];input.addEventListener('change',()=>{block[key]=input.checked;changed();});label.prepend(input);group.append(label);}
    selectControl('Fuente',Object.keys(fontMap).map(key=>[key,key]),block.font||'system',value=>block.font=value);
    for(const [key,label] of [['background','Fondo del bloque'],['text_color','Color del texto']]){const wrap=create('label','',label);const input=create('input');input.type='color';input.value=block[key]||'#ffffff';input.addEventListener('input',()=>{block[key]=input.value;changed();});wrap.append(input,button('Restablecer',label,()=>{block[key]='';changed();renderInspector();}));group.append(wrap);}
    const spacing=create('input');spacing.type='range';spacing.min='0';spacing.max='120';spacing.value=String(block.spacing||0);spacing.addEventListener('input',()=>{block.spacing=Number(spacing.value);changed();});const label=create('label','','Espaciado adicional');label.append(spacing);group.append(label);
    selectControl('Alineación del bloque',[['left','Izquierda'],['center','Centro'],['right','Derecha']],block.block_align||'left',value=>block.block_align=value);
    if(block.row)selectControl('Proporciones de columnas',[['1 1 1','Iguales'],['2 1 1','Primera doble'],['1 2 1','Segunda doble'],['1 1 2','Tercera doble']],block.widths||'1 1 1',value=>blocks.filter(b=>b.row===block.row).forEach(b=>b.widths=value));
    for(const [key,title,max,fallback] of [['gap','Separación entre columnas',80,24],['border_width','Grosor del borde',12,0],['radius','Radio de esquinas',80,0]]){const label=create('label','',title);const input=create('input');input.type='range';input.min='0';input.max=String(max);input.value=String(block[key]??fallback);input.addEventListener('input',()=>{block[key]=Number(input.value);if(key==='gap'&&block.row)blocks.filter(b=>b.row===block.row).forEach(b=>b.gap=block.gap);changed();});label.append(input);group.append(label);}
    const borderLabel=create('label','','Color del borde'),borderColor=create('input');borderColor.type='color';borderColor.value=block.border_color||'#dce2eb';borderColor.addEventListener('input',()=>{block.border_color=borderColor.value;changed();});borderLabel.append(borderColor);group.append(borderLabel);
    for(const [key,title,min,max,fallback] of [['font_size','Tamaño exacto de texto (px)',0,160,0],['line_height','Interlineado',1,3,1.6],['letter_spacing','Espaciado de letras (px)',-2,12,0],['max_width','Ancho máximo (px)',80,2400,2400],['min_height','Altura mínima (px)',0,1600,0]]){const label=create('label','',title),input=create('input');input.type='number';input.min=String(min);input.max=String(max);input.step=key==='line_height'?'0.1':'1';input.value=String(block[key]??fallback);input.addEventListener('input',()=>{block[key]=Math.max(min,Math.min(max,Number(input.value)));changed();});label.append(input);group.append(label);}
    selectControl('Abrir enlace',[['_self','Misma pestaña'],['_blank','Nueva pestaña']],block.link_target||'_self',v=>block.link_target=v);
    if(block.type==='image'){selectControl('Proporción de imagen',[['auto','Original'],['1/1','Cuadrada'],['4/3','4:3'],['16/9','16:9'],['3/4','Vertical']],block.image_ratio||'auto',v=>block.image_ratio=v);selectControl('Posición de recorte',[['center','Centro'],['top','Arriba'],['bottom','Abajo'],['left','Izquierda'],['right','Derecha']],block.image_position||'center',v=>block.image_position=v);}
    const backgroundLabel=create('label','','Imagen de fondo del bloque'),backgroundInput=create('input');backgroundInput.value=block.background_image||'';backgroundInput.placeholder='HTTPS o imagen de la biblioteca';backgroundInput.oninput=()=>{block.background_image=backgroundInput.value;changed();};backgroundLabel.append(backgroundInput);const backgroundPicker=create('select');backgroundPicker.append(new Option('Elegir imagen subida',''));media.forEach(m=>backgroundPicker.append(new Option(m.name,m.path)));backgroundPicker.onchange=()=>{backgroundInput.value=backgroundPicker.value;block.background_image=backgroundPicker.value;changed();};backgroundLabel.append(backgroundPicker);group.append(backgroundLabel);
    inspector.append(group);
  }
  function applyBlockLayout(doc,fragment){
    const output=doc.createDocumentFragment(),rows=new Map();
    Array.from(fragment.children).forEach(node=>{
      const idx=Number(node.dataset.blockIndex),block=blocks[idx];if(!block)return;
      node.classList.toggle('lcy-flow',!!block.canvas);node.classList.toggle('lcy-full',!!block.full_width);if(block.canvas){const span=Math.min(Number(block.grid_span)||12,13-(Number(block.grid_start)||1));node.style.width=(span/12*100)+'%';node.style.maxWidth=(block.max_width||2400)+'px';node.style.marginLeft=(((Number(block.grid_start)||1)-1)/12*100)+'%';node.style.marginRight='0';node.style.marginTop=(block.space_before||0)+'px';node.style.boxSizing='border-box';}if(block.font_size){node.style.fontSize=block.font_size+'px';node.style.setProperty('--lcy-font-size',block.font_size+'px');}node.style.minHeight=block.min_height?block.min_height+'px':'';node.style.lineHeight=String(block.line_height||1.6);node.style.letterSpacing=(block.letter_spacing||0)+'px';node.style.fontWeight=block.bold?'700':'';node.style.fontStyle=block.italic?'italic':'';node.style.textDecoration=block.underline?'underline':'';node.style.fontFamily=fontMap[block.font||'system'];
      if(block.text_color)node.style.color=block.text_color;if(block.background)node.style.background=block.background;
      if(block.background_image){const background=safeImage(block.background_image);node.style.backgroundImage=background?'url("'+background+'")':'';node.style.backgroundSize='cover';node.style.backgroundPosition='center';}if(block.spacing)node.style.paddingBlock=block.spacing+'px';node.style.textAlign=block.block_align||'left';node.style.border=(Number(block.border_width)||0)+'px solid '+(block.border_color||'#dce2eb');node.style.borderRadius=(Number(block.radius)||0)+'px';
      if(!block.row){output.append(node);return;}
      let row=rows.get(block.row);
      if(!row){row=doc.createElement('div');const count=Math.max(1,Math.min(4,Number(block.columns)||2));row.className='content-row columns-'+count;row.dataset.row=block.row;row.style.setProperty('--row-tracks',String(block.widths||'1 1 1').split(' ').slice(0,count).map(n=>'minmax(0,'+Number(n)+'fr)').join(' '));row.style.gap=(block.gap??24)+'px';
        for(let col=1;col<=count;col++){const cell=doc.createElement('div');cell.className='content-cell';cell.dataset.column=String(col);
          const drop=doc.createElement('div');drop.className='column-drop';drop.textContent=`Columna ${col} · arrastra un bloque aquí`;cell.append(drop);
          cell.addEventListener('dragover',event=>{event.preventDefault();cell.classList.add('drop-active');});cell.addEventListener('dragleave',()=>cell.classList.remove('drop-active'));
          cell.addEventListener('drop',event=>{event.preventDefault();event.stopPropagation();const moved=blocks[dragging];dragging=-1;if(!moved)return;moved.row=block.row;moved.column=col;moved.columns=count;changed();renderInspector();});row.append(cell);
        }rows.set(block.row,row);output.append(row);
      }
      row.children[Math.max(0,Math.min(row.children.length-1,(Number(block.column)||1)-1))].append(node);
    });return output;
  }
  function renderMenuControls(){
    const root=byId('menu-controls'),output=byId('menu-json');if(!root||!output)return;
    let entries;try{entries=JSON.parse(output.value);}catch{entries=[];}
    const save=()=>{output.value=JSON.stringify(entries);changed();};root.replaceChildren();
    entries.forEach((entry,index)=>{
      const card=create('div','menu-entry'),label=create('input');label.placeholder='Texto del enlace';label.value=entry.label||'';label.addEventListener('input',()=>{entry.label=label.value;save();});card.append(label);
      const kind=create('select');kind.append(new Option('Página de este sitio','page'),new Option('URL externa','link'));kind.value=entry.kind;kind.addEventListener('change',()=>{entry.kind=kind.value;save();renderMenuControls();});card.append(kind);
      const target=create(entry.kind==='page'?'select':'input');if(entry.kind==='page'){pages.forEach(p=>target.append(new Option(p.name,p.slug)));target.value=entry.page||'index';target.addEventListener('change',()=>{entry.page=target.value;save();});}else{target.value=entry.url||'';target.placeholder='https://... / mailto: / tel:';target.addEventListener('input',()=>{entry.url=target.value;save();});}card.append(target);
      card.append(button('↑','Subir enlace',()=>{if(index){[entries[index-1],entries[index]]=[entries[index],entries[index-1]];save();renderMenuControls();}}),button('↓','Bajar enlace',()=>{if(index+1<entries.length){[entries[index+1],entries[index]]=[entries[index],entries[index+1]];save();renderMenuControls();}}),button('Eliminar','Eliminar enlace',()=>{if(confirm('¿Eliminar enlace?')){entries.splice(index,1);save();renderMenuControls();}}));root.append(card);
    });
    root.append(button('Añadir enlace','Añadir enlace al menú',()=>{if(entries.length>=40)return;entries.push({kind:'page',page:'index',label:'',url:'#'});save();renderMenuControls();}));
  }
  function globalExtras(doc){
    const val=id=>byId(id)?.value||'';const header=doc.querySelector('.site-head:not([data-template-part])'),footer=doc.querySelector('.site-foot:not([data-template-part])');
    if(header){header.style.fontFamily=fontMap[val('header_font')||'system'];header.style.fontSize=(Number(val('header_font_size'))||16)+'px';header.style.minHeight=(Number(val('header_height'))||80)+'px';header.style.paddingBlock=(Number(val('layout_spacing'))||0)+'px';header.style.textAlign=val('header_align');const image=safeImage(val('header_image'));header.style.backgroundImage=image?`url("${image}")`:'';header.style.backgroundSize='cover';
      const inside=header.querySelector('.wrap');if(inside){inside.style.justifyContent={left:'space-between',center:'center',right:'flex-end'}[val('header_align')]||'space-between';}
      let menu;try{menu=JSON.parse(byId('menu-json')?.value||'[]');}catch{menu=[];}
      const nav=header.querySelector('nav')||doc.createElement('nav');nav.className='site-nav';nav.setAttribute('aria-label','Navegación principal');nav.replaceChildren();
      (menu.length?menu:pages.map(p=>({kind:'page',page:p.slug,label:p.name}))).forEach(item=>{const a=doc.createElement('a');if(item.kind==='page'){const page=pageBySlug(item.page);if(!page)return;a.dataset.sitePage=page.slug;a.href=`?action=preview&id=${siteId}&page=${page.id}`;a.textContent=item.label||page.name;}else{a.href=safeLink(item.url);a.textContent=item.label||'';}nav.append(a);});if(!nav.parentElement)inside?.append(nav);
    }
    if(footer){footer.style.fontFamily=fontMap[val('footer_font')||'system'];footer.style.fontSize=(Number(val('footer_font_size'))||16)+'px';footer.style.minHeight=(Number(val('footer_min_height'))||0)+'px';const footerImage=safeImage(val('footer_image'));footer.style.backgroundImage=footerImage?'url("'+footerImage+'")':'';footer.style.backgroundSize='cover';footer.style.paddingBlock=(Number(val('footer_padding'))||0)+'px';footer.style.textAlign=val('footer_align')||'left';footer.style.setProperty('--footer-columns',String(Number(val('footer_columns'))||1));}
    for(const [node,key] of [[header,'header_visibility'],[footer,'footer_visibility']])if(node){for(const value of ['all','desktop','mobile','none'])node.classList.toggle('visibility-'+value,(val(key)||'all')===value);}
    if(header){let btn=header.querySelector('.header-button');if(!btn){btn=doc.createElement('a');btn.className='btn header-button';header.querySelector('.wrap')?.append(btn);}btn.textContent=val('header_button_text');btn.href=safeLink(val('header_button_url'));btn.hidden=!btn.textContent;btn.style.display=btn.textContent?'':'none';}
    if(footer){let links;try{links=JSON.parse(val('social_links')||'[]');}catch{links=[];}let social=footer.querySelector('.social-links');if(!social){social=doc.createElement('div');social.className='social-links';footer.append(social);}social.replaceChildren();if(Array.isArray(links))links.slice(0,40).forEach(item=>{const link=doc.createElement('a');link.href=safeLink(item.url);link.textContent=item.label||'Enlace social';social.append(link,doc.createTextNode(' '));});}
  }

  function renderPreview() {
    const doc=frame.contentDocument;
    if (!doc || !doc.querySelector('main')) return;
    const root=doc.documentElement;root.dataset.landicyTheme=byId('color_scheme')?.value||'light';
    for(const [id,key] of [['text_color','--text'],['muted_color','--muted'],['background_alt','--bg'],['border_color','--border']])if(byId(id))root.style.setProperty(key,byId(id).value);
    root.style.setProperty('--primary',byId('theme-primary').value);
    root.style.setProperty('--accent',byId('theme-accent').value);
    root.style.setProperty('--surface',byId('theme-background').value);
    root.style.setProperty('--font-body',fontMap[byId('font_body').value]||fontMap.system);
    root.style.setProperty('--font-heading',fontMap[byId('font_heading').value]||fontMap.system);
    root.style.setProperty('--base-size',`${Math.max(14,Math.min(20,Number(byId('base_size').value)||16))}px`);
    root.style.setProperty('--title-scale',String(Math.max(80,Math.min(125,Number(byId('title_scale').value)||100))/100));
    const main=doc.querySelector('main');
    const scroll=doc.scrollingElement?doc.scrollingElement.scrollTop:0;
    const fragment=doc.createDocumentFragment();
    const el=(tag,cls,text,field)=>fieldNode(doc,tag,cls,text,field);
    const section=(tag,cls,idx)=>{
      const node=el(tag,cls);
      node.dataset.blockIndex=String(idx);node.tabIndex=0;node.setAttribute('aria-label','Bloque '+(idx+1)+': '+(defs[blocks[idx]?.type]?.label||''));fragment.append(node);
      return node;
    };
    const wrap=node=>{const w=el('div','wrap');node.append(w);return w;};
    const addButton=(parent,b)=>{
      if(!String(b.button_text||'').trim())return;
      const anchor=el('a','btn',b.button_text,'button_text');
      if (b.button_kind==='page' && pageBySlug(b.button_page||'index')) {
        anchor.dataset.sitePage=b.button_page||'index';
        const page=pageBySlug(anchor.dataset.sitePage);
        anchor.href=`?action=preview&id=${siteId}&page=${encodeURIComponent(page.id||'')}`;
      } else anchor.href=safeLink(b.button_url);
      parent.append(anchor);
    };
    blocks.forEach((b,idx)=>{
      if(!b || !defs[b.type])return;
      if(b.type==='hero') {
        const src=safeImage(b.image_url);
        const node=section('section','hero'+(src?' has-image':''),idx);node.id='inicio';
        if(src){const img=el('img','hero-background');img.src=src;img.alt='';node.append(img);}
        const w=wrap(node);
        w.append(el('div','eyebrow',b.eyebrow,'eyebrow'),el('h1','',b.title,'title'),el('p','lead',b.subtitle,'subtitle'));
        addButton(w,b);
      } else if (b.type==='text') {
        const node=section('section',`section text-align-${['left','center','right'].includes(b.align)?b.align:'left'} text-size-${['small','normal','large','huge'].includes(b.size)?b.size:'normal'}`,idx);node.id='informacion';
        const w=wrap(node);w.append(el('h2','',b.title,'title'),el('div','prose',b.body,'body'));
      } else if(b.type==='text_box') {
        const node=section('section',`text-box text-align-${['left','center','right'].includes(b.align)?b.align:'left'} text-size-${['small','normal','large','huge'].includes(b.size)?b.size:'normal'}`,idx);
        wrap(node).append(el('div','text-box-inner',b.body,'body'));
      } else if(b.type==='features') {
        const w=wrap(section('section','section alt',idx));w.append(el('h2','',b.title,'title'));
        const grid=el('div','grid');
        (b.items||[]).slice(0,12).forEach(item=>{
          const card=el('article','feature');card.append(el('h3','',item.title),el('p','',item.text));grid.append(card);
        });w.append(grid);
      } else if (b.type==='cta') {
        const w=wrap(section('section','section cta',idx));
        w.append(el('h2','',b.title,'title'),el('div','prose',b.body,'body'));addButton(w,b);
      } else if(b.type==='image') {
        const w=wrap(section('section','section image-panel',idx));
        if(b.title) w.append(el('h2','',b.title,'title'));
        const figure=el('figure');
        if(safeImage(b.src)){
          const img=el('img');img.src=safeImage(b.src);img.alt=String(b.alt||'');img.loading='lazy';img.style.objectFit=b.fit==='contain'?'contain':'cover';
          const page=b.link_kind==='page'?pageBySlug(b.link_page):null;const href=page?'?action=preview&id='+siteId+'&page='+page.id:safeLink(b.link_url);
          if(href!=='#'){const link=el('a');link.href=href;if(page)link.dataset.sitePage=page.slug;link.append(img);figure.append(link);}else figure.append(img);
        } else figure.append(el('p','image-placeholder','▧ Añade una URL HTTPS en el panel para mostrar una imagen.'));
        if(b.caption)figure.append(el('figcaption','',b.caption,'caption'));
        w.append(figure);
      } else if(b.type==='columns') {
        const w=wrap(section('section','section',idx));w.append(el('h2','',b.title,'title'));
        const split=el('div','split-grid');split.append(el('div','prose',b.left,'left'),el('div','prose',b.right,'right'));w.append(split);
      } else if(b.type==='quote') {
        const w=wrap(section('section','section quote',idx));
        w.append(el('blockquote','',b.body,'body'),el('cite','',b.author,'author'));
      } else if(b.type==='embed') {
        const w=wrap(section('section','section',idx));
        w.append(el('h2','',b.title,'title'));
        const placeholder=el('div','embed-wrap');
        const src=embedUrl(b.url);
        if(src){const video=el('iframe');video.title=b.title||'Contenido incorporado';video.loading='lazy';video.src=src;video.setAttribute('allowfullscreen','');video.setAttribute('allow','accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture');video.setAttribute('sandbox','allow-scripts allow-same-origin allow-presentation');video.referrerPolicy='strict-origin-when-cross-origin';placeholder.append(video);}
        else placeholder.append(el('p','embed-placeholder','URL no incorporable. Usa HTTPS y YouTube, Maps o Forms.'));
        const fallback=el('p','field-hint','Si el proveedor rechaza la reproducción, abre el contenido en una pestaña.');if(src){const link=el('a','','Abrir contenido');link.href=src;link.target='_blank';link.rel='noopener noreferrer';fallback.append(link);}w.append(fallback);
        w.append(placeholder);
      } else if(b.type==='imported') {
        const node=section('section','imported-section',idx);node.append(el('p','imported-label','HTML importado · edita el código desde el proyecto'));
        const preview=el('iframe','imported-frame');preview.title='HTML importado';preview.setAttribute('sandbox','allow-same-origin');preview.src='?action=import-file&id='+siteId+'&file='+encodeURIComponent(b.path||'');node.append(preview);
      } else if(b.type==='spacer') {section('div','spacer',idx);
      } else if(b.type==='gallery') {const node=section('div','wrap gallery',idx);String(b.images||'').split('\n').slice(0,12).forEach(src=>{const safe=safeImage(src);if(safe){const image=el('img');image.src=safe;image.alt='';node.append(image);}});
       } else if(b.type==='heading'){const w=wrap(section('section','section text-align-'+(['left','center','right'].includes(b.align)?b.align:'left'),idx));w.append(el('h2','',b.title,'title'));
      } else if(b.type==='button'){const w=wrap(section('section','section',idx));addButton(w,b);
      } else if(b.type==='form'){const w=wrap(section('section','section',idx));w.append(el('h2','',b.title,'title'),el('p','','Formulario por correo: abre la aplicación del visitante. No almacena datos.'));for(const name of ['Nombre','Correo','Mensaje']){const label=el('label','',name),input=el(name==='Mensaje'?'textarea':'input');input.disabled=true;label.append(input);w.append(label);}w.append(el('p','',b.button_text||'Abrir correo'));
      } else if(b.type==='divider') {
        section('hr','section-divider',idx);
      }
    });
    const customPageSnippet=main.querySelector('.site-code-snippet');
    for(const node of fragment.querySelectorAll('[data-block-index]')){const b=blocks[Number(node.dataset.blockIndex)];for(const field of node.querySelectorAll('[data-field]'))if(b.rich?.[field.dataset.field])field.innerHTML=window.LandiCyCanvas?window.LandiCyCanvas.cleanRich(b.rich[field.dataset.field]):field.textContent;if(b.type==='heading'&&b.heading_level&&b.heading_level!==2){const h=node.querySelector('[data-field=title]');if(h){const next=doc.createElement('h'+Math.max(1,Math.min(4,b.heading_level)));next.dataset.field='title';next.innerHTML=h.innerHTML;h.replaceWith(next);}}if(b.type==='image'){const image=node.querySelector('img');if(image){image.style.aspectRatio=b.image_ratio||'auto';image.style.objectPosition=b.image_position||'center';image.style.width='100%';image.style.height='auto';}}for(const a of node.querySelectorAll('a'))if(b.link_target==='_blank'){a.target='_blank';a.rel='noopener noreferrer';}}
    main.replaceChildren(applyBlockLayout(doc,fragment));
    if(customPageSnippet) main.append(customPageSnippet);
    renderGlobalLayout(doc);
    globalExtras(doc);
    if(byId('color_scheme')?.value==='auto'&&window.matchMedia('(prefers-color-scheme: dark)').matches)for(const [key,value] of [['--surface','#10131c'],['--bg','#191f2b'],['--text','#f5f7fc'],['--muted','#bdc8dc'],['--border','#35415a'],['--header-bg','#10131c'],['--header-color','#f5f7fc'],['--footer-bg','#191f2b'],['--footer-color','#bdc8dc']])root.style.setProperty(key,value);
    paintSelection();
    if(doc.scrollingElement)doc.scrollingElement.scrollTop=scroll;
  }
  function renderGlobalLayout(doc) {
    const root=doc.documentElement;
    const val=id=>byId(id)?.value||'';
    root.style.setProperty('--header-bg',val('header_bg'));
    root.style.setProperty('--header-color',val('header_color'));
    root.style.setProperty('--footer-bg',val('footer_bg'));
    root.style.setProperty('--footer-color',val('footer_color'));
    let icon=doc.querySelector('link[rel="icon"]');
    if(!icon){icon=doc.createElement('link');icon.rel='icon';doc.head.append(icon);}
    const iconUrl=safeImage(val('favicon'));if(iconUrl)icon.href=iconUrl;else icon.removeAttribute('href');
    const head=doc.querySelector('.site-head');
    if(head&&!head.hasAttribute('data-template-part')){
      head.classList.toggle('is-sticky',val('header_style')==='sticky');
      head.dataset.globalLayout='header';
      const brand=head.querySelector('.brand');
      if(brand){
        brand.replaceChildren();
        const logo=safeImage(val('header_logo'));
        if(logo){const img=doc.createElement('img');img.className='brand-logo';img.src=logo;img.alt='';brand.append(img);}
        brand.append(doc.createTextNode(val('header_brand')||JSON.parse(document.getElementById('site-title').textContent)));
      }
    }
    const foot=doc.querySelector('footer.site-foot');
    if(foot&&!foot.hasAttribute('data-template-part')){
      foot.dataset.globalLayout='footer';
      const inside=foot.querySelector('.wrap');
      if(inside){
        inside.replaceChildren();
        const footerLogo=safeImage(val('footer_logo'));
        if(footerLogo){const img=doc.createElement('img');img.className='footer-logo';img.src=footerLogo;img.alt='';inside.append(img);}
        const text=doc.createElement('span');text.textContent=`© ${new Date().getFullYear()} ${val('footer_text')}`;
        inside.append(text);
        if(val('footer_link_text')){
          const a=doc.createElement('a');a.textContent=val('footer_link_text');a.href=safeLink(val('footer_link_url'));
          inside.append(a);
        }
      }
    }
  }

  function decorateDragHandles(doc) {
    doc.querySelectorAll('[data-landicy-grip]').forEach(node=>node.remove());
    doc.querySelectorAll('[data-block-index]').forEach(node=>{
      const index=Number(node.dataset.blockIndex);
      const grip=doc.createElement('button');
      grip.type='button';grip.className='landicy-grip';grip.textContent='⠿ Mover sección';
      grip.dataset.landicyGrip='1';grip.draggable=true;grip.title='Arrastra para cambiar de posición';
      grip.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();select(index);});
      grip.addEventListener('dragstart',e=>{dragging=index;e.dataTransfer.setData('text/plain',String(index));e.dataTransfer.effectAllowed='move';});
      if(node.dataset.landicyDragAttached==='1') return;
      node.dataset.landicyDragAttached='1';
      node.addEventListener('dragover',e=>{e.preventDefault();e.dataTransfer.dropEffect='move';node.classList.add('drop-active');});
      node.addEventListener('dragleave',()=>node.classList.remove('drop-active'));
      node.addEventListener('drop',e=>{
        e.preventDefault();e.stopPropagation();
        const from=dragging;dragging=-1;
        const to=Number(node.dataset.blockIndex);
        if(from<0||from>=blocks.length||from===to)return;
        const target=blocks[to];const [moved]=blocks.splice(from,1);moved.row=target.row||'';moved.columns=target.columns||1;moved.column=target.column||1;blocks.splice(to,0,moved);
        selected=to;renderInspector();renderList();changed();scrollSelectedIntoView();
      });
      if(node.tagName==='HR') node.before(grip); else node.prepend(grip);
    });
  }
  function paintSelection() {
    const doc=frame.contentDocument;
    if(!doc)return;
    if(!doc.getElementById('landicy-editor-css')){
      const style=doc.createElement('style');style.id='landicy-editor-css';
      style.textContent='.column-drop{padding:14px;border:2px dashed #a6c1ff;font:12px system-ui;color:#586478}.drop-active{outline:4px solid #ffbd5c!important}.block-context{position:absolute;top:4px;right:4px;display:flex;gap:5px;z-index:8;background:#fff;padding:5px;width:max-content;max-width:100%;border:1px solid #2463eb}.block-context button{cursor:pointer;padding:5px}.landicy-grip{display:block;position:relative;z-index:5;margin:0 auto 7px;padding:6px 15px;border:1px solid #a6c1ff;border-radius:0 0 9px 9px;background:#eef4ff;color:#264c9d;font:600 12px/1.2 system-ui;cursor:grab}.landicy-grip:active{cursor:grabbing}[data-block-index]{cursor:pointer;outline:2px solid transparent;outline-offset:-3px;transition:outline .08s}[data-block-index]:hover{outline:2px dashed #3d71e9;outline-offset:-3px}[data-block-index].landicy-selected{outline:3px solid #2463eb!important;outline-offset:-4px;position:relative}[data-field]{cursor:text}[data-field][contenteditable=true]{background:rgba(255,255,255,.94);color:#18253b;outline:3px solid #ffbd5c;border-radius:3px;padding:2px;white-space:pre-wrap}.image-placeholder{padding:55px 15px;border:2px dashed #b1bdd0;border-radius:12px;text-align:center;color:#5e6e81}[data-global-layout]{cursor:pointer}[data-global-layout]:hover{outline:3px dashed #3d71e9;outline-offset:-4px}';
      doc.head.append(style);
    }
    doc.querySelectorAll('[data-block-index]').forEach(node=>node.classList.toggle('landicy-selected',Number(node.dataset.blockIndex)===selected));
    doc.querySelectorAll('.block-context').forEach(el=>el.remove());
    const selectedNode=doc.querySelector('[data-block-index="'+selected+'"]');
    if(selectedNode && selectedNode.tagName!=='HR'){
      const bar=doc.createElement('div');bar.className='block-context';
      for(const [label,action] of [['↑',()=>move(-1)],['↓',()=>move(1)],['Duplicar',duplicate],['Eliminar',remove]]){const btn=doc.createElement('button');btn.type='button';btn.textContent=label;btn.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();action();});bar.append(btn);}selectedNode.prepend(bar);
    }
    decorateDragHandles(doc);canvasTools?.attach();canvasTools?.select(selectedNative());
  }
  function scrollSelectedIntoView() {
    const doc=frame.contentDocument;
    const target=doc && doc.querySelector(`[data-block-index="${selected}"]`);
    if(target)target.scrollIntoView({block:'center',behavior:'auto'});canvasTools?.paint();
  }
  function onFrameClick(event) {
    if(event.target.closest('[data-theme-toggle]')){event.preventDefault();const scheme=byId('color_scheme');scheme.value=scheme.value==='dark'?'light':'dark';scheme.dispatchEvent(new Event('change'));return;}
    const a=event.target.closest('a');
    if(a) {
      event.preventDefault();
      if(a.dataset.sitePage&&!a.closest('[data-block-index]')) {
        const to=pageBySlug(a.dataset.sitePage);
        if(to && to.href) {
          if (dirty && !confirm('Tienes cambios sin guardar. ¿Cambiar de página y descartarlos?')) return;
          dirty=false;
          window.location.assign(to.href);
        }
        return;
      }
    }
    const block=event.target.closest('[data-block-index]');
    if(block){const index=Number(block.dataset.blockIndex);if(index!==selected)select(index);}
    else if(event.target.closest('[data-global-layout]')){switchTab('layout');canvasTools?.select(event.target.closest('[data-global-layout]'));}
  }
  function onFrameDoubleClick(event) {
    const editable=event.target.closest('[data-field]');
    if(!editable)return;
    const section=editable.closest('[data-block-index]');
    if(!section)return;
    const idx=Number(section.dataset.blockIndex),key=editable.dataset.field;
    if(!blocks[idx] || !Object.hasOwn(blocks[idx],key))return;
    event.preventDefault();
    select(idx);
    editable.contentEditable='true';
    editable.focus();
    const range=editable.ownerDocument.createRange();range.selectNodeContents(editable);
    const selection=editable.ownerDocument.getSelection();selection.removeAllRanges();selection.addRange(range);
    const commit=()=>{
      const old=String(blocks[idx][key]||'');
      let text=(editable.innerText||'').replace(/\r/g,'').trim();
      text=text.slice(0,12000);
      editable.contentEditable='false';
      const rich=window.LandiCyCanvas?.cleanRich(editable.innerHTML)||'';if(old!==text||rich!==(blocks[idx].rich?.[key]||'')){blocks[idx][key]=text;if(!blocks[idx].rich||Array.isArray(blocks[idx].rich))blocks[idx].rich={};blocks[idx].rich[key]=rich;changed(key==='title');renderInspector();}
      else renderPreview();
    };
    editable.addEventListener('blur',commit,{once:true});
    editable.addEventListener('keydown',e=>{
      if(e.key==='Escape'){editable.textContent=String(blocks[idx][key]||'');editable.blur();}
      if(e.key==='Enter' && !['body','left','right','subtitle'].includes(key)){e.preventDefault();editable.blur();}
    });
  }
  function attachFrameListeners() {
    const doc=frame.contentDocument;
    if(!doc || doc.documentElement.dataset.landicyListeners)return;
    doc.documentElement.dataset.landicyListeners='1';
    doc.addEventListener('click',onFrameClick);
    doc.addEventListener('dblclick',onFrameDoubleClick);
    doc.addEventListener('keydown',event=>{if(event.target.closest('input,textarea,[contenteditable=true]'))return;if(event.altKey&&event.key==='ArrowUp'){event.preventDefault();move(-1);}if(event.altKey&&event.key==='ArrowDown'){event.preventDefault();move(1);}if(event.altKey&&event.key==='d'){event.preventDefault();duplicate();}});
    renderPreview();
  }
  for(const id of ['header_font','footer_font','header_font_size','footer_font_size','footer_min_height','footer_image','header_button_text','header_button_url','footer_padding','footer_align','header_visibility','footer_visibility','social_links','header_image','header_height','layout_spacing','header_align','footer_columns','favicon','header_brand','header_logo','header_bg','header_color','header_style','footer_text','footer_logo','footer_bg','footer_color','footer_link_text','footer_link_url']){
    byId(id)?.addEventListener('input',()=>changed());
    byId(id)?.addEventListener('change',()=>changed());
  }
  for (const logoId of ['header_image','header_logo','footer_logo','favicon']) {
  const headerLogoInput=byId(logoId);
  if(headerLogoInput && media.length){
    const picker=create('select','image-picker');
    picker.append(new Option('— Seleccionar logo subido —',''));
    media.forEach(item=>picker.append(new Option(item.name,item.path)));
    picker.addEventListener('change',()=>{if(picker.value){headerLogoInput.value=picker.value;changed();}});
    headerLogoInput.after(picker);
  }
  }
  themeIds.forEach(id=>{
    byId(id).addEventListener('input',()=>{
      byId('base-size-label').textContent=byId('base_size').value;
      byId('title-scale-label').textContent=byId('title_scale').value;
      changed();
    });
  });
  document.querySelectorAll('.palette').forEach(btn=>btn.addEventListener('click',()=>{
    byId('theme-primary').value=btn.dataset.primary;
    byId('theme-accent').value=btn.dataset.accent;
    byId('theme-background').value=btn.dataset.background;
    changed();
  }));
  for(const mode of ['desktop','tablet','mobile']){
    byId('view-'+mode).addEventListener('click',()=>{
      holder.classList.toggle('tablet',mode==='tablet');
      holder.classList.toggle('mobile',mode==='mobile');
      for(const other of ['desktop','tablet','mobile']){
        const el=byId('view-'+other);el.classList.toggle('active',other===mode);
        el.setAttribute('aria-pressed',String(other===mode));
      }
    });
  }
  frame.addEventListener('load',attachFrameListeners);
  if(frame.contentDocument && frame.contentDocument.readyState==='complete')attachFrameListeners();
  form.addEventListener('submit',event=>{
    if(!Array.isArray(blocks) || blocks.length>MAX){event.preventDefault();return;}
    output.value=JSON.stringify(blocks);
    submitted=true;
  });
  window.addEventListener('beforeunload',event=>{
    if(dirty && !submitted){event.preventDefault();event.returnValue='';}
  });
  document.addEventListener('keydown',event=>{if(event.target.closest('input,textarea,[contenteditable=true]'))return;if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='z'){event.preventDefault();event.shiftKey?restore(redoStack,undoStack):restore(undoStack,redoStack);}});
  byId('color_scheme')?.addEventListener('change',()=>{const dark=byId('color_scheme').value==='dark';{for(const [id,light,night] of [['theme-background','#ffffff','#10131c'],['text_color','#162239','#f5f7fc'],['muted_color','#586478','#bdc8dc'],['background_alt','#f6f8fc','#191f2b'],['border_color','#e6eaf2','#35415a'],['header_bg','#ffffff','#10131c'],['header_color','#162239','#f5f7fc'],['footer_bg','#f6f8fc','#191f2b'],['footer_color','#586478','#bdc8dc']])if(byId(id))byId(id).value=dark?night:light;}changed();});
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change',()=>{if(byId('color_scheme')?.value==='auto')renderPreview();});
  for(const id of ['header_mode','footer_mode'])byId(id)?.addEventListener('change',()=>changed());
  renderInsert();renderList();renderInspector();renderMenuControls();lastSnapshot=snapshot();

  const recoveryKey='landicy-draft:'+siteId+':'+(new URL(location.href).searchParams.get('page')||'index')+':'+(form.querySelector('[name=csrf]')?.value||'').slice(-16);
  function persistRecovery(){try{sessionStorage.setItem(recoveryKey,JSON.stringify({version:form.querySelector('[name=version]')?.value,time:Date.now(),snapshot:snapshot()}));}catch{stateLabel.textContent='● Sin guardar (recuperación local no disponible)';}}
  const recoveryBar=create('div','notice');
  try{const saved=JSON.parse(sessionStorage.getItem(recoveryKey)||'null');if(saved&&Date.now()-saved.time<86400000&&saved.snapshot!==snapshot()){
    recoveryBar.append(create('p','','Hay un borrador de esta pestaña sin confirmar. Si la versión del servidor cambió, revisa el contenido antes de guardar.'),button('Recuperar borrador local','Recuperar contenido sin publicar',()=>{const version=form.querySelector('[name=version]');const conflict=String(saved.version)!==version.value;if(conflict){document.querySelector('[data-canvas-pref=autosave]').checked=false;version.value=String(saved.version);}const data=JSON.parse(saved.snapshot);if(!Array.isArray(data.blocks)||data.blocks.length>MAX)return;undoStack.push(snapshot());blocks=data.blocks;data.fields.forEach(([id,value])=>{const el=byId(id);if(el)el.value=value;});selected=blocks.length?0:-1;lastSnapshot=snapshot();markDirty();renderList();renderInspector();renderPreview();renderMenuControls();if(conflict)stateLabel.textContent='Copia recuperada en conflicto: conserva tu texto y recarga antes de guardar.';recoveryBar.remove();}),button('Descartar copia local','Conservar versión del servidor',()=>{sessionStorage.removeItem(recoveryKey);recoveryBar.remove();}));form.prepend(recoveryBar);
  }}catch{}

  if(window.location.hash === '#layout') switchTab('layout');

  window.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){e.preventDefault();document.getElementById("editor-form").requestSubmit();}});
  const canvasIndex=node=>{const found=node?.closest('[data-block-index]')||node?.querySelector('[data-block-index]')||node?.closest('.content-row')?.querySelector('[data-block-index]');return found?Number(found.dataset.blockIndex):selected;};
  const selectedNative=()=>frame.contentDocument?.querySelector('[data-block-index="'+selected+'"]');
  const nativeGroup=node=>{const row=node?.classList.contains('content-row')?node:null;return row?blocks.filter(b=>b.row===row.dataset.row):[blocks[canvasIndex(node)]].filter(Boolean);};
  const nativeText=node=>node?.querySelector('[data-field]')||null;
  function canvasProperty(node,key,value){if(node?.matches('.site-head,.site-foot')){const prefix=node.matches('.site-head')?'header_':'footer_';const id=prefix+({font:'font',font_size:'font_size',text_color:'color',align:'align'}[key]||'font_size');const input=byId(id);if(input){input.value=String(value);changed();}return;}const group=nativeGroup(node);for(const b of group){if(key==='align')b.block_align=value;else b[key]=value;}changed();renderInspector();}
  function canvasPattern(kind){const presets={one:[1],two:[1,1],asymmetric:[2,1],three:[1,1,1],four:[1,1,1,1],image:[1,1],cards:[1,1,1],gallery:[1,1,1],full:[1]};const tracks=presets[kind];if(!tracks)return;if(blocks.length+tracks.length>MAX){alert('Máximo 40 bloques.');return;}const row=tracks.length>1?'r-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,6):'';const items=tracks.map((v,i)=>{const type=kind==='image'&&i===0?'image':kind==='cards'?'features':kind==='gallery'?'image':'text_box';const b=defaultBlock(type);Object.assign(b,{row,columns:tracks.length,column:i+1,widths:[...tracks,...Array(4-tracks.length).fill(1)].join(' '),canvas:true,grid_span:12,grid_start:1,full_width:kind==='full'});if(type==='image'){b.src=media[i]?.path||media[0]?.path||'';if(kind==='gallery')b.image_ratio='1/1';}if(kind==='cards')b.items=[{title:'Tarjeta '+(i+1),text:'Contenido de la tarjeta.'}];if(type==='gallery')b.images=media.slice(0,6).map(m=>m.path).join('\n');if(kind==='full'){b.background='#eef3ff';b.spacing=48;}return b;});const at=selected>=0?selected+1:blocks.length;blocks.splice(at,0,...items);selected=at;changed();renderList();renderInspector();scrollSelectedIntoView();}
  function canvasDrop(node,target,placement){const idx=canvasIndex(node);if(!blocks[idx])return;const section=node.classList.contains('content-row');if(section){const row=node.dataset.row,group=blocks.filter(b=>b.row===row),rest=blocks.filter(b=>b.row!==row);const targetIndex=canvasIndex(target),targetBlock=blocks[targetIndex];let at=rest.indexOf(targetBlock);if(at<0)at=rest.length;else if(placement.after)at++;blocks=[...rest.slice(0,at),...group,...rest.slice(at)];selected=blocks.indexOf(group[0]);}
    else{const moved=blocks[idx],cell=target?.closest('.content-cell'),row=cell?.closest('.content-row');const to=canvasIndex(target);if(row){moved.row=row.dataset.row;moved.columns=row.children.length;moved.column=Number(cell.dataset.column);moved.widths=blocks.find(b=>b!==moved&&b.row===moved.row)?.widths||'1 1 1 1';}else if(target&&target!==node&&blocks[to]){moved.row=blocks[to].row||'';moved.columns=blocks[to].columns||1;moved.column=blocks[to].column||1;}if(target&&target!==node&&to>=0&&to<blocks.length&&to!==idx){blocks.splice(idx,1);let at=clampIndex(to+(placement.after?1:0)-(idx<to?1:0));blocks.splice(at,0,moved);selected=at;}else{moved.canvas=true;moved.grid_start=placement.start||1;moved.space_before=placement.space||0;selected=idx;}}
    changed();renderList();renderInspector();}
  const clampIndex=n=>Math.max(0,Math.min(blocks.length,n));
  const canvasAdapter={canDrag:n=>!n.matches('.site-head,.site-foot,[data-template-part]'),selector:'[data-block-index]',get:selectedNative,pick:target=>target?.closest('[data-block-index],.content-cell,.content-row')||null,select:node=>{const i=canvasIndex(node);if(i>=0)select(i);},scope:node=>node?.closest('.content-cell')||node?.parentElement||frame.contentDocument?.querySelector('main'),section:node=>node?.closest('.content-row')||node?.closest('[data-block-index]')||node,columnRow:node=>node?.closest('.content-row'),text:nativeText,
    resize(node,size){if(node.matches('.site-head,.site-foot')){const input=byId(node.matches('.site-head')?'header_height':'footer_min_height');if(input){input.value=String(Math.max(40,Math.min(480,Math.round(size.height))));changed();}return;}for(const b of nativeGroup(node)){b.canvas=true;b.grid_span=size.span;b.grid_start=size.start;b.max_width=2400;if(/[ns]/.test(size.direction)&&b.type!=='image')b.min_height=Math.min(1600,size.height);}changed();renderInspector();},
    drop:canvasDrop,columns(row,widths){blocks.filter(b=>b.row===row.dataset.row).forEach(b=>b.widths=widths.join(' '));changed();renderInspector();},
    nudge(node,key){selected=canvasIndex(node);if(key==='ArrowUp'||key==='ArrowDown')move(key==='ArrowUp'?-1:1);else{const b=blocks[selected];if(b){b.canvas=true;b.grid_start=Math.max(1,Math.min(13-(b.grid_span||12),(b.grid_start||1)+(key==='ArrowLeft'?-1:1)));changed();}}},
    duplicate(node){selected=canvasIndex(node);if(node?.classList.contains('content-row')){const group=nativeGroup(node);if(group.length+blocks.length>MAX)return;const row='r-'+Date.now().toString(36);const clones=group.map(b=>({...structuredClone(b),id:'b-'+crypto.randomUUID(),row}));blocks.push(...clones);selected=blocks.length-clones.length;changed();renderList();renderInspector();}else duplicate();},remove(node){selected=canvasIndex(node);if(node?.classList.contains('content-row')){if(!confirm('¿Eliminar esta sección completa? Puedes deshacer.'))return;blocks=blocks.filter(b=>b.row!==node.dataset.row);selected=Math.min(selected,blocks.length-1);changed();renderList();renderInspector();}else remove();},undo:()=>restore(undoStack,redoStack),redo:()=>restore(redoStack,undoStack),property:canvasProperty,
    rich(node,text,html){const b=blocks[canvasIndex(node)],field=text.dataset.field;if(!b||!field)return;if(!b.rich||Array.isArray(b.rich))b.rich={};b[field]=text.innerText.slice(0,12000);b.rich[field]=html;changed();renderInspector();},
    textStyle(node,tag){const b=blocks[canvasIndex(node)];if(!b||!['text_box','text','heading'].includes(b.type))return;const text=b.body||b.title||'';const rich=b.rich?.body||b.rich?.title;b.type=tag==='p'?'text_box':'heading';b[tag==='p'?'body':'title']=text;b.rich=rich?{[tag==='p'?'body':'title']:rich}:{};b.heading_level=Number(tag.slice(1))||2;changed();renderInspector();},
    reset(node){for(const b of nativeGroup(node)){b.canvas=false;b.grid_span=12;b.grid_start=1;b.space_before=0;b.min_height=0;b.font_size=0;}changed();renderInspector();},pattern:canvasPattern};
  if(window.LandiCyCanvas){canvasTools=new window.LandiCyCanvas.Canvas(frame,canvasAdapter);saver=new window.LandiCyCanvas.Autosave(form,{serialize:()=>{frame.contentDocument?.activeElement?.blur();output.value=JSON.stringify(blocks);},start:()=>submitted=false,status:text=>stateLabel.textContent=text,saved:unchanged=>{submitted=false;if(unchanged){dirty=false;try{sessionStorage.removeItem(recoveryKey);}catch{}}},failed:()=>submitted=false});}


  function renderSocialEditor(){const source=byId('social_links');if(!source)return;source.hidden=true;let root=byId('social-visual');if(!root){root=create('div');root.id='social-visual';source.after(root);}root.replaceChildren();let items;try{items=JSON.parse(source.value||'[]');}catch{items=[];}const save=()=>{source.value=JSON.stringify(items);changed();};items.forEach((item,index)=>{const row=create('div','menu-entry'),label=create('input'),url=create('input');label.placeholder='Nombre de la red';label.value=item.label||'';url.placeholder='https://...';url.value=item.url||'';label.oninput=()=>{item.label=label.value;save();};url.oninput=()=>{item.kind='link';item.url=url.value;save();};row.append(label,url,button('Eliminar','Eliminar red social',()=>{items.splice(index,1);save();renderSocialEditor();}));root.append(row);});root.append(button('Añadir red social','Añadir red social',()=>{if(items.length>=40)return;items.push({kind:'link',url:'https://',label:'Nueva red'});save();renderSocialEditor();}));}
  renderSocialEditor();

})();

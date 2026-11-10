// SPDX-License-Identifier: AGPL-3.0-or-later
(() => {
  'use strict';
  const model=window.LANDICY_SNAPSHOT,virtual='https://snapshot.invalid/';
  const resolve=input=>{try{let value=String(input);if(value.startsWith('null/'))value=value.slice(4);const url=new URL(value,virtual+model.path);if(url.host!=='snapshot.invalid'&&url.origin!==location.origin)return null;return decodeURIComponent(url.pathname.slice(1));}catch{return null;}};
  const memory=new Map();const storage={getItem:k=>memory.get(String(k))??null,setItem:(k,v)=>memory.set(String(k),String(v)),removeItem:k=>memory.delete(String(k)),clear:()=>memory.clear(),key:i=>[...memory.keys()][i]??null,get length(){return memory.size;}};
  for(const key of ['localStorage','sessionStorage'])try{Object.defineProperty(window,key,{value:storage});}catch{}
  // Component loaders receive only resources already attached to this snapshot.
  window.fetch=async input=>{const path=resolve(input instanceof Request?input.url:input);if(path&&Object.hasOwn(model.resources,path))return new Response(model.resources[path],{status:200,headers:{'Content-Type':'text/html; charset=utf-8'}});throw new TypeError('Solo recursos de la copia privada.');};
  try{history.pushState=()=>{};history.replaceState=()=>{};}catch{}
  const rewrite=node=>{if(node.nodeType!==1)return;for(const el of [node,...node.querySelectorAll('img,source,video')])for(const attr of ['src','poster']){const raw=el.getAttribute(attr);if(!raw||raw.startsWith('data:'))continue;const path=resolve(raw);if(path&&Object.hasOwn(model.assets,path)){el.setAttribute(attr,model.assets[path]);el.dataset.lcyPreviewAsset=path;}}};
  new MutationObserver(records=>{for(const r of records){if(r.type==='attributes')rewrite(r.target);for(const n of r.addedNodes)rewrite(n);}}).observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['src','poster']});
  rewrite(document.documentElement);
  document.addEventListener('click',e=>{const a=e.target.closest('a');if(a){e.preventDefault();const hash=new URL(a.href,location.href).hash;if(hash)document.getElementById(decodeURIComponent(hash.slice(1)))?.scrollIntoView();else{const url=new URL(a.getAttribute('href')||'',location.href);const target=url.searchParams.get('file')||resolve(a.getAttribute('href'));if(target&&model.files.includes(target)&&/\.html?$/i.test(target))parent.postMessage({type:'landicy-preview-page',path:target},'*');}}},true);
  document.addEventListener('submit',e=>e.preventDefault(),true);
  delete window.LANDICY_SNAPSHOT;
})();

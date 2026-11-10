// SPDX-License-Identifier: AGPL-3.0-or-later
// Small first-party runtime for exported sites. Original imported scripts never run.
(() => {
  'use strict';
  document.querySelectorAll('[data-year]').forEach(node=>node.textContent=String(new Date().getFullYear()));
  document.querySelectorAll('[data-theme-toggle],#theme-toggle').forEach(button=>{
    if(!button.textContent.trim())button.textContent='◐';
    button.setAttribute('aria-label','Alternar modo claro y oscuro');
    button.addEventListener('click',()=>{const dark=document.body.dataset.theme==='dark'||document.documentElement.dataset.landicyTheme==='dark'||(document.documentElement.dataset.landicyTheme==='auto'&&window.matchMedia('(prefers-color-scheme: dark)').matches);const mode=dark?'light':'dark';document.body.dataset.theme=mode;document.documentElement.dataset.landicyTheme=mode;document.querySelectorAll('[data-lcy-light-src][data-lcy-dark-src]').forEach(image=>image.src=mode==='dark'?image.dataset.lcyDarkSrc:image.dataset.lcyLightSrc);button.setAttribute('aria-pressed',String(!dark));});
  });
  const toggle=document.querySelector('#nav-toggle,[data-nav-toggle]'),menu=document.querySelector('#nav-menu,.nav-menu,[data-nav-menu]');
  if(toggle&&menu){toggle.setAttribute('aria-expanded','false');if(!toggle.textContent.trim())toggle.textContent='☰';toggle.addEventListener('click',()=>{const active=menu.classList.toggle('active');toggle.setAttribute('aria-expanded',String(active));});const close=()=>{menu.classList.remove('active');toggle.setAttribute('aria-expanded','false');};menu.addEventListener('click',event=>{if(event.target.closest('a'))close();});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu.classList.contains('active')){close();toggle.focus();}});document.addEventListener('click',event=>{if(!menu.contains(event.target)&&!toggle.contains(event.target))close();});}
})();

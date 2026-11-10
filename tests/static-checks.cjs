const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const controller=read('public_html/landicy-admin/index.php'),builder=read('landicy-private/src/Builder.php'),editor=read('public_html/landicy-admin/assets/editor.js');
const checks=[
 ['Favicon configurado',()=>assert.match(controller,/name="favicon" id="favicon"/)],
 ['Favicon en generador',()=>assert.match(builder,/rel="icon" href=/)],
 ['Favicon en modelo',()=>assert.match(builder,/'favicon' => self::imageUrl/)],
 ['Publicación con rol explícito',()=>assert.match(controller,/!\$post \|\| \$user\['role'\] !== 'superadmin' \|\| !can_publish/)],
 ['Sin raíz implícita de escaneo',()=>assert.doesNotMatch(controller,/scan_root.*\?: dirname/)],
 ['Historial de editor',()=>assert.match(editor,/undoStack.push\(lastSnapshot\)/)],
 ['Historial privado de código',()=>assert.match(read('landicy-private/src/DraftCode.php'),/history\/.*siteId/)],
 ['Política central en servidor',()=>assert.match(controller,/Access::allows/)]
];for(const [name,test]of checks){test();console.log('PASS '+name);}console.log(checks.length+' verificaciones estáticas; no equivalen a integración PHP/MySQL.');

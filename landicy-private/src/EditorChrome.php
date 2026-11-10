<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
/** Shared navigation vocabulary for native and imported editors. */
final class EditorChrome {
    public static function imported(array $site,array $manifest,string $path,array $versions):void {
        $id=(int)$site['id'];
        echo '<form method="post" id="imported-form" class="imported-editor visual-editor unified-editor">'.\csrf_field().'<input type="hidden" name="file_versions" value="'.\e(json_encode($versions,JSON_THROW_ON_ERROR)).'"><input type="hidden" name="contents" id="imported-html">';
        echo '<div class="editor-toolbar"><div class="editor-identity"><a class="editor-back" href="?action=site&id='.$id.'" aria-label="Volver al proyecto">←</a><div><strong>'.\e($site['name']).'</strong><small>'.\e($path).'</small></div></div><div class="editor-toolbar-mid"><span class="editor-tip">Clic selecciona · doble clic escribe · arrastra para mover</span><span id="editor-state" role="status" aria-live="polite">'.\e(Views::editorStatus()).'</span></div><button type="button" id="imported-undo" aria-label="Deshacer">↶</button><button type="button" id="imported-redo" aria-label="Rehacer">↷</button><button type="button" id="imported-preview-open">Vista previa con JS</button><button class="primary editor-save">Guardar borrador</button></div>';
        echo CanvasUI::controls();
        echo '<div class="visual-workspace imported-workspace"><div class="visual-stage"><div class="stage-top"><div class="stage-info"><strong>Lienzo del sitio</strong><small id="imported-selection">Selecciona contenido para personalizarlo</small></div><div class="actions" role="group" aria-label="Tamaño de vista previa">';
        foreach(['desktop'=>'Escritorio','tablet'=>'Tableta','mobile'=>'Móvil'] as $key=>$label)echo '<button type="button" id="imported-'.$key.'" class="view-mode'.($key==='desktop'?' active':'').'" aria-pressed="'.($key==='desktop'?'true':'false').'">'.$label.'</button>';
        echo '</div></div><div class="imported-frame-holder" data-view="desktop"><iframe id="imported-canvas" title="Lienzo editable de la página" referrerpolicy="no-referrer" sandbox="allow-same-origin" src="?action=import-file&amp;id='.$id.'&amp;file='.rawurlencode($path).'"></iframe></div><div id="imported-actions" class="editor-help">Arrastra para reordenar; suelta en una columna para colocar contenido dentro. La vista previa con JS muestra el último borrador guardado.</div></div><aside class="visual-controls" aria-label="Herramientas de edición">'.self::tabs().'<div class="editor-sidebar-scroll">';
        echo '<section class="tab-pane active" data-pane="insert"><h2>Insertar contenido</h2><p class="pane-intro">Selecciona dónde insertar y añade contenido.</p><div class="insert-grid">';
        foreach(['text'=>'+ Texto','heading'=>'+ Título','image'=>'+ Imagen','button'=>'+ Botón','columns'=>'+ Columnas','divider'=>'+ Separador'] as $key=>$label)echo '<button type="button" data-add-imported="'.$key.'">'.$label.'</button>';
        echo '</div><h3>Estructura de la página</h3><div id="imported-outline" class="section-list"></div><p><a class="button" href="#imported-upload">Subir imágenes</a></p></section><section class="tab-pane" data-pane="edit"><h2>Editar elemento</h2><div id="imported-inspector"><p>Haz clic en texto, imagen o sección del lienzo. Doble clic para escribir.</p></div></section>';
        echo '<section class="tab-pane" data-pane="pages"><h2>Páginas del sitio</h2><label>Página<select id="imported-page">';
        foreach($manifest['pages'] as $entry)echo '<option value="?action=import-visual&amp;id='.$id.'&amp;file='.rawurlencode($entry['path']).'"'.($entry['path']===$path?' selected':'').'>'.\e($entry['title']).'</option>';
        echo '</select></label><div class="editor-pages">';
        foreach(\query('SELECT id,title,slug FROM pages WHERE site_id=? ORDER BY (slug = ?) DESC,id',[$id,'index'])->fetchAll() as $page)echo '<a class="editor-page-link" href="?action=edit&id='.$id.'&page='.(int)$page['id'].'"><span>▤</span><strong>'.\e($page['title']).'</strong></a>';
        echo '</div><p><a class="button" href="?action=new-page&id='.$id.'">+ Crear página</a></p></section>';
        echo '<section class="tab-pane" data-pane="themes"><h2>Apariencia</h2><p class="pane-intro">Conserva el diseño importado o cambia su apariencia. Selecciona elementos para ajustar colores.</p><label>Apariencia<select id="imported-mode" aria-label="Apariencia"><option value="original">Original</option><option value="light">Claro</option><option value="dark">Oscuro</option></select></label><div class="theme-presets"><button type="button" data-copy-palette="light">Claro</button><button type="button" data-copy-palette="dark">Oscuro</button></div><label>Fuente del contenido<select id="imported-font"><option value="">Original</option><option value="system-ui">Sistema</option><option value="Arial">Arial</option><option value="Georgia">Georgia</option></select></label></section>';
        echo '<section class="tab-pane" data-pane="layout"><h2>Cabecera y pie</h2><p class="pane-intro">Edita partes compartidas en el lienzo. Los cambios solo afectan a esta copia.</p><button type="button" data-select-part="header">Editar cabecera visualmente</button><button type="button" data-select-part="footer">Editar pie visualmente</button>';
        foreach(['components/header.html'=>'Código cabecera','components/footer.html'=>'Código pie',$path=>'Código de esta página'] as $part=>$label)if(in_array($part,$manifest['files'],true))echo '<p><a class="button" href="?action=import-code&id='.$id.'&file='.rawurlencode($part).'">'.$label.'</a></p>';
        echo '<h3>JavaScript de la copia</h3><p>Se ejecuta en la vista previa aislada. El lienzo de edición permanece estable.</p>';
        foreach(ScriptPreview::scripts($manifest,$path) as $js)echo '<a class="script-source" href="?action=import-code&id='.$id.'&file='.rawurlencode($js).'">'.\e($js).'</a><br>';
        echo '</section></div></aside></div></form><dialog id="imported-preview-dialog"><div class="preview-dialog-toolbar"><strong>Vista previa interactiva · borrador guardado</strong><button type="button" id="imported-preview-close">Volver a editar</button></div><p id="preview-dirty-note" hidden>Hay cambios sin guardar en el editor.</p><iframe id="imported-preview-frame" title="Vista previa interactiva aislada" sandbox="allow-scripts" referrerpolicy="no-referrer" data-src="?action=import-live&id='.$id.'&amp;file='.rawurlencode($path).'"></iframe></dialog>';
    }
    public static function tabs(string $active='insert'):string {
        $html='<div class="editor-tabs" role="tablist" aria-label="Herramientas">';
        foreach(['insert'=>'Insertar','edit'=>'Editar','pages'=>'Páginas','themes'=>'Temas','layout'=>'Cabecera y pie'] as $key=>$label)$html.='<button type="button" role="tab" class="editor-tab'.($key===$active?' active':'').'" data-tab="'.$key.'" aria-selected="'.($key===$active?'true':'false').'">'.$label.'</button>';
        return $html.'</div>';
    }
    public static function iconCss():string {
        // Small local fallbacks; no external icon fonts or third-party assets.
        $icons=['moon'=>'◐','sun'=>'☀','bars'=>'☰','xmark'=>'×','arrow-right'=>'→','arrow-up-right-from-square'=>'↗','users'=>'♟','lightbulb'=>'☼','eye'=>'◉','compass-drafting'=>'◇','bullseye'=>'◎','heart'=>'♡','award'=>'☆','folder-tree'=>'▤','check'=>'✓','arrow-trend-up'=>'↗','seedling'=>'♧','briefcase'=>'▣','bullhorn'=>'◁','envelope'=>'✉','location-dot'=>'⌖','phone'=>'☎','linkedin'=>'in','instagram'=>'◎','github'=>'⌘','link'=>'↗'];
        $css='[class*="fa-"]:not(:has(svg))::before{font-family:system-ui!important;font-style:normal;display:inline-block;min-width:1em;text-align:center}';foreach($icons as $key=>$glyph)$css.='.fa-'.$key.'::before{content:"'.$glyph.'"}';return $css;
    }
}

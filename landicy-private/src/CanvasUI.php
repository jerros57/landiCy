<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace LandiCy;
final class CanvasUI {
    public static function controls():string {
        $html='<div class="canvas-controls" role="group" aria-label="Herramientas del lienzo"><label><input type="checkbox" data-canvas-pref="grid" checked> Guías al mover</label><label><input type="checkbox" data-canvas-pref="snap" checked> Ajustar</label><label>Paso<input type="range" data-canvas-pref="step" min="8" max="80" value="24"><output data-grid-step-label>24 px</output></label><label>Zoom<select data-canvas-pref="zoom"><option value="0.5">50%</option><option value="0.75">75%</option><option value="1" selected>100%</option><option value="1.25">125%</option></select></label><button type="button" data-canvas-section>Seleccionar sección</button><button type="button" data-canvas-reset>Restablecer tamaño</button><label><input type="checkbox" data-canvas-pref="autosave" checked> Autoguardado</label><details class="canvas-patterns"><summary>＋ Diseños</summary><div class="canvas-pattern-grid">';
        foreach(['one'=>['Una columna',1],'two'=>['Dos columnas',2],'asymmetric'=>['Dos asimétricas',2],'three'=>['Tres columnas',3],'four'=>['Cuatro columnas',4],'image'=>['Imagen y texto',2],'cards'=>['Tarjetas',3],'gallery'=>['Galería',3],'full'=>['Ancho completo',1]] as $kind=>[$label,$columns]){$html.='<button type="button" data-canvas-pattern="'.$kind.'"><span class="pattern-mini" style="grid-template-columns:'.($kind==='asymmetric'?'2fr 1fr':'repeat('.$columns.',1fr)').'">';for($i=0;$i<$columns;$i++)$html.='<i></i>';$html.='</span>'.$label.'</button>';}
        return $html.'</div></details><span class="canvas-hint">Guías durante el movimiento · arrastra desde ⠿ · cambia tamaño desde las esquinas</span></div>';
    }
    public static function requested():bool {return ($_POST['autosave']??'')==='1';}
    public static function reply(array $data,int $status=200):never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;}
    public static function codeHash(string $storage,int $site):string {return hash('sha256',json_encode(DraftCode::read($storage,$site),JSON_THROW_ON_ERROR));}
}

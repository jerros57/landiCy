<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2026 LandiCy contributors. See LICENSE and LICENSE-OUTPUT-EXCEPTION.md.

declare(strict_types=1);
namespace LandiCy;

use InvalidArgumentException;
use RuntimeException;

final class Builder {
    public const TYPES = ['hero', 'text', 'text_box', 'features', 'cta', 'image', 'columns', 'quote', 'divider', 'embed', 'imported', 'spacer', 'gallery', 'heading', 'button', 'form', 'footer'];

    public static function defaultBlocks(string $siteName): array {
        return [
            ['type' => 'hero', 'eyebrow' => 'Bienvenido', 'title' => $siteName,
             'subtitle' => 'Un sitio creado con LandiCy, software libre para la web.',
             'button_text' => 'Descubrir', 'button_url' => '#informacion'],
            ['type' => 'text', 'title' => 'Información',
             'body' => 'Edita este texto desde el panel de LandiCy y publícalo cuando estés listo.'],
            ['type' => 'features', 'title' => 'Lo que ofrecemos', 'items' => [
                ['title' => 'Ligero', 'text' => 'Solo HTML y CSS para la parte pública.'],
                ['title' => 'Libre', 'text' => 'Tú controlas tus datos y tus archivos.'],
                ['title' => 'Sencillo', 'text' => 'Edición por bloques predefinidos.'],
            ]],
        ];
    }

    public static function normalize(array $blocks): array {
        if (count($blocks) > 40) throw new InvalidArgumentException('Máximo 40 bloques.');
        $normalized = []; $ids=[];
        $fields = [
            'hero' => ['eyebrow', 'title', 'subtitle', 'button_text', 'button_url', 'button_kind', 'button_page', 'image_url'],
            'text' => ['title', 'body', 'align', 'size'],
            'text_box' => ['body', 'align', 'size'],
            'features' => ['title'],
            'cta' => ['title', 'body', 'button_text', 'button_url', 'button_kind', 'button_page'],
            'image' => ['title', 'src', 'alt', 'caption', 'link_kind', 'link_page', 'link_url', 'fit'],
            'columns' => ['title', 'left', 'right'],
            'quote' => ['body', 'author'],
            'divider' => [],
            'embed' => ['url', 'title', 'embed_mode'],
            'heading'=>['title','align','size'],'button'=>['button_text','button_url','button_kind','button_page'],'form'=>['title','recipient','button_text'],
            'footer' => ['copyright'],
            'imported'=>['path'], 'spacer'=>[], 'gallery'=>['images'],
        ];
        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['type']) || !in_array($block['type'], self::TYPES, true)) {
                throw new InvalidArgumentException('Tipo de bloque no permitido.');
            }
            $type = $block['type'];
            $id=(string)($block['id']??'');if(!preg_match('/^[a-zA-Z0-9_-]{8,64}$/D',$id))$id='b-'.bin2hex(random_bytes(12));
            if(isset($ids[$id]))throw new InvalidArgumentException('Identificador de bloque duplicado.');$ids[$id]=true;
            $clean = ['type' => $type,'id'=>$id];
            foreach ($fields[$type] as $field) {
                $value = $block[$field] ?? '';
                if (!is_string($value)) throw new InvalidArgumentException('Campo de bloque inválido: ' . $field);
                if (strlen($value) > 12000) throw new InvalidArgumentException('Texto demasiado largo.');
                if (in_array($field, ['src', 'image_url'], true)) $value = self::imageUrl($value);
                if ($field === 'embed_mode' && !in_array($value,['url','html'],true)) $value='url';
                if ($field === 'align' && !in_array($value, ['left','center','right'], true)) $value = 'left';
                if ($field === 'size' && !in_array($value, ['small','normal','large','huge'], true)) $value = 'normal';
                if ($field === 'recipient' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Correo de formulario inválido.');
                if ($field === 'fit' && !in_array($value,['cover','contain'],true)) $value='cover';
                if ($field === 'link_kind' && !in_array($value,['link','page'],true)) $value='link';
                if ($field === 'link_page' && !preg_match('/^[a-z0-9-]{1,100}$/D',$value)) $value='index';
                if ($field === 'link_url') $value=self::url($value);
                if ($field === 'button_kind' && !in_array($value, ['link','page'], true)) $value = 'link';
                if ($field === 'button_page' && !preg_match('/^(index|[a-z0-9-]{1,99})$/D', $value)) $value = 'index';
                if ($field === 'url') {
                    if ($value !== '' && self::embedUrl($value) === '') throw new InvalidArgumentException('URL no incorporable. YouTube /feed/yot no es un vídeo: usa un enlace o la URL de un vídeo. Maps y Forms requieren su URL de inserción HTTPS.');
                    $value = self::embedUrl($value);
                }
                $clean[$field] = trim($value);
            }
            if ($type === 'features') {
                $items = $block['items'] ?? [];
                if (!is_array($items) || count($items) > 12) throw new InvalidArgumentException('Máximo 12 características.');
                $clean['items'] = [];
                foreach ($items as $item) {
                    if (!is_array($item)) throw new InvalidArgumentException('Característica inválida.');
                    $title = (string) ($item['title'] ?? '');
                    $text = (string) ($item['text'] ?? '');
                    if (strlen($title) > 300 || strlen($text) > 1200) throw new InvalidArgumentException('Característica demasiado larga.');
                    $clean['items'][] = ['title' => $title, 'text' => $text];
                }
            }
            if ($type==='imported' && !Importer::safePath($clean['path'])) throw new InvalidArgumentException('Chemin importé invalide.');
            $normalized[] = array_merge($clean,Layout::normalize($block));
        }
        return $normalized;
    }

    /** URL de imagen HTTPS estricta: no se permiten esquemas ejecutables, SVG de datos ni rutas locales arbitrarias. */
    public static function imageUrl(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~^img/[a-f0-9]{24}\.(?:jpg|png|webp|gif)$~D', $url)) return $url;
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url)) return '';
        $parts = parse_url($url);
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
        return $url;
    }

    public static function url(string $url): string {
        $url = trim($url);
        if ($url === '') return '#';
        if (strlen($url)>2048 || preg_match('/[\x00-\x20\x7f]/',$url)) return '#';
        if (preg_match('~^https?://~i',$url)) { $p=parse_url($url); return $p && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) ? $url : '#'; }
        if (preg_match('~^(mailto:[^<>]+@[^<>]+|tel:[+0-9()-]+)$~iD',$url)) return $url;
        if (str_starts_with($url, '#') && preg_match('/^#[a-zA-Z0-9_-]*$/', $url)) return $url;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\') && !preg_match('/[\x00-\x1F]/', $url)) return $url;
        return '#';
    }

    /** Parse only; never fetch or execute pasted provider HTML. */
    public static function embedUrl(string $url): string {
        if(strlen($url)>12000)return '';
        $url=trim(html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if(str_starts_with($url,'<')){
            $doc=new \DOMDocument();$prior=libxml_use_internal_errors(true);
            try{$doc->loadHTML($url,LIBXML_NONET);$frames=$doc->getElementsByTagName('iframe');$url=$frames->length===1?$frames->item(0)->getAttribute('src'):'';}
            finally{libxml_clear_errors();libxml_use_internal_errors($prior);}
        }
        if($url===''||strlen($url)>2048||preg_match('/[\x00-\x20\x7f]/',$url))return '';
        $p=parse_url($url);if(!$p||strtolower($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port']))return '';
        $host=strtolower($p['host']??'');$path=$p['path']??'';parse_str($p['query']??'',$q);$video='';
        if($host==='youtu.be'&&preg_match('~^/([A-Za-z0-9_-]{11})/?$~D',$path,$m))$video=$m[1];
        if(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'],true)){
            if($path==='/watch'&&is_string($q['v']??null)&&preg_match('/^[A-Za-z0-9_-]{11}$/D',$q['v']))$video=$q['v'];
            if(preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$~D',$path,$m))$video=$m[1];
        }
        if($video!==''){
            $params=[];
            foreach(['controls','rel','autoplay','mute','loop'] as $key)if(isset($q[$key])&&is_string($q[$key])&&in_array($q[$key],['0','1'],true))$params[$key]=$q[$key];
            foreach(['start','end'] as $key)if(isset($q[$key])&&is_string($q[$key])&&preg_match('/^\d{1,6}$/D',$q[$key]))$params[$key]=(string)(int)$q[$key];
            if(!isset($params['start'])&&isset($q['t'])&&is_string($q['t'])&&preg_match('/^(\d{1,6})s?$/D',$q['t'],$m))$params['start']=(string)(int)$m[1];
            return 'https://www.youtube-nocookie.com/embed/'.$video.($params?'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986):'');
        }
        if($host==='www.google.com'&&preg_match('~^/maps/embed(?:/|$)~D',$path))return $url;
        if($host==='docs.google.com'&&preg_match('~^/forms/d/e/[A-Za-z0-9_-]+/viewform$~D',$path))return $url;
        return '';
    }

    /** Lista cerrada de tipografías del sistema (sin peticiones a terceros). */
    public static function fonts(): array {
        return [
            'system' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'arial' => 'Arial, Helvetica, sans-serif',
            'verdana' => 'Verdana, Geneva, sans-serif',
            'trebuchet' => '"Trebuchet MS", Arial, sans-serif',
            'georgia' => 'Georgia, "Times New Roman", serif',
            'times' => '"Times New Roman", Times, serif',
            'mono' => 'ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace',
        ];
    }

    /** Valida la apariencia: nunca se admite CSS introducido por usuarios. */
    /** Configuración global de cabecera y pie, común a todas las páginas. */
    public static function menu(mixed $items): array {
        if(is_string($items)){try{$items=json_decode($items,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new InvalidArgumentException('Menú inválido.');}}
        if(!is_array($items)||count($items)>40)throw new InvalidArgumentException('Menú máximo 40 enlaces.');
        $clean=[];foreach($items as $item){if(!is_array($item))throw new InvalidArgumentException('Enlace inválido.');$kind=($item['kind']??'page')==='page'?'page':'link';$page=(string)($item['page']??'index');if(!preg_match('/^[a-z0-9-]{1,100}$/D',$page))$page='index';$clean[]=['kind'=>$kind,'page'=>$page,'url'=>self::url((string)($item['url']??'')),'label'=>mb_substr((string)($item['label']??''),0,100)];}return $clean;
    }

    public static function normalizeLayout(array $submitted, string $siteName = ''): array {
        $stored = is_array($submitted['layout'] ?? null) ? $submitted['layout'] : [];
        $get = static fn(string $name, string $fallback = ''): string => trim((string)($submitted[$name] ?? $stored[$name] ?? $fallback));
        $clip = static function(string $value, int $limit): string {
            if (function_exists('mb_substr')) return mb_substr($value, 0, $limit, 'UTF-8');
            $chars = preg_split('//u', $value, $limit + 1, PREG_SPLIT_NO_EMPTY);
            return $chars === false ? '' : implode('', array_slice($chars, 0, $limit));
        };
        $brand = $clip($get('header_brand', $siteName) ?: $siteName, 120);
        $footerText = $clip($get('footer_text', $siteName) ?: $siteName, 400);
        $footerLink = $clip($get('footer_link_text'), 100);
        $style = $get('header_style', 'normal');
        if (!in_array($style, ['normal','sticky'], true)) $style = 'normal';
        return [
            'header_brand' => $brand,
            'header_mode'=>$get('header_mode')==='template'?'template':'visual','footer_mode'=>$get('footer_mode')==='template'?'template':'visual',
            'header_button_text'=>$clip($get('header_button_text'),100),'header_button_url'=>self::url($get('header_button_url')),
            'social_links'=>self::menu($submitted['social_links']??$stored['social_links']??[]),
            'header_visibility'=>in_array($get('header_visibility'),['all','desktop','mobile','none'],true)?$get('header_visibility'):'all',
            'footer_visibility'=>in_array($get('footer_visibility'),['all','desktop','mobile','none'],true)?$get('footer_visibility'):'all',
            'footer_align'=>in_array($get('footer_align'),['left','center','right'],true)?$get('footer_align'):'left',
            'header_font'=>array_key_exists($get('header_font','system'),self::fonts())?$get('header_font','system'):'system','footer_font'=>array_key_exists($get('footer_font','system'),self::fonts())?$get('footer_font','system'):'system',
            'footer_min_height'=>max(0,min(480,(int)$get('footer_min_height','0'))),'footer_image'=>self::imageUrl($get('footer_image')),'header_font_size'=>max(12,min(80,(int)$get('header_font_size','16'))),'footer_font_size'=>max(12,min(80,(int)$get('footer_font_size','16'))),
            'footer_padding'=>max(0,min(120,(int)$get('footer_padding','35'))),
            'header_logo' => self::imageUrl($get('header_logo')),
            'favicon' => self::imageUrl($get('favicon')),
            'header_image'=>self::imageUrl($get('header_image')),
            'header_height'=>max(40,min(480,(int)$get('header_height','80'))),
            'layout_spacing'=>max(0,min(80,(int)$get('layout_spacing','18'))),
            'header_align'=>in_array($get('header_align'),['left','center','right'],true)?$get('header_align'):'left',
            'footer_columns'=>max(1,min(4,(int)$get('footer_columns','1'))),
            'menu'=>self::menu($submitted['menu']??$stored['menu']??[]),
            'header_bg' => self::color($get('header_bg', '#ffffff')),
            'header_color' => self::color($get('header_color', '#162239')),
            'header_style' => $style,
            'footer_text' => $footerText,
            'footer_logo' => self::imageUrl($get('footer_logo')),
            'footer_bg' => self::color($get('footer_bg', '#f6f8fc')),
            'footer_color' => self::color($get('footer_color', '#586478')),
            'footer_link_text' => $footerLink,
            'footer_link_url' => self::url($get('footer_link_url')),
        ];
    }

    /** Valida la apariencia: nunca se admite CSS introducido por usuarios. */
    public static function normalizeTheme(array $submitted): array {
        $fonts = self::fonts();
        $body = (string) ($submitted['font_body'] ?? 'system');
        $heading = (string) ($submitted['font_heading'] ?? 'system');
        if (!isset($fonts[$body])) $body = 'system';
        if (!isset($fonts[$heading])) $heading = 'system';
        $base = filter_var($submitted['base_size'] ?? 16, FILTER_VALIDATE_INT);
        $titles = filter_var($submitted['title_scale'] ?? 100, FILTER_VALIDATE_INT);
        return [
            'primary' => self::color((string) ($submitted['primary'] ?? '#225cf0')),
            'accent' => self::color((string) ($submitted['accent'] ?? '#111827')),
            'background' => self::color((string) ($submitted['background'] ?? '#ffffff')),
            'color_scheme'=>in_array($submitted['color_scheme']??'light',['light','dark','auto'],true)?($submitted['color_scheme']??'light'):'light',
            'text_color'=>self::color((string)($submitted['text_color']??'#162239')),'muted_color'=>self::color((string)($submitted['muted_color']??'#586478')),
            'background_alt'=>self::color((string)($submitted['background_alt']??'#f6f8fc')),'border_color'=>self::color((string)($submitted['border_color']??'#e6eaf2')),
            'font_body' => $body,
            'font_heading' => $heading,
            'base_size' => $base !== false ? max(14, min(20, $base)) : 16,
            'title_scale' => $titles !== false ? max(80, min(125, $titles)) : 100,
            'layout' => self::normalizeLayout($submitted),
            'pages' => Pages::metadata($submitted['pages']??[]),
        ];
    }

    public static function css(array $theme): string {
        $theme = self::normalizeTheme($theme);
        $primary = $theme['primary'];
        $accent = $theme['accent'];
        $background = $theme['background'];$textColor=$theme['text_color'];$mutedColor=$theme['muted_color'];$altColor=$theme['background_alt'];$borderColor=$theme['border_color'];
        $scheme=$theme['color_scheme'];$auto=$scheme==='auto'?'@media(prefers-color-scheme:dark){:root:not([data-landicy-theme=light]){--surface:#10131c;--bg:#191f2b;--text:#f5f7fc;--muted:#bdc8dc;--border:#35415a;--header-bg:#10131c;--header-color:#f5f7fc;--footer-bg:#191f2b;--footer-color:#bdc8dc}}':'';
        $bodyFont = self::fonts()[$theme['font_body']];
        $headFont = self::fonts()[$theme['font_heading']];
        $baseSize = $theme['base_size'];
        $titleScale = number_format($theme['title_scale'] / 100, 2, '.', '');
        $layout = $theme['layout'];
        $headerBg = $layout['header_bg'];
        $headerText = $layout['header_color'];
        $footerBg = $layout['footer_bg'];
        $footerText = $layout['footer_color'];
        return <<<CSS
:root{--primary:$primary;--accent:$accent;--text:$textColor;--muted:$mutedColor;--border:$borderColor;--surface:$background;--bg:$altColor;--font-body:$bodyFont;--font-heading:$headFont;--base-size:{$baseSize}px;--title-scale:$titleScale;--header-bg:$headerBg;--header-color:$headerText;--footer-bg:$footerBg;--footer-color:$footerText}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--surface);color:var(--text);font:var(--base-size)/1.65 var(--font-body)}
h1,h2,h3{font-family:var(--font-heading)}
a{color:var(--primary)}a:focus-visible,button:focus-visible{outline:3px solid var(--primary);outline-offset:3px}
.wrap{width:min(1120px,calc(100% - 40px));margin-inline:auto}.site-head{border-bottom:1px solid var(--border);padding:18px 0;background:var(--header-bg);color:var(--header-color);z-index:10}.site-head.is-sticky{position:sticky;top:0}.site-head .brand{color:var(--header-color)}.site-head .site-nav a{color:var(--header-color);opacity:.78}.site-head .site-nav a:hover,.site-head .site-nav a[aria-current=page]{opacity:1;text-decoration:underline}.brand-logo{width:auto;height:43px;max-width:150px;object-fit:contain;vertical-align:middle;margin-right:9px}.site-head .wrap{display:flex;justify-content:space-between;align-items:center;gap:24px;flex-wrap:wrap}.site-nav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.site-nav a{color:var(--muted);text-decoration:none;font-size:.9rem;font-weight:650}.site-nav a:hover,.site-nav a[aria-current=page]{color:var(--primary)}
.brand{color:var(--accent);font-weight:800;letter-spacing:-.02em;font-size:1.1rem;text-decoration:none}.hero{padding:110px 0;background:linear-gradient(135deg,var(--bg),var(--surface))}
.hero.has-image{position:relative;isolation:isolate;color:#fff;background:#1c2637;overflow:hidden}.hero.has-image .hero-background{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2}.hero.has-image:before{content:'';position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(10,17,29,.72),rgba(10,17,29,.15))}.hero.has-image .lead,.hero.has-image .eyebrow{color:#fff}.hero h1{font-size:clamp(calc(2.3rem * var(--title-scale)),calc(6vw * var(--title-scale)),calc(4.8rem * var(--title-scale)));line-height:1.1;max-width:900px;letter-spacing:-.05em;margin:14px 0 22px}
.eyebrow{text-transform:uppercase;letter-spacing:.15em;font-size:.73rem;font-weight:800;color:var(--primary)}.lead{font-size:1.2rem;max-width:760px;color:var(--muted)}
.section{padding:76px 0}.section.alt{background:var(--bg)}h2{font-size:clamp(calc(1.8rem * var(--title-scale)),calc(3.4vw * var(--title-scale)),calc(2.7rem * var(--title-scale)));line-height:1.2;letter-spacing:-.035em;margin:0 0 25px}
.prose{max-width:770px;white-space:pre-line}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.feature{border:1px solid var(--border);border-radius:18px;padding:26px;background:var(--surface)}
.feature h3{margin:0 0 8px}.feature p{margin:0;color:var(--muted)}.btn{display:inline-block;color:#fff;background:var(--primary);padding:12px 23px;border-radius:11px;text-decoration:none;font-weight:700;margin-top:12px}
.btn:hover{filter:brightness(.88)}.cta{background:var(--accent);color:#fff}.cta .prose{opacity:.88}.cta .btn{background:#fff;color:var(--accent)}
.image-panel img{display:block;width:100%;max-height:620px;object-fit:cover;border-radius:16px}.image-panel figcaption{font-size:.9rem;color:var(--muted);margin-top:12px}.split-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:36px}.quote{background:var(--bg);text-align:center}.quote blockquote{font-family:var(--font-heading);font-size:clamp(1.4rem,3vw,2.2rem);line-height:1.4;margin:0 auto;max-width:850px}.quote cite{display:block;margin-top:18px;color:var(--muted);font-style:normal}.section-divider{border:0;border-top:1px solid var(--border);margin:0}.text-box{padding:26px 0}.text-box-inner{white-space:pre-line}.text-align-center{text-align:center}.text-align-right{text-align:right}.text-size-small{font-size:.88rem}.text-size-large{font-size:1.35rem}.text-size-huge{font-size:1.9rem;line-height:1.3}.embed-wrap{aspect-ratio:16/9;max-width:980px;margin-inline:auto;background:#f2f5fa;border-radius:12px;overflow:hidden}.embed-wrap iframe{width:100%;height:100%;border:0}.site-code-snippet{margin:18px auto}.site-code-snippet.header-snippet{margin-top:0}.site-foot{padding:35px 0;border-top:1px solid var(--border);color:var(--footer-color);background:var(--footer-bg);font-size:.93rem}.site-foot .wrap{display:flex;gap:15px;justify-content:space-between;flex-wrap:wrap}.footer-logo{width:auto;height:36px;max-width:130px;object-fit:contain;vertical-align:middle;margin-right:10px}.site-foot a{color:inherit;text-decoration:underline}.content-row{display:grid;gap:24px;max-width:1200px;margin:auto}.columns-2{grid-template-columns:var(--row-tracks,repeat(2,minmax(0,1fr)))}.columns-3{grid-template-columns:var(--row-tracks,repeat(3,minmax(0,1fr)))}.content-cell{min-width:0}.site-head .wrap{justify-content:var(--header-justify,space-between)}.site-foot .wrap{display:grid;grid-template-columns:repeat(var(--footer-columns,1),minmax(0,1fr))}.content-cell .wrap{width:calc(100% - 32px)}.imported-frame{width:100%;min-height:75vh;border:0}.imported-label{font:12px system-ui;color:#586478}.spacer{height:48px}.gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.gallery img{max-width:100%;height:220px;object-fit:cover}.visibility-none{display:none!important}.visibility-mobile{display:none!important}@media(max-width:700px){.visibility-mobile{display:block!important}.visibility-desktop{display:none!important}}.content-block{overflow-wrap:anywhere}img{max-width:100%}@media(max-width:700px){.content-row,.gallery{grid-template-columns:1fr!important}}@media(max-width:700px){.hero{padding:75px 0}.section{padding:52px 0}.grid,.split-grid{grid-template-columns:1fr}.site-head .wrap{align-items:flex-start}.site-nav{gap:10px}}
:root[data-landicy-theme="dark"]:not([data-base-theme="dark"]){--surface:#10131c;--bg:#191f2b;--text:#f5f7fc;--muted:#bdc8dc;--border:#35415a;--header-bg:#10131c;--header-color:#f5f7fc;--footer-bg:#191f2b;--footer-color:#bdc8dc}.theme-toggle{border:1px solid var(--border);background:var(--surface);color:var(--text);border-radius:50%;padding:8px 12px;cursor:pointer}.hero .lead{color:inherit}.hero.has-image .lead{color:inherit}
:root[data-landicy-theme="light"][data-base-theme="dark"]{--surface:#fff;--bg:#f6f8fc;--text:#162239;--muted:#586478;--border:#e6eaf2;--header-bg:#fff;--header-color:#162239;--footer-bg:#f6f8fc;--footer-color:#586478}
$auto
CSS;
    }

    public static function color(string $value): string {
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? $value : '#225cf0';
    }

    public static function canvasCss():string {return '.content-block{min-width:0;box-sizing:border-box;overflow-wrap:anywhere}.lcy-flow img{max-width:100%}.lcy-flow .wrap{max-width:100%;padding-inline:min(4vw,24px)}.lcy-flow .btn{max-width:100%;box-sizing:border-box}.lcy-flow h1,.lcy-flow h2,.lcy-flow h3,.lcy-flow p,.lcy-flow .prose{font-size:var(--lcy-font-size,revert);line-height:inherit}.lcy-full .wrap{max-width:none}.lcy-flow:has(>.wrap>.btn) .btn{width:100%}.content-row{min-width:0}.columns-4{grid-template-columns:var(--row-tracks,repeat(4,minmax(0,1fr)))}@media(max-width:650px){.lcy-flow{min-height:0!important;width:100%!important;max-width:100%!important;margin-left:0!important;margin-right:0!important}.content-row{grid-template-columns:1fr!important}.lcy-layout-grid>.lcy-column{grid-column:auto!important}}';}
    public static function html(array $site, array $page, array $blocks, bool $inlineCss = false, array $navigation = [], array $draftCode = []): string {
        $theme = self::normalizeTheme(json_decode($site['theme_json'], true) ?: []);
        $layout = self::normalizeLayout($theme, (string)$site['name']);
        $pageMeta=Pages::metadata($theme['pages']??[]);if(isset($pageMeta[(string)($page['id']??0)]['layout']))$layout=$pageMeta[(string)$page['id']]['layout'];
        $menuPages=Pages::listing($navigation,$theme,false,true);$navigation=Pages::listing($navigation,$theme);
        $body = ''; $parts=[];
        foreach ($blocks as $block) {
            $startLength=strlen($body);
            $type = $block['type'];
            $title = RichText::render($block,'title');
            if ($type === 'hero') {
                $eyebrow = \e($block['eyebrow'] ?? '');
                $sub = RichText::render($block,'subtitle');
                $btn = self::button($block, $navigation, $inlineCss, (int)($site['id']??0));
                $image = self::renderImageUrl((string)($block['image_url'] ?? ''), $inlineCss, (int)($site['id'] ?? 0));
                $cover = $image !== '' ? '<img class="hero-background" src="' . \e($image) . '" alt="" loading="eager">' : '';
                $class = $image !== '' ? 'hero has-image' : 'hero';
                $body .= "<section class=\"$class\" id=\"inicio\">$cover<div class=\"wrap\"><div class=\"eyebrow\">$eyebrow</div><h1>$title</h1><p class=\"lead\">$sub</p>$btn</div></section>";
            } elseif ($type === 'text') {
                $text = RichText::render($block,'body');
                $align = self::textClass($block);
                $body .= "<section class=\"section $align\" id=\"informacion\"><div class=\"wrap\"><h2>$title</h2><div class=\"prose\">$text</div></div></section>";
            } elseif ($type === 'text_box') {
                $align=self::textClass($block);
                $body.='<section class="text-box '. $align .'"><div class="wrap text-box-inner">'.RichText::render($block,'body').'</div></section>';
            } elseif ($type === 'features') {
                $cards = '';
                foreach (($block['items'] ?? []) as $item) {
                    $cards .= '<article class="feature"><h3>' . \e($item['title']) . '</h3><p>' . \e($item['text']) . '</p></article>';
                }
                $body .= "<section class=\"section alt\"><div class=\"wrap\"><h2>$title</h2><div class=\"grid\">$cards</div></div></section>";
            } elseif ($type === 'cta') {
                $txt = RichText::render($block,'body');
                $body .= "<section class=\"section cta\"><div class=\"wrap\"><h2>$title</h2><div class=\"prose\">$txt</div>" . self::button($block, $navigation, $inlineCss, (int)($site['id']??0)) . '</div></section>';
            } elseif ($type === 'image') {
                $src = self::renderImageUrl((string)($block['src'] ?? ''), $inlineCss, (int)($site['id'] ?? 0));
                $img = $src !== '' ? '<img loading="lazy" src="' . \e($src) . '" alt="' . \e($block['alt'] ?? '') . '">' : '';
                if($img!==''){
                    $img=str_replace('<img ','<img style="object-fit:'.(($block['fit']??'cover')==='contain'?'contain':'cover').';object-position:'.($block['image_position']??'center').';aspect-ratio:'.($block['image_ratio']??'auto').';width:100%;max-width:100%;height:auto" ',$img);
                    $href=self::url((string)($block['link_url']??''));$data='';
                    if(($block['link_kind']??'link')==='page')foreach($navigation as $nav)if($nav['slug']===($block['link_page']??'index')){$href=$inlineCss?'?action=preview&id='.(int)$site['id'].'&page='.(int)$nav['id']:$nav['slug'].'.html';$data=' data-site-page="'.\e($nav['slug']).'"';break;}
                    if($href!=='#')$img='<a'.$data.(($block['link_target']??'')==='_blank'?' target="_blank" rel="noopener noreferrer"':'').' href="'.\e($href).'">'.$img.'</a>';
                }
                $caption = RichText::render($block,'caption');
                $body .= '<section class="section image-panel"><div class="wrap">' . ($title!=='' ? '<h2>'.$title.'</h2>' : '') . '<figure>' . $img . ($caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '') . '</figure></div></section>';
            } elseif ($type === 'columns') {
                $body .= '<section class="section"><div class="wrap"><h2>' . $title . '</h2><div class="split-grid"><div class="prose">' . RichText::render($block,'left') . '</div><div class="prose">' . RichText::render($block,'right') . '</div></div></div></section>';
            } elseif ($type === 'quote') {
                $body .= '<section class="section quote"><div class="wrap"><blockquote>' . RichText::render($block,'body') . '</blockquote><cite>' . \e($block['author'] ?? '') . '</cite></div></section>';
            } elseif ($type === 'embed') {
                $embed=self::embedUrl($block['url']??'');
                $iframe=$embed!=='' ? '<iframe title="'.\e($block['title']?:'Contenido incorporado').'" loading="lazy" allowfullscreen allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation" src="'.\e($embed).'"></iframe>' : '';
                $body.='<section class="section"><div class="wrap"><h2>'.$title.'</h2><div class="embed-wrap">'.($iframe?:'<p>URL no incorporable. Usa YouTube, Maps o Forms con HTTPS.</p>').'</div></div></section>';
             } elseif ($type === 'heading') {$level=max(1,min(4,(int)($block['heading_level']??2)));$body.='<section class="section '.self::textClass($block).'"><div class="wrap"><h'.$level.'>'.$title.'</h'.$level.'></div></section>';
            } elseif ($type === 'button') {$body.='<div class="wrap">'.self::button($block,$navigation,$inlineCss,(int)($site['id']??0)).'</div>';
            } elseif ($type === 'form') {
                $recipient=(string)($block['recipient']??'');$disabled=$inlineCss||$recipient===''?' disabled':'';
                $body.='<section class="section"><div class="wrap"><h2>'.$title.'</h2><p>Este formulario abre tu aplicación de correo. No almacena ni envía datos desde LandiCy.</p><form action="mailto:'.\e($recipient).'" method="post" enctype="text/plain"><label>Nombre<input name="nombre" maxlength="120" required'.$disabled.'></label><label>Correo<input type="email" name="correo" maxlength="180" required'.$disabled.'></label><label>Mensaje<textarea name="mensaje" maxlength="4000" required'.$disabled.'></textarea></label><button'.$disabled.'>'.\e($block['button_text']?:'Abrir correo').'</button></form></div></section>';
            } elseif ($type === 'divider') {
                $body .= '<hr class="section-divider">';
            } elseif ($type === 'imported') {
                $path=(string)$block['path'];
                $src=$inlineCss?'?action=import-file&id='.(int)$site['id'].'&file='.rawurlencode($path):'imported/'.$path;
                $body.='<section class="imported-section"><p class="imported-label">HTML importado · scripts desactivados</p><iframe class="imported-frame" sandbox="" title="HTML importado" src="'.\e($src).'"></iframe></section>';
            } elseif ($type === 'spacer') {
                $body.='<div class="spacer" aria-hidden="true"></div>';
            } elseif ($type === 'gallery') {
                $body.='<div class="wrap gallery">';foreach(array_slice(explode("\n",$block['images']??''),0,12) as $image){$src=self::renderImageUrl(trim($image),$inlineCss,(int)($site['id']??0));if($src!=='')$body.='<img loading="lazy" alt="" src="'.\e($src).'">';}$body.='</div>';
            } elseif ($type === 'footer') {
                // Bloque heredado de versiones anteriores: el pie ahora es global.
            }
            $parts[]=substr($body,$startLength);
        }
        $body=Layout::render($blocks,$parts);
        if($inlineCss)$body=preg_replace_callback('~href="([a-z0-9-]+)\.html"~',static function($m)use($navigation,$site){foreach($navigation as $nav)if($nav['slug']===$m[1])return 'href="?action=preview&amp;id='.(int)$site['id'].'&amp;page='.(int)$nav['id'].'"';return $m[0];},$body)??$body;

        $snippetPage='html/'.($page['slug']??'index').'.html';
        $headerExtra=(string)($draftCode['html/header.html']??'');
        $footerExtra=(string)($draftCode['html/footer.html']??'');
        $bodyExtra=(string)($draftCode[$snippetPage]??'');
        $customCss=(string)($draftCode['css/custom.css']??'');
        $customJs=(string)($draftCode['js/custom.js']??'');
        // Los fragmentos fueron sanitizados al guardarlos, y al publicar se vuelven a validar.
        // Los fragmentos se normalizan al guardarse en DraftCode::write; no volver a escapar entidades.
        if ($inlineCss) {
            $resolve=function(string $html) use($site): string {
                return preg_replace_callback('~src="img/([a-f0-9]{24}\.(?:jpg|png|webp|gif))"~D',static function($m) use($site) {
                    return 'src="?action=media&amp;id='.(int)($site['id']??0).'&amp;file='.rawurlencode($m[1]).'"';
                }, $html)??$html;
            };
            $headerExtra=$resolve($headerExtra);$footerExtra=$resolve($footerExtra);$bodyExtra=$resolve($bodyExtra);
        }
        $css = $inlineCss ? '<style>' . self::css($theme) . "\n" . htmlspecialchars($customCss, ENT_NOQUOTES|ENT_SUBSTITUTE, 'UTF-8') . '</style>' : '<link rel="stylesheet" href="css/site.css">' . ($customCss!==''?'<link rel="stylesheet" href="css/custom.css">':'');
        $script = $inlineCss ? '' : '<script src="js/site.js" defer></script>' . ($customJs!=='' ? '<script src="js/custom.js" defer></script>':'');
        $icon = self::renderImageUrl($layout['favicon'], $inlineCss, (int)($site['id'] ?? 0));
        $favicon = $icon === '' ? '' : '<link rel="icon" href="' . \e($icon) . '">';
        $lang = 'es';
        $css.='<style>'.self::canvasCss().'</style>';
        $siteName = \e($site['name']);
        $description = \e($page['title'] ?? $site['name']);
        $navHtml = '';
        $menuNavigation=$menuPages;
        if ($layout['menu']) {
            $menuNavigation=[];
            foreach($layout['menu'] as $entry){
                if($entry['kind']==='link'){$menuNavigation[]=['external'=>$entry];continue;}
                foreach($menuPages as $nav)if($nav['slug']===$entry['page']){$nav['title']=$entry['label']?:$nav['title'];$menuNavigation[]=$nav;break;}
            }
        }
        foreach ($menuNavigation as $nav) {
            if(isset($nav['external'])){$entry=$nav['external'];$navHtml.='<a href="'.\e($entry['url']).'">'.\e($entry['label']).'</a>';continue;}
            if (!is_array($nav) || !preg_match('/^(index|[a-z0-9](?:[a-z0-9-]{0,97}[a-z0-9])?)$/D', (string)($nav['slug'] ?? ''))) continue;
            $slug = (string)$nav['slug'];
            $href = $slug === 'index' ? 'index.html' : $slug . '.html';
            if ($inlineCss && (int)($site['id']??0)>0 && isset($nav['id'])) $href='?action=preview&id='.(int)$site['id'].'&page='.(int)$nav['id'];
            $active = $slug === ($page['slug'] ?? 'index') ? ' aria-current="page"' : '';
            $navHtml .= '<a data-site-page="' . \e($slug) . '" href="' . \e($href) . '"' . $active . '>' . \e($nav['title'] ?? $slug) . '</a>';
        }
        if ($navHtml !== '') $navHtml = '<nav class="site-nav" aria-label="Navegación principal">' . $navHtml . '</nav>';
        $brandText = \e($layout['header_brand'] ?: (string)$site['name']);
        $logoSrc = self::renderImageUrl($layout['header_logo'], $inlineCss, (int)($site['id'] ?? 0));
        $brandLogo = $logoSrc === '' ? '' : '<img class="brand-logo" src="' . \e($logoSrc) . '" alt="">';
        $headerClass = $layout['header_style'] === 'sticky' ? 'site-head is-sticky' : 'site-head';
        $footerLabel = \e($layout['footer_text'] ?: (string)$site['name']);
        $footerLogoSrc = self::renderImageUrl($layout['footer_logo'], $inlineCss, (int)($site['id'] ?? 0));
        $footerLogo = $footerLogoSrc === '' ? '' : '<img class="footer-logo" src="' . \e($footerLogoSrc) . '" alt="">';
        $footerLink = $layout['footer_link_text'] === '' ? '' : '<a href="' . \e($layout['footer_link_url']) . '">' . \e($layout['footer_link_text']) . '</a>';
        $homeHref = $inlineCss && (int)($site['id']??0)>0 ? '?action=preview&id='.(int)$site['id'].'&page='.(int)(array_values(array_filter($navigation,static fn($x)=>($x['slug']??'')==='index'))[0]['id']??0) : 'index.html';
        $headerImage=self::renderImageUrl($layout['header_image'],$inlineCss,(int)($site['id']??0));
        $headerStyle='font-family:'.self::fonts()[$layout['header_font']].';font-size:'.$layout['header_font_size'].'px;min-height:'.$layout['header_height'].'px;padding-block:'.$layout['layout_spacing'].'px;text-align:'.$layout['header_align'].';--header-justify:'.(['left'=>'space-between','center'=>'center','right'=>'flex-end'][$layout['header_align']]).';';
        if($headerImage!=='')$headerStyle.='background-image:url(&quot;'.\e($headerImage).'&quot;);background-size:cover;';
        $headerStyle=\e(html_entity_decode($headerStyle,ENT_QUOTES,'UTF-8'));
        $footerImage=self::renderImageUrl($layout['footer_image'],$inlineCss,(int)($site['id']??0));
        $footerStyle='font-family:'.self::fonts()[$layout['footer_font']].';font-size:'.$layout['footer_font_size'].'px;min-height:'.$layout['footer_min_height'].'px;'.($footerImage!==''?'background-image:url("'.$footerImage.'");background-size:cover;':'').'text-align:'.$layout['footer_align'].';--footer-columns:'.$layout['footer_columns'].';padding-block:'.$layout['footer_padding'].'px;';
        $social='';foreach($layout['social_links'] as $link){$href=$link['url'];if($link['kind']==='page'){foreach($navigation as $nav)if($nav['slug']===$link['page'])$href=$inlineCss?'?action=preview&id='.(int)$site['id'].'&page='.(int)$nav['id']:$nav['slug'].'.html';}$social.='<a href="'.\e($href).'">'.\e($link['label']?:'Enlace social').'</a> ';}
        $headerButton=$layout['header_button_text']!==''?'<a class="btn header-button" href="'.\e($layout['header_button_url']).'">'.\e($layout['header_button_text']).'</a>':'';
        $headerClass.=' visibility-'.$layout['header_visibility'];$footerVisibility='visibility-'.$layout['footer_visibility'];
        $footerHtml = '<footer class="site-foot '.$footerVisibility.'" style="' . \e($footerStyle) . '"><div class="wrap"><span>' . $footerLogo . '© <span data-year>' . date('Y') . '</span> ' . $footerLabel . '</span>' . $footerLink . '<div class="social-links">'.$social.'</div></div><div class="site-code-snippet">' . $footerExtra . '</div></footer>';
        $headerHtml='<header class="'.$headerClass.'" style="'.$headerStyle.'"><div class="wrap"><a class="brand" data-site-page="index" href="'.$homeHref.'">'.$brandLogo.$brandText.'</a>'.$navHtml.$headerButton.'<button type="button" class="theme-toggle" data-theme-toggle aria-label="Alternar modo claro y oscuro">◐</button></div><div class="site-code-snippet header-snippet">'.$headerExtra.'</div></header>';
        $tokens=['brand'=>$brandText,'menu'=>$navHtml,'logo'=>$brandLogo,'copyright'=>$footerLabel,'year'=>(string)date('Y')];
        if($layout['header_mode']==='template'&&trim((string)($draftCode['html/header-template.html']??''))!=='')$headerHtml='<header class="'.$headerClass.'" data-template-part="header">'.TemplateParts::render($draftCode['html/header-template.html'],$tokens).'</header>';
        if($layout['footer_mode']==='template'&&trim((string)($draftCode['html/footer-template.html']??''))!=='')$footerHtml='<footer class="site-foot" data-template-part="footer">'.TemplateParts::render($draftCode['html/footer-template.html'],$tokens).'</footer>';
        return <<<HTML
<!doctype html>
<html lang="$lang" data-landicy-theme="{$theme['color_scheme']}" data-base-theme="{$theme['color_scheme']}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="$description"><meta name="generator" content="LandiCy">
<title>$description — $siteName</title>
$css
$favicon
</head>
<body><a class="skip" href="#contenido" style="position:absolute;left:-10000px">Ir al contenido</a>$headerHtml
<main id="contenido">$body<div class="site-code-snippet">$bodyExtra</div></main>
$footerHtml
$script
</body></html>
HTML;
    }

    /** Referencias locales img/ se sirven desde el almacén privado solo en vistas autenticadas. */
    private static function renderImageUrl(string $value, bool $inline, int $siteId): string {
        $safe = self::imageUrl($value);
        if ($inline && preg_match('~^img/([a-f0-9]{24}\.(?:jpg|png|webp|gif))$~D', $safe, $m)) {
            return $siteId > 0 ? '?action=media&id=' . $siteId . '&file=' . rawurlencode($m[1]) : '';
        }
        return $safe;
    }

    private static function textClass(array $block): string {
        $align=in_array($block['align']??'', ['left','center','right'],true)?$block['align']:'left';
        $size=in_array($block['size']??'', ['small','normal','large','huge'],true)?$block['size']:'normal';
        return 'text-align-'.$align.' text-size-'.$size;
    }

    private static function button(array $block, array $navigation=[], bool $inline=false, int $siteId=0): string {
        $text = trim($block['button_text'] ?? '');
        if ($text === '') return '';
        $href=self::url((string) ($block['button_url'] ?? '#'));
        $siteSlug='';
        if (($block['button_kind']??'link')==='page') {
            $wanted=$block['button_page']??'index';
            foreach ($navigation as $nav) if (($nav['slug']??'')===$wanted) {
                $siteSlug=$wanted;
                $href=$wanted==='index'?'index.html':$wanted.'.html';
                if ($inline && $siteId>0 && isset($nav['id'])) $href='?action=preview&id='.$siteId.'&page='.(int)$nav['id'];
                break;
            }
        }
        return '<a class="btn"'.($siteSlug!==''?' data-site-page="'.\e($siteSlug).'"':'').' href="' . \e($href) . '"'.(($block['link_target']??'')==='_blank'?' target="_blank" rel="noopener noreferrer"':'').'>' . RichText::render($block,'button_text') . '</a>';
    }

    /** Compilación estática multipágina. Las imágenes HTTPS remotas permanecen enlazadas. */
    public static function runtimeJs():string { $path=dirname(__DIR__,2).'/public_html/landicy-admin/assets/site-runtime.js';if(!is_file($path))throw new RuntimeException('Runtime de exportación ausente.');return (string)file_get_contents($path); }
    public static function publishPages(array $site, array $pages, string $storage): string {
        if (!$pages || count($pages) > 100) throw new InvalidArgumentException('Sitio sin páginas o con más de 100 páginas.');
        $slug = (string)$site['slug'];
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/D', $slug)) throw new InvalidArgumentException('Slug de sitio inválido.');
        $base = rtrim($storage, '/') . '/exports';
        if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) throw new RuntimeException('No se pudo crear exports');
        $temp = $base . '/.' . $slug . '-' . bin2hex(random_bytes(8));
        foreach ([$temp, "$temp/css", "$temp/js", "$temp/data", "$temp/assets", "$temp/img"] as $dir) {
            if (!mkdir($dir, 0700) && !is_dir($dir)) throw new RuntimeException('No se pudo crear carpeta temporal.');
        }
        try {
            $nav = array_map(static fn($p) => ['id'=>$p['id']??0,'slug'=>$p['slug'], 'title'=>$p['title']], $pages);
            $code=DraftCode::read($storage,(int)($site['id']??0));
            $data = [];
            foreach ($pages as $page) {
                $pageSlug = (string)$page['slug'];
                if (!preg_match('/^(index|[a-z0-9](?:[a-z0-9-]{0,97}[a-z0-9])?)$/D', $pageSlug)) throw new InvalidArgumentException('Slug de página inválido.');
                $blocks = self::normalize(\json_data($page['draft_json']));
                $name = $pageSlug === 'index' ? 'index.html' : $pageSlug . '.html';
                self::write("$temp/$name", count($blocks)===1&&$blocks[0]['type']==='imported'?ImportedCanvas::exportPage($storage,(int)$site['id'],$blocks[0]['path'],$nav):self::html($site, $page, $blocks, false, $nav, $code));
                $data[] = ['slug'=>$pageSlug,'title'=>$page['title'],'blocks'=>$blocks];
            }
            $theme = self::normalizeTheme(json_decode($site['theme_json'], true) ?: []);
            Importer::export($storage,(int)($site['id']??0),$temp);
            self::write("$temp/css/site.css", self::css($theme));
            self::write("$temp/css/custom.css", (string)($code['css/custom.css']??''));
            self::write("$temp/js/custom.js", (string)($code['js/custom.js']??''));
            self::write("$temp/js/site.js", self::runtimeJs());
            self::write("$temp/data/content.json", json_encode(['site'=>['slug'=>$slug,'name'=>$site['name']], 'theme'=>self::normalizeTheme($theme),'blocks'=>($data[0]['blocks'] ?? []), 'pages'=>$data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            self::write("$temp/assets/LEEME.txt", 'Las imágenes externas HTTPS se enlazan desde sus direcciones originales. Para producción, hospédalas bajo un dominio que controles.' . "\n");
            // Incluye imágenes privadas del sitio en el ZIP, con rutas relativas img/.
            $mediaPath = rtrim($storage, '/') . '/media/' . (int)($site['id'] ?? 0);
            if (is_dir($mediaPath)) {
                foreach (new \DirectoryIterator($mediaPath) as $entry) {
                    if (!$entry->isLink() && $entry->isFile() && preg_match('/^[a-f0-9]{24}\.(?:jpg|png|webp|gif)$/D', $entry->getFilename())) {
                        if (!copy($entry->getPathname(), "$temp/img/" . $entry->getFilename())) throw new RuntimeException('Error al exportar imágenes.');
                    }
                }
            }
            $target = $base . '/' . $slug;
            $backup = $base . '/.' . $slug . '-backup-' . bin2hex(random_bytes(6));
            if (is_dir($target) && !rename($target, $backup)) throw new RuntimeException('No se pudo apartar la versión anterior.');
            if (!rename($temp, $target)) {
                if (is_dir($backup)) rename($backup, $target);
                throw new RuntimeException('No se pudo publicar el sitio.');
            }
            if (is_dir($backup)) self::remove($backup);
            return $target;
        } catch (\Throwable $e) {
            if (is_dir($temp)) self::remove($temp);
            throw $e;
        }
    }

    public static function publish(array $site, array $page, string $storage): string {
        return self::publishPages($site,[$page],$storage);
    }

    private static function write(string $path, string $data): void {
        if (file_put_contents($path, $data, LOCK_EX) === false) throw new RuntimeException('Error al escribir ' . basename($path));
    }

    private static function remove(string $folder): void {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($folder);
    }
}

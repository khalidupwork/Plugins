<?php
namespace { define('ABSPATH','/tmp/'); define('ELEMENTOR_PRO_VERSION','3.25');
$GLOBALS['opts']=[]; $GLOBALS['meta']=[]; $GLOBALS['posts']=0;
function add_action(){} function add_management_page(){} function did_action(){return 1;}
function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;} function update_option($k,$v){$GLOBALS['opts'][$k]=$v;} function delete_option($k){unset($GLOBALS['opts'][$k]);}
function sanitize_text_field($v){return $v;} function wp_unslash($v){return $v;} function esc_html($v){return htmlspecialchars($v);} function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES);} function esc_url($v){return $v;}
function admin_url($p=''){return '/wp-admin/'.$p;} function wp_nonce_field(){} function submit_button($l){echo "[$l]";} function wp_json_encode($v){return json_encode($v);}
function get_the_title($id){return 'Default Kit';} function current_time(){return '2026-10-10';} function is_wp_error($x){return false;}
function wp_get_attachment_url($id){return "https://site/up/$id";} function plugins_url($p){return "https://site/plugin/$p";} function get_post($id){return true;}
function update_post_meta($id,$k,$v){$GLOBALS['meta'][$id][$k]=$v;} function home_url($p='/'){return 'https://site'.$p;}
function wp_get_nav_menu_object($m){return is_int($m)?(object)['slug'=>'myrepairco-main']:false;} function wp_create_nav_menu(){return 5;} function wp_update_nav_menu_item(){}
function wp_tempnam(){return tempnam(sys_get_temp_dir(),'x');} function media_handle_sideload($f){return ++$GLOBALS['posts']+100;}
}
namespace Elementor {
class Doc { public $id; public $saved; function __construct($id){$this->id=$id;} function get_settings(){return $GLOBALS['kit'];} function save($d){ if(isset($d['settings'])&&$this->id==1) $GLOBALS['kit']=$d['settings']; $this->saved=$d; $GLOBALS['docs'][$this->id]=$d;} function get_main_id(){return $this->id;} }
class Plugin { public static $instance; public $kits_manager,$files_manager,$documents; }
$GLOBALS['kit']=['system_colors'=>[['_id'=>'primary','color'=>'#6EC1E4']],'old'=>1];
Plugin::$instance=new Plugin; $kitdoc=new Doc(1);
Plugin::$instance->kits_manager=new class($kitdoc){function __construct(public $k){} function get_active_kit(){return $this->k;}};
Plugin::$instance->files_manager=new class{function clear_cache(){}};
Plugin::$instance->documents=new class{public $n=10; function create($t,$p){return new Doc(++$this->n);}};
}
namespace ElementorPro\Modules\ThemeBuilder { class Module { static function instance(){return new self;} function get_conditions_manager(){return new class{function save_conditions($id,$c){$GLOBALS['cond'][$id]=$c;}};} } }
namespace {
foreach(['file','media','image'] as $f){ @mkdir('/tmp/wp-admin/includes',0777,true); touch("/tmp/wp-admin/includes/$f.php"); }
require $argv[1];
$k=new MyRepairCo_Site_Kit; $r=new ReflectionClass($k);
$call=function($m,...$a)use($k,$r){$x=$r->getMethod($m);$x->setAccessible(true);return $x->invoke($k,...$a);};
ob_start(); $k->page(); $h=ob_get_clean(); echo (strpos($h,'&#10008; Global Colors')!==false?"before: missing OK\n":"before: ???\n");
echo $call('apply_styles'),"\n";
ob_start(); $k->page(); $h=ob_get_clean(); echo (substr_count($h,'&#10004;')==3?"after apply: 3 checks OK\n":"after apply FAIL\n"), (strpos($h,'onsubmit="return confirm(')!==false?"confirm OK\n":"confirm missing\n");
echo isset($GLOBALS['kit']['old'])?"merged old keys OK\n":"lost old keys\n";
echo $call('restore_styles'),"\n"; echo $GLOBALS['kit']['system_colors'][0]['color']=='#6EC1E4'?"restore OK\n":"restore FAIL\n";
echo $call('apply_styles'),"\n";
echo $call('import_templates'),"\n";
$j=json_encode($GLOBALS['docs']); echo preg_match('/\{\{(asset|menu)/',$j)?"unresolved placeholders!\n":"placeholders resolved OK\n";
echo strpos($j,'hero-technician')===false && strpos($j,'up\/')!==false ? "assets -> media urls OK\n":"asset check: ".(strpos($j,'plugin\/')!==false?'plugin url fallback used':'?')."\n";
echo json_encode($GLOBALS['cond']),"\n"; echo count($GLOBALS['opts']['mrc_site_kit_assets'])," assets uploaded\n";
}

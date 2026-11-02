<?php
// Isolated behavioral tests; no live WordPress site or database is modified.
define( 'ABSPATH', __DIR__ );
define( 'VANTSHSH_PLUGIN_URL', 'https://store.test/plugin/' );
define( 'VANTSHSH_VERSION', '1.0.3' );
class Stop_Request extends Exception {}
$options = array(); $allowed = true; $nonce_ok = true; $hooks = array(); $inline = ''; $assets = array();
function get_option($k,$d=false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k,$v) { $GLOBALS['options'][$k]=$v; }
function current_user_can($c) { return $GLOBALS['allowed']; }
function check_admin_referer($a,$n='_wpnonce') { if (!$GLOBALS['nonce_ok']) throw new Stop_Request('nonce'); return true; }
function wp_verify_nonce($n,$a) { return $GLOBALS['nonce_ok']; }
function check_ajax_referer($a,$n,$die) { return $GLOBALS['nonce_ok']; }
function wp_send_json_error($d,$status=null) { throw new Stop_Request('ajax-error'); }
function wp_send_json_success($d) { throw new Stop_Request('ajax-success'); }
function sanitize_text_field($s) { return strip_tags($s); }
function sanitize_textarea_field($s) { return strip_tags($s); }
function sanitize_hex_color($s) { return preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i',$s)?$s:null; }
function wp_unslash($s) { return stripslashes($s); }
function absint($s) { return abs((int)$s); }
function add_action($a,$c,$p=10,$args=1) { $GLOBALS['hooks'][]=$a; }
function add_filter($a,$c,$p=10,$args=1) { $GLOBALS['hooks'][]=$a; }
function add_shortcode($a,$c) {}
function __($s,$d='') { return $s; }
function esc_html__($s,$d='') { return htmlspecialchars($s); }
function esc_attr($s) { return htmlspecialchars($s); }
function esc_url($s) { return $s; }
function esc_sql($s) { return $s; }
function wc_add_notice($s,$t) {}
function wp_generate_password($l,$special=false) { return bin2hex(random_bytes($l/2)); }
function wc_get_cart_url() { return 'https://store.test/cart/'; }
function wp_hash($s) { return md5($s); }
function add_query_arg($k,$v,$url) { return $url.'?'.$k.'='.$v; }
function get_current_user_id() { return 0; }
function apply_filters($k,$v,...$args) { return $v; }
define('DAY_IN_SECONDS',86400);
function wp_nonce_field($a,$n,$r=true,$e=true) { return '<input name="'.$n.'" value="nonce" />'; }
function nocache_headers() {}
function wp_die($s,$title='',$args=array()) { $GLOBALS['confirmation']=$s; throw new Stop_Request('confirmation'); }
function wp_enqueue_style($h,$src=null,$d=array(),$v=null) { $GLOBALS['assets'][]=$h; }
function wp_enqueue_script($h,$src=null,$d=array(),$v=null,$f=false) { $GLOBALS['assets'][]=$h; }
function wp_add_inline_style($h,$s) { $GLOBALS['inline']=$s; }
function wp_localize_script($h,$v,$d) {}
function wp_create_nonce($a) { return 'nonce'; }
function admin_url($s='') { return 'https://store.test/wp-admin/'.$s; }
function get_bloginfo($s) { return 'Test'; }
class FakeSession {
    public $values=array(); public $cookie=false;
    function get($k) { return $this->values[$k]??null; }
    function set($k,$v) { $this->values[$k]=$v; }
    function set_customer_session_cookie($v) { $this->cookie=$v; }
}
class FakeCart {
    public $items=array(99); public $emptied=0; public $calculated=0; public $coupons=array();
    function empty_cart() { $this->items=array(); $this->coupons=array(); ++$this->emptied; }
    function is_empty() { return empty($this->items); }
    function add_to_cart($id,$q,$vid,$variation,$extra) { $this->items[]=$id; return 'key'; }
    function has_discount($c) { return in_array($c,$this->coupons,true); }
    function apply_coupon($c) { $this->coupons[]=$c; }
    function calculate_totals() { ++$this->calculated; }
    function get_total($context='') { return 40.00; }
}
$wc=(object)array('session'=>new FakeSession,'cart'=>new FakeCart);
function WC() { return $GLOBALS['wc']; }
class FakeDB {
    public $prefix='wp_'; public $queries=0; public $last_error=''; public $expired=false; public $table_exists=true; public $updated=array();
    function prepare($sql,...$args) { return $sql; }
    function get_var($sql) { return $this->table_exists?'wp_vantshsh_shared_carts':null; }
    function get_row($sql) { return (object)array('id'=>1,'share_count'=>1,'expires_at'=>$this->expired?'2000-01-01 00:00:00':null,'cart_data'=>serialize(array('cart'=>array(array('product_id'=>42,'quantity'=>2,'variation_id'=>0)),'applied_coupons'=>array('SAVE')))); }
    function update($table,$data,...$args) { ++$this->queries; $this->updated=$data; }
    function query($sql) { ++$this->queries; }
}
$wpdb=new FakeDB;
$root=$argv[1]??dirname(__DIR__);
require $root.'/includes/class-vantshsh-settings.php';
require $root.'/includes/class-vantshsh-frontend.php';
require $root.'/includes/class-vantshsh-cart-manager.php';
require $root.'/includes/class-vantshsh-ajax.php';
$wp_core = getenv('WP_TEST_CORE_DIR');
if ( ! $wp_core || ! is_file( $wp_core . '/wp-includes/kses.php' ) ) {
    throw new RuntimeException( 'Set WP_TEST_CORE_DIR to a WordPress core directory containing wp-includes/kses.php.' );
}
spl_autoload_register( function( $class ) use ( $wp_core ) {
    if ( 0 === strpos( $class, 'WP_HTML_' ) ) {
        $file = $wp_core.'/wp-includes/html-api/class-'.str_replace('_','-',strtolower($class)).'.php';
        if ( is_file($file) ) require_once $file;
    }
} );
require $wp_core.'/wp-includes/kses.php';
$count=0;
function expect($ok,$label) { if(!$ok) throw new Exception('FAIL: '.$label); ++$GLOBALS['count']; echo 'PASS: '.$label.PHP_EOL; }
// These checks reproduce the pre-fix arbitrary-hook bug against the original copy.
$options=array('vantshsh_enable_shop_page'=>'yes','vantshsh_enable_single_product'=>'yes','vantshsh_button_position'=>'wp_head','vantshsh_shop_position'=>'init','vantshsh_product_position'=>'shutdown');
VANTSHSH_Frontend::init();
expect(!in_array('init',$hooks,true)&&!in_array('shutdown',$hooks,true)&&!in_array('wp_head',$hooks,true),'saved hooks cannot attach to arbitrary actions');
foreach (VANTSHSH_Settings::placement_options() as $k=>$values) {
    foreach($values as $v) expect(VANTSHSH_Settings::validate_placement($k,$v)===$v,'allowed hook '.$v);
    expect(VANTSHSH_Settings::validate_placement($k,array('init'))===$values[0],'array hook rejected');
}
$options=array(); $_POST=array('vantshsh_button_position'=>'init'); $allowed=false;
VANTSHSH_Settings::save_settings(); expect(!$options,'unauthorized settings do not write');
$allowed=true; $nonce_ok=false;
try { VANTSHSH_Settings::save_settings(); } catch(Stop_Request $e) {}
expect(!$options,'invalid settings nonce does not write');
$nonce_ok=true; $_POST=array('vantshsh_button_position'=>'init','vantshsh_layout_style'=>'<script>x</script>','vantshsh_btn_bg_color'=>'red;}body{}','vantshsh_btn_border_radius'=>'500','vantshsh_custom_css'=>'body{display:none}','vantshsh_whatsapp_message'=>array('bad'));
VANTSHSH_Settings::save_settings();
expect($options['vantshsh_button_position']==='woocommerce_before_cart_table','invalid posted hook gets safe default');
expect($options['vantshsh_layout_style']==='modal','invalid layout gets safe default');
expect($options['vantshsh_btn_bg_color']==='#2271b1','CSS injection rejected');
expect($options['vantshsh_btn_border_radius']==='50','radius bounded');
expect(!isset($options['vantshsh_custom_css']),'custom CSS cannot be saved');
expect(!isset($options['vantshsh_whatsapp_message']),'malformed message input rejected');
ob_start(); VANTSHSH_Frontend::enqueue_assets(); $html=ob_get_clean();
expect($html===''&&strpos($inline,'--vantshsh-radius:50px')!==false,'styles use enqueue API with no direct output');
$options=array(); $_POST=array(); $_REQUEST=$_GET=array('share_cart'=>str_repeat('a',32));
VANTSHSH_Cart_Manager::apply_shared_cart_session();
expect($wc->cart->items===array(99)&&$wpdb->queries===0,'opening a shared GET link does not mutate cart');
try { VANTSHSH_Cart_Manager::render_restore_confirmation(); } catch(Stop_Request $e) {}
expect(strpos($GLOBALS['confirmation'],'vantshsh_restore_nonce')!==false,'shared link renders nonce-protected confirmation');
$GLOBALS['confirmation']='';
$_GET=array('share_cart'=>addslashes('<b>'.str_repeat('a',32).'</b>'));
try { VANTSHSH_Cart_Manager::render_restore_confirmation(); } catch(Stop_Request $e) {}
expect(strpos($GLOBALS['confirmation'],'vantshsh_restore_hash')!==false,'shared-link input is unslashed and sanitized');
expect(strpos($GLOBALS['confirmation'],'<form method="post"')!==false&&strpos($GLOBALS['confirmation'],'name="vantshsh_restore_nonce"')!==false,'confirmation escaping preserves form and nonce');
$GLOBALS['confirmation']=''; $_GET=array('share'=>str_repeat('b',32));
try { VANTSHSH_Cart_Manager::render_restore_confirmation(); } catch(Stop_Request $e) {}
expect(strpos($GLOBALS['confirmation'],str_repeat('b',32))!==false,'legacy shared-link parameter supported');
$GLOBALS['confirmation']=''; $_GET=array('share_cart'=>array('bad'));
VANTSHSH_Cart_Manager::render_restore_confirmation();
expect($GLOBALS['confirmation']==='','array-valued shared link rejected');
$_POST=array('vantshsh_restore_hash'=>str_repeat('a',32),'vantshsh_restore_nonce'=>'invalid'); $nonce_ok=false;
VANTSHSH_Cart_Manager::apply_shared_cart_session();
expect($wc->cart->items===array(99)&&$wpdb->queries===0,'invalid restore nonce blocks changes');
$nonce_ok=true; $wpdb->expired=true;
VANTSHSH_Cart_Manager::apply_shared_cart_session(); expect($wc->cart->items===array(99),'expired link preserves current cart');
$wpdb->expired=false;
VANTSHSH_Cart_Manager::apply_shared_cart_session(); expect($wc->cart->items===array(42)&&$wc->cart->calculated===1,'confirmed replace recalculates products');
$options['vantshsh_restore_mode']='append'; $wc->cart=new FakeCart;
VANTSHSH_Cart_Manager::apply_shared_cart_session(); expect($wc->cart->items===array(99,42)&&$wc->cart->emptied===0,'confirmed append retains current cart');
$wc->session=new FakeSession;
$token=VANTSHSH_Cart_Manager::guest_nonce_user(0,'vantshsh_restore_cart_a');
expect($token===VANTSHSH_Cart_Manager::guest_nonce_user(0,'vantshsh_share_cart_nonce')&&$wc->session->cookie,'guest nonce identity persists in session');
$wc->session=new FakeSession;
expect($token!==VANTSHSH_Cart_Manager::guest_nonce_user(0,'vantshsh_restore_cart_a'),'different guests have different nonce identities');
expect(VANTSHSH_Cart_Manager::guest_nonce_user(0,'other_plugin')===0,'other plugins nonces unchanged');
$writes=$wpdb->queries;
$preview=VANTSHSH_Cart_Manager::generate_share_url(false);
expect(strpos($preview,'share_cart=')!==false&&$wpdb->queries===$writes,'rendering preview URL does not save data');
$url=VANTSHSH_Cart_Manager::generate_share_url();
expect($url===$preview&&$wpdb->queries===$writes+1,'persisted URL matches preview');
expect(!empty($wpdb->updated['expires_at']),'regenerated link receives configured expiry');
$wpdb->table_exists=false;
expect(VANTSHSH_Cart_Manager::generate_share_url()==='','missing database table does not return unusable URL');
$wpdb->table_exists=true;
$nonce_ok=false;
try { VANTSHSH_Ajax::generate_share_link(); expect(false,'AJAX rejected'); } catch(Stop_Request $e) { expect($e->getMessage()==='ajax-error','AJAX checks nonce'); }
echo "All $count assertions passed.\n";

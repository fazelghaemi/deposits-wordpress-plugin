<?php
/**
 * Plugin Name: RS Installments (WooCommerce)
 * Plugin URI:  https://readystudio.ir/
 * Description: نمایش باکس اقساط در صفحه محصول + پنل تنظیمات + درج خودکار زیر یک المان مشخص. شورتکد: [wc_installment_prices]
 * Version:     1.0.0
 * Author:      Ready Studio
 * Author URI:  https://readystudio.ir/
 * Text Domain: rs-installments
 * Domain Path: /languages
 */

if ( ! defined('ABSPATH') ) exit;

define('RSIP_VERSION', '1.0.0');
define('RSIP_PATH', plugin_dir_path(__FILE__));
define('RSIP_URL',  plugin_dir_url(__FILE__));

// Autoload (very simple)
spl_autoload_register(function($class){
    if (strpos($class, 'RSIP_') === 0) {
        $file = RSIP_PATH . 'includes/class-' . strtolower(str_replace('_','-',$class)) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

// Boot
add_action('plugins_loaded', function(){
    load_plugin_textdomain('rs-installments', false, dirname(plugin_basename(__FILE__)).'/languages');

    // Core singletons
    RSIP_Settings::instance();
    RSIP_Render::instance();

    // Admin menu
    add_action('admin_menu', ['RSIP_Settings','add_menu']);
    add_action('admin_init', ['RSIP_Settings','register']);

    // Shortcode
    add_shortcode('wc_installment_prices', ['RSIP_Render','shortcode']);

    // Auto inject on single product
    add_action('wp', function(){
        if ( function_exists('is_product') && is_product() ) {
            $s = RSIP_Settings::get();
            if ( !empty($s['auto_inject']) && (!empty($s['target_selector']) || !empty($s['target_class'])) ) {
                add_action('woocommerce_single_product_summary', function(){
                    echo '<div id="rsip-autobox" style="display:none;">'. do_shortcode('[wc_installment_prices]') .'</div>';
                }, 99);
                add_action('wp_enqueue_scripts', function(){
                    wp_enqueue_script('jquery');
                    wp_enqueue_script('rsip-auto', RSIP_URL.'assets/js/auto-inject.js', ['jquery'], RSIP_VERSION, true);
                    $s = RSIP_Settings::get();
                    wp_localize_script('rsip-auto', 'RSIP_SETTINGS', [
                        'target_selector' => (string)($s['target_selector'] ?? ''),
                        'target_class'    => (string)($s['target_class'] ?? ''),
                        'rtl'             => is_rtl(),
                    ]);
                });
            }
        }
    });
});

// Activation defaults
register_activation_hook(__FILE__, function(){
    $defs = RSIP_Settings::defaults();
    $cur  = get_option('rsip_settings', []);
    if (!is_array($cur)) $cur = [];
    update_option('rsip_settings', wp_parse_args($cur, $defs));
});

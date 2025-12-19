<?php
/**
 * Plugin Name: RS Installments (Pro Edition)
 * Plugin URI:  https://github.com/fazelghaemi/deposits-wordpress-plugin
 * Description: سیستم پیشرفته محاسبه اقساط ووکامرس. با قابلیت تعریف سود پلکانی، شرط حداقل مبلغ سبد خرید، سازگاری با HPOS و پنل تنظیمات حرفه‌ای.
 * Version:     2.0.0
 * Author:      Ready Studio
 * Text Domain: rs-installments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined('ABSPATH') ) exit;

/**
 * تعریف ثابت‌های افزونه
 */
define('RSIP_VERSION', '2.0.0');
define('RSIP_PATH', plugin_dir_path(__FILE__));
define('RSIP_URL',  plugin_dir_url(__FILE__));

/**
 * لود خودکار کلاس‌ها (Autoloader)
 * کلاس‌هایی که با RSIP_ شروع می‌شوند را از پوشه includes فراخوانی می‌کند.
 */
spl_autoload_register(function($class){
    if (strpos($class, 'RSIP_') === 0) {
        $file = RSIP_PATH . 'includes/class-' . strtolower(str_replace('_','-',$class)) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

/**
 * راه‌اندازی اولیه افزونه
 */
add_action('plugins_loaded', function(){
    // لود فایل ترجمه
    load_plugin_textdomain('rs-installments', false, dirname(plugin_basename(__FILE__)).'/languages');

    // نمونه‌سازی از کلاس‌های اصلی (Singleton)
    RSIP_Settings::instance();
    RSIP_Render::instance();
});

/**
 * افزودن منوها و تنظیمات ادمین
 */
add_action('admin_menu', ['RSIP_Settings','add_menu']);
add_action('admin_init', ['RSIP_Settings','register']);

add_action('admin_enqueue_scripts', function($hook){
    // فقط در صفحه تنظیمات خودمان لود شود تا تداخلی ایجاد نکند
    if (strpos($hook, 'rsip-settings') !== false) {
        wp_enqueue_style('rsip-admin-css', RSIP_URL . 'assets/css/admin.css', [], RSIP_VERSION);
    }
});

/**
 * اعلام سازگاری با HPOS (High-Performance Order Storage)
 * این بخش برای نسخه‌های جدید ووکامرس ضروری است تا اخطار Legacy Mode ندهد.
 */
add_action('before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

/**
 * ثبت شورتکد
 * شورتکد قدیمی حفظ شده تا صفحات فعلی سایت به هم نریزد.
 */
add_shortcode('wc_installment_prices', ['RSIP_Render','shortcode']);

/**
 * فراخوانی فایل‌های استایل و اسکریپت در فرانت‌اند
 * فقط در صفحات محصول تکی ووکامرس لود می‌شوند تا سرعت سایت در صفحات دیگر افت نکند.
 */
add_action('wp_enqueue_scripts', function(){
    if ( ! function_exists('is_product') || ! is_product() ) return;

    // فایل استایل جدید (بهینه شده)
    wp_enqueue_style('rsip-styles', RSIP_URL . 'assets/css/style.css', [], RSIP_VERSION);
    
    // فایل جاوااسکریپت محاسبات زنده
    wp_enqueue_script('rsip-auto', RSIP_URL.'assets/js/auto-inject.js', ['jquery'], RSIP_VERSION, true);
    
    // ارسال تنظیمات PHP به JS
    $settings = RSIP_Settings::get();
    wp_localize_script('rsip-auto', 'RSIP_DATA', [
        'auto_inject'     => !empty($settings['auto_inject']),
        'target_selector' => (string)($settings['target_selector'] ?? ''),
        'target_class'    => (string)($settings['target_class'] ?? ''),
        'is_rtl'          => is_rtl(),
        'currency_symbol' => get_woocommerce_currency_symbol(),
        // این متغیرها برای دیباگ یا استفاده‌های خاص مفید هستند
        'ajax_url'        => admin_url('admin-ajax.php'),
    ]);
});

/**
 * هوک درج خودکار باکس در صفحه محصول
 * باکس ابتدا به صورت مخفی (hidden) رندر می‌شود و سپس توسط JS به مکان دقیق منتقل می‌شود.
 * این روش بهترین سازگاری را با صفحه‌سازها و قالب‌های مختلف دارد.
 */
add_action('woocommerce_single_product_summary', function(){
    $s = RSIP_Settings::get();
    
    // اگر تیک درج خودکار زده شده باشد
    if ( !empty($s['auto_inject']) ) {
        // یک Wrapper با ID مشخص ایجاد می‌کنیم تا JS بتواند آن را پیدا کند
        echo '<div id="rsip-autobox-wrapper" style="display:none;">';
        // شورتکد را اجرا می‌کنیم تا منطق رندر (شامل بررسی قیمت و ...) انجام شود
        echo do_shortcode('[wc_installment_prices]'); 
        echo '</div>';
    }
}, 35); // اولویت 35 معمولاً بعد از دکمه افزودن به سبد خرید است

/**
 * هوک فعال‌سازی افزونه: تنظیم مقادیر پیش‌فرض
 */
register_activation_hook(__FILE__, function(){
    $defs = RSIP_Settings::defaults();
    $cur  = get_option('rsip_settings', []);
    if (!is_array($cur)) $cur = [];
    update_option('rsip_settings', wp_parse_args($cur, $defs));
});
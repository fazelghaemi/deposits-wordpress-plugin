<?php
if ( ! defined('ABSPATH') ) exit;

class RSIP_Settings {
    private static $instance = null;

    public static function instance(){
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public static function defaults(){
        return [
            'target_selector' => '',       // CSS selector دقیق (اولویت بالاتر از کلاس)
            'target_class'    => '',       // تنها نام کلاس بدون نقطه
            'months'          => '8,10,12',
            'calc_type'       => 'markup', // markup | apr
            'monthly_rate'    => '0',      // درصد ماهانه
            'payment_label'   => __('چک / سفته','rs-installments'),
            'down_percent'    => '0',
            'down_fixed'      => '0',
            'rounding'        => '0',      // 0 = بدون گرد کردن | 1000 | 10000
            'auto_inject'     => '0',
        ];
    }

    public static function get(){
        $opts = get_option('rsip_settings', []);
        return wp_parse_args( is_array($opts) ? $opts : [], self::defaults() );
    }

    public static function add_menu(){
        if ( class_exists('WooCommerce') ) {
            add_submenu_page('woocommerce', __('تنظیمات اقساط','rs-installments'), __('اقساط','rs-installments'),
                'manage_options', 'rsip-settings', [__CLASS__, 'render_page']);
        } else {
            add_options_page(__('تنظیمات اقساط','rs-installments'), __('تنظیمات اقساط','rs-installments'),
                'manage_options', 'rsip-settings', [__CLASS__, 'render_page']);
        }
    }

    public static function register(){
        register_setting('rsip_settings_group', 'rsip_settings', [__CLASS__, 'sanitize']);

        add_settings_section('rsip_main', __('پیکربندی باکس اقساط','rs-installments'), function(){}, 'rsip-settings');

        add_settings_field('target_selector', __('سلکتور هدف (CSS)','rs-installments'), [__CLASS__,'field_target_selector'], 'rsip-settings', 'rsip_main');
        add_settings_field('target_class', __('کلاس هدف (بدون نقطه)','rs-installments'), [__CLASS__,'field_target_class'], 'rsip-settings', 'rsip_main');
        add_settings_field('months', __('تعداد ماه‌ها','rs-installments'), [__CLASS__,'field_months'], 'rsip-settings', 'rsip_main');
        add_settings_field('calc_type', __('نوع محاسبه','rs-installments'), [__CLASS__,'field_calc_type'], 'rsip-settings', 'rsip_main');
        add_settings_field('monthly_rate', __('سود ماهانه (%)','rs-installments'), [__CLASS__,'field_monthly_rate'], 'rsip-settings', 'rsip_main');
        add_settings_field('payment_label', __('نوع پرداخت (نمایشی)','rs-installments'), [__CLASS__,'field_payment_label'], 'rsip-settings', 'rsip_main');
        add_settings_field('down_percent', __('پیش‌پرداخت درصدی (%)','rs-installments'), [__CLASS__,'field_down_percent'], 'rsip-settings', 'rsip_main');
        add_settings_field('down_fixed', __('پیش‌پرداخت ثابت','rs-installments'), [__CLASS__,'field_down_fixed'], 'rsip-settings', 'rsip_main');
        add_settings_field('rounding', __('گرد کردن اقساط به','rs-installments'), [__CLASS__,'field_rounding'], 'rsip-settings', 'rsip_main');
        add_settings_field('auto_inject', __('درج خودکار','rs-installments'), [__CLASS__,'field_auto_inject'], 'rsip-settings', 'rsip_main');
    }

    public static function sanitize($in){
        $d = self::defaults(); $o = [];
        $o['target_selector'] = sanitize_text_field($in['target_selector'] ?? $d['target_selector']);
        $o['target_class']    = sanitize_html_class($in['target_class'] ?? $d['target_class']);
        $o['months']          = sanitize_text_field($in['months'] ?? $d['months']);
        $ct = isset($in['calc_type']) && in_array($in['calc_type'], ['markup','apr'], true) ? $in['calc_type'] : 'markup';
        $o['calc_type']       = $ct;
        $o['monthly_rate']    = is_numeric($in['monthly_rate'] ?? '') ? (string) floatval($in['monthly_rate']) : $d['monthly_rate'];
        $o['payment_label']   = sanitize_text_field($in['payment_label'] ?? $d['payment_label']);
        $dp                   = floatval($in['down_percent'] ?? $d['down_percent']);
        $o['down_percent']    = (string) max(0, min(100, $dp));
        $df                   = floatval(preg_replace('/[^\d\.]/','', (string)($in['down_fixed'] ?? $d['down_fixed'])));
        $o['down_fixed']      = (string) max(0, $df);
        $rounding             = intval($in['rounding'] ?? 0);
        $o['rounding']        = in_array($rounding, [0,1000,10000], true) ? (string)$rounding : '0';
        $o['auto_inject']     = !empty($in['auto_inject']) ? '1' : '0';
        return $o;
    }

    public static function render_page(){
        if (!current_user_can('manage_options')) return;
        $o = self::get(); ?>
        <div class="wrap" dir="rtl">
            <h1><?php _e('تنظیمات اقساط','rs-installments'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('rsip_settings_group');
                do_settings_sections('rsip-settings');
                submit_button(__('ذخیره تنظیمات','rs-installments'));
                ?>
            </form>
            <hr>
            <p><strong><?php _e('راهنما','rs-installments'); ?>:</strong></p>
            <ul>
                <li><?php _e('برای درج خودکار، یا سلکتور کامل CSS وارد کنید یا نام کلاس بدون نقطه. اگر هر دو موجود باشند، سلکتور اولویت دارد.','rs-installments'); ?></li>
                <li><?php _e('سود ماهانه در هر دو حالت محاسبه اعمال می‌شود. در APR فرمول قسط ثابت و در Markup افزایش کلی اعمال می‌شود.','rs-installments'); ?></li>
                <li><?php _e('پیش‌پرداخت درصدی بر ثابت اولویت دارد.','rs-installments'); ?></li>
                <li><?php _e('شورتکد:','rs-installments'); ?> <code>[wc_installment_prices]</code></li>
            </ul>
        </div>
    <?php }

    // Fields
    public static function field_target_selector(){ $o=self::get(); ?>
        <input type="text" class="regular-text" name="rsip_settings[target_selector]" value="<?php echo esc_attr($o['target_selector']); ?>" placeholder=".product .summary .price">
        <p class="description"><?php _e('اگر این مقدار خالی باشد، از کلاس هدف استفاده می‌شود.','rs-installments'); ?></p>
    <?php }
    public static function field_target_class(){ $o=self::get(); ?>
        <input type="text" class="regular-text" name="rsip_settings[target_class]" value="<?php echo esc_attr($o['target_class']); ?>" placeholder="price, summary">
        <p class="description"><?php _e('نام کلاس بدون نقطه. باکس بعد از اولین المان دارای این کلاس درج می‌شود.','rs-installments'); ?></p>
    <?php }
    public static function field_months(){ $o=self::get(); ?>
        <input type="text" class="regular-text" name="rsip_settings[months]" value="<?php echo esc_attr($o['months']); ?>" placeholder="8,10,12">
    <?php }
    public static function field_calc_type(){ $o=self::get(); ?>
        <select name="rsip_settings[calc_type]">
            <option value="markup" <?php selected($o['calc_type'],'markup'); ?>><?php _e('ساده (Markup)','rs-installments'); ?></option>
            <option value="apr" <?php selected($o['calc_type'],'apr'); ?>><?php _e('قسط بانکی (APR ماهانه)','rs-installments'); ?></option>
        </select>
    <?php }
    public static function field_monthly_rate(){ $o=self::get(); ?>
        <input type="number" step="0.01" min="0" name="rsip_settings[monthly_rate]" value="<?php echo esc_attr($o['monthly_rate']); ?>" class="small-text"> %
    <?php }
    public static function field_payment_label(){ $o=self::get(); ?>
        <input type="text" class="regular-text" name="rsip_settings[payment_label]" value="<?php echo esc_attr($o['payment_label']); ?>">
    <?php }
    public static function field_down_percent(){ $o=self::get(); ?>
        <input type="number" step="0.01" min="0" max="100" name="rsip_settings[down_percent]" value="<?php echo esc_attr($o['down_percent']); ?>" class="small-text"> %
    <?php }
    public static function field_down_fixed(){ $o=self::get(); ?>
        <input type="text" class="regular-text" name="rsip_settings[down_fixed]" value="<?php echo esc_attr($o['down_fixed']); ?>" placeholder="1500000">
    <?php }
    public static function field_rounding(){ $o=self::get(); ?>
        <select name="rsip_settings[rounding]">
            <option value="0" <?php selected($o['rounding'],'0'); ?>><?php _e('بدون گرد کردن','rs-installments'); ?></option>
            <option value="1000" <?php selected($o['rounding'],'1000'); ?>><?php _e('گرد به ۱٬۰۰۰','rs-installments'); ?></option>
            <option value="10000" <?php selected($o['rounding'],'10000'); ?>><?php _e('گرد به ۱۰٬۰۰۰','rs-installments'); ?></option>
        </select>
    <?php }
    public static function field_auto_inject(){ $o=self::get(); ?>
        <label><input type="checkbox" name="rsip_settings[auto_inject]" value="1" <?php checked($o['auto_inject'],'1'); ?>> <?php _e('فعال','rs-installments'); ?></label>
    <?php }
}

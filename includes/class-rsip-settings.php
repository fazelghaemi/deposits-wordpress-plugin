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
            // تنظیمات نمایش
            'auto_inject'     => '0',
            'target_selector' => '.product .summary .price', 
            'target_class'    => '',
            'box_design'      => 'default',   // تنظیم جدید: default | modern | horizontal
            
            // تنظیمات منطقی
            'min_price'       => '0',
            'calc_mode'       => 'simple',
            'calc_type'       => 'markup',
            
            // متغیرهای حالت ساده
            'simple_months'   => '3,6,12',
            'simple_rate'     => '3.5',
            
            // متغیرهای حالت پیشرفته
            'advanced_plan'   => "3|2\n6|3.5\n9|4.5\n12|5",
            
            // تنظیمات پرداخت
            'down_percent'    => '20',
            'down_fixed'      => '0',
            'rounding'        => '1000',
            
            // متن‌ها
            'box_title'       => __('محاسبه اقساط خرید','rs-installments'),
            'payment_label'   => __('چک صیادی','rs-installments'),
        ];
    }

    public static function get(){
        $opts = get_option('rsip_settings', []);
        return wp_parse_args( is_array($opts) ? $opts : [], self::defaults() );
    }

    public static function register(){
        register_setting('rsip_settings_group', 'rsip_settings', [__CLASS__, 'sanitize']);

        // --- بخش 1: عمومی و نمایش ---
        add_settings_section('rsip_general', __('تنظیمات عمومی و نمایش','rs-installments'), function(){}, 'rsip-settings');
        add_settings_field('auto_inject', __('فعالسازی درج خودکار','rs-installments'), [__CLASS__,'f_auto_inject'], 'rsip-settings', 'rsip_general');
        
        // فیلد جدید انتخاب دیزاین
        add_settings_field('box_design', __('طرح ظاهری باکس','rs-installments'), [__CLASS__,'f_box_design'], 'rsip-settings', 'rsip_general');
        
        add_settings_field('min_price', __('حداقل قیمت محصول','rs-installments'), [__CLASS__,'f_min_price'], 'rsip-settings', 'rsip_general');
        add_settings_field('target_selector', __('محل نمایش (CSS Selector)','rs-installments'), [__CLASS__,'f_target'], 'rsip-settings', 'rsip_general');

        // --- بخش 2: محاسبات ---
        add_settings_section('rsip_calc', __('محاسبات و نرخ سود','rs-installments'), function(){}, 'rsip-settings');
        add_settings_field('calc_mode', __('شیوه محاسبه','rs-installments'), [__CLASS__,'f_calc_mode'], 'rsip-settings', 'rsip_calc');
        add_settings_field('simple_settings', __('تنظیمات حالت ساده','rs-installments'), [__CLASS__,'f_simple_settings'], 'rsip-settings', 'rsip_calc');
        add_settings_field('advanced_plan', __('نقشه پیشرفته (ماه | سود)','rs-installments'), [__CLASS__,'f_advanced_plan'], 'rsip-settings', 'rsip_calc');
        add_settings_field('calc_type', __('فرمول بانکی','rs-installments'), [__CLASS__,'f_calc_type'], 'rsip-settings', 'rsip_calc');
        
        // --- بخش 3: مالی ---
        add_settings_section('rsip_payment', __('پیش‌پرداخت و مبالغ','rs-installments'), function(){}, 'rsip-settings');
        add_settings_field('down_payment', __('پیش‌پرداخت','rs-installments'), [__CLASS__,'f_down_payment'], 'rsip-settings', 'rsip_payment');
        add_settings_field('rounding', __('رند کردن اقساط','rs-installments'), [__CLASS__,'f_rounding'], 'rsip-settings', 'rsip_payment');
        
        // --- بخش 4: متون ---
        add_settings_section('rsip_text', __('متن‌ها','rs-installments'), function(){}, 'rsip-settings');
        add_settings_field('labels', __('عناوین نمایشی','rs-installments'), [__CLASS__,'f_labels'], 'rsip-settings', 'rsip_text');
    }

    public static function add_menu(){
        add_submenu_page('woocommerce', __('محاسبه اقساط','rs-installments'), __('محاسبه اقساط','rs-installments'),
            'manage_options', 'rsip-settings', [__CLASS__, 'render_page']);
    }

    public static function sanitize($in){
        $d = self::defaults();
        $o = [];
        
        $o['auto_inject']     = !empty($in['auto_inject']) ? '1' : '0';
        $o['box_design']      = in_array($in['box_design']??'', ['default','modern','horizontal']) ? $in['box_design'] : 'default';
        $o['target_selector'] = sanitize_text_field($in['target_selector'] ?? $d['target_selector']);
        $o['target_class']    = sanitize_html_class($in['target_class'] ?? '');
        
        $o['min_price']       = absint(preg_replace('/[^0-9]/', '', $in['min_price'] ?? '0'));
        $o['rounding']        = absint($in['rounding'] ?? 0);
        
        $o['calc_mode']       = ($in['calc_mode'] === 'advanced') ? 'advanced' : 'simple';
        $o['calc_type']       = ($in['calc_type'] === 'apr') ? 'apr' : 'markup';
        
        $o['simple_months']   = sanitize_text_field($in['simple_months'] ?? $d['simple_months']);
        $o['simple_rate']     = floatval($in['simple_rate'] ?? 0);
        
        $plan_raw = wp_strip_all_tags($in['advanced_plan'] ?? '');
        $o['advanced_plan']   = $plan_raw; 
        
        $o['down_percent']    = min(100, max(0, floatval($in['down_percent'] ?? 0)));
        $o['down_fixed']      = absint(preg_replace('/[^0-9]/', '', $in['down_fixed'] ?? '0'));
        
        $o['box_title']       = sanitize_text_field($in['box_title'] ?? $d['box_title']);
        $o['payment_label']   = sanitize_text_field($in['payment_label'] ?? $d['payment_label']);
        
        return $o;
    }

    public static function render_page(){
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap" dir="rtl">
            <h1><?php _e('تنظیمات پیشرفته اقساط ووکامرس','rs-installments'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('rsip_settings_group'); do_settings_sections('rsip-settings'); submit_button(); ?>
            </form>
            <script>
                jQuery(document).ready(function($){
                    function toggleMode(){
                        var mode = $('select[name="rsip_settings[calc_mode]"]').val();
                        if(mode === 'simple'){
                            $('.rsip-simple-row').slideDown();
                            $('.rsip-advanced-row').slideUp();
                        } else {
                            $('.rsip-simple-row').slideUp();
                            $('.rsip-advanced-row').slideDown();
                        }
                    }
                    $('select[name="rsip_settings[calc_mode]"]').on('change', toggleMode);
                    toggleMode();
                });
            </script>
            <style>
                .form-table th { width: 220px; }
                .rsip-simple-row, .rsip-advanced-row { padding: 10px; background: #f9f9f9; border-radius: 5px; border: 1px solid #ddd; }
                .description { font-style: italic; color: #666; }
            </style>
        </div>
        <?php
    }

    // --- Fields ---

    public static function f_auto_inject(){ $o=self::get(); ?>
        <label>
            <input type="checkbox" name="rsip_settings[auto_inject]" value="1" <?php checked($o['auto_inject'], '1'); ?>>
            <?php _e('نمایش خودکار در صفحه محصول','rs-installments'); ?>
        </label>
    <?php }

    public static function f_box_design(){ $o=self::get(); ?>
        <select name="rsip_settings[box_design]">
            <option value="default" <?php selected($o['box_design'], 'default'); ?>><?php _e('لیست ساده (کلاسیک)','rs-installments'); ?></option>
            <option value="modern" <?php selected($o['box_design'], 'modern'); ?>><?php _e('کارت‌های مدرن (طرح گرافیکی)','rs-installments'); ?></option>
            <option value="horizontal" <?php selected($o['box_design'], 'horizontal'); ?>><?php _e('سطری فشرده (دسکتاپ افقی)','rs-installments'); ?></option>
        </select>
        <p class="description"><?php _e('نحوه نمایش باکس اقساط را انتخاب کنید.','rs-installments'); ?></p>
    <?php }

    public static function f_min_price(){ $o=self::get(); ?>
        <input type="text" class="regular-text" name="rsip_settings[min_price]" value="<?php echo esc_attr($o['min_price']); ?>">
        <p class="description"><?php _e('حداقل قیمت برای نمایش باکس (تومان).','rs-installments'); ?></p>
    <?php }

    public static function f_target(){ $o=self::get(); ?>
        <input type="text" class="large-text" name="rsip_settings[target_selector]" value="<?php echo esc_attr($o['target_selector']); ?>" placeholder=".product .summary .price">
    <?php }

    public static function f_calc_mode(){ $o=self::get(); ?>
        <select name="rsip_settings[calc_mode]">
            <option value="simple" <?php selected($o['calc_mode'],'simple'); ?>><?php _e('حالت ساده','rs-installments'); ?></option>
            <option value="advanced" <?php selected($o['calc_mode'],'advanced'); ?>><?php _e('حالت پیشرفته','rs-installments'); ?></option>
        </select>
    <?php }

    public static function f_simple_settings(){ $o=self::get(); ?>
        <div class="rsip-simple-row">
            <label><?php _e('ماه‌ها:','rs-installments'); ?></label><br>
            <input type="text" class="regular-text" name="rsip_settings[simple_months]" value="<?php echo esc_attr($o['simple_months']); ?>"><br>
            <label><?php _e('سود ماهانه (%):','rs-installments'); ?></label><br>
            <input type="number" step="0.01" name="rsip_settings[simple_rate]" value="<?php echo esc_attr($o['simple_rate']); ?>" class="small-text"> %
        </div>
    <?php }

    public static function f_advanced_plan(){ $o=self::get(); ?>
        <div class="rsip-advanced-row">
            <textarea name="rsip_settings[advanced_plan]" rows="5" class="large-text code"><?php echo esc_textarea($o['advanced_plan']); ?></textarea>
        </div>
    <?php }

    public static function f_calc_type(){ $o=self::get(); ?>
        <select name="rsip_settings[calc_type]">
            <option value="markup" <?php selected($o['calc_type'],'markup'); ?>><?php _e('ساده (Markup)','rs-installments'); ?></option>
            <option value="apr" <?php selected($o['calc_type'],'apr'); ?>><?php _e('بانکی (APR)','rs-installments'); ?></option>
        </select>
    <?php }

    public static function f_down_payment(){ $o=self::get(); ?>
        <label><?php _e('درصد:','rs-installments'); ?> <input type="number" step="0.1" name="rsip_settings[down_percent]" value="<?php echo esc_attr($o['down_percent']); ?>" class="small-text">%</label>
        <label><?php _e('مبلغ ثابت:','rs-installments'); ?> <input type="text" name="rsip_settings[down_fixed]" value="<?php echo esc_attr($o['down_fixed']); ?>" class="regular-text"></label>
    <?php }

    public static function f_rounding(){ $o=self::get(); ?>
        <select name="rsip_settings[rounding]">
            <option value="0" <?php selected($o['rounding'],'0'); ?>><?php _e('بدون گرد کردن','rs-installments'); ?></option>
            <option value="1000" <?php selected($o['rounding'],'1000'); ?>>1,000</option>
            <option value="10000" <?php selected($o['rounding'],'10000'); ?>>10,000</option>
            <option value="50000" <?php selected($o['rounding'],'50000'); ?>>50,000</option>
            <option value="100000" <?php selected($o['rounding'],'100000'); ?>>100,000</option>
        </select>
    <?php }

    public static function f_labels(){ $o=self::get(); ?>
        <label><?php _e('عنوان باکس:','rs-installments'); ?></label><br>
        <input type="text" class="regular-text" name="rsip_settings[box_title]" value="<?php echo esc_attr($o['box_title']); ?>"><br>
        <label><?php _e('عنوان پرداخت:','rs-installments'); ?></label><br>
        <input type="text" class="regular-text" name="rsip_settings[payment_label]" value="<?php echo esc_attr($o['payment_label']); ?>">
    <?php }
}
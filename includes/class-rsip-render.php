<?php
if ( ! defined('ABSPATH') ) exit;

class RSIP_Render {
    private static $instance = null;
    public static function instance(){
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /** Shortcode handler */
    public static function shortcode($atts){
        $s = RSIP_Settings::get();
        $atts = shortcode_atts([
            'months'       => $s['months'],
            'type'         => $s['calc_type'],
            'rate'         => $s['monthly_rate'],
            'down'         => '',                 // درصد
            'down_fixed'   => '',                 // مبلغ
            'title'        => __('خرید اقساطی','rs-installments'),
            'class'        => '',
            'payment_type' => $s['payment_label'],
        ], $atts, 'wc_installment_prices');

        if ( ! function_exists('wc_get_product') ) return '';

        global $product;
        if ( ! $product instanceof WC_Product ) $product = wc_get_product(get_the_ID());
        if ( ! $product ) return '';

        $base_price = wc_get_price_to_display($product); // respects tax display
        if ( $base_price <= 0 ) return '';

        $months_raw = array_filter(array_map('trim', explode(',', (string)$atts['months'])));
        $months = [];
        foreach($months_raw as $m){ $mi=absint($m); if($mi>0) $months[]=$mi; }
        if (empty($months)) $months = [8,10,12];

        $type   = in_array($atts['type'], ['markup','apr'], true) ? $atts['type'] : 'markup';
        $rate   = floatval($atts['rate']); // monthly %
        $title  = sanitize_text_field($atts['title']);
        $class  = sanitize_html_class($atts['class']);
        $paylbl = sanitize_text_field($atts['payment_type']);

        // down payment
        $g = RSIP_Settings::get();
        $down_percent = ($atts['down'] !== '') ? floatval($atts['down']) : floatval($g['down_percent']);
        $down_fixed   = ($atts['down_fixed'] !== '') ? floatval($atts['down_fixed']) : floatval($g['down_fixed']);

        if ($down_percent > 0){
            $down_amount = $base_price * ($down_percent/100.0);
            $down_note   = sprintf(__('پیش‌پرداخت (%s%%)','rs-installments'), round($down_percent,2));
        } elseif ($down_fixed > 0){
            $down_amount = min($base_price, $down_fixed);
            $down_note   = __('پیش‌پرداخت (مبلغ ثابت)','rs-installments');
        } else {
            $down_amount = 0.0;
            $down_note   = '';
        }

        $principal = max(0.0, $base_price - $down_amount);

        // helpers
        $format_money = function($a){ return wc_price( max(0,$a) ); };
        $round_to = intval($g['rounding'] ?? 0);
        $rounder = function($a) use ($round_to){
            if ($round_to > 0) {
                return round($a / $round_to) * $round_to;
            }
            return $a;
        };
        $apr_payment = function($P,$n,$monthly_rate_percent){
            if($n<=0 || $P<=0) return 0.0;
            $r = max(0.0, floatval($monthly_rate_percent)) / 100.0;
            if ($r==0.0) return $P / $n;
            $pow = pow(1+$r, $n);
            return $P * $r * $pow / ($pow - 1);
        };

        $items = [];
        foreach($months as $m){
            if ($type === 'apr'){
                $monthly = $apr_payment($principal, $m, $rate);
                $monthly = $rounder($monthly);
                $total   = $down_amount + $monthly * $m;
            } else {
                // markup simple (increase on principal, not compounded)
                $total_with_markup = $principal * ( $rate>0 ? (1 + $rate/100.0) : 1 );
                $monthly = $rounder($total_with_markup / $m);
                $total   = $down_amount + $monthly * $m;
            }
            $items[] = ['months'=>$m, 'monthly'=>$monthly, 'total'=>$total];
        }

        // Prepare context for template
        $context = [
            'title'        => $title,
            'class'        => $class,
            'base_price'   => $base_price,
            'down_amount'  => $down_amount,
            'down_note'    => $down_note,
            'items'        => $items,
            'type'         => $type,
            'rate'         => $rate,
            'months'       => $months,
            'payment_type' => $paylbl,
        ];

        ob_start();
        self::template('box.php', $context);
        return ob_get_clean();
    }

    /** Simple template loader with override support (theme/child-theme) */
    public static function template($file, $vars = []){
        $paths = [
            trailingslashit(get_stylesheet_directory()) . 'rs-installments/' . $file,
            trailingslashit(get_template_directory())   . 'rs-installments/' . $file,
            RSIP_PATH . 'templates/' . $file,
        ];
        foreach($vars as $k=>$v){ $$k=$v; }
        foreach($paths as $p){
            if (file_exists($p)) { include $p; return; }
        }
        echo '<!-- RSIP template missing -->';
    }
}

<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="rs-installments-box <?php echo esc_attr($class); ?>" dir="<?php echo is_rtl() ? 'rtl':'ltr'; ?>"
     data-type="<?php echo esc_attr($type); ?>"
     data-rate="<?php echo esc_attr($rate); ?>"
     data-months="<?php echo esc_attr(implode(',', $months)); ?>"
     data-base-price="<?php echo esc_attr($base_price); ?>"
     data-down-amount="<?php echo esc_attr($down_amount); ?>">
    <div class="rs-installments-header"><?php echo esc_html($title); ?></div>

    <div class="rs-installments-meta">
        <div class="rs-installments-row rs-installments-row--price">
            <span class="rs-label"><?php _e('قیمت محصول:','rs-installments'); ?></span>
            <span class="rs-value rs-base-price"><?php echo wc_price($base_price); ?></span>
        </div>
        <?php if ($down_amount>0): ?>
        <div class="rs-installments-row rs-installments-row--down">
            <span class="rs-label"><?php echo esc_html($down_note); ?>:</span>
            <span class="rs-value rs-down-amount"><?php echo wc_price($down_amount); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payment_type): ?>
        <div class="rs-installments-row rs-installments-row--payment">
            <span class="rs-label"><?php _e('نوع پرداخت:','rs-installments'); ?></span>
            <span class="rs-value rs-payment-type"><?php echo esc_html($payment_type); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <ul class="rs-installments-list">
        <?php foreach ($items as $it): ?>
            <li class="rs-installments-item" data-months="<?php echo intval($it['months']); ?>">
                <div class="rs-installments-item__months"><?php echo esc_html($it['months']); ?> <?php _e('ماهه','rs-installments'); ?></div>
                <div class="rs-installments-item__monthly"><?php _e('هر قسط:','rs-installments'); ?>
                    <strong class="rs-monthly"><?php echo wc_price($it['monthly']); ?></strong>
                </div>
                <div class="rs-installments-item__total"><?php _e('مجموع پرداختی:','rs-installments'); ?>
                    <span class="rs-total"><?php echo wc_price($it['total']); ?></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <style>
        .rs-installments-box{border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin-top:10px;background:#fff}
        .rs-installments-header{font-weight:700;margin-bottom:8px;font-size:15px}
        .rs-installments-row{display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px}
        .rs-label{color:#6b7280}
        .rs-value{font-weight:600}
        .rs-installments-list{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:8px}
        .rs-installments-item{display:flex;align-items:center;justify-content:space-between;border:1px dashed #e5e7eb;border-radius:10px;padding:10px}
        .rs-installments-item__months{font-weight:700}
        .rs-installments-item__monthly{font-size:14px}
        .rs-installments-item__total{font-size:13px;color:#6b7280}
    </style>
</div>

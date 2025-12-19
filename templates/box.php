<?php
/**
 * RS Installments - Box Template
 *
 * This template renders the installment calculator box on the frontend.
 * It is designed to be fully responsive and supports multiple design layouts
 * (Default, Modern, Horizontal) purely via CSS classes.
 *
 * Variables available in this scope (passed from RSIP_Render):
 * @var string $title          The title of the box.
 * @var string $payment_label  The label for the payment method (e.g., Check).
 * @var string $class          CSS classes for the container (includes design variant).
 * @var float  $price          The product's base price.
 * @var array  $down_data      Array containing 'amount' and 'label' for down payment.
 * @var array  $rows           Array of calculated installment plans.
 * @var string $settings_json  JSON configuration for JavaScript live calculations.
 *
 * @package     RS_Installments
 * @subpackage  Templates
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>

<!-- 
    Start RS Installments Box 
    The 'rsip-box' class is the main hook for CSS and JS.
    Additional classes like 'rsip-design-modern' control the visual layout.
-->
<div class="rsip-box <?php echo esc_attr( $class ); ?>" 
     dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
     data-rsip-config="<?php echo esc_attr( $settings_json ); ?>"
     data-base-price="<?php echo esc_attr( $price ); ?>">
    
    <!-- 
        1. Header Section
        Contains the main title and a generic calculator icon.
    -->
    <div class="rsip-header">
        <div class="rsip-header-content">
            <span class="rsip-title-icon" aria-hidden="true">
                <!-- SVG Icon: Calculator / Percentage -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
            </span>
            <h3 class="rsip-title-text"><?php echo esc_html( $title ); ?></h3>
        </div>
        
        <!-- Optional: A badge or info tooltip could go here in future updates -->
    </div>

    <!-- 
        2. Summary Section
        Displays the cash price, down payment, and payment method type.
        This section updates dynamically via JS when variations change.
    -->
    <div class="rsip-summary">
        
        <!-- Row: Cash Price -->
        <div class="rsip-row rsip-row-price">
            <span class="rsip-label"><?php esc_html_e( 'قیمت نقدی محصول:', 'rs-installments' ); ?></span>
            <span class="rsip-value rsip-price-display">
                <?php echo wc_price( $price ); ?>
            </span>
        </div>
        
        <!-- Row: Down Payment (Conditionally Rendered) -->
        <?php 
        // Determine visibility style based on amount to prevent layout shift
        $down_style = ( $down_data['amount'] <= 0 ) ? 'display:none;' : ''; 
        ?>
        <div class="rsip-row rsip-row-down" style="<?php echo esc_attr( $down_style ); ?>">
            <span class="rsip-label"><?php echo esc_html( $down_data['label'] ); ?>:</span>
            <span class="rsip-value rsip-down-display">
                <?php echo wc_price( $down_data['amount'] ); ?>
            </span>
        </div>

        <!-- Row: Payment Method Label (e.g., Cheque) -->
        <?php if ( ! empty( $payment_label ) ) : ?>
            <div class="rsip-row rsip-row-type">
                <span class="rsip-label"><?php esc_html_e( 'روش پرداخت:', 'rs-installments' ); ?></span>
                <span class="rsip-value rsip-payment-method">
                    <?php echo esc_html( $payment_label ); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- 
        3. Installment Plans Grid
        Loops through the calculated plans and renders cards/rows for each option.
    -->
    <div class="rsip-plans-container">
        <?php if ( ! empty( $rows ) ) : ?>
            
            <div class="rsip-plans-grid">
                <?php foreach ( $rows as $index => $row ) : ?>
                    
                    <!-- Single Plan Item -->
                    <div class="rsip-plan-item" data-months="<?php echo esc_attr( $row['months'] ); ?>">
                        
                        <!-- Plan Header: Duration & Interest Rate -->
                        <div class="rsip-plan-header">
                            <span class="rsip-badge-month">
                                <?php echo esc_html( $row['months'] ); ?> <?php esc_html_e( 'ماه', 'rs-installments' ); ?>
                            </span>
                            
                            <?php if ( $row['rate'] > 0 ) : ?>
                                <span class="rsip-badge-rate" title="<?php esc_attr_e( 'نرخ سود ماهانه', 'rs-installments' ); ?>">
                                    <?php echo esc_html( $row['rate'] ); ?>%
                                </span>
                            <?php else : ?>
                                <span class="rsip-badge-rate rsip-rate-zero">
                                    <?php esc_html_e( 'بدون سود', 'rs-installments' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Plan Body: Financial Details -->
                        <div class="rsip-plan-body">
                            
                            <!-- Monthly Payment Column -->
                            <div class="rsip-plan-col rsip-col-monthly">
                                <small class="rsip-meta-label"><?php esc_html_e( 'مبلغ هر قسط:', 'rs-installments' ); ?></small>
                                <strong class="rsip-amount-large rsip-monthly-val">
                                    <?php echo wc_price( $row['monthly'] ); ?>
                                </strong>
                            </div>

                            <!-- Total Repayment Column -->
                            <div class="rsip-plan-col rsip-col-total">
                                <small class="rsip-meta-label"><?php esc_html_e( 'بازپرداخت نهایی:', 'rs-installments' ); ?></small>
                                <span class="rsip-amount-muted rsip-total-val">
                                    <?php echo wc_price( $row['total'] ); ?>
                                </span>
                            </div>

                        </div>
                        
                        <!-- Optional: Action Button (Layout Specific) -->
                        <div class="rsip-plan-action">
                            <span class="rsip-check-icon"></span>
                        </div>

                    </div><!-- End Plan Item -->

                <?php endforeach; ?>
            </div><!-- End Grid -->

        <?php else : ?>
            
            <!-- Empty State -->
            <div class="rsip-empty-state">
                <p><?php esc_html_e( 'در حال حاضر شرایط اقساطی برای این محصول تعریف نشده است.', 'rs-installments' ); ?></p>
            </div>

        <?php endif; ?>
    </div>
    
    <!-- 
        4. Footer / Disclaimer (Optional)
        Can be used for legal text or bank disclaimers.
    -->
    <div class="rsip-footer">
        <small><?php esc_html_e( 'محاسبات تقریبی است و ممکن است در زمان عقد قرارداد تغییر کند.', 'rs-installments' ); ?></small>
    </div>

</div>
<!-- End RS Installments Box -->
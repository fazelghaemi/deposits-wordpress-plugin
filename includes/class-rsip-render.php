<?php
/**
 * RS Installments - Render Class
 *
 * This class handles the rendering logic for the installment box.
 * It processes shortcodes, calculates installment plans based on settings,
 * determines down payments, and loads the appropriate template view.
 *
 * @package     RS_Installments
 * @subpackage  Includes
 * @author      Ready Studio
 * @version     2.0.0
 */

if ( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly for security.
}

class RSIP_Render {

    /**
     * The single instance of the class.
     *
     * @var RSIP_Render
     */
    private static $instance = null;

    /**
     * Singleton Pattern: Returns the single instance of this class.
     *
     * @return RSIP_Render
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Main Shortcode Handler: [wc_installment_prices]
     *
     * This method orchestrates the entire process:
     * 1. Fetches settings.
     * 2. Identifies the current product context.
     * 3. Validates price thresholds.
     * 4. Calculates financial data (interest, installments, down payment).
     * 5. Determines the UI design variant.
     * 6. Renders the template.
     *
     * @param array $atts Shortcode attributes passed by the user.
     * @return string The rendered HTML of the installment box.
     */
    public static function shortcode( $atts ) {
        // Retrieve global settings from the database
        $settings = RSIP_Settings::get();
        
        // Merge user-provided attributes with defaults and global settings
        // This allows per-shortcode overrides for flexibility.
        $atts = shortcode_atts( [
            'id'            => '', // Specific Product ID (optional)
            'title'         => isset($settings['box_title']) ? $settings['box_title'] : '',
            'payment_label' => isset($settings['payment_label']) ? $settings['payment_label'] : '',
            'class'         => '', // Custom CSS class
        ], $atts, 'wc_installment_prices' );

        // ---------------------------------------------------------------------
        // 1. Context Resolution: Determine which product we are dealing with.
        // ---------------------------------------------------------------------
        $product = null;

        if ( ! empty( $atts['id'] ) ) {
            // Case A: Explicit ID passed in shortcode
            $product = wc_get_product( $atts['id'] );
        } elseif ( function_exists( 'is_product' ) && is_product() ) {
            // Case B: We are on a Single Product Page
            $product = wc_get_product( get_the_ID() );
        } elseif ( in_the_loop() ) {
            // Case C: We are inside a Loop (e.g., Shop Page, Archives)
            global $post;
            if ( $post && isset($post->ID) ) {
                $product = wc_get_product( $post->ID );
            }
        }

        // Safety Check: If no valid WC_Product object found, abort silently.
        if ( ! $product instanceof WC_Product ) {
            return '';
        }

        // ---------------------------------------------------------------------
        // 2. Price Validation & Threshold Check
        // ---------------------------------------------------------------------
        // Get the price to display (this handles taxes based on store config).
        $price = wc_get_price_to_display( $product );
        
        // If product is free or price is invalid, do not show the box.
        if ( $price <= 0 ) {
            return '';
        }

        // Check Minimum Price Threshold (Feature Request #1)
        // If the product price is lower than the configured minimum, hide the box.
        $min_threshold = isset($settings['min_price']) ? floatval( $settings['min_price'] ) : 0;
        if ( $min_threshold > 0 && $price < $min_threshold ) {
            // Return a comment for debugging purposes in HTML source
            return '<!-- RSIP: Product price (' . $price . ') is below the minimum threshold (' . $min_threshold . '). Box hidden. -->';
        }

        // ---------------------------------------------------------------------
        // 3. Plan Construction: Build the roadmap of installments
        // ---------------------------------------------------------------------
        $plans = self::build_plans( $settings );
        
        // If no valid plans are configured (e.g., empty settings), abort.
        if ( empty( $plans ) ) {
            return '<!-- RSIP: No installment plans configured. -->';
        }

        // ---------------------------------------------------------------------
        // 4. Financial Calculations
        // ---------------------------------------------------------------------
        
        // A. Calculate Down Payment
        $down_data = self::calc_down_payment( $price, $settings );
        
        // The principal amount is the loan amount (Price - Down Payment).
        // Ensure it doesn't go below zero.
        $principal = max( 0.0, $price - $down_data['amount'] );

        // B. Calculate Monthly Payments for each plan
        $rows = [];
        $rounding = isset($settings['rounding']) ? intval( $settings['rounding'] ) : 0;
        $calc_type = isset($settings['calc_type']) ? $settings['calc_type'] : 'markup';
        
        foreach ( $plans as $plan ) {
            $months = intval( $plan['months'] );
            $rate   = floatval( $plan['rate'] ); // Monthly Interest Rate (%)
            
            $monthly_payment = 0.0;
            $total_payment   = 0.0;

            if ( $months > 0 ) {
                if ( $calc_type === 'apr' ) {
                    // --- Banking Formula (Amortization / PMT) ---
                    // Formula: P * r * (1+r)^n / ((1+r)^n - 1)
                    // Where r = monthly rate (decimal), n = months
                    $r = $rate / 100.0;
                    
                    if ( $r <= 0 ) {
                        // Zero interest case
                        $monthly_payment = $principal / $months;
                    } else {
                        $pow = pow( 1 + $r, $months );
                        // Prevent division by zero if pow is 1 (shouldn't happen with positive rate)
                        if ( $pow == 1 ) {
                            $monthly_payment = $principal / $months;
                        } else {
                            $monthly_payment = ( $principal * $r * $pow ) / ( $pow - 1 );
                        }
                    }
                } else {
                    // --- Simple Markup Formula (Market Standard) ---
                    // Logic: Total Interest = (Monthly Rate * Months)
                    // Total Amount = Principal * (1 + Total Interest%)
                    // Monthly Payment = Total Amount / Months
                    
                    $total_interest_percent = ( $rate * $months ) / 100.0;
                    $total_with_markup      = $principal * ( 1.0 + $total_interest_percent );
                    $monthly_payment        = $total_with_markup / $months;
                }

                // C. Rounding Logic
                // Round the monthly payment to the nearest configured integer (e.g., 1000 Tomans).
                if ( $rounding > 0 ) {
                    $monthly_payment = round( $monthly_payment / $rounding ) * $rounding;
                }
                
                // D. Total Repayment Calculation
                // Total = Down Payment + (Monthly Payment * Months)
                $total_payment = $down_data['amount'] + ( $monthly_payment * $months );
            }

            // Store the calculated row
            $rows[] = [
                'months'  => $months,
                'rate'    => $rate,
                'monthly' => $monthly_payment,
                'total'   => $total_payment
            ];
        }

        // ---------------------------------------------------------------------
        // 5. UI & Design Configuration
        // ---------------------------------------------------------------------
        // Determine the design class based on settings (default, modern, horizontal)
        $design_type = isset( $settings['box_design'] ) ? $settings['box_design'] : 'default';
        
        // Sanitize design type to ensure valid CSS class
        $valid_designs = ['default', 'modern', 'horizontal'];
        if ( ! in_array( $design_type, $valid_designs, true ) ) {
            $design_type = 'default';
        }
        
        $design_class = 'rsip-design-' . $design_type;
        
        // Combine user custom classes with the design class
        $final_class = trim( $atts['class'] . ' ' . $design_class );

        // ---------------------------------------------------------------------
        // 6. Data Preparation & Rendering
        // ---------------------------------------------------------------------
        // Prepare configuration JSON for the frontend JavaScript.
        // This allows JS to perform live recalculations when variations change
        // without making AJAX calls, ensuring instant feedback.
        $js_config = [
            'type'     => $calc_type,
            'rounding' => $rounding,
            'plans'    => $plans, // Pass the plan map (months/rates) to JS
            'down_pct' => isset($settings['down_percent']) ? floatval($settings['down_percent']) : 0,
            'down_fix' => isset($settings['down_fixed']) ? floatval($settings['down_fixed']) : 0,
        ];

        $context = [
            'title'         => $atts['title'],
            'payment_label' => $atts['payment_label'],
            'class'         => esc_attr( $final_class ),
            'price'         => $price,
            'down_data'     => $down_data,
            'rows'          => $rows,
            'settings_json' => json_encode( $js_config ),
        ];

        // Buffer the output to return it as a string
        ob_start();
        self::template( 'box.php', $context );
        return ob_get_clean();
    }

    /**
     * Helper: Build Installment Plans
     *
     * Parses the settings to create a normalized array of plans.
     * Handles both 'simple' (fixed rate) and 'advanced' (variable rate) modes.
     *
     * @param array $settings The plugin settings array.
     * @return array Array of plans, each containing ['months' => int, 'rate' => float].
     */
    private static function build_plans( $settings ) {
        $plans = [];
        $mode = isset( $settings['calc_mode'] ) ? $settings['calc_mode'] : 'simple';
        
        if ( $mode === 'advanced' ) {
            // --- Advanced Mode Parsing ---
            // Parses a textarea string where each line is "Months|Rate"
            // Example: "3|1.5 \n 6|2.0"
            $raw_plan = isset( $settings['advanced_plan'] ) ? $settings['advanced_plan'] : '';
            $lines = explode( "\n", $raw_plan );
            
            foreach ( $lines as $line ) {
                $parts = explode( '|', $line );
                if ( count( $parts ) >= 2 ) {
                    $m = absint( trim( $parts[0] ) );
                    $r = floatval( trim( $parts[1] ) );
                    
                    // Only add if months > 0
                    if ( $m > 0 ) {
                        $plans[] = [ 'months' => $m, 'rate' => $r ];
                    }
                }
            }
        } else {
            // --- Simple Mode Parsing ---
            // Parses a comma-separated string for months and uses a single fixed rate.
            // Example: Months="3,6,12", Rate="3.5"
            $months_str = isset( $settings['simple_months'] ) ? $settings['simple_months'] : '';
            $rate       = isset( $settings['simple_rate'] ) ? floatval( $settings['simple_rate'] ) : 0;
            
            $ms = explode( ',', $months_str );
            foreach ( $ms as $m ) {
                $mi = absint( $m );
                if ( $mi > 0 ) {
                    $plans[] = [ 'months' => $mi, 'rate' => $rate ];
                }
            }
        }
        
        // Sort plans by month duration (Ascending) for better UX
        usort( $plans, function( $a, $b ) {
            return $a['months'] - $b['months'];
        });

        return $plans;
    }

    /**
     * Helper: Calculate Down Payment
     *
     * Determines the down payment amount based on percentage or fixed value settings.
     * Priority Logic: Percentage > Fixed Amount.
     *
     * @param float $price The product price.
     * @param array $settings The plugin settings.
     * @return array ['amount' => float, 'label' => string]
     */
    private static function calc_down_payment( $price, $settings ) {
        $pct = isset( $settings['down_percent'] ) ? floatval( $settings['down_percent'] ) : 0;
        $fix = isset( $settings['down_fixed'] ) ? floatval( $settings['down_fixed'] ) : 0;
        
        $amount = 0.0;
        $label  = '';

        // Priority 1: Percentage Down Payment
        if ( $pct > 0 ) {
            $amount = $price * ( $pct / 100.0 );
            $label  = sprintf( __( 'پیش‌پرداخت (%s%%)', 'rs-installments' ), $pct );
        } 
        // Priority 2: Fixed Amount Down Payment
        elseif ( $fix > 0 ) {
            // Ensure down payment doesn't exceed product price
            $amount = min( $price, $fix );
            $label  = __( 'پیش‌پرداخت', 'rs-installments' );
        }

        return [
            'amount' => $amount,
            'label'  => $label
        ];
    }

    /**
     * Template Loader
     *
     * Locates and includes the template file.
     * Supports overriding templates via the theme folder for developer flexibility.
     * Search Order:
     * 1. Child Theme: /rs-installments/{file}
     * 2. Parent Theme: /rs-installments/{file}
     * 3. Plugin Default: /templates/{file}
     *
     * @param string $file The template filename (e.g., 'box.php').
     * @param array $vars Associative array of variables to pass to the view.
     */
    public static function template( $file, $vars = [] ) {
        // Paths to search for the template
        $paths = [
            trailingslashit( get_stylesheet_directory() ) . 'rs-installments/' . $file,
            trailingslashit( get_template_directory() )   . 'rs-installments/' . $file,
            RSIP_PATH . 'templates/' . $file,
        ];
        
        // Extract variables to local scope so they can be accessed simply as $varname
        extract( $vars );
        
        // Loop through paths and include the first one found
        foreach ( $paths as $p ) {
            if ( file_exists( $p ) ) {
                include $p;
                return;
            }
        }

        // Fallback or error logging could go here if template is missing
    }
}
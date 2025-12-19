/**
 * RS Installments - Ultimate Hunter Injector
 * Version: 2.1.0 (Fix for "It didn't work")
 */

(function($) {
    'use strict';

    const RSIP = {
        config: {},
        interval: null,
        attempts: 0,
        maxAttempts: 40, // 40 * 100ms = 4 seconds aggressive search

        init: function() {
            if (typeof RSIP_DATA !== 'undefined') {
                this.config = RSIP_DATA;
            }

            // Start the hunt immediately
            this.startHunting();
            
            // Listen for WooCommerce variation changes
            this.bindEvents();
        },

        startHunting: function() {
            const self = this;
            // Check every 100ms
            this.interval = setInterval(function() {
                self.attempts++;
                const success = self.tryInject();
                
                // Stop if successful or timeout
                if (success || self.attempts >= self.maxAttempts) {
                    clearInterval(self.interval);
                    if(!success) console.log('RSIP: Could not auto-inject box after 4 seconds.');
                }
            }, 100);
        },

        tryInject: function() {
            // 1. Find the Source Box (Generated in Footer)
            const $wrapper = $('#rsip-autobox-wrapper');
            let $box = $wrapper.find('.rsip-box');
            
            // If wrapper is gone, maybe we already moved it? Check visibility.
            if (!$wrapper.length) {
                if ($('.rsip-box:visible').length > 0) return true; // Already done
                // If box exists but hidden elsewhere
                $box = $('.rsip-box').first();
            }

            if (!$box.length) return false; // Source not ready yet

            // 2. Find the Target Destination
            let $target = $();
            let method = 'insertAfter'; // default method

            // Priority A: User Selector (e.g., .elementor-widget-container)
            if (this.config.target_selector) {
                try {
                    $target = $(this.config.target_selector).first();
                    // If user selected a container (like a div), usually we want to append inside it
                    if ($target.length) method = 'appendTo'; 
                } catch(e) {}
            }

            // Priority B: Specific Class
            if (!$target.length && this.config.target_class) {
                $target = $('.' + this.config.target_class).first();
            }

            // Priority C: Intelligent Fallbacks (High positions)
            if (!$target.length) {
                // Try 1: After Price (Standard WC)
                $target = $('.summary .price').first();
                method = 'insertAfter';
                
                // Try 2: After Title (If price is missing/hidden)
                if (!$target.length) {
                    $target = $('.product_title').first();
                    method = 'insertAfter';
                }
                
                // Try 3: Before Add to Cart Form
                if (!$target.length) {
                    $target = $('form.cart').first();
                    method = 'insertBefore';
                }
            }

            // 3. Execute Move
            if ($target.length) {
                if (method === 'appendTo') {
                    $box.appendTo($target);
                } else if (method === 'insertBefore') {
                    $box.insertBefore($target);
                } else {
                    $box.insertAfter($target);
                }

                // Force Visibility
                $wrapper.remove(); // Kill the wrapper
                $box.css({
                    'display': 'block',
                    'opacity': 1,
                    'visibility': 'visible',
                    'margin-top': '15px',
                    'margin-bottom': '15px'
                });
                
                return true; // Success!
            }

            return false; // Keep trying
        },

        formatMoney: function(amount) {
            let n = Math.round(parseFloat(amount));
            let formatted = n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            const symbol = this.config.currency_symbol || '';
            return this.config.is_rtl ? (formatted + ' ' + symbol) : (symbol + formatted);
        },

        calculate: function($box, currentPrice) {
            const configRaw = $box.attr('data-rsip-config');
            if (!configRaw) return;
            
            const settings = JSON.parse(configRaw);
            const plans = settings.plans || [];
            const rounding = parseInt(settings.rounding || 0);
            
            let downAmount = 0;
            if (settings.down_pct > 0) {
                downAmount = currentPrice * (settings.down_pct / 100);
            } else if (settings.down_fix > 0) {
                downAmount = Math.min(currentPrice, settings.down_fix);
            }
            
            const principal = Math.max(0, currentPrice - downAmount);
            
            $box.find('.rsip-price-display').text( this.formatMoney(currentPrice) );
            
            const $downRow = $box.find('.rsip-row-down');
            if (downAmount > 0) {
                $box.find('.rsip-down-display').text( this.formatMoney(downAmount) );
                $downRow.slideDown();
            } else {
                $downRow.slideUp();
            }

            $box.find('.rsip-plan-item').each((i, el) => {
                const $row = $(el);
                const months = parseInt($row.data('months'));
                const plan = plans.find(p => parseInt(p.months) === months);
                if (!plan) return;

                const rate = parseFloat(plan.rate);
                let monthly = 0, total = 0;

                if (settings.type === 'apr') {
                    const r = rate / 100;
                    if (r <= 0) monthly = principal / months;
                    else {
                        const pow = Math.pow(1 + r, months);
                        monthly = (pow === 1) ? principal/months : (principal * r * pow) / (pow - 1);
                    }
                } else {
                    const totalInterestRate = (rate * months) / 100.0;
                    monthly = (principal * (1 + totalInterestRate)) / months;
                }

                if (rounding > 0) monthly = Math.round(monthly / rounding) * rounding;
                total = downAmount + (monthly * months);

                $row.find('.rsip-monthly-val').text( RSIP.formatMoney(monthly) );
                $row.find('.rsip-total-val').text( RSIP.formatMoney(total) );
            });
        },

        bindEvents: function() {
            const self = this;
            $(document).on('found_variation', 'form.variations_form', function(event, variation) {
                const $box = $('.rsip-box');
                if (variation.display_price !== undefined && variation.display_price !== '') {
                    $box.addClass('loading');
                    self.calculate($box, variation.display_price);
                    setTimeout(() => $box.removeClass('loading'), 200);
                }
            });

            $(document).on('reset_data', 'form.variations_form', function() {
                const $box = $('.rsip-box');
                const basePrice = parseFloat($box.attr('data-base-price'));
                if (basePrice) {
                    $box.addClass('loading');
                    self.calculate($box, basePrice);
                    setTimeout(() => $box.removeClass('loading'), 200);
                }
            });
        }
    };

    $(document).ready(function() {
        RSIP.init();
    });

})(jQuery);
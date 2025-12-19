(function($){
  'use strict';

  const RSIP = {
      config: {},
      
      init: function() {
          // Read localized data
          if (typeof RSIP_DATA !== 'undefined') {
              this.config = RSIP_DATA;
          }
          
          this.moveBox();
          this.bindEvents();
      },

      // Helper: Format number like WooCommerce
      formatMoney: function(amount) {
          let n = parseFloat(amount).toFixed(0);
          // Simple thousand separator for display
          let formatted = n.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
          // Add currency symbol (simple append/prepend based on simple check or config)
          // Ideally we should use wc_price_format but that's complex in pure JS.
          // Using symbol passed from PHP
          const symbol = this.config.currency_symbol || '';
          return this.config.is_rtl ? (formatted + ' ' + symbol) : (symbol + formatted);
      },

      // Logic: Calculate payment details based on config & plans
      calculate: function($box, currentPrice) {
          const configRaw = $box.attr('data-rsip-config');
          if(!configRaw) return;
          
          const settings = JSON.parse(configRaw);
          const plans    = settings.plans || [];
          const rounding = parseInt(settings.rounding || 0);
          
          // Calc Down Payment
          let downAmount = 0;
          if (settings.down_pct > 0) {
              downAmount = currentPrice * (settings.down_pct / 100);
          } else if (settings.down_fix > 0) {
              downAmount = Math.min(currentPrice, settings.down_fix);
          }
          
          const principal = Math.max(0, currentPrice - downAmount);
          
          // Update UI: Price & Down Payment
          $box.find('.rsip-price-display').html( this.formatMoney(currentPrice) );
          if(downAmount > 0) {
              $box.find('.rsip-down-display').html( this.formatMoney(downAmount) );
              $box.find('.rsip-row-down').show();
          } else {
              $box.find('.rsip-row-down').hide();
          }

          // Loop through each plan item in DOM and update
          $box.find('.rsip-plan-item').each((i, el) => {
              const $row = $(el);
              const months = parseInt($row.data('months'));
              
              // Find matching plan config
              const plan = plans.find(p => parseInt(p.months) === months);
              if (!plan) return;

              const rate = parseFloat(plan.rate);
              let monthly = 0;
              let total = 0;

              if (settings.type === 'apr') {
                  // APR Calculation
                  const r = rate / 100;
                  if (r <= 0) {
                      monthly = principal / months;
                  } else {
                      const pow = Math.pow(1 + r, months);
                      monthly = (principal * r * pow) / (pow - 1);
                  }
              } else {
                  // Markup Calculation (matches PHP logic)
                  const totalWithMarkup = principal * (rate > 0 ? (1 + rate / 100) : 1);
                  monthly = totalWithMarkup / months;
              }

              // Apply Rounding
              if (rounding > 0) {
                  monthly = Math.round(monthly / rounding) * rounding;
              }
              
              total = downAmount + (monthly * months);

              // Update DOM
              $row.find('.rsip-monthly-val').html( RSIP.formatMoney(monthly) );
              $row.find('.rsip-total-val').html( RSIP.formatMoney(total) );
          });
      },

      moveBox: function() {
          if (!this.config.auto_inject) return;
          
          const $wrapper = $('#rsip-autobox-wrapper');
          if (!$wrapper.length) return;
          
          const $box = $wrapper.find('.rsip-box');
          
          // Find target
          let $target = $();
          if (this.config.target_selector) {
              $target = $(this.config.target_selector).first();
          }
          if (!$target.length && this.config.target_class) {
              $target = $('.' + this.config.target_class).first();
          }
          
          // Fallback: Entry Summary
          if (!$target.length) {
              $target = $('.summary.entry-summary, .product .summary').first();
              if ($target.length) {
                  $box.appendTo($target); // Append to bottom of summary
              }
          } else {
              $box.insertAfter($target); // Insert after found price/element
          }
          
          $wrapper.remove(); // Clean up wrapper
          $box.show();
      },

      bindEvents: function() {
          // Listen to WooCommerce Variation Changes
          // 'found_variation' passes (event, variation)
          $('form.variations_form').on('found_variation', function(event, variation) {
              const price = variation.display_price; // WC provides numeric price here!
              const $box = $('.rsip-box');
              
              if (price && $box.length) {
                  $box.addClass('loading');
                  RSIP.calculate($box, price);
                  $box.removeClass('loading');
              }
          });

          // Listen to Reset Variation
          $('form.variations_form').on('reset_data', function() {
              // Reset to base price (stored in data-base-price on load)
              const $box = $('.rsip-box');
              const basePrice = parseFloat($box.attr('data-base-price'));
              if (basePrice) {
                  RSIP.calculate($box, basePrice);
              }
          });

          // Listen to Quantity Change (Optional: if total price should multiply by qty)
          // Usually installments are calculated Per Unit, but if you want Total Cart Value logic:
          /*
          $('input.qty').on('change', function() {
               // Logic to multiply price by qty if needed
          });
          */
      }
  };

  // Run on DOM Ready
  $(function(){
      RSIP.init();
  });

})(jQuery);
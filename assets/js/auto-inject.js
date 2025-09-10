jQuery(function($){
  function parsePrice(text){
    if(!text) return 0;
    var n = (text+'').replace(/[^0-9.,]/g,'').replace(/,/g,'');
    var f = parseFloat(n);
    return isNaN(f) ? 0 : f;
  }
  function roundTo(val, step){
    step = parseInt(step||0,10);
    if(!step) return val;
    return Math.round(val/step)*step;
  }
  function recalc(box){
    var type   = box.data('type');
    var rate   = parseFloat(box.data('rate')||0); // monthly %
    var months = (box.data('months')+'').split(',').map(function(x){return parseInt(x,10)||0;}).filter(Boolean);
    var base   = parseFloat(box.data('base-price')||0);
    var down   = parseFloat(box.data('down-amount')||0);
    var principal = Math.max(0, base - down);

    // detect rounding from PHP by reading a hidden style? Not passed. Optional improvement.

    box.find('.rs-installments-item').each(function(){
      var li = $(this);
      var m  = parseInt(li.data('months'),10)||0;
      if(!m) return;

      var monthly = 0, total = 0;
      if(type === 'apr'){
        var r = Math.max(0, rate)/100;
        if(r === 0){ monthly = principal / m; }
        else {
          var pow = Math.pow(1+r, m);
          monthly = principal * r * pow / (pow - 1);
        }
      } else {
        var total_with_markup = principal * (rate>0 ? (1 + rate/100) : 1);
        monthly = total_with_markup / m;
      }
      total = down + (monthly * m);

      li.find('.rs-monthly').text( wc_price(monthly) );
      li.find('.rs-total').text( wc_price(total) );
    });
  }

  // Simple wc_price reproduction (approx) using WooCommerce price on page as format reference
  function wc_price(amount){
    // Try to read last price element and mimic separators/symbol
    var ref = $('.summary .price .amount').last().text();
    var symbol = (ref.match(/[^\d\s.,]+/)||[''])[0] || '';
    var num = Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return symbol ? (num + ' ' + symbol) : num;
  }

  function moveBox(){
    var box = $('#rsip-autobox');
    if(!box.length) return;

    var targetSel = (window.RSIP_SETTINGS && RSIP_SETTINGS.target_selector) ? RSIP_SETTINGS.target_selector : '';
    var target = targetSel ? $(targetSel).first() : $();
    if(!target.length && window.RSIP_SETTINGS && RSIP_SETTINGS.target_class){
      target = $('.' + RSIP_SETTINGS.target_class).first();
    }
    if(target.length){
      box.insertAfter(target).show();
    } else {
      var fb = $('.summary.entry-summary, .product .summary').first();
      if(fb.length) box.appendTo(fb).show();
      else box.show();
    }

    // First recalc using current DOM price if available
    var pEl = $('.summary .price .amount').last();
    var boxInner = box.find('.rs-installments-box');
    if(pEl.length && boxInner.length){
      var price = parsePrice(pEl.text());
      if(price>0){
        boxInner.attr('data-base-price', price);
        recalc(boxInner);
        boxInner.find('.rs-base-price').text( wc_price(price) );
      }
    }
  }

  // Live update on variation/qty change
  function bindLive(){
    $('form.variations_form')
      .on('found_variation woocommerce_variation_has_changed change', function(){
        var pEl = $('.summary .price .amount').last();
        var box = $('.rs-installments-box').first();
        if(pEl.length && box.length){
          var price = parsePrice(pEl.text());
          if(price>0){
            box.attr('data-base-price', price);
            recalc(box);
            box.find('.rs-base-price').text( wc_price(price) );
          }
        }
      });
    $('input.qty').on('change', function(){
      var qty = parseFloat($(this).val()||1);
      if(isNaN(qty) || qty<=0) qty = 1;
      var baseEl = $('.summary .price .amount').last();
      var base = parsePrice(baseEl.text());
      var box = $('.rs-installments-box').first();
      if(box.length && base>0){
        var total = base * qty;
        box.attr('data-base-price', total);
        recalc(box);
        box.find('.rs-base-price').text( wc_price(total) );
      }
    });
  }

  moveBox();
  bindLive();
});

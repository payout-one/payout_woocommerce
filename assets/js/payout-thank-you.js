jQuery(document).ready(function($) {
    jQuery('.woocommerce-order').addClass('processing').block({
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: .25
        }
    });

    jQuery('.woocommerce-order').css('opacity', '1');
    jQuery('.woocommerce-order > *').css('opacity', '0');

    var counter = 0;
    var checkingTime = 8;
    var checkInterval = 1000;
    var oid = payout_thank_you_data.order_id;

    params = {
        action: 'checkOrderStatus',
        oid: oid,
    }

    var checkingInterval = setInterval(function() {
        $.ajax({
            type: "POST",
            dataType: "html",
            url: payout_thank_you_data.ajax_url,
            data: params,
            success: function(data) {

                if ((data == "succeeded") || data == "processing") {
                    clearInterval(checkingInterval);
                    jQuery('.woocommerce-order').addClass('done').unblock();
                    jQuery('.woocommerce-order > *').css('opacity', '1');
                }

                //console.log(data);
                counter++;

                // Redirect to payment URL if response is different than "succeeded" or "processing"
                if ((counter > checkingTime)) {
                    clearInterval(checkingInterval);
                    window.location.replace(payout_thank_you_data.payment_url);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Cannot retrieve data.');
            }
        });
    }, checkInterval);
});
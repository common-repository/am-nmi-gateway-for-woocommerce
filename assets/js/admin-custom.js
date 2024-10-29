//alert(123);
jQuery(function ($) {
    'use strict';

    /**
     * Object to handle NMI admin functions.
     */
    let am_nmi_admin = {
        isAPIKey: function () {
            return $('#woocommerce_am-nmi-gateway-for-woocommerce_testmode').is(':checked');
        },

        /**
         * Initialize.
         */
        init: function () {
            $(document.body).on('change', '#woocommerce_am-nmi-gateway-for-woocommerce_testmode', function () {
                let liveusername = $('#woocommerce_am-nmi-gateway-for-woocommerce_liveusername').parents('tr').eq(0),
                    livepassword = $('#woocommerce_am-nmi-gateway-for-woocommerce_livepassword').parents('tr').eq(0),
                    testusername = $('#woocommerce_am-nmi-gateway-for-woocommerce_testusername').parents('tr').eq(0),
                    testpassword = $('#woocommerce_am-nmi-gateway-for-woocommerce_testpassword').parents('tr').eq(0);

                if ($(this).is(':checked')) {
                    testusername.show();
                    testpassword.show();
                    liveusername.hide();
                    livepassword.hide();
                } else {
                    testusername.hide();
                    testpassword.hide();
                    liveusername.show();
                    livepassword.show();
                }
            });

            $('#woocommerce_am-nmi-gateway-for-woocommerce_testmode').change();
        }
    };

    am_nmi_admin.init();
});
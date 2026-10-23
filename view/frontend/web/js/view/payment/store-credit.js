define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'tonyapps_b2b_credit',
        component: 'Tonyapps_B2b/js/view/payment/method-renderer/store-credit-method'
    });

    return Component.extend({});
});

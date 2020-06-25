/**
 * Remove local storage address
 */
define([
    'jquery',
    'Magento_Checkout/js/checkout-data'
], function ($, checkoutData){
    'use strict';
    $.widget('mage.removelocal', {

        _init: function () {
            if(checkoutData.getShippingAddressFromData() != null)
            {
                checkoutData.setShippingAddressFromData(null);
            }
            if(checkoutData.getBillingAddressFromData() != null)
            {
                checkoutData.setBillingAddressFromData(null);
            }
        }
    });
    return $.mage.removelocal;
});
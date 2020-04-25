/**
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 * @author   Mohandass Unnikrishnan <mohandass.unnikrishnan@diconium.com>
 */

/**
 * Mixin added to update the Billing Addresse
 * 
 */

define([
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Ui/js/model/messageList'
], 
function (setBillingAddressAction, globalMessageList) {
    'use strict';

    var mixinBilling = {

        updateAddresses: function () {
            setBillingAddressAction(globalMessageList);
        }
    };

    return function (target) {
        return target.extend(mixinBilling);
    };
});
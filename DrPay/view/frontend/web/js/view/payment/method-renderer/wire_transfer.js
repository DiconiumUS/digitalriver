/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/

define([
    'jquery',
    'underscore',
    'Magento_Checkout/js/view/payment/default',
	'Magento_Paypal/js/action/set-payment-method',
], function (
    $,
    _,
    Component,
	setPaymentMethodAction
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Digitalriver_DrPay/payment/wire_transfer',
            code: 'drpay_wire_transfer'
		},
        redirectAfterPlaceOrder: false,
        /** Redirect to custom controller for payment */
        afterPlaceOrder: function () {
            $.mage.redirect(window.checkoutConfig.payment.drpay_wire_transfer.redirect_url);
            return false;
        },        
        /**
         * Get payment name
         *
         * @returns {String}
         */
        getCode: function () {
            return this.code;
        },
        
        /**
         * Get payment description
         *
         * @returns {String}
         */
        getInstructions: function () {		
            return window.checkoutConfig.payment.instructions[this.getCode()];
        },

        /**
         * Get payment title
         *
         * @returns {String}
         */
        getTitle: function () {
            return window.checkoutConfig.payment[this.getCode()].title;
        },

        /**
        * Get Digitalriver js url
        * 
        * @returns {String}
        */
        getJsUrl: function () {
            return window.checkoutConfig.payment[this.getCode()].js_url;
        },
        /**
        * Get Digitalrive public key
        * 
        * @returns {String}
        */
        getPublicKey: function () {
            return window.checkoutConfig.payment[this.getCode()].public_key;
        },

        /**
         * Check if payment is active
         *
         * @returns {Boolean}
         */
        isActive: function () {
            var active = this.getCode() === this.isChecked();

            this.active(active);

            return active;
        },
		placeOrder: function () {
			//update payment method information if additional data was changed
//			this.selectPaymentMethod();
			setPaymentMethodAction(this.messageContainer).done(
				function () {
					$.mage.redirect(window.checkoutConfig.payment.drpay_wire_transfer.redirect_url);
				}
			);

			return false;
		},
        radioInit: function(){
            $(".payment-methods input:radio:first").prop("checked", true).trigger("click");
        }        
    });
});

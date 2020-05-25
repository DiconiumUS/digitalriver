/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
/*browser:true*/

define(
    [
    'jquery',
    'underscore',
    'Magento_Checkout/js/view/payment/default',
	'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/place-order'
    ], function (
        $,
        _,
        Component,
		quote,
		placeOrderAction
    ) {
        'use strict';

        return Component.extend(
            {
                defaults: {
                    template: 'Digitalriver_DrPay/payment/creditcard',
                    code: 'drpay_creditcard'
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
                radioInit: function () {
                    $(".payment-methods input:radio:first").prop("checked", true).trigger("click");
                },			

				/**
				 * @return {*}
				 */
				getPlaceOrderDeferredObject: function () {
					if(jQuery("#creditcard-address").css("display") != "none"){
						quote.billingAddress(null);
					}
					return $.when(
						placeOrderAction(this.getData(), this.messageContainer)
					);
				}  
            }
        );
    }
);

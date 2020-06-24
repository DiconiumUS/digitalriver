/*global alert*/
define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',	
	'Magento_Checkout/js/model/step-navigator',
	'Magento_Checkout/js/model/cart/totals-processor/default',
	'Magento_Checkout/js/model/cart/cache'
], function ($, wrapper, quote, stepNavigator, defaultTotal, cartCache) {
    'use strict';

    return function (setShippingInformationAction) {

        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
			originalAction().done(
				function () {					
					cartCache.set('totals',null);
					defaultTotal.estimateTotals();
					stepNavigator.next();
				}
			);
            return this;
        });
    };
});

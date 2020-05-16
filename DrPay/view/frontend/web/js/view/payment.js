/**
 * Mixin added to update the Payment information
 * 
 */

define([
	'jquery',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/checkout-data',
	'Magento_Customer/js/model/address-list',
	'Magento_Checkout/js/action/select-billing-address',
	'Magento_Checkout/js/action/set-billing-address',
    'Magento_Checkout/js/action/create-billing-address',
    'Magento_Ui/js/model/messageList'
], 
function ($, quote, checkoutData, addressList, selectBillingAddress, setBillingAddressAction, createBillingAddress, globalMessageList) {
    'use strict';
    var mixin = {
		initialize: function () {
            this._super();
			if(!quote.billingAddress() && quote.isVirtual()){
				var selectedBillingAddress,
					newCustomerBillingAddressData;

				if (!checkoutData.getBillingAddressFromData() &&
					window.checkoutConfig.billingAddressFromData
				) {
					checkoutData.setBillingAddressFromData(window.checkoutConfig.billingAddressFromData);
				}

				selectedBillingAddress = checkoutData.getSelectedBillingAddress();
				newCustomerBillingAddressData = checkoutData.getNewCustomerBillingAddress();
				if (selectedBillingAddress) {
					if (selectedBillingAddress === 'new-customer-billing-address' && newCustomerBillingAddressData) {
						selectBillingAddress(createBillingAddress(newCustomerBillingAddressData));
						setBillingAddressAction(globalMessageList);
					} else {
						addressList.some(function (address) {
							if (selectedBillingAddress === address.getKey()) {
								selectBillingAddress(address);
								setBillingAddressAction(globalMessageList);
							}
						});
					}
				}else{
					var isBillingAddressInitialized = addressList.some(function (address) {
						if (address.isDefaultBilling()) {
							selectBillingAddress(address);
							setBillingAddressAction(globalMessageList);
						}
					});
				}
			};
            return this;
		}
	};
	return function (target) {
		return target.extend(mixin);
	};
});
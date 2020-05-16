/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/billing-address': {
                'Digitalriver_DrPay/js/view/billing-address': true
            },
            'Magento_Checkout/js/view/payment': {
                'Digitalriver_DrPay/js/view/payment': true
            },
        }
    },    
    map: {
        '*': {
            'Magento_Checkout/js/model/step-navigator': 'Digitalriver_DrPay/js/model/step-navigator'
        }
    }
};


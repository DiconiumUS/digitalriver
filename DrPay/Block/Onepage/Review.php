<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Block\Onepage;

use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * One page checkout review page
 *
 * @api
 */
class Review extends \Magento\Checkout\Block\Onepage 
{
    /**
     * @var \Magento\Framework\Data\Form\FormKey
     */
    protected $formKey;

    /**
     * @var bool
     */
    protected $_isScopePrivate = false;
   
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;
    
    /**
     * @var \Digitalriver\DrPay\Helper\Data
     */
    protected $helper;
    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $countryFactory;
    /**
     * @var \Magento\Framework\Locale\CurrencyInterface
     */
    protected $localeCurrency;        
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;    
    
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     * @param \Magento\Checkout\Model\CompositeConfigProvider $configProvider
     * @param array $layoutProcessors
     * @param array $data
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     * @param \Magento\Framework\Serialize\SerializerInterface $serializerInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Digitalriver\DrPay\Helper\Data $helper
     * @param \Magento\Framework\Locale\CurrencyInterface $localeCurrency
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * 
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Checkout\Model\CompositeConfigProvider $configProvider,
        array $layoutProcessors = [],
        array $data = [],
        \Magento\Framework\Serialize\Serializer\Json $serializer = null,
        \Magento\Framework\Serialize\SerializerInterface $serializerInterface = null,            
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context, $formKey, $configProvider, $layoutProcessors, $data, $serializer, $serializerInterface);
        
        $this->formKey          = $formKey;
        $this->_isScopePrivate  = true;
        $this->_checkoutSession = $checkoutSession;        
        $this->helper           = $helper;
        $this->countryFactory   = $countryFactory;
        $this->localeCurrency   = $localeCurrency;        
        $this->messageManager   = $messageManager;        
    }

    /**
     * Prepares block data
     *
     * @return void
     */
    public function getQuoteData()
    {
        $quoteData  = [];
        $quoteItems = [];
        $isVirtual  = null;
        $countryNameBilling  = null;
        $countryNameShipping = null;        
                
        try {
            $quote = $this->_checkoutSession->getQuote();
            
            if($quote && $quote->getId() && $quote->getIsActive() && $this->getRequest()->getParam('sourceId')) {
                $currencySymbol = $this->getCurrencySymbol();
                $paymentMethod  = $this->_checkoutSession->getSelectedPaymentMethod();
                // Get country name based on country_id from billing and shipping addresses
                $country        = $this->countryFactory->create()->loadByCode($quote->getBillingAddress()->getCountryId());

                if (!empty($country)) {
                    $countryNameBilling = $country->getName();
                } // end: if

                $isVirtual = $quote->getIsVirtual(); // 0- If not virtual, 1- if virtual

                if(empty($isVirtual)) {
                    $countryShipping    = $this->countryFactory->create()->loadByCode($quote->getShippingAddress()->getCountryId());

                    if (!empty($countryShipping)) {
                        $countryNameShipping = $countryShipping->getName();
                    } // end: if
                } // end: if

                $quoteData = [
                    'formKey'               => $this->formKey->getFormKey(),
                    'paymentMethod'         => $paymentMethod,
                    'sourceId'              => $this->getRequest()->getParam('sourceId'),
                    'isVirtual'             => $isVirtual,
                    'shippingAddress'       => (empty($isVirtual)) ? $quote->getShippingAddress()->getData() : null,
                    'billingAddress'        => $quote->getBillingAddress()->getData(),
                    'countryNameBilling'    => $countryNameBilling,
                    'countryNameShipping'   => $countryNameShipping,
                    'currencySymbol'        => $currencySymbol,
                    'cartData'              => [
                        'subTotal'              => $quote->getSubtotal(),
                        'orderTotal'            => $quote->getGrandTotal(),
                        'subtotalWithDiscount'  => $quote->getSubtotalWithDiscount(),
                        'shippingAmount'        => (empty($isVirtual)) ? $quote->getShippingAddress()->getShippingAmount() : 0,
                        'shippingDescription'   => (empty($isVirtual)) ? $quote->getShippingAddress()->getShippingDescription() : null,
                        'taxAmount'             => (empty($isVirtual)) ? $quote->getShippingAddress()->getTaxAmount() : $quote->getBillingAddress()->getTaxAmount(),
                    ],
                    'placeOrderUrl'         => $this->getPlaceOrderUrl($paymentMethod)
                ];

                $checkoutConfigs = json_decode($this->getSerializedCheckoutConfig(), TRUE);
                $productOptionsAndImage = [];

                array_walk($checkoutConfigs['quoteItemData'], function($value, $key) use(&$productOptionsAndImage) {
                    $productOptionsAndImage[$value['product']['entity_id']]['thumbnail'] = $value['thumbnail'];

                    if(isset($value['options']) && !empty($value['options'])) {
                        $productOptionsAndImage[$value['product']['entity_id']]['options'] = $value['options'];
                    } // end: if
                });

                // Get quote items details
                $itemsCount = 0;
                $qtyCount   = 0;
                foreach($quote->getItems() as $item) {
                    $quoteItems[] = [
                        'productId'         => $item->getProductId(),
                        'name'              => $item->getName(),
                        'sku'               => $item->getSku(),
                        'quantity'          => $item->getQty(),
                        'price'             => $item->getPrice(),
                        'rowTotal'          => $item->getRowTotal(),
                        'productOptions'    => (isset($productOptionsAndImage[$item->getProductId()]) && isset($productOptionsAndImage[$item->getProductId()]['options'])) ? $productOptionsAndImage[$item->getProductId()]['options'] : null,
                        'thumbnail'         => (isset($productOptionsAndImage[$item->getProductId()]) && isset($productOptionsAndImage[$item->getProductId()]['thumbnail'])) ? $productOptionsAndImage[$item->getProductId()]['thumbnail'] : null
                    ];
                    $qtyCount += $item->getQty();
                    $itemsCount++;
                } // end: foreach

                if(!empty($quoteItems) && $itemsCount > 0) { 
                    $itemsCountText = ($qtyCount == 1) ? 'Item in Cart' : 'Items in Cart';                
                    $quoteData['qtyCount']          = $qtyCount;
                    $quoteData['itemsCount']        = $itemsCount;
                    $quoteData['itemsCountText']    = $itemsCountText;
                    $quoteData['cartData']['items'] = $quoteItems;
                } // end: if
            }
        }  catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Order Review Error : '.json_encode($le->getRawMessage()));
            $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
            $this->_redirect('checkout/cart');
            return;
        } catch (\Exception $ex) {
            $this->_logger->error('Order Review Error : '.json_encode($ex->getMessage()));
            $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
            $this->_redirect('checkout/cart');
            return;
        } // end: try// end: try
        
        return $quoteData;
    }
    
    /**
     * Get currency symbol based on current store
     * @return string
     */
    protected function getCurrencySymbol() {
        $code = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        return $this->localeCurrency->getCurrency($code)->getSymbol();
    } // end: function getCurrencySymbol
    
    /**
     * Get review order create ur based on Payment method
     * 
     * @param string
     * @return string
     */
    protected function getPlaceOrderUrl($paymentMethod) {
        $placeOrderUrl = null;
        
        if($paymentMethod == 'paypal') {
            $placeOrderUrl = 'drpay/paypal/success';
        } else if ($paymentMethod == 'klarna') {
            $placeOrderUrl = 'drpay/paypal/success';
        } else if ($paymentMethod == 'direct debit') {
            $placeOrderUrl = 'drpay/directdebit/success';
        } // end if
        
        return $placeOrderUrl;
    } // end: function getPlaceOrderUrl    
}
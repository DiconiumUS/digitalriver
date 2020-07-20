<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Block\Checkout;

class Review extends \Magento\Paypal\Block\Express\Review
{
    /**
     * Block alias fallback
     */
    const DEFAULT_TYPE = 'default';
    /**
     * Controller path
     *
     * @var string
     * @since 100.1.0
     */
    protected $_controllerPath = 'drpay/review';
    /**
     * @var \Magento\Checkout\Block\Cart\AbstractCart
     */
    protected $abstractCart;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        array $data = [],
        \Magento\Catalog\Helper\Product\ConfigurationPool $configurationPool,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->configurationPool    = $configurationPool;
        $this->checkoutSession      = $checkoutSession;
        parent::__construct($context, $taxHelper, $addressConfig, $priceCurrency, $data);
    }
    
    /**
     * Does not allow editing payment information as customer has gone through payment flow already
     *
     * @return null
     * @since 100.1.0
     */
    public function getEditUrl()
    {
        return null;
    }
    
    public function getQuote() {
        return $this->_quote;
    }
    
    public function setQuote(\Magento\Quote\Model\Quote $quote) {
        return parent::setQuote($quote);
    }
    
    public function getItemOptions($item) {
        return $this->configurationPool->getByProductType($item->getProductType())->getOptions($item);
    }
    
    public function getSessionId() {
        return $this->checkoutSession->getId();
    }
}
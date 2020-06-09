<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Plugin;

class QuoteAddressPlugin
{

    protected $drHelper;
    
    protected $scopeConfig;
    
    const XML_PATH_ENABLE_DRPAY = 'dr_settings/config/active';
    
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
         $this->drHelper= $drHelper;
         $this->scopeConfig = $scopeConfig;
    }
    /**
     * Get DrPay Module Status
     */
    public function getDrPayEnable()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(self::XML_PATH_ENABLE_DRPAY, $storeScope);
    }

    /**
     * Set shipping method
     *
     * @param  \Magento\Quote\Model\Quote\Address $subject
     * @param  string $value
     * @return $this
     */
    public function afterSetShippingAmount(
        \Magento\Quote\Model\Quote\Address $subject,
        $result,
        $value
    ) {
        $enableDrPayValue = $this->getDrPayEnable();
        if ($enableDrPayValue) {
			$quote = $subject->getQuote();
            if (!$quote->isVirtual()) {
                //Create the cart in DR
                $this->drHelper->createFullCartInDr($quote);
            }
        }
        return $result;
    }
}

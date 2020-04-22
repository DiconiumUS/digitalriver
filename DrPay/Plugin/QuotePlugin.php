<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Plugin;

class QuotePlugin
{

    protected $drHelper;
    
    protected $scopeConfig;
    
    const XML_PATH_ENABLE_DRPAY = 'dr_settings/config/active';
    
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
         $this->drHelper= $drHelper;
         $this->scopeConfig = $scopeConfig;
         $this->_logger = $logger;
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
     * Set shipping address
     *
     * @param  \Magento\Quote\Model\Quote               $subject
     * @param  \Magento\Quote\Api\Data\AddressInterface $address
     * @return $this
     */
    public function afterSetShippingAddress(
        \Magento\Quote\Model\Quote $subject,
        $result,
        $address
    ) {
        $enableDrPayValue = $this->getDrPayEnable();
        if ($enableDrPayValue) {
            if (!$subject->isVirtual()) {
                //Create the cart in DR
                //$this->drHelper->createFullCartInDr($subject);
            }
        }
        return $result;
    }

    /**
     * Set billing address.
     *
     * @param  \Magento\Quote\Model\Quote               $subject
     * @param  \Magento\Quote\Api\Data\AddressInterface $address
     * @return $this
     */
    public function afterSetBillingAddress(
        \Magento\Quote\Model\Quote $subject,
        $result,
        $address
    ) {
        $enableDrPayValue = $this->getDrPayEnable();
        if ($enableDrPayValue) {
            if ($subject->isVirtual()) {
                //Create the cart in DR
                $this->drHelper->createFullCartInDr($subject);
            }
        }
        return $result;
    }
}

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
    
    protected $_customerFactory;
    
    protected $_addressFactory;
    
    const XML_PATH_ENABLE_DRPAY = 'dr_settings/config/active';
    
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory
    ) {
         $this->drHelper= $drHelper;
         $this->scopeConfig = $scopeConfig;
         $this->_customerFactory = $customerFactory;
         $this->_addressFactory = $addressFactory;
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
			$address =  $quote->getBillingAddress();
			if($address->getCustomerId() && $address->getCity() == null) {
			   $customer = $this->_customerFactory->create()->load($address->getCustomerId());
			   $billingAddressId = $customer->getDefaultBilling();
			   if($billingAddressId) {
					$billingAddress = $this->_addressFactory->create()->load($billingAddressId);
					$address->setFirstName($billingAddress->getFirstname());
					$address->setLastName($billingAddress->getLastname());
					$address->setMiddlename($billingAddress->getMiddlename());
					$address->setCompany($billingAddress->getCompany());
					$address->setTelephone($billingAddress->getTelephone());
					$address->setStreet($billingAddress->getStreet());
					$address->setCity($billingAddress->getCity());
					$address->setPostCode($billingAddress->getPostCode());
					$address->setRegionId($billingAddress->getRegionId());
					$address->setRegion($billingAddress->getRegion());
					$address->setCountryId($billingAddress->getCountryId());
			   }
			}
            //if (!$quote->isVirtual()) {
                //Create the cart in DR
                $this->drHelper->createFullCartInDr($quote);
            //}
        }
        return $result;
    }
}

<?php
namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
class SaveAddressAfterObserver implements ObserverInterface
{
   /**
    * Constructs the observer
    * 
    * @param \Magento\Checkout\Model\Session $session
    */
    public function __construct(
        \Magento\Checkout\Model\Session $session
    ) {
        $this->session = $session;
    }

    /**
     * Update quote address
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $customerAddress = $observer->getCustomerAddress();
        $accessToken = $this->session->getDrAccessToken();
        if ($customerAddress && $accessToken) {
            $quote = $this->session->getQuote();
            if ($customerAddress->getDefaultBilling() == true) {
                $quoteBillingAddress = $quote->getBillingAddress();
                $quoteBillingAddress->setCity($customerAddress->getCity());
                $quoteBillingAddress->setRegion($customerAddress->getRegion());
                $quoteBillingAddress->setRegionId($customerAddress->getRegionId());
                $quoteBillingAddress->setCountryId($customerAddress->getCountryId());
                $quoteBillingAddress->setPostcode($customerAddress->getPostcode());
                $quoteBillingAddress->save();
            }
            if ($customerAddress->getDefaultShipping() == true) {
                $quoteShippingAddress = $quote->getShippingAddress();
                $quoteShippingAddress->setCity($customerAddress->getCity());
                $quoteShippingAddress->setRegion($customerAddress->getRegion());
                $quoteShippingAddress->setRegionId($customerAddress->getRegionId());
                $quoteShippingAddress->setCountryId($customerAddress->getCountryId());
                $quoteShippingAddress->setPostcode($customerAddress->getPostcode());
                $quoteShippingAddress->save();
            }
        }
    }
}

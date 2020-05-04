<?php
namespace Digitalriver\DrPay\Plugin\Sales\Order\Email\Container;

class OrderIdentityPlugin
{
    /**
     *
     * @var \Magento\Checkout\Model\Session $checkoutSession
     */
    protected $checkoutSession;
    
    /**
     * Constructs the OrderIdentityPlugin
     * 
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(\Magento\Checkout\Model\Session $checkoutSession) {
        $this->checkoutSession = $checkoutSession;
    }
    
    public function aroundIsEnabled(\Magento\Sales\Model\Order\Email\Container\OrderIdentity $subject, callable $proceed) {
        $returnValue = $proceed();
        $returnValue = false;
        $forceOrderMailSentOnSuccess = $this->checkoutSession->getForceOrderMailSentOnSuccess();
        if (isset($forceOrderMailSentOnSuccess) && $forceOrderMailSentOnSuccess) {
            $returnValue = true;
        }

        return $returnValue;
    }
}

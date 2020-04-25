<?php
/**
 * DrPay Observer
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
 
namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;

/**
 *  CreateDrOrder
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
class CreateDrOrder implements ObserverInterface
{
        /**
         * @param \Digitalriver\DrPay\Helper\Data            $helper
         * @param \Magento\Checkout\Model\Session            $session
         * @param \Magento\Store\Model\StoreManagerInterface $storeManager
         */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Checkout\Model\Session $session,
		\Digitalriver\DrPay\Model\DrConnector $drconnector,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->helper =  $helper;
        $this->session = $session;
        $this->drconnector = $drconnector;
		$this->jsonHelper = $jsonHelper;
        $this->_storeManager = $storeManager;
		$this->_eventManager = $eventManager;
    }

    /**
     * Create order
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer['order'];
        $quote = $observer['quote'];
        if ($quote->getPayment()->getMethod() == \Digitalriver\DrPay\Model\CreditCard::PAYMENT_METHOD_CREDITCARD_CODE || $quote->getPayment()->getMethod() == \Digitalriver\DrPay\Model\ApplePay::PAYMENT_METHOD_APPLE_PAY_CODE) {
            $result = $this->helper->createFullCartInDr($quote, 1);
            $accessToken = $this->session->getDrAccessToken();
            if ($this->session->getDrQuoteError()) {
                throw new CouldNotSaveException(__('Unable to Place Order'));
            } else {
                $totals = $result["cart"]["pricing"]["orderTotal"];
                $drCurrency = $totals["currency"];
                $dr_grand_total = (int)round($totals["value"]);
                $currency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
                $grand_total = (int)round($quote->getGrandTotal());
                //if($currency == $drCurrency && $grand_total == $dr_grand_total){
				$cartresult = $this->helper->getDrCart();
                $result = $this->helper->createOrderInDr($accessToken);
                if ($result && isset($result["errors"])) {
                    throw new CouldNotSaveException(__('Unable to Place Order'));
                } else {
					$this->_eventManager->dispatch('dr_place_order_success', ['order' => $order, 'quote' => $quote, 'result' => $result, 'cart_result' => $cartresult]);
                }
            }
        }
    }
}

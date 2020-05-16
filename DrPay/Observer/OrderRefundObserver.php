<?php
/**
 * @category Digitalriver
 * @package: Digitalriver_DrPay
 *
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Sales\Model\Order;
use Magento\Framework\Event\ObserverInterface;

class OrderRefundObserver implements ObserverInterface
{

    /**
     *
     * @param \Digitalriver\DrPay\Helper\Data $drHelper
     */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper
    ) {
        $this->drHelper = $drHelper;
    }

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        if ($order->getDrOrderId()) {
            $status = $this->drHelper->initiateRefundRequest($creditmemo);
            if (!$status) {
                throw new \Exception(__('There is an issue with Refund at DR side'));
            }
            return $this;
        }
    }
}

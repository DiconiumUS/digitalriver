<?php

/**
 * Cancel DR Fulfillment when entire order cancellation or partial order item cancellation is done in Magento
 * 
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 * @author   Mohandass <mohandass.unnikrishnan@diconium.com>
 *
 */

namespace Digitalriver\DrPay\Plugin\Sales\Order;

class ItemPlugin
{
    /**
     * @var \Digitalriver\DrPay\Helper\Data $drHelper
     */
    protected $helper;
    /**
     * @var \Digitalriver\DrPay\Helper\Data $drHelper
     */
    protected $logger;
    
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->drHelper = $drHelper;
        $this->_logger  = $logger;
    }

    /**
     * This function to used to get the canceled qty from $item->cancel() execution
     * \Magento\Sales\Model\Order\Item
     * @param object $subject
     * @param object $result
     * 
     * @return $result
     */
    public function afterCancel(
        \Magento\Sales\Model\Order\Item $subject,
        $result
    ) {                        
        try {
            if ($subject->getQtyCanceled() > 0) {
                $drItemId   = $subject->getDrOrderLineitemId();
                $order      = $subject->getOrder();
                $drOrderId  = $order->getDrOrderId();
                
                // DR line item id is empty for some child/parent line items
                if(!empty($drItemId) && !empty($drOrderId)) {
                    $items[$drItemId] = [
                        "requisitionID"             => $drOrderId,
                        "noticeExternalReferenceID" => $order->getIncrementId(),
                        "lineItemID"                => $drItemId,
                        "quantity"                  => $subject->getQtyCanceled()
                    ];
                    
                    $this->drHelper->cancelFulfillmentRequestToDr($items, $order);                    
                } else {
                    $this->_logger->info('afterCancel: Invalid DR Id');
                } // end: if
            } else {
                $this->_logger->info('afterCancel: Invalid Order Item Qty to Cancel');
            } // end: if
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error afterCancel: '.json_encode($le->getRawMessage()));
        } catch (\Exception $ex) {
            $this->_logger->error('Error afterCancel: '. $ex->getMessage());
        } // end: try 
        
        return $result;
    } // end: function afterCancel
}
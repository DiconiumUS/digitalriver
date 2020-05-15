<?php
/**
 * Shipment Model Register Plugin
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 * @author   Mohandass <mohandass.unnikrishnan@diconium.com>
 */

namespace Digitalriver\DrPay\Plugin\Sales\Order;

class ShipmentPlugin {   
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
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->drHelper = $drHelper;
        $this->_logger  = $logger;
    }
    
    /**
     * This function to used to get the shipped qty from register functionality
     * \Magento\Sales\Model\Order\Shipment
     * @param object $subject
     * @param object $result
     * 
     * @return $result
     */
    public function afterRegister(
        \Magento\Sales\Model\Order\Shipment $subject,
        $result
    ) {
        $items = [];
        
        if ($subject->getId()) {
            return $result;
        } // end: if
        
        try {
            foreach ($subject->getItems() as $shipmentItem) {                
                /** @var OrderItemInterface $orderItem */
                $orderItem  = $shipmentItem->getOrderItem();
                /** @var OrderInterface $order */
                $order      = $orderItem->getOrder();              

                if($shipmentItem->getQty() > 0) {
                    $lineItemId = $orderItem->getDrOrderLineitemId();
                    // Some cases, DR line item id is empty for parent products
                    if(!empty($lineItemId)) {
                        $items[$lineItemId] = [
                            "requisitionID"             => $order->getDrOrderId(),
                            "noticeExternalReferenceID" => $order->getIncrementId(),
                            "lineItemID"                => $lineItemId,
                            "quantity"                  => $orderItem->getQtyShipped()
                        ];
                    }
                }
            } // end: foreach 
            
            if(!empty($items)) {
                $this->drHelper->createFulfillmentRequestToDr($items, $subject->getOrder());
            }          
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error afterRegister : '.json_encode($le->getRawMessage()));
        } catch (\Exception $ex) {
            $this->_logger->error('Error afterRegister : '.$ex->getMessage());
        } // end: try
        
        return $result;
    } // end: function afterRegister
}

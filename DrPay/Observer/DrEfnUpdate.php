<?php

/**
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 *
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;

class DrEfnUpdate implements ObserverInterface 
{
    /**
     * Event name for Shipment Save
     */
    CONST EVENT_SHIPMENT_SAVE   = 'sales_order_shipment_save_after';
    /**
     * Event name for Invoice Save
     */
    CONST EVENT_INVOICE_SAVE    = 'sales_order_invoice_save_after';
    
    /**
     *
     * @param \Digitalriver\DrPay\Helper\Data $drHelper
     */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->drHelper = $drHelper;
        $this->_logger  = $logger;
    }

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer) {
        $items = [];
                
        try {
            $event = $observer->getEvent()->getName();
            
            if(!empty($event)) {
                if($event == self::EVENT_SHIPMENT_SAVE) {
                    $items = $this->_getShipmentDetails($observer->getEvent()->getShipment());
                } else if($event == self::EVENT_INVOICE_SAVE) {
                    $items = $this->_getInvoiceDetails($observer->getEvent()->getInvoice());
                } // end: if
            } // end: if        
        
            if(!empty($items)) {
                $this->drHelper->sendEfnToDr($items);
            } else {
                $this->_logger->info('DrEfnUpdate: No items to send to DR EFN');
            } //  end: if
        } catch (Exception $ex) {
            $this->_logger->error('DrEfnUpdate Error : '. $ex->getMessage());
        } // end: try      
    }
    
    /**
     * Collect the shipment details from observer and process
     * @param object $observer
     * 
     */
    private function _getShipmentDetails($shipmentObj) {
        $items = [];
        
        try {
            foreach ($shipmentObj->getItems() as $shipmentItem) {                
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
                    } else {
                        $this->_logger->info('_getShipmentDetails(): Invalid DR Line Item Id');
                    } // end: if
                } else {
                    $this->_logger->info('_getShipmentDetails(): Invalid Order Item Quantity');
                } // end: if
            } // end: foreach
        } catch (Exception $ex) {
            $this->_logger->error('Error from _getShipmentDetails() : '. $ex->getMessage());
        } // end: try 

        return $items;
    } // end: function _getShipmentDetails
    
    /**
     * Collect the shipment details from observer and process
     * @param object $observer
     * 
     * @return array $items
     * 
     */
    private function _getInvoiceDetails($invoiceObj) {
        $items = [];

        try {
            foreach ($invoiceObj->getItems() as $invoiceItem) {
                /** @var OrderItemInterface $orderItem */
                $orderItem  = $invoiceItem->getOrderItem();
                /** @var OrderInterface $order */
                $order      = $orderItem->getOrder();
                $isVirtual  = $orderItem->getIsVirtual();                

                if (!empty($isVirtual) && $orderItem->getQtyInvoiced() > 0) {
                    $lineItemId = $orderItem->getDrOrderLineitemId();
                    // Some cases, DR line item id is empty for parent products
                    if(!empty($lineItemId)) {
                        $items[$lineItemId] = [
                            "requisitionID"             => $order->getDrOrderId(),
                            "noticeExternalReferenceID" => $order->getIncrementId(),
                            "lineItemID"                => $lineItemId,
                            "quantity"                  => $orderItem->getQtyInvoiced()
                        ];
                    } else {
                        $this->_logger->info('_getInvoiceDetails(): Invalid DR Line Item Id');
                    } // end: if
                } else {
                    $this->_logger->info('_getInvoiceDetails(): Order Item is not virtual');
                } // end: if
            } // end: foreach
        } catch (Exception $ex) {
            $this->_logger->error('Error from _getInvoiceDetails() : '. $ex->getMessage());
        } // end: try 

        return $items;
    } // end: function _getInvoiceDetails
}

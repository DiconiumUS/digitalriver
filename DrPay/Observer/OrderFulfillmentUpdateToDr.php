<?php

/**
 * Order Invoice Register Observer
 * 
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 * @author   Mohandass <mohandass.unnikrishnan@diconium.com>
 *
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderFulfillmentUpdateToDr implements ObserverInterface 
{
    /**
     * Event name for Invoice Save
     */
    CONST EVENT_INVOICE_REGISTER = 'sales_order_invoice_register';
    /**
     *
     * @param \Digitalriver\DrPay\Helper\Data $drHelper
     */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Digitalriver\DrPay\Logger\Logger $logger
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
            $order = $observer->getEvent()->getInvoice()->getOrder();
            
            if(!empty($event)) {
                if($event == self::EVENT_INVOICE_REGISTER) {
                    $items = $this->_getInvoiceDetails($observer->getEvent()->getInvoice());
                } // end: if
            } // end: if        
        
            if(!empty($items)) {                                
                $this->drHelper->createFulfillmentRequestToDr($items, $order);
            }
        } catch (Exception $ex) {
            $this->_logger->error('createFulfillmentRequestToDr Error : '.$ex->getMessage());
        } // end: try      
    }
    
    /**
     * Collect the invoice details from observer and process line items
     * 
     * @param object $invoiceObj
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
                    }
                }
            } // end: foreach
        } catch (Exception $ex) {
            $this->_logger->error('Error from _getInvoiceDetails(): '.$ex->getMessage());
        } // end: try 

        return $items;
    } // end: function _getInvoiceDetails
}

<?php

/**
 * Update EFN status once order is canceled
 * 
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 * @author   Mohandass <mohandass.unnikrishnan@diconium.com>
 *
 */

namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderCancelObserver implements ObserverInterface 
{
    /**
     * Event name for Order Cancel
     */
    CONST EVENT_ORDER_CANCEL_AFTER   = 'order_cancel_after';
    /**
     * @var Digitalriver\DrPay\Model\DrConnectorFactory
     */   
    protected $drFactory;
    
    /**
     *
     * @param \Digitalriver\DrPay\Helper\Data $drHelper
     * @param \Digitalriver\DrPay\Model\DrConnectorFactory
     * @param \Psr\Log\LoggerInterface
     * 
     */
    public function __construct(
        \Digitalriver\DrPay\Model\DrConnectorFactory $drFactory,
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->drFactory    = $drFactory;
        $this->drHelper     = $drHelper;
        $this->_logger      = $logger;        
    }

    /**
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer) {
        $items = [];
                
        try {
            $order = $observer->getEvent()->getOrder();
            
            if ($order->getDrOrderId()) {
                // If order is canceled or complete, Update EFN Post Status column
                if($order->getState() == \Magento\Sales\Model\Order::STATE_CANCELED || $order->getState() == \Magento\Sales\Model\Order::STATE_COMPLETE) {
                    $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                    $drModel->setPostStatus(1);
                    $drModel->save();
                } // end: if
            } // end: if
        } catch (Exception $ex) {
            $this->_logger->error('OrderCancelObserver Error : '. $ex->getMessage());
        } // end: try      
    }
}
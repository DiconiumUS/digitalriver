<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Digitalriver\DrPay\Block\Onepage;

use Magento\Customer\Model\Context;
use Magento\Sales\Model\Order;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * One page checkout success page
 *
 * @api
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;
    
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;
    
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    protected $product;
    
    /**
     * Custom Date
     * 
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $customDateTime;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param array $data
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
		PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\Registry $registry,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderConfig = $orderConfig;
        $this->_isScopePrivate = true;
        $this->httpContext = $httpContext;
		$this->priceCurrency = $priceCurrency;
        $this->_coreRegistry = $registry;       
        $this->product = $product;		
        $this->helper =  $helper;
        $this->customDateTime = $dateTime;
    }

    /**
     * Prepares block data
     *
     * @return void
     */
    protected function prepareBlockData()
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        $this->addData(
            [
                'is_order_visible' => $this->isVisible($order),
                'view_order_url' => $this->getUrl(
                    'sales/order/view/',
                    ['order_id' => $order->getEntityId()]
                ),
                'print_url' => $this->getUrl(
                    'sales/order/print',
                    ['order_id' => $order->getEntityId()]
                ),
                'can_print_order' => $this->isVisible($order),
                'can_view_order'  => $this->canViewOrder($order),
                'order_id'  => $order->getIncrementId(),
                'order' => $order
            ]
        );
    }

    /**
     * Format the given date
     * 
     * @return string
     */
    public function getFormattedDate($date) {
        if($date) {
            $dateToTimestamp = $date;
            $timeStamp = $this->customDateTime->gmtTimestamp($dateToTimestamp);
            $convertdate = (new \DateTime())->setTimestamp($timeStamp);
            return $convertdate->format("M d, Y, H:i:s A");
        }
        return;
    }
}

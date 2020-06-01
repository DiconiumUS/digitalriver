<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Controller\Review;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Index
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Digitalriver\DrPay\Helper\Data
     */
    protected $helper;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;    
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;
    /**
     * @var \Digitalriver\DrPay\Logger\Logger
     */
    protected $_logger;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->helper           =  $helper;
        $this->checkoutSession  = $checkoutSession;
        $this->pageFactory      = $pageFactory;
        $this->_logger      = $logger;
        parent::__construct($context);
    }
    
    /**
     * @return mixed|null
     */
    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getId() && $quote->getIsActive()) {
            if ($this->getRequest()->getParam('sourceId')) {
                return $this->pageFactory->create();
            } else {
                $this->_logger->error('Order Review Error : Invalid Source Id');
                $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                $this->_redirect('checkout/cart');
                return;
            } // end: if
        } else {
            $this->_logger->error('Order Review Error : Invalid Quote details');
            $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
            $this->_redirect('checkout/cart');
            return;
        } // end: if
    }
}
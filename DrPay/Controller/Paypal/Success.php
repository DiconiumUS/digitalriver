<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Controller\Paypal;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Context;

/**
 * Class Success
 */
class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
        /**
         * @var \Magento\Quote\Model\QuoteFactory
         */
    protected $quoteFactory;
        /**
         * @var \Magento\Directory\Model\Region
         */
    protected $regionModel;
    /**
     * @var \Digitalriver\DrPay\Logger\Logger
     */
    protected $logger;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session       $customerSession
     * \Magento\Sales\Model\Order $order
     * \Magento\Checkout\Model\Session $checkoutSession
     * \Digitalriver\DrPay\Helper\Data $helper
     * \Magento\Directory\Model\Region $regionModel
     * \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Digitalriver\DrPay\Logger\Logger $logger
     */

    public function __construct(
        Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Directory\Model\Region $regionModel,
		\Digitalriver\DrPay\Model\DrConnector $drconnector,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->customerSession = $customerSession;
        $this->order = $order;
        $this->helper =  $helper;
        $this->checkoutSession = $checkoutSession;
        $this->quoteFactory = $quoteFactory;
        $this->regionModel = $regionModel;
        $this->drconnector = $drconnector;
		$this->jsonHelper = $jsonHelper;
		$this->quoteManagement = $quoteManagement;
        $this->_logger = $logger;
        return parent::__construct($context);
    }
    
    /**
     * Paypal Success response
     *
     * @return mixed|null
     */
    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getId() && $quote->getIsActive()) {
            try {
                /**
                 * @var \Magento\Framework\Controller\Result\Redirect $resultRedirect
                 */
                $resultRedirect = $this->resultRedirectFactory->create();
                if ($this->getRequest()->getParam('sourceId')) {
                    $source_id = $this->getRequest()->getParam('sourceId');
                    $accessToken = $this->checkoutSession->getDrAccessToken();
                    $paymentResult = $this->helper->applyQuotePayment($source_id);
                    $cartresult = $this->helper->getDrCart();
                    $result = $this->helper->createOrderInDr($accessToken);
                    if ($result && isset($result["errors"])) {
                        $this->messageManager->addError(__('Unable to Place Order!! Payment has been failed'));
                        return $resultRedirect->setPath('checkout/cart');
                    } else {
                        // "last successful quote"
                        $quoteId = $quote->getId();
                        $this->checkoutSession->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);
                        if (!$quote->getCustomerId()) {
                            $quote->setCustomerId(null)
                                    ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                                    ->setCustomerIsGuest(true)
                                    ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
                        }
                        $quote->collectTotals();

                        // Check quote has any errors
                        $isValidQuote = $this->helper->validateQuote($quote);

                        if (!empty($isValidQuote)) {

                            // Update Quote's Shipping Address details from DR Order creation response
                            if (isset($result['submitCart']['shippingAddress']) && !$quote->isVirtual()) {
                                $shippingAddress = $this->helper->getDrAddress('shipping', $result);
                                if (!empty($shippingAddress)) {
                                    $quote->getShippingAddress()->addData($shippingAddress);
                                } // end: if
                            } // end: if
                            // Update Quote's Billing Address details from DR Order creation response
                            if (isset($result['submitCart']['billingAddress'])) {
                                $billingAddress = $this->helper->getDrAddress('billing', $result);
                                if (!empty($billingAddress)) {
                                    $quote->getBillingAddress()->addData($billingAddress);
                                } // end: if
                            } // end: if

                            $order = $this->quoteManagement->submit($quote);
                            if ($order) {
                                $this->checkoutSession->setLastOrderId($order->getId())
                                        ->setLastRealOrderId($order->getIncrementId())
                                        ->setLastOrderStatus($order->getStatus());
                            } else {
                                $this->helper->cancelDROrder($quote, $result);
                                $this->messageManager->addError(__('Unable to Place Order!! Payment has been failed'));
                                $this->_redirect('checkout/cart');
                                return;
                            }

                            $this->_eventManager->dispatch('dr_place_order_success', ['order' => $order, 'quote' => $quote, 'result' => $result, 'cart_result' => $cartresult]);
                            $this->_redirect('checkout/onepage/success', array('_secure' => true));
                            return;
                        } else {
                            $this->helper->cancelDROrder($quote, $result);
                            $this->_redirect('checkout/cart');
                            return;
                        } // end: if
                    }
                }
            } catch (\Magento\Framework\Exception\LocalizedException $le) {
                $this->_logger->error('Paypal Error : '.json_encode($le->getRawMessage()));
                $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                // If exception thrown from DR calls, then $result may be emtpy which will lead to another error
                if(!empty($result) && is_array($result)) {
                    $this->helper->cancelDROrder($quote, $result);
                } // end: if
                $this->_redirect('checkout/cart');
                return;
            } catch (\Exception $ex) {
                $this->_logger->error('Paypal Error : '.json_encode($ex->getMessage()));
                $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                // If exception thrown from DR calls, then $result may be emtpy which will lead to another error
                if(!empty($result) && is_array($result)) {
                    $this->helper->cancelDROrder($quote, $result);
                } // end: if
                $this->_redirect('checkout/cart');
                return;
            } // end: try            
        }
        $this->_redirect('checkout/cart');
        return;
    }
}

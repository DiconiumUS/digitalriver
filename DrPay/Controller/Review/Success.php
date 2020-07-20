<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Controller\Review;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Context;

/**
 * Class Success
 */
class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Digitalriver\DrPay\Logger\Logger
     */
    protected $logger;
    
    // Default redirection url if any error occurs
    protected $errorRedirect  = 'checkout/cart';
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Digitalriver\DrPay\Helper\Data $helper
     * @param \Digitalriver\DrPay\Logger\Logger $logger
     * @param \Magento\Quote\Api\CartManagementInterface $quoteManagement
     */

    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->helper           = $helper;
        $this->checkoutSession  = $checkoutSession;
        $this->quoteManagement  = $quoteManagement;
        $this->_logger          = $logger;
        return parent::__construct($context);
    }
    
    /**
     * Review Success response
     *
     * @return mixed|null
     */
    public function execute()
    {
        $sourceId       = $this->getRequest()->getPost('sourceId');
        $quote          = $this->checkoutSession->getQuote();
        $paymentMethod  = $quote->getPayment()->getMethod();
        // Check payment method is in DR list of payment methods enabled
        if(!in_array($paymentMethod, [
            \Digitalriver\DrPay\Model\PayPal\ConfigProvider::PAYMENT_METHOD_PAYPAL_CODE,
            \Digitalriver\DrPay\Model\Klarna\ConfigProvider::PAYMENT_METHOD_KLARNA_CODE,
            \Digitalriver\DrPay\Model\DirectDebit\ConfigProvider::PAYMENT_METHOD_DIRECT_DEBIT_CODE
        ], TRUE)) {
            $this->_redirect($this->errorRedirect);
            return;
        } // end: if
                
        if(empty($sourceId) || empty($quote)) {
            $this->_redirect($this->errorRedirect);
            return;
        } else {
            $sourceId = base64_decode($sourceId);
            
            if ($quote->getId() && $quote->getIsActive()) {
                try {
                    /**
                     * @var \Magento\Framework\Controller\Result\Redirect $resultRedirect
                     */
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $accessToken    = $this->checkoutSession->getDrAccessToken();
                    $cartresult     = $this->helper->getDrCart();
                    $result         = $this->helper->createOrderInDr($accessToken);
                    
                    if ($result && isset($result["errors"])) {
                        $this->messageManager->addError(__('Unable to Place Order!! Payment has been failed'));
                        return $resultRedirect->setPath($this->errorRedirect);
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
                            if($paymentMethod == \Digitalriver\DrPay\Model\PayPal\ConfigProvider::PAYMENT_METHOD_PAYPAL_CODE) {
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
                            } // end: if

                            $order = $this->quoteManagement->submit($quote);
                            if ($order) {
                                $this->checkoutSession->setLastOrderId($order->getId())
                                        ->setLastRealOrderId($order->getIncrementId())
                                        ->setLastOrderStatus($order->getStatus());
                            } else {
                                $this->helper->cancelDROrder($quote, $result);
                                $this->messageManager->addError(__('Unable to Place Order!! Payment has been failed'));
                                $this->_redirect($this->errorRedirect);
                                return;
                            }

                            $this->_eventManager->dispatch('dr_place_order_success', ['order' => $order, 'quote' => $quote, 'result' => $result, 'cart_result' => $cartresult]);
                            $this->_redirect('checkout/onepage/success', array('_secure' => true));
                            return;
                        } else {
                            $this->helper->cancelDROrder($quote, $result);
                            $this->_redirect($this->errorRedirect);
                            return;
                        } // end: if
                    } // end: if
                } catch (\Magento\Framework\Exception\LocalizedException $le) {
                    $this->_logger->error('Order Creation Error : '.json_encode($le->getRawMessage()));
                    $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                    // If exception thrown from DR calls, then $result may be emtpy which will lead to another error
                    if(!empty($result) && is_array($result)) {
                        $this->helper->cancelDROrder($quote, $result);
                    } // end: if
                    $this->_redirect($this->errorRedirect);
                    return;
                } catch (\Exception $ex) {
                    $this->_logger->error('Order Creation Error : '.json_encode($ex->getMessage()));
                    $this->messageManager->addError(__('Sorry! An error occurred, Try again later.'));
                    // If exception thrown from DR calls, then $result may be emtpy which will lead to another error
                    if(!empty($result) && is_array($result)) {
                        $this->helper->cancelDROrder($quote, $result);
                    } // end: if
                    $this->_redirect($this->errorRedirect);
                    return;
                } // end: try
            } else {
                $this->_redirect($this->errorRedirect);
                return;
            } // end: if
        } // end: if
    }
}
<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Controller\Review;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Braintree\Gateway\Config\PayPal\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Class Index
 */
class Index extends AbstractAction implements HttpPostActionInterface, HttpGetActionInterface
{    
    /**
     * Constructor
     *
     * @param Context $context
     * @param Config $config
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Config $config,
        Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper
    ) {
        $this->helper =  $helper;
        parent::__construct($context, $config, $checkoutSession);
    }
    
    public function execute()
    {
        $requestData = trim($this->getRequest()->getParam('sourceId'));

        try {
            if(empty($requestData)) {
                throw new LocalizedException(__('Checkout failed to initialize. Verify and try again.'));
            } // end: if
            
            $quote          = $this->checkoutSession->getQuote();
            $paymentMethod  = $quote->getPayment()->getMethod();

            if (empty($paymentMethod)) {
                throw new LocalizedException(__('Invalid Payment Details'));
            } // end: if
            
            if (empty($quote) || $quote->getPayment()->hasAdditionalInformation() && !$quote->getPayment()->getAdditionalInformation()) {
                throw new LocalizedException(__('Checkout failed to initialize. Verify and try again.'));
            } // end: if
            
            if(!empty($paymentMethod) && $paymentMethod === \Digitalriver\DrPay\Model\PayPal::PAYMENT_METHOD_PAYPAL_CODE) {
                $paymentResult  = $this->helper->applyQuotePayment($requestData, true);
                
                if (empty($paymentResult) || isset($paymentResult['errors']) || empty($paymentResult['cart']) || empty($paymentResult['cart']['billingAddress'])) {
                    throw new LocalizedException(__('Invalid Payment Details'));
                } // end: if
                
                // Update cart totals from DR Result if quote is virtual
                if ($quote->isVirtual()) {
                    // Update Quote's Billing Address details from DR Order creation response
                    $billingAddress = $this->helper->getDRApplyPaymentAddress('billing', $paymentResult);
                    if (!empty($billingAddress)) {
                        $quote->getBillingAddress()->addData($billingAddress);
                    } // end: if                
                
                    $this->helper->updateReviewTotals($quote, $paymentResult);
                } // end: if
            } // end: if
            
            $this->validateQuote($quote);

            /** @var \Magento\Framework\View\Result\Page $resultPage */
            $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

            /** @var \Digitalriver\DrPay\Block\Checkout\Review $reviewBlock */
            $reviewBlock = $resultPage->getLayout()->getBlock('drpay.paypal.review');

            $reviewBlock->setQuote($quote);
            $reviewBlock->getChildBlock('shipping_method')->setData('quote', $quote);

            return $resultPage;
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } // end: try

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
    }
}
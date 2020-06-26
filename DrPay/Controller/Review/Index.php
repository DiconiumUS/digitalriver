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
        $sourceIdValid = false;
        $requestData = trim($this->getRequest()->getParam('sourceId'));

        try {
            if(empty($requestData)) {
                throw new LocalizedException(__('Checkout failed to initialize. Verify and try again.'));
            } // end: if

            $paymentResult  = $this->helper->applyQuotePayment($requestData);
            
            if (empty($paymentResult) || isset($paymentResult["errors"])) {
                throw new LocalizedException(__('Invalid Payment Details'));
            } // end: if
            
            /*// verify against cookie value         
            if(isset($_COOKIE['sessId']) && !empty($_COOKIE['sessId'])) {
                $allowedPaymentMethods = [
                    \Digitalriver\DrPay\Model\PayPal::PAYMENT_METHOD_PAYPAL_CODE,
                    \Digitalriver\DrPay\Model\Klarna::PAYMENT_METHOD_KLARNA_CODE,
                    \Digitalriver\DrPay\Model\DirectDebit::PAYMENT_METHOD_DIRECT_DEBIT_CODE,
                ];
                
                $cookieValue = $_COOKIE['sessId'];
                $splitValues = explode('#', $cookieValue);
                
                if(count($splitValues) == 2) { 
                    $paymentMethod = base64_decode($splitValues[0]);
                    $sourceIdValid = in_array($paymentMethod, $allowedPaymentMethods, TRUE) && $splitValues[1] == $requestData;
                } // end: if
            } // end: if
            
            if(empty($sourceIdValid)) {
                throw new LocalizedException(__('Invalid Payment Details'));
            } // end: if  */
            
            $quote = $this->checkoutSession->getQuote();
            
            $this->validateQuote($quote);

            if ($quote->getPayment()->hasAdditionalInformation() && !$quote->getPayment()->getAdditionalInformation()) {
                throw new LocalizedException(__('Checkout failed to initialize. Verify and try again.'));
            }

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
<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Controller\Creditcard;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Savedrsource
 */
class Savedrsource extends \Magento\Framework\App\Action\Action
{

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     * @param \Psr\Log\LoggerInterface             $logger
     * @param \Digitalriver\DrPay\Helper\Data       $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Digitalriver\DrPay\Helper\Data $helper
    ) {
        $this->helper =  $helper;
        $this->_checkoutSession = $checkoutSession;
         $this->_logger = $logger;
        parent::__construct($context);
    }
    /**
     * @return mixed|null
     */
    public function execute()
    {
        $responseContent = [
            'success'        => false,
            'content'        => __("Unable to process")
        ];
        $source_id = $this->getRequest()->getParam('source_id');
        $quote = $this->_checkoutSession->getQuote();
        $cartResult = $this->helper->createFullCartInDr($quote, 1);
        if ($cartResult) {
            if ($this->getRequest()->getParam('source_id')) {
                $source_id = $this->getRequest()->getParam('source_id');
                $paymentResult = $this->helper->applyQuotePayment($source_id);
                $is_save_future = $this->getRequest()->getParam('save_future_use');
                $save_future_name = $this->getRequest()->getParam('save_future_name');
                if ($is_save_future == "true" && $save_future_name) {
                    $name = $this->getRequest()->getParam('save_future_name');
                    $this->helper->applySourceShopper($source_id, $name);
                }
                if ($paymentResult) {
                    $responseContent = [
                        'success'        => true,
                        'content'        => $paymentResult
                    ];
                }
            }
            if ($this->getRequest()->getParam('option_id')) {
                $option_id = $this->getRequest()->getParam('option_id');
                $paymentResult = $this->helper->applyQuotePaymentOptionId($option_id);
                if ($paymentResult) {
                    $responseContent = [
                        'success'        => true,
                        'content'        => $paymentResult
                    ];
                }
            }
        }

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);

        return $response;
    }
}

<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Digitalriver\DrPay\Controller\Wiretransfer;

use Magento\Framework\Controller\ResultFactory;

class Savedrquote extends \Magento\Framework\App\Action\Action
{
    protected $regionModel;
	/**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
		\Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Directory\Model\Region $regionModel,
		\Digitalriver\DrPay\Helper\Data $helper
    ) {
		$this->helper =  $helper;
		$this->_checkoutSession = $checkoutSession;
        $this->regionModel = $regionModel;
		parent::__construct($context);
    }

    public function execute()
    {
        $responseContent = [
            'success'        => false,
            'content'        => __("Unable to process")
        ];

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $isEnabled = $this->helper->getIsEnabled();
        if(!$isEnabled) {
            return $response->setData($responseContent);
        }
        $quote = $this->_checkoutSession->getQuote();
        $cartResult = $this->helper->createFullCartInDr($quote, 1);
            // $paymentResult = $this->helper->applyQuotePayment($source_id);
        if($cartResult){
            $responseContent = [
                'success'        => true,
                'content'        => $cartResult
            ];            
        }
        
        $response->setData($responseContent);

        return $response;
    }
}

<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Digitalriver\DrPay\Controller\Wiretransfer;

use Magento\Framework\Controller\ResultFactory;

class Savedrsource extends \Magento\Framework\App\Action\Action
{

	/**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Digitalriver\DrPay\Helper\Data $helper
    ) {
		$this->helper =  $helper;
		$this->_checkoutSession = $checkoutSession;
		parent::__construct($context);
    }

    public function execute()
    {
        $responseContent = [
            'success'        => false,
            'content'        => __("Unable to process")
        ];      
        if($this->getRequest()->getParam('source_id')){
            $source_id = $this->getRequest()->getParam('source_id');
            $paymentResult = $this->helper->applyQuotePayment($source_id); 
            $accessToken = $this->_checkoutSession->getDrAccessToken(); 
            $fulldir        = explode('app/code',dirname(__FILE__)); 
            $logfilename    = $fulldir[0] . 'var/log/dr-pay-flow.log'; 
            file_put_contents($logfilename, "Wire Source ID: ".$source_id." accessToken: ".$accessToken." Savedrsource result : ".json_encode($paymentResult)."\r\n", FILE_APPEND);

            if($paymentResult){
                $responseContent = [
                    'success'        => true,
                    'content'        => $paymentResult
                ];            
            }
        }
		$response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);

        return $response;
    }
}

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
     * @param \Digitalriver\DrPay\Logger\Logger     $logger
     * @param \Digitalriver\DrPay\Helper\Data       $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Directory\Model\Region $regionModel,
		\Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Digitalriver\DrPay\Logger\Logger $logger
    ) {
        $this->helper =  $helper;
        $this->_checkoutSession = $checkoutSession;
		$this->regionModel      = $regionModel;
		$this->addressRepository = $addressRepository;
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
        
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $isEnabled = $this->helper->getIsEnabled();
        if(!$isEnabled) {
            return $response->setData($responseContent);
        }
        
        $source_id = $this->getRequest()->getParam('source_id');
        $quote = $this->_checkoutSession->getQuote();
		if ($optionId = $this->getRequest()->getParam('option_id')) {
			$cardResult = $this->helper->getSavedCards();
			foreach($cardResult['paymentOptions']['paymentOption'] as $id => $card) {
				if($optionId == $id){
					$region = $this->regionModel->loadByCode($card['address']['state'], $card['address']['country'])->getData();
					$street = $card['address']['line1'];
					$street .= (!empty($card['address']['line2'])) ? (' '.$card['address']['line2']) : null;
					$street .= (!empty($card['address']['line3'])) ? (' '.$card['address']['line3']) : null;

					$street = trim($street);
					$phone = $quote->getBillingAddress()->getTelephone();
					$billingAddress = [
						'firstname'     => (!empty($card['owner']['firstName'])) ? trim($card['owner']['firstName']) : null,
						'lastname'      => (!empty($card['owner']['lastName'])) ? trim($card['owner']['lastName']) : null,
						'street'        => $street,
						'city'          => $card['address']['city'],
						'postcode'      => $card['address']['postalCode'],
						'country_id'    => $card['address']['country'],
						'region'        => !empty($region['name']) ? $region['name'] : null,
						'region_id'     => !empty($region['region_id']) ? $region['region_id'] : null,
						'telephone'     => $phone
					];
					$quote->getBillingAddress()->addData($billingAddress);
					$quote->save();
					break;
				}
			}
		}else{
			$customerAddressId = $quote->getBillingAddress()->getCustomerAddressId();
			if($customerAddressId){
				$address = $this->addressRepository->getById($customerAddressId);
				$billingAddress = [
                    'firstname'     => $address->getFirstname(),
                    'lastname'      => $address->getLastname(),
                    'street'        => $address->getStreet(),
                    'city'          => $address->getCity(),
                    'postcode'      => $address->getPostcode(),
                    'country_id'    => $address->getCountryId(),
                    'region'        => $address->getRegion()->getRegion(),
                    'region_id'     => $address->getRegionId(),
                    'telephone'     => $address->getTelephone()
                ];
				$quote->getBillingAddress()->addData($billingAddress);
				$quote->save();
			}
		}
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

        
        $response->setData($responseContent);

        return $response;
    }
}

<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */

namespace Digitalriver\DrPay\Controller\Klarna;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Savedrquote
 */
class Savedrquote extends \Magento\Framework\App\Action\Action
{
        /**
         * @var \Magento\Directory\Model\Region
         */
    protected $regionModel;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session       $checkoutSession
     * @param \Magento\Directory\Model\Region       $regionModel
     * @param \Digitalriver\DrPay\Helper\Data       $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Directory\Model\Region $regionModel,
		\Magento\Customer\Model\AddressFactory $addressFactory,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->helper =  $helper;
        $this->_checkoutSession = $checkoutSession;
        $this->regionModel = $regionModel;
		$this->_addressFactory = $addressFactory;
		$this->scopeConfig = $scopeConfig;
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
        
        $quote = $this->_checkoutSession->getQuote();
        $cartResult = $this->helper->createFullCartInDr($quote, 1);
        if ($cartResult) {
            $this->_checkoutSession->setSelectedPaymentMethod('klarna');
            $payload = [];
            //$returnurl = $this->_url->getUrl('drpay/klarna/success');
            $returnurl = $this->_url->getUrl('drpay/review/index');
            $cancelurl = $this->_url->getUrl('drpay/klarna/cancel');
            $itemsArr = [];
            $shipping = [];
            $itemPrice = 0;
            $taxAmnt = 0;
            $shipAmnt = 0;
			$tax_inclusive = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
			$lineitems = $cartResult['cart']['lineItems']['lineItem'];
            foreach ($lineitems as $lineitem) {
                $itemsArr[] = [
                    'name' => $lineitem['product']['displayName'],
                    'quantity' => $lineitem['quantity'],
                    'unitAmount' => $lineitem['pricing']['listPrice']['value'],
                    'taxRate' => $lineitem['pricing']['taxRate'],
                ];
            }
            $address = $quote->getShippingAddress();
            $billingAddress = $quote->getBillingAddress();
            if ($quote->isVirtual()) {
                $address = $quote->getBillingAddress();
            }
            if ($address && $address->getId()) {
				if(!$address->getCity()){
					$customer = $quote->getCustomer();
					if($customer->getId()){
						$billingAddressId = $customer->getDefaultBilling();
						if($billingAddressId){
							$billingAddress = $this->_addressFactory->create()->load($billingAddressId);
							$address = $billingAddress;
						}
					}
				}
                $shipAmnt = $cartResult['cart']['pricing']['shippingAndHandling']['value'];
                $taxAmnt = $cartResult['cart']['pricing']['tax']['value'];
                $shipping =  [];
                $street = $address->getStreet();
                if (isset($street[0])) {
                    $street1 = $street[0];
                } else {
                    $street1 = "";
                }
                if (isset($street[1])) {
                    $street2 = $street[1];
                } else {
                    $street2 = "";
                }
                $state = 'na';
                $regionName = $address->getRegion();
                if ($regionName) {
                    $countryId = $address->getCountryId();
                    $region = $this->regionModel->loadByName($regionName, $countryId);
                    $state = $region->getCode()?:$regionName;
                }

                $shipping =  [
                        'recipient' => $address->getFirstname()." ".$address->getLastname(),
                        'phoneNumber' => $address->getTelephone(),
						'email' => $quote->getCustomerEmail() ? $quote->getCustomerEmail() : $this->_checkoutSession->getGuestCustomerEmail(),
                        'address' =>  [
                            'line1' => $street1,
                            'line2' => $street2,
                            'city' => (null !== $address->getCity())?$address->getCity():'na',
                            'state' => $state,
                            'country' => $address->getCountryId(),
                            'postalCode' => $address->getPostcode(),
                        ],
                    ];
            }

			$street = $billingAddress->getStreet();
			if (isset($street[0])) {
				$street1 = $street[0];
			} else {
				$street1 = "";
			}
			if (isset($street[1])) {
				$street2 = $street[1];
			} else {
				$street2 = "";
			}
			$state = 'na';
			$regionName = $billingAddress->getRegion();
			if ($regionName) {
				$countryId = $billingAddress->getCountryId();
				$region = $this->regionModel->loadByName($regionName, $countryId);
				$state = $region->getCode();
			}
        
            //Prepare the payload and return in response for DRJS klarna payload
            $payload['payload'] = [
                'type' => 'klarnaCredit',
                'amount' => round($quote->getGrandTotal(), 2),
                'currency' => $quote->getQuoteCurrencyCode(),
				'owner' => [
					'firstName' => $billingAddress->getFirstname(),
					'lastName' => $billingAddress->getLastname(),
					'email' => $quote->getCustomerEmail() ? $quote->getCustomerEmail() : $this->_checkoutSession->getGuestCustomerEmail(),
					'phoneNumber' => $billingAddress->getTelephone(),
					'address' =>  [
						'line1' => $street1,
						'city' => (null !== $billingAddress->getCity())?$billingAddress->getCity():'na',
						'state' => $state,
						'country' => $billingAddress->getCountryId(),
						'postalCode' => $billingAddress->getPostcode(),
					],
				],
                'klarnaCredit' =>  [
					"setPaidBefore" => true,
                    'returnUrl' => $returnurl,
                    'cancelUrl' => $cancelurl,
                    'items' => $itemsArr,
                    'taxAmount' => ($tax_inclusive) ? 0 : $cartResult['cart']['pricing']['tax']['value'],
                    'shippingAmount' => $cartResult['cart']['pricing']['shippingAndHandling']['value'],
                    'requestShipping' => true,
                    'shipping' => $shipping,
                ],
            ];
            $responseContent = [
                'success'        => true,
                'content'        => $payload
            ];
        }
        
        $response->setData($responseContent);

        return $response;
    }
}

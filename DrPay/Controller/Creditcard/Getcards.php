<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
 
namespace Digitalriver\DrPay\Controller\Creditcard;

use Magento\Framework\Controller\ResultFactory;

/**
 * Class Getcards
 */
class Getcards extends \Magento\Framework\App\Action\Action
{

    /**
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Checkout\Model\Session        $checkoutSession
     * @param \Digitalriver\DigitalRiver\Helper\Data $helper
     * @param \Magento\Directory\Model\Region        $regionModel,
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Directory\Model\Region $regionModel,
        \Magento\Directory\Model\CountryFactory $countryFactory
    ) {
        $this->helper =  $helper;
        $this->_checkoutSession = $checkoutSession;
        $this->regionModel      = $regionModel;
        $this->countryFactory  = $countryFactory;
        parent::__construct($context);
    }
    /**
     * @return mixed|null
     */
    public function execute()
    {
        $responseContent = [
            'success'        => false
        ];
        $cardResult = $this->helper->getSavedCards();
        if ($cardResult) {
            foreach($cardResult['paymentOptions']['paymentOption'] as $id => $card) {
                $region     = $this->regionModel->loadByCode($card['address']['state'], $card['address']['country'])->getData();
                $cardResult['paymentOptions']['paymentOption'][$id]['address']['region']    = !empty($region['name']) ? $region['name'] : null;
                $cardResult['paymentOptions']['paymentOption'][$id]['address']['regionId'] = !empty($region['region_id']) ? $region['region_id'] : null;
                
                $country    = $this->countryFactory->create()->loadByCode($card['address']['country']);
                if (!empty($country)) {
                    $cardResult['paymentOptions']['paymentOption'][$id]['address']['countryName'] = $country->getName();
                }
            } // end: if
            
            $responseContent = [
                'success'        => true,
                'content'        => $cardResult
            ];
        }
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);

        return $response;
    }
}

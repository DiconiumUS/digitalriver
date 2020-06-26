<?php
/**
 * Digitalriver Helper
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 * @author   Balaji S <balaji.setti@diconium.com>
 */
 
namespace Digitalriver\DrPay\Helper;

use Magento\Framework\App\Helper\Context;

/**
 * Class Data
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
        /**
         * @var session
         */
    protected $session;
        /**
         * @var storeManager
         */
    protected $storeManager;
        /**
         * @var regionModel
         */
    protected $regionModel;
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    /**
     * @var CartManagementInterface
     */
    private $_cartManagement;

    /**
     * @var Session
     */
    private $_customerSession;
    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;
    protected $drFactory;
    protected $jsonHelper;

        /**
         * @param Context                                          $context
         * @param \Magento\Checkout\Model\Session                  $session
         * @param \Magento\Store\Model\StoreManagerInterface       $storeManager
         * @param \Magento\Catalog\Api\ProductRepositoryInterface  $productRepository
         * @param \Magento\Quote\Api\CartManagementInterface       $_cartManagement
         * @param \Magento\Customer\Model\Session                  $_customerSession
         * @param \Magento\Checkout\Helper\Data                    $checkoutHelper
         * @param \Magento\Framework\Encryption\EncryptorInterface $enc
         * @param \Magento\Framework\HTTP\Client\Curl              $curl
         * @param \Magento\Directory\Model\Region                  $regionModel
         * @param \Digitalriver\DrPay\Model\DrConnectorFactory $drFactory
         * @param \Magento\Framework\Json\Helper\Data $jsonHelper
         * @param \Digitalriver\DrPay\Logger\Logger                $logger
         */
    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\CartManagementInterface $_cartManagement,
        \Magento\Customer\Model\Session $_customerSession,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Framework\Encryption\EncryptorInterface $enc,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Directory\Model\Region $regionModel,
        \Digitalriver\DrPay\Model\DrConnectorFactory $drFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Digitalriver\DrPay\Logger\Logger $logger		
    ) {
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->_cartManagement = $_cartManagement;
        $this->_customerSession = $_customerSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->regionModel = $regionModel;
        $this->_enc = $enc;
        $this->curl = $curl;
        $this->jsonHelper = $jsonHelper;
        $this->_enc = $enc;
        $this->drFactory = $drFactory;
		$this->remoteAddress = $remoteAddress;
		$this->currencyFactory = $currencyFactory;
        parent::__construct($context);
        $this->_logger = $logger;
    }
    /**
     * @return string|null
     */
    public function convertTokenToFullAccessToken()
    {
        $quote = $this->session->getQuote();
        $address = $quote->getBillingAddress();
        if ($this->_customerSession->isLoggedIn()) {
            $external_reference_id = $address->getEmail().$address->getCustomerId();
        } else {
            $guestEmail = $this->session->getGuestCustomerEmail();
            $external_reference_id = $guestEmail.$quote->getId();
        }
        $customerData = $quote->getCustomer();
        try {
            $this->createShopperInDr($quote, $external_reference_id);
            if ($external_reference_id) {
                $fillAccessToken = '';
                $url = $this->getDrBaseUrl()."oauth20/token";
                $data = [
                   "grant_type" => "client_credentials",
                   "dr_external_reference_id" => $external_reference_id,
                   "format" => "json"
                ];
                if ($this->getDrBaseUrl() && $this->getDrAuthUsername() && $this->getDrAuthPassword()) {
                    $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                    $this->curl->setCredentials($this->getDrAuthUsername(), $this->getDrAuthPassword());
                    $this->curl->addHeader("Content-Type", "application/x-www-form-urlencoded");
                    $this->curl->post($url, $data);
                    $result = $this->curl->getBody();
                    $result = json_decode($result, true);
                    if (isset($result["access_token"])) {
                        $fillAccessToken = $result["access_token"];
                    }
                    if ($fillAccessToken) {
                        $this->session->setDrAccessToken($fillAccessToken);
                    }
                    return $fillAccessToken;
                }
            }
        } catch (Exception $e) {
            $this->_logger->error("Error in Token request.".$e->getMessage());
        }
    }

	public function checkDrAccessTokenValidation($token){
		if($token){			
			$url = $this->getDrBaseUrl()."oauth20/access-tokens?token=".$token."&format=json";
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			 
			$result = curl_exec($ch);
			curl_close($ch);
			
			$result = json_decode($result, true);
			$currency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
			if($result["currency"] != $currency){
				$this->updateAccessTokenCurrency($token, $currency);
			}
			if(isset($result["expiresIn"]) && $result["expiresIn"] > 1000){
				return true;
			}
		}
		return false;
	}

    /**
     * @return null
     */
    public function createShopperInDr($quote, $external_reference_id)
    {
        if ($external_reference_id) {
            $address = $quote->getBillingAddress();
            $firstname = $address->getFirstname();
            $lastname = $address->getLastname();
            if ($this->_customerSession->isLoggedIn()) {
                $email = $address->getEmail();
            } else {
                $email = $this->session->getGuestCustomerEmail();
            }
            $username = $external_reference_id;
            $currency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
            $apikey = $this->getDrApiKey();
            $locale = $this->getLocale();
            $drBaseUrl = $this->getDrBaseUrl();
            if ($apikey && $locale && $drBaseUrl && $firstname) {
                $url = $this->getDrBaseUrl()."v1/shoppers?apiKey=".$apikey."&format=json";
                $data = "<shopper><firstName>".$firstname."</firstName><lastName>".$lastname .
                "</lastName><externalReferenceId>".$username."</externalReferenceId><username>" .
                $username."</username><emailAddress>".$email."</emailAddress><locale>".$locale .
                "</locale><currency>".$currency."</currency></shopper>";
                $this->_logger->info(json_encode($data));
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->addHeader("Content-Type", "application/xml");
                $this->curl->post($url, $data);
                $result = $this->curl->getBody();
                $this->_logger->info(json_encode($result));
            }
        }
        return;
    }
    public function updateAccessTokenCurrency($accessToken, $currentCurrency)
    {
        if ($accessToken) {
            $apikey = $this->getDrApiKey();
            $locale = $this->getLocale();
            $drBaseUrl = $this->getDrBaseUrl();
            if ($apikey && $locale && $drBaseUrl) {
                $data = [];
                $url = $this->getDrBaseUrl()."v1/shoppers/me?locale=".$locale."&currency=".$currentCurrency."&format=json";
                $this->_logger->info("Url: ".$url);
                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->addHeader("Authorization", "Bearer ".$accessToken);
                $this->curl->post($url, $data);
                $result = $this->curl->getBody();
            }
        }
        return;
    }
    /**
     * @return array|null
     */
    public function createFullCartInDr($quote, $return = null)
    {		
		$address = $quote->getBillingAddress();
		if (!$address || !$address->getCity()) {
				return;
		}
        if ($this->session->getDrAccessToken()) {
            $accessToken = $this->session->getDrAccessToken();			
			if ($accessToken) {
				$checktoken = $this->checkDrAccessTokenValidation($accessToken);
				if(!$checktoken){
					$accessToken = $this->convertTokenToFullAccessToken();
					$this->session->setDrAccessToken($accessToken);
				}
			}
        } else {
            $accessToken = $this->convertTokenToFullAccessToken();
			$this->checkDrAccessTokenValidation($accessToken);
            $this->session->setDrAccessToken($accessToken);
        }
        $token = '';
        $this->_logger->info("Token: ".$accessToken);
        if ($accessToken) {
            try {                
                $testorder = $this->getIsTestOrder();
                if ($testorder) {
                    $url = $this->getDrBaseUrl() .
                    "v1/shoppers/me/carts/active?format=json&skipOfferArbitration=true&testOrder=true&expand=lineItems.lineItem.customAttributes";
                } else {
                    $url = $this->getDrBaseUrl() .
                    "v1/shoppers/me/carts/active?format=json&skipOfferArbitration=true&expand=lineItems.lineItem.customAttributes";
                }
				$tax_inclusive = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                $data = [];
                $orderLevelExtendedAttribute = ['name' => 'QuoteID', 'value' => $quote->getId()];
                $data["cart"]["customAttributes"]["attribute"][] = $orderLevelExtendedAttribute;
				$taxInclusiveOverride = ['name' => 'TaxInclusiveOverride', 'type' => 'Boolean', 'value' => 'false'];
				if($tax_inclusive){
					$taxInclusiveOverride["value"] = "true";
				}
                $data["cart"]["customAttributes"]["attribute"][] = $taxInclusiveOverride;
                $lineItems = [];				

                $currency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
                foreach ($quote->getAllItems() as $item) {					
					if($item->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE || $item->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE){
						continue;
					}
					if($item->getParentItemId()){
						if($item->getParentItem()->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE){
							$item = $item->getParentItem();
						}
					}
                    $lineItem =  [];
                    $lineItem["quantity"] = $item->getQty();
					if($item->getParentItemId()){
						if($item->getParentItem()->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE){
							$lineItem["quantity"] = $item->getQty() * $item->getParentItem()->getQty();
						}
					}
					$sku = $item->getSku();
                    $price = $item->getCalculationPrice();
					if($tax_inclusive) {
						$price = $item->getPriceInclTax();
					}
					$actualprice = round($price,2);
					
                    if ($item->getDiscountAmount() > 0) {
                        $price = $price - ($item->getDiscountAmount()/$lineItem["quantity"]);
                    }
                    if ($price <= 0) {
                        $price = 0;
                    }
					
                    $lineItem["product"] = ['id' => $sku];
                    //$lineItem["product"] = ['id' => '5321623900'];
                    $lineItem["pricing"]["salePrice"] = ['currency' => $currency, 'value' => round($price, 2)];
					$lineItemLevelExtendedAttribute = ['name' => 'magento_quote_item_id', 'value' => $item->getId()];
                    $lineItem["customAttributes"]["attribute"][] = $lineItemLevelExtendedAttribute;
					$lineItem["customAttributes"]["attribute"][] = ['name' => 'originalProductPrice', 'value' => $actualprice];
					if($item->getParentItemId()){
						$parentExternalReferenceId = ["name" => "parentExternalReferenceId", "value" => $item->getParentItem()->getSku()];
						$lineItem["customAttributes"]["attribute"][] = $parentExternalReferenceId;
					}
                    $lineItems["lineItem"][] = $lineItem;
                }
                $data["cart"]["lineItems"] = $lineItems;
                $address = $quote->getBillingAddress();
                if ($address && $address->getCity()) {
                    $billingAddress =  [];
                    $billingAddress["id"] = "billingAddress";
                    $billingAddress["firstName"] = $address->getFirstname();
                    $billingAddress["lastName"] = $address->getLastname();
                    $street = $address->getStreet();
                    if (isset($street[0])) {
                        $billingAddress["line1"] = $street[0];
                    } else {
                        $billingAddress["line1"] = "";
                    }
                    if (isset($street[1])) {
                        $billingAddress["line2"] = $street[1];
                    } else {
                        $billingAddress["line2"] = "";
                    }
                    $billingAddress["line3"] = "";
                    $billingAddress["city"] = $address->getCity();
                    $billingAddress["countrySubdivision"] = 'na';
                    $regionName = $address->getRegion();
                    if ($regionName) {
						if(is_array($regionName)){
							$billingAddress["countrySubdivision"] = 'na';
						}else{
							$countryId = $address->getCountryId();
							$region = $this->regionModel->loadByName($regionName, $countryId);
							$billingAddress["countrySubdivision"] = $region->getCode() ? $region->getCode() : 'na';
						}
                    }
                    $billingAddress["postalCode"] = $address->getPostcode();
                    $billingAddress["country"] = $address->getCountryId();
                    $billingAddress["countryName"] = $address->getCountryId();
                    $billingAddress["phoneNumber"] = $address->getTelephone();
                    $billingAddress["emailAddress"] = $address->getEmail();
                    $billingAddress["companyName"] = ($address->getCompany()) ?: '';

                    $data["cart"]["billingAddress"] = $billingAddress;
                    if ($quote->getIsVirtual()) {
                        $billingAddress["id"] = "shippingAddress";
                        $data["cart"]["shippingAddress"] = $billingAddress;
                    } else {
                        $address = $quote->getShippingAddress();
                        $shippingAddress =  [];
                        $shippingAddress["id"] = "shippingAddress";
                        $shippingAddress["firstName"] = $address->getFirstname();
                        $shippingAddress["lastName"] = $address->getLastname();
                        $street = $address->getStreet();
                        if (isset($street[0])) {
                            $shippingAddress["line1"] = $street[0];
                        } else {
                            $shippingAddress["line1"] = "";
                        }
                        if (isset($street[1])) {
                            $shippingAddress["line2"] = $street[1];
                        } else {
                            $shippingAddress["line2"] = "";
                        }
                        $shippingAddress["line3"] = "";
                        $shippingAddress["city"] = $address->getCity();
                        $shippingAddress["countrySubdivision"] = 'na';
                        $regionName = $address->getRegion();
                        if ($regionName) {
							if(is_array($regionName)){
								$shippingAddress["countrySubdivision"] = 'na';
							}else{
								$countryId = $address->getCountryId();
								$region = $this->regionModel->loadByName($regionName, $countryId);
								$shippingAddress["countrySubdivision"] = $region->getCode() ? $region->getCode() : 'na';
							}
                        }
                        $shippingAddress["postalCode"] = $address->getPostcode();
                        $shippingAddress["country"] = $address->getCountryId();
                        $shippingAddress["countryName"] = $address->getCountryId();
                        $shippingAddress["phoneNumber"] = $address->getTelephone();
                        $shippingAddress["emailAddress"] = $address->getEmail();
                        $shippingAddress["companyName"] = ($address->getCompany()) ?: '';

                        $data["cart"]["shippingAddress"] = $shippingAddress;
                    }
                }
                if ($quote->getIsVirtual()) {
					$originalShippingAmount = 0;
                    $shippingAmount = 0;
					$shippingMethod = '';
                    $shippingTitle = "Shipping Price";
                } else {
					$shippingAmount = $quote->getShippingAddress()->getShippingAmount();
					$shippingInclTax = $quote->getShippingAddress()->getShippingInclTax();
                    if($tax_inclusive && $shippingInclTax > 0 && $shippingAmount != 0){
						$shippingAmount = $shippingInclTax;
					}
					$originalShippingAmount = $shippingAmount;
					if($shippingAmount > 0 && $quote->getShippingAddress()->getShippingDiscountAmount() > 0) {
						$shippingAmount = $shippingAmount - $quote->getShippingAddress()->getShippingDiscountAmount();
					}
                    $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
                    $shippingTitle = $quote->getShippingAddress()->getShippingDescription();
                }
                if ($shippingMethod) {
                    $shippingDetails =  [];
                    $shippingDetails["shippingOffer"]["offerId"] = $this->getShippingOfferId();
                    //$shippingDetails["shippingOffer"]["customDescription"] = $shippingTitle;
                    $shippingDetails["shippingOffer"]["overrideDiscount"]["discount"] = round($shippingAmount, 2);
                    $shippingDetails["shippingOffer"]["overrideDiscount"]["discountType"] = "amount";
                    $data["cart"]["appliedOrderOffers"] = $shippingDetails;
                }
                $result = [];
                if ($this->getDrBaseUrl()) {
                    $original_data = $data;					
					$data = $this->encryptRequest(json_encode($data));
					$checksum = sha1(base64_encode($data));					
					$existingChecksum = $this->session->getSessionCheckSum();
					if(!empty($existingChecksum) && $checksum == $existingChecksum){
						if ($return){
							$drresult = $this->session->getDrResult();
							if($drresult){
								$result = json_decode(base64_decode($drresult), true);
								return $result;
							}
						}else{
							return;
						}
					}

					$this->_logger->info("Request: ".json_encode($original_data));
					$this->session->setSessionCheckSum($checksum);					
					$this->deleteDrCartItems($accessToken);                    
                    $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                    $this->curl->addHeader("Content-Type", "application/json");
                    $this->curl->addHeader("Authorization", "Bearer ".$accessToken);
                    $this->curl->post($url, $data);
                    $result = $this->curl->getBody();
                    $result = json_decode($result, true);
                    $this->_logger->info("Response : ".json_encode($result));
					$this->session->setDrResult(base64_encode(json_encode($result)));
                }
                if (isset($result["errors"])) {
                    $this->session->setDrQuoteError(true);
					$this->session->unsSessionCheckSum();
					//$this->session->unsDrAccessToken();
                    if ($return) {
                        return $result;
                    } else {
                        return;
                    }
                }
                $this->session->setDrQuoteError(false);
                $drquoteId = $result["cart"]["id"];
                $this->session->setDrQuoteId($drquoteId);
				
				$shippingTax = 0;
				$productTax = 0;
				$productTotal = 0;
				$productTotalExcl = 0;
				if(isset($result["cart"]['lineItems']) && isset($result["cart"]['lineItems']['lineItem'])) {					
					$lineItems = $result["cart"]['lineItems']['lineItem'];
					foreach($lineItems as $item){
						$productTax += $item['pricing']['productTax']['value'];
						$shippingTax += $item['pricing']['shippingTax']['value'];
						$qty = $item['quantity'];
						
						if($tax_inclusive){
							//$productTotal += $item['pricing']['salePriceWithQuantity']['value'];
							$customAttributes = $item["customAttributes"]["attribute"];
							foreach($customAttributes as $customAttribute){
								if($customAttribute["name"] == "originalProductPrice"){
									$itemOriginalPrice = $customAttribute["value"];
									$productTotal += $itemOriginalPrice * $qty;
									$productTotalExcl += ($itemOriginalPrice / (1 + $item['pricing']['taxRate'])) * $qty;
								}
							}
						}
						else {
							$customAttributes = $item["customAttributes"]["attribute"];
							foreach($customAttributes as $customAttribute){
								if($customAttribute["name"] == "originalProductPrice"){
									$productTotal += $customAttribute["value"] * $qty;
								}
							}
						}
					}
				}

				if($tax_inclusive) {
					// Acceptable hack - this is display only
					$shippingDiff = $result["cart"]['pricing']['shippingAndHandling']['value'] - $shippingTax;
					$shippingAmountExcl = $shippingDiff;
					if($shippingDiff > 0 ){
						$shippingTaxRate = $result["cart"]['pricing']['shippingAndHandling']['value'] / $shippingDiff;
						$shippingAmountExcl =  $originalShippingAmount / $shippingTaxRate;
					}
					$quote->setShippingInclTax($shippingAmount);
					$quote->setShippingAmount($shippingAmountExcl);
				}
				else {
					$shippingAmountExcl = $originalShippingAmount;
					$shippingAmount = $shippingAmountExcl + $shippingTax;
					$productTotalExcl = $productTotal;
					$productTotal = $productTotalExcl + $productTax;	
					$this->session->setDrShippingAndHandling($shippingAmount);	
				}				
				
				$this->session->setDrProductTotal($productTotal);
				$this->session->setDrProductTax($productTax);
				$this->session->setDrShippingTax($shippingTax);				
				$this->session->setDrShippingAndHandlingExcl($shippingAmountExcl);
				$this->session->setDrProductTotalExcl($productTotalExcl);
				
				$orderTotal = $result["cart"]['pricing']['orderTotal']['value'];
				$quote->setGrandTotal($orderTotal);
				$quote->setBaseGrandTotal($this->convertToBaseCurrency($orderTotal));
				$this->session->setDrOrderTotal($orderTotal);
				
				$drtax = $result["cart"]["pricing"]["tax"]["value"];
				$quote->setTaxAmount($drtax);
				$quote->setBaseTaxAmount($drtax);
				$quote->setDrTax($drtax);
				$this->session->setDrTax($drtax);

                if ($return) {
                    return $result;
                } else {
                    return;
                }
            } catch (Exception $e) {
                $this->_logger->error("Error in cart creation.".$e->getMessage());
            }
        }
        $this->session->setDrQuoteError(true);
        return;
    }

	public function convertToBaseCurrency($price){
        $currentCurrency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        $baseCurrency = $this->storeManager->getStore()->getBaseCurrency()->getCode();
        $rate = $this->currencyFactory->create()->load($currentCurrency)->getAnyRate($baseCurrency);
        $returnValue = $price * $rate;
        return $returnValue;
    }
    /**
     * @param  mixed $sourceId
     * @return mixed|null
     */
    public function applyQuotePayment($sourceId = null)
    {
        $result = "";
        if ($this->getDrBaseUrl() && $this->session->getDrAccessToken() && $sourceId!=null) {
            $accessToken = $this->session->getDrAccessToken();
            $url = $this->getDrBaseUrl()."v1/shoppers/me/carts/active/apply-payment-method?format=json";
            $data["paymentMethod"]["sourceId"] = $sourceId;
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->addHeader("Authorization", "Bearer " . $accessToken);
            $this->curl->post($url, json_encode($data));
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
            $this->_logger->info("Apply Quote Result :".json_encode($result));

            if (isset($result['errors']) && count($result['errors']['error'])>0) {
                $result = "";
            }
        }
        return $result;
    }
    /**
     * @param  mixed $paymentId
     * @return mixed|null
     */
    public function applyQuotePaymentOptionId($paymentId = null)
    {
        $result = "";
        $data = [];
        if ($this->getDrBaseUrl() && $this->session->getDrAccessToken() && $paymentId!=null) {
            $accessToken = $this->session->getDrAccessToken();
            $url = $this->getDrBaseUrl().
            "v1/shoppers/me/carts/active/apply-shopper?paymentOptionId=".$paymentId."&format=json";
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->addHeader("Authorization", "Bearer " . $accessToken);
            $this->curl->post($url, $data);
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
            $this->_logger->info("Apply Quote Result :".json_encode($result));
            if (isset($result['errors']) && count($result['errors']['error'])>0) {
                $result = "";
            }
        }
        return $result;
    }

    /**
     * @param  mixed  $sourceId
     * @param  string $name
     * @return mixed|null
     */
    public function applySourceShopper($sourceId = null, $name = "Default Card")
    {
        if ($this->getDrBaseUrl() && $this->session->getDrAccessToken() && $sourceId!=null) {
            $accessToken = $this->session->getDrAccessToken();
            $url = $this->getDrBaseUrl()."v1/shoppers/me/payment-options?format=json";
            $data["paymentOption"]["nickName"] = $name;
            $data["paymentOption"]["isDefault"] = "true";
            $data["paymentOption"]["sourceId"] = $sourceId;
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->addHeader("Authorization", "Bearer " . $accessToken);
            $this->curl->post($url, json_encode($data));
            $result = $this->curl->getBody();
        }
    }
    /**
     * @return array|null
     */
    public function getSavedCards()
    {
        $result = "";
        if ($this->getDrBaseUrl() && $this->session->getDrAccessToken()) {
            $accessToken = $this->session->getDrAccessToken();
            $url = $this->getDrBaseUrl()."v1/shoppers/me/payment-options?expand=all&format=json";
            
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->addHeader("Authorization", "Bearer " . $accessToken);
            $this->curl->get($url);
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
        }
        return $result;
    }
    /**
     * @param  mixed $data
     * @return mixed|null
     */
    public function encryptRequest($data)
    {
        $key = $this->getEncryptionKey();
        $method = 'AES-128-CBC';
        $encrypt = trim(openssl_encrypt($data, $method, $key, 0, $key));
        return $encrypt;
    }
	
    /**
     * @return array|null
     */
    public function getDrCart()
    {
		return '';		
	}
    /**
     * @param  mixed $accessToken
     * @return mixed|null
     */
    public function deleteDrCartItems($accessToken)
    {
        if ($accessToken && $this->getDrBaseUrl()) {
            $url = $this->getDrBaseUrl()."v1/shoppers/me/carts/active/line-items?format=json";
            $request = new \Zend\Http\Request();
            $httpHeaders = new \Zend\Http\Headers();
            $client = new \Zend\Http\Client();
            $httpHeaders->addHeaders(
                [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
                ]
            );
            $request->setHeaders($httpHeaders);
            $request->setMethod(\Zend\Http\Request::METHOD_DELETE);
            $request->setUri($url);
            $response = $client->send($request);
        }
        return;
    }
    /**
     * @param  mixed $accessToken
     * @return mixed|null
     */
    public function applyShopperToCart($accessToken)
    {
        if ($this->getDrBaseUrl() && $accessToken) {
            $url = $this->getDrBaseUrl()."v1/shoppers/me/carts/active/apply-shopper?format=json";
            $data = [];
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->addHeader("Authorization", "Bearer " . $accessToken);
            $this->curl->post($url, $data);
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
            return $result;
        }
        return;
    }
    /**
     * @param  mixed $accessToken
     * @return mixed|null
     */
    public function createOrderInDr($accessToken)
    {
        if ($this->getDrBaseUrl() && $accessToken) {
			$ip = $this->remoteAddress->getRemoteAddress();
            $url = $this->getDrBaseUrl()."v1/shoppers/me/carts/active/submit-cart?expand=all&format=json&ipAddress=".$ip;
            $data = [];
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 60);
            $this->curl->addHeader("Authorization", "Bearer " . $accessToken);
            $this->curl->post($url, $data);
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
            $this->_logger->info("createOrderInDr Result :".json_encode($result));
            return $result;
        }
        return;
    }
    
    /**
     * Execute operation
     *
     * @param  Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function createOrderInMagento($quote)
    {
        if ($this->getCheckoutMethod($quote) === \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote($quote);
        }

        $quote->collectTotals();
        $orderId = $this->_cartManagement->placeOrder($quote->getId());
        return $orderId;
    }

    /**
     * Get checkout method
     *
     * @param  Quote $quote
     * @return string
     */
    private function getCheckoutMethod($quote)
    {
        if ($this->_customerSession->isLoggedIn()) {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_REGISTER);
            }
        }
        return $quote->getCheckoutMethod();
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @param  Quote $quote
     * @return void
     */
    private function prepareGuestQuote($quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
    }
    /**
     *
     * @return type
     */
    public function postDrRequest($order)
    {
        if ($order->getDrOrderId()) {
            $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
			if(!$drModel->getId()){
				return;
			}
            if ($drModel->getPostStatus() == 1) {
                return;
            }
            $storeCode = $order->getStore()->getCode();
            $url = $this->getDrPostUrl($storeCode);
            $fulFillmentPost = $this->getFulFillmentPostRequest($order, $storeCode);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 40);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->post($url, $fulFillmentPost);
            $result = $this->curl->getBody();
			$statusCode = $this->curl->getStatus();
            if ($statusCode == "200") {
                $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                $drModel->setPostStatus(1);
                $drModel->save();
            }
            return $statusCode;
            //return $xml;
        }
    }
    /**
     *
     * @param type $order
     * @return type
     */
    public function getFulFillmentPostRequest($order, $storeCode = null)
    {

        $status = '';
        $responseCode = '';
        switch ($order->getStatus()) {
            case 'complete':
                $status = "Completed";
                $responseCode = "Success";
                break;
            case 'canceled':
                $status = "Cancelled";
                $responseCode = "Cancelled";
                break;
            case 'pending':
                $status = "Pending";
                $responseCode = "Pending";
                break;
        }

        $drConnector = $this->drFactory->create();

        $drObj = $drConnector->load($order->getDrOrderId(), 'requisition_id');
        $items = [];
        if ($drObj->getId()) {
            $lineItems = $this->jsonHelper->jsonDecode($drObj->getLineItemIds());
            foreach ($lineItems as $item) {
                $items['item'][] = 
                    ["requisitionID" => $order->getDrOrderId(),
                        "noticeExternalReferenceID" => $order->getIncrementId(),
                        "lineItemID" => $item['lineitemid'],
                        "fulfillmentCompanyID" => $this->getCompanyId($storeCode),
                        "electronicFulfillmentNoticeItems" => [
                            "item" => [
                                [
                                    "status" => $status,
                                    "reasonCode" => $responseCode,
                                    "quantity" => $item['qty'],
                                    "electronicContentType" => "EntitlementDetail",
                                    "electronicContent" => "magentoEventID"
                                ]
                            ]
                        ]
                    ];
            }
        }
        $request['ElectronicFulfillmentNoticeArray'] = $items;
        return $this->jsonHelper->jsonEncode($request);
    }
	/**
     *
     * @return type
     */
    public function cancelDROrder($quote, $result){		
        if ($quote->getId()) {
            $url = $this->getDrPostUrl();
			$status = "Cancelled";
			$responseCode = "Cancelled";
			$items = [];
            $lineItems = $result["submitCart"]['lineItems']['lineItem'];
			$orderId = $result["submitCart"]["order"]["id"];
            foreach ($lineItems as $item) {
                $items['item'][] = 
                    ["requisitionID" => $orderId,
                        "noticeExternalReferenceID" => $quote->getId(),
                        "lineItemID" => $item['id'],
                        "fulfillmentCompanyID" => $this->getCompanyId(),
                        "electronicFulfillmentNoticeItems" => [
                            "item" => [
                                [
                                    "status" => $status,
                                    "reasonCode" => $responseCode,
                                    "quantity" => $item['quantity'],
                                    "electronicContentType" => "EntitlementDetail",
                                    "electronicContent" => "magentoEventID"
                                ]
                            ]
                        ]
                    ];
            }
			$request['ElectronicFulfillmentNoticeArray'] = $items;
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 40);
            $this->curl->addHeader("Content-Type", "application/json");
            $this->curl->post($url, $this->jsonHelper->jsonEncode($request));
            $result = $this->curl->getBody();
		}
	}

    /**
     *
     * @return type
     */
    public function initiateRefundRequest($creditmemo)
    {
        $order = $creditmemo->getOrder();
        $flag = false;
        if ($order->getDrOrderId()) {
            $storeCode = $order->getStore()->getCode();
            $url = $this->getDrRefundUrl($storeCode)."orders/".$order->getDrOrderId()."/refunds";
            $token = $this->generateRefundToken($storeCode);
            if ($token) {
				$grandTotal = round($creditmemo->getGrandTotal(), 2);
				$shippingAmount = round($creditmemo->getShippingInclTax(), 2);
				if($shippingAmount > 0){
					$data = ["type" => "orderRefund", "category" => "ORDER_LEVEL_SHIPPING", "reason" => "VENDOR_APPROVED_REFUND", "comments" => "Unhappy with the product", "refundAmount" => ["currency" => $order->getOrderCurrencyCode(), "value" => $shippingAmount]];
					$response = $this->curlRefundRequest($order->getDrOrderId(), $data, $token, $storeCode);
					if(!$response) return $response;
					$grandTotal = $grandTotal - $shippingAmount;
				}
				if($grandTotal > 0){
					$data = ["type" => "orderRefund", "category" => "ORDER_LEVEL_PRODUCT", "reason" => "VENDOR_APPROVED_REFUND", "comments" => "Unhappy with the product", "refundAmount" => ["currency" => $order->getOrderCurrencyCode(), "value" => $grandTotal]];
					$response = $this->curlRefundRequest($order->getDrOrderId(), $data, $token, $storeCode);
					if(!$response) return $response;
				}
				$flag = true;
                return $flag;
            }
        }
        return $flag;
    }
	
	public function curlRefundRequest($drOrderId, $data, $token, $storeCode)
	{
		$flag = true;
		$this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
		$this->curl->setOption(CURLOPT_TIMEOUT, 40);
		$this->curl->addHeader("Content-Type", "application/json");
		$this->curl->addHeader("x-siteid", $this->getCompanyId($storeCode));
		$this->curl->addHeader("Authorization", "Bearer " . $token);
		$url = $this->getDrRefundUrl($storeCode)."orders/".$drOrderId."/refunds";
		$this->curl->post($url, json_encode($data));
		$this->_logger->info("Refund Request :".json_encode($data));
		$result = $this->curl->getBody();
		$result = json_decode($result, true);
		if (isset($result['errors']) && count($result['errors'])>0) {
			$this->_logger->error("Refund Error :".json_encode($result));
			$flag = false;
		}
		return $flag;
	}
    /**
     *
     * @return type
     */
    public function generateRefundToken($storeCode = null)
    {
        $token = '';
        if ($this->getDrBaseUrl($storeCode) && $this->getDrRefundUsername($storeCode) && $this->getDrRefundPassword($storeCode) && $this->getDrRefundAuthUsername($storeCode) && $this->getDrRefundAuthPassword($storeCode)) {
            $url = $this->getDrBaseUrl($storeCode).'auth';

            $data = ["grant_type" => "password", "username" => $this->getDrRefundUsername($storeCode), "password" => $this->getDrRefundPassword($storeCode)];

            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 40);
            $this->curl->setOption(CURLOPT_USERPWD, $this->getDrRefundAuthUsername($storeCode) . ":" . $this->getDrRefundAuthPassword($storeCode));
            $this->curl->addHeader("Content-Type", 'application/x-www-form-urlencoded');
            $this->curl->addHeader("x-siteid", $this->getCompanyId($storeCode));
            $this->curl->post($url, http_build_query($data));
            $result = $this->curl->getBody();
            $result = json_decode($result, true);
            $token = '';
            if (isset($result["access_token"])) {
                $token = $result["access_token"];
            }

        }
        return $token;
    }

    /**
     *
     * @return type
     */
    public function getDrPostUrl($storecode = null)
    {
        return $this->scopeConfig->getValue('dr_settings/config/dr_post_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }

    /**
     *
     * @return type
     */
    public function getDrRefundUrl($storecode = null)
    {
        return $this->scopeConfig->getValue('dr_settings/config/dr_refund_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }

    /**
     *
     * @return type
     */
    public function getCompanyId($storecode = null)
    {
        return $this->scopeConfig->getValue('dr_settings/config/company_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }

    public function getDrRefundUsername($storecode = null)
    {
        return $this->scopeConfig->getValue('dr_settings/config/dr_refund_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }

    public function getDrRefundPassword($storecode = null)
    {
        $dr_refund_pass = $this->scopeConfig->getValue('dr_settings/config/dr_refund_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
        return $this->_enc->decrypt($dr_refund_pass);
    }

    public function getDrRefundAuthUsername($storecode = null)
    {
        return $this->scopeConfig->getValue('dr_settings/config/dr_refund_auth_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }

    public function getDrRefundAuthPassword($storecode = null)
    {
        $dr_auth_pass = $this->scopeConfig->getValue('dr_settings/config/dr_refund_auth_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
        return $this->_enc->decrypt($dr_auth_pass);
    }

    /**
     * @return mixed|null
     */
    public function getIsEnabled($storecode = null)
    {
        $key_enable = 'dr_settings/config/active';
        return $this->scopeConfig->getValue($key_enable, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getDrStoreUrl($storecode = null)
    {
        $key_token_url = 'dr_settings/config/session_token_url';
        return $this->scopeConfig->getValue($key_token_url, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getDrBaseUrl($storecode = null)
    {
        $url_key = 'dr_settings/config/dr_url';
        return $this->scopeConfig->getValue($url_key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getDrApiKey($storecode = null)
    {
        $dr_key_api = $this->scopeConfig->getValue('dr_settings/config/dr_api_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
        return $this->_enc->decrypt($dr_key_api);
    }
    /**
     * @return mixed|null
     */
    public function getDrAuthUsername($storecode = null)
    {
        $dr_auth_name = 'dr_settings/config/dr_auth_username';
        return $this->scopeConfig->getValue($dr_auth_name, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getDrAuthPassword($storecode = null)
    {
        $dr_auth_pass = $this->scopeConfig->getValue('dr_settings/config/dr_auth_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
        return $this->_enc->decrypt($dr_auth_pass);
    }

    /**
     * @return mixed|null
     */
    public function getIsTestOrder($storecode = null)
    {
        $dr_test_key = 'dr_settings/config/testorder';
        return $this->scopeConfig->getValue($dr_test_key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getEncryptionKey($storecode = null)
    {
        $dr_encrypt_key = 'dr_settings/config/encryption_key';
        return $this->scopeConfig->getValue($dr_encrypt_key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getLocale($storecode = null)
    {
        $dr_locale = 'dr_settings/config/locale';
        return $this->scopeConfig->getValue($dr_locale, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    /**
     * @return mixed|null
     */
    public function getShippingOfferId($storecode = null)
    {
        $dr_offer = 'dr_settings/config/offer_id';
        return $this->scopeConfig->getValue($dr_offer, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storecode);
    }
    
    /**
     * Function to validate Quote for any errors, As in some cases Magento encounters an exception. 
     * To avoid this, Quote is validated before proceeding for order processing
     * 
     * @param object $quote
     * @return bool $isValidQuote
     **/
    public function validateQuote(\Magento\Quote\Model\Quote $quote) {
        $isValidQuote = false;
        
        try {
            $errors         = $quote->getErrors();
            $isValidQuote   = (empty($errors)) ? true : false;
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error($this->jsonHelper->jsonEncode($le->getRawMessage()));
        } catch (\Exception $e) {
            $this->_logger->error($this->jsonHelper->jsonEncode($e->getMessage()));
        } // end: try
        
        return $isValidQuote;                
    } // end: functoin validateQuote
    
    /**
     * Function to fetch Billing & Shipping address from DR order creation response
     * 
     * @param string $type | 'billing' or 'shipping'
     * @param array $drResponse
     * 
     * @return array $returnAddress
     */
    public function getDrAddress($type, $drResponse) {
        $returnAddress  = [];   
        $drAddress      = null;

        if(!empty($type) && !empty($drResponse['submitCart'])) {         
            if($type == 'billing') {
                $drAddress = isset($drResponse['submitCart']['billingAddress']) ? $drResponse['submitCart']['billingAddress'] : null;
            } else if($type == 'shipping') {
                $drAddress = isset($drResponse['submitCart']['shippingAddress']) ? $drResponse['submitCart']['shippingAddress'] : null;
            } else {
                $this->_logger->error('Address Type missing');
                return $returnAddress;
            } // end: if 
        } // end: if

        if(!empty($drAddress) && is_array($drAddress)) {            
            $addressFields = ['firstName', 'line1', 'city', 'countrySubdivision', 'postalCode', 'country', 'phoneNumber'];
            
            if(count(array_diff($addressFields, array_keys($drAddress))) == 0) {
                // Get Region details
                $region = $this->regionModel->loadByCode($drAddress['countrySubdivision'], $drAddress['country'])->getData();

                $street = $drAddress['line1'];
                $street .= (!empty($drAddress['line2'])) ? (' '.$drAddress['line2']) : null;
                $street .= (!empty($drAddress['line3'])) ? (' '.$drAddress['line3']) : null;

                $street = trim($street);
                $phone  = str_replace('-', '', $drAddress['phoneNumber']);

                $returnAddress = [
                    'firstname'     => (!empty($drAddress['firstName'])) ? trim($drAddress['firstName']) : null,
                    'lastname'      => (!empty($drAddress['lastName'])) ? trim($drAddress['lastName']) : null,
                    'street'        => $street,
                    'city'          => $drAddress['city'],
                    'postcode'      => $drAddress['postalCode'],
                    'country_id'    => $drAddress['country'],
                    'region'        => !empty($region['name']) ? $region['name'] : null,
                    'region_id'     => !empty($region['region_id']) ? $region['region_id'] : null,
                    'telephone'     => $phone
                ];
            } else {
                $this->_logger->error('Mandatory Address Details missing');
            }// end: if
        } // end: if
        
        return $returnAddress;
    } // end: function getDrAddress  
    
    /**
     * Function to send EFN request to DR when Invoice/Shipment created from Magento Admin
     * Only Invoice/Shipment Success cases are sent
     * 
     * @param array $lineItems
     * @param object $order
     * 
     * @return array $result
     */
    public function createFulfillmentRequestToDr($lineItems, $order) {
        $items      = [];
        $request    = [];
        $status         = 'Completed';
        $responseCode   = 'Success';   
        
        try {
            if ($order->getDrOrderId()) {
                $storeCode = $order->getStore()->getCode();
                $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');

                if(!$drModel->getId() || $drModel->getPostStatus() == 1) {
                    return;
                } // end: if
                
                foreach ($lineItems as $itemId => $item) {
                    $items['item'][] = [
                        "requisitionID"             => $item['requisitionID'],
                        "noticeExternalReferenceID" => $item['noticeExternalReferenceID'],
                        "lineItemID"                => $itemId,
                        "fulfillmentCompanyID"      => $this->getCompanyId($storeCode),
                        "electronicFulfillmentNoticeItems" => [
                            "item" => [
                                [
                                    "status"                => $status,
                                    "reasonCode"            => $responseCode,
                                    "quantity"              => $item['quantity'],
                                    "electronicContentType" => "EntitlementDetail",
                                    "electronicContent"     => "magentoEventID"
                                ]
                            ]
                        ]
                    ];
                } // end: foreach

                $request['ElectronicFulfillmentNoticeArray'] = $items;

                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->setOption(CURLOPT_TIMEOUT, 40);
                $this->curl->addHeader("Content-Type", "application/json");
                $this->curl->post($this->getDrPostUrl($storeCode), $this->jsonHelper->jsonEncode($request));
                $result     = $this->curl->getBody();
                $statusCode = $this->curl->getStatus();

                // Status Update: Exsisting code used according to review changes
                if ($statusCode == '200') {
                    // Post Status updated only if entire order items are fulfilled
                    if($this->getPendingFulfillment($order)) {
                        // if all the quantites are satisfied then mark as 1
                        $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');
                        $drModel->setPostStatus(1);
                        $drModel->save();
                    } // end: if
                    $comment = 'Magento & DR order status are matched';
                } else {
                    $comment = 'Magento & DR order status are mis-matched';
                } // end: if

                $order->addStatusToHistory($order->getStatus(), __($comment));

                $this->_logger->info('createFulfillmentRequestToDr Request : '.json_encode($request));
                $this->_logger->info('createFulfillmentRequestToDr Response : '.json_encode($result));        
            } else {
                $this->_logger->error('Error createFulfillmentRequestToDr : Empty DR Order Id');
            } // end: if
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error createFulfillmentRequestToDr : '.json_encode($le->getRawMessage()));
        } catch (\Exception $ex) {
            $this->_logger->error('Error createFulfillmentRequestToDr : '. $ex->getMessage());
        } // end: try       
        
        return $result;
    } // end: function createFulfillmentRequestToDr
    
    /**
     * Function to check order has any items to Invoice or Ship
     * 
     * @var object $orderObj
     * 
     * @return boolean true/false
     *
     */
    public function getPendingFulfillment($orderObj) {
        try {
            $canInvoice = $orderObj->canInvoice(); // returns true for pending items
            $canShip    = $orderObj->canShip();  // returns true for pending items
            
            // Return true if both invoice and shipment are false, i.e. No items to fulfill
            return (empty($canInvoice) && empty($canShip));
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error getInvoicesOrShipmentsList : '.json_encode($le->getRawMessage()));
        } catch (\Exception $ex) {
            $this->_logger->error('Error getInvoicesOrShipmentsList : '.$ex->getMessage());
            return false;
        } // end: try    
    } // end: function getPendingFulfillment   
    
    /**
     * Function to send EFN request to DR when @OrderItem is cancelled from Magento Admin
     * 
     * @param array $lineItems
     * @param object $order
     * 
     * @return array $result
     */
    public function cancelFulfillmentRequestToDr($lineItems, $order) {
        $items      = [];
        $request    = [];
        $status         = 'Cancelled';
        $responseCode   = 'Cancelled'; 
        
        try {
            if ($order->getDrOrderId()) {
                $storeCode = $order->getStore()->getCode();
                $drModel = $this->drFactory->create()->load($order->getDrOrderId(), 'requisition_id');

                if(!$drModel->getId() || $drModel->getPostStatus() == 1) {
                    return;
                } // end: if
                
                foreach ($lineItems as $itemId => $item) {
                    $items['item'][] = [
                        "requisitionID"             => $item['requisitionID'],
                        "noticeExternalReferenceID" => $item['noticeExternalReferenceID'],
                        "lineItemID"                => $itemId,
                        "fulfillmentCompanyID"      => $this->getCompanyId($storeCode),
                        "electronicFulfillmentNoticeItems" => [
                            "item" => [
                                [
                                    "status"                => $status,
                                    "reasonCode"            => $responseCode,
                                    "quantity"              => $item['quantity'],
                                    "electronicContentType" => "EntitlementDetail",
                                    "electronicContent"     => "magentoEventID"
                                ]
                            ]
                        ]
                    ];
                } // end: foreach

                $request['ElectronicFulfillmentNoticeArray'] = $items;

                $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
                $this->curl->setOption(CURLOPT_TIMEOUT, 40);
                $this->curl->addHeader("Content-Type", "application/json");
                $this->curl->post($this->getDrPostUrl($storeCode), $this->jsonHelper->jsonEncode($request));
                $result     = $this->curl->getBody();
                $statusCode = $this->curl->getStatus();

                // Status Update: Exsisting code used according to review changes
                if ($statusCode == '200') {
                    $comment = 'Order cancellation pushed to DR';
                    $order->addStatusToHistory($order->getStatus(), __($comment));
                } // end: if               

                $this->_logger->info('cancelFulfillmentRequestToDr Request : '.json_encode($request));
                $this->_logger->info('cancelFulfillmentRequestToDr Response : '.json_encode($result));        
            } else {
                $this->_logger->error('Error cancelFulfillmentRequestToDr : Empty DR Order Id');
            } // end: if
        } catch (\Magento\Framework\Exception\LocalizedException $le) {
            $this->_logger->error('Error cancelFulfillmentRequestToDr : '.json_encode($le->getRawMessage()));
        } catch (\Exception $ex) {
            $this->_logger->error('Error cancelFulfillmentRequestToDr : '. $ex->getMessage());
        } // end: try       
        
        return $result;
    } // end: function cancelFulfillmentRequestToDr
}

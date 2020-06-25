<?php
/**
 * DrPay Observer
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
 
namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use \Magento\Sales\Model\Order as Order;

/**
 *  CreateDrOrder
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
class UpdateOrderDetails implements ObserverInterface
{
        /**
         * @param \Digitalriver\DrPay\Helper\Data            $helper
         * @param \Magento\Checkout\Model\Session            $session
         * @param \Magento\Store\Model\StoreManagerInterface $storeManager
         */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $helper,
        \Magento\Checkout\Model\Session $session,
		\Magento\Sales\Model\Order $order,
		\Digitalriver\DrPay\Model\DrConnector $drconnector,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
    ) {
        $this->helper =  $helper;
        $this->session = $session;
		$this->order = $order;
		$this->drconnector = $drconnector;
		$this->jsonHelper = $jsonHelper;
        $this->_storeManager = $storeManager;
		$this->currencyFactory = $currencyFactory;
		$this->scopeConfig = $scopeConfig;
		$this->priceCurrency = $priceCurrency;
    }

    /**
     * Create order
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
		$order = $observer->getEvent()->getOrder();
		$quote = $observer->getEvent()->getQuote();
		$result = $observer->getEvent()->getResult();
		$cartresult = $observer->getEvent()->getCartResult();
		//print_r($result);die;
		if(isset($result["submitCart"]["order"]["id"])){
			$orderId = $result["submitCart"]["order"]["id"];
			$order->setDrOrderId($orderId);
			$amount = $this->session->getDrTax();
			$tax_inclusive = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
//			if($tax_inclusive){
//				if(isset($result["submitCart"]["pricing"]["tax"]["value"])){
//					$amount = $result["submitCart"]["pricing"]["tax"]["value"];
//				}
//			}
			$order->setDrTax($amount);
			$order->setTaxAmount($amount);
			$order->setBaseTaxAmount($this->convertToBaseCurrency($amount));
			if($result["submitCart"]["order"]["orderState"]){
				$order->setDrOrderState($result["submitCart"]["order"]["orderState"]);
			}
			if(isset($result["submitCart"]['lineItems']['lineItem'])){
				$lineItems = $result["submitCart"]['lineItems']['lineItem'];
				$model = $this->drconnector->load($orderId, 'requisition_id');
				$model->setRequisitionId($orderId);
				$lineItemIds = array();
				foreach($lineItems as $item){
					$qty = $item['quantity'];
					$lineitemId = $item['id'];
					$lineItemIds[] = ['qty' => $qty,'lineitemid' => $lineitemId];
				}
				$model->setLineItemIds($this->jsonHelper->jsonEncode($lineItemIds));
				$model->save();
				$subtotal = 0;
				foreach ($order->getAllItems() as $orderitem) {		
					// get the magento lineitem quote ID
					$magentoItemId = $orderitem->getQuoteItemId();			
					
					foreach($lineItems as $item){						
						// loop thru the item's custom attributes to extract the magento_quote_item_id and the originalProductPrice
						$customAttributes = $item["customAttributes"]["attribute"];
						
						$drItemMagentoRefId = '';
						$originalProductPrice = 0;

						foreach($customAttributes as $customAttribute) {
							if($customAttribute["name"] == "magento_quote_item_id") {
								$drItemMagentoRefId = $customAttribute["value"];
							}
							if($customAttribute["name"] == "originalProductPrice") {
								$originalProductPrice = $customAttribute["value"];
							}
						}
						$item['originalProductPrice'] = $originalProductPrice;

						// if the DR cart's magento_quote_item_id == magentoItemId, then update the Items details
						if($drItemMagentoRefId == $magentoItemId){
							$this->updateDrItemsDetails($orderitem, $item, $tax_inclusive);
							$subtotal = $subtotal + $orderitem->getRowTotal();
							break;
						}
					}
				}
			}
			if($tax_inclusive){
				$order->setSubtotal($this->priceCurrency->round($subtotal));
				$order->setBaseSubtotal($this->priceCurrency->round(($this->convertToBaseCurrency($subtotal))));
				$order->setBaseShippingAmount($this->priceCurrency->round(($this->convertToBaseCurrency($order->getShippingAmount()))));
			}
			$order->save();
			$this->session->unsDrAccessToken();
			$this->session->unsSessionCheckSum();
			$this->session->unsDrResult();
			$this->session->unsGuestCustomerEmail();
			$this->session->unsDrQuoteId();
			$this->session->unsDrTax();

			$this->session->unsDrProductTax();
			$this->session->unsDrProductTotal();			
			$this->session->unsDrProductTotalExcl();

			$this->session->unsDrShippingTax();
			$this->session->unsDrShippingAndHandling();
			$this->session->unsDrShippingAndHandlingExcl();
			
			$this->session->unsDrOrderTotal();			
		}
    }

	public function updateDrItemsDetails($orderitem, $item, $tax_inclusive){		
		$orderitem->setDrOrderLineitemId($item['id']);
		$qty = $item['quantity'];
		$listprice = $item["pricing"];
		if(isset($listprice["productTax"]['value'])){
			$total_tax_amount = $listprice["productTax"]['value'];
			$tax_amount = $total_tax_amount/$qty;
			$orderitem->setTaxAmount($this->priceCurrency->round($total_tax_amount));
			$orderitem->setBaseTaxAmount($this->priceCurrency->round($this->convertToBaseCurrency($orderitem->getTaxAmount())));
			if(isset($listprice["taxRate"])){
				$orderitem->setTaxPercent($listprice["taxRate"] * 100);
			}
			if($tax_inclusive){
				$discountAmount = $orderitem->getDiscountAmount();
				($discountAmount) OR $discountAmount = 0;
				$salePrice = $item['originalProductPrice'];
				if(isset($listprice["taxRate"]) && $listprice["taxRate"] > 0) {
					$salePrice = $item['originalProductPrice'] / (1 + $listprice["taxRate"]);
				}
				$orderitem->setDiscountTaxCompensationAmount(0);
				$orderitem->setBaseDiscountTaxCompensationAmount(0);
				$orderitem->setPrice($salePrice);
				$orderitem->setBasePrice($this->convertToBaseCurrency($orderitem->getPrice()));
				$salePriceWithQuantity = $listprice["salePriceWithQuantity"]['value'] - $total_tax_amount + $discountAmount;
				$orderitem->setRowTotal($this->priceCurrency->round($salePriceWithQuantity));
				$orderitem->setBaseRowTotal($this->priceCurrency->round($this->convertToBaseCurrency($orderitem->getRowTotal())));
				$orderitem->setRowTotalInclTax($this->priceCurrency->round($listprice["salePriceWithQuantity"]['value'] + $discountAmount));
				$orderitem->setBaseRowTotalInclTax($this->priceCurrency->round($this->convertToBaseCurrency($orderitem->getRowTotalInclTax())));
			}else{
				$orderitem->setPriceInclTax($orderitem->getPrice() + $tax_amount);
				$orderitem->setBasePriceInclTax($this->convertToBaseCurrency($orderitem->getPriceInclTax()));
				$orderitem->setRowTotalInclTax($this->priceCurrency->round($orderitem->getRowTotal() + $total_tax_amount));
				$orderitem->setBaseRowTotalInclTax($this->priceCurrency->round($this->convertToBaseCurrency($orderitem->getRowTotalInclTax())));
			}
		}
	}

	public function convertToBaseCurrency($price){
        $currentCurrency = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
        $baseCurrency = $this->_storeManager->getStore()->getBaseCurrency()->getCode();
        $rate = $this->currencyFactory->create()->load($currentCurrency)->getAnyRate($baseCurrency);
        $returnValue = $price * $rate;
        return $returnValue;
    }
}
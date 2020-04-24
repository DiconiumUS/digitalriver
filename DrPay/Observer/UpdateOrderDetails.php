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
            \Psr\Log\LoggerInterface $logger
    ) {
        $this->helper =  $helper;
        $this->session = $session;
		$this->order = $order;
		$this->drconnector = $drconnector;
		$this->jsonHelper = $jsonHelper;
        $this->_storeManager = $storeManager;
		$this->currencyFactory = $currencyFactory;
		$this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
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
        $paymentResult = $observer->getEvent()->getPaymentResult();                
		//print_r($result);die;
		if(isset($result["submitCart"]["order"]["id"])){
			// Update Order's Shipping Address details
			if(!empty($paymentResult) && !$quote->isVirtual()) {
				$shippingAddress = $this->helper->getPaymentShippingAddress($paymentResult);
				if(!empty($shippingAddress)) {
					$order->getShippingAddress()->addData($shippingAddress);
				} // end: if
			} // end: if
                    
			if(isset($result["submitCart"]['paymentMethod']['wireTransfer'])){
				$paymentData = $result["submitCart"]['paymentMethod']['wireTransfer'];
				$order->getPayment()->setAdditionalInformation($paymentData);
			}
			$orderId = $result["submitCart"]["order"]["id"];
			$order->setDrOrderId($orderId);
			$amount = $quote->getDrTax();
			$tax_inclusive = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
			if($tax_inclusive){
				if(isset($result["submitCart"]["pricing"]["tax"]["value"])){
					$amount = $result["submitCart"]["pricing"]["tax"]["value"];
				}
			}
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
					$lineItems = $cartresult["cart"]['lineItems']['lineItem'];
					foreach($lineItems as $item){
						if($item["customAttributes"]["attribute"][0]["name"] == "magento_quote_item_id"){
							$drItemMagentoRefId = $item["customAttributes"]["attribute"][0]["value"];
							$magentoItemId = $orderitem->getQuoteItemId();
						}else{
							$drItemMagentoRefId = $item["product"]["externalReferenceId"];
							$magentoItemId = $orderitem->getSku();
						}
						if($drItemMagentoRefId == $magentoItemId){
							$this->updateDrItemsDetails($orderitem, $item, $tax_inclusive);
							$subtotal = $subtotal + $orderitem->getRowTotal();
							break;
						}
					}
				}
			}
			if($tax_inclusive){
				$order->setSubtotal($subtotal);
				$order->setBaseSubtotal($this->convertToBaseCurrency($subtotal));
			}
			$order->save();
			$this->session->setDrAccessToken('');
			$this->session->setSessionCheckSum('');
			$this->session->setDrResult('');
			$this->session->setGuestCustomerEmail('');
			$this->session->setDrQuoteId('');
			$this->session->setDrTax('');
			$this->session->setDrShipping('');
			$this->session->setMagentoAppliedTax('');
		}
    }

	public function updateDrItemsDetails($orderitem, $item, $tax_inclusive){		
		$orderitem->setDrOrderLineitemId($item['id']);
		$qty = $item['quantity'];
		$listprice = $item["pricing"];
		if(isset($listprice["tax"]['value'])){
			$total_tax_amount = $listprice["tax"]['value'];
			$tax_amount = $total_tax_amount/$qty;
			$orderitem->setTaxAmount($total_tax_amount);
			$orderitem->setBaseTaxAmount($this->convertToBaseCurrency($total_tax_amount));
			if(isset($listprice["taxRate"])){
				$orderitem->setTaxPercent($listprice["taxRate"] * 100);
			}
			if($tax_inclusive){				
				$orderitem->setPrice($listprice["salePrice"]['value']);
				$orderitem->setBasePrice($this->convertToBaseCurrency($orderitem->getPrice()));
				//$orderitem->setOriginalPrice($orderitem->getPrice());
				//$orderitem->setBaseOriginalPrice($orderitem->getBasePrice());
				$orderitem->setRowTotal($orderitem->getPrice() * $qty);
				$orderitem->setBaseRowTotal($this->convertToBaseCurrency($orderitem->getRowTotal()));
				/*
				$orderitem->setPrice($orderitem->getPriceInclTax() - $tax_amount);
				$orderitem->setBasePrice($this->convertToBaseCurrency($orderitem->getPrice()));
				//$orderitem->setOriginalPrice($orderitem->getPrice());
				//$orderitem->setBaseOriginalPrice($orderitem->getBasePrice());
				$orderitem->setRowTotal($orderitem->getRowTotalInclTax() - $total_tax_amount);
				$orderitem->setBaseRowTotal($this->convertToBaseCurrency($orderitem->getRowTotal()));
				*/
			}else{
				$orderitem->setPriceInclTax($orderitem->getPrice() + $tax_amount);
				$orderitem->setBasePriceInclTax($this->convertToBaseCurrency($orderitem->getPriceInclTax()));
				$orderitem->setRowTotalInclTax($orderitem->getRowTotal() + $total_tax_amount);
				$orderitem->setBaseRowTotalInclTax($this->convertToBaseCurrency($orderitem->getRowTotalInclTax()));
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
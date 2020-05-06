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
class UpdateOrderStatus implements ObserverInterface
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
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->helper =  $helper;
        $this->session = $session;
		$this->order = $order;
        $this->_storeManager = $storeManager;
		$this->currencyFactory = $currencyFactory;
		$this->scopeConfig = $scopeConfig;
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
		$orderId = $observer->getEvent()->getOrderIds();
        $order = $this->order->load($orderId);
        if($order->getDrOrderId()){
            if($order->getDrOrderState() == "Submitted"){ 
                $order->setState(Order::STATE_PROCESSING); 
                $order->setStatus(Order::STATE_PROCESSING);
            }else if($order->getDrOrderState() == "Source Pending Funds"){ 
                $order->setState(Order::STATE_PENDING_PAYMENT); 
                $order->setStatus(Order::STATE_PENDING_PAYMENT);
            }else{ 
                $order->setState(Order::STATE_PAYMENT_REVIEW); 
                $order->setStatus(Order::STATE_PAYMENT_REVIEW);
            }
			$tax_inclusive = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
			foreach ($order->getAllVisibleItems() as $orderitem) {
				if($orderitem->getProductType() == \Magento\Bundle\Model\Product\Type::TYPE_CODE){
					$parent_tax_amount = 0;
					foreach($orderitem->getChildrenItems() as $childitem){						
						$child_tax_amount = $childitem->getPriceInclTax() - $childitem->getPrice();
						if($child_tax_amount > 0){
							$qty = $childitem->getQtyOrdered();
							$parent_tax_amount = $parent_tax_amount + ($child_tax_amount * $qty);
						}
					}
					if($parent_tax_amount > 0){
						$qty = $orderitem->getQtyOrdered();
						$total_tax_amount = $parent_tax_amount * $qty;
						$orderitem->setTaxAmount($total_tax_amount);
						$orderitem->setBaseTaxAmount($this->convertToBaseCurrency($total_tax_amount));
						if($tax_inclusive){
							$orderitem->setPrice($orderitem->getPriceInclTax() - $parent_tax_amount);
							$orderitem->setBasePrice($this->convertToBaseCurrency($orderitem->getPrice()));
							$orderitem->setRowTotal($orderitem->getPrice() * $qty);
							$orderitem->setBaseRowTotal($this->convertToBaseCurrency($orderitem->getRowTotal()));
						}else{
							$orderitem->setPriceInclTax($orderitem->getPrice() + $parent_tax_amount);
							$orderitem->setBasePriceInclTax($this->convertToBaseCurrency($orderitem->getPriceInclTax()));
							$orderitem->setRowTotalInclTax($orderitem->getRowTotal() + $total_tax_amount);
							$orderitem->setBaseRowTotalInclTax($this->convertToBaseCurrency($orderitem->getRowTotalInclTax()));		
						}
					}
				}
			}
            $order->save();
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
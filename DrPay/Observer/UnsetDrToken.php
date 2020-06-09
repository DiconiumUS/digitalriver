<?php
/**
 * DrPay Observer
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
 
namespace Digitalriver\DrPay\Observer;

use Magento\Framework\Event\ObserverInterface;

/**
 *  UnsetDrToken
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
class UnsetDrToken implements ObserverInterface
{
	/**
	 * @param \Digitalriver\DrPay\Helper\Data  $drHelper
	 * @param \Magento\Checkout\Model\Session  $session
	 */
    public function __construct(
        \Digitalriver\DrPay\Helper\Data $drHelper,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->drHelper =  $drHelper;
        $this->session = $session;
    }

    /**
     * Create Cart
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
		$enableDrPayValue = $this->drHelper->getIsEnabled();
        if ($enableDrPayValue) {
			$accessToken = $this->session->getDrAccessToken();
			if(!empty($accessToken)){
				$this->session->unsDrAccessToken();
			}
		}
    }
}

<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Digitalriver\DrPay\Plugin\Currency;


class SwitchAction
{
	protected $storeManager;
	protected $session;

	public function __construct(
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Checkout\Model\Session $session,
		\Digitalriver\DrPay\Helper\Data $helper
	) {
		$this->storeManager= $storeManager;
		$this->session = $session;
		$this->helper = $helper;
	}

    /**
     * @param \Magento\Directory\Controller\Currency\Switch $subject
     * @param array $result
     * @return array
     */

    public function afterExecute(\Magento\Directory\Controller\Currency\SwitchAction $subject, $result)
    {
        $currentCurrency = $this->storeManager->getStore()->getCurrentCurrencyCode();
		$accessToken = $this->session->getDrAccessToken();
		if($accessToken){
			$this->helper->updateAccessTokenCurrency($accessToken, $currentCurrency);
		}
    }
}

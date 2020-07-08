<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Tax Total Row Renderer
 */
namespace Digitalriver\DrPay\Block\Checkout;

class Discount extends \Magento\Checkout\Block\Total\DefaultTotal
{
    /**
     * @var string
     */
    protected $_template = 'Digitalriver_DrPay::checkout/discount.phtml';
}

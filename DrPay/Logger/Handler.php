<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Logger;

/**
 * Class Handler
 */
class Handler extends \Magento\Framework\Logger\Handler\Base
{
	protected $loggerType = Logger::INFO; 
    protected $fileName = '/var/log/drlog.log';

}

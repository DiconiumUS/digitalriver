<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Logger;

/**
 * Class Logger
 */
class Logger extends \Monolog\Logger
{
	/**
     * {@inheritdoc}
     */
    public function __construct($name, array $handlers = [], array $processors = [], \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        /**
         * TODO: This should be eliminated with MAGETWO-53989
         */
        $handlers = array_values($handlers);
		$this->scopeConfig = $scopeConfig;

        parent::__construct($name, $handlers, $processors);
    }
    /**
     * Adds a log record.
     *
     * @param  int     $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return bool Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = array())
    {
		$debug = $this->scopeConfig->getValue('dr_settings/config/debug', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		if($debug){
			return parent::addRecord($level, $message, $context);
		}
		return;
	}
}

<?php
/**
 *
 * @category Digitalriver
 * @package  Digitalriver_DrPay
 */
namespace Digitalriver\DrPay\Controller\Fulfillment;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Index
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Digitalriver\DrPay\Model\DrConnectorRepositoryFactory $drConnectorRepositoryFactory
     */

    public function __construct(
        Context $context,
        \Digitalriver\DrPay\Model\DrConnectorRepositoryFactory $drConnectorRepositoryFactory
    ) {
        $this->drConnectorRepositoryFactory = $drConnectorRepositoryFactory;
        return parent::__construct($context);
    }
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    public function execute()
    {
        $data = $this->getRequest()->getContent();
        $data = utf8_encode($data);
        $data = json_decode($data, true);
        $responseContent = [];
        if (is_array($data) && isset($data['OrderLevelElectronicFulfillmentRequest'])) {
            $orderLevelElectronicFulfillmentRequest = $data['OrderLevelElectronicFulfillmentRequest'];
            $responseContent = $this->drConnectorRepositoryFactory->Create()->saveFulFillment($orderLevelElectronicFulfillmentRequest);
            $responseContent = $responseContent;
        } else {
            $responseContent = ["error" => "Invalid Request"];
        }
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        return $response;
    }
}

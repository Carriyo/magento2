<?php
/**
 */

namespace Carriyo\Shipment\Controller\Webhook;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Configuration;
use Carriyo\Shipment\Model\Helper;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;

class OrderUpdate extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\Request
     */
    private $request;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\HTTP\PhpEnvironment\Request $request
     * @param Helper $helper
     * @param Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\HTTP\PhpEnvironment\Request $request,
        Helper $helper,
        Configuration $configuration,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->configuration = $configuration;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $this->logger->info("Carriyo webhook invoked (OrderUpdated): " . $this->request->getContent());

        if (stripos((string)$this->request->getHeader('Content-Type'), 'application/json') !== 0) {
            $this->returnHttpResponse(400);
        }

        $payload = json_decode($this->request->getContent(), true);
        if (empty($payload)) {
            $this->returnHttpResponse(400);
        }

        try {
            if ($this->configuration->isShipmentOnlyFlow()) {
                $this->returnHttpResponse(200, 'IGNORED');
            }
            $this->helper->syncOrderStatus($payload);
            $this->returnHttpResponse(200, 'SYNCED');
        } catch (\Exception $e) {
            $this->returnHttpResponse(400, $e->getMessage());
        }
    }

    /**
     * @param int $responseCode
     * @param string|null $body
     * @return void
     */
    protected function returnHttpResponse($responseCode, $body = null)
    {
        if (!empty($body)) {
            $this->getResponse()->setBody($body);
        }
        $this->getResponse()
            ->setHttpResponseCode($responseCode)
            ->sendResponse();
        exit;
    }
}

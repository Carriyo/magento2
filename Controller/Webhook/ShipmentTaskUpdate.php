<?php
/**
 */

namespace Carriyo\Shipment\Controller\Webhook;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Helper;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;

class ShipmentTaskUpdate extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
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
     * ShipmentTaskUpdate constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\HTTP\PhpEnvironment\Request $request
     * @param Helper $helper
     * @param Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\HTTP\PhpEnvironment\Request $request,
        Helper $helper,
        Logger $logger
    )
    {
        parent::__construct($context);
        $this->request = $request;
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $this->logger->info("Carriyo webhook invoked (ShipmentUpdated): " . $this->request->getContent());

        if ($this->request->getHeader('Content-Type') !== 'application/json') {
            $this->returnHttpResponse(400);
        }

        $payload = json_decode($this->request->getContent(), true);
        if (empty($payload)) {
            $this->returnHttpResponse(400);
        }
        if (!isset($payload['references'])) {
            $this->returnHttpResponse(400, 'MISSING references');
        }
        if (!isset($payload['references']['partner_order_reference'])) {
            $this->returnHttpResponse(400, 'MISSING partner_order_reference');
        }
        if (!isset($payload['post_shipping_info']['status'])) {
            $this->returnHttpResponse(400, 'MISSING status');
        }

        $orderRef = $payload['references']['partner_order_reference'];
        $carriyoStatus = $payload['post_shipping_info']['status'];

        try {
            $this->helper->updateOrder($orderRef, $carriyoStatus);
            $this->returnHttpResponse(200, 'STATUS UPDATED');
        } catch (\Exception $e) {
            $this->returnHttpResponse(400, $e->getMessage());

        }
    }

    /**
     * Flush output and set http code
     *
     * @param int $responseCode
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

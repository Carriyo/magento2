<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model;

use Carriyo\Shipment\Core\Api\Client;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;
use Psr\Log\LoggerInterface;


class Helper
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var ShipmentLoader
     */
    private $shipmentLoader;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Client
     */
    private $carriyoClient;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;

    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ShipmentSave constructor.
     *
     * @param Configuration $configuration
     * @param Client $carriyoClient
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentLoader $shipmentLoader
     * @param RedirectFactory $resultRedirectFactory
     * @param Context $context
     * @param ShipmentRepository $shipmentRepository
     * @param TrackFactory $trackFactory
     */
    public function __construct(
        Configuration $configuration,
        Client $carriyoClient,
        OrderRepositoryInterface $orderRepository,
        ShipmentLoader $shipmentLoader,
        RedirectFactory $resultRedirectFactory,
        Context $context,
        ShipmentRepository $shipmentRepository,
        TrackFactory $trackFactory,
        LoggerInterface $logger
    )
    {
        $this->configuration = $configuration;
        $this->shipmentLoader = $shipmentLoader;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->context = $context;
        $this->carriyoClient = $carriyoClient;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface $subject
     * @param $result
     * @return mixed
     */
    public function sendOrderDetails(
        $order,
        $result
    )
    {
        if (!$this->configuration->isActive()) {
            return $result;
        }
        try {
            $response = $this->carriyoClient->sendOrderDraft($order);
            if (!array_key_exists('errors', $response)) {
                $this->logger->debug("Response ShipmentId ::" . $response["shipment_id"]);
            }
            if (array_key_exists('errors', $response)) {
                $this->logger->debug("Response Error ::" . $response['errors'][0]);
            }

        } catch (\Exception $e) {
            $this->logger->debug("Error while sendingOrderDetails " . $e->getMessage() . " Trace" . $e->getTraceAsString());
        }

        return $result;
    }
}

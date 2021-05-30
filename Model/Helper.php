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

    private $orderFactory;

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
        \Magento\Sales\Model\OrderFactory $orderFactory,
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
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    /**
     * @param $order
     * @return |null
     */
    public function sendOrderDetails(
        $order
    )
    {
        $shipmentId = null;
        if (!$this->configuration->isActive()) {
            return $shipmentId;
        }
        try {
            $response = $this->carriyoClient->sendOrderDraft($order);
            if (!array_key_exists('errors', $response)) {
                $shipmentId = $response["shipment_id"];
                $this->logger->debug("Carriyo Response ShipmentId ::" . $shipmentId);
            }
            if (array_key_exists('errors', $response)) {
                $this->logger->debug("Carriyo Response Error ::" . $response['errors'][0]);
            }

        } catch (\Exception $e) {
            $this->logger->debug("Carriyo Error while sendingOrderDetails " . $e->getMessage() . " Trace" . $e->getTraceAsString());
        }

        return $shipmentId;
    }

    public function sendOrderCancel($orderId)
    {
        if (!$this->configuration->isActive()) {
            return;
        }
        try {
            $response = $this->carriyoClient->sendOrderCancel($orderId);
            if (!array_key_exists('errors', $response)) {
                $this->logger->debug("Carriyo Cancel Order ::" . print_r($response, 1));
            }
            if (array_key_exists('errors', $response)) {
                $this->logger->debug("Carriyo Response Error ::" . $response['errors'][0]);
            }

        } catch (\Exception $e) {
            $this->logger->debug("Carriyo Error while sendOrderCancel " . $e->getMessage() . " Trace" . $e->getTraceAsString());
        }
        return;
    }

    /**
     *
     */
    public function sendOrderUpdate($order)
    {
        if (!$this->configuration->isActive()) {
            return;
        }
        try {
            $response = $this->carriyoClient->sendUpdateOrderDraft($order);
            if (!array_key_exists('errors', $response)) {
                $this->logger->debug("Carriyo Response ShipmentId ::" . $response["shipment_id"]);
            }
            if (array_key_exists('errors', $response)) {
                $this->logger->debug("Carriyo Response Error ::" . $response['errors'][0]);
            }

        } catch (\Exception $e) {
            $this->logger->debug("Carriyo Error while sendOrderUpdate " . $e->getMessage() . " Trace" . $e->getTraceAsString());
        }
        return;
    }

    /**
     * @param $orderId
     * @param $status
     * @return bool
     */
    public function updateOrder($orderId, $status)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            $this->logger->debug("ORDER NOT FOUND");
            return false;
        }
        try {
            $mageStatus = $this->configuration->getMagentoStatus($status);
            if (empty($mageStatus)) {
                $this->logger->debug("Carriyo Status Not Mapped To Magento Status");
                return false;
            }
            $order
                ->addCommentToStatusHistory(
                    __('Carriyo Status Update: %1.', $status), $mageStatus
                );
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->debug("Error updateOrder " . $e->getTraceAsString());
            return false;
        }
        return true;
    }
}

<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model;

use Carriyo\Shipment\Core\Api\Client;
use Carriyo\Shipment\Logger\Logger;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
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
        Logger $logger
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
     * Function to create or update draft shipment in Carriyo
     * triggered manually by the "Send Shipment" on Order 
     * Details page
     *
     * @param $orderId
     * @return |null
     * @throws LocalizedException
     */
    public function sendOrder($orderId)
    {
        $order = $this->orderFactory->create()->loadByAttribute('entity_id', $orderId);
        if (!$order->getId()) {
            $this->logger->info("sendOrder ORDER NOT FOUND {$orderId}");
            return ['error' => 'ORDER NOT FOUND'];
        }
        return $this->sendOrderCreateOrUpdate($order);
    }

     /**
     * @param $order
     * @return |null
     * @throws LocalizedException
     */
    public function sendOrderCreateOrUpdate($order)
    {
        $orderId = $order->getIncrementId();
        $allowedStatusesOther = $this->configuration->getAllowedStatusesOther();
        $allowedStatusesCOD = $this->configuration->getAllowedStatusesCOD();

        if ((in_array($order->getStatus(), $allowedStatusesOther)
         && $order->getPayment()->getMethod() !== 'cashondelivery') || (in_array($order->getStatus(), $allowedStatusesCOD)
         && $order->getPayment()->getMethod() === 'cashondelivery')) {
            foreach ($order->getAllStatusHistory() as $orderComment) {
                //invoke update order if shipment has already been created in Carriyo
                if (is_string($orderComment->getComment())) {
                    //TODO: Remove the DraftShipmentId# check in the future as we are now setting ShipmentId#
                    if (strpos($orderComment->getComment(), 'Carriyo DraftShipmentId#') === 0 || strpos($orderComment->getComment(), 'Carriyo ShipmentId#') === 0) {
                        return $this->sendOrderUpdate($order);
                    }
                }
            }
            $shipmentId = $this->sendOrderCreate($order);
            $this->orderRepository->save($order);
            return $shipmentId;
        } else {
            $this->logger->info("Carriyo Shipment skipped because the order status is not allowed Order ID: {$orderId} :: Status: " . $order->getStatus());
                
            $order->addCommentToStatusHistory("Carriyo Shipment Skipped (status not allowed)");
            $this->orderRepository->save($order);
        }
        return null;
    }

    /**
     * Function to create draft shipment in Carriyo
     * triggered automatically by the "afterPlace" plugin
     *
     * @param $order
     * @return |null
     * @throws LocalizedException
     */
    public function sendOrderCreate(
        $order
    )
    {
        $shipmentId = null;
        $orderId = $order->getIncrementId();
        if (!$this->configuration->isActive()) {
            return $shipmentId;
        }
        $autoBookShipments = $this->configuration->isAutoBookShipments();
        try {
            $response = $this->carriyoClient->createShipment($order, $autoBookShipments);
            if (!array_key_exists('errors', $response)) {
                $shipmentId = $response["shipment_id"];
                $this->logger->info("Carriyo Response ShipmentId {$orderId}::" . $shipmentId);

                if (!empty($shipmentId)) {
                    $order->addCommentToStatusHistory("Carriyo ShipmentId# " . $shipmentId);
                }
            }
            if (array_key_exists('errors', $response)) {
                throw new LocalizedException(__($response['errors']));
            }

        } catch (\Exception $e) {
            $this->logger->info("Carriyo Error while sendingOrderDetails {$orderId} " . $e->getMessage() . " Trace " . $e->getTraceAsString());
            throw new LocalizedException(__('Carriyo createShipment Error %1', $e->getMessage()));
        }

        return $shipmentId;
    }

    /**
     * @param $order
     * @return void|null
     * @throws LocalizedException
     */
    public function sendOrderUpdate($order)
    {
        if (!$this->configuration->isActive()) {
            return;
        }
        $orderId = $order->getIncrementId();
        $shipmentId = null;
        $autoBookShipments = $this->configuration->isAutoBookShipments();
        try {
            $response = $this->carriyoClient->updateShipment($order, $autoBookShipments);
            if (!array_key_exists('errors', $response)) {
                $shipmentId = $response["shipment_id"];
                $this->logger->info("Carriyo Response ShipmentId {$orderId} ::" . $shipmentId);
            }
            if (array_key_exists('errors', $response)) {
                $this->logger->info("Carriyo Response Error {$orderId} ::" . $response['errors']);
                throw new LocalizedException(__($response['errors']));
            }

        } catch (\Exception $e) {
            $this->logger->info("Carriyo Error while sendOrderUpdate {$orderId} :: " . $e->getMessage() . " Trace" . $e->getTraceAsString());
            throw new LocalizedException(__('Carriyo updateShipment Error %1', $e->getMessage()));
        }
        return $shipmentId;
    }

    /**
     * @param $orderId
     * @throws LocalizedException
     */
    public function sendOrderCancel($orderId)
    {
        if (!$this->configuration->isActive()) {
            return;
        }
        try {
            $response = $this->carriyoClient->cancelShipment($orderId);
            if (!array_key_exists('errors', $response)) {
                $this->logger->info("Carriyo Cancel Order {$orderId} ::" . print_r($response, 1));
            }
            if (array_key_exists('errors', $response)) {
                $this->logger->info("Carriyo Response Error {$orderId}::" . $response['errors']);
                throw new LocalizedException(__($response['errors']));
            }

        } catch (\Exception $e) {
            $this->logger->info("Carriyo Error while cancelShipment {$orderId} " . $e->getMessage() . " Trace" . $e->getTraceAsString());
            throw new LocalizedException(__('Carriyo cancelShipment Error %1', $e->getMessage()));
        }
        return;
    }

    /**
     * @param $orderId
     * @param $status
     * @return bool
     * @throws LocalizedException
     */
    public function updateOrder($orderId, $status)
    {
        $this->logger->info("Carriyo webhook invoked for OrderId {$orderId}");
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            $this->logger->info("{$orderId} ORDER NOT FOUND");
            throw new LocalizedException(__("{$orderId} ORDER NOT FOUND"));
        }
        try {
            $mageStatus = $this->configuration->getCarriyoMappedStatuses();
            if (!isset($mageStatus[$status])) {
                $this->logger->info("Carriyo Status Not Mapped To Magento Status");
                throw new LocalizedException(__("INVALID STATUS {$status} "));
            }
            $order
                ->addCommentToStatusHistory(
                    __('Carriyo Status Update: %1.', $status), $mageStatus[$status]
                );
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->info("Error updateOrder " . $e->getTraceAsString());
            throw new LocalizedException(__("UPDATE ORDER ID# {$orderId}  FAILED Reason :: " . $e->getMessage()));
        }
        return true;
    }
}

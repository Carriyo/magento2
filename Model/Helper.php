<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model;

use Carriyo\Shipment\Core\Api\Client;
use Carriyo\Shipment\Logger\Logger;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentRepository;

class Helper
{
    private const SHIPMENT_COMMENT_PREFIX = 'Carriyo ShipmentId# ';
    private const SHIPMENT_EXPORT_HASH_PREFIX = 'shipment:';
    private const STANDARD_ORDER_STATES = [
        'processing' => Order::STATE_PROCESSING,
        'complete' => Order::STATE_COMPLETE,
        'canceled' => Order::STATE_CANCELED,
    ];

    /**
     * @var Configuration
     */
    private $configuration;

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
     * @var ConvertOrder
     */
    private $convertOrder;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        Configuration $configuration,
        Client $carriyoClient,
        OrderRepositoryInterface $orderRepository,
        ShipmentRepository $shipmentRepository,
        ConvertOrder $convertOrder,
        TransactionFactory $transactionFactory,
        TrackFactory $trackFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        Logger $logger
    ) {
        $this->configuration = $configuration;
        $this->carriyoClient = $carriyoClient;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->convertOrder = $convertOrder;
        $this->transactionFactory = $transactionFactory;
        $this->trackFactory = $trackFactory;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
    }

    /**
     * @param int|string $orderId
     * @return string|null
     * @throws LocalizedException
     */
    public function sendOrder($orderId)
    {
        $order = $this->orderFactory->create()->loadByAttribute('entity_id', $orderId);
        if (!$order || !$order->getId()) {
            $this->logger->info("sendOrder ORDER NOT FOUND {$orderId}");

            throw new LocalizedException(__('Order not found.'));
        }

        return $this->configuration->isOrderMode()
            ? $this->sendOrderCreateOrUpdate($order, true)
            : $this->sendShipmentCreateOrUpdate($order, true);
    }

    /**
     * @param Order $order
     * @param bool $force
     * @return string|null
     * @throws LocalizedException
     */
    public function sendOrderCreateOrUpdate($order, $force = false)
    {
        if ($this->configuration->isShipmentMode()) {
            return $this->sendShipmentCreateOrUpdate($order, $force);
        }

        if (!$this->configuration->isActive()) {
            return null;
        }

        $carriyoOrderId = trim((string)$order->getData('carriyo_order_id'));
        $isLinkedOrder = $carriyoOrderId !== '';
        $payloadHash = $this->carriyoClient->getOrderPayloadHash($order, $isLinkedOrder);
        if ($isLinkedOrder && !$force && trim((string)$order->getData('carriyo_export_hash')) === $payloadHash) {
            return $carriyoOrderId;
        }

        if (!$isLinkedOrder) {
            $itemIdsReady = true;
            foreach ($order->getAllVisibleItems() as $item) {
                if ((int)$item->getQtyOrdered() > 0 && trim((string)$item->getItemId()) === '') {
                    $itemIdsReady = false;
                    break;
                }
            }
            if (!$itemIdsReady) {
                if ($this->configuration->isDebugEnabled()) {
                    $this->logger->debug(sprintf('Carriyo order skipped because item ids are not ready %s', $order->getIncrementId()));
                }

                return null;
            }

            $carriyoOrderId = trim((string)$order->getData('carriyo_order_id'));
            $isLinkedOrder = $carriyoOrderId !== '';
            if ($isLinkedOrder) {
                return $carriyoOrderId;
            }
        }

        $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : null;
        $allowedStatuses = $paymentMethod === 'cashondelivery'
            ? $this->configuration->getAllowedStatusesCOD()
            : $this->configuration->getAllowedStatusesOther();

        if (!$isLinkedOrder && !in_array($order->getStatus(), $allowedStatuses, true)) {
            if ($this->configuration->isDebugEnabled()) {
                $this->logger->debug(
                    sprintf('Carriyo order skipped because status is not allowed %s :: %s', $order->getIncrementId(), $order->getStatus())
                );
            }
            return null;
        }

        $action = $isLinkedOrder ? 'updateOrder' : 'createOrder';
        $response = $isLinkedOrder ? $this->carriyoClient->updateOrder($order) : $this->carriyoClient->createOrder($order);

        if (isset($response['errors'])) {
            $this->logger->error(sprintf('Carriyo Error while %s %s %s', $action, $order->getIncrementId(), $response['errors']));
            throw new LocalizedException(__('Carriyo %1 Error %2', $action, $response['errors']));
        }

        if (!$isLinkedOrder) {
            $carriyoOrderId = (string)($response['order_id'] ?? '');
        }
        if ($carriyoOrderId !== '') {
            $order->setData('carriyo_order_id', $carriyoOrderId);
        }
        $order->setData('carriyo_export_hash', $this->carriyoClient->getOrderPayloadHash($order, true));
        $this->orderRepository->save($order);

        if ($this->configuration->isDebugEnabled()) {
            $this->logger->debug(sprintf('Carriyo order response %s::%s', $order->getIncrementId(), print_r($response, true)));
        }

        return $carriyoOrderId;
    }

    /**
     * @param Order $order
     * @param bool $force
     * @return string|null
     * @throws LocalizedException
     */
    public function sendShipmentCreateOrUpdate($order, $force = false)
    {
        if (!$this->configuration->isActive()) {
            return null;
        }

        $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : null;
        $allowedStatuses = $paymentMethod === 'cashondelivery'
            ? $this->configuration->getAllowedStatusesCOD()
            : $this->configuration->getAllowedStatusesOther();
        if (!in_array($order->getStatus(), $allowedStatuses, true)) {
            if ($this->configuration->isDebugEnabled()) {
                $this->logger->debug(
                    sprintf('Carriyo shipment skipped because status is not allowed %s :: %s', $order->getIncrementId(), $order->getStatus())
                );
            }
            return null;
        }

        $hasExistingShipment = false;
        $existingShipmentId = null;
        foreach ($order->getAllStatusHistory() as $orderComment) {
            $comment = is_string($orderComment->getComment()) ? $orderComment->getComment() : '';
            if (strpos($comment, 'Carriyo DraftShipmentId#') === 0 || strpos($comment, self::SHIPMENT_COMMENT_PREFIX) === 0) {
                $hasExistingShipment = true;
                if (preg_match('/^Carriyo (?:Draft)?ShipmentId#\s*(.+)$/', $comment, $matches)) {
                    $existingShipmentId = trim((string)$matches[1]);
                }
            }
        }

        $autoBookShipments = $this->configuration->isAutoBookShipments();
        $payloadHash = $this->carriyoClient->getShipmentPayloadHash($order, $autoBookShipments);
        if (
            $hasExistingShipment
            && !$force
            && trim((string)$order->getData('carriyo_export_hash')) === self::SHIPMENT_EXPORT_HASH_PREFIX . $payloadHash
        ) {
            return $existingShipmentId;
        }

        $response = $hasExistingShipment
            ? $this->carriyoClient->updateShipment($order, $autoBookShipments)
            : $this->carriyoClient->createShipment($order, $autoBookShipments);
        if (isset($response['errors'])) {
            $action = $hasExistingShipment ? 'updateShipment' : 'createShipment';
            $this->logger->error(sprintf('Carriyo Error while %s %s %s', $action, $order->getIncrementId(), $response['errors']));
            throw new LocalizedException(__('Carriyo %1 Error %2', $action, $response['errors']));
        }

        $shipmentId = (string)($response['shipment_id'] ?? '');
        if (!$hasExistingShipment && $shipmentId !== '') {
            $order->addCommentToStatusHistory(self::SHIPMENT_COMMENT_PREFIX . $shipmentId);
        }
        $order->setData('carriyo_export_hash', self::SHIPMENT_EXPORT_HASH_PREFIX . $payloadHash);
        $this->orderRepository->save($order);

        if ($this->configuration->isDebugEnabled()) {
            $this->logger->debug(sprintf('Carriyo shipment response %s::%s', $order->getIncrementId(), print_r($response, true)));
        }

        return $shipmentId;
    }

    /**
     * @param string $orderId
     * @throws LocalizedException
     */
    public function sendOrderCancel($orderId)
    {
        if (!$this->configuration->isActive()) {
            return;
        }

        if ($this->configuration->isShipmentMode()) {
            $response = $this->carriyoClient->cancelShipment($orderId);
            if (isset($response['errors'])) {
                $this->logger->error("Carriyo Error while cancelShipment {$orderId} " . $response['errors']);
                throw new LocalizedException(__('Carriyo cancelShipment Error %1', $response['errors']));
            }

            return;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId() || trim((string)$order->getData('carriyo_order_id')) === '') {
            return;
        }

        $response = $this->carriyoClient->cancelOrder($orderId);
        if (isset($response['errors'])) {
            $this->logger->error("Carriyo Error while cancelOrder {$orderId} " . $response['errors']);
            throw new LocalizedException(__('Carriyo cancelOrder Error %1', $response['errors']));
        }

        $order->setData('carriyo_export_hash', null);
        $this->orderRepository->save($order);
    }

    /**
     * @param array $payload
     * @throws LocalizedException
     */
    public function syncShipment(array $payload)
    {
        if ($this->configuration->isShipmentMode()) {
            if (!isset($payload['references']['partner_order_reference'])) {
                throw new LocalizedException(__('MISSING partner_order_reference'));
            }
            if (!isset($payload['post_shipping_info']['status'])) {
                throw new LocalizedException(__('MISSING status'));
            }

            $this->updateOrder(
                (string)$payload['references']['partner_order_reference'],
                (string)$payload['post_shipping_info']['status']
            );

            return;
        }

        $order = $this->orderFactory->create()->loadByIncrementId(
            $this->configuration->getMagentoOrderReference($payload['references']['partner_order_reference'])
        );
        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found.'));
        }

        $shipmentId = (string)($payload['shipment_id'] ?? '');
        if ($shipmentId === '') {
            throw new LocalizedException(__('Missing shipment_id.'));
        }

        $magentoShipmentId = null;
        foreach ($order->getAllStatusHistory() as $history) {
            $comment = (string)$history->getComment();
            if (!preg_match('/^' . preg_quote(self::SHIPMENT_COMMENT_PREFIX, '/') . '(.+?) MagentoShipmentId# (\d+)$/', $comment, $matches)) {
                continue;
            }
            if ($matches[1] === $shipmentId) {
                $magentoShipmentId = (int)$matches[2];
                break;
            }
        }
        if ($magentoShipmentId) {
            $shipment = $this->shipmentRepository->get($magentoShipmentId);
            $trackingNo = (string)($payload['post_shipping_info']['tracking_no'] ?? '');
            $trackingUrl = (string)($payload['post_shipping_info']['carriyo_tracking_url'] ?? '');
            if ($trackingNo !== '') {
                $matchingTrack = null;
                foreach ($shipment->getTracksCollection() as $track) {
                    if ((string)$track->getTrackNumber() === $trackingNo || (string)$track->getNumber() === $trackingNo) {
                        $matchingTrack = $track;
                        break;
                    }
                }
                if (!$matchingTrack) {
                    $shipment->addTrack($this->trackFactory->create()->addData([
                        'carrier_code' => 'custom',
                        'title' => $payload['carrier_account']['carrier'] ?? 'Carriyo',
                        'number' => $trackingNo,
                        'description' => $trackingUrl,
                    ]));
                } elseif ($trackingUrl !== '' && (string)$matchingTrack->getDescription() !== $trackingUrl) {
                    $matchingTrack->setDescription($trackingUrl);
                }
            }

            $status = $payload['post_shipping_info']['status'] ?? null;
            if ($status) {
                $shipment->addComment(__('Carriyo Status Update: %1.', $status));
            }
            $this->shipmentRepository->save($shipment);
            return;
        }

        if (empty($payload['items'])) {
            throw new LocalizedException(__('Missing shipment items for a new Magento shipment.'));
        }

        if (!$order->canShip()) {
            throw new LocalizedException(__('Order can no longer create shipments.'));
        }

        $shipmentItems = $this->getShipmentItemQuantities($order, $payload);
        $shipment = $this->convertOrder->toShipment($order);
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $quantity = $shipmentItems[(int)$orderItem->getItemId()] ?? 0;
            if ($quantity <= 0) {
                continue;
            }
            $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($quantity);
            $shipment->addItem($shipmentItem);
        }
        if (!count($shipment->getAllItems())) {
            throw new LocalizedException(__('Shipment payload does not match shippable Magento items.'));
        }

        $trackingNo = (string)($payload['post_shipping_info']['tracking_no'] ?? '');
        $trackingUrl = (string)($payload['post_shipping_info']['carriyo_tracking_url'] ?? '');
        if ($trackingNo !== '') {
            $shipment->addTrack($this->trackFactory->create()->addData([
                'carrier_code' => 'custom',
                'title' => $payload['carrier_account']['carrier'] ?? 'Carriyo',
                'number' => $trackingNo,
                'description' => $trackingUrl,
            ]));
        }

        $status = $payload['post_shipping_info']['status'] ?? null;
        if ($status) {
            $shipment->addComment(__('Carriyo Status Update: %1.', $status));
        }

        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);

        $this->transactionFactory->create()
            ->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        $order->addCommentToStatusHistory(
            sprintf('%s%s MagentoShipmentId# %s', self::SHIPMENT_COMMENT_PREFIX, $shipmentId, $shipment->getEntityId())
        );
        $this->orderRepository->save($order);
    }

    /**
     * @param array $payload
     * @throws LocalizedException
     */
    public function syncOrderStatus(array $payload)
    {
        if ($this->configuration->isShipmentMode()) {
            return;
        }

        $newImage = $payload['new_image'] ?? $payload['newImage'] ?? [];
        $orderReference = (string)($newImage['partner_order_reference'] ?? $newImage['partnerOrderReference'] ?? '');
        $carriyoStatus = (string)($newImage['status'] ?? '');
        if ($orderReference === '' || $carriyoStatus === '') {
            throw new LocalizedException(__('Missing order status payload.'));
        }

        $magentoStatus = $this->configuration->getMagentoOrderStatus($carriyoStatus);
        if (!$magentoStatus) {
            return;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($this->configuration->getMagentoOrderReference($orderReference));
        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found.'));
        }

        if (isset(self::STANDARD_ORDER_STATES[$magentoStatus])) {
            $order->setState(self::STANDARD_ORDER_STATES[$magentoStatus]);
        }
        $order->setStatus($magentoStatus);
        $order->addCommentToStatusHistory(__('Carriyo Order Status Update: %1.', $carriyoStatus), $magentoStatus);
        $this->orderRepository->save($order);
    }

    /**
     * @param string $orderReference
     * @param string $carriyoStatus
     * @return bool
     * @throws LocalizedException
     */
    public function updateOrder($orderReference, $carriyoStatus)
    {
        if ($this->configuration->isDebugEnabled()) {
            $this->logger->debug("Carriyo webhook invoked for OrderId {$orderReference}");
        }

        $order = $this->orderFactory->create()->loadByIncrementId($this->configuration->getMagentoOrderReference($orderReference));
        if (!$order->getId()) {
            $this->logger->error("{$orderReference} ORDER NOT FOUND");
            throw new LocalizedException(__("{$orderReference} ORDER NOT FOUND"));
        }

        $statusMap = $this->configuration->getShipmentMappedStatuses();
        if (!isset($statusMap[$carriyoStatus])) {
            $this->logger->error("Carriyo Status Not Mapped To Magento Status");
            throw new LocalizedException(__("INVALID STATUS {$carriyoStatus}"));
        }

        $magentoStatus = $statusMap[$carriyoStatus];
        if (isset(self::STANDARD_ORDER_STATES[$magentoStatus])) {
            $order->setState(self::STANDARD_ORDER_STATES[$magentoStatus]);
        }
        $order->setStatus($magentoStatus);
        $order->addCommentToStatusHistory(__('Carriyo Status Update: %1.', $carriyoStatus), $magentoStatus);
        $this->orderRepository->save($order);

        return true;
    }

    /**
     * @param Order $order
     * @param array $payload
     * @return array
     * @throws LocalizedException
     */
    private function getShipmentItemQuantities(Order $order, array $payload)
    {
        $itemsById = [];
        $itemsBySku = [];
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getIsVirtual() || (float)$item->getQtyToShip() <= 0) {
                continue;
            }

            $itemsById[(string)$item->getItemId()] = $item;
            $itemsBySku[(string)$item->getSku()][] = $item;
        }

        $shipmentItems = [];
        foreach ((array)($payload['items'] ?? []) as $payloadItem) {
            $quantity = (float)($payloadItem['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $orderItem = null;
            $productRef = (string)($payloadItem['product_ref'] ?? '');
            if ($productRef !== '' && isset($itemsById[$productRef])) {
                $orderItem = $itemsById[$productRef];
            } else {
                $sku = (string)($payloadItem['sku'] ?? '');
                if ($sku === '' || empty($itemsBySku[$sku])) {
                    throw new LocalizedException(__('Shipment item is not present in Magento order.'));
                }
                if (count($itemsBySku[$sku]) > 1) {
                    throw new LocalizedException(__('Shipment item SKU is ambiguous without product_ref.'));
                }
                $orderItem = $itemsBySku[$sku][0];
            }

            $itemId = (int)$orderItem->getItemId();
            $shipmentItems[$itemId] = ($shipmentItems[$itemId] ?? 0) + $quantity;
            if ($shipmentItems[$itemId] > (float)$orderItem->getQtyToShip()) {
                throw new LocalizedException(__('Shipment quantity exceeds Magento shippable quantity.'));
            }
        }

        return $shipmentItems;
    }
}

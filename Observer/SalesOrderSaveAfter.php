<?php
/**
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Configuration;
use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderSaveAfter implements ObserverInterface
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(
        Helper $helper,
        Logger $logger,
        Configuration $configuration
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->configuration = $configuration;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order->isCanceled()) {
            return $this;
        }

        if ($this->configuration->isShipmentMode()) {
            if (!$order->getOrigData('status') || $order->getStatus() === $order->getOrigData('status')) {
                return $this;
            }
        } else {
            $isLinkedOrder = trim((string)$order->getData('carriyo_order_id')) !== '';
            $hasStatusChanged = $order->getOrigData('status') && $order->getStatus() !== $order->getOrigData('status');
            if (!$isLinkedOrder && !$hasStatusChanged) {
                $itemIdsReady = true;
                foreach ($order->getAllVisibleItems() as $item) {
                    if ((int)$item->getQtyOrdered() > 0 && trim((string)$item->getItemId()) === '') {
                        $itemIdsReady = false;
                        break;
                    }
                }
                if (!$itemIdsReady) {
                    return $this;
                }

                $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : null;
                $allowedStatuses = $paymentMethod === 'cashondelivery'
                    ? $this->configuration->getAllowedStatusesCOD()
                    : $this->configuration->getAllowedStatusesOther();
                if (!in_array($order->getStatus(), $allowedStatuses, true)) {
                    return $this;
                }
            }
        }

        try {
            $this->helper->sendOrderCreateOrUpdate($order);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
            $this->logger->info('Failed in SalesOrderSaveAfter');
        }

        return $this;
    }
}

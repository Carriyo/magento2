<?php
/**
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Configuration;
use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderPlaceAfter implements ObserverInterface
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
        if ($this->configuration->isShipmentOnlyFlow()) {
            return $this;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order->isCanceled() || trim((string)$order->getData('carriyo_order_id')) !== '') {
            return $this;
        }

        try {
            $this->helper->sendOrderCreateOrUpdate($order);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
            $this->logger->info('Failed in SalesOrderPlaceAfter');
        }

        return $this;
    }
}

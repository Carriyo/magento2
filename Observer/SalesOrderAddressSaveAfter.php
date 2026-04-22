<?php
/**
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Model\Configuration;
use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderAddressSaveAfter implements ObserverInterface
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @param Configuration $configuration
     * @param Helper $helper
     */
    public function __construct(
        Configuration $configuration,
        Helper $helper
    ) {
        $this->configuration = $configuration;
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this|void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->configuration->isOrderMode()) {
            return $this;
        }

        $order = $observer->getEvent()->getAddress()->getOrder();
        try {
            $this->helper->sendOrderCreateOrUpdate($order);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
        }

        return $this;
    }
}

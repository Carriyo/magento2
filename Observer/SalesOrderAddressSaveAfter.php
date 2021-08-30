<?php
/**
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderAddressSaveAfter implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;


    protected $helper;

    /**
     * @param \Magento\Framework\Registry $registry
     * @param \Carriyo\Shipment\Model $helper
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        Helper $helper
    )
    {
        $this->registry = $registry;
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this|void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getAddress()->getOrder();
        try {
            $this->helper->sendOrder($order);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
            $this->logger->info("Failed in SalesOrderAddressSaveAfter");
        }
        return $this;
    }
}

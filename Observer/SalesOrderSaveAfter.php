<?php
/**
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderSaveAfter implements ObserverInterface
{
    /**
     * @var Logger
     */
    private $logger;

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
        Helper $helper,
        Logger $logger
    )
    {
        $this->registry = $registry;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this|void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $this->logger->info("order status: " . $order->getStatus());
        $this->logger->info("previous order status: " . $order->getOrigData('status'));
        //Skip new order and updates without status change
        if (!empty($order->getOrigData('status')) && $order->getStatus() !==  $order->getOrigData('status') ) {
            $this->logger->info("order status matched");

            try {
                $this->helper->sendOrderCreateOrUpdate($order);
            } catch (\Exception $e) {
                $order->addCommentToStatusHistory($e->getMessage());
                $this->logger->info("Failed in SalesOrderSaveAfter");
            }
        }
        return $this;
    }
}

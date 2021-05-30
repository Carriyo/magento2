<?php
/**
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class SalesOrderAddressSaveAfter implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    protected $logger;

    protected $helper;

    /**
     * @param \Magento\CustomerCustomAttributes\Model\Sales\Order\AddressFactory $orderAddressFactory
     */
    public function __construct(
        \Magento\Framework\Registry $registry,
        Helper $helper,
        LoggerInterface $logger
    )
    {
        $this->registry = $registry;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * After load observer for order address
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->registry->registry('orderSentToCarriyo')) {
            return $this;
        }
        $order = $observer->getEvent()->getAddress()->getOrder();
        $this->helper->sendOrderUpdate($order);
        return $this;
    }
}

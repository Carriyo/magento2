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
     * @param \Magento\CustomerCustomAttributes\Model\Sales\Order\AddressFactory $orderAddressFactory
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
        if ($this->registry->registry('orderSentToCarriyo')) {
            return $this;
        }
        $order = $observer->getEvent()->getAddress()->getOrder();
        try {
            $this->helper->sendOrderUpdate($order);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
        }
        return $this;
    }
}

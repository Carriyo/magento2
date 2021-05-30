<?php
/**
 *
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class SalesOrderCancelAfter implements ObserverInterface
{

    /**
     * @var Helper
     */
    protected $helper;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * SalesOrderCancelAfter constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(
        Helper $helper,
        LoggerInterface $logger
    )
    {
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $orderIncrementId=$order->getIncrementId();
        $this->helper->sendOrderCancel($orderIncrementId);
        //TODO save a comment on history
        return $this;
    }
}

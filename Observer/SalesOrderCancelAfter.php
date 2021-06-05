<?php
/**
 *
 */

namespace Carriyo\Shipment\Observer;

use Carriyo\Shipment\Model\Helper;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderCancelAfter implements ObserverInterface
{

    /**
     * @var Helper
     */
    protected $helper;


    /**
     * SalesOrderCancelAfter constructor.
     * @param Helper $helper
     */
    public function __construct(
        Helper $helper
    )
    {
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this|void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $orderIncrementId = $order->getIncrementId();
        try {
            $this->helper->sendOrderCancel($orderIncrementId);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
        }
        return $this;
    }
}

<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Plugin;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Helper;
use Magento\Sales\Model\Order;

/**
 * Increments number of coupon usages after placing order.
 */
class CreateOrderPlugin
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Helper
     */
    private $helper;

    private $registry;

    /**
     * CreateOrderPlugin constructor.
     * @param Helper $helper
     * @param \Magento\Framework\Registry $registry
     * @param Logger $logger
     */
    public function __construct(
        Helper $helper,
        \Magento\Framework\Registry $registry,
        Logger $logger
    )
    {
        $this->helper = $helper;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * @param Order $subject
     * @param Order $result
     * @return Order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterPlace(Order $subject, Order $result): Order
    {
        try {
            $shipmentId = $this->helper->sendOrderDetails($subject);
            if (!empty($shipmentId)) {
                $subject->addCommentToStatusHistory("Carriyo DraftShipmentId# " . $shipmentId);
            }
        } catch (\Exception $e) {
            $subject->addCommentToStatusHistory($e->getMessage());
        }
        $this->registry->register('orderSentToCarriyo', 1);
        return $subject;
    }
}

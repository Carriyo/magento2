<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Plugin;

use Carriyo\Shipment\Model\Helper;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Increments number of coupon usages after placing order.
 */
class CreateOrderPlugin
{
    /**
     * @var LoggerInterface
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
     * @param LoggerInterface $logger
     */
    public function __construct(
        Helper $helper,
        \Magento\Framework\Registry $registry,
        LoggerInterface $logger
    )
    {
        $this->helper = $helper;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     *
     * @param Order $subject
     * @param Order $result
     * @return Order
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterPlace(Order $subject, Order $result): Order
    {
        $shipmentId = $this->helper->sendOrderDetails($subject);
        $subject->addCommentToStatusHistory("Carriyo Draft Shipment Id : " . $shipmentId);
        $this->registry->register('orderSentToCarriyo', 1);
        return $subject;
    }
}

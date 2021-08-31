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
 * Create a shipment in Carriyo when an order is placed
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
     * @param Order $order
     * @param Order $result
     * @return Order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterPlace(Order $order, Order $result): Order
    {
        //commented out because we are using the Sales Order Save After observer to create and update shipment in Carriyo
        /* 
        try {
            $shipmentId = $this->helper->sendOrderCreateOrUpdate($order);
        } catch (\Exception $e) {
            $order->addCommentToStatusHistory($e->getMessage());
            $this->logger->info("Failed in CreateOrderPlugin");
        }
        $this->registry->register('orderSentToCarriyo', 1);
        */
        return $order;
    }
}

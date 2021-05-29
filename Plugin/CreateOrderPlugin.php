<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Plugin;

use Carriyo\Shipment\Model\Helper;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Model\Coupon\UpdateCouponUsages;
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
    /**
     * @param UpdateCouponUsages $updateCouponUsages
     */
    public function __construct(
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->helper=$helper;
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
        $this->logger->debug('plugin invoked----order');

        $this->helper->sendOrderDetails($subject, $result);
        return $subject;
    }
}

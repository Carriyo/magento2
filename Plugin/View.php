<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Plugin;


use Carriyo\Shipment\Model\Configuration;

/**
 * Class View
 * @package Carriyo\Shipment\Plugin
 */
class View
{
    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * View constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(
        Configuration $configuration
    )
    {
        $this->configuration = $configuration;
    }

    public function getSendToCarriyoUrl(\Magento\Shipping\Block\Adminhtml\View $block)
    {
        return $block->getUrl(
            'carriyo/order_shipment/send',
            [
                'order_id'    => $block->getShipment()->getOrderId(),
                'shipment_id' => $block->getShipment()->getId()
            ]
        );
    }

    public function beforeSetLayout(\Magento\Shipping\Block\Adminhtml\View $view)
    {
        if (!$this->configuration->isActive()) return;

        $view->addButton(
            'send-to-carriyo',
            [
                'label' => __('Send Shipment to Carriyo'),
                'class' => 'save',
                'onclick' => 'setLocation(\'' . $this->getSendToCarriyoUrl($view) . '\')'
            ]
        );
    }
}

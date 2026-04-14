<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Plugin\Adminhtml\Order;


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

    public function getSendToCarriyoUrl(\Magento\Sales\Block\Adminhtml\Order\View $block)
    {
        $order = $block->getOrder();
        return $block->getUrl(
            'carriyo/order/send',
            [
                'order_id' => $order->getId()
            ]
        );
    }

    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $view)
    {
        if (!$this->configuration->isActive()) {
            return null;
        }

        $label = $this->configuration->isOrderAndShipmentFlow()
            ? (
                trim((string)$view->getOrder()->getData('carriyo_order_id')) !== ''
                    ? __('Update Carriyo Order')
                    : __('Send Order to Carriyo')
            )
            : __('Send Shipment to Carriyo');

        $view->addButton(
            'send-to-carriyo',
            [
                'label' => $label,
                'class' => 'save',
                'onclick' => 'setLocation(\'' . $this->getSendToCarriyoUrl($view) . '\')'
            ]
        );
    }
}

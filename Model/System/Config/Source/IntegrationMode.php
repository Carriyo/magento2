<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model\System\Config\Source;

use Carriyo\Shipment\Model\Configuration;
use Magento\Framework\Option\ArrayInterface;

class IntegrationMode implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $options[] = [
            'value' => Configuration::INTEGRATION_MODE_SHIPMENTS,
            'label' => __('Shipments'),
        ];
        $options[] = [
            'value' => Configuration::INTEGRATION_MODE_ORDERS,
            'label' => __('Orders'),
        ];

        return $options;
    }
}

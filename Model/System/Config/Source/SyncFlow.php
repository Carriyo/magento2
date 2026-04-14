<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model\System\Config\Source;

use Carriyo\Shipment\Model\Configuration;
use Magento\Framework\Option\ArrayInterface;

class SyncFlow implements ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $options[] = [
            'value' => Configuration::SYNC_FLOW_SHIPMENT_ONLY,
            'label' => __('Shipment Only'),
        ];
        $options[] = [
            'value' => Configuration::SYNC_FLOW_ORDER_AND_SHIPMENT,
            'label' => __('Order and Shipment'),
        ];

        return $options;
    }
}

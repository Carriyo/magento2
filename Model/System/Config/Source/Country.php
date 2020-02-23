<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model\System\Config\Source;

/**
 * Class Country
 *
 * @package Carriyo\Shipment\Model\System\Config\Source
 */
class Country implements\Magento\Framework\Data\OptionSourceInterface
{

    /**
     *
     * @param bool $isMultiselect
     * @param string $foregroundCountries
     * @return array
     */
    public function toOptionArray($isMultiselect = false, $foregroundCountries = '')
    {
        return [
            ['value' => 'AE', 'label' => __('United Arab Emirates')],
            ['value' => 'SA', 'label' => __('Saudi Arabia')]
        ];
    }
}
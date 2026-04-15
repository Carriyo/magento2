<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */
namespace Carriyo\Shipment\Plugin;

use Magento\Framework\DataObject;
use Magento\Sales\Model\Order\Shipment\Track as SalesShipmentTrack;
use Magento\Shipping\Model\Order\Track as ShippingOrderTrack;

class TrackingLink
{
    /**
     * @param SalesShipmentTrack $subject
     * @param bool $isCustom
     * @return bool
     */
    public function afterIsCustom(SalesShipmentTrack $subject, $isCustom)
    {
        $description = (string)$subject->getDescription();
        if (!$isCustom || $subject->getCarrierCode() !== SalesShipmentTrack::CUSTOM_CARRIER_CODE) {
            return $isCustom;
        }
        return !$this->isValidTrackingUrl($description);
    }

    /**
     * @param ShippingOrderTrack $subject
     * @param mixed $numberDetail
     * @return mixed
     */
    public function afterGetNumberDetail(ShippingOrderTrack $subject, $numberDetail)
    {
        $trackingUrl = (string)$subject->getDescription();
        if ($subject->getCarrierCode() !== SalesShipmentTrack::CUSTOM_CARRIER_CODE || !$this->isValidTrackingUrl($trackingUrl)) {
            return $numberDetail;
        }

        $title = $subject->getTitle();
        $number = $subject->getTrackNumber();
        if (is_array($numberDetail)) {
            $title = (string)($numberDetail['title'] ?? $title);
            $number = (string)($numberDetail['number'] ?? $number);
        }

        return new DataObject([
            'carrier_title' => $title,
            'tracking' => $number,
            'url' => $trackingUrl,
        ]);
    }

    /**
     * @param string $trackingUrl
     * @return bool
     */
    private function isValidTrackingUrl($trackingUrl)
    {
        return filter_var($trackingUrl, FILTER_VALIDATE_URL) !== false;
    }
}

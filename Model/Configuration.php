<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Configuration
{
    public const MODULE_NAME = 'Carriyo_Shipment';
    public const INTEGRATION_MODE_SHIPMENTS = 'shipments';
    public const INTEGRATION_MODE_ORDERS = 'orders';
    public const CONFIG_PATH_ACTIVE = 'carriyo/general/active';
    public const CONFIG_PATH_DEBUG = 'carriyo/general/debug';
    public const CONFIG_PATH_INTEGRATION_MODE = 'carriyo/general/integration_mode';
    public const CONFIG_PATH_API_URL = 'carriyo/api_credentials/api_url';
    public const CONFIG_PATH_API_KEY = 'carriyo/api_credentials/api_key';
    public const CONFIG_PATH_TENANT_ID = 'carriyo/api_credentials/tenant_id';
    public const CONFIG_PATH_CLIENT_ID = 'carriyo/api_credentials/client_id';
    public const CONFIG_PATH_CLIENT_SECRET = 'carriyo/api_credentials/client_secret';
    public const CONFIG_PATH_MERCHANT = 'carriyo/api_credentials/merchant';
    public const CONFIG_PATH_LOCATION_CODE = 'carriyo/pickup_address/location_code';
    public const CONFIG_PATH_AUTO_BOOK_SHIPMENTS = 'carriyo/carriyo_mappings/auto_book_shipments';
    public const CONFIG_PATH_SHIPPING_METHODS = 'carriyo/carriyo_mappings/shipping_methods_map';
    public const CONFIG_PATH_ALLOWED_STATUSES_OTHER = 'carriyo/carriyo_mappings/allowed_statuses_other';
    public const CONFIG_PATH_ALLOWED_STATUSES_COD = 'carriyo/carriyo_mappings/allowed_statuses_cod';
    public const CONFIG_PATH_SHIPMENT_STATUS_MAP = 'carriyo/carriyo_mappings/order_status_map';
    public const CONFIG_PATH_ORDER_STATUS_MAP = 'carriyo/order_mappings/order_status_map';
    public const CONFIG_PATH_SHIPMENT_PREFIX = 'carriyo/carriyo_mappings/shipment_reference_prefix';
    public const CONFIG_PATH_ORDER_PREFIX = 'carriyo/order_mappings/order_reference_prefix';

    /**
     * @var ScopeConfigInterface
     */
    private $configReader;

    /**
     * @var EncryptorInterface
     */
    private $decryptor;

    /**
     * @param ScopeConfigInterface $configReader
     * @param EncryptorInterface $decryptor
     */
    public function __construct(
        ScopeConfigInterface $configReader,
        EncryptorInterface $decryptor
    ) {
        $this->configReader = $configReader;
        $this->decryptor = $decryptor;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return (string)$this->decryptor->decrypt($this->configReader->getValue(self::CONFIG_PATH_API_KEY));
    }

    /**
     * @return string
     */
    public function getGrantType()
    {
        return 'client_credentials';
    }

    /**
     * @return string
     */
    public function getTenantId()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_TENANT_ID);
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_CLIENT_ID);
    }

    /**
     * @return string
     */
    public function getClientSecret()
    {
        return (string)$this->decryptor->decrypt($this->configReader->getValue(self::CONFIG_PATH_CLIENT_SECRET));
    }

    /**
     * @return string
     */
    public function getMerchant()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_MERCHANT);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return rtrim((string)$this->configReader->getValue(self::CONFIG_PATH_API_URL), '/');
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool)$this->configReader->getValue(self::CONFIG_PATH_ACTIVE);
    }

    /**
     * @return bool
     */
    public function isDebugEnabled()
    {
        return (bool)$this->configReader->getValue(self::CONFIG_PATH_DEBUG);
    }

    /**
     * @return string
     */
    public function getIntegrationMode()
    {
        $mode = (string)$this->configReader->getValue(self::CONFIG_PATH_INTEGRATION_MODE);

        return in_array($mode, [self::INTEGRATION_MODE_SHIPMENTS, self::INTEGRATION_MODE_ORDERS], true)
            ? $mode
            : self::INTEGRATION_MODE_SHIPMENTS;
    }

    /**
     * @return bool
     */
    public function isOrderMode()
    {
        return $this->getIntegrationMode() === self::INTEGRATION_MODE_ORDERS;
    }

    /**
     * @return bool
     */
    public function isShipmentMode()
    {
        return !$this->isOrderMode();
    }

    /**
     * @return string
     */
    public function getLocationCode()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_LOCATION_CODE);
    }

    /**
     * @return bool
     */
    public function isAutoBookShipments()
    {
        return (bool)$this->configReader->getValue(self::CONFIG_PATH_AUTO_BOOK_SHIPMENTS);
    }

    /**
     * @return array
     */
    public function getAllowedStatusesOther()
    {
        return $this->getCsvValues(self::CONFIG_PATH_ALLOWED_STATUSES_OTHER);
    }

    /**
     * @return array
     */
    public function getAllowedStatusesCOD()
    {
        return $this->getCsvValues(self::CONFIG_PATH_ALLOWED_STATUSES_COD);
    }

    /**
     * @param string $carriyoStatus
     * @return string|null
     */
    public function getMagentoOrderStatus($carriyoStatus)
    {
        return $this->getStatusMap(self::CONFIG_PATH_ORDER_STATUS_MAP)[$carriyoStatus] ?? null;
    }

    /**
     * @return array
     */
    public function getShipmentMappedStatuses()
    {
        return $this->getStatusMap(self::CONFIG_PATH_SHIPMENT_STATUS_MAP);
    }

    /**
     * @param string $code
     * @return string|null
     */
    public function getDeliveryType($code)
    {
        foreach ($this->getCsvValues(self::CONFIG_PATH_SHIPPING_METHODS) as $mapping) {
            [$shippingMethod, $deliveryType] = array_pad(array_map('trim', explode('=', $mapping, 2)), 2, null);
            if ($shippingMethod === $code && $deliveryType !== null && $deliveryType !== '') {
                return $deliveryType;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public function getShipmentReferencePrefix()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_SHIPMENT_PREFIX);
    }

    /**
     * @param string $incrementId
     * @return string
     */
    public function getShipmentReference($incrementId)
    {
        return $this->getShipmentReferencePrefix() !== ''
            ? $this->getShipmentReferencePrefix() . $incrementId
            : (string)$incrementId;
    }

    /**
     * @return string
     */
    public function getOrderReferencePrefix()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_ORDER_PREFIX);
    }

    /**
     * @param string $incrementId
     * @return string
     */
    public function getOrderReference($incrementId)
    {
        return $this->getOrderReferencePrefix() !== ''
            ? $this->getOrderReferencePrefix() . $incrementId
            : (string)$incrementId;
    }

    /**
     * @param string $incrementId
     * @return string
     */
    public function getFulfillmentOrderReference($incrementId)
    {
        return sprintf('%s-1', $this->getOrderReference($incrementId));
    }

    /**
     * @param string $orderReference
     * @return string
     */
    public function getMagentoOrderReference($orderReference)
    {
        $prefix = $this->getOrderReferencePrefix();

        return $prefix !== '' && strpos((string)$orderReference, $prefix) === 0
            ? substr((string)$orderReference, strlen($prefix))
            : (string)$orderReference;
    }

    /**
     * @param string $configPath
     * @return array
     */
    private function getCsvValues($configPath)
    {
        $values = array_map('trim', explode(',', (string)$this->configReader->getValue($configPath)));

        return array_values(array_filter($values, static function ($value) {
            return $value !== '';
        }));
    }

    /**
     * @param string $configPath
     * @return array
     */
    private function getStatusMap($configPath)
    {
        $map = [];
        foreach ($this->getCsvValues($configPath) as $mapping) {
            [$status, $magentoStatus] = array_pad(array_map('trim', explode('=', $mapping, 2)), 2, null);
            if ($status === null || $status === '' || $magentoStatus === null || $magentoStatus === '') {
                continue;
            }
            $map[$status] = $magentoStatus;
        }

        return $map;
    }
}

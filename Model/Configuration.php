<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Model;


use Carriyo\Shipment\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Shipping\Model\Config;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Configuration
 * @package Carriyo\Shipment\Model
 */
class Configuration
{
    const MODULE_NAME = 'Carriyo_Shipment';

    //= General
    const CONFIG_PATH_ACTIVE = 'carriyo/general/active';

    //= API Credentials
    const CONFIG_PATH_API_KEY = 'carriyo/api_credentials/api_key';
    const CONFIG_PATH_TENANT_ID = 'carriyo/api_credentials/tenant_id';
    const CONFIG_PATH_CLIENT_ID = 'carriyo/api_credentials/client_id';
    const CONFIG_PATH_CLIENT_SECRET = 'carriyo/api_credentials/client_secret';
    const CONFIG_PATH_MERCHANT = 'carriyo/api_credentials/merchant';

    //= API Endpoints
    const CONFIG_PATH_API_URL = 'carriyo/api_endpoints/api_url';
    const CONFIG_PATH_API_OAUTH_URL = 'carriyo/api_endpoints/api_oauth_url';

    //= Pickup Address
    const CONFIG_PATH_CONTACT_NAME = 'carriyo/pickup_address/contact_name';
    const CONFIG_PATH_CONTACT_PHONE = 'carriyo/pickup_address/contact_phone';
    const CONFIG_PATH_ADDRESS = 'carriyo/pickup_address/address1';
    const CONFIG_PATH_CITY = 'carriyo/pickup_address/city';
    const CONFIG_PATH_STATE = 'carriyo/pickup_address/state';
    const CONFIG_PATH_COUNTRY = 'carriyo/pickup_address/country';
    const CONFIG_PATH_LOCATION_CODE = 'carriyo/pickup_address/location_code';

    // = Shipping Method Map
    const CONFIG_PATH_SHIPPING_METHODS = 'carriyo/carriyo_mappings/shipping_methods_map';

    const CONFIG_PATH_ALLOWED_STATUSES_OTHER = 'carriyo/carriyo_mappings/allowed_statuses_other';

    const CONFIG_PATH_ALLOWED_STATUSES_COD = 'carriyo/carriyo_mappings/allowed_statuses_cod';

    const CONFIG_PATH_STATUS_MAP = 'carriyo/carriyo_mappings/order_status_map';

    const CONFIG_PATH_SHIPMENT_PREFIX = 'carriyo/carriyo_mappings/shipment_reference_prefix';

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $configReader;

    /**
     * @var EncryptorInterface
     */
    private $decryptor;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $shippingModelConfig;

    /**
     * @method __construct
     * @param ScopeConfigInterface $configReader
     * @param EncryptorInterface $decryptor
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleList
     * @param Config $shippingModelConfig
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $configReader,
        EncryptorInterface $decryptor,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleList,
        Config $shippingModelConfig,
        ScopeConfigInterface $scopeConfig,
        Logger $logger
    )
    {
        $this->configReader = $configReader;
        $this->decryptor = $decryptor;
        $this->storeManager = $storeManager;
        $this->moduleList = $moduleList;
        $this->shippingModelConfig = $shippingModelConfig;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_API_KEY);
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
        return (string)$this->decryptor->decrypt(
            $this->configReader->getValue(self::CONFIG_PATH_CLIENT_SECRET)
        );
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
        return (string)$this->configReader->getValue(self::CONFIG_PATH_API_URL);
    }

    /**
     * @return string
     */
    public function getOauthUrl()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_API_OAUTH_URL);
    }

    /**
     * @return string
     */
    public function getContactName()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_CONTACT_NAME);
    }

    /**
     * @return string
     */
    public function getLocationCode()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_LOCATION_CODE);
    }

    /**
     * @return string
     */
    public function getContactPhone()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_CONTACT_PHONE);
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_ADDRESS);
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_CITY);
    }

    /**
     * @return string
     */
    public function getState()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_STATE);
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_COUNTRY);
    }

    /**
     * @return string
     */
    public function getShippingMethods()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_SHIPPING_METHODS);
    }

    /**
     * @return string
     */
    public function getAllowedStatusesOther()
    {
        $allowedStatuses = $this->configReader->getValue(self::CONFIG_PATH_ALLOWED_STATUSES_OTHER);
        $allowedStatusesList = [];
        foreach (explode(",", $allowedStatuses) as $status) {
            $allowedStatusesList[] = $status;
        }
        return $allowedStatuses;
    }

    /**
     * @return string
     */
    public function getAllowedStatusesCOD()
    {
        $allowedStatuses = $this->configReader->getValue(self::CONFIG_PATH_ALLOWED_STATUSES_COD);
        $allowedStatusesList = [];
        foreach (explode(",", $allowedStatuses) as $status) {
            $allowedStatusesList[] = $status;
        }
        return $allowedStatuses;
    }

    /**
     * @return string
     */
    public function getOrderStatusMap()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_STATUS_MAP);
    }

    /**
     * @return string
     */
    public function getShipmentReferencePrefix()
    {
        return (string)$this->configReader->getValue(self::CONFIG_PATH_SHIPMENT_PREFIX);
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return (bool)$this->configReader->getValue(self::CONFIG_PATH_ACTIVE);
    }

    /**
     * @param $code
     * @return mixed|string
     */
    public function getDeliveryType($code)
    {
        $deliveryType = null;
        try {
            $shippingMap = [];
            foreach (explode(",", $this->getShippingMethods()) as $shipping) {
                $shippingValues = explode("=", $shipping);
                $shippingMap[$shippingValues[0]] = $shippingValues[1];
            }

            if (array_key_exists($code, $this->getActiveShippingMethod())) {
                $deliveryType = $shippingMap[$this->getActiveShippingMethod()[$code]];
            }
        } catch (\Exception $e) {
            //do nothing as value is already defaulted
        }
        return $deliveryType;
    }

    /**
     * @return array
     */
    public function getActiveShippingMethod()
    {
        $activeCarriers = $this->shippingModelConfig->getActiveCarriers();
        $methods = array();
        foreach ($activeCarriers as $shippingCode => $shippingModel) {
            if ($carrierMethods = $shippingModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $shippingCode . '_' . $methodCode;
                    $carrierTitle = $this->scopeConfig->getValue('carriers/' . $shippingCode . '/title');
                    $methods[$code] = $carrierTitle;
                }
            }
        }
        return $methods;
    }

    /**
     * @return array
     */
    public function getCarriyoMappedStatuses()
    {
        $orderStatusMap = [];
        foreach (explode(",", $this->getOrderStatusMap()) as $orderStatus) {
            $orderStatusValues = explode("=", $orderStatus);
            $orderStatusMap[trim($orderStatusValues[0])] = trim($orderStatusValues[1]);
        }
        return $orderStatusMap;
    }

    /**
     * @param $incrementId
     * @return string
     */
    public function getShipmentReference($incrementId)
    {
        if ($this->getShipmentReferencePrefix() !== '') {
            return $this->getShipmentReferencePrefix() . $incrementId;
        }
        return $incrementId;
    }
}

<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Core\Api;

use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Configuration;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;

class Client extends AbstractHttp
{
    /**
     * @var OAuth
     */
    private $oauth;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Configuration $configuration
     * @param SerializerInterface $serializer
     * @param OAuth $oauth
     * @param Logger $logger
     */
    public function __construct(
        Configuration $configuration,
        SerializerInterface $serializer,
        OAuth $oauth,
        Logger $logger
    ) {
        parent::__construct($configuration, $serializer);
        $this->oauth = $oauth;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    public function createOrder(OrderInterface $order)
    {
        return $this->request('post', '/orders', $this->getOrderRequestBody($order), $order);
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    public function updateOrder(OrderInterface $order)
    {
        return $this->request(
            'patch',
            sprintf('/orders/%s?key=partner_order_reference', rawurlencode($this->configuration->getOrderReference($order->getIncrementId()))),
            $this->getOrderRequestBody($order, false),
            $order
        );
    }

    /**
     * @param OrderInterface $order
     * @param bool $isLinkedOrder
     * @return string
     */
    public function getOrderPayloadHash(OrderInterface $order, $isLinkedOrder = false)
    {
        return $this->getPayloadHash($this->getOrderRequestBody($order, !$isLinkedOrder));
    }

    /**
     * @param OrderInterface $order
     * @param bool $autoBookShipments
     * @return string
     */
    public function getShipmentPayloadHash(OrderInterface $order, $autoBookShipments)
    {
        return $this->getPayloadHash([
            'auto_book_shipments' => (bool)$autoBookShipments,
            'body' => $this->getShipmentRequestBody(
                $order,
                $this->configuration->getShipmentReference($order->getIncrementId())
            ),
        ]);
    }

    /**
     * @param string $orderReference
     * @return array
     */
    public function cancelOrder($orderReference)
    {
        return $this->request(
            'post',
            sprintf('/orders/%s/cancel?key=partner_order_reference', rawurlencode($this->configuration->getOrderReference($orderReference))),
            ['cancellation_reason' => 'ORDER_CANCELLED']
        );
    }

    /**
     * @param OrderInterface $order
     * @param bool $autoBookShipments
     * @return array
     */
    public function createShipment(OrderInterface $order, $autoBookShipments)
    {
        $path = '/shipments' . ($autoBookShipments ? '' : '?draft=true');

        return $this->request(
            'post',
            $path,
            $this->getShipmentRequestBody($order, $this->configuration->getShipmentReference($order->getIncrementId())),
            $order
        );
    }

    /**
     * @param OrderInterface $order
     * @param bool $autoBookShipments
     * @return array
     */
    public function updateShipment(OrderInterface $order, $autoBookShipments)
    {
        $shipmentReference = $this->configuration->getShipmentReference($order->getIncrementId());

        return $this->request(
            $autoBookShipments ? 'post' : 'patch',
            $autoBookShipments
                ? sprintf('/shipments/%s/confirm', rawurlencode($shipmentReference))
                : sprintf('/shipments/%s', rawurlencode($shipmentReference)),
            $this->getShipmentRequestBody($order, $shipmentReference),
            $order
        );
    }

    /**
     * @param string $orderReference
     * @return array
     */
    public function cancelShipment($orderReference)
    {
        return $this->request(
            'patch',
            sprintf('/shipments/%s/cancel', rawurlencode($this->configuration->getShipmentReference($orderReference))),
            []
        );
    }

    /**
     * @return void
     */
    protected function beforeGetClient()
    {
        if ($this->headers) {
            return;
        }

        $this->headers = [
            'x-api-key' => $this->configuration->getApiKey(),
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->oauth->getAccessToken(),
            'tenant-id' => $this->configuration->getTenantId()
        ];

        if ($this->configuration->isDebugEnabled()) {
            $this->logger->debug(
                'Carriyo Auth Debug Headers: ' . print_r([
                    'x-api-key' => $this->headers['x-api-key'] ?? null,
                    'tenant-id' => $this->headers['tenant-id'] ?? null,
                    'Authorization' => $this->headers['Authorization'] ?? null,
                ], true)
            );
        }
    }

    /**
     * @param OrderInterface $order
     * @param bool $includePartnerOrderReference
     * @return array
     */
    protected function getOrderRequestBody(OrderInterface $order, $includePartnerOrderReference = true)
    {
        $payment = $order->getPayment();
        $paymentMethod = $payment ? $payment->getMethod() : null;
        $shippingAddress = $order->getShippingAddress() ?: $order->getBillingAddress();
        $billingAddress = $order->getBillingAddress();
        $weightUnit = $this->configuration->getWeightUnit();
        $body = [
            'sales_channel' => 'magento',
            'payment' => [
                'currency' => $order->getOrderCurrencyCode(),
                'order_total' => (float)$order->getGrandTotal(),
                'payment_on_delivery' => $paymentMethod === 'cashondelivery' ? (float)$order->getGrandTotal() : 0.0,
            ],
            'line_items' => [],
        ];
        if ($includePartnerOrderReference) {
            $body['partner_order_reference'] = $this->configuration->getOrderReference($order->getIncrementId());
        }
        $body['delivery_type'] = $this->configuration->getDeliveryType((string)$order->getShippingMethod());
        if ($body['delivery_type'] === null || $body['delivery_type'] === '') {
            unset($body['delivery_type']);
        }

        if ($shippingAddress) {
            $body['delivery_address'] = array_filter([
                'contact_name' => trim(implode(' ', array_filter([$shippingAddress->getFirstname(), $shippingAddress->getLastname()]))),
                'contact_phone' => $shippingAddress->getTelephone(),
                'contact_email' => $shippingAddress->getEmail(),
                'company_name' => $shippingAddress->getCompany(),
                'address1' => implode(', ', (array)$shippingAddress->getStreet()),
                'city' => $shippingAddress->getCity(),
                'state' => $shippingAddress->getRegion(),
                'postcode' => $shippingAddress->getPostcode(),
                'country' => $shippingAddress->getCountryId(),
            ], static function ($value) {
                return $value !== null && $value !== '';
            });
            $body['customer'] = array_filter([
                'contact_name' => trim(implode(' ', array_filter([$order->getCustomerFirstname(), $order->getCustomerLastname()]))),
                'contact_phone' => $shippingAddress->getTelephone(),
                'contact_email' => $order->getCustomerEmail(),
                'address1' => implode(', ', (array)$shippingAddress->getStreet()),
                'city' => $shippingAddress->getCity(),
                'state' => $shippingAddress->getRegion(),
                'postcode' => $shippingAddress->getPostcode(),
                'country' => $shippingAddress->getCountryId(),
            ], static function ($value) {
                return $value !== null && $value !== '';
            });
        }

        if ($billingAddress) {
            $body['billing_address'] = array_filter([
                'contact_name' => trim(implode(' ', array_filter([$billingAddress->getFirstname(), $billingAddress->getLastname()]))),
                'contact_phone' => $billingAddress->getTelephone(),
                'contact_email' => $billingAddress->getEmail(),
                'company_name' => $billingAddress->getCompany(),
                'address1' => implode(', ', (array)$billingAddress->getStreet()),
                'city' => $billingAddress->getCity(),
                'state' => $billingAddress->getRegion(),
                'postcode' => $billingAddress->getPostcode(),
                'country' => $billingAddress->getCountryId(),
            ], static function ($value) {
                return $value !== null && $value !== '';
            });
            $body['customer'] = $body['customer'] ?? array_filter([
                'contact_name' => trim(implode(' ', array_filter([$order->getCustomerFirstname(), $order->getCustomerLastname()]))),
                'contact_phone' => $billingAddress->getTelephone(),
                'contact_email' => $order->getCustomerEmail(),
                'address1' => implode(', ', (array)$billingAddress->getStreet()),
                'city' => $billingAddress->getCity(),
                'state' => $billingAddress->getRegion(),
                'postcode' => $billingAddress->getPostcode(),
                'country' => $billingAddress->getCountryId(),
            ], static function ($value) {
                return $value !== null && $value !== '';
            });
        }

        if ($this->configuration->getMerchant() !== '') {
            $body['merchant'] = $this->configuration->getMerchant();
        }

        if ($order->getShippingDescription() || $order->getShippingMethod()) {
            $body['shipping_lines'] = [[
                'name' => (string)($order->getShippingDescription() ?: $order->getShippingMethod()),
                'carrier' => (string)$order->getShippingMethod(),
                'price' => (float)$order->getShippingAmount(),
            ]];
        }

        $customAttributeMappings = $this->configuration->getCustomAttributeMappings();

        $fulfillmentOrderItems = [];
        foreach ($order->getAllVisibleItems() as $item) {
            if ((int)$item->getQtyOrdered() <= 0) {
                continue;
            }
            $quantity = (int)$item->getQtyOrdered();
            $lineItem = array_filter([
                'id' => (string)$item->getItemId(),
                'sku' => (string)$item->getSku(),
                'description' => (string)$item->getName(),
                'digital' => (bool)$item->getIsVirtual(),
                'quantity' => $quantity,
                'unit_price' => (float)$item->getPriceInclTax(),
                'product_ref' => (string)$item->getItemId(),
                'weight' => (float)$item->getWeight() > 0 && $weightUnit !== null ? [
                    'value' => (float)$item->getWeight(),
                    'unit' => $weightUnit,
                ] : null,
                'dangerous_goods' => false,
            ], static function ($value) {
                return $value !== null && $value !== '';
            });
            $customAttributes = $this->buildCustomAttributesPayload($item, $customAttributeMappings);
            if ($customAttributes) {
                $lineItem['custom_attributes'] = $customAttributes;
            }
            $body['line_items'][] = $lineItem;
            if (!$item->getIsVirtual() && $quantity > 0) {
                $fulfillmentOrderItems[] = [
                    'id' => (string)$item->getItemId(),
                    'quantity' => $quantity,
                ];
            }
        }

        if ($fulfillmentOrderItems) {
            $body['fulfillment_orders'] = [array_filter([
                'partner_fulfillment_order_reference' => $this->configuration->getFulfillmentOrderReference($order->getIncrementId()),
                'location_id' => $this->configuration->getLocationCode(),
                'line_items' => $fulfillmentOrderItems,
            ], static function ($value) {
                return $value !== null && $value !== '';
            })];
        }

        return $body;
    }

    /**
     * @param OrderInterface $order
     * @param string $shipmentReference
     * @return array
     */
    protected function getShipmentRequestBody(OrderInterface $order, $shipmentReference)
    {
        /** @var Address|null $shippingAddress */
        $shippingAddress = $order->getShippingAddress() ?: $order->getBillingAddress();
        $payment = $order->getPayment();
        $paymentMethod = $payment ? $payment->getMethod() : null;
        $items = [];
        $customAttributeMappings = $this->configuration->getCustomAttributeMappings();

        foreach ($order->getItems() as $item) {
            $shipmentItem = [
                'sku' => $item->getSku(),
                'description' => $item->getName(),
                'quantity' => (float)$item->getQtyOrdered(),
                'price' => [
                    'amount' => (float)$item->getPriceInclTax(),
                    'currency' => $order->getOrderCurrencyCode(),
                ],
                'dangerous_goods' => false,
            ];
            $customAttributes = $this->buildCustomAttributesPayload($item, $customAttributeMappings);
            if ($customAttributes) {
                $shipmentItem['custom_attributes'] = $customAttributes;
            }
            $items[] = $shipmentItem;
        }

        $body = [
            'entity_type' => 'FORWARD',
            'source' => [
                'source_type' => 'magento_connector',
            ],
            'references' => [
                'partner_order_reference' => (string)$order->getIncrementId(),
                'partner_shipment_reference' => $shipmentReference,
            ],
            'payment' => [
                'payment_mode' => $paymentMethod === 'cashondelivery' ? 'CASH_ON_DELIVERY' : 'PRE_PAID',
                'total_amount' => (float)$order->getGrandTotal(),
                'pending_amount' => $paymentMethod === 'cashondelivery' ? (float)$order->getGrandTotal() : 0.0,
                'currency' => $order->getOrderCurrencyCode(),
            ],
            'delivery' => array_filter([
                'delivery_type' => $this->configuration->getDeliveryType((string)$order->getShippingMethod()),
                'scheduled_date' => '',
            ], static function ($value) {
                return $value !== null && $value !== '';
            }),
            'items' => $items,
            'pickup' => [
                'partner_location_code' => $this->configuration->getLocationCode(),
            ],
        ];

        if ($shippingAddress) {
            $body['dropoff'] = array_filter([
                'contact_name' => trim(implode(' ', array_filter([$shippingAddress->getFirstname(), $shippingAddress->getLastname()]))),
                'contact_phone' => $shippingAddress->getTelephone(),
                'contact_email' => $shippingAddress->getEmail(),
                'address1' => implode(', ', (array)$shippingAddress->getStreet()),
                'city' => $shippingAddress->getCity(),
                'state' => $shippingAddress->getRegion(),
                'postcode' => $shippingAddress->getPostcode(),
                'country' => $shippingAddress->getCountryId(),
            ], static function ($value) {
                return $value !== null && $value !== '';
            });
        }

        if ($this->configuration->getMerchant() !== '') {
            $body['merchant'] = $this->configuration->getMerchant();
        }

        return $body;
    }

    /**
     * Build the `custom_attributes` payload fragment for an item based on the
     * merchant-configured Magento => Carriyo attribute mappings.
     *
     * @param object $item Magento sales order/shipment item
     * @param array<string, string> $mappings Magento attribute name => Carriyo custom attribute name
     * @return array<string, string[]>
     */
    private function buildCustomAttributesPayload($item, array $mappings)
    {
        $customAttributes = [];
        foreach ($mappings as $magentoName => $carriyoName) {
            $value = $this->resolveMagentoAttributeValue($item, $magentoName);
            if ($value === null || $value === '') {
                continue;
            }
            $customAttributes[$carriyoName] = [(string)$value];
        }

        return $customAttributes;
    }

    /**
     * Resolve a Magento attribute value from an order/shipment item. Looks up
     * extension_attributes first, then product_options.
     *
     * @param object $item
     * @param string $magentoName
     * @return string|null
     */
    private function resolveMagentoAttributeValue($item, $magentoName)
    {
        if ($magentoName === '' || !is_object($item)) {
            return null;
        }

        // 1. extension_attributes
        if (method_exists($item, 'getExtensionAttributes')) {
            $extension = $item->getExtensionAttributes();
            if ($extension !== null) {
                if (method_exists($extension, 'getDataUsingMethod')) {
                    $value = $extension->getDataUsingMethod($magentoName);
                    if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                        return (string)$value;
                    }
                }
                if (method_exists($extension, 'getData')) {
                    $value = $extension->getData($magentoName);
                    if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                        return (string)$value;
                    }
                }
            }
        }

        // 2. product_options (serialized array commonly under info_buyRequest, attributes_info, options)
        $productOptions = method_exists($item, 'getProductOptions') ? $item->getProductOptions() : null;
        if (is_array($productOptions)) {
            $value = $this->lookupInProductOptions($productOptions, $magentoName);
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        // 3. Fallback to direct getData on the item itself
        if (method_exists($item, 'getData')) {
            $value = $item->getData($magentoName);
            if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                return (string)$value;
            }
        }

        return null;
    }

    /**
     * Search for the named attribute inside a Magento product_options array.
     *
     * @param array $productOptions
     * @param string $magentoName
     * @return string|null
     */
    private function lookupInProductOptions(array $productOptions, $magentoName)
    {
        if (array_key_exists($magentoName, $productOptions)) {
            $value = $productOptions[$magentoName];
            if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                return (string)$value;
            }
        }

        foreach (['info_buyRequest', 'attributes_info', 'options', 'additional_options'] as $bucket) {
            if (!isset($productOptions[$bucket])) {
                continue;
            }
            $section = $productOptions[$bucket];
            if (!is_array($section)) {
                continue;
            }
            if (array_key_exists($magentoName, $section)) {
                $value = $section[$magentoName];
                if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                    return (string)$value;
                }
            }
            // attributes_info / options are typically lists of {label, value, code} entries.
            foreach ($section as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $code = $entry['code'] ?? $entry['label'] ?? null;
                if ($code !== null && $code === $magentoName && isset($entry['value'])) {
                    $value = $entry['value'];
                    if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                        return (string)$value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizePayload($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizePayload($item);
        }

        if ($this->isAssoc($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param array $value
     * @return bool
     */
    private function isAssoc(array $value)
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @param array $payload
     * @return string
     */
    private function getPayloadHash(array $payload)
    {
        return hash('sha256', json_encode($this->normalizePayload($payload)));
    }

    /**
     * @param string $method
     * @param string $path
     * @param array $body
     * @param OrderInterface|null $order
     * @return array
     */
    private function request($method, $path, array $body, OrderInterface $order = null)
    {
        try {
            if ($order && $this->configuration->isDebugEnabled()) {
                $this->logger->debug(
                    sprintf('Carriyo %s Request Body %s %s', strtoupper($method), $order->getIncrementId(), print_r($body, true))
                );
            }

            $response = $this->getClient()->{$method}($this->configuration->getUrl() . $path, ['json' => $body]);

            return $this->serializer->unserialize($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            $this->logger->error(sprintf('Carriyo %s Exception %s', strtoupper($method), $exception->getMessage()));

            return ['errors' => $exception->getMessage()];
        }
    }
}

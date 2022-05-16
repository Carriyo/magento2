<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Core\Api;


use Carriyo\Shipment\Logger\Logger;
use Carriyo\Shipment\Model\Configuration;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;

/**
 * Class Client
 *
 * @package Carriyo\Shipment\Core\Api
 */
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
     * Client constructor.
     * @param Configuration $configuration
     * @param SerializerInterface $serializer
     * @param OAuth $oauth
     */
    public function __construct(
        Configuration $configuration,
        SerializerInterface $serializer,
        OAuth $oauth,
        Logger $logger
    )
    {
        parent::__construct($configuration, $serializer);
        $this->oauth = $oauth;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface $order
     * @param \Magento\Sales\Api\Data\ShipmentInterface $shipment
     * @return array|bool|float|int|string|null
     */
    public function sendOrderDraft(OrderInterface $order)
    {
        $response = null;
        try {
            /** @var Address $shippingAddress */
            $body = $this->getRequestBody($order);
            $this->logger->info("Carriyo Request {$order->getIncrementId()} " . print_r($body, 1));
            $response = $this->getClient()
                ->post($this->configuration->getUrl() . '/shipments?draft=true', ['json' => $body]);

        } catch (\Exception $exception) {
            $this->logger->info('Failed sending draft shipment to ' . $this->configuration->getUrl());
            $this->logger->info('Carriyo sendOrderDraft Exception ' . $exception->getMessage());
            return ['errors' => $exception->getMessage()];
        }
        return $this->serializer->unserialize($response->getBody()->getContents());
    }

    /**
     * @param $orderId
     * @return array|bool|float|int|string|null
     */
    public function sendOrderCancel($orderId)
    {
        $response = null;
        try {
            $this->logger->info("Carriyo Cancel Request " . $orderId);
            $response = $this->getClient()
                ->post($this->configuration->getUrl() . "/shipments/" . $this->configuration->getShipmentReference($orderId) . "/cancel");

        } catch (\GuzzleHttp\Exception\ClientException $exception) {
            $this->logger->info('Carriyo sendOrderCancel Exception ' . $exception->getMessage());
            return ['errors' => $exception->getMessage()];
        }
        return $this->serializer->unserialize($response->getBody()->getContents());
    }

    /**
     * @param OrderInterface $order
     * @return array|bool|float|int|string|null
     */
    public function sendUpdateOrderDraft(OrderInterface $order)
    {
        $response = null;
        try {
            /** @var Address $shippingAddress */
            $body = $this->getRequestBody($order);
            $this->logger->info("Carriyo Request {$order->getIncrementId()} " . print_r($body, 1));
            $response = $this->getClient()
                ->post($this->configuration->getUrl() . "/shipments?draft=true", ['json' => $body]);

        } catch (\GuzzleHttp\Exception\ClientException $exception) {
            $this->logger->info('Failed sending draft shipment to ' . $this->configuration->getUrl());
            $this->logger->info('Carriyo sendOrderDraft Exception ' . $exception->getMessage());
            return ['errors' => $exception->getMessage()];
        }
        return $this->serializer->unserialize($response->getBody()->getContents());
    }

    /**
     * @param OrderInterface $order
     * @param \Magento\Sales\Api\Data\ShipmentInterface $shipment
     * @return array|bool|float|int|string|null
     */
    public function send(OrderInterface $order, $shipment)
    {
        $response = null;
        try {
            /** @var Address $shippingAddress */
            $shippingAddress = $order->getShippingAddress();
            $payment = $order->getPayment();
            $paymentMethod = $payment->getMethod();

            $items = [];
            foreach ($shipment->getItems() as $item) {
                $items[] = [
                    'sku' => $item->getSku(),
                    'description' => $item->getName(),
                    'quantity' => (float)$item->getQty(),
                    'price' => [
                        'amount' => (float)$item->getPrice(),
                        'currency' => $order->getOrderCurrencyCode()
                    ]
                ];
            }

            $body = [
                'references' => [
                    'partner_order_reference' => $order->getIncrementId(),
                    'partner_shipment_reference' => $this->configuration->getShipmentReference($shipment->getIncrementId()),
                ],
                'payment' => [
                    'payment_mode' => $paymentMethod === 'cashondelivery' ? 'CASH_ON_DELIVERY' : 'PRE_PAID',
                    'total_amount' => $order->getGrandTotal(),
                    'pending_amount' => $paymentMethod === 'cashondelivery' ? $order->getGrandTotal() : 0,
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'delivery' => [
                    'delivery_type' => $this->configuration->getDeliveryType($order->getShippingMethod())
                ],
                'items' => $items,
                'pickup' => [
                    'partner_location_code' => $this->configuration->getLocationCode()
                ],
                'dropoff' => [
                    'contact_name' => implode(" ", [$shippingAddress->getFirstname(), $shippingAddress->getLastname()]),
                    'contact_phone' => $shippingAddress->getTelephone(),
                    'contact_email' => $shippingAddress->getEmail(),
                    'address1' => implode(",", $shippingAddress->getStreet()),
                    'city' => $shippingAddress->getCity(),
                    'state' => $shippingAddress->getRegion(),
                    'postcode' => $shippingAddress->getPostcode(),
                    'country' => $shippingAddress->getCountryId(),
                ]
            ];

            if (!empty($this->configuration->getMerchant())) {
                $body['merchant'] = $this->configuration->getMerchant();
            }

            $response = $this->getClient()
                ->post($this->configuration->getUrl() . '/shipments', ['json' => $body]);
        } catch (\GuzzleHttp\Exception\GuzzleException $exception) {
            return [
                'errors' => ['Error trying to reach Carriyo, please create the shipment manually']
            ];
        } catch (\GuzzleHttp\Exception\ClientException $exception) {
            //$message = $this->serializer->unserialize($exception->getResponse()->getBody()->getContents())['errors'];
            return ['errors' => $exception->getMessage()];
        }

        return $this->serializer->unserialize($response->getBody()->getContents());
    }

    protected function beforeGetClient()
    {
        if (!$this->headers) {
            $this->headers = [
                'x-api-key' => $this->configuration->getApiKey(),
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->oauth->getAccessToken(),
                'tenant-id' => $this->configuration->getTenantId()
            ];
        }
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    protected function getRequestBody(OrderInterface $order)
    {
        $shippingAddress = $order->getShippingAddress();
        $payment = $order->getPayment();
        $paymentMethod = $payment->getMethod();

        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'description' => $item->getName(),
                'quantity' => (float)$item->getQtyOrdered(),
                'price' => [
                    'amount' => (float)$item->getPriceInclTax(),
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'dangerous_goods' => false
            ];
        }

        $body = [
            'references' => [
                'partner_order_reference' => $order->getIncrementId(),
                'partner_shipment_reference' => $this->configuration->getShipmentReference($order->getIncrementId())
            ],
            'payment' => [
                'payment_mode' => $paymentMethod === 'cashondelivery' ? 'CASH_ON_DELIVERY' : 'PRE_PAID',
                'total_amount' => $order->getGrandTotal(),
                'pending_amount' => $paymentMethod === 'cashondelivery' ? $order->getGrandTotal() : 0,
                'currency' => $order->getOrderCurrencyCode()
            ],
            'delivery' => [
                'delivery_type' => $this->configuration->getDeliveryType($order->getShippingMethod()),
                'scheduled_date' => ''
            ],
            'items' => $items,
            'pickup' => [
                'partner_location_code' => $this->configuration->getLocationCode()
            ],
            'dropoff' => [
                'contact_name' => implode(" ", [$shippingAddress->getFirstname(), $shippingAddress->getLastname()]),
                'contact_phone' => $shippingAddress->getTelephone(),
                'contact_email' => $shippingAddress->getEmail(),
                'address1' => implode(",", $shippingAddress->getStreet()),
                'city' => $shippingAddress->getCity(),
                'state' => $shippingAddress->getRegion(),
                'postcode' => $shippingAddress->getPostcode(),
                'country' => $shippingAddress->getCountryId(),
            ],
            'merchant' => $this->configuration->getMerchant()
        ];
        return $body;
    }
}

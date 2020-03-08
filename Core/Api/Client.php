<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Core\Api;


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
     * Client constructor.
     * @param Configuration $configuration
     * @param SerializerInterface $serializer
     * @param OAuth $oauth
     */
    public function __construct(
        Configuration $configuration,
        SerializerInterface $serializer,
        OAuth $oauth
    )
    {
        parent::__construct($configuration, $serializer);
        $this->oauth = $oauth;
    }

    /**
     * @param OrderInterface $order
     * @return array|bool|float|int|string|null
     */
    public function send(OrderInterface $order)
    {
        $response = null;
        try {
            /** @var Address $shippingAddress */
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
                        'amount' => (float)$item->getPrice(),
                        'currency' => $order->getOrderCurrencyCode()
                    ]
                ];
            }

            $body = [
                'merchant' => $this->configuration->getMerchant(),
                'references' => [
                    'partner_order_reference' => $order->getEntityId()
                ],
                'payment' => [
                    'payment_mode' => $paymentMethod === 'cashondelivery' ? 'CASH_ON_DELIVERY' : 'PRE_PAID',
                    'total_amount' => $payment->getAmountOrdered(),
                    'pending_amount' => $payment->getAmountOrdered(),
                    'currency' => $order->getOrderCurrencyCode()
                ],
                'delivery' => [
                    'delivery_type' => $this->configuration->getDeliveryType($order->getShippingMethod())
                ],
                'items' => $items,
                'pickup' => [
                    'contact_name' => $this->configuration->getContactName(),
                    'contact_phone' => $this->configuration->getContactPhone(),
                    'address1' => $this->configuration->getAddress(),
                    'city' => $this->configuration->getCity(),
                    'state' => $this->configuration->getState(),
                    'country' => $this->configuration->getCountry(),
                ],
                'dropoff' => [
                    'contact_name' => implode(" ", [$shippingAddress->getFirstname(), $shippingAddress->getLastname()]),
                    'contact_phone' => $shippingAddress->getTelephone(),
                    'contact_email' => $shippingAddress->getEmail(),
                    'address1' => implode(",", $shippingAddress->getStreet()),
                    'city' => $shippingAddress->getCity(),
                    'state' => $shippingAddress->getRegion(),
                    'postcode' => $shippingAddress->getPostcode(),
                    'country' => 'AE',
                ]
            ];

            $response = $this->getClient()
                ->post($this->configuration->getUrl() . '/shipments', ['json' => $body]);
        } catch (\GuzzleHttp\Exception\ClientException $exception) {
            $response = $exception->getResponse();
        } catch (\GuzzleHttp\Exception\GuzzleException $exception) {
            return [
                'errors' => ['Error trying to reach Carriyo, please create the shipment manually']
            ];
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
}
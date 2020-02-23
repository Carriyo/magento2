<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Core\Api;


use Carriyo\Shipment\Model\Configuration;
use GuzzleHttp\Client;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class AbstractHttp
 * @package Carriyo\Shipment\Core\Api
 */
abstract class AbstractHttp
{
    /**
     * @var int
     */
    const TIMEOUT = 15;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var array
     */
    protected $headers;

    /**
     * AbstractHttp constructor.
     * @param Configuration $configuration
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Configuration $configuration,
        SerializerInterface $serializer
    )
    {
        $this->configuration = $configuration;
        $this->serializer = $serializer;
    }

    protected function beforeGetClient(){}

    /**
     * @return Client
     */
    protected function getClient()
    {
        $this->beforeGetClient();
        return new Client([
            'headers' => $this->headers,
            'timeout' => self::TIMEOUT,
        ]);
    }
}
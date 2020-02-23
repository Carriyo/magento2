<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Core\Api;


/**
 * Class OAuth
 * @package Carriyo\Shipment\Core\Api
 */
class OAuth extends AbstractHttp
{
    /**
     * @var String
     */
    private $accessToken;

    protected $headers = [
        'Content-Type' => 'application/x-www-form-urlencoded'
    ];

    /**
     * @return mixed
     */
    public function getAccessToken()
    {
        if (!$this->accessToken) {
            $client = $this->getClient();

            $body = [
                'grant_type' => $this->configuration->getGrantType(),
                'client_id' => $this->configuration->getClientId(),
                'client_secret' => $this->configuration->getClientSecret(),
                'audience' => $this->configuration->getAudience()
            ];

            $response = $client->post(
                $this->configuration->getOauthUrl() . '/oauth/token',
                [ 'json' => $body ]
            );
            $this->accessToken = $this->serializer
                ->unserialize($response->getBody()->getContents())['access_token'];
        }

        return $this->accessToken;
    }
}
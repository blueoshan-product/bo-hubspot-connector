<?php

namespace Blueoshan\HubspotConnector\Api;

interface ConnectorInterface
{
    /**
     * Store APP Token
     * @param string $token
     * @return string
     */
    public function postIntegration($token);
}
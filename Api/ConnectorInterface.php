<?php

namespace Blueoshan\HubspotConnector\Api;

interface ConnectorInterface
{
    /**
     * GET for Post api
     * @param string $token
     * @return string
     */
    public function postIntegration($token);
}
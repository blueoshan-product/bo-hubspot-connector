<?php

namespace Blueoshan\HubspotConnector\Api;

interface AbandonedCartInterface
{
    /**
     * GET for Post api
     * @param string $quoteId
     * @return string
     */
    public function getAbandonedCart($quoteId);
}
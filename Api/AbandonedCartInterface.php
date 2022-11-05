<?php

namespace Blueoshan\HubspotConnector\Api;

interface AbandonedCartInterface
{
    /**
     * Get Masked Id for Abandoned Cart
     * @param string $quoteId
     * @return string
     */
    public function getAbandonedCart($quoteId);
}
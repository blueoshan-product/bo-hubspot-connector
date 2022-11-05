<?php

namespace Blueoshan\HubspotConnector\Api;

interface AttributeInterface
{
    /**
     * GET Attributes List API
     * @param string $object
     * @return string
     */
    public function getAttributes($object);
}
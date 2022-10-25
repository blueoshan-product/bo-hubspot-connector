<?php

namespace Blueoshan\HubspotConnector\Api;

interface AttributeInterface
{
    /**
     * GET for Post api
     * @param string $object
     * @return string
     */
    public function getAttributes($object);
}
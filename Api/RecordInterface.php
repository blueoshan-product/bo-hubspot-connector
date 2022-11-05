<?php

namespace Blueoshan\HubspotConnector\Api;

interface RecordInterface
{
    /**
     * Update HubSpot Record ID for objects
     * @param string $object
     * @param string $objectId
     * @param string $hubid
     * @return string
     */
    public function updateHubId($object,$objectId,$hubid);
}
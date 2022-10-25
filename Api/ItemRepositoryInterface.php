<?php

namespace Blueoshan\HubspotConnector\Api;

interface ItemRepositoryInterface
{
    /**
     * @return \Blueoshan\HubspotConnector\Api\Data\AttributeInterface[]
     */
    public function getList();
}
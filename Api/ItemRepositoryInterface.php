<?php

namespace Blueoshan\HubspotConnector\Api;

interface ItemRepositoryInterface
{
    /**
     * @return \Blueoshan\HubspotConnector\Api\Data\ItemInterface[]
     */
    public function getList();
}
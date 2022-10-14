<?php

namespace Blueoshan\HubspotConnector\Model;
use Blueoshan\HubspotConnector\Api\ItemRepositoryInterface;
use Blueoshan\HubspotConnector\Model\ResourceModel\Item\CollectionFactory;

class ItemRepository implements ItemRepositoryInterface
{
    private $collectionFactory;

    public function __construct(CollectionFactory $collectionFactory) 
    {
        $this->collectionFactory = $collectionFactory;
    }

    public function getList()
    {
        return $this->collectionFactory->create()->getItems();
    }

}
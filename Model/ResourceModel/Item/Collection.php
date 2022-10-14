<?php 

namespace Blueoshan\HubspotConnector\Model\ResourceModel\Item;

use Blueoshan\HubspotConnector\Model\Item;
use Blueoshan\HubspotConnector\Model\ResourceModel\Item as ResourceModelItem;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';
    
    protected function _construct()
    {
        $this->_init(Item::class,ResourceModelItem::class);
    }
}

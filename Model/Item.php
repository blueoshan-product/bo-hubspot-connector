<?php 

namespace Blueoshan\HubspotConnector\Model;

use Blueoshan\HubspotConnector\Model\ResourceModel\Item as ResourceModelItem;
use Magento\Framework\Model\AbstractModel;

class Item extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModelItem::class);
    }
}



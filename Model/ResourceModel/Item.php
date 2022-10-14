<?php 

namespace Blueoshan\HubspotConnector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Item extends AbstractDb
{
 protected function _construct()
 {
    $this->_init('blueoshan_sample_item','id');
 }   
}

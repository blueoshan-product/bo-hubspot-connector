<?php 

namespace Blueoshan\HubspotConnector\Block;

use Blueoshan\HubspotConnector\Model\ResourceModel\Item\Collection;
use Blueoshan\HubspotConnector\Model\ResourceModel\Item\CollectionFactory;
use Magento\Framework\View\Element\Template;

class Hello extends Template
{
    private $collectionFactory;
    
    public function __construct(
        Template\Context $context, 
        CollectionFactory $collectionFactory,
        array $data = []
    )
    {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context,$data);
    }

    /**
     * @return \Blueoshan\HubspotConnector\Model\Item[]
     */

    public function getItems()
    {
        return $this->collectionFactory->create()->getItems();
    }
}
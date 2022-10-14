<?php
namespace Blueoshan\HubspotConnector\Observer;

use Exception;
use Magento\Framework\Event\Observer;

/**
 * Class AfterCustomer
 * @package Blueoshan\HubspotConnector\Observer
 */
class AfterCustomerAddress extends AfterSave
{

    /**
     * @var int
     */
    protected $i = 0;

    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getDataObject();
        $this->helper->logData($observer->getEvent()->getName(),$item);
        if ($item->getBoNew()) {
            if ($this->i === 0) {
                parent::execute($observer); 
            }
            $this->i++;
        } else {
            $this->updateObserver($observer);
        }
    }
}

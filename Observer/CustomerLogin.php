<?php

namespace Blueoshan\HubspotConnector\Observer;

use Exception;
use Magento\Framework\Event\Observer;

/**
 * Class CustomerLogin
 * @package Blueoshan\HubspotConnector\Observer
 */
class CustomerLogin extends AfterSave
{
    
    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getCustomer();
        $this->helper->logData($observer->getEvent()->getName(),$item);
        $pushData = $this->helper->sendHttpRequest($observer);
    }
}

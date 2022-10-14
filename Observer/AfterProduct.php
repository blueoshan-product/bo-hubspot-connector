<?php

namespace Blueoshan\HubspotConnector\Observer;

use Exception;
use Magento\Framework\Event\Observer;

/**
 * Class AfterProduct
 * @package Blueoshan\HubspotConnector\Observer
 */
class AfterProduct extends AfterSave
{

    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getDataObject();
        if ($item->getMpNew()) {
            parent::execute($observer); 
        } else {
            $this->updateObserver($observer);
        }
        $item->storeDetails = $this->helper->getItemStore($item);
        $this->helper->logData($observer->getEvent()->getName(),$item);
        // $body = json_encode(array(
        //     'event' =>  $observer->getEvent()->getName(),
        //     'data' => $item->getData()
        // ));
        $pushData = $this->helper->sendHttpRequest($observer);
    }
}

<?php
namespace Blueoshan\HubspotConnector\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Newsletter\Model\Subscriber as SubscriberMagento;
use Magento\Store\Model\Store;

/**
 * Class CustomerLogin
 * @package Blueoshan\HubspotConnector\Observer
 */
class Subscriber extends AfterSave
{
    /**
     * @param Observer $observer
     *
     * @return $this|void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getEvent()->getSubscriber();
        $subscriberStatus = $item->getSubscriberStatus();

        if ($subscriberStatus === SubscriberMagento::STATUS_UNSUBSCRIBED) {
            return $this;
        }

        $item->storeDetails = $this->helper->getItemStore($item);
        $this->helper->logData($observer->getEvent()->getName(),$item);
        $pushData = $this->helper->sendHttpRequest($observer);
        return $this;
    }
}

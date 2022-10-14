<?php
namespace Blueoshan\HubspotConnector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Blueoshan\HubspotConnector\Helper\Data;

class BeforeSave implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * BeforeSave constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->helper->isConnectorEnabled()) {
            return;
        }
        $item = $observer->getDataObject();
        if ($item->isObjectNew()) {
            $item->setBoNew(1);
        }
        $this->helper->logData($observer->getEvent()->getName(),$item);
    }
}

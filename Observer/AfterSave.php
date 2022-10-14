<?php
namespace Blueoshan\HubspotConnector\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Blueoshan\HubspotConnector\Helper\Data;

/**
 * Class AfterSave
 * @package Blueoshan\HubspotConnector\Observer
 */
abstract class AfterSave implements ObserverInterface
{
    
    /**
     * @var Data
     */
    protected $helper;

    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * AfterSave constructor.
     *
     * @param ManagerInterface $messageManager
     * @param StoreManagerInterface $storeManager
     * @param Data $helper
     */
    public function __construct(
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        Data $helper
    ) {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $item = $observer->getDataObject();
        // return var_dump(\Magento\Framework\Serialize\JsonConverter::convert($observer->getEvent()->getData('items')));
        // $item->storeDetails = $this->helper->getItemStore($item);
        $this->helper->logData($observer->getEvent()->getName(),$item);
        // $body = \Magento\Framework\Serialize\JsonConverter::convert(array(
        //     'event' =>  $observer->getEvent()->getName(),
        //     'data' => $item->getData()
        // ));
        $pushData = $this->helper->sendHttpRequest($observer);
    }

    /**
     * @param $observer
     *
     * @throws Exception
     */
    protected function updateObserver($observer)
    {
        $item = $observer->getDataObject();
        $item->storeDetails = $this->helper->getItemStore($item);
        $this->helper->logData($observer->getEvent()->getName(),$item);
        // $body = \Magento\Framework\Serialize\JsonConverter::convert(array(
        //     'event' =>  $observer->getEvent()->getName(),
        //     'data' => $item->getData()
        // ));
        $pushData = $this->helper->sendHttpRequest($observer);
    }
}

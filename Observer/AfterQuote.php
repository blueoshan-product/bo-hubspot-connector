<?php

namespace Blueoshan\HubspotConnector\Observer;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Blueoshan\HubspotConnector\Helper\Data;
       
/**
 * Class AfterQuote
 * @package Blueoshan\HubspotConnector\Observer
 */
class AfterQuote implements ObserverInterface
{
    protected $loggerInterface;

    /**
     * @var Data
     */
    protected $helper;

    public function __construct(LoggerInterface $loggerInterface,Data $helper)
    {
        $this->loggerInterface = $loggerInterface;
        $this->helper = $helper;        
    }
    /**
     * @param Observer $observer
     *
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        $websiteId = $this->helper->getWebsiteId();
        $storeId = $this->helper->getStoreId();
        $storeCode = $this->helper->getStoreCode();
        $storeName = $this->helper->getStoreName();
        $stores=$this->helper->getStores();
        $result = $this->helper->objToArray($observer->getEvent()->getQuote());
        $customerGroups = $this->helper->getCustomerGroups();
        $eventName = $observer->getEvent()->getName();
        $body = array();
        $body["eventName"] = $observer->getEvent()->getName();
        $result['items'] = array();
        $quote = $observer->getEvent()->getQuote();
        foreach ($quote->getAllItems() as $item) {
            $result['items'][] = $this->helper->objToArray($item);
        }
        if (method_exists($quote,'getShippingAddress')) {
            $result["shipping_address"] = $this->helper->objToArray($quote->getShippingAddress());
        }
        if (method_exists($quote,'getBillingAddress')) {
            $result["billing_address"] = $this->helper->objToArray($quote->getBillingAddress());
        }
        $groupId = (int) $quote->getCustomerGroupId();
        if (isset($customerGroups[$groupId])) {
            $result['customer_group'] = $customerGroups[$groupId];
        } else {
            $result['customer_group'] = 'Guest';
        }
        $result['original_active_status'] = $quote->getOrigData('is_active');
        $body["data"] = $result;
        $body["storeData"] = [
            "websiteId" => $websiteId,
            "storeId" => $storeId,
            "storeCode" => $storeCode,
            "storeName" => $storeName,
            "storeURL" => (isset($stores[$storeId]['store_url']))?  $stores[$storeId]['store_url']: $this->helper->getBaseUrl(),
            "mediaURL" => (isset($stores[$storeId]['media_url']))?  $stores[$storeId]['media_url']:$this->helper->getMediaUrl()
        ];
        $this->loggerInterface->debug(json_encode($observer->getEvent()->getName()).' is '.json_encode($result));
        if ($this->helper->isConnectorEnabled($quote->getStoreId())) {
            $this->helper->sendDataToHook($body);
        }
        
    }
}

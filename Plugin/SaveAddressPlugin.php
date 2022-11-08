<?php
namespace Blueoshan\HubspotConnector\Plugin;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Blueoshan\HubspotConnector\Helper\Data;
use Psr\Log\LoggerInterface;

class SaveAddressPlugin
{
    protected $helper;
    protected $loggerInterface;

    public function __construct(
        Data $helper,
        LoggerInterface $loggerInterface
    )
    {
        $this->helper = $helper;
        $this->loggerInterface = $loggerInterface;
    }
    /**
     * @param ShippingInformationManagementInterface $subject
     * @param PaymentDetailsInterface $result
     * @param int $cartId
     * @return PaymentDetailsInterface
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        PaymentDetailsInterface $result,
        int $cartId
    ) {
        $websiteId = $this->helper->getWebsiteId();
        $storeId = $this->helper->getStoreId();
        $storeCode = $this->helper->getStoreCode();
        $storeName = $this->helper->getStoreName();
        $stores=$this->helper->getStores();
        $quote = $this->helper->getQuote($cartId);
        $output = $this->helper->objToArray($quote);
        $eventName = "quote_address_save_after";
        $customerGroups = $this->helper->getCustomerGroups();
        $body = array();
        $body["eventName"] = $eventName;
        $output['items'] = array();
        $groupId = (int) $quote->getCustomerGroupId();
        if (isset($customerGroups[$groupId])) {
            $output['customer_group'] = $customerGroups[$groupId];
        } else {
            $output['customer_group'] = 'Guest';
        }
        foreach ($quote->getAllItems() as $item) {
            $output['items'][] = $this->helper->objToArray($item);
        }
        if (method_exists($quote,'getShippingAddress')) {
            $output["shipping_address"] = $this->helper->objToArray($quote->getShippingAddress());
        }
        if (method_exists($quote,'getBillingAddress')) {
            $output["billing_address"] = $this->helper->objToArray($quote->getBillingAddress());
        }
        $body["data"] = $output;
        $body["storeData"] = [
            "websiteId" => $websiteId,
            "storeId" => $storeId,
            "storeCode" => $storeCode,
            "storeName" => $storeName,
            "storeURL" => (isset($stores[$storeId]['store_url']))?  $stores[$storeId]['store_url']: $this->helper->getBaseUrl(),
            "mediaURL" => (isset($stores[$storeId]['media_url']))?  $stores[$storeId]['media_url']:$this->helper->getMediaUrl()
        ];
        $this->loggerInterface->debug(json_encode($eventName).' is '.json_encode($body));
        if ($this->helper->isConnectorEnabled($quote->getStoreId())) {
            $this->helper->sendDataToHook($body);
        }

        return $result;
    }
}

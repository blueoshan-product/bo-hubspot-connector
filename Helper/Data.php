<?php

namespace Blueoshan\HubspotConnector\Helper;
use Exception;
use DateTime;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Psr\Log\LoggerInterface;
use Zend_Http_Response;

class Data extends AbstractHelper
{

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var CustomerGroupCollection
     */
    protected $customerGroup;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var UrlInterface
     */
    protected $backendUrl;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customer;
    /**
     * @var ProductMetadataInterface
     */
    protected $metaData;
    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $backendUrl
     * @param CustomerGroupCollection $customerGroup
     * @param TransportBuilder $transportBuilder
     * @param CurlFactory $curlFactory
     * @param ProductMetadataInterface $metaData
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CustomerRepositoryInterface $customer
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        CustomerGroupCollection $customerGroup,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrl,
        TransportBuilder $transportBuilder,
        ProductMetadataInterface $metaData,
        CurlFactory $curlFactory,
        CustomerRepositoryInterface $customer,
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->backendUrl       = $backendUrl;
        $this->customerGroup = $customerGroup;
        $this->customer         = $customer;
        $this->storeManager     = $storeManager;
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->metaData = $metaData;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }
    public function logData($logName,$item)
    {
        $this->logger->debug($logName . ' is ' . \Magento\Framework\Serialize\JsonConverter::convert($this->objToArray($item)));
    }
    /**
     * @param $item
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getItemStore($item)
    {
        if (method_exists($item, 'getData')) {
            return $item->getData('store_id') ?: $this->storeManager->getStore()->getId();
        }

        return $this->storeManager->getStore()->getId();
    }
    /**
     * Website Id
     *
     * @return int
     */
    public function getWebsiteId()
    {
        return $this->storeManager->getWebsite()->getId();
    }

    /**
     * Store Code
     *
     * @return string
     */
    public function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Store URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
    }

    /**
     * Store Name
     *
     * @return string
     */
    public function getStoreName()
    {
        return $this->storeManager->getStore()->getName();
    }

    /**
     * Store Media URL
     *
     * @return string
     */
    public function getMediaUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * Store Object
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    public function getStore()
    {
        return $this->storeManager->getStore();
    }

    /**
     * Get all the web and media url for the stores
     *
     * @return array
     */
    public function getStores()
    {
        foreach ($this->storeManager->getStores(true) as $store) {
            $storeId = $store->getId();
            $result[$storeId] = array(
                'store_id' => $storeId,
                'website_id' => $store->getWebsiteId(),
                'store_url' => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB),
                'media_url' => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
            );
        }
        return $result;
    }

    /**
     * @param mixed $cartId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote($cartId)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $cartId = $quoteIdMask->getQuoteId() ?: $cartId;

        return $this->cartRepository->get($cartId);
    }

    /**
     * @param $body
     *
     * @return array
     */
    public function sendHttpRequest($item)
    {
        if (!$this->isConnectorEnabled()) {
            return;
        }

        $eventName = $item->getEvent()->getName();
        $endpoint = "";
        if($eventName == "customer_save_after" || $eventName == "customer_address_save_after" || $eventName == "newsletter_subscriber_save_after"){
            $endpoint = "customer_webhook";
        }else if($eventName == "sales_order_save_after" || $eventName == "sales_order_status_history_save_after" || $eventName == "sales_order_shipment_save_after" || $eventName == "sales_order_invoice_save_after" || $eventName == "sales_quote_save_after" || $eventName == "quote_address_save_after"){
            $endpoint = "order_webhook";
        }else if($eventName == "catalog_product_save_after" || $eventName == "catalog_product_delete_before"){
            $endpoint = "product_webhook";
        }

        $url = $this->getConfigGeneral('blueoshan/webhook/hook_url').'/'.$endpoint;
        
        $method = 'POST';
        
        $body = $this->generateBody($item);
        $this->logger->debug('Final Data is ' . $body);
        $headersConfig = [];
        $headersConfig[] = 'X-APP-KEY: ' . $this->getConfigGeneral('blueoshan/connection/apptoken');
        $headersConfig[] = 'Content-Type: application/json';
        $curl = $this->curlFactory->create();
        
        $curl->write($method, $url, '1.1', $headersConfig, $body);

        $result = ['success' => false];

        try {
            $resultCurl         = $curl->read();
            $result['response'] = $resultCurl;
            if (!empty($resultCurl)) {
                $result['status'] = Zend_Http_Response::extractCode($resultCurl);
                if (isset($result['status']) && $this->isSuccess($result['status'])) {
                    $result['success'] = true;
                } else {
                    $result['message'] = __('Cannot connect to server. Please try again later.');
                }
            } else {
                $result['message'] = __('Cannot connect to server. Please try again later.');
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }
        $curl->close();

        return $result;
    }
    /**
     * @param array $body
     * 
     * @return array
     */
    public function sendDataToHook($body)
    {
        if (!$this->isConnectorEnabled()) {
            return;
        }
        

        $url = $this->getConfigGeneral('blueoshan/webhook/hook_url').'/order_webhook';
        
        $method = 'POST';

        $headersConfig = [];
        $headersConfig[] = 'X-APP-KEY: ' . $this->getConfigGeneral('blueoshan/connection/apptoken');
        $headersConfig[] = 'Content-Type: application/json';
        $curl = $this->curlFactory->create();
        
        $curl->write($method, $url, '1.1', $headersConfig, json_encode($body));

        $result = ['success' => false];

        try {
            $resultCurl         = $curl->read();
            $result['response'] = $resultCurl;
            if (!empty($resultCurl)) {
                $result['status'] = Zend_Http_Response::extractCode($resultCurl);
                if (isset($result['status']) && $this->isSuccess($result['status'])) {
                    $result['success'] = true;
                } else {
                    $result['message'] = __('Cannot connect to server. Please try again later.');
                }
            } else {
                $result['message'] = __('Cannot connect to server. Please try again later.');
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }
        $curl->close();
        
        return $result;
    }
    public function generateBody($item)
    {
        $websiteId = $this->getWebsiteId();
        $storeId = $this->getStoreId();
        $storeCode = $this->getStoreCode();
        $storeName = $this->getStoreName();
        $stores=$this->getStores();
        $customerGroups = $this->getCustomerGroups();
        $body = array();
        $eventName = $item->getEvent()->getName();
        $event = $item->getEvent();
        $body["eventName"] = $item->getEvent()->getName();
        $data = $this->objToArray($item->getDataObject());
        if($eventName == "customer_login" || $eventName == "customer_save_after"){
            $data = $this->objToArray($item->getCustomer());
            $customer = $item->getCustomer();
            $groupId = (int) $customer->getCustomerGroupId();
            if (isset($customerGroups[$groupId])) {
                $data['customer_group'] = $customerGroups[$groupId];
            } else {
                $data['customer_group'] = 'Guest';
            }
        }
        if($eventName != "customer_login"){
            if (is_object( $item->getDataObject() ) && method_exists($item->getDataObject(),'getShippingAddress')) {
                $data["shipping_address"] = $this->objToArray($item->getDataObject()->getShippingAddress());
            }
            if (is_object( $item->getDataObject() ) && method_exists($item->getDataObject(),'getImage')) {
                $data["product_image"] = $item->getDataObject()->getImage();
            }
            if (is_object( $item->getDataObject() ) && method_exists($item->getDataObject(),'getBillingAddress')) {
                $data["billing_address"] = $this->objToArray($item->getDataObject()->getBillingAddress());
            }
            if (is_object( $item->getDataObject() ) && method_exists($item->getDataObject(),'getAllItems')) {
                $data['items'] = [];
                foreach ($item->getDataObject()->getAllItems() as $item) {
                    $data['items'][] = $this->objToArray($item);
                }
            }
        }
        if($eventName == "sales_order_save_after"){
            $order = $event->getOrder();
            $data['payment_info'] = $this->objToArray($order->getPayment());
            $tracksCollection = $order->getTracksCollection();
            $data['trackCollection'] = [];
            foreach ($tracksCollection->getItems() as $track) {
                $data['trackCollection'][] = $this->objToArray($track);
            }
            $data['statusHistories'] = [];
            foreach($order->getStatusHistories() as $history){
                $data['statusHistories'][] = $this->objToArray($history);
            }
            $data['original_status'] = $order->getOrigData('status');
            $data['original_state'] = $order->getOrigData('state');
            $groupId = (int) $order->getCustomerGroupId();
            if (isset($customerGroups[$groupId])) {
                $data['customer_group'] = $customerGroups[$groupId];
            } else {
                $data['customer_group'] = 'Guest';
            }
        }
        $timestamp = new DateTime();
        $timestamp->setTimezone(new \DateTimeZone('Asia/Kolkata')); 
        $data['event_timestamp'] = $timestamp->format('Y-m-d H:i:s');
        // if(is_object( $item->getDataObject() ) && method_exists($item->getDataObject(),'tracks')){
        //     $data['tracks'] = $this->objToArray($item->getDataObject()->getTracksCollection());
        // }
        // if($eventName == "sales_order_shipment_save_after"){
        //     $shipment = $item->getEvent()->getShipment();
        //     $tracksCollection = $shipment->getTracksCollection();
        //     $tracks = array();
        //     foreach ($tracksCollection->getItems() as $track) {
        //         $tracks['trackingNumber'] = $track->getTrackNumber();
        //         $tracks['carrierName'] = $track->getTitle();
        //     }
        //     $data['trackCollection'] = $tracks;
        // }
        
        $body["data"] = $data;
        $body["storeData"] = [
            "websiteId" => $websiteId,
            "storeId" => $storeId,
            "storeCode" => $storeCode,
            "storeName" => $storeName,
            "storeVersion" => $this->metaData->getVersion(),
            "storeURL" => (isset($stores[$storeId]['store_url']))?  $stores[$storeId]['store_url']: $this->getBaseUrl(),
            "mediaURL" => (isset($stores[$storeId]['media_url']))?  $stores[$storeId]['media_url']:$this->getMediaUrl()
        ];
        return json_encode($body);
    }
    /**
     * Convert Object data into array
     *
     * @return array
     */
	public function objToArray($data, $i=0)
    {
        $result = array();
        $i++;

        if (is_object($data)) {
            foreach ($data->getData() as $attribute => $value) {
                if ((! preg_match('/^[_,attributes]/', $attribute))
                 && ((is_object($value)
                     && ($value instanceof \Magento\Catalog\Model\Product\Interceptor || $value instanceof ProductInterface) &&
                            $i<2)
                      || (is_array($value)))) {
                    $result[$attribute] = $this->objToArray($value, $i);
                } else {
                    $result[$attribute] = $value;
                }
            }
        } elseif (is_array($data)) {
            foreach ($data as $k => $v) {
                $result[$k] = $this->objToArray($v, $i);
            }
        } else {
            return $data;
        }

        return $result;
    }
    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }
    /**
     * @param $classPath
     *
     * @return mixed
     */
    public function getObjectClass($classPath)
    {
        return $this->objectManager->create($classPath);
    }
    /**
     * @param $code
     *
     * @return bool
     */
    public function isSuccess($code)
    {
        return (200 <= $code && 300 > $code);
    }
    /**
     * Check the connector status
     *
     * @return int
     */
    public function isConnectorEnabled()
    {
        return (int) $this->scopeConfig->getValue(
            'blueoshan/connection/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    /**
     * Customer Group
     *
     * @return array
     */
    public function getCustomerGroups()
    {
        $output = array();

        foreach ($this->customerGroup as $group) {
            $output[$group->getId()] = $group->getCustomerGroupCode();
        }
        return $output;
    }

    public function getConfigGeneral($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

}
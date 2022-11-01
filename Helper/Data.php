<?php

namespace Blueoshan\HubspotConnector\Helper;
use Exception;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->backendUrl       = $backendUrl;
        $this->customerGroup = $customerGroup;
        $this->customer         = $customer;
        $this->storeManager     = $storeManager;
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
     * @param $body
     * @param $method
     *
     * @return array
     */
    public function sendHttpRequest($item)
    {
        if (!$this->isConnectorEnabled()) {
            return;
        }
        $url = $this->getConfigGeneral('blueoshan/webhook/hook_url');
        
        $method = 'POST';
        
        $body = $this->generateBody($item);
        $this->logger->debug('Final Data is ' . $body);
        
        $client = new \GuzzleHttp\Client();
        
        $result = $client->request($method, $url, [
            'verify' => false,
            'headers'   => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'X-APP-KEY'     => $this->getConfigGeneral('blueoshan/connection/apptoken')
            ],
            'json' => $body
        ]);

        return $result;
    }
    public function generateBody($item)
    {
        $websiteId = $this->getWebsiteId();
        $storeId = $this->getStoreId();
        $storeCode = $this->getStoreCode();
        $storeName = $this->getStoreName();
        $stores=$this->getStores();
        $body = array();
        $eventName = $item->getEvent()->getName();
        $body["eventName"] = $item->getEvent()->getName();
        $data = $this->objToArray($item->getDataObject());
        if($eventName == "customer_login"){
            $data = $this->objToArray($item->getCustomer());
        }
        if($eventName != "customer_login"){
            if (method_exists($item->getDataObject(),'getShippingAddress')) {
                $data["shipping_address"] = $this->objToArray($item->getDataObject()->getShippingAddress());
            }
            if (method_exists($item->getDataObject(),'getBillingAddress')) {
                $data["billing_address"] = $this->objToArray($item->getDataObject()->getBillingAddress());
            }
            if (method_exists($item->getDataObject(),'getAllItems')) {
                $data['items'] = [];
                foreach ($item->getDataObject()->getAllItems() as $item) {
                    $data['items'][] = $this->objToArray($item);
                }
            }
        }
        if($eventName == "sales_order_save_after" || $eventName == "sales_order_shipment_save_after"){
            if (method_exists($item->getDataObject(),'getPayment')) {
                $data['payments'] = $this->objToArray($item->getDataObject()->getPayment());
            }
            if(method_exists($item->getDataObject(),'getTracksCollection')){
                $data['tracks'] = $this->objToArray($item->getDataObject()->getData());
            }
        }
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
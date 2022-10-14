<?php

namespace Blueoshan\HubspotConnector\Helper;
use Exception;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
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
     * @param TransportBuilder $transportBuilder
     * @param CurlFactory $curlFactory
     * @param ProductMetadataInterface $metaData
     * @param CustomerRepositoryInterface $customer
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
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
        $this->customer         = $customer;
        $this->storeManager     = $storeManager;
        $this->metaData = $metaData;
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
        parent::__construct($context);
    }
    public function logData($logName,$item)
    {
        $this->logger->debug($logName . ' is ' . \Magento\Framework\Serialize\JsonConverter::convert(method_exists($item, 'getData')?$item->getData():$this->objToArray($item)));
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
     * Get website id
     *
     * @return int
     */
    public function getWebsiteId()
    {
        return $this->storeManager->getWebsite()->getId();
    }

    /**
     * Get store code
     *
     * @return string
     */
    public function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Get application base url
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
    }

    /**
     * Get module user key
     *
     * @return string
     */
    public function getMediaUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * Get application store object
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
        
        $headersConfig[] = 'Content-Type: application/json';
        $headersConfig[] = 'X-MAGENTOSTORE-VERSION : ' .$this->metaData->getVersion();
        $headersConfig[] = 'X-ROOT-DOMAIN : '. $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $body = $this->generateBody($item);

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
    public function generateBody($item)
    {
        $websiteId = $this->getWebsiteId();
        $storeId = $this->getStoreId();
        $storeCode = $this->getStoreCode();
        $stores=$this->getStores();
        $body = array();
        $body["eventName"] = $item->getEvent()->getName();
        $this->logData($item->getEvent()->getName() , $item->getDataObject());
        $data = $this->objToArray($item->getDataObject());
        if($item->getEvent()->getName() == "customer_login"){
            $data = $this->objToArray($item->getCustomer());
        }

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
        if (method_exists($item->getDataObject(),'getPayment')) {
            $data['payments'] = $this->objToArray($item->getDataObject()->getPayment());
        }
        if(method_exists($item->getDataObject(),'getTracks')){
            $data['tracks'] = $this->objToArray($item->getDataObject()->getTracks());
        }
        // if($item->getDataObject()->getTracksCollection()){
        //     $data->setData('shipmentTracks', $data->getTracksCollection()->getData());
        // }
        if(method_exists($item->getDataObject(),'getPackages')){
            $data['packages'] = $this->objToArray($item->getDataObject()->getPackages());
        }
        if (method_exists($item->getDataObject(),'getShipmentsCollection')) {
            $data['shipments'] = $this->objToArray($item->getDataObject()->getShipmentsCollection());
        }
        $body["data"] = $data;
        $body["storeData"] = [
            "websiteId" => $websiteId,
            "storeId" => $storeId,
            "storeCode" => $storeCode,
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

    public function getConfigGeneral($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

}
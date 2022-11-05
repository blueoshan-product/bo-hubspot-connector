<?php

namespace Blueoshan\HubspotConnector\Cron;

use DateInterval;
use DateTime;
use Exception;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Blueoshan\HubspotConnector\Helper\Data;
use Psr\Log\LoggerInterface;
use Zend_Http_Response;

/**
 * Class AbandonedCart
 * @package Blueoshan\HubspotConnector\Cron
 */
class AbandonedCart
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;
    
    /**
     * @var Data
     */
    protected $helper;

    /**
     *
     * @var \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory
     */
    protected $quoteCollection;

    /**
     *
     * @var \Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory
     */
    protected $quoteItemCollection;

    private $quoteIdMaskFactory;

    /**
     * AbandonedCart constructor.
     * @param LoggerInterface $logger
     * @param QuoteFactory $quoteFactory
     * @param StoreManagerInterface $storeManager
     * @param CurlFactory $curlFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollection
     * @param \Magento\Quote\Model\ResourceModel\Quote\Item\Collection $quoteItemCollection
     * @param Data $helper
     */
    public function __construct(
        LoggerInterface $logger,
        QuoteFactory $quoteFactory,
        StoreManagerInterface $storeManager,
        Data $helper,
        CurlFactory $curlFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteCollection $quoteCollection,
        QuoteItemCollection $quoteItemCollection
    ) {
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->curlFactory = $curlFactory;
        $this->quoteCollection = $quoteCollection;
        $this->quoteItemCollection = $quoteItemCollection;
    }

    /**
     * @throws Exception
     */
    public function execute()
    {
        if (!$this->helper->isConnectorEnabled()) {
            return;
        }
        $websiteId = $this->helper->getWebsiteId();
        $storeId = $this->helper->getStoreId();
        $storeCode = $this->helper->getStoreCode();
        $storeName = $this->helper->getStoreName();
        $stores=$this->helper->getStores();
        $body = array();
        $body["eventName"] = "abandoned_cart";
        $customerGroups = $this->helper->getCustomerGroups();
        $this->logger->debug('Customer Group is '. json_encode($customerGroups));
        $abandonedTime = (int)$this->helper->getConfigGeneral('blueoshan/webhook/abandoned_time');
        $this->logger->debug('Abandoned Time is '.$abandonedTime);
        $update = (new DateTime())->sub(new DateInterval("PT{$abandonedTime}H"));
        $updateTo = clone $update;
        $updateFrom = $update->sub(new DateInterval("PT1H"));
        $this->logger->debug('Updated From is '.$updateFrom->format('Y-m-d H:i:s'));
        $this->logger->debug('Updated To is '.$updateTo->format('Y-m-d H:i:s'));

        /** @var Collection $quoteCollection */
        $quoteCollection = $this->quoteFactory->create()->getCollection()
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('updated_at', ['from' => $updateFrom])
            ->addFieldToFilter('updated_at', ['to' => $updateTo]);

        /** @var Collection $noneUpdateQuoteCollection */
        $noneUpdateQuoteCollection = $this->quoteFactory->create()->getCollection()
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('created_at', ['from' => $updateFrom])
            ->addFieldToFilter('created_at', ['to' => $updateTo])
            ->addFieldToFilter('updated_at', ['eq' => '0000-00-00 00:00:00']);

        try {
            foreach ($quoteCollection as $quote) {
                $output = $this->helper->objToArray($quote);
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quote->getId(), 'quote_id');
                $maskedQuoteId = $quoteIdMask->getMaskedId();
                if ($quoteIdMask->getMaskedId() === null) {
                    $quoteIdMask->setQuoteId($quote->getId())->save();
                    $maskedQuoteId = $quoteIdMask->getMaskedId();
                }
                $output['shipping_address'] = $this->helper->objToArray($quote->getShippingAddress());
                $output['billing_address'] = $this->helper->objToArray($quote->getBillingAddress());
                $output['abandoned_cart_url'] = $this->storeManager->getStore(
                    $quote->getStoreId()
                )->getUrl(
                    'blueoshan/cart/recovercart',
                    [
                        'token'      => $maskedQuoteId
                    ]
                );
                $output['items'] = [];
                foreach ($quote->getAllItems() as $item) {
                        $output['items'][] = $this->helper->objToArray($item);
                }
                $groupId = (int) $quote->getCustomerGroupId();
                if (isset($customerGroups[$groupId])) {
                    $output['customer_group'] = $customerGroups[$groupId];
                } else {
                    $output['customer_group'] = 'Guest';
                }
                $body["data"] = $output;
                $this->logger->debug('Abandoned cart is' . json_encode($output));
                $body["storeData"] = [
                    "websiteId" => $websiteId,
                    "storeId" => $storeId,
                    "storeCode" => $storeCode,
                    "storeName" => $storeName,
                    "storeURL" => (isset($stores[$storeId]['store_url']))?  $stores[$storeId]['store_url']: $this->helper->getBaseUrl(),
                    "mediaURL" => (isset($stores[$storeId]['media_url']))?  $stores[$storeId]['media_url']:$this->helper->getMediaUrl()
                ];
                $url = $this->helper->getConfigGeneral('blueoshan/webhook/hook_url');
        
                $method = 'POST';

                $headersConfig = [];
                $headersConfig[] = 'X-APP-KEY: ' . $this->helper->getConfigGeneral('blueoshan/connection/apptoken');
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
            }
            foreach ($noneUpdateQuoteCollection as $quote) {
                $output = $this->helper->objToArray($quote);
                $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quote->getId(), 'quote_id');
                $maskedQuoteId = $quoteIdMask->getMaskedId();
                if ($quoteIdMask->getMaskedId() === null) {
                    $quoteIdMask->setQuoteId($quote->getId())->save();
                    $maskedQuoteId = $quoteIdMask->getMaskedId();
                }
                $output['shipping_address'] = $this->helper->objToArray($quote->getShippingAddress());
                $output['billing_address'] = $this->helper->objToArray($quote->getBillingAddress());
                $output['abandoned_cart_url'] = $this->storeManager->getStore(
                    $quote->getStoreId()
                )->getUrl(
                    'blueoshan/cart/recovercart',
                    [
                        'token'      => $maskedQuoteId
                    ]
                );
                $output['items'] = [];
                foreach ($quote->getAllItems() as $item) {
                        $output['items'][] = $this->helper->objToArray($item);
                }
                $groupId = (int) $quote->getCustomerGroupId();
                if (isset($customerGroups[$groupId])) {
                    $output['customer_group'] = $customerGroups[$groupId];
                } else {
                    $output['customer_group'] = 'Guest';
                }
                $body["data"] = $output;
                $this->logger->debug('Abandoned cart is' . json_encode($output));
                $body["storeData"] = [
                    "websiteId" => $websiteId,
                    "storeId" => $storeId,
                    "storeCode" => $storeCode,
                    "storeName" => $storeName,
                    "storeURL" => (isset($stores[$storeId]['store_url']))?  $stores[$storeId]['store_url']: $this->helper->getBaseUrl(),
                    "mediaURL" => (isset($stores[$storeId]['media_url']))?  $stores[$storeId]['media_url']:$this->helper->getMediaUrl()
                ];
                $url = $this->helper->getConfigGeneral('blueoshan/webhook/hook_url');
        
                $method = 'POST';

                $headersConfig = [];
                $headersConfig[] = 'X-APP-KEY: ' . $this->helper->getConfigGeneral('blueoshan/connection/apptoken');
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
            }
        } catch (Exception $e) {
            $this->logger->debug('Abandoned cart webhook error ' . $e->getMessage());
            $this->logger->critical($e->getMessage());
        }
    }
}

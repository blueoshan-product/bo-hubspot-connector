<?php

namespace Blueoshan\HubspotConnector\Cron;

use DateInterval;
use DateTime;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Blueoshan\HubspotConnector\Helper\Data;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;

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
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteCollection $quoteCollection,
        QuoteItemCollection $quoteItemCollection
    ) {
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
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
                $this->logger->debug('Abandoned cart is' . json_encode($output));
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
                $this->logger->debug('Abandoned cart is' . json_encode($output));
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
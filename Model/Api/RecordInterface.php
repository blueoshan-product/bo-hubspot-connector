<?php

namespace Blueoshan\HubspotConnector\Model\Api;

use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class RecordInterface
{
protected $logger;
protected $orderRepository;
protected $quoteRepository;

public function __construct(
    LoggerInterface $logger,
    OrderRepositoryInterface $orderRepository,
    CartRepositoryInterface $quoteRepository
)
{
    $this->logger = $logger;
    $this->orderRepository = $orderRepository;
    $this->quoteRepository = $quoteRepository;
}

/**
     * @inheritdoc
     */

public function updateHubId($object,$objectId,$hubid)
{
    if($object == "order"){
        $order = $this->orderRepository->get($objectId);
        $order->setHubRecordId($hubid);
        $this->orderRepository->save($order);
    }else if($object == "quote"){
        $quote = $this->quoteRepository->get($objectId);
        $quote->setHubRecordId($hubid);
        $this->quoteRepository->save($quote);
    }   
    $result = [];
    $result["success"] = true;
    return json_encode($result);
}

}
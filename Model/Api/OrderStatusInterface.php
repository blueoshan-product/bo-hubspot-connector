<?php

namespace Blueoshan\HubspotConnector\Model\Api;

use Psr\Log\LoggerInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;

class OrderStatusInterface
{

protected $logger;
private $orderStatusCollection;

public function __construct(
    LoggerInterface $logger,
    OrderStatusCollection $orderStatusCollection
)
{
    $this->logger = $logger;
    $this->orderStatusCollection=$orderStatusCollection;
}

/**
     * @inheritdoc
     */
public function getOrderStatuses()
{
    return $this->orderStatusCollection->toOptionArray();
}

}
<?php

namespace Blueoshan\HubspotConnector\Model\Api;

use Psr\Log\LoggerInterface;

class AttributeInterface
{
protected $logger;
protected $resourceConnection;

public function __construct(LoggerInterface $logger,\Magento\Framework\App\ResourceConnection $resourceConnection)
{
    $this->logger = $logger;
    $this->resourceConnection = $resourceConnection;
}

/**
     * @inheritdoc
     */

public function getAttributes($object)
{
    return $this->getFields($object);
}
public function getFields($object)
{
    $fields = $this->resourceConnection->getConnection()->describeTable($object);
    return $fields;
}
}
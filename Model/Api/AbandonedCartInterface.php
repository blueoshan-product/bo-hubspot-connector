<?php

namespace Blueoshan\HubspotConnector\Model\Api;

use Psr\Log\LoggerInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class AbandonedCartInterface
{
protected $logger;
private $quoteIdMaskFactory;

public function __construct(
    QuoteIdMaskFactory $quoteIdMaskFactory,
    LoggerInterface $logger
)
{
    $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    $this->logger = $logger;
}

/**
     * @inheritdoc
     */

public function getAbandonedCart($quoteId)
{
    $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'quote_id');
    $maskedQuoteId = $quoteIdMask->getMaskedId();
    if ($quoteIdMask->getMaskedId() === null) {
        $quoteIdMask->setQuoteId($quoteId)->save();
        $maskedQuoteId = $quoteIdMask->getMaskedId();
    }
    $result = array();
    $result["maskedId"] = $maskedQuoteId;
    return $result;
}

}
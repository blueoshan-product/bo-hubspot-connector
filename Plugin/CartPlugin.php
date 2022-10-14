<?php

namespace Unific\Connector\Plugin;

use Magento\Checkout\Model\Cart as ModelCart;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class CartPlugin extends AbstractPlugin
{
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function afterSave(ModelCart $subject, ModelCart $result)
    {
        $this->logger->debug('Product data is' . json_encode([
            'cart' => $result->getStoredData()
        ]));
        $client = new Client(); //GuzzleHttp\Client
        $result = $client->post('https://webhook.site/abc5c21b-2e81-4a9e-84f3-b1e5cd2787c0', [
            'body' => json_encode([
                'data' => $subject->getStoredData()
            ])
        ]);

        return $result;
    }
}

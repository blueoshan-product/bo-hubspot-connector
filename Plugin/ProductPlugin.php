<?php

namespace Blueoshan\HubspotConnector\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class ProductPlugin
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Catalog\Model\Product $subject
     * @return \Magento\Catalog\Model\Product
     */
    public function afterSave(Product $subject)
    {
        
        $this->logger->debug('Product data is' . json_encode([
            'id' => $subject->getId(),
            'name' => $subject->getName(),
            'sku' => $subject->getSku(),
            'image' => $subject->getImage(),
            'createdAt'=> $subject->getCreatedAt(),
            'updatedAt' => $subject->getUpdatedAt()
        ]));
        $client = new Client(); //GuzzleHttp\Client
        $result = $client->post('https://webhook.site/abc5c21b-2e81-4a9e-84f3-b1e5cd2787c0', [
            'body' => json_encode([
                'id' => $subject->getId(),
                'name' => $subject->getName(),
                'description' => $subject->getDescription(),
                'sku' => $subject->getSku(),
                'category' => $subject->getCategory(),
                'category_ids' => $subject->getCategoryIds(),
                'url_key' => $subject->getUrlKey(),
                'product_url' => $subject->getProductUrl(),
                'quantity' => $subject->getQty(),
                'image' => $subject->getImage(),
                'store_id' => $subject->getStoreId(),
                'store_data' => $subject->getStoredData(),
                'createdAt'=> $subject->getCreatedAt(),
                'updatedAt' => $subject->getUpdatedAt()
            ])
        ]);

        return $subject;
    }
}

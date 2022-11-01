<?php

namespace Blueoshan\HubspotConnector\Model\Api;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ConnectorInterface
{
protected $logger;
protected $configInterface;
protected $scopeInterface;

public function __construct(
    LoggerInterface $logger,
    ConfigInterface $configInterface,
    ScopeConfigInterface $scopeInterface
)
{
    $this->logger = $logger;
    $this->configInterface = $configInterface;
    $this->scopeInterface = $scopeInterface;
}

/**
     * @inheritdoc
     */

public function postIntegration($token)
{
    $this->configInterface->saveConfig(
        'blueoshan/connection/apptoken',
        $token,
        ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        0
    );
    $result = [];
    $result["success"] = true;
    $result["token"] = $token;
    return json_encode($result);
}

}
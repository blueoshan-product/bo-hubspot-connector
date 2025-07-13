<?php
namespace Blueoshan\HubspotConnector\Logger;

use Monolog\Logger;
use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    protected $fileName = '/var/log/bohubspot_connector.log';  // <--- simple, flat file in var/log/
    protected $loggerType = Logger::DEBUG;
}

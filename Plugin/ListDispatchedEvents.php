<?php
 
namespace Blueoshan\HubspotConnector\Plugin;
use Psr\Log\LoggerInterface;
class ListDispatchedEvents
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function beforeDispatch($subject, $eventName, array $data = [])
    {
        $this->logger->debug('Event name is' . $eventName);
    }
 
}
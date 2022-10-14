<?php
namespace Blueoshan\HubspotConnector\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;


class Config
{
	const XML_PATH_ENABLED = 'blueoshan/general/enabled';

	protected $config;

	protected function _construct(ScopeConfigInterface $config)
	{
		$this->config = $config;
	}

	public function isEnabled()
	{
		return $this->config->getValue(self::XML_PATH_ENABLED);
	}

}
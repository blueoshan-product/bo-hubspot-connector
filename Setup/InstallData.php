<?php

namespace Blueoshan\HubspotConnector\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
	

	public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		$setup->startSetup();

        $setup->getConnection()->insert(
            $setup->getTable('blueoshan_sample_item'),
            [
                'name'=>"Item 1"
            ]
        );

        $setup->getConnection()->insert(
            $setup->getTable('blueoshan_sample_item'),
            [
                'name'=>"Item 2"
            ]
        );

        $setup->endSetup();
	}
}
<?php

namespace Blueoshan\HubspotConnector\Setup;

use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeData implements UpgradeDataInterface
{
	
	public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
        $setup->startSetup();
		if (version_compare($context->getVersion(), '1.0.1', '<')) {
			$setup->getConnection()->update(
                $setup->getTable('blueoshan_sample_item'),
                [
                    'description' => 'Default description'
                ],
                $setup->getConnection()->quoteInto('id = ?',1)
            );
		}
        $setup->endSetup();
	}
}
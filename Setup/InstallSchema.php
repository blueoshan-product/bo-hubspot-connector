<?php

namespace Blueoshan\HubspotConnector\Setup;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{

	public function install(\Magento\Framework\Setup\SchemaSetupInterface $setup, \Magento\Framework\Setup\ModuleContextInterface $context)
	{
		$setup->startSetup();

        $table = $setup->getConnection()->newTable(
           $setup->getTable('blueoshan_sample_item') 
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity'=>true,'nullable'=>false,'primary'=>true]
        )->addColumn(
            'name',
            Table::TYPE_TEXT,
            255,
            ['nullable'=>false],
            'Item Name'
        )->addIndex(
            $setup->getIdxName('blueoshan_sample_item', ['name']),
            ['name']
        )->setComment(
            'Sample Items'
        );

        $setup->getConnection()->createTable($table);

		$setup->endSetup();
	}
}
<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();
        $table = $setup->getTable('sales_order');
        if (!$connection->tableColumnExists($table, 'carriyo_order_id')) {
            $connection->addColumn($table, 'carriyo_order_id', [
                'type' => Table::TYPE_TEXT,
                'length' => 64,
                'nullable' => true,
                'comment' => 'Carriyo Order ID',
            ]);
        }
        if (!$connection->tableColumnExists($table, 'carriyo_export_hash')) {
            $connection->addColumn($table, 'carriyo_export_hash', [
                'type' => Table::TYPE_TEXT,
                'length' => 64,
                'nullable' => true,
                'comment' => 'Carriyo Export Hash',
            ]);
        }
        $setup->endSetup();
    }
}

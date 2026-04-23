<?php
/**
 * Copyright © Carriyo. All rights reserved.
 * https://www.carriyo.com | info@carriyo.com
 */

namespace Carriyo\Shipment\Setup;

use Carriyo\Shipment\Model\Configuration;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        if ($context->getVersion() && version_compare($context->getVersion(), '2.0.0', '>=')) {
            return;
        }

        $setup->startSetup();
        try {
            $connection = $setup->getConnection();
            $table = $setup->getTable('core_config_data');
            $path = Configuration::CONFIG_PATH_INTEGRATION_MODE;
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['config_id', 'scope', 'scope_id', 'value'])
                    ->where('path = ?', $path)
            );

            foreach ($rows as $row) {
                $value = $row['value'] ?? null;
                $normalizedValue = $value === 'shipment_only'
                    ? Configuration::INTEGRATION_MODE_SHIPMENTS
                    : ($value === 'order_and_shipment' ? Configuration::INTEGRATION_MODE_ORDERS : $value);
                if (!in_array($normalizedValue, [
                    Configuration::INTEGRATION_MODE_SHIPMENTS,
                    Configuration::INTEGRATION_MODE_ORDERS,
                ], true)) {
                    $normalizedValue = Configuration::INTEGRATION_MODE_SHIPMENTS;
                }
                if ($normalizedValue === $value) {
                    continue;
                }

                $connection->update(
                    $table,
                    ['value' => $normalizedValue],
                    ['config_id = ?' => (int)$row['config_id']]
                );
            }

            if ($rows === []) {
                $connection->insert($table, [
                    'scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    'scope_id' => 0,
                    'path' => $path,
                    'value' => Configuration::INTEGRATION_MODE_SHIPMENTS,
                ]);
            }
        } catch (\Throwable $exception) {
            // Fall back to config.xml default for fresh installs or if migration fails.
        }
        $setup->endSetup();
    }
}

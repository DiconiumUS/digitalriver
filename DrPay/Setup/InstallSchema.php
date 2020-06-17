<?php

namespace Digitalriver\DrPay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if ($installer->getConnection()->isTableExists("electronic_fulfillment") != true) {
            $table = $installer->getConnection()
                ->newTable("electronic_fulfillment")
                ->addColumn(
                    'entity_id',
                    Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                    'Entity ID'
                )
                ->addColumn(
                    'request_obj',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false],
                    'Request Object'
                )
                ->addColumn(
                    'requisition_id',
                    Table::TYPE_TEXT,
                    25,
                    ['nullable' => false],
                    'requisitionID'
                )
                ->addColumn(
                    'line_item_ids',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => true],
                    'Line Item Ids'
                )
                ->addColumn(
                    'post_status',
                    Table::TYPE_SMALLINT,
                    null,
                    ['nullable' => false, 'default' => 0],
                    'Status'
                );
            $installer->getConnection()->createTable($table);
            
            $setup->getConnection()->addIndex(
                $setup->getTable('electronic_fulfillment'),
                'ELECTRONIC_FULFILLMENT_REQUISITION_ID',
                'requisition_id'
            );
        }
        $installer->endSetup();
    }
}

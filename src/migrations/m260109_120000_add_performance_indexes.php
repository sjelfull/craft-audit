<?php

namespace superbig\audit\migrations;

use craft\db\Migration;

/**
 * m260109_120000_add_performance_indexes migration.
 */
class m260109_120000_add_performance_indexes extends Migration
{
    protected string $tableName = '{{%audit_log}}';

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Add index for dateCreated - used by pruneLogs() queries
        $this->createIndex(null, $this->tableName, 'dateCreated', false);

        // Add index for siteId - commonly filtered by site
        $this->createIndex(null, $this->tableName, 'siteId', false);

        // Add index for event - commonly filtered by event type
        $this->createIndex(null, $this->tableName, 'event', false);

        // Expand userAgent column - modern UA strings can exceed 255 chars
        $this->alterColumn($this->tableName, 'userAgent', $this->text()->null());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260109_120000_add_performance_indexes cannot be reverted.\n";

        return false;
    }
}

<?php

namespace superbig\audit\migrations;

use craft\db\Migration;

/**
 * Adds `impersonatorId`, so rows written during an impersonated session name both users.
 */
class m261022_000000_add_impersonator_id extends Migration
{
    protected string $tableName = '{{%audit_log}}';

    public function safeUp(): bool
    {
        if (!$this->db->columnExists($this->tableName, 'impersonatorId')) {
            $this->addColumn($this->tableName, 'impersonatorId', $this->integer()->null()->after('userId'));
            $this->createIndex(null, $this->tableName, 'impersonatorId', false);
            $this->addForeignKey(null, $this->tableName, 'impersonatorId', '{{%users}}', 'id', 'SET NULL', 'SET NULL');
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261022_000000_add_impersonator_id cannot be reverted.\n";

        return false;
    }
}

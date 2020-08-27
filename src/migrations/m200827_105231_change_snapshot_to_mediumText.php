<?php

namespace superbig\audit\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200827_105231_change_snapshot_to_mediumText migration.
 */
class m200827_105231_change_snapshot_to_mediumText extends Migration
{
    protected $tableName = '{{%audit_log}}';

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn($this->tableName,'snapshot', $this->mediumText()->null()->defaultValue(null));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200827_105231_change_snapshot_to_mediumText cannot be reverted.\n";
        return false;
    }
}

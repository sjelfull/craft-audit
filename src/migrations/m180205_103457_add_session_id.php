<?php

namespace superbig\audit\migrations;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * m180205_103457_add_session_id migration.
 */
class m180205_103457_add_session_id extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    protected $tableName = '{{%audit_log}}';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->modifyTable();
        $this->createIndexes();
    }

    /**
     * @return bool
     */
    protected function modifyTable()
    {
        $columnCreated = true;

        $this->addColumn($this->tableName, 'sessionId', $this->string()->defaultValue(null));

        return $columnCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex(
            $this->db->getIndexName(
                $this->tableName,
                'sessionId',
                false
            ),
            $this->tableName,
            'sessionId',
            false
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180205_103457_add_session_id cannot be reverted.\n";

        return false;
    }
}

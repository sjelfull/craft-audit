<?php

namespace superbig\audit\migrations;

use Craft;
use craft\db\Migration;

/**
 * m180402_191237_add_parent_id migration.
 */
class m180402_191237_add_parent_id extends Migration
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
        $this->addForeignKeys();
    }

    /**
     * @return bool
     */
    protected function modifyTable()
    {
        $columnCreated = true;

        $this->addColumn($this->tableName, 'parentId', $this->integer()->defaultValue(null)->after('sessionId'));

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
                'parentId',
                false
            ),
            $this->tableName,
            'parentId',
            false
        );
    }

    public function addForeignKeys()
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName($this->tableName, 'parentId'),
            $this->tableName,
            'parentId',
            $this->tableName,
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180402_191237_add_parent_id cannot be reverted.\n";

        return false;
    }
}

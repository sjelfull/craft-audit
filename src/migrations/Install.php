<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\migrations;

use Craft;
use craft\db\Migration;
use superbig\audit\Audit;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class Install extends Migration
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
        $this->driver = $this->getDb()->getDriverName();
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = $this->getDb()->getDriverName();
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema($this->tableName);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                $this->tableName,
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'siteId' => $this->integer()->notNull(),
                    'sessionId' => $this->string()->null()->defaultValue(null),
                    'parentId' => $this->integer()->null()->defaultValue(null),
                    'elementId' => $this->integer()->null()->defaultValue(null),
                    'elementType' => $this->string()->null()->defaultValue(null),
                    'userId' => $this->integer()->null()->defaultValue(null),
                    'event' => $this->string()->null()->defaultValue(null),
                    'title' => $this->string()->null()->defaultValue(null),
                    'ip' => $this->string()->null()->defaultValue(null),
                    'request' => $this->string(10)->null(),
                    'userAgent' => $this->text()->null(),
                    'location' => $this->json()->null(),
                    'snapshot' => $this->json()->null(),
                    'changedFields' => $this->json()->null(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex(null, $this->tableName, 'userId', false);
        $this->createIndex(null, $this->tableName, 'elementId', false);
        $this->createIndex(null, $this->tableName, 'sessionId', false);
        $this->createIndex(null, $this->tableName, 'parentId', false);
        $this->createIndex(null, $this->tableName, 'dateCreated', false);
        $this->createIndex(null, $this->tableName, 'siteId', false);
        $this->createIndex(null, $this->tableName, 'event', false);
        $this->createIndex(null, $this->tableName, ['dateCreated', 'event'], false);
        $this->createIndex(null, $this->tableName, ['userId', 'dateCreated'], false);
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, $this->tableName, 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, $this->tableName, 'elementId', '{{%elements}}', 'id', 'SET NULL', 'SET NULL');
        $this->addForeignKey(null, $this->tableName, 'userId', '{{%users}}', 'id', 'SET NULL', 'SET NULL');
        $this->addForeignKey(null, $this->tableName, 'parentId', $this->tableName, 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%audit_log}}');
    }
}

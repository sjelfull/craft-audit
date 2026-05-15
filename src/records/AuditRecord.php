<?php

namespace superbig\audit\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\User;

use yii\db\ActiveQueryInterface;

/**
 * @property integer      $id
 * @property integer      $siteId
 * @property integer|null $userId
 * @property integer|null $parentId
 * @property integer|null $elementId
 * @property string|null  $elementType
 * @property string|null  $title
 * @property string       $event
 * @property \DateTime    $dateCreated
 * @property \DateTime    $dateUpdated
 * @property string       $ip
 * @property string       $userAgent
 * @property array|string|null $snapshot      JSON column. Reads return an array (Craft AR auto-decodes); writes accept arrays. String is the legacy doubly-encoded form (still readable defensively via {@see \superbig\audit\models\AuditModel::createFromRecord()}).
 * @property string|null  $sessionId
 * @property array|string|null $location      JSON column. Same shape semantics as $snapshot.
 * @property array|string|null $changedFields JSON column. Same shape semantics as $snapshot.
 * @property string|null  $request           Request source: 'cp', 'site', 'console', 'yaml'
 */
class AuditRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%audit_log}}';
    }

    /**
     * Returns the log entry’s user.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * Returns the log entry's element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'elementId']);
    }

    /**
     * Returns the log entry's parent.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getParent(): ActiveQueryInterface
    {
        return $this->hasOne(AuditRecord::class, ['id' => 'parentId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(AuditRecord::class, ['parentId' => 'id']);
    }
}

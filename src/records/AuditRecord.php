<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\records;

use superbig\audit\Audit;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 *
 * @property integer      $id
 * @property integer      $siteId
 * @property integer|null $userId
 * @property integer|null $elementId
 * @property string|null  $elementType
 * @property string|null  $title
 * @property string       $event
 * @property \DateTime    $dateCreated
 * @property \DateTime    $dateUpdated
 * @property string       $ip
 * @property string       $userAgent
 * @property string|null  $snapshot
 */
class AuditRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName ()
    {
        return '{{%audit_log}}';
    }
}

<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\models;

use craft\base\ElementInterface;
use DateTime;
use superbig\audit\Audit;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditModel extends Model
{
    const EVENT_SAVED_ELEMENT   = 'saved-element';
    const EVENT_CREATED_ELEMENT = 'created-element';
    const EVENT_DELETED_ELEMENT = 'deleted-element';
    const USER_LOGGED_OUT       = 'user-logged-out';
    const USER_LOGGED_IN        = 'user-logged-in';

    const EVENT_LABELS = [
        self::EVENT_SAVED_ELEMENT   => 'Saved element',
        self::EVENT_CREATED_ELEMENT => 'Created element',
        self::EVENT_DELETED_ELEMENT => 'Deleted element',
        self::USER_LOGGED_IN        => 'Logged in',
        self::USER_LOGGED_OUT       => 'Logged out',
    ];

    // Public Properties
    // =========================================================================

    /**
     * @var integer|null
     */
    public $elementId = null;

    /**
     * @var string|null
     */
    public $elementType = null;

    /**
     * @var integer|null
     */
    public $userId = null;

    /**
     * @var integer|null
     */
    public $siteId = null;

    /**
     * @var string
     */
    public $title = '';

    /**
     * @var string
     */
    public $event = '';

    /**
     * @var string
     */
    public $ip = '';

    /**
     * @var string
     */
    public $userAgent = '';

    /**
     * @var array
     */
    public $location = [];

    /**
     * @var DateTime|null
     */
    public $dateCreated = null;

    /**
     * @var array
     */
    public $snapshot = [];

    protected $_user = null;

    protected $_element = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules ()
    {
        return [
            //['someAttribute', 'default', 'value' => 'Some Default'],
        ];
    }
}

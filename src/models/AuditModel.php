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
use craft\elements\Asset;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use DateTime;
use superbig\audit\Audit;

use Craft;
use craft\base\Model;
use superbig\audit\records\AuditRecord;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditModel extends Model
{
    const EVENT_SAVED_ELEMENT      = 'saved-element';
    const EVENT_CREATED_ELEMENT    = 'created-element';
    const EVENT_DELETED_ELEMENT    = 'deleted-element';
    const USER_LOGGED_OUT          = 'user-logged-out';
    const USER_LOGGED_IN           = 'user-logged-in';
    const EVENT_SAVED_DRAFT        = 'saved-draft';
    const EVENT_CREATED_DRAFT      = 'created-draft';
    const EVENT_DELETED_DRAFT      = 'deleted-draft';
    const EVENT_PLUGIN_INSTALLED   = 'installed-plugin';
    const EVENT_PLUGIN_UNINSTALLED = 'uninstalled-plugin';
    const EVENT_PLUGIN_DISABLED    = 'disabled-plugin';
    const EVENT_PLUGIN_ENABLED    = 'enabled-plugin';

    const EVENT_LABELS = [
        self::EVENT_SAVED_ELEMENT      => 'Saved element',
        self::EVENT_CREATED_ELEMENT    => 'Created element',
        self::EVENT_DELETED_ELEMENT    => 'Deleted element',
        self::EVENT_SAVED_DRAFT        => 'Saved draft',
        self::EVENT_CREATED_DRAFT      => 'Created draft',
        self::EVENT_DELETED_DRAFT      => 'Created draft',
        self::USER_LOGGED_IN           => 'Logged in',
        self::USER_LOGGED_OUT          => 'Logged out',

        // Plugins
        self::EVENT_PLUGIN_INSTALLED   => 'Plugin installed',
        self::EVENT_PLUGIN_UNINSTALLED => 'Plugin uninstalled',
        self::EVENT_PLUGIN_DISABLED    => 'Plugin disabled',
        self::EVENT_PLUGIN_ENABLED    => 'Plugin enabled',
    ];

    // Public Properties
    // =========================================================================

    /**
     * @var integer|null
     */
    public $id = null;

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

    /**
     * @var string
     */
    public $sessionId = null;

    protected $_user = null;

    protected $_element = null;

    public static function createFromRecord (AuditRecord $record)
    {
        $model              = new self();
        $model->id          = $record->id;
        $model->event       = $record->event;
        $model->title       = $record->title;
        $model->userId      = $record->userId;
        $model->elementId   = $record->elementId;
        $model->elementType = $record->elementType;
        $model->ip          = $record->ip;
        $model->userAgent   = $record->userAgent;
        $model->siteId      = $record->siteId;
        $model->dateCreated = $record->dateCreated;
        $model->snapshot    = unserialize($record->snapshot);
        $model->sessionId    = $record->sessionId;

        return $model;
    }

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

    public function getEventLabel ()
    {
        return self::EVENT_LABELS[ $this->event ] ?? '';
    }

    public function getElement ()
    {
        if ( !$this->elementId || !$this->elementType ) {
            return null;
        }

        if ( !$this->_element ) {
            $this->_element = Craft::$app->getElements()->getElementById($this->elementId, $this->elementType, $this->siteId);
        }

        return $this->_element;
    }

    public function getElementLabel ()
    {
        $element = $this->getElement();

        if ( !$element ) {
            return null;
        }

        return $element::displayName();
    }

    public function getElementLink ()
    {
        $element = $this->getElement();

        if ( !$element && $this->title ) {
            return $this->title;
        }

        if ( !$element ) {
            return null;
        }

        $text = $element->hasTitles() ? $this->title : 'Edit';
        $url  = $element->getCpEditUrl();

        if ( $this->elementType === Asset::class ) {
            $url = $element->getUrl();
        }

        return Template::raw('<a href="' . $url . '">' . $text . '</a>');
    }

    public function getUserLink ()
    {
        $user = $this->getUser();

        if ( !$user ) {
            return null;
        }

        $text = $user->username;

        return Template::raw('<a href="' . $user->getCpEditUrl() . '">' . $text . '</a>');
    }

    /**
     * @return \craft\elements\User|null
     */
    public function getUser ()
    {
        if ( $this->userId && !$this->_user ) {
            $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return $this->_user;
    }

    public function getAgent ()
    {
        $parser = parse_user_agent($this->userAgent);

        return $parser;
    }

    public function getSnapshotTable ()
    {
        return Template::raw('<pre>' . print_r($this->snapshot, true) . '</pre>');
    }

    public function getCpEditUrl ()
    {
        return UrlHelper::cpUrl('audit/log/' . $this->id);
    }
}

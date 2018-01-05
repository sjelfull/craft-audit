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
use craft\helpers\Template;
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

    public static function createFromRecord ($record)
    {
        $model              = new self();
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
}

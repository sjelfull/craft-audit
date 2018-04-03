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
    const EVENT_RESAVED_ELEMENTS   = 'resaved-elements';
    const EVENT_CREATED_ELEMENT    = 'created-element';
    const EVENT_DELETED_ELEMENT    = 'deleted-element';
    const EVENT_CREATED_ROUTE      = 'created-route';
    const EVENT_SAVED_ROUTE        = 'saved-route';
    const EVENT_DELETED_ROUTE      = 'deleted-route';
    const USER_LOGGED_OUT          = 'user-logged-out';
    const USER_LOGGED_IN           = 'user-logged-in';
    const EVENT_SAVED_DRAFT        = 'saved-draft';
    const EVENT_CREATED_DRAFT      = 'created-draft';
    const EVENT_DELETED_DRAFT      = 'deleted-draft';
    const EVENT_PLUGIN_INSTALLED   = 'installed-plugin';
    const EVENT_PLUGIN_UNINSTALLED = 'uninstalled-plugin';
    const EVENT_PLUGIN_DISABLED    = 'disabled-plugin';
    const EVENT_PLUGIN_ENABLED     = 'enabled-plugin';

    const FLASH_RESAVE_ID = 'auditResaveId';

    const EVENT_LABELS = [
        self::EVENT_SAVED_ELEMENT      => 'Saved element',
        self::EVENT_RESAVED_ELEMENTS   => 'Resaved elements',
        self::EVENT_CREATED_ELEMENT    => 'Created element',
        self::EVENT_DELETED_ELEMENT    => 'Deleted element',
        self::EVENT_SAVED_DRAFT        => 'Saved draft',
        self::EVENT_CREATED_DRAFT      => 'Created draft',
        self::EVENT_DELETED_DRAFT      => 'Created draft',
        self::USER_LOGGED_IN           => 'Logged in',
        self::USER_LOGGED_OUT          => 'Logged out',

        // Routes
        self::EVENT_SAVED_ROUTE      => 'Saved route',
        self::EVENT_CREATED_ROUTE    => 'Created route',
        self::EVENT_DELETED_ROUTE    => 'Deleted route',

        // Plugins
        self::EVENT_PLUGIN_INSTALLED   => 'Plugin installed',
        self::EVENT_PLUGIN_UNINSTALLED => 'Plugin uninstalled',
        self::EVENT_PLUGIN_DISABLED    => 'Plugin disabled',
        self::EVENT_PLUGIN_ENABLED     => 'Plugin enabled',
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
     * @var integer|null
     */
    public $parentId = null;

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

    protected $_user     = null;
    protected $_element  = null;
    protected $_children = null;

    /**
     * @param AuditRecord $record
     *
     * @return AuditModel
     */
    public static function createFromRecord(AuditRecord $record)
    {
        $model              = new self();
        $model->id          = $record->id;
        $model->event       = $record->event;
        $model->title       = $record->title;
        $model->userId      = $record->userId;
        $model->elementId   = $record->elementId;
        $model->parentId    = $record->parentId;
        $model->elementType = $record->elementType;
        $model->ip          = $record->ip;
        $model->userAgent   = $record->userAgent;
        $model->siteId      = $record->siteId;
        $model->dateCreated = $record->dateCreated;
        $model->snapshot    = unserialize($record->snapshot);
        $model->sessionId   = $record->sessionId;

        return $model;
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }

    /**
     * @return mixed|string
     */
    public function getEventLabel()
    {
        return self::EVENT_LABELS[ $this->event ] ?? '';
    }

    /**
     * @return ElementInterface|null
     */
    public function getElement()
    {
        if (!$this->elementId || !$this->elementType) {
            return null;
        }

        if (!$this->_element) {
            $this->_element = Craft::$app->getElements()->getElementById($this->elementId, $this->elementType, $this->siteId);
        }

        return $this->_element;
    }

    /**
     * @return array|null
     */
    public function getChildren()
    {
        if (!$this->_children) {
            $this->_children = Audit::$plugin->auditService->getEventsByAttributes(['parentId' => $this->id]);
        }

        return $this->_children;
    }

    /**
     * @return null|string
     */
    public function getElementLabel()
    {
        $element = $this->getElement();

        if (!$element) {
            return null;
        }

        return $element::displayName();
    }

    /**
     * @return null|string|\Twig_Markup
     */
    public function getElementLink()
    {
        $element = $this->getElement();

        if (!$element && $this->title) {
            return Template::raw($this->title);
        }

        if (!$element) {
            return null;
        }

        $text = $element->hasTitles() ? $this->title : 'Edit';
        $url  = $element->getCpEditUrl();

        if ($this->elementType === Asset::class) {
            $url = $element->getUrl();
        }

        return Template::raw('<a href="' . $url . '">' . $text . '</a>');
    }

    /**
     * @return null|\Twig_Markup
     */
    public function getUserLink()
    {
        $user = $this->getUser();

        if (!$user) {
            return null;
        }

        $text = $user->username;

        return Template::raw('<a href="' . $user->getCpEditUrl() . '">' . $text . '</a>');
    }

    /**
     * @return \craft\elements\User|null
     */
    public function getUser()
    {
        if ($this->userId && !$this->_user) {
            $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return $this->_user;
    }

    /**
     * @return string[]
     */
    public function getAgent()
    {
        $parser = parse_user_agent($this->userAgent);

        return $parser;
    }

    /**
     * @return mixed|null
     */
    public function getGeolocation()
    {
        if (empty($this->ip) || $this->ip === '127.0.0.1') {
            return null;
        }

        return Audit::$plugin->geo->getLocationInfoForIp($this->ip);
    }

    public function appendSnapshot($key = null, $data = null)
    {
        if (!is_array($this->snapshot)) {
            $this->snapshot = [];
        }

        $this->snapshot[ $key ] = $data;
    }

    /**
     * @return string|\Twig_Markup
     */
    public function getSnapshotTable()
    {
        return Audit::$plugin->auditService->outputObjectAsTable($this->snapshot);
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('audit/log/' . $this->id);
    }
}

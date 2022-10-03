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

use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\models\Site;
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
    const EVENT_SAVED_GLOBAL       = 'saved-global';
    const EVENT_SAVED_DRAFT        = 'saved-draft';
    const EVENT_CREATED_DRAFT      = 'created-draft';
    const EVENT_DELETED_DRAFT      = 'deleted-draft';
    const EVENT_CREATED_ROUTE      = 'created-route';
    const EVENT_SAVED_ROUTE        = 'saved-route';
    const EVENT_DELETED_ROUTE      = 'deleted-route';
    const USER_LOGGED_OUT          = 'user-logged-out';
    const USER_LOGGED_IN           = 'user-logged-in';
    const EVENT_PLUGIN_INSTALLED   = 'installed-plugin';
    const EVENT_PLUGIN_UNINSTALLED = 'uninstalled-plugin';
    const EVENT_PLUGIN_DISABLED    = 'disabled-plugin';
    const EVENT_PLUGIN_ENABLED     = 'enabled-plugin';
    const FLASH_RESAVE_ID          = 'auditResaveId';

    private static $_users;

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

    /**
     * @var User|null
     */
    protected $_user = null;

    /**
     * @var Element|null
     */
    protected $_element = null;

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
        $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);
        $model->sessionId   = $record->sessionId;

        $snapshot = $record->snapshot;

        try {
            if (StringHelper::isBase64($snapshot)) {
                $model->snapshot = unserialize(base64_decode($snapshot));
            }
            else {
                $model->snapshot = unserialize($snapshot);
            }
        } catch (\Exception $e) {
            $error = Craft::t('audit', 'Failed to unserialize snapshot of log entry #{id}: {message}', [
                'id' => $model->id,
                'message' => $e->getMessage(),
            ]);
            Craft::warning($error, 'audit');
        }

        return $model;
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return mixed|string
     */
    public function getEventLabel()
    {
        return Craft::t('audit', $this->event);
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
     * @return Site|null
     */
    public function getSite()
    {
        return Craft::$app->getSites()->getSiteById($this->siteId);
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
        if ($label = $this->getSnapshotValue('elementTypeLabel')) {
            return $label;
        }

        $element = $this->getElement();

        if (!$element) {
            return null;
        }

        return $element::displayName();
    }

    /**
     * @return null|string|\Twig\Markup
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

        $text = $this->title ?? 'Edit';
        $url  = $element->getCpEditUrl();

        if ($this->elementType === Asset::class) {
            $url = $element->getUrl();
        }

        return Template::raw('<a href="' . $url . '">' . $text . '</a>');
    }

    /**
     * @return null|\Twig\Markup
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
        if ($this->userId && !isset(static::$_users[ $this->userId ])) {
            static::$_users[ $this->userId ] = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return static::$_users[ $this->userId ] ?? null;
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
     * @return string|\Twig\Markup
     */
    public function getSnapshotTable()
    {
        return Audit::$plugin->auditService->outputObjectAsTable($this->snapshot);
    }

    public function getSnapshotValue($key)
    {
        return ArrayHelper::getValue($this->snapshot, $key);
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('audit/log/' . $this->id);
    }
}

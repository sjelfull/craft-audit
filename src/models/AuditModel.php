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

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\models\Site;

use DateTime;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use Throwable;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditModel extends Model
{
    /**
     * String constants for audit event types.
     *
     * @deprecated since 3.0.0. Use {@see \superbig\audit\enums\AuditEvent} instead.
     *             The constants remain as string backing values for backward
     *             compatibility with third-party consumers and existing DB rows.
     */
    public const EVENT_SAVED_ELEMENT = 'saved-element';
    public const EVENT_RESAVED_ELEMENTS = 'resaved-elements';
    public const EVENT_CREATED_ELEMENT = 'created-element';
    public const EVENT_DELETED_ELEMENT = 'deleted-element';

    // Entry Events
    public const EVENT_ENTRY_CREATED = 'entry-created';
    public const EVENT_ENTRY_SAVED = 'entry-saved';
    public const EVENT_ENTRY_DELETED = 'entry-deleted';

    public const EVENT_SAVED_GLOBAL = 'saved-global';
    public const EVENT_SAVED_DRAFT = 'saved-draft';
    public const EVENT_CREATED_DRAFT = 'created-draft';
    public const EVENT_DELETED_DRAFT = 'deleted-draft';
    public const EVENT_CREATED_ROUTE = 'created-route';
    public const EVENT_SAVED_ROUTE = 'saved-route';
    public const EVENT_DELETED_ROUTE = 'deleted-route';
    public const USER_LOGGED_OUT = 'user-logged-out';
    public const USER_LOGGED_IN = 'user-logged-in';
    public const EVENT_PLUGIN_INSTALLED = 'installed-plugin';
    public const EVENT_PLUGIN_UNINSTALLED = 'uninstalled-plugin';
    public const EVENT_PLUGIN_DISABLED = 'disabled-plugin';
    public const EVENT_PLUGIN_ENABLED = 'enabled-plugin';

    // User Security Events
    public const EVENT_USER_ACTIVATED = 'user-activated';
    public const EVENT_USER_DEACTIVATED = 'user-deactivated';
    public const EVENT_USER_SUSPENDED = 'user-suspended';
    public const EVENT_USER_UNSUSPENDED = 'user-unsuspended';
    public const EVENT_USER_LOCKED = 'user-locked';
    public const EVENT_USER_UNLOCKED = 'user-unlocked';
    public const EVENT_USER_GROUPS_ASSIGNED = 'user-groups-assigned';

    // Permission Events
    public const EVENT_USER_PERMISSIONS_SAVED = 'user-permissions-saved';
    public const EVENT_GROUP_PERMISSIONS_SAVED = 'group-permissions-saved';
    public const EVENT_USER_GROUP_CREATED = 'user-group-created';
    public const EVENT_USER_GROUP_SAVED = 'user-group-saved';
    public const EVENT_USER_GROUP_DELETED = 'user-group-deleted';

    // Schema Events
    public const EVENT_FIELD_CREATED = 'field-created';
    public const EVENT_FIELD_SAVED = 'field-saved';
    public const EVENT_FIELD_DELETED = 'field-deleted';
    public const EVENT_SECTION_CREATED = 'section-created';
    public const EVENT_SECTION_SAVED = 'section-saved';
    public const EVENT_SECTION_DELETED = 'section-deleted';
    public const EVENT_ENTRY_TYPE_CREATED = 'entry-type-created';
    public const EVENT_ENTRY_TYPE_SAVED = 'entry-type-saved';
    public const EVENT_ENTRY_TYPE_DELETED = 'entry-type-deleted';

    // Settings Events
    public const EVENT_SYSTEM_SETTINGS_CHANGED = 'system-settings-changed';
    public const EVENT_EMAIL_SETTINGS_CHANGED = 'email-settings-changed';

    // Database Events
    public const EVENT_BACKUP_CREATED = 'backup-created';
    public const EVENT_BACKUP_RESTORED = 'backup-restored';

    public const FLASH_RESAVE_ID = 'auditResaveId';

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
     * Typed enum representation of {@see $event}, if the string value matches
     * a known {@see AuditEvent} case. Null for legacy / unknown events.
     *
     * Populated by {@see self::createFromRecord()}. The string {@see $event}
     * property remains the canonical value for backward compatibility.
     */
    public ?AuditEvent $eventEnum = null;

    /**
     * @var string|null
     */
    public ?string $sessionId = null;

    /** @var string|null Request source: 'cp', 'site', 'console', 'yaml' */
    public ?string $request = null;

    /** @var array Field-level diff data */
    public array $changedFields = [];

    /**
     * @var User|null
     */
    protected $_user = null;

    /**
     * @var ElementInterface|null
     */
    protected ?ElementInterface $_element = null;

    protected $_children = null;

    /**
     * @param AuditRecord $record
     *
     * @return AuditModel
     */
    public static function createFromRecord(AuditRecord $record)
    {
        $model = new self();
        $model->id = $record->id;
        $model->event = $record->event;
        $model->eventEnum = AuditEvent::tryFromString($record->event);
        $model->title = $record->title;
        $model->userId = $record->userId;
        $model->elementId = $record->elementId;
        $model->parentId = $record->parentId;
        $model->elementType = $record->elementType;
        $model->ip = $record->ip;
        $model->userAgent = $record->userAgent;
        $model->siteId = $record->siteId;
        $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);
        $model->sessionId = $record->sessionId;

        $snapshot = $record->snapshot;

        try {
            $model->snapshot = $snapshot ? Json::decode($snapshot, true) : [];

            if (!is_array($model->snapshot)) {
                $model->snapshot = [];
            }
        } catch (Throwable $e) {
            $error = Craft::t('audit', 'Failed to decode JSON snapshot of log entry #{id}: {message}', [
                'id' => $model->id,
                'message' => $e->getMessage(),
            ]);
            Craft::warning($error, 'audit');
            $model->snapshot = [];
        }

        $model->request = $record->request;
        try {
            $model->changedFields = $record->changedFields
                ? (Json::decode($record->changedFields, true) ?: [])
                : [];
            if (!is_array($model->changedFields)) {
                $model->changedFields = [];
            }
        } catch (Throwable) {
            $model->changedFields = [];
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

        // Entry events: show entry title linked, with section name fallback
        if ($this->isEntryEvent()) {
            $sectionName = $this->getSnapshotValue('sectionName');
            $title = $this->title ?: $sectionName;

            if ($element) {
                $url = $element->getCpEditUrl();
                return Template::raw('<a href="' . $url . '">' . $title . '</a>');
            }

            return $title;
        }

        // Section events: link to section settings
        if ($this->isSectionEvent()) {
            $sectionId = $this->getSnapshotValue('sectionId');
            $sectionName = $this->getSnapshotValue('sectionName');

            if ($sectionId && $this->event !== self::EVENT_SECTION_DELETED) {
                $url = UrlHelper::cpUrl('settings/sections/' . $sectionId);
                return Template::raw('<a href="' . $url . '">' . $sectionName . '</a>');
            }

            return $sectionName;
        }

        // Settings events: link to settings page
        if ($this->isSettingsEvent()) {
            $settingsType = $this->getSnapshotValue('settingsType');
            $settingsPath = $settingsType === 'email' ? 'settings/email' : 'settings/general';
            $url = UrlHelper::cpUrl($settingsPath);

            return Template::raw('<a href="' . $url . '">' . $this->title . '</a>');
        }

        if (!$element && $this->title) {
            return Template::raw($this->title);
        }

        if (!$element) {
            return null;
        }

        $text = $this->title ?: 'Edit';
        $url = $element->getCpEditUrl();

        if ($this->elementType === Asset::class) {
            $url = $element->getUrl();
        }

        return Template::raw('<a href="' . $url . '">' . $text . '</a>');
    }

    /**
     * Check if this is an entry event
     */
    public function isEntryEvent(): bool
    {
        return in_array($this->event, [
            self::EVENT_ENTRY_CREATED,
            self::EVENT_ENTRY_SAVED,
            self::EVENT_ENTRY_DELETED,
        ]);
    }

    /**
     * Check if this is a section event
     */
    public function isSectionEvent(): bool
    {
        return in_array($this->event, [
            self::EVENT_SECTION_CREATED,
            self::EVENT_SECTION_SAVED,
            self::EVENT_SECTION_DELETED,
        ]);
    }

    /**
     * Check if this is a settings event
     */
    public function isSettingsEvent(): bool
    {
        return in_array($this->event, [
            self::EVENT_SYSTEM_SETTINGS_CHANGED,
            self::EVENT_EMAIL_SETTINGS_CHANGED,
        ]);
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

        $text = $user->fullName ?: $user->username;

        return Template::raw('<a href="' . $user->getCpEditUrl() . '">' . $text . '</a>');
    }

    /**
     * @return \craft\elements\User|null
     */
    public function getUser()
    {
        if ($this->userId && !isset(self::$_users[$this->userId])) {
            self::$_users[$this->userId] = Craft::$app->getUsers()->getUserById($this->userId);
        }

        return self::$_users[$this->userId] ?? null;
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

    public function getSnapshotJson()
    {
        return Json::encode($this->snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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

    /**
     * Get the created date formatted for the current user's timezone
     */
    public function getFormattedDate(string $format = 'short'): string
    {
        if (!$this->dateCreated) {
            return '';
        }

        return Craft::$app->getFormatter()->asDatetime($this->dateCreated, $format);
    }
}

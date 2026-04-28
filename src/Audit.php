<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit;

use Craft;

use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\db\Connection as DbConnection;
use craft\events\BackupEvent;
use craft\events\ConfigEvent;
use craft\events\ElementEvent;
use craft\events\EntryTypeEvent;
use craft\events\FieldEvent;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RestoreEvent;
use craft\events\RouteEvent;
use craft\events\SectionEvent;
use craft\events\UserAssignGroupEvent;
use craft\events\UserGroupEvent;
use craft\events\UserGroupPermissionsEvent;
use craft\events\UserPermissionsEvent;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\queue\Queue;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Fields;
use craft\services\Plugins;
use craft\services\ProjectConfig;
use craft\services\Routes;
use craft\services\UserGroups;
use craft\services\UserPermissions;
use craft\services\Users;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\RegisterFieldHandlersEvent;
use superbig\audit\fieldHandlers\handlers\BooleanHandler;
use superbig\audit\fieldHandlers\handlers\ColorHandler;
use superbig\audit\fieldHandlers\handlers\DateHandler;
use superbig\audit\fieldHandlers\handlers\MatrixHandler;
use superbig\audit\fieldHandlers\handlers\MoneyHandler;
use superbig\audit\fieldHandlers\handlers\MultiOptionHandler;
use superbig\audit\fieldHandlers\handlers\NeoHandler;
use superbig\audit\fieldHandlers\handlers\OptionHandler;
use superbig\audit\fieldHandlers\handlers\PlainHandler;
use superbig\audit\fieldHandlers\handlers\RelationHandler;
use superbig\audit\fieldHandlers\handlers\RichTextHandler;
use superbig\audit\fieldHandlers\handlers\SeoHandler;
use superbig\audit\fieldHandlers\handlers\SuperTableHandler;
use superbig\audit\fieldHandlers\handlers\TableHandler;
use superbig\audit\fieldHandlers\handlers\VizyHandler;
use superbig\audit\models\Settings;
use superbig\audit\services\Audit_GeoService;
use superbig\audit\services\AuditRecorder;
use superbig\audit\services\AuditService;
use superbig\audit\services\DiffRenderer;
use superbig\audit\services\FieldDiffService;
use superbig\audit\services\FieldHandlerRegistry;
use superbig\audit\services\handlers\BackupHandler;
use superbig\audit\services\handlers\ElementHandler;
use superbig\audit\services\handlers\PluginHandler;
use superbig\audit\services\handlers\RouteHandler;
use superbig\audit\services\handlers\SchemaHandler;
use superbig\audit\services\handlers\SettingsHandler;
use superbig\audit\services\handlers\UserGroupHandler;
use superbig\audit\services\handlers\UserHandler;
use superbig\audit\services\ProjectConfigTracker;

use superbig\audit\variables\AuditVariable;
use yii\base\Event;
use yii\queue\ExecEvent;
use yii\web\User;
use yii\web\UserEvent;

/**
 * Class Audit
 *
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 *
 * @property  AuditService          $auditService
 * @property  Audit_GeoService      $geo
 * @property  FieldHandlerRegistry  $fieldHandlerRegistry
 * @property  FieldDiffService      $fieldDiffService
 * @property  AuditRecorder         $auditRecorder
 * @property  ProjectConfigTracker  $projectConfigTracker
 * @property  DiffRenderer          $diffRenderer
 * @property  ElementHandler        $elementHandler
 * @property  UserHandler           $userHandler
 * @property  UserGroupHandler      $userGroupHandler
 * @property  SchemaHandler         $schemaHandler
 * @property  RouteHandler          $routeHandler
 * @property  BackupHandler         $backupHandler
 * @property  PluginHandler         $pluginHandler
 * @property  SettingsHandler       $settingsHandler
 * @method  Settings getSettings()
 */
class Audit extends Plugin
{
    public const PERMISSION_VIEW_LOGS = 'audit-view-logs';
    public const PERMISSION_CLEAR_LOGS = 'audit-clear-logs';

    public static Audit $plugin;
    public static $craft31 = false;
    public static $craft32 = false;
    public static $craft33 = false;
    public static $craft34 = false;
    public static $craft37 = false;
    public string $schemaVersion = '1.0.3';

    /**
     * Determine whether our table schema exists or not; this is needed because
     * migrations such as the install migration and base_install migration may
     * not have been run by the time our init() method has been called
     *
     * @return bool
     */
    protected function tableSchemaExists(): bool
    {
        return (Craft::$app->db->schema->getTableSchema('{{%audit_log}}') !== null);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'superbig\audit\console\controllers';
        }

        // Versions
        $currentVersion = Craft::$app->getVersion();
        self::$craft31 = version_compare($currentVersion, '3.1', '>=');
        self::$craft32 = version_compare($currentVersion, '3.2', '>=');
        self::$craft33 = version_compare($currentVersion, '3.3', '>=');
        self::$craft34 = version_compare($currentVersion, '3.4', '>=');
        self::$craft37 = version_compare($currentVersion, '3.7', '>=');

        $this->setComponents([
            'auditService' => AuditService::class,
            'geo' => Audit_GeoService::class,
            'fieldHandlerRegistry' => FieldHandlerRegistry::class,
            'fieldDiffService' => FieldDiffService::class,
            'auditRecorder' => AuditRecorder::class,
            'projectConfigTracker' => ProjectConfigTracker::class,
            'diffRenderer' => DiffRenderer::class,
            'elementHandler' => ElementHandler::class,
            'userHandler' => UserHandler::class,
            'userGroupHandler' => UserGroupHandler::class,
            'schemaHandler' => SchemaHandler::class,
            'routeHandler' => RouteHandler::class,
            'backupHandler' => BackupHandler::class,
            'pluginHandler' => PluginHandler::class,
            'settingsHandler' => SettingsHandler::class,
        ]);

        $this->registerFieldHandlers();
        $this->projectConfigTracker->register();

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    $settings = $this->getSettings();

                    if (empty($settings->updateAuthKey)) {
                        $settings->updateAuthKey = StringHelper::randomString(16);

                        Craft::$app->getPlugins()->savePluginSettings($this, $settings->toArray());
                    }
                }
            }
        );

        /**
         * Install our event listeners. We do it only after we receive the event
         * EVENT_AFTER_LOAD_PLUGINS so that any pending db migrations can be run
         * before our event listeners kick in
         */
        // Handler: EVENT_AFTER_LOAD_PLUGINS
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest() && $this->tableSchemaExists()) {
            if ($this->getSettings()->enabled) {
                Event::on(
                    Plugins::class,
                    Plugins::EVENT_AFTER_LOAD_PLUGINS,
                    function() {
                        $this->initLogEvents();
                    });
            }

            if ($this->getSettings()->pruneRecordsOnAdminRequests && ($request->getIsCpRequest() && !$request->getIsActionRequest() && Craft::$app->getUser()->getIsAdmin())) {
                self::$plugin->auditService->pruneLogs();
            }
        }

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('audit', AuditVariable::class);
            }
        );

        $this->setupRouteEvents();
        $this->setupPermissions();
    }

    /**
     * @return void
     */
    public function setupQueueEvents(): void
    {
        Event::on(
            Queue::class,
            Queue::EVENT_BEFORE_EXEC,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->elementHandler->onBeforeResave($event->job);
                }
            }
        );

        Event::on(
            Queue::class,
            Queue::EVENT_AFTER_EXEC,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->elementHandler->onResaveEnd($event->job);
                }
            }
        );

        Event::on(
            Queue::class,
            Queue::EVENT_AFTER_ERROR,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->elementHandler->onResaveEnd($event->job);
                }
            }
        );
    }

    public function setupRouteEvents(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['audit/update-database'] = 'audit/geo/update-database';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['audit'] = 'audit/default/index';
                $event->rules['audit/log/<id:\d+>'] = 'audit/default/details';
                $event->rules['audit/prune-logs'] = 'audit/default/prune-logs';
            }
        );
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $validDb = $this->geo->checkValidDb();

        return Craft::$app->view->renderTemplate(
            'audit/settings',
            [
                'settings' => $this->getSettings(),
                'validDb' => $validDb,
            ]
        );
    }

    protected function initLogEvents()
    {
        $events = [
            [
                'class' => User::class,
                'event' => User::EVENT_AFTER_LOGIN,
                'handler' => function(UserEvent $event) {
                    $this->userHandler->onLogin();
                },
            ],
            [
                'class' => User::class,
                'event' => User::EVENT_BEFORE_LOGOUT,
                'handler' => function(UserEvent $event) {
                    $this->userHandler->onBeforeLogout();
                },
            ],
            [
                'class' => Elements::class,
                'event' => Elements::EVENT_AFTER_SAVE_ELEMENT,
                'handler' => function(ElementEvent $event) {
                    $isNew = $event->sender->firstSave ?? $event->isNew;
                    $this->elementHandler->onSaveElement($event->element, $isNew);
                },
            ],
            [
                'class' => Elements::class,
                'event' => Elements::EVENT_AFTER_DELETE_ELEMENT,
                'handler' => function(ElementEvent $event) {
                    $this->elementHandler->onDeleteElement($event->element);
                },
            ],
            // Routes
            [
                'class' => Routes::class,
                'event' => Routes::EVENT_AFTER_SAVE_ROUTE,
                'handler' => function(RouteEvent $event) {
                    $this->routeHandler->onSaveRoute($event);
                },
            ],
            [
                'class' => Routes::class,
                'event' => Routes::EVENT_BEFORE_DELETE_ROUTE,
                'handler' => function(RouteEvent $event) {
                    $this->routeHandler->onDeleteRoute($event);
                },
            ],
            [
                'class' => Plugins::class,
                'event' => Plugins::EVENT_AFTER_UNINSTALL_PLUGIN,
                'handler' => function(PluginEvent $event) {
                    $this->pluginHandler->onPluginEvent(AuditEvent::PluginUninstalled->value, $event->plugin);
                },
            ],
            [
                'class' => Plugins::class,
                'event' => Plugins::EVENT_AFTER_DISABLE_PLUGIN,
                'handler' => function(PluginEvent $event) {
                    $this->pluginHandler->onPluginEvent(AuditEvent::PluginDisabled->value, $event->plugin);
                },
            ],
            [
                'class' => Plugins::class,
                'event' => Plugins::EVENT_AFTER_ENABLE_PLUGIN,
                'handler' => function(PluginEvent $event) {
                    $this->pluginHandler->onPluginEvent(AuditEvent::PluginEnabled->value, $event->plugin);
                },
            ],
            // User Security Events
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_ACTIVATE_USER,
                'handler' => function(\craft\events\UserEvent $event) {
                    $this->userHandler->onUserActivated($event);
                },
            ],
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_DEACTIVATE_USER,
                'handler' => function(\craft\events\UserEvent $event) {
                    $this->userHandler->onUserDeactivated($event);
                },
            ],
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_SUSPEND_USER,
                'handler' => function(\craft\events\UserEvent $event) {
                    $this->userHandler->onUserSuspended($event);
                },
            ],
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_UNSUSPEND_USER,
                'handler' => function(\craft\events\UserEvent $event) {
                    $this->userHandler->onUserUnsuspended($event);
                },
            ],
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_LOCK_USER,
                'handler' => function(\craft\events\UserEvent $event) {
                    $this->userHandler->onUserLocked($event);
                },
            ],
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_UNLOCK_USER,
                'handler' => function(\craft\events\UserEvent $event) {
                    $this->userHandler->onUserUnlocked($event);
                },
            ],
            [
                'class' => Users::class,
                'event' => Users::EVENT_AFTER_ASSIGN_USER_TO_GROUPS,
                'handler' => function(UserAssignGroupEvent $event) {
                    $this->userGroupHandler->onUserGroupsAssigned($event);
                },
            ],
            // Permission Events
            [
                'class' => UserPermissions::class,
                'event' => UserPermissions::EVENT_AFTER_SAVE_USER_PERMISSIONS,
                'handler' => function(UserPermissionsEvent $event) {
                    $this->userGroupHandler->onUserPermissionsSaved($event);
                },
            ],
            [
                'class' => UserPermissions::class,
                'event' => UserPermissions::EVENT_AFTER_SAVE_GROUP_PERMISSIONS,
                'handler' => function(UserGroupPermissionsEvent $event) {
                    $this->userGroupHandler->onGroupPermissionsSaved($event);
                },
            ],
            [
                'class' => UserGroups::class,
                'event' => UserGroups::EVENT_AFTER_SAVE_USER_GROUP,
                'handler' => function(UserGroupEvent $event) {
                    $this->userGroupHandler->onUserGroupSaved($event);
                },
            ],
            [
                'class' => UserGroups::class,
                'event' => UserGroups::EVENT_AFTER_DELETE_USER_GROUP,
                'handler' => function(UserGroupEvent $event) {
                    $this->userGroupHandler->onUserGroupDeleted($event);
                },
            ],
            // Schema Events
            [
                'class' => Fields::class,
                'event' => Fields::EVENT_AFTER_SAVE_FIELD,
                'handler' => function(FieldEvent $event) {
                    $this->schemaHandler->onFieldSaved($event);
                },
            ],
            [
                'class' => Fields::class,
                'event' => Fields::EVENT_AFTER_DELETE_FIELD,
                'handler' => function(FieldEvent $event) {
                    $this->schemaHandler->onFieldDeleted($event);
                },
            ],
            [
                'class' => Entries::class,
                'event' => Entries::EVENT_AFTER_SAVE_SECTION,
                'handler' => function(SectionEvent $event) {
                    $this->schemaHandler->onSectionSaved($event);
                },
            ],
            [
                'class' => Entries::class,
                'event' => Entries::EVENT_AFTER_DELETE_SECTION,
                'handler' => function(SectionEvent $event) {
                    $this->schemaHandler->onSectionDeleted($event);
                },
            ],
            [
                'class' => Entries::class,
                'event' => Entries::EVENT_AFTER_SAVE_ENTRY_TYPE,
                'handler' => function(EntryTypeEvent $event) {
                    $this->schemaHandler->onEntryTypeSaved($event);
                },
            ],
            [
                'class' => Entries::class,
                'event' => Entries::EVENT_AFTER_DELETE_ENTRY_TYPE,
                'handler' => function(EntryTypeEvent $event) {
                    $this->schemaHandler->onEntryTypeDeleted($event);
                },
            ],
            // Database Events
            [
                'class' => DbConnection::class,
                'event' => DbConnection::EVENT_AFTER_CREATE_BACKUP,
                'handler' => function(BackupEvent $event) {
                    $this->backupHandler->onBackupCreated($event);
                },
            ],
            [
                'class' => DbConnection::class,
                'event' => DbConnection::EVENT_AFTER_RESTORE_BACKUP,
                'handler' => function(RestoreEvent $event) {
                    $this->backupHandler->onBackupRestored($event);
                },
            ],
        ];

        foreach ($events as $event) {
            Event::on(
                $event['class'],
                $event['event'],
                $event['handler']
            );
        }

        // Settings Events (ProjectConfig)
        Event::on(
            ProjectConfig::class,
            ProjectConfig::EVENT_UPDATE_ITEM,
            function(ConfigEvent $event) {
                if (str_starts_with($event->path, 'system')) {
                    $this->settingsHandler->onSettingsChanged($event, 'system');
                } elseif (str_starts_with($event->path, 'email')) {
                    $this->settingsHandler->onSettingsChanged($event, 'email');
                }
            }
        );

        $this->setupQueueEvents();
    }

    /**
     * Register the built-in field handlers with the FieldHandlerRegistry.
     * Third-party plugin handlers gate themselves via class_exists() inside
     * supportedFields(), so they register safely even when the plugin is missing.
     */
    protected function registerFieldHandlers(): void
    {
        Event::on(
            FieldHandlerRegistry::class,
            FieldHandlerRegistry::EVENT_REGISTER_HANDLERS,
            static function(RegisterFieldHandlersEvent $event) {
                $event->handlers = array_merge($event->handlers, [
                    PlainHandler::class,
                    ColorHandler::class,
                    RichTextHandler::class,
                    DateHandler::class,
                    BooleanHandler::class,
                    OptionHandler::class,
                    MultiOptionHandler::class,
                    RelationHandler::class,
                    MoneyHandler::class,
                    TableHandler::class,
                    MatrixHandler::class,
                    NeoHandler::class,
                    SuperTableHandler::class,
                    VizyHandler::class,
                    SeoHandler::class,
                ]);
            }
        );
    }

    public function setupPermissions()
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('audit', 'Audit'),
                    'permissions' => [
                        self::PERMISSION_VIEW_LOGS => [
                            'label' => 'View audit logs',
                        ],
                        self::PERMISSION_CLEAR_LOGS => [
                            'label' => 'Clear old logs',
                        ],
                    ],
                ];
            }
        );
    }
}

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

use craft\events\ElementEvent;

use craft\events\RegisterUserPermissionsEvent;
use craft\events\RouteEvent;
use craft\helpers\StringHelper;
use craft\queue\jobs\ResaveElements;
use craft\queue\Queue;
use craft\services\Routes;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use superbig\audit\models\AuditModel;
use superbig\audit\services\Audit_GeoService;
use superbig\audit\services\AuditService;
use superbig\audit\models\Settings;

use Craft;
use craft\console\Application as ConsoleApplication;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

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
 * @property  AuditService     $auditService
 * @property  Audit_GeoService $geo
 * @method  Settings getSettings()
 */
class Audit extends Plugin
{
    const PERMISSION_VIEW_LOGS  = 'audit-view-logs';
    const PERMISSION_CLEAR_LOGS = 'audit-clear-logs';

    /**
     * @var Audit
     */
    public static $plugin;

    public static $craft31 = false;
    public static $craft32 = false;
    public static $craft33 = false;
    public static $craft34 = false;

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

    // Public Methods
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.2';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'superbig\audit\console\controllers';
        }

        // Versions
        $currentVersion = Craft::$app->getVersion();
        self::$craft31  = version_compare($currentVersion, '3.1', '>=');
        self::$craft32  = version_compare($currentVersion, '3.2', '>=');
        self::$craft33  = version_compare($currentVersion, '3.3', '>=');
        self::$craft34  = version_compare($currentVersion, '3.4', '>=');

        $this->setComponents([
            'auditService' => AuditService::class,
            'geo'          => Audit_GeoService::class,
        ]);

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
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['audit/update-database'] = 'audit/geo/update-database';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('audit', AuditVariable::class);
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['audit']              = 'audit/default/index';
                $event->rules['audit/log/<id:\d+>'] = 'audit/default/details';
                $event->rules['audit/prune-logs']   = 'audit/default/prune-logs';
            }
        );

        Craft::info(
            Craft::t(
                'audit',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

        $this->registerPermissions();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        $validDb = $this->geo->checkValidDb();

        return Craft::$app->view->renderTemplate(
            'audit/settings',
            [
                'settings' => $this->getSettings(),
                'validDb'  => $validDb,
            ]
        );
    }

    protected function initLogEvents()
    {
        $events = [
            [
                'class'   => User::class,
                'event'   => User::EVENT_AFTER_LOGIN,
                'handler' => function(UserEvent $event) {
                    $this->auditService->onLogin();
                },
            ],
            [
                'class'   => User::class,
                'event'   => User::EVENT_BEFORE_LOGOUT,
                'handler' => function(UserEvent $event) {
                    $this->auditService->onBeforeLogout();
                },
            ],
            [
                'class'   => Elements::class,
                'event'   => Elements::EVENT_AFTER_SAVE_ELEMENT,
                'handler' => function(ElementEvent $event) {
                    $this->auditService->onSaveElement($event->element, $event->isNew);
                },
            ],
            [
                'class'   => Elements::class,
                'event'   => Elements::EVENT_AFTER_DELETE_ELEMENT,
                'handler' => function(ElementEvent $event) {
                    $this->auditService->onDeleteElement($event->element);
                },
            ],
            // Routes
            [
                'class'   => Routes::class,
                'event'   => Routes::EVENT_AFTER_SAVE_ROUTE,
                'handler' => function(RouteEvent $event) {
                    $this->auditService->onSaveRoute($event);
                },
            ],
            [
                'class'   => Routes::class,
                'event'   => Routes::EVENT_BEFORE_DELETE_ROUTE,
                'handler' => function(RouteEvent $event) {
                    $this->auditService->onDeleteRoute($event);
                },
            ],
            [
                'class'   => Plugins::class,
                'event'   => Plugins::EVENT_AFTER_UNINSTALL_PLUGIN,
                'handler' => function(PluginEvent $event) {
                    $this->auditService->onPluginEvent(AuditModel::EVENT_PLUGIN_UNINSTALLED, $event->plugin);
                },
            ],
            [
                'class'   => Plugins::class,
                'event'   => Plugins::EVENT_AFTER_DISABLE_PLUGIN,
                'handler' => function(PluginEvent $event) {
                    $this->auditService->onPluginEvent(AuditModel::EVENT_PLUGIN_DISABLED, $event->plugin);
                },
            ],
            [
                'class'   => Plugins::class,
                'event'   => Plugins::EVENT_AFTER_ENABLE_PLUGIN,
                'handler' => function(PluginEvent $event) {
                    $this->auditService->onPluginEvent(AuditModel::EVENT_PLUGIN_ENABLED, $event->plugin);
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

        Event::on(
            Queue::class,
            Queue::EVENT_BEFORE_EXEC,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->auditService->onBeforeResave($event->job);
                }
            }
        );

        Event::on(
            Queue::class,
            Queue::EVENT_AFTER_EXEC,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->auditService->onResaveEnd($event->job);
                }
            }
        );

        Event::on(
            Queue::class,
            Queue::EVENT_AFTER_ERROR,
            function(ExecEvent $event) {
                if ($event->job instanceof ResaveElements) {
                    $this->auditService->onResaveEnd($event->job);
                }
            }
        );
    }

    public function registerPermissions()
    {
        $permissions = [
            'Audit' => [
                self::PERMISSION_VIEW_LOGS  => [
                    'label' => 'View audit logs',
                ],
                self::PERMISSION_CLEAR_LOGS => [
                    'label' => 'Clear old logs',
                ],
            ],
        ];
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) use ($permissions) {
                $event->permissions = array_merge($event->permissions, $permissions);
            }
        );
    }
}

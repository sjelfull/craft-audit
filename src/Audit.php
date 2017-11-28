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

use superbig\audit\services\AuditService as AuditServiceService;
use superbig\audit\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;
use yii\web\User;
use yii\web\UserEvent;

/**
 * Class Audit
 *
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 *
 * @property  AuditServiceService $auditService
 * @method  Settings getSettings()
 */
class Audit extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Audit
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init ()
    {
        parent::init();
        self::$plugin = $this;

        $this->initLogEvents();

        /*Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 'audit/default';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cpActionTrigger1'] = 'audit/default/do-something';
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ( $event->plugin === $this ) {
                }
            }
        );*/

        Craft::info(
            Craft::t(
                'audit',
                '{name} plugin loaded',
                [ 'name' => $this->name ]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel ()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml (): string
    {
        return Craft::$app->view->renderTemplate(
            'audit/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    protected function initLogEvents ()
    {
        // Users
        Event::on(
            User::class,
            User::EVENT_AFTER_LOGIN,
            function (UserEvent $event) {
                $this->auditService->onLogin();
            }
        );


        Event::on(
            User::class,
            User::EVENT_BEFORE_LOGOUT,
            function (UserEvent $event) {
                $this->auditService->onBeforeLogout();
            }
        );

        Event::on(
            User::class,
            User::EVENT_AFTER_LOGOUT,
            function (UserEvent $event) {
                $this->auditService->onLogout();
            }
        );

        // Elements
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $this->auditService->onSaveElement($event->element, $event->isNew);
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function (ElementEvent $event) {
                $this->auditService->onDeleteElement($event->element, $event->isNew);
            }
        );


    }
}

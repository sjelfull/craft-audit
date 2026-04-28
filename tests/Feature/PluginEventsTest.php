<?php

use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

/**
 * These tests stay handler-direct rather than exercising
 * \Craft::$app->plugins->enablePlugin()/disablePlugin() because:
 *
 *   1. The test fixture only installs the Audit plugin itself. Disabling
 *      Audit would tear down the very event listeners we are trying to
 *      verify, so we cannot use it to test the wiring.
 *
 *   2. Bringing a fixture plugin (e.g., a tiny no-op craftcms package)
 *      into the test harness is out of scope for P2.9 — it would
 *      require composer changes and a stub plugin class.
 *
 * The wiring between Plugins::EVENT_AFTER_ENABLE_PLUGIN /
 * EVENT_AFTER_DISABLE_PLUGIN / EVENT_AFTER_UNINSTALL_PLUGIN and
 * PluginHandler::onPluginEvent is registered in Audit::initLogEvents()
 * and is verified manually against installations.
 */

it('logs audit event when plugin is enabled', function () {
    $mockPlugin = new class('test-plugin', \Craft::$app) extends \craft\base\Plugin {
        public ?string $name = 'Test Plugin';
        public string $version = '1.0.0';
    };

    Audit::$plugin->pluginHandler->onPluginEvent(
        AuditEvent::PluginEnabled->value,
        $mockPlugin
    );

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::PluginEnabled->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Test Plugin');
});

it('logs audit event when plugin is disabled', function () {
    $mockPlugin = new class('test-plugin', \Craft::$app) extends \craft\base\Plugin {
        public ?string $name = 'Test Plugin';
        public string $version = '1.0.0';
    };

    Audit::$plugin->pluginHandler->onPluginEvent(
        AuditEvent::PluginDisabled->value,
        $mockPlugin
    );

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::PluginDisabled->value])
        ->one();

    expect($record)->not->toBeNull();
});

it('does not log plugin events when disabled', function () {
    Audit::$plugin->getSettings()->logPluginEvents = false;

    $mockPlugin = new class('test-plugin', \Craft::$app) extends \craft\base\Plugin {
        public ?string $name = 'Test Plugin';
        public string $version = '1.0.0';
    };

    $result = Audit::$plugin->pluginHandler->onPluginEvent(
        AuditEvent::PluginEnabled->value,
        $mockPlugin
    );

    expect($result)->toBeFalse();

    Audit::$plugin->getSettings()->logPluginEvents = true;
});

it('captures plugin info in snapshot', function () {
    $mockPlugin = new class('test-plugin', \Craft::$app) extends \craft\base\Plugin {
        public ?string $name = 'Test Plugin';
        public string $version = '1.0.0';
    };

    Audit::$plugin->pluginHandler->onPluginEvent(
        AuditEvent::PluginEnabled->value,
        $mockPlugin
    );

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::PluginEnabled->value])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toBeArray();
    expect($model->snapshot)->toHaveKey('title');
    expect($model->snapshot)->toHaveKey('handle');
    expect($model->snapshot)->toHaveKey('version');
    expect($model->snapshot['handle'])->toBe('test-plugin');
    expect($model->snapshot['version'])->toBe('1.0.0');
});

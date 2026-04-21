<?php

use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    // Clear audit logs before each test
    AuditRecord::deleteAll();
});

it('logs audit event when plugin is enabled', function () {
    // Create a mock plugin for testing (Craft 5 requires $id and $parent in constructor)
    $mockPlugin = new class('test-plugin', \Craft::$app) extends \craft\base\Plugin {
        public ?string $name = 'Test Plugin';
        public string $version = '1.0.0';
    };

    Audit::$plugin->auditService->onPluginEvent(
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

    Audit::$plugin->auditService->onPluginEvent(
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

    $result = Audit::$plugin->auditService->onPluginEvent(
        AuditEvent::PluginEnabled->value,
        $mockPlugin
    );

    expect($result)->toBeFalse();

    // Re-enable for other tests
    Audit::$plugin->getSettings()->logPluginEvents = true;
});

it('captures plugin info in snapshot', function () {
    $mockPlugin = new class('test-plugin', \Craft::$app) extends \craft\base\Plugin {
        public ?string $name = 'Test Plugin';
        public string $version = '1.0.0';
    };

    Audit::$plugin->auditService->onPluginEvent(
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

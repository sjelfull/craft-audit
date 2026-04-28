<?php

use superbig\audit\Audit;

uses(\markhuot\craftpest\test\TestCase::class)
    ->in('Feature', 'Unit');

/**
 * Register the Audit plugin's event listeners once per test process.
 *
 * In production, listeners are registered inside Audit::init() but gated
 * behind `!isConsoleRequest && tableSchemaExists && settings->enabled`,
 * and the actual registration runs inside a `Plugins::EVENT_AFTER_LOAD_PLUGINS`
 * callback. None of those preconditions hold in the Pest test environment
 * (Craft sees the test runner as a console request), so without this hook
 * real Craft API calls fire events that nothing listens to.
 *
 * Calling the protected `initLogEvents()` method via reflection lets us
 * invoke real Craft services (`Craft::$app->fields->saveField($field)` etc.)
 * and assert that audit records are written. This exercises the wiring
 * between Craft event handles and audit handlers — the full path that
 * direct handler calls bypass.
 */
function _audit_register_listeners_for_tests(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $plugin = Audit::$plugin;
    if ($plugin === null) {
        return;
    }
    $ref = new \ReflectionMethod($plugin, 'initLogEvents');
    $ref->setAccessible(true);
    $ref->invoke($plugin);
    $registered = true;
}

uses()
    ->beforeEach(function () {
        _audit_register_listeners_for_tests();
    })
    ->in('Feature', 'Unit');

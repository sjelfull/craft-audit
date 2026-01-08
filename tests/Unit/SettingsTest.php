<?php

use superbig\audit\models\Settings;

it('has correct default values', function () {
    $settings = new Settings();

    expect($settings->pruneDays)->toBe(30);
    expect($settings->logElementEvents)->toBeTrue();
    expect($settings->logUserEvents)->toBeTrue();
    expect($settings->logPluginEvents)->toBeTrue();
    expect($settings->logRouteEvents)->toBeTrue();
});

it('allows toggling boolean settings', function () {
    $settings = new Settings();

    $settings->logElementEvents = false;
    expect($settings->logElementEvents)->toBeFalse();

    $settings->logElementEvents = true;
    expect($settings->logElementEvents)->toBeTrue();

    $settings->logUserEvents = false;
    expect($settings->logUserEvents)->toBeFalse();

    $settings->logPluginEvents = false;
    expect($settings->logPluginEvents)->toBeFalse();

    $settings->logRouteEvents = false;
    expect($settings->logRouteEvents)->toBeFalse();

    $settings->logDraftEvents = true;
    expect($settings->logDraftEvents)->toBeTrue();

    $settings->logChildElementEvents = true;
    expect($settings->logChildElementEvents)->toBeTrue();
});

it('allows setting custom pruneDays value', function () {
    $settings = new Settings();

    $settings->pruneDays = 7;
    expect($settings->pruneDays)->toBe(7);

    $settings->pruneDays = 90;
    expect($settings->pruneDays)->toBe(90);

    $settings->pruneDays = 365;
    expect($settings->pruneDays)->toBe(365);
});

<?php

use superbig\audit\enums\AuditEvent;

it('has 69 unique kebab-case backing values', function () {
    $values = array_map(fn ($c) => $c->value, AuditEvent::cases());
    expect(count($values))->toBe(69);
    expect(count(array_unique($values)))->toBe(69);
    foreach ($values as $v) {
        expect($v)->toMatch('/^[a-z]+(-[a-z]+)*$/');
    }
});

it('keeps stable backing values for known events', function () {
    // Smoke-test a few important values to lock them in
    expect(AuditEvent::EntrySaved->value)->toBe('entry-saved');
    expect(AuditEvent::UserLoggedIn->value)->toBe('user-logged-in');
    expect(AuditEvent::SystemSettingsChanged->value)->toBe('system-settings-changed');
    expect(AuditEvent::BackupCreated->value)->toBe('backup-created');
});

it('tryFromString returns null for unknown/empty values', function () {
    expect(AuditEvent::tryFromString(null))->toBeNull();
    expect(AuditEvent::tryFromString(''))->toBeNull();
    expect(AuditEvent::tryFromString('bogus-event'))->toBeNull();
});

it('tryFromString resolves existing events', function () {
    expect(AuditEvent::tryFromString('entry-saved'))->toBe(AuditEvent::EntrySaved);
    expect(AuditEvent::tryFromString('user-logged-in'))->toBe(AuditEvent::UserLoggedIn);
    expect(AuditEvent::tryFromString('saved-element'))->toBe(AuditEvent::SavedElement);
});

it('has 69 total cases (45 original + 24 project config)', function () {
    $count = count(AuditEvent::cases());
    expect($count)->toBe(69);
});

it('returns a translated label for each case', function () {
    $label = AuditEvent::EntrySaved->label();
    expect($label)->toBeString();
    expect($label)->not->toBeEmpty();

    // A sampling of other cases should also produce non-empty labels
    expect(AuditEvent::UserLoggedIn->label())->toBeString()->not->toBeEmpty();
    expect(AuditEvent::SavedElement->label())->toBeString()->not->toBeEmpty();
});

it('all enum cases have unique backing values', function () {
    $values = array_map(fn ($c) => $c->value, AuditEvent::cases());
    expect(count($values))->toBe(count(array_unique($values)));
});

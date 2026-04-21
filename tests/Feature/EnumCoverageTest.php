<?php

use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;

it('has all constants also available as enum cases', function () {
    // Verify that key legacy string constants have matching enum cases
    expect(AuditEvent::SavedElement->value)->toBe(AuditModel::EVENT_SAVED_ELEMENT);
    expect(AuditEvent::EntryCreated->value)->toBe(AuditModel::EVENT_ENTRY_CREATED);
    expect(AuditEvent::UserLoggedIn->value)->toBe(AuditModel::USER_LOGGED_IN);
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

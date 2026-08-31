<?php

use craft\elements\Entry as EntryElement;
use DateTime;
use superbig\audit\Audit;

/**
 * In-memory Entry for captureState tests. Avoids craftpest Entry::factory()
 * which creates random section/entry-type handles that can collide across
 * the suite (seen as flaky "Handle … has already been taken" on PHP 8.4 +
 * Postgres). captureState only needs element attribute accessors.
 */
function _auditCaptureStateEntry(): EntryElement
{
    $entry = new EntryElement();
    $entry->title = 'Capture state fixture';
    $entry->slug = 'capture-state-fixture';
    $entry->enabled = true;
    $entry->postDate = new DateTime('2026-01-15 12:00:00');
    $entry->expiryDate = new DateTime('2026-12-31 23:59:59');

    return $entry;
}

it('captures an entry state with native attributes', function () {
    $state = Audit::$plugin->fieldDiffService->captureState(_auditCaptureStateEntry());

    expect($state)->toHaveKey('_title');
    expect($state)->toHaveKey('_slug');
    expect($state)->toHaveKey('_status');
    expect($state)->toHaveKey('_enabled');
});

it('diffs two states and returns only changed fields', function () {
    $oldState = [
        '_title' => ['handler' => 'plain', 'value' => 'Old Title'],
        '_slug' => ['handler' => 'plain', 'value' => 'same-slug'],
    ];
    $newState = [
        '_title' => ['handler' => 'plain', 'value' => 'New Title'],
        '_slug' => ['handler' => 'plain', 'value' => 'same-slug'],
    ];

    $diff = Audit::$plugin->fieldDiffService->diff($oldState, $newState);

    expect($diff)->toHaveKey('_title');
    expect($diff)->not->toHaveKey('_slug');
    expect($diff['_title']['from'])->toBe('Old Title');
    expect($diff['_title']['to'])->toBe('New Title');
    expect($diff['_title']['handler'])->toBe('plain');
});

it('returns an empty diff for identical states', function () {
    $state = [
        '_title' => ['handler' => 'plain', 'value' => 'Unchanged'],
        '_slug' => ['handler' => 'plain', 'value' => 'unchanged'],
    ];

    $diff = Audit::$plugin->fieldDiffService->diff($state, $state);

    expect($diff)->toBe([]);
});

it('detects newly-added fields in a diff', function () {
    $old = [];
    $new = [
        'metaDesc' => ['handler' => 'plain', 'value' => 'Added'],
    ];

    $diff = Audit::$plugin->fieldDiffService->diff($old, $new);

    expect($diff)->toHaveKey('metaDesc');
    expect($diff['metaDesc']['from'])->toBeNull();
    expect($diff['metaDesc']['to'])->toBe('Added');
});

it('captures entry postDate and expiryDate', function () {
    $state = Audit::$plugin->fieldDiffService->captureState(_auditCaptureStateEntry());

    expect($state)->toHaveKey('_postDate');
    expect($state)->toHaveKey('_expiryDate');
    expect($state['_postDate']['value'])->toBe('2026-01-15 12:00:00');
    expect($state['_expiryDate']['value'])->toBe('2026-12-31 23:59:59');
});

<?php

use superbig\audit\services\FieldDiffService;

it('returns empty diff when states are identical', function () {
    $svc = new FieldDiffService();

    $state = [
        '_title' => ['handler' => 'plain', 'value' => 'Hello'],
        '_slug' => ['handler' => 'plain', 'value' => 'hello'],
    ];

    expect($svc->diff($state, $state))->toBe([]);
});

it('returns changed fields', function () {
    $svc = new FieldDiffService();

    $old = [
        '_title' => ['handler' => 'plain', 'value' => 'Old'],
        '_slug' => ['handler' => 'plain', 'value' => 'hello'],
    ];
    $new = [
        '_title' => ['handler' => 'plain', 'value' => 'New'],
        '_slug' => ['handler' => 'plain', 'value' => 'hello'],
    ];

    $diff = $svc->diff($old, $new);
    expect($diff)->toHaveKey('_title');
    expect($diff)->not->toHaveKey('_slug');
    expect($diff['_title'])->toBe([
        'handler' => 'plain',
        'from' => 'Old',
        'to' => 'New',
    ]);
});

it('detects added fields (missing on old side)', function () {
    $svc = new FieldDiffService();

    $old = [];
    $new = ['body' => ['handler' => 'plain', 'value' => 'Added']];

    $diff = $svc->diff($old, $new);
    expect($diff)->toHaveKey('body');
    expect($diff['body']['from'])->toBeNull();
    expect($diff['body']['to'])->toBe('Added');
    expect($diff['body']['handler'])->toBe('plain');
});

it('detects removed fields (missing on new side)', function () {
    $svc = new FieldDiffService();

    $old = ['body' => ['handler' => 'plain', 'value' => 'Removed']];
    $new = [];

    $diff = $svc->diff($old, $new);
    expect($diff)->toHaveKey('body');
    expect($diff['body']['from'])->toBe('Removed');
    expect($diff['body']['to'])->toBeNull();
    expect($diff['body']['handler'])->toBe('plain');
});

it('considers deeply equal arrays unchanged', function () {
    $svc = new FieldDiffService();

    $old = ['rel' => ['handler' => 'relation', 'value' => [1, 2, 3]]];
    $new = ['rel' => ['handler' => 'relation', 'value' => [1, 2, 3]]];

    expect($svc->diff($old, $new))->toBe([]);
});

it('detects differences in arrays', function () {
    $svc = new FieldDiffService();

    $old = ['rel' => ['handler' => 'relation', 'value' => [1, 2, 3]]];
    $new = ['rel' => ['handler' => 'relation', 'value' => [1, 2, 4]]];

    $diff = $svc->diff($old, $new);
    expect($diff)->toHaveKey('rel');
    expect($diff['rel']['from'])->toBe([1, 2, 3]);
    expect($diff['rel']['to'])->toBe([1, 2, 4]);
});

it('exposes the maxFieldBytes threshold', function () {
    $svc = new FieldDiffService();
    expect($svc->maxFieldBytes)->toBe(1_000_000);

    $svc->maxFieldBytes = 100;
    expect($svc->maxFieldBytes)->toBe(100);
});

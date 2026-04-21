<?php

use superbig\audit\Audit;
use superbig\audit\services\DiffRenderer;

it('exposes DiffRenderer as a registered component', function () {
    $r = Audit::$plugin->diffRenderer;
    expect($r)->toBeInstanceOf(DiffRenderer::class);
});

it('renders identical strings as a "No changes" badge', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderTextDiff('same', 'same');
    expect($out)->toContain('No changes');
});

it('renders a text diff with ins/del markup for differing strings', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderTextDiff('Hello world', 'Hello everyone');
    $hasMarkup = str_contains($out, 'ins') || str_contains($out, 'del');
    expect($hasMarkup)->toBeTrue();
});

it('renders HTML diff and preserves ins markup', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderHtmlDiff('<p>Hello</p>', '<p>Hello world</p>');
    // Either contains <ins or falls back to simple diff; either way non-empty & marks the change
    expect($out)->not->toBeEmpty();
    $marks = str_contains($out, 'ins') || str_contains($out, 'audit-diff-new');
    expect($marks)->toBeTrue();
});

it('handles empty old value', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderTextDiff('', 'New content');
    expect($out)->not->toBeEmpty();
});

it('handles empty new value', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderTextDiff('Old content', '');
    expect($out)->not->toBeEmpty();
});

it('treats both-empty as identical', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderTextDiff('', '');
    expect($out)->toContain('No changes');
});

it('truncates content over maxBytes', function () {
    $r = Audit::$plugin->diffRenderer;
    $huge = str_repeat('x', 100000);
    $out = $r->maybeTruncate($huge, 1000);
    expect(strlen($out))->toBeLessThan(1200); // truncated + marker
    expect($out)->toContain('truncated');
});

it('returns original content when under maxBytes', function () {
    $r = Audit::$plugin->diffRenderer;
    $small = 'hello';
    expect($r->maybeTruncate($small, 1000))->toBe('hello');
});

it('renders a simple "old → new" diff for scalar values', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderSimpleDiff('on', 'off');
    expect($out)->toContain('on');
    expect($out)->toContain('off');
    expect($out)->toContain('audit-diff-simple');
});

it('escapes HTML in simple diff to prevent XSS', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderSimpleDiff('<script>alert(1)</script>', 'safe');
    expect($out)->not->toContain('<script>alert(1)</script>');
    expect($out)->toContain('&lt;script');
});

it('renders json diff for structured data', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderJsonDiff(['a' => 1], ['a' => 2]);
    expect($out)->not->toBeEmpty();
    expect($out)->not->toContain('No changes');
});

it('returns identical for equal json', function () {
    $r = Audit::$plugin->diffRenderer;
    $out = $r->renderJsonDiff(['x' => 1, 'y' => 2], ['x' => 1, 'y' => 2]);
    expect($out)->toContain('No changes');
});

it('gracefully handles malformed HTML without throwing', function () {
    $r = Audit::$plugin->diffRenderer;
    $malformed = '<p><p><p>unclosed tags<script>';
    $closure = fn() => $r->renderHtmlDiff($malformed, '');
    expect($closure)->not->toThrow(\Throwable::class);
    // Must still return a non-empty string
    expect($closure())->toBeString()->not->toBeEmpty();
});

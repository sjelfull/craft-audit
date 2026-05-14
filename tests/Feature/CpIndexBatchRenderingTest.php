<?php

/**
 * @P3.3 CP UI batch rendering — template-level tests
 *
 * Tests the `audit/_table` partial in isolation. The full `audit/index`
 * template extends `_layouts/cp` and pulls in CP-only context (current
 * user, asset bundles, etc.), so we factor the table out into a partial
 * and render that directly. This is presentation-logic testing — we're
 * asserting that the right classes/attributes/strings land in the HTML
 * given a given set of input models.
 *
 * Limitation: the rendered HTML is examined with string/regex assertions.
 * Visual behavior (dark mode, hover, animations, the actual toggle click)
 * requires manual CP verification.
 */

use Craft;
use craft\web\View;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

/**
 * Hydrate top-level rows the way the controller does, then render the
 * `audit/_table` partial against them. Returns the HTML string.
 */
function _renderAuditTable(): string
{
    $records = AuditRecord::find()
        ->orderBy('dateCreated desc, id desc')
        ->where(['parentId' => null])
        ->all();
    $logs = [];
    foreach ($records as $record) {
        $logs[] = AuditModel::createFromRecord($record);
    }

    $view = Craft::$app->getView();
    $oldMode = $view->getTemplateMode();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);
    try {
        return $view->renderTemplate('audit/_table', ['logs' => $logs]);
    } finally {
        $view->setTemplateMode($oldMode);
    }
}

it('renders a non-batch row without any batch markup', function () {
    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'A plain entry save',
    );

    $html = _renderAuditTable();

    expect($html)->toContain('A plain entry save');
    expect($html)->not->toContain('audit-batch-parent');
    expect($html)->not->toContain('audit-batch-toggle');
    expect($html)->not->toContain('audit-child-row');
});

it('renders a batch parent with toggle, aria attributes, and meta showing childCount + completed', function () {
    $batch = Audit::$plugin->batch;
    $batchId = null;

    $batch->run('Resaving Entry elements', function () use ($batch, &$batchId) {
        $batchId = $batch->currentBatchId();
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: '1');
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: '2');
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: '3');
    });

    $html = _renderAuditTable();

    expect($html)->toContain('audit-batch-parent');
    expect($html)->toContain('data-audit-batch="' . $batchId . '"');
    expect($html)->toContain('class="audit-batch-toggle"');
    expect($html)->toContain('aria-controls="batch-' . $batchId . '"');
    expect($html)->toContain('aria-expanded="false"');
    expect($html)->toContain('type="button"');
    // Meta: count + state
    expect($html)->toContain('3 child rows');
    expect($html)->toContain('completed');
});

it('renders child rows as hidden tr.audit-child-row with data-parent matching the batch id', function () {
    $batch = Audit::$plugin->batch;
    $batchId = null;
    $batch->run('Resaving Entry elements', function () use ($batch, &$batchId) {
        $batchId = $batch->currentBatchId();
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: 'Child 1');
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: 'Child 2');
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: 'Child 3');
    });

    $html = _renderAuditTable();

    // All 3 children appear, each as audit-child-row hidden with data-parent=batchId
    $count = preg_match_all(
        '/<tr class="audit-child-row hidden" data-parent="' . preg_quote((string) $batchId, '/') . '">/',
        $html
    );
    expect($count)->toBe(3);
    expect($html)->toContain('Child 1');
    expect($html)->toContain('Child 2');
    expect($html)->toContain('Child 3');
});

it('renders an empty batch with a disabled toggle and no child rows', function () {
    $batch = Audit::$plugin->batch;
    $batch->run('Empty batch', fn () => null);

    $html = _renderAuditTable();

    expect($html)->toContain('audit-batch-parent');
    expect($html)->toContain('audit-batch-toggle');
    // The disabled attribute lands on the button
    expect($html)->toMatch('/<button[^>]+class="audit-batch-toggle"[^>]+disabled/');
    expect($html)->not->toContain('audit-child-row');
    expect($html)->toContain('No child rows in this batch');
});

it('renders a failed batch with state=failed in the meta', function () {
    $batch = Audit::$plugin->batch;
    try {
        $batch->run('Failing batch', function () {
            throw new \RuntimeException('boom');
        });
    } catch (\RuntimeException) {
        // expected
    }

    $html = _renderAuditTable();

    expect($html)->toContain('audit-batch-parent');
    expect($html)->toContain('Failing batch');
    expect($html)->toContain('failed');
});

it('getChildren() returns a falsy value (null or empty array) for a non-batch row', function () {
    // AuditService::getEventsByAttributes() currently returns null when no
    // records match (rather than []). The template normalizes via the null
    // coalescing operator (\`log.children ?? []\`). This test pins both
    // behaviors: the model accessor doesn't throw on no-children rows, and
    // the result is falsy so \`{% if children %}\` works.
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'No-children row',
    );
    expect($model)->not->toBeNull();

    $record = AuditRecord::findOne($model->id);
    $loaded = AuditModel::createFromRecord($record);

    $children = $loaded->getChildren();
    expect($children === null || $children === [])->toBeTrue();
});

it('renders a nested batch as a child row of the outer batch (depth-1)', function () {
    // The controller filters parentId is null so only the outer batch
    // appears at top level. The inner batch's parent row should be
    // emitted as an audit-child-row under the outer, carrying the outer
    // batch's id as its data-parent.
    $batch = Audit::$plugin->batch;

    $outerId = $batch->open('Outer batch');
    $innerId = $batch->open('Inner batch');
    Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: 'Grandchild');
    $batch->close($innerId);
    $batch->close($outerId);

    $html = _renderAuditTable();

    // Outer is the only top-level row (inner is a child of outer)
    $topLevelMatches = preg_match_all('/<tr class="audit-batch-parent[^"]*"/', $html);
    expect($topLevelMatches)->toBe(1);

    // Inner batch's row appears as a child row of the outer
    expect($html)->toMatch(
        '/<tr class="audit-child-row hidden" data-parent="' . preg_quote((string) $outerId, '/') . '">/'
    );
    expect($html)->toContain('Inner batch');
});

it('mixed: non-batches + a batch with 2 children render 3 top-level rows + 2 child rows', function () {
    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Plain row before',
    );

    $batch = Audit::$plugin->batch;
    $batch->run('Mixed batch', function () {
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: 'C1');
        Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: 'C2');
    });

    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Plain row after',
    );

    $html = _renderAuditTable();

    $batchParents = preg_match_all('/<tr class="audit-batch-parent[^"]*"/', $html);
    $childRows = preg_match_all('/<tr class="audit-child-row hidden"/', $html);

    expect($batchParents)->toBe(1);
    expect($childRows)->toBe(2);
    expect($html)->toContain('Plain row before');
    expect($html)->toContain('Plain row after');
    expect($html)->toContain('Mixed batch');
});

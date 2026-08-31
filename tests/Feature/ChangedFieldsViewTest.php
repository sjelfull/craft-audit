<?php

/**
 * Changed-fields pane — template-level tests.
 *
 * Mirrors CpIndexBatchRenderingTest: render the `audit/_changed-fields`
 * partial in isolation (avoids the full CP layout of audit/_view) and
 * assert the right classes/handles/labels land in the HTML.
 */

use Craft;
use craft\web\View;
use superbig\audit\models\AuditModel;

/**
 * @param array $changedFields
 * @param array $fieldMap
 */
function _renderChangedFields(array $changedFields, array $fieldMap = []): string
{
    $view = Craft::$app->getView();
    $oldMode = $view->getTemplateMode();
    $view->setTemplateMode(View::TEMPLATE_MODE_CP);
    try {
        return $view->renderTemplate('audit/_changed-fields', [
            'changedFields' => $changedFields,
            'fieldMap' => $fieldMap,
        ]);
    } finally {
        $view->setTemplateMode($oldMode);
    }
}

it('renders nothing when changedFields is empty', function () {
    $html = _renderChangedFields([]);

    expect($html)->not->toContain('audit-changed-fields');
    expect($html)->not->toContain('Changed fields');
});

it('renders a Changed fields heading and dispatches each change through _diff', function () {
    $html = _renderChangedFields(
        changedFields: [
            '_title' => [
                'handler' => 'plain',
                'from' => 'Old Title',
                'to' => 'New Title',
            ],
            'body' => [
                'handler' => 'generic',
                'from' => ['text' => 'before'],
                'to' => ['text' => 'after'],
            ],
        ],
        fieldMap: [
            '_title' => ['name' => 'Title'],
            'body' => ['name' => 'Body'],
        ],
    );

    expect($html)->toContain('audit-changed-fields');
    expect($html)->toContain('Changed fields');
    expect($html)->toContain('data-handle="_title"');
    expect($html)->toContain('data-handle="body"');
    expect($html)->toContain('Title');
    expect($html)->toContain('Body');
    // plain handler short-diff markup
    expect($html)->toContain('audit-diff-simple');
    expect($html)->toContain('Old Title');
    expect($html)->toContain('New Title');
    // generic handler side-by-side
    expect($html)->toContain('audit-diff-generic');
});

it('falls back to the handle when fieldMap has no entry', function () {
    $html = _renderChangedFields(
        changedFields: [
            'mysteryField' => [
                'handler' => 'plain',
                'from' => 'a',
                'to' => 'b',
            ],
        ],
        fieldMap: [],
    );

    expect($html)->toContain('data-handle="mysteryField"');
    expect($html)->toContain('<strong>mysteryField</strong>');
});

it('AuditModel::getFieldMap labels native attributes and falls back for unknown handles', function () {
    $model = new AuditModel();
    $model->changedFields = [
        '_title' => ['handler' => 'plain', 'from' => 'a', 'to' => 'b'],
        '_slug' => ['handler' => 'plain', 'from' => 'a', 'to' => 'b'],
        'noSuchFieldHandleXyz' => ['handler' => 'config', 'from' => 1, 'to' => 2],
    ];

    $map = $model->getFieldMap();

    expect($map)->toHaveKey('_title');
    expect($map['_title']['name'])->not->toBe('_title');
    expect($map)->toHaveKey('_slug');
    expect($map['noSuchFieldHandleXyz']['name'])->toBe('noSuchFieldHandleXyz');
});

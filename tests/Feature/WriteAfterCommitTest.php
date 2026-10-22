<?php

use craft\db\Connection;
use craft\db\Query;
use craft\helpers\App;
use markhuot\craftpest\factories\Entry as EntryFactory;
use superbig\audit\Audit;
use superbig\audit\events\SnapshotEvent;
use superbig\audit\records\AuditRecord;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

/**
 * craft-pest keeps every test inside a transaction it rolls back, so real commits and
 * rollbacks are driven on a second connection the recorder is pointed at. Rows it
 * commits are real and removed again afterwards.
 */
beforeEach(function () {
    $this->secondDb = Craft::createObject(App::dbConfig());
    $this->recorder = Audit::$plugin->auditRecorder;
    $this->recorder->db = $this->secondDb;
    $this->recorder->writeAfterCommit = true;
    $this->marker = 'write-after-commit-' . bin2hex(random_bytes(4));
});

afterEach(function () {
    $this->secondDb->createCommand()->delete(AuditRecord::tableName(), ['like', 'title', 'write-after-commit-%', false])->execute();
    $this->secondDb->close();
    Event::off(AuditRecorder::class, AuditRecorder::EVENT_SNAPSHOT);
});

function _committedRows(Connection $db, string $title): int
{
    return (int)(new Query())->from(AuditRecord::tableName())->where(['title' => $title])->count('*', $db);
}

it('writes a row recorded with no transaction open straight away', function () {
    $model = $this->recorder->record('custom-event', $this->marker);

    expect($model->id)->not->toBeNull();
    expect(_committedRows($this->secondDb, $this->marker))->toBe(1);
});

it('holds a row recorded inside a transaction until it commits', function () {
    $transaction = $this->secondDb->beginTransaction();
    $this->recorder->record('custom-event', $this->marker);

    expect(_committedRows($this->secondDb, $this->marker))->toBe(0);

    $transaction->commit();

    expect(_committedRows($this->secondDb, $this->marker))->toBe(1);
});

it('drops a row recorded inside a transaction that rolls back', function () {
    $transaction = $this->secondDb->beginTransaction();
    $this->recorder->record('custom-event', $this->marker);
    $transaction->rollBack();

    expect(_committedRows($this->secondDb, $this->marker))->toBe(0);
    expect($this->recorder->pendingChildCount(0))->toBe(0);

    // A later commit doesn't resurrect it.
    $this->secondDb->transaction(fn() => null);
    expect(_committedRows($this->secondDb, $this->marker))->toBe(0);
});

it('waits for the outermost transaction, not a savepoint', function () {
    $outer = $this->secondDb->beginTransaction();
    $inner = $this->secondDb->beginTransaction();
    $this->recorder->record('custom-event', $this->marker);
    $inner->commit();

    expect(_committedRows($this->secondDb, $this->marker))->toBe(0);

    $outer->commit();

    expect(_committedRows($this->secondDb, $this->marker))->toBe(1);
});

it('writes rows in the order they were recorded', function () {
    $this->secondDb->transaction(function() {
        $this->recorder->record('first-event', $this->marker);
        $this->recorder->record('second-event', $this->marker);
    });

    $events = (new Query())->select('event')->from(AuditRecord::tableName())
        ->where(['title' => $this->marker])->orderBy(['id' => SORT_ASC])->column($this->secondDb);
    expect($events)->toBe(['first-event', 'second-event']);
});

it('never throws when the database write fails', function () {
    $this->recorder->db = new Connection(['dsn' => 'mysql:host=127.0.0.1;port=1;dbname=nothing', 'username' => 'x', 'password' => 'x']);

    expect($this->recorder->record('custom-event', $this->marker))->toBeNull();

    $entry = EntryFactory::factory()->section('news')->create();
    expect($entry->id)->not->toBeNull();
});

it('never throws into an element save when a listener fails', function () {
    $this->recorder->db = null;
    $this->recorder->writeAfterCommit = false;
    Event::on(AuditRecorder::class, AuditRecorder::EVENT_SNAPSHOT, function(SnapshotEvent $event) {
        throw new Error('A broken snapshot listener');
    });

    $entry = EntryFactory::factory()->section('news')->title('Still saved')->make();
    expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();
});

it('never throws into a sign-in when recording fails', function () {
    $this->recorder->db = null;
    $this->recorder->writeAfterCommit = false;
    Event::on(AuditRecorder::class, AuditRecorder::EVENT_SNAPSHOT, function(SnapshotEvent $event) {
        throw new Error('A broken snapshot listener');
    });

    $response = $this->action('users/login', ['loginName' => 'nobody@example.test', 'password' => 'wrong']);

    expect($response->getStatusCode())->toBeLessThan(500);
    expect(AuditRecord::find()->where(['event' => 'user-login-failed'])->count())->toEqual(0);
});

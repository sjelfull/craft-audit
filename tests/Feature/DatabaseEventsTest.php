<?php

use craft\elements\User;
use craft\events\BackupEvent;
use craft\events\RestoreEvent;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('logs audit event when backup is created', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new BackupEvent([
        'file' => '/path/to/backup/craft-db-backup-2025-01-08.sql',
    ]);
    Audit::$plugin->backupHandler->onBackupCreated($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::BackupCreated->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('craft-db-backup-2025-01-08.sql');
});

it('logs audit event when backup is restored', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new RestoreEvent([
        'file' => '/path/to/backup/craft-db-backup-2025-01-08.sql',
    ]);
    Audit::$plugin->backupHandler->onBackupRestored($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::BackupRestored->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('craft-db-backup-2025-01-08.sql');
});

it('does not log database events when disabled', function () {
    Audit::$plugin->getSettings()->logDatabaseEvents = false;

    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new BackupEvent([
        'file' => '/path/to/backup/test.sql',
    ]);
    $result = Audit::$plugin->backupHandler->onBackupCreated($event);

    expect($result)->toBeFalse();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::BackupCreated->value])
        ->one();

    expect($record)->toBeNull();

    Audit::$plugin->getSettings()->logDatabaseEvents = true;
});

it('captures backup file path in snapshot', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $backupFile = '/path/to/backup/craft-db-backup-2025-01-08.sql';
    $event = new BackupEvent([
        'file' => $backupFile,
    ]);
    Audit::$plugin->backupHandler->onBackupCreated($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::BackupCreated->value])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('file');
    expect($model->snapshot['file'])->toBe($backupFile);
});

it('captures restore file path in snapshot', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $restoreFile = '/path/to/backup/craft-db-restore-2025-01-08.sql';
    $event = new RestoreEvent([
        'file' => $restoreFile,
    ]);
    Audit::$plugin->backupHandler->onBackupRestored($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::BackupRestored->value])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('file');
    expect($model->snapshot['file'])->toBe($restoreFile);
});

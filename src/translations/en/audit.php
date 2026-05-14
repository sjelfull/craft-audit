<?php

return [
    'Audit plugin loaded' => 'Audit plugin loaded',
    'Error when logging: {error}' => 'Error when logging: {error}',

    // Event labels
    \superbig\audit\enums\AuditEvent::SavedElement->value => 'Saved element',
    \superbig\audit\enums\AuditEvent::ResavedElements->value => 'Resaved elements',
    \superbig\audit\enums\AuditEvent::CreatedElement->value => 'Created element',
    \superbig\audit\enums\AuditEvent::DeletedElement->value => 'Deleted element',

    // Entries
    \superbig\audit\enums\AuditEvent::EntryCreated->value => 'Created',
    \superbig\audit\enums\AuditEvent::EntrySaved->value => 'Saved',
    \superbig\audit\enums\AuditEvent::EntryDeleted->value => 'Deleted',

    \superbig\audit\enums\AuditEvent::SavedDraft->value => 'Saved draft',
    \superbig\audit\enums\AuditEvent::CreatedDraft->value => 'Created draft',
    \superbig\audit\enums\AuditEvent::DeletedDraft->value => 'Deleted draft',
    \superbig\audit\enums\AuditEvent::SavedGlobal->value => 'Saved global',
    \superbig\audit\enums\AuditEvent::UserLoggedIn->value => 'Logged in',
    \superbig\audit\enums\AuditEvent::UserLoggedOut->value => 'Logged out',

    // Routes
    \superbig\audit\enums\AuditEvent::SavedRoute->value => 'Saved route',
    \superbig\audit\enums\AuditEvent::CreatedRoute->value => 'Created route',
    \superbig\audit\enums\AuditEvent::DeletedRoute->value => 'Deleted route',

    // Plugins
    \superbig\audit\enums\AuditEvent::PluginInstalled->value => 'Plugin installed',
    \superbig\audit\enums\AuditEvent::PluginUninstalled->value => 'Plugin uninstalled',
    \superbig\audit\enums\AuditEvent::PluginDisabled->value => 'Plugin disabled',
    \superbig\audit\enums\AuditEvent::PluginEnabled->value => 'Plugin enabled',

    // Users
    \superbig\audit\enums\AuditEvent::UserActivated->value => 'User activated',
    \superbig\audit\enums\AuditEvent::UserDeactivated->value => 'User deactivated',
    \superbig\audit\enums\AuditEvent::UserSuspended->value => 'User suspended',
    \superbig\audit\enums\AuditEvent::UserUnsuspended->value => 'User unsuspended',
    \superbig\audit\enums\AuditEvent::UserLocked->value => 'User locked',
    \superbig\audit\enums\AuditEvent::UserUnlocked->value => 'User unlocked',
    \superbig\audit\enums\AuditEvent::UserGroupsAssigned->value => 'User groups assigned',

    // Permissions
    \superbig\audit\enums\AuditEvent::UserPermissionsSaved->value => 'User permissions saved',
    \superbig\audit\enums\AuditEvent::GroupPermissionsSaved->value => 'Group permissions saved',
    \superbig\audit\enums\AuditEvent::UserGroupCreated->value => 'User group created',
    \superbig\audit\enums\AuditEvent::UserGroupSaved->value => 'User group saved',
    \superbig\audit\enums\AuditEvent::UserGroupDeleted->value => 'User group deleted',

    // Schema
    \superbig\audit\enums\AuditEvent::FieldCreated->value => 'Field created',
    \superbig\audit\enums\AuditEvent::FieldSaved->value => 'Field saved',
    \superbig\audit\enums\AuditEvent::FieldDeleted->value => 'Field deleted',
    \superbig\audit\enums\AuditEvent::SectionCreated->value => 'Section created',
    \superbig\audit\enums\AuditEvent::SectionSaved->value => 'Section saved',
    \superbig\audit\enums\AuditEvent::SectionDeleted->value => 'Section deleted',
    \superbig\audit\enums\AuditEvent::EntryTypeCreated->value => 'Entry type created',
    \superbig\audit\enums\AuditEvent::EntryTypeSaved->value => 'Entry type saved',
    \superbig\audit\enums\AuditEvent::EntryTypeDeleted->value => 'Entry type deleted',

    // Settings
    \superbig\audit\enums\AuditEvent::SystemSettingsChanged->value => 'System settings changed',
    \superbig\audit\enums\AuditEvent::EmailSettingsChanged->value => 'Email settings changed',

    // Backups
    \superbig\audit\enums\AuditEvent::BackupCreated->value => 'Backup created',
    \superbig\audit\enums\AuditEvent::BackupRestored->value => 'Backup restored',

    // Batch lifecycle
    \superbig\audit\enums\AuditEvent::BatchStarted->value => 'Batch started',
    \superbig\audit\enums\AuditEvent::BatchCompleted->value => 'Batch completed',
    \superbig\audit\enums\AuditEvent::BatchFailed->value => 'Batch failed',
];

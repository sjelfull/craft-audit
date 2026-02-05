<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
return [
    'Audit plugin loaded' => 'Audit plugin loaded',
    'Error when logging: {error}' => 'Error when logging: {error}',

    // Event labels
    \superbig\audit\models\AuditModel::EVENT_SAVED_ELEMENT => 'Saved element',
    \superbig\audit\models\AuditModel::EVENT_RESAVED_ELEMENTS => 'Resaved elements',
    \superbig\audit\models\AuditModel::EVENT_CREATED_ELEMENT => 'Created element',
    \superbig\audit\models\AuditModel::EVENT_DELETED_ELEMENT => 'Deleted element',

    // Entries
    \superbig\audit\models\AuditModel::EVENT_ENTRY_CREATED => 'Created',
    \superbig\audit\models\AuditModel::EVENT_ENTRY_SAVED => 'Saved',
    \superbig\audit\models\AuditModel::EVENT_ENTRY_DELETED => 'Deleted',

    \superbig\audit\models\AuditModel::EVENT_SAVED_DRAFT => 'Saved draft',
    \superbig\audit\models\AuditModel::EVENT_CREATED_DRAFT => 'Created draft',
    \superbig\audit\models\AuditModel::EVENT_DELETED_DRAFT => 'Deleted draft',
    \superbig\audit\models\AuditModel::EVENT_SAVED_GLOBAL => 'Saved global',
    \superbig\audit\models\AuditModel::USER_LOGGED_IN => 'Logged in',
    \superbig\audit\models\AuditModel::USER_LOGGED_OUT => 'Logged out',

    // Routes
    \superbig\audit\models\AuditModel::EVENT_SAVED_ROUTE => 'Saved route',
    \superbig\audit\models\AuditModel::EVENT_CREATED_ROUTE => 'Created route',
    \superbig\audit\models\AuditModel::EVENT_DELETED_ROUTE => 'Deleted route',

    // Plugins
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_INSTALLED => 'Plugin installed',
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_UNINSTALLED => 'Plugin uninstalled',
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_DISABLED => 'Plugin disabled',
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_ENABLED => 'Plugin enabled',

    // Users
    \superbig\audit\models\AuditModel::EVENT_USER_ACTIVATED => 'User activated',
    \superbig\audit\models\AuditModel::EVENT_USER_DEACTIVATED => 'User deactivated',
    \superbig\audit\models\AuditModel::EVENT_USER_SUSPENDED => 'User suspended',
    \superbig\audit\models\AuditModel::EVENT_USER_UNSUSPENDED => 'User unsuspended',
    \superbig\audit\models\AuditModel::EVENT_USER_LOCKED => 'User locked',
    \superbig\audit\models\AuditModel::EVENT_USER_UNLOCKED => 'User unlocked',
    \superbig\audit\models\AuditModel::EVENT_USER_GROUPS_ASSIGNED => 'User groups assigned',

    // Permissions
    \superbig\audit\models\AuditModel::EVENT_USER_PERMISSIONS_SAVED => 'User permissions saved',
    \superbig\audit\models\AuditModel::EVENT_GROUP_PERMISSIONS_SAVED => 'Group permissions saved',
    \superbig\audit\models\AuditModel::EVENT_USER_GROUP_CREATED => 'User group created',
    \superbig\audit\models\AuditModel::EVENT_USER_GROUP_SAVED => 'User group saved',
    \superbig\audit\models\AuditModel::EVENT_USER_GROUP_DELETED => 'User group deleted',

    // Schema
    \superbig\audit\models\AuditModel::EVENT_FIELD_CREATED => 'Field created',
    \superbig\audit\models\AuditModel::EVENT_FIELD_SAVED => 'Field saved',
    \superbig\audit\models\AuditModel::EVENT_FIELD_DELETED => 'Field deleted',
    \superbig\audit\models\AuditModel::EVENT_SECTION_CREATED => 'Section created',
    \superbig\audit\models\AuditModel::EVENT_SECTION_SAVED => 'Section saved',
    \superbig\audit\models\AuditModel::EVENT_SECTION_DELETED => 'Section deleted',
    \superbig\audit\models\AuditModel::EVENT_ENTRY_TYPE_CREATED => 'Entry type created',
    \superbig\audit\models\AuditModel::EVENT_ENTRY_TYPE_SAVED => 'Entry type saved',
    \superbig\audit\models\AuditModel::EVENT_ENTRY_TYPE_DELETED => 'Entry type deleted',

    // Settings
    \superbig\audit\models\AuditModel::EVENT_SYSTEM_SETTINGS_CHANGED => 'System settings changed',
    \superbig\audit\models\AuditModel::EVENT_EMAIL_SETTINGS_CHANGED => 'Email settings changed',

    // Backups
    \superbig\audit\models\AuditModel::EVENT_BACKUP_CREATED => 'Backup created',
    \superbig\audit\models\AuditModel::EVENT_BACKUP_RESTORED => 'Backup restored',
];

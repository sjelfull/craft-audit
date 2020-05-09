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
    'Audit plugin loaded'                                       => 'Audit plugin loaded',
    'Error when logging: {error}'                               => 'Error when logging: {error}',

    // Event labels
    \superbig\audit\models\AuditModel::EVENT_SAVED_ELEMENT      => 'Saved element',
    \superbig\audit\models\AuditModel::EVENT_RESAVED_ELEMENTS   => 'Resaved elements',
    \superbig\audit\models\AuditModel::EVENT_CREATED_ELEMENT    => 'Created element',
    \superbig\audit\models\AuditModel::EVENT_DELETED_ELEMENT    => 'Deleted element',
    \superbig\audit\models\AuditModel::EVENT_SAVED_DRAFT        => 'Saved draft',
    \superbig\audit\models\AuditModel::EVENT_CREATED_DRAFT      => 'Created draft',
    \superbig\audit\models\AuditModel::EVENT_DELETED_DRAFT      => 'Created draft',
    \superbig\audit\models\AuditModel::EVENT_SAVED_GLOBAL        => 'Saved global',
    \superbig\audit\models\AuditModel::USER_LOGGED_IN           => 'Logged in',
    \superbig\audit\models\AuditModel::USER_LOGGED_OUT          => 'Logged out',

    // Routes
    \superbig\audit\models\AuditModel::EVENT_SAVED_ROUTE        => 'Saved route',
    \superbig\audit\models\AuditModel::EVENT_CREATED_ROUTE      => 'Created route',
    \superbig\audit\models\AuditModel::EVENT_DELETED_ROUTE      => 'Deleted route',

    // Plugins
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_INSTALLED   => 'Plugin installed',
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_UNINSTALLED => 'Plugin uninstalled',
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_DISABLED    => 'Plugin disabled',
    \superbig\audit\models\AuditModel::EVENT_PLUGIN_ENABLED     => 'Plugin enabled',
];

<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2026 Superbig
 */

namespace superbig\audit\enums;

use Craft;

/**
 * AuditEvent — canonical list of audit event types.
 *
 * Backed by the same kebab-case strings historically used in
 * {@see \superbig\audit\models\AuditModel} `EVENT_*` constants so that
 * existing database rows continue to resolve.
 *
 * New code should prefer this enum over the string constants; the constants
 * remain for backward compatibility and are deprecated.
 *
 * @since 3.0.0
 */
enum AuditEvent: string
{
    // Element events
    case SavedElement = 'saved-element';
    case ResavedElements = 'resaved-elements';
    case CreatedElement = 'created-element';
    case DeletedElement = 'deleted-element';

    // Entry events
    case EntryCreated = 'entry-created';
    case EntrySaved = 'entry-saved';
    case EntryDeleted = 'entry-deleted';

    // Global events
    case SavedGlobal = 'saved-global';

    // Draft events
    case SavedDraft = 'saved-draft';
    case CreatedDraft = 'created-draft';
    case DeletedDraft = 'deleted-draft';

    // Route events
    case CreatedRoute = 'created-route';
    case SavedRoute = 'saved-route';
    case DeletedRoute = 'deleted-route';

    // User (session) events
    case UserLoggedOut = 'user-logged-out';
    case UserLoggedIn = 'user-logged-in';

    // Plugin events
    case PluginInstalled = 'installed-plugin';
    case PluginUninstalled = 'uninstalled-plugin';
    case PluginDisabled = 'disabled-plugin';
    case PluginEnabled = 'enabled-plugin';

    // User security events
    case UserActivated = 'user-activated';
    case UserDeactivated = 'user-deactivated';
    case UserSuspended = 'user-suspended';
    case UserUnsuspended = 'user-unsuspended';
    case UserLocked = 'user-locked';
    case UserUnlocked = 'user-unlocked';
    case UserGroupsAssigned = 'user-groups-assigned';

    // Permission events
    case UserPermissionsSaved = 'user-permissions-saved';
    case GroupPermissionsSaved = 'group-permissions-saved';
    case UserGroupCreated = 'user-group-created';
    case UserGroupSaved = 'user-group-saved';
    case UserGroupDeleted = 'user-group-deleted';

    // Schema events
    case FieldCreated = 'field-created';
    case FieldSaved = 'field-saved';
    case FieldDeleted = 'field-deleted';
    case SectionCreated = 'section-created';
    case SectionSaved = 'section-saved';
    case SectionDeleted = 'section-deleted';
    case EntryTypeCreated = 'entry-type-created';
    case EntryTypeSaved = 'entry-type-saved';
    case EntryTypeDeleted = 'entry-type-deleted';

    // Settings events
    case SystemSettingsChanged = 'system-settings-changed';
    case EmailSettingsChanged = 'email-settings-changed';

    // Database events
    case BackupCreated = 'backup-created';
    case BackupRestored = 'backup-restored';

    /**
     * Human-readable, translated label for this event.
     *
     * Uses the backing string value as the translation key so existing
     * translation files continue to work unchanged.
     */
    public function label(): string
    {
        return Craft::t('audit', $this->value);
    }

    /**
     * Safe cast from a nullable string to the enum.
     *
     * Returns null if the value is null, empty, or doesn't match any case —
     * never throws. Useful when reading untrusted/legacy data from the DB.
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}

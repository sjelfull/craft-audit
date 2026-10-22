<?php

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
    case UserLoginFailed = 'user-login-failed';
    case UserImpersonated = 'user-impersonated';
    case UserPasswordChanged = 'user-password-changed';
    case UserEmailChanged = 'user-email-changed';
    case UserMfaEnabled = 'user-mfa-enabled';
    case UserMfaDisabled = 'user-mfa-disabled';
    case UserPasskeyAdded = 'user-passkey-added';
    case UserPasskeyRemoved = 'user-passkey-removed';

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

    // Project Config events (Branch 4)

    // Category Groups
    case CategoryGroupCreated = 'category-group-created';
    case CategoryGroupSaved = 'category-group-saved';
    case CategoryGroupDeleted = 'category-group-deleted';

    // Tag Groups
    case TagGroupCreated = 'tag-group-created';
    case TagGroupSaved = 'tag-group-saved';
    case TagGroupDeleted = 'tag-group-deleted';

    // Filesystems
    case FilesystemCreated = 'filesystem-created';
    case FilesystemSaved = 'filesystem-saved';
    case FilesystemDeleted = 'filesystem-deleted';

    // Image Transforms
    case ImageTransformCreated = 'image-transform-created';
    case ImageTransformSaved = 'image-transform-saved';
    case ImageTransformDeleted = 'image-transform-deleted';

    // Sites
    case SiteCreated = 'site-created';
    case SiteSaved = 'site-saved';
    case SiteDeleted = 'site-deleted';

    // Site Groups
    case SiteGroupCreated = 'site-group-created';
    case SiteGroupSaved = 'site-group-saved';
    case SiteGroupDeleted = 'site-group-deleted';

    // Volumes
    case VolumeCreated = 'volume-created';
    case VolumeSaved = 'volume-saved';
    case VolumeDeleted = 'volume-deleted';

    // Global Sets (config)
    case GlobalSetConfigCreated = 'global-set-config-created';
    case GlobalSetConfigSaved = 'global-set-config-saved';
    case GlobalSetConfigDeleted = 'global-set-config-deleted';

    // Batch lifecycle (P3.1)
    case BatchStarted = 'batch-started';
    case BatchCompleted = 'batch-completed';
    case BatchFailed = 'batch-failed';

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

    public function kind(): AuditEventKind
    {
        return match ($this) {
            self::SavedElement, self::ResavedElements, self::CreatedElement, self::DeletedElement,
            self::EntryCreated, self::EntrySaved, self::EntryDeleted, self::SavedGlobal,
            self::SavedDraft, self::CreatedDraft, self::DeletedDraft => AuditEventKind::Content,

            self::UserLoggedIn, self::UserLoggedOut, self::UserLoginFailed, self::UserImpersonated,
            self::UserPasswordChanged, self::UserEmailChanged, self::UserMfaEnabled, self::UserMfaDisabled,
            self::UserPasskeyAdded, self::UserPasskeyRemoved => AuditEventKind::Security,

            self::UserActivated, self::UserDeactivated, self::UserSuspended, self::UserUnsuspended,
            self::UserLocked, self::UserUnlocked, self::UserGroupsAssigned => AuditEventKind::Users,

            self::UserPermissionsSaved, self::GroupPermissionsSaved, self::UserGroupCreated,
            self::UserGroupSaved, self::UserGroupDeleted => AuditEventKind::Permissions,

            self::FieldCreated, self::FieldSaved, self::FieldDeleted,
            self::SectionCreated, self::SectionSaved, self::SectionDeleted,
            self::EntryTypeCreated, self::EntryTypeSaved, self::EntryTypeDeleted,
            self::CategoryGroupCreated, self::CategoryGroupSaved, self::CategoryGroupDeleted,
            self::TagGroupCreated, self::TagGroupSaved, self::TagGroupDeleted,
            self::FilesystemCreated, self::FilesystemSaved, self::FilesystemDeleted,
            self::ImageTransformCreated, self::ImageTransformSaved, self::ImageTransformDeleted,
            self::SiteCreated, self::SiteSaved, self::SiteDeleted,
            self::SiteGroupCreated, self::SiteGroupSaved, self::SiteGroupDeleted,
            self::VolumeCreated, self::VolumeSaved, self::VolumeDeleted,
            self::GlobalSetConfigCreated, self::GlobalSetConfigSaved, self::GlobalSetConfigDeleted => AuditEventKind::Schema,

            self::SystemSettingsChanged, self::EmailSettingsChanged,
            self::CreatedRoute, self::SavedRoute, self::DeletedRoute => AuditEventKind::Settings,

            self::PluginInstalled, self::PluginUninstalled, self::PluginEnabled, self::PluginDisabled => AuditEventKind::Plugins,

            self::BackupCreated, self::BackupRestored => AuditEventKind::Database,

            self::BatchStarted, self::BatchCompleted, self::BatchFailed => AuditEventKind::Batches,
        };
    }

    /**
     * Colour hint for the CP: 'positive', 'negative', 'warning' or 'neutral'.
     */
    public function tone(): string
    {
        $words = explode('-', $this->value);

        return match (true) {
            (bool)array_intersect($words, ['failed', 'impersonated', 'locked', 'suspended']) => 'warning',
            (bool)array_intersect($words, ['deleted', 'uninstalled', 'removed', 'disabled', 'deactivated']) => 'negative',
            (bool)array_intersect($words, ['created', 'installed', 'added', 'enabled', 'activated', 'restored']) => 'positive',
            default => 'neutral',
        };
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

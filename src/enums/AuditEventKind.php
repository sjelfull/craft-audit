<?php

namespace superbig\audit\enums;

use Craft;

/**
 * Broad groups of events, used to filter the log.
 */
enum AuditEventKind: string
{
    case Content = 'content';
    case Security = 'security';
    case Users = 'users';
    case Permissions = 'permissions';
    case Schema = 'schema';
    case Settings = 'settings';
    case Plugins = 'plugins';
    case Database = 'database';
    case Batches = 'batches';

    public function label(): string
    {
        return match ($this) {
            self::Content => Craft::t('audit', 'Content'),
            self::Security => Craft::t('audit', 'Sign-ins & security'),
            self::Users => Craft::t('audit', 'User accounts'),
            self::Permissions => Craft::t('audit', 'Permissions'),
            self::Schema => Craft::t('audit', 'Schema'),
            self::Settings => Craft::t('audit', 'Settings & routes'),
            self::Plugins => Craft::t('audit', 'Plugins'),
            self::Database => Craft::t('audit', 'Database'),
            self::Batches => Craft::t('audit', 'Batches'),
        };
    }

    /**
     * @return AuditEvent[]
     */
    public function events(): array
    {
        return array_values(array_filter(AuditEvent::cases(), fn(AuditEvent $event) => $event->kind() === $this));
    }
}

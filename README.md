# Audit plugin for Craft CMS 5

Enterprise-grade audit logging for Craft CMS. Track every change, login, and system event — then send your logs anywhere.

![Plugin icon](resources/img/icon.png)

_$99.00 through the [Craft Plugin Store](https://plugins.craftcms.com/audit) for production use._

![Screenshot of index view](resources/screenshots/audit-index.png)

## What It Does

Audit automatically logs actions performed by authenticated users:

- **Content** — Creating, saving, and deleting entries, assets, users, globals, and Commerce products/variants
- **Users** — Logins, logouts, account activation/deactivation, suspension, locking, group assignments
- **Permissions** — User and group permission changes
- **Schema** — Field, section, and entry type changes
- **System** — Plugin installs/uninstalls, route changes, database backups/restores, settings changes

No configuration required — install the plugin and logging starts immediately.

## Requirements

- Craft CMS 5.5+
- PHP 8.2+

## Installation

```bash
cd /path/to/project
composer require superbig/craft-audit
```

Then go to **Settings → Plugins** in the Control Panel and click **Install** for Audit.

## Screenshots

![Screenshot of index view](resources/screenshots/audit-index.png)

![Screenshot of details view](resources/screenshots/audit-details.png)

---

## Configuration

Create a `config/audit.php` file to customize behavior:

```php
<?php

return [
    // How many days to keep log entries (default: 30)
    'pruneDays' => 30,

    // Master switch for logging
    'enabled' => true,

    // Toggle event categories
    'logElementEvents'       => true,
    'logChildElementEvents'  => false,
    'logDraftEvents'         => false,
    'logPluginEvents'        => true,
    'logUserEvents'          => true,
    'logRouteEvents'         => true,
    'logUserSecurityEvents'  => true,
    'logPermissionEvents'    => true,
    'logSchemaEvents'        => true,
    'logDatabaseEvents'      => true,

    // Auto-prune on admin CP requests
    'pruneRecordsOnAdminRequests' => false,

    // Geolocation (requires MaxMind license)
    'enabledGeolocation' => true,
    'maxmindAccountId'   => '',
    'maxmindLicenseKey'  => '',
    'dbPath'             => '',
];
```

This file supports [multi-environment config](https://craftcms.com/docs/5.x/configure.html#multi-environment-configs), so you can have different settings per environment.

### Event Categories

| Setting | What It Logs | Default |
|---------|-------------|---------|
| `logElementEvents` | Entry/asset/user create, save, delete | `true` |
| `logChildElementEvents` | Child element changes (e.g., Matrix blocks) | `false` |
| `logDraftEvents` | Draft creation and saves | `false` |
| `logPluginEvents` | Plugin install, uninstall, enable, disable | `true` |
| `logUserEvents` | Login, logout | `true` |
| `logRouteEvents` | Route create, save, delete | `true` |
| `logUserSecurityEvents` | Account activate, deactivate, suspend, lock | `true` |
| `logPermissionEvents` | User/group permission changes | `true` |
| `logSchemaEvents` | Field, section, entry type changes | `true` |
| `logDatabaseEvents` | Database backup and restore | `true` |

---

## Console Commands

### Prune Old Logs

Remove log entries older than `pruneDays`:

```bash
./craft audit/default/prune-logs
```

### Update Geolocation Database

Download the latest MaxMind GeoLite2 database:

```bash
./craft audit/default/update-database
```

---

## Geolocation

Audit can enrich log entries with geographic data using [MaxMind GeoLite2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) databases.

### Setup

1. Create a free account at [MaxMind.com](https://www.maxmind.com/en/geolite2/signup)
2. Generate a [license key](https://www.maxmind.com/en/accounts/current/license-key)
3. Add your credentials to `config/audit.php`:

```php
return [
    'enabledGeolocation' => true,
    'maxmindAccountId'   => getenv('MAXMIND_ACCOUNT_ID'),
    'maxmindLicenseKey'  => getenv('MAXMIND_LICENSE_KEY'),
];
```

4. Download the database:

```bash
./craft audit/default/update-database
```

---

## Tracked Events

Event types are defined by the `AuditEvent` enum (`src/enums/AuditEvent.php`). Values stored in the database are kebab-case strings (e.g. `entry-saved`). Prefer `AuditEvent::EntrySaved` (or `->value`) in new code.

### Content Events

| Event | Value | Description |
|-------|-------|-------------|
| Entry created | `entry-created` | New entry saved for the first time |
| Entry saved | `entry-saved` | Existing entry updated |
| Entry deleted | `entry-deleted` | Entry removed |
| Element created | `created-element` | Any element (asset, user, etc.) created |
| Element saved | `saved-element` | Any element updated |
| Element deleted | `deleted-element` | Any element removed |
| Global set saved | `saved-global` | Global set content updated |
| Draft created | `created-draft` | New draft created |
| Draft saved | `saved-draft` | Draft updated |
| Draft deleted | `deleted-draft` | Draft removed |
| Elements resaved | `resaved-elements` | Bulk resave operation (batch parent) |

### User Events

| Event | Value | Description |
|-------|-------|-------------|
| User logged in | `user-logged-in` | Successful login |
| User logged out | `user-logged-out` | Logout |
| User activated | `user-activated` | Account activated |
| User deactivated | `user-deactivated` | Account deactivated |
| User suspended | `user-suspended` | Account suspended |
| User unsuspended | `user-unsuspended` | Suspension lifted |
| User locked | `user-locked` | Account locked (too many failed logins) |
| User unlocked | `user-unlocked` | Account unlocked |
| Groups assigned | `user-groups-assigned` | User assigned to groups |

### Permission Events

| Event | Value | Description |
|-------|-------|-------------|
| User permissions saved | `user-permissions-saved` | Individual user permissions changed |
| Group permissions saved | `group-permissions-saved` | Group-level permissions changed |
| User group created | `user-group-created` | New user group |
| User group saved | `user-group-saved` | User group updated |
| User group deleted | `user-group-deleted` | User group removed |

### Schema Events

| Event | Value | Description |
|-------|-------|-------------|
| Field created | `field-created` | New field added |
| Field saved | `field-saved` | Field settings updated |
| Field deleted | `field-deleted` | Field removed |
| Section created | `section-created` | New section added |
| Section saved | `section-saved` | Section settings updated |
| Section deleted | `section-deleted` | Section removed |
| Entry type created | `entry-type-created` | New entry type added |
| Entry type saved | `entry-type-saved` | Entry type updated |
| Entry type deleted | `entry-type-deleted` | Entry type removed |

### Project Config Events

| Event | Value | Description |
|-------|-------|-------------|
| Category / tag groups | `category-group-*`, `tag-group-*` | Create, save, delete |
| Filesystems / volumes | `filesystem-*`, `volume-*` | Create, save, delete |
| Image transforms | `image-transform-*` | Create, save, delete |
| Sites / site groups | `site-*`, `site-group-*` | Create, save, delete |
| Global set config | `global-set-config-*` | Create, save, delete |

### System & Batch Events

| Event | Value | Description |
|-------|-------|-------------|
| Plugin installed | `installed-plugin` | Plugin installed |
| Plugin uninstalled | `uninstalled-plugin` | Plugin removed |
| Plugin enabled | `enabled-plugin` | Plugin activated |
| Plugin disabled | `disabled-plugin` | Plugin deactivated |
| Route created | `created-route` | URL route added |
| Route saved | `saved-route` | URL route updated |
| Route deleted | `deleted-route` | URL route removed |
| Backup created | `backup-created` | Database backup made |
| Backup restored | `backup-restored` | Database backup restored |
| System settings changed | `system-settings-changed` | System config updated |
| Email settings changed | `email-settings-changed` | Email config updated |
| Batch started | `batch-started` | Bulk operation opened |
| Batch completed | `batch-completed` | Bulk operation finished |
| Batch failed | `batch-failed` | Bulk operation failed |

---

## Extending Audit

Audit exposes a small set of Yii events on the recording pipeline. Prefer these over listening to Craft element events yourself when you only need to filter or enrich audit rows.

### Events Reference

| Event | Class | Cancelable | Description |
|-------|-------|:----------:|-------------|
| `EVENT_BEFORE_RECORD` | `AuditRecorder` | ✓ | Modify or cancel a row before it is saved |
| `EVENT_SNAPSHOT` | `AuditRecorder` | — | Modify snapshot data attached to a row |
| `EVENT_REGISTER_HANDLERS` | `FieldHandlerRegistry` | — | Register custom field diff handlers |
| `EVENT_BATCH_STARTED` | `BatchService` | — | React when a batch opens |
| `EVENT_BATCH_ENDED` | `BatchService` | — | React when a batch completes or fails |

> Concepts and recipes: [docs/concepts/events-and-recording.md](docs/concepts/events-and-recording.md) and [docs/how-to/filter-records.md](docs/how-to/filter-records.md).

### Cancel or Filter Logging

Use `EVENT_BEFORE_RECORD` to cancel or modify entries before they're saved:

```php
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        // Skip audit for bot traffic
        if (str_contains($event->model->userAgent ?? '', 'Googlebot')) {
            $event->isValid = false;
        }
    }
);
```

### Filter by Element Type

```php
use craft\elements\Asset;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        if ($event->model->elementType === Asset::class) {
            $event->isValid = false;
        }
    }
);
```

### Modifying Snapshots

Use `EVENT_SNAPSHOT` to add custom data to audit log snapshots:

```php
use superbig\audit\events\SnapshotEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_SNAPSHOT,
    function (SnapshotEvent $event) {
        $event->snapshot['customField'] = 'custom value';
    }
);
```

### Record Custom Events

```php
use superbig\audit\Audit;

Audit::$plugin->auditRecorder->record(
    'order-completed', // or an AuditEvent case
    title: 'Order #1234',
    snapshot: ['orderId' => 1234],
);
```

### Permissions

Audit registers two permissions you can assign to user groups:

| Permission | Handle | Description |
|-----------|--------|-------------|
| View audit logs | `audit-view-logs` | Access the Audit CP section |
| Clear old logs | `audit-clear-logs` | Prune old log entries |

### Template Variables

Access audit data in your Twig templates:

```twig
{# Get events for a specific element #}
{% set events = craft.audit.getEventsForElement(entry) %}

{# Loop through events #}
{% for event in events %}
    {{ event.event }} by {{ event.user }} at {{ event.dateCreated|date }}
{% endfor %}
```

---

## Upgrading

### From Craft 4 (Audit 3.x) to Craft 5 (Audit 5.0.0)

- **Craft CMS 5.5+** and **PHP 8.2+** are required
- Run `composer require superbig/craft-audit:^5.0` (or `composer update superbig/craft-audit`) after upgrading Craft
- Run migrations (`php craft migrate/all` or **Utilities → Migrations**). This converts `snapshot` / `location` to JSON, adds `changedFields` and `request`, and creates new indexes. Existing audit rows are preserved.
- Prefer `AuditEvent` enum cases (or kebab-case strings such as `entry-created`) instead of the removed `AuditModel::EVENT_*` constants
- Extension hooks live on `AuditRecorder` (`EVENT_BEFORE_RECORD`, `EVENT_SNAPSHOT`), not the old `AuditService::EVENT_BEFORE_LOG` / `EVENT_DEFINE_SHOULD_LOG` names from earlier drafts

---

## Support

- [GitHub Issues](https://github.com/sjelfull/craft-audit/issues)
- [Craft Plugin Store](https://plugins.craftcms.com/audit)

## Credits

- [Auditing icon by Ralf Schmitzer](https://thenounproject.com/term/auditing/960985)

Brought to you by [Superbig](https://superbig.co)

# How to filter records before they save

Your audit log is drowning. A nightly Feed Me import wrote 10,000 rows. A resave job ran on every product. The one change you care about is buried on page 47. Every entry save still produces a record, and filtering the UI after the fact doesn't help when the signal is gone.

`BeforeRecordEvent` is the escape hatch. It fires inside `AuditRecorder::record()` immediately before the row is written. Your listener can cancel the record, mutate the model in place, or tag the snapshot with your own data.

## The hook

One listener. Drops every record. Useful only as a sanity check that the hook is wired up:

```php
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        $event->isValid = false;
    }
);
```

Two things to know:

- `$event->model` is the `AuditModel` instance about to be persisted. Its properties are the row-to-be: `event`, `title`, `userId`, `elementType`, `elementId`, `request`, `snapshot` (array), `changedFields` (array), `ip`, `userAgent`, `siteId`, `sessionId`.
- `$event->isValid = false` cancels the save. The row is never written. `AuditRecorder::record()` returns `null` instead of the model. No exception is thrown.

Mutations to `$event->model` stick. Set `$event->model->snapshot['tenant'] = 'acme'` and that key lands in the DB.

## Recipes

### Drop records from console requests

Feed Me, `php craft resave/entries`, cron-driven imports, and any custom console script all run in a console request. The `request` field on the model captures that: `cp`, `site`, `console`, or `yaml`. If your operators only care about what humans do in the control panel, drop the console writes outright.

```php
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        if ($event->model->request === 'console') {
            $event->isValid = false;
        }
    }
);
```

Two warnings. First, this drops *all* console writes — including legitimate ad-hoc ones you run by hand. If you want a narrower filter, pair `request === 'console'` with `event === 'entry-saved'` or an `elementType` check. Second, `yaml` is a separate source for project config applies; leave it alone unless you really want to lose schema-change history.

### Drop records for specific event types

Your security team cares about logins. Your content team cares about entry saves. They don't need each other's noise. The `event` field is a kebab-case string; the canonical list lives in the `AuditEvent` enum.

```php
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        $drop = [
            AuditEvent::UserLoggedIn->value,
            AuditEvent::UserLoggedOut->value,
        ];

        if (in_array($event->model->event, $drop, true)) {
            $event->isValid = false;
        }
    }
);
```

Use the enum's `->value` to compare — that's the backing string that matches `$event->model->event`. If you prefer, compare against the enum directly via `$event->model->eventEnum`, which is set when the event string matches a known case.

### Drop records from a service account

You have a `webhook-bot` user that writes entries from incoming webhooks. Its writes aren't interesting; the upstream system already logs them. Filter by user ID.

```php
use craft\elements\User;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        $botUser = User::find()->username('webhook-bot')->one();

        if ($botUser && $event->model->userId === $botUser->id) {
            $event->isValid = false;
        }
    }
);
```

The `User::find()` lookup runs once per audit record, which is fine for a handful of saves and wasteful under a batch. Cache the ID in the listener's closure (or resolve it once in `init()` and capture it) if you're running this under Feed Me.

### Redact sensitive fields from the snapshot

A custom field holds an API key. Another holds a `passwordHash`. Neither belongs in `audit_log`. Keep the record — you still want to know a write happened — but strip the values before the row is written.

```php
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        $secret = ['passwordHash', 'secretApiKey', 'stripeSecretKey'];

        if (!empty($event->model->snapshot['content'])) {
            foreach ($secret as $key) {
                if (array_key_exists($key, $event->model->snapshot['content'])) {
                    $event->model->snapshot['content'][$key] = '[redacted]';
                }
            }
        }

        // Also clear these from changedFields so the diff UI doesn't leak them.
        foreach ($secret as $key) {
            unset($event->model->changedFields[$key]);
        }
    }
);
```

The element handler stores serialized field values under `snapshot.content`. That's where your Craft field values live. Project config events store their payload under `snapshot.config` instead — adjust the path if you're redacting config values.

### Tag records with custom metadata

Multi-tenant install, or you run one Craft with `staging` and `production` switched by env. Tag every record with the tenant ID or environment so you can filter later. Mutations to `$event->model->snapshot` are persisted as part of the row.

```php
use Craft;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        $event->model->snapshot['tenant'] = Craft::parseEnv('$TENANT_ID');
        $event->model->snapshot['environment'] = Craft::$app->env;
    }
);
```

The `audit_log.snapshot` column is JSON. Your tagged keys survive alongside whatever Audit wrote. Querying by them requires JSON operators (`JSON_EXTRACT` on MySQL, `->>` on Postgres) — possible, not cheap. If you need fast lookup, pair tagging with a structured filter in the CP index via a custom column later.

### Filter to only elements in a specific section

You're running a migration on the `homepage-hero` section and want audit coverage for those entries only. Everything else is noise for the duration. Section info sits in the snapshot under `sectionHandle`, set by `ElementHandler` when the element is an entry.

```php
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;

Event::on(
    AuditRecorder::class,
    AuditRecorder::EVENT_BEFORE_RECORD,
    function (BeforeRecordEvent $event) {
        $entryEvents = [
            AuditEvent::EntryCreated->value,
            AuditEvent::EntrySaved->value,
            AuditEvent::EntryDeleted->value,
        ];

        if (!in_array($event->model->event, $entryEvents, true)) {
            return; // not an entry event — leave it alone
        }

        if (($event->model->snapshot['sectionHandle'] ?? null) !== 'homepage-hero') {
            $event->isValid = false;
        }
    }
);
```

The early return matters. Without it you'd drop logins, schema changes, and plugin installs — because they don't have a `sectionHandle`. Scope your filter to the events you actually intend to touch.

## Where to put the listener

`Event::on()` calls need to run during Craft's boot so they're registered before the first save. Three places work:

- A plugin's `init()` method. This is the canonical home for any shipped extension.
- A custom module's `init()` method. Same lifecycle, less ceremony. Good for site-specific rules.
- `config/app.php` via the `bootstrap` array. Good for one-off filters that don't warrant a module.

Here's the module pattern — most sites end up with a `modules/sitemodule` in their Craft install:

```php
namespace modules\sitemodule;

use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\services\AuditRecorder;
use yii\base\Event;
use yii\base\Module;

class SiteModule extends Module
{
    public function init(): void
    {
        parent::init();

        Event::on(
            AuditRecorder::class,
            AuditRecorder::EVENT_BEFORE_RECORD,
            function (BeforeRecordEvent $event) {
                if ($event->model->request === 'console') {
                    $event->isValid = false;
                }
            }
        );
    }
}
```

If you haven't set up a site module yet, the [Craft CMS module documentation](https://craftcms.com/docs/5.x/extend/module-guide.html) covers the scaffolding.

## Limitations

`BeforeRecordEvent` is a per-record hook. That shapes what it can and can't do.

- **Fires per record.** A 10,000-entry Feed Me import fires your listener 10,000 times. Each call skips the DB write when you cancel, which is the dominant cost — but the listener itself runs. Keep filter logic cheap. No `User::find()` inside the hot path without caching.
- **Can't batch or group records.** This hook doesn't know a batch is happening. If you want a single summary row instead of 10,000 children, that's a separate piece of work. See [events-and-recording](../concepts/events-and-recording.md) for the roadmap.
- **Runs synchronously.** Every save blocks on your listener. Heavy work (HTTP calls, large DB reads) slows every element save in the request.
- **Can't rewrite the event type.** Setting `$event->model->event = 'bulk-import-saved'` works, but the CP index view filters, labels, and diff rendering all key off known event strings. A rewritten event will render as an unknown type. Use this for filtering, not for reshaping.
- **Snapshot mutations happen in-memory.** If your listener throws after mutating `$event->model->snapshot`, there's no rollback and no partial-write. The record is never persisted; the exception propagates up through `AuditRecorder::record()`. Wrap risky work in `try`/`catch` if you need to fail soft.
- **Doesn't fire for records that were never built.** Audit skips propagation saves, revisions, and (by default) drafts inside the element handler, before `AuditRecorder::record()` is called. Your listener never sees those. That's usually what you want; call it out if you were planning to re-enable them via this hook.

## Related

- [Events and recording](../concepts/events-and-recording.md) — when Audit fires a record, and when it doesn't.
- `docs/reference/events.md` — full `BeforeRecordEvent` reference. Lands in P3.1.

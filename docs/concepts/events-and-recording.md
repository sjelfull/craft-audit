# Events and recording

Audit records events. That's a short sentence and it hides everything a serious user wants to know.

When *exactly* does Audit fire a record? What's actually in a "record"? Can you intercept one before it writes? Does the plugin batch, or is every save a row? These aren't edge-case questions — they're the first questions from anyone planning to extend the plugin, filter its output, or route records to another system.

This page walks through the recording pipeline: from the Craft event that triggers a write, through the handler that builds the model, to the row in `audit_log`. It's a conceptual view — no tasks, no recipes. Use it to orient yourself, then jump to a how-to or reference when you need to do something.

## The recording pipeline

Every audit row travels the same path, regardless of whether it started as an entry save, a user login, or a project config change:

```
Craft event fires
    (Element::EVENT_AFTER_SAVE, User::EVENT_AFTER_LOGIN,
     ProjectConfig onAdd, etc.)
        ↓
Audit handler receives it
    (ElementHandler, UserHandler, SchemaHandler, ...)
        ↓
Handler builds an AuditModel
    (actor, timestamp, request context, snapshot, changed fields)
        ↓
AuditRecorder::record() is called
        ↓
BeforeRecordEvent fires
    (your chance to cancel or mutate)
        ↓
If not cancelled, AuditRecord is persisted to audit_log
```

Each step has a specific job.

**The Craft event** is whatever signal Craft itself emits: `Elements::EVENT_AFTER_SAVE_ELEMENT`, `User::EVENT_AFTER_LOGIN`, `Fields::EVENT_AFTER_SAVE_FIELD`, and so on. For config areas where Craft has no PHP event (filesystems, image transforms, volumes, sites, category groups, tag groups, global sets), Audit listens on project config paths directly — `onAdd`, `onUpdate`, `onRemove`.

**The Audit handler** is one of eight small services under `src/services/handlers/` — `ElementHandler`, `UserHandler`, `UserGroupHandler`, `SchemaHandler`, `RouteHandler`, `BackupHandler`, `PluginHandler`, `SettingsHandler`. Each one owns a thin slice of Craft's event surface. It decides whether the event is worth recording (skipping propagation saves, revisions, disabled event types), extracts what matters from the payload, and builds an `AuditModel`.

**`AuditRecorder::record()`** is the single entry point for persistence. Every handler eventually calls it. It hydrates the request context (user, session, site, IP, user agent), detects the request source (`cp`, `site`, `console`, `yaml`), fires `BeforeRecordEvent`, and — if the event wasn't cancelled — writes one row to `audit_log`.

**`BeforeRecordEvent`** is the extension seam. It runs on every record, carries the fully-built `AuditModel`, and is cancellable. This is where user code filters, mutates, or tags records. See [how to filter records](../how-to/filter-records.md) for recipes.

**The write** is a single INSERT into `audit_log`. Failures are warned, not thrown — a broken audit insert doesn't crash the request that triggered it.

## What gets captured

Every row in `audit_log` has the same four groups of data. This is what makes Audit more than a line in the Craft log.

**Actor.** Who did it. `userId`, `sessionId`, and `dateCreated`. The `users` FK uses `SET NULL` on delete, so deleting a user doesn't orphan their audit trail — the record survives with `userId = NULL` and whatever context it captured at write time.

**Request context.** Where and how it happened. `ip`, `userAgent`, `location` (JSON-encoded GeoIP payload, when MaxMind credentials are configured), and `request` — one of `cp`, `site`, `console`, or `yaml`. This is the block that separates Audit from the Craft log. The Craft log tells you `webuser` saved an entry. Audit tells you `webuser` saved an entry from `94.228.41.12` in Oslo via a console job.

**Subject.** What changed. `elementId`, `elementType`, `event` (the kebab-case string, e.g. `entry-saved`), `title` (human-readable label), and `parentId` for grouped records — currently used by the `ResaveElements` job to collapse thousands of children under one summary row.

**Snapshot and changed fields.** The payload. `snapshot` is the full element state as JSON, built by the relevant handler. For entry saves, the snapshot includes `content` (Craft's `getSerializedFieldValues()` output), `sectionName`, `elementTypeLabel`, and a title. For project config events, it includes the config path and the raw `newValue`/`oldValue`. `changedFields` is a per-field diff, produced by `FieldDiffService` from the registered field handlers — what changed, handler-typed, rendered later by `DiffRenderer`.

All four groups are set on the `AuditModel` before `BeforeRecordEvent` fires. Your listener sees the full row, not a stub.

## When a record is (and isn't) fired

This is the question support tickets keep asking. The short version:

### Fired

- **Element saves and deletes** — entries, categories, assets, users, globals, and any other element type Craft emits `EVENT_AFTER_SAVE_ELEMENT` for.
- **User lifecycle** — login, logout, activate, deactivate, suspend, unsuspend, lock, unlock, and group assignment.
- **Permission changes** — per user and per group. User groups created, saved, and deleted.
- **Schema changes** — fields, sections, and entry types (create, save, delete).
- **Project config changes** — filesystems, volumes, image transforms, sites, site groups, category groups, tag groups, and global sets. Audit listens via `ProjectConfig::onAdd`, `onUpdate`, and `onRemove` because Craft doesn't emit PHP events for these areas directly.
- **Settings changes** — system settings and email settings, via `ProjectConfig::EVENT_UPDATE_ITEM` scoped to `system.*` and `email.*` paths.
- **Plugin lifecycle** — install, uninstall, enable, disable.
- **Routes** — saves and deletes of routes defined in the control panel.
- **Database backups** — created and restored.

### Not fired

- **Element propagation saves.** Audit detects these via `$element->propagating` and skips. A multi-site entry save emits one save per site; only the initial one is recorded.
- **Resave-job children.** `$element->resaving` is also short-circuited in `ElementHandler::onSaveElement()`. The parent `ResaveElements` job gets one summary row via `onBeforeResave` / `onResaveEnd`; the individual element saves are skipped. Feed Me is a different story — see the roadmap below.
- **Revisions.** `ElementHelper::rootElement()` walks to the canonical element. If it's a revision, Audit returns early — revisions are Craft's immutable history, separately addressable.
- **Drafts.** Skipped by default. Enable `logDraftEvents` in plugin settings if you need them.
- **Child elements.** Matrix blocks, Neo blocks, and other nested elements. Audit records the root save (the parent entry), and the child content lands inside its `snapshot.content`. You don't get a separate row per block. Enable `logChildElementEvents` if you want per-child rows.
- **Anything Craft doesn't emit.** There's no PHP event for it and no project config path for it? Audit doesn't see it.

The "not fired" list is explicit because "why isn't X in my audit log?" is a recurring question. Most of the time the answer is in that list.

## Customization points

Four hooks cover most extension needs. Each one is documented on its own page.

- **Cancel or mutate records before they save.** Use `BeforeRecordEvent`. Fires on every record, carries the full model, cancellable. See [how to filter records](../how-to/filter-records.md).
- **Register a field handler for a custom field type.** Listen for `FieldHandlerRegistry::EVENT_REGISTER_HANDLERS` and add your handler class to `$event->handlers`. See `docs/how-to/handle-custom-fields.md` (lands in P3.2).
- **Record your own events from a third-party plugin.** Call `Audit::$plugin->auditRecorder->record()` directly. The first argument is an `AuditEvent` enum case or a string; the backing strings match the legacy `AuditModel::EVENT_*` constants, so anything written against v2 keeps working. See `docs/how-to/record-custom-events.md` (lands in P3.2).
- **Query or export records.** The `audit_log` table is indexed on `(dateCreated, event)`, `(userId, dateCreated)`, and the individual columns `userId`, `elementId`, `sessionId`, `parentId`, `dateCreated`, `siteId`, and `event`. Use standard Craft query builder or raw SQL. See `docs/reference/services.md` (lands in P3.1).

## What's on the roadmap (and what it means for you)

Audit records every write individually. That's the right default for compliance, and the wrong default for operators who run nightly imports. The plugin's known gaps — and the work planned to close them — are worth knowing about now.

**Batch grouping.** Today, only `ResaveElements` gets batch treatment (one parent summary row, child rows with `parent_id` set). Feed Me, custom import scripts, and plugin-driven save loops produce one row per element. The plan is a first-class `EVENT_BATCH_STARTED` / `EVENT_BATCH_ENDED` API plus a generalized `parent_id` mechanism, so any code path — your own imports included — can bracket a batch and collapse its output in the UI. Tracking in the [batch-processing analysis](../../analysis/batch-processing-strategies.md); in progress.

**Queue-backed recording.** Currently the audit write is synchronous — every save blocks on the INSERT. For high-volume installs that's a noticeable tax on response time. A queue-backed mode is planned: `AuditRecorder::record()` would build the model, fire `BeforeRecordEvent`, and push a job instead of writing directly. Fidelity preserved, response time reclaimed. Planned, not in flight.

Until both ship, the interim workaround for batch noise is `BeforeRecordEvent`. It's not as clean as grouping — the listener still runs per record — but it's the escape hatch available today. See [how to filter records](../how-to/filter-records.md).

## Related

- [How to filter records](../how-to/filter-records.md) — drop, transform, or tag records with `BeforeRecordEvent`.
- `docs/reference/events.md` — every event type, with example payloads. Lands in P3.1.
- [Batch processing strategies](../../analysis/batch-processing-strategies.md) — the roadmap in detail.

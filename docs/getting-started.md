# Getting started with Audit

In the next five minutes you'll install Audit, record your first audit entry, and read it in the Craft control panel. No config, no scaffolding.

## What you need

- Craft CMS 5.5 or later
- PHP 8.2 or later
- A Craft install you can log in to, with at least one entry you can edit

If you're still setting up Craft itself, start with the [Craft CMS installation guide](https://craftcms.com/docs/5.x/install.html) and come back when you have a working admin login.

## Install

From the root of your Craft project:

```bash
composer require superbig/craft-audit
php craft plugin/install audit
```

Two lines. If you'd rather click, the control panel works: **Settings → Plugins → Audit → Install**.

The install migration creates the `audit_log` table with composite indexes on `(dateCreated, event)` and `(userId, dateCreated)`, and wires up the event listeners. From this point on, every save, login, and schema change is recorded.

## Your first audit record

Log out of Craft. Log back in. Open **Audit** from the control panel sidebar.

<!-- TODO: screenshot — Audit index page showing a fresh "user-logged-in" record -->

You'll see one row: your own login. Click it.

<!-- TODO: screenshot — detail view of the user-logged-in record -->

The detail view shows the full record:

- **Actor and timestamp.** The user who logged in, and when.
- **Request context.** IP, user agent, session ID, and request source (`cp` in this case).
- **Referrer.** Where the request came from.

That's the shape of every record from here on. Every entry save. Every schema change. Every project config edit. Same four groups of data, different payload.

## Make something change

Open any entry in **Entries**. Change the title. Save.

Go back to **Audit**. Refresh. A new row appears at the top: `entry-saved` with the entry's new title. Click through.

<!-- TODO: screenshot — detail view of an entry-saved record with changed-fields panel visible -->

Scroll to the **Changed fields** panel. It shows the old value next to the new one, per field, rendered by Audit's field handlers. For text fields you see a line-by-line diff. For assets and relations, you see the IDs before and after. The full JSON snapshot sits below — click to expand.

That's the core loop. Edit, save, read the record. Now you know what the audit trail looks like when it fires.

## Layer in a field-level diff

Open an entry with a rich-text body. Change a sentence in the middle of the body. Add a line at the end. Save.

Back in **Audit**, open the new record.

<!-- TODO: screenshot — changed-fields panel with before/after body diff -->

The body field now renders as a line-by-line diff: removed lines in red, added lines in green, unchanged context in grey. That's the `RichTextHandler` at work — one of 15 field handlers Audit ships with, one per Craft field type. Plain text, color, date, money, table, Matrix, Neo, SuperTable, Vizy, SEO Framework, options, relations — all diffed in place.

The snapshot still holds the full `getSerializedFieldValues()` output for the entry. The diff is derived data on top, rendered by `DiffRenderer`. You get both: the per-field view for the UI, and the raw JSON for anything else you want to do with it.

If you have a custom field type, Audit doesn't know about it yet — it falls back to a plain-text diff of the serialized value. You can register a handler for it. See `docs/how-to/handle-custom-fields.md` (lands in P3.2).

## What Audit records automatically

Out of the box, with no config:

- Entry, category, asset, user, and global saves and deletes
- User logins, logouts, activations, suspensions, locks, and group assignments
- Permission changes — per user and per group
- Field, section, and entry type changes
- Filesystem, volume, image transform, and site config changes (via project config)
- Plugin installs, uninstalls, enables, and disables
- Console commands and YAML config applies (labeled `console` or `yaml` in the `request` field)
- Database backups created and restored

The full list lives in the `AuditEvent` enum — 69 cases, grouped by area.

## What's next

- Need to know when a record gets filtered out or what the full lifecycle looks like? See [Events and recording](concepts/events-and-recording.md).
- Want to drop records you don't care about (console jobs, service accounts, specific event types)? See [How to filter records](how-to/filter-records.md).
- Want to record events from your own plugin? See `docs/how-to/record-custom-events.md`. Lands in P3.2.

<?php

namespace superbig\audit\services;

use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\events\ElementEvent;
use superbig\audit\Audit;
use superbig\audit\records\AuditRecord;

/**
 * Keeps personal data out of the log: redacts configured handles before rows are saved, and anonymises
 * a user's rows when the user is deleted.
 */
class Privacy extends Component
{
    public const MASK = '[redacted]';
    public const ANONYMISED_TITLE = 'Anonymised user';

    /**
     * @var array<int, array{by: int[], impersonated: int[], about: int[]}> Rows matched before a user was deleted,
     * since a hard delete nulls the foreign keys that find them.
     */
    private array $rowsBeforeDelete = [];

    /**
     * @var array<int, true> Users anonymised in this request, so rows recorded afterwards about them,
     * such as their own deletion, are anonymised as they're written.
     */
    private array $anonymised = [];

    /**
     * @return string[]
     */
    public function redactedHandles(): array
    {
        return Audit::$plugin->getSettings()->getRedactedHandles();
    }

    /**
     * Masks the value of every key named in `$handles`, at any depth.
     */
    public function redactSnapshot(array $data, ?array $handles = null): array
    {
        $handles ??= $this->redactedHandles();
        if (!$handles) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, $handles, true)) {
                $data[$key] = $this->isBlank($value) ? $value : self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->redactSnapshot($value, $handles);
            }
        }

        return $data;
    }

    /**
     * Replaces changes to redacted fields with a marker that records the field changed but not its values.
     */
    public function redactChanges(array $changes, ?array $handles = null): array
    {
        $handles ??= $this->redactedHandles();
        if (!$handles) {
            return $changes;
        }

        foreach ($changes as $key => $change) {
            if (is_string($key) && in_array($key, $handles, true)) {
                $changes[$key] = ['handler' => 'redacted'];
            } elseif (is_array($change)) {
                $changes[$key] = $this->redactChanges($change, $handles);
            }
        }

        return $changes;
    }

    /**
     * Applies the current redact setting to rows already in the log.
     *
     * @return int The number of rows changed
     */
    public function redactExistingRows(): int
    {
        $handles = $this->redactedHandles();
        if (!$handles) {
            return 0;
        }

        $count = 0;
        $query = AuditRecord::find()->where(['or', ['not', ['snapshot' => null]], ['not', ['changedFields' => null]]]);

        foreach ($query->each(200) as $record) {
            /** @var AuditRecord $record */
            $snapshot = is_array($record->snapshot) ? $this->redactSnapshot($record->snapshot, $handles) : $record->snapshot;
            $changes = is_array($record->changedFields) ? $this->redactChanges($record->changedFields, $handles) : $record->changedFields;

            if ($snapshot !== $record->snapshot || $changes !== $record->changedFields) {
                $record->snapshot = $snapshot;
                $record->changedFields = $changes;
                $record->save(false);
                $count++;
            }
        }

        return $count;
    }

    public function onBeforeDeleteElement(ElementEvent $event): void
    {
        if ($event->element instanceof User && $event->element->id && Audit::$plugin->getSettings()->anonymiseDeletedUsers) {
            $this->rowsBeforeDelete[$event->element->id] = $this->matchRows($event->element->id);
        }
    }

    public function onAfterDeleteElement(ElementEvent $event): void
    {
        $userId = $event->element instanceof User ? $event->element->id : null;
        if (!$userId || !array_key_exists($userId, $this->rowsBeforeDelete)) {
            return;
        }

        $before = $this->rowsBeforeDelete[$userId];
        unset($this->rowsBeforeDelete[$userId]);
        $this->anonymised[$userId] = true;

        // The deletion's own row may still be waiting for the transaction to commit.
        Audit::$plugin->auditRecorder->afterWrites(fn() => $this->anonymiseUser($userId, $before));
    }

    /**
     * Removes a user from the log: their name and details on rows about them, and their IP address,
     * browser, location and session on rows they made. The rows themselves are kept.
     *
     * @param array{by?: int[], impersonated?: int[], about?: int[]} $alsoRows Rows matched earlier, for when the
     *                                                                        user's foreign keys are already gone
     * @return int The number of rows changed
     */
    public function anonymiseUser(int $userId, array $alsoRows = []): int
    {
        $rows = $this->matchRows($userId);
        foreach (['by', 'impersonated', 'about'] as $key) {
            $rows[$key] = array_values(array_unique([...$rows[$key], ...($alsoRows[$key] ?? [])]));
        }

        $db = Audit::$plugin->auditRecorder->getDb();
        $table = AuditRecord::tableName();
        $changed = [];

        $update = function(array $columns, array $ids) use ($db, $table, &$changed) {
            if ($ids) {
                $db->createCommand()->update($table, $columns, ['id' => $ids], [], false)->execute();
                array_push($changed, ...$ids);
            }
        };

        $update([
            'userId' => null,
            'ip' => null,
            'userAgent' => null,
            'location' => null,
            'sessionId' => null,
        ], $rows['by']);
        $update(['impersonatorId' => null], $rows['impersonated']);
        $update([
            'title' => self::ANONYMISED_TITLE,
            'snapshot' => null,
            'changedFields' => null,
        ], $rows['about']);

        return count(array_unique($changed));
    }

    /**
     * Anonymises a row about to be written if it involves a user anonymised earlier in this request.
     */
    public function scrubRecord(AuditRecord $record): void
    {
        if (!$this->anonymised) {
            return;
        }

        $about = $record->elementType === User::class && isset($this->anonymised[(int)$record->elementId]);
        $by = isset($this->anonymised[(int)$record->userId]);

        if ($by || ($about && $record->userId === null)) {
            $record->userId = null;
            $record->ip = null;
            $record->userAgent = null;
            $record->location = null;
            $record->sessionId = null;
        }
        if (isset($this->anonymised[(int)$record->impersonatorId])) {
            $record->impersonatorId = null;
        }
        if ($about) {
            $record->title = self::ANONYMISED_TITLE;
            $record->snapshot = null;
            $record->changedFields = null;
        }
    }

    /**
     * @return array{by: int[], impersonated: int[], about: int[]}
     */
    private function matchRows(int $userId): array
    {
        $ids = fn(array $condition) => array_map('intval', (new Query())
            ->select('id')
            ->from(AuditRecord::tableName())
            ->where($condition)
            ->column(Audit::$plugin->auditRecorder->getDb()));

        $about = ['elementId' => $userId, 'elementType' => User::class];

        return [
            // Sign-ins, failed sign-ins and password resets about a user carry that user's IP address too.
            'by' => $ids(['or', ['userId' => $userId], ['and', $about, ['userId' => null]]]),
            'impersonated' => $ids(['impersonatorId' => $userId]),
            'about' => $ids($about),
        ];
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}

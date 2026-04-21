<?php

namespace superbig\audit\services;

use Craft;
use craft\base\Component;
use craft\events\ConfigEvent;
use craft\helpers\Json;
use craft\services\ProjectConfig;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;

/**
 * Declarative project config tracker.
 *
 * Registers listeners on the project config for config areas that Craft lacks
 * dedicated PHP service events for (filesystems, image transforms, sites, etc.).
 * Each config area gets onAdd / onUpdate / onRemove handlers that route to
 * AuditRecorder with the appropriate AuditEvent case.
 *
 * @since 4.0.0
 */
class ProjectConfigTracker extends Component
{
    /**
     * Declarative map: config path → handler spec.
     *
     * Shape: [
     *   'path' => [
     *     'created' => AuditEvent,
     *     'saved' => AuditEvent,
     *     'deleted' => AuditEvent,
     *     'nameField' => 'name',   // which config key to use for the title
     *   ]
     * ]
     */
    protected function configMap(): array
    {
        return [
            ProjectConfig::PATH_CATEGORY_GROUPS => [
                'created' => AuditEvent::CategoryGroupCreated,
                'saved' => AuditEvent::CategoryGroupSaved,
                'deleted' => AuditEvent::CategoryGroupDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_TAG_GROUPS => [
                'created' => AuditEvent::TagGroupCreated,
                'saved' => AuditEvent::TagGroupSaved,
                'deleted' => AuditEvent::TagGroupDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_FS => [
                'created' => AuditEvent::FilesystemCreated,
                'saved' => AuditEvent::FilesystemSaved,
                'deleted' => AuditEvent::FilesystemDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_IMAGE_TRANSFORMS => [
                'created' => AuditEvent::ImageTransformCreated,
                'saved' => AuditEvent::ImageTransformSaved,
                'deleted' => AuditEvent::ImageTransformDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_SITES => [
                'created' => AuditEvent::SiteCreated,
                'saved' => AuditEvent::SiteSaved,
                'deleted' => AuditEvent::SiteDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_SITE_GROUPS => [
                'created' => AuditEvent::SiteGroupCreated,
                'saved' => AuditEvent::SiteGroupSaved,
                'deleted' => AuditEvent::SiteGroupDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_VOLUMES => [
                'created' => AuditEvent::VolumeCreated,
                'saved' => AuditEvent::VolumeSaved,
                'deleted' => AuditEvent::VolumeDeleted,
                'nameField' => 'name',
            ],
            ProjectConfig::PATH_GLOBAL_SETS => [
                'created' => AuditEvent::GlobalSetConfigCreated,
                'saved' => AuditEvent::GlobalSetConfigSaved,
                'deleted' => AuditEvent::GlobalSetConfigDeleted,
                'nameField' => 'name',
            ],
        ];
    }

    /**
     * Register all config listeners. Call this from Audit::init().
     */
    public function register(): void
    {
        $projectConfig = Craft::$app->projectConfig;

        foreach ($this->configMap() as $path => $spec) {
            $fullPath = $path . '.{uid}';

            $projectConfig->onAdd($fullPath, function(ConfigEvent $event) use ($spec) {
                $this->handleAdd($event, $spec);
            });

            $projectConfig->onUpdate($fullPath, function(ConfigEvent $event) use ($spec) {
                $this->handleUpdate($event, $spec);
            });

            $projectConfig->onRemove($fullPath, function(ConfigEvent $event) use ($spec) {
                $this->handleRemove($event, $spec);
            });
        }
    }

    protected function handleAdd(ConfigEvent $event, array $spec): void
    {
        $name = $this->extractName($event->newValue ?? [], $spec['nameField']);
        $uid = $event->tokenMatches[0] ?? null;

        Audit::$plugin->auditRecorder->record(
            event: $spec['created'],
            title: $name,
            snapshot: [
                'uid' => $uid,
                'config' => $event->newValue,
            ],
        );
    }

    protected function handleUpdate(ConfigEvent $event, array $spec): void
    {
        $oldValue = $event->oldValue ?? [];
        $newValue = $event->newValue ?? [];
        $changedFields = $this->diffConfig($oldValue, $newValue);

        // Skip no-op updates (can happen during project config rebuilds)
        if (empty($changedFields)) {
            return;
        }

        $name = $this->extractName($newValue, $spec['nameField'])
             ?? $this->extractName($oldValue, $spec['nameField'])
             ?? 'Unknown';
        $uid = $event->tokenMatches[0] ?? null;

        Audit::$plugin->auditRecorder->record(
            event: $spec['saved'],
            title: $name,
            snapshot: [
                'uid' => $uid,
                'config' => $newValue,
            ],
            changedFields: $changedFields,
        );
    }

    protected function handleRemove(ConfigEvent $event, array $spec): void
    {
        $name = $this->extractName($event->oldValue ?? [], $spec['nameField']) ?? 'Unknown';
        $uid = $event->tokenMatches[0] ?? null;

        Audit::$plugin->auditRecorder->record(
            event: $spec['deleted'],
            title: $name,
            snapshot: [
                'uid' => $uid,
                'config' => $event->oldValue,
            ],
        );
    }

    /**
     * Extract a descriptive name from the config.
     */
    protected function extractName(array $config, string $nameField): ?string
    {
        $value = $config[$nameField] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Diff two config arrays. Returns a map of key → {handler, from, to}
     * in the same format as FieldDiffService for UI consistency.
     */
    public function diffConfig(array $old, array $new): array
    {
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($this->configValuesEqual($oldVal, $newVal)) {
                continue;
            }

            $changes[$key] = [
                'handler' => 'config',
                'from' => $oldVal,
                'to' => $newVal,
            ];
        }

        return $changes;
    }

    protected function configValuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        // Deep equality for arrays via normalized JSON
        if (is_array($a) && is_array($b)) {
            return Json::encode($a) === Json::encode($b);
        }
        return false;
    }
}

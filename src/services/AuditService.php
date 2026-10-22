<?php

namespace superbig\audit\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\helpers\Template;

use DateTime;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEventKind;
use superbig\audit\models\AuditModel;
use superbig\audit\models\LogFilter;
use superbig\audit\records\AuditRecord;

class AuditService extends Component
{
    public const EVENT_TRIGGER = 'eventTrigger';
    public const EVENT_SNAPSHOT = 'snapshot';

    public function init(): void
    {
        parent::init();
    }

    /**
     * @param ElementInterface $element
     *
     * @return array|null
     */
    public function getEventsForElement(ElementInterface $element): ?array
    {
        $elementId = $element->getId();
        $elementType = get_class($element);

        return $this->getEventsByAttributes(['elementId' => $elementId, 'elementType' => $elementType]);
    }

    /**
     * Choices for the log filters, limited to values that appear in the log.
     *
     * @return array{kinds: array, events: array, users: array, elementTypes: array, sites: array}
     */
    public function getFilterOptions(): array
    {
        $distinct = fn(string $column) => AuditRecord::find()
            ->select($column)
            ->distinct()
            ->where(['not', [$column => null]])
            ->column();

        $loggedEvents = array_flip($distinct('event'));
        $events = [];
        $kinds = [];

        foreach (AuditEventKind::cases() as $kind) {
            $options = [];

            foreach ($kind->events() as $event) {
                if (isset($loggedEvents[$event->value])) {
                    $options[] = ['label' => $event->label(), 'value' => $event->value];
                }
            }

            if ($options) {
                $kinds[] = ['label' => $kind->label(), 'value' => $kind->value];
                $events[] = ['optgroup' => $kind->label()];
                array_push($events, ...$options);
            }
        }

        $users = [];
        $userIds = $distinct('userId');
        if ($userIds) {
            foreach (User::find()->id($userIds)->status(null)->orderBy(['fullName' => SORT_ASC, 'username' => SORT_ASC])->all() as $user) {
                $users[] = ['label' => $user->fullName ?: $user->username, 'value' => $user->id];
            }
        }

        $elementTypes = array_map(fn(string $class) => [
            'label' => LogFilter::elementTypeLabel($class),
            'value' => $class,
        ], $distinct('elementType'));
        usort($elementTypes, fn($a, $b) => strcmp($a['label'], $b['label']));

        $sites = array_map(fn($site) => ['label' => $site->getName(), 'value' => $site->id], Craft::$app->getSites()->getAllSites());

        return compact('kinds', 'events', 'users', 'elementTypes', 'sites');
    }

    /**
     * @param int $id
     *
     * @return null|AuditModel
     */
    public function getEventById(int $id): ?AuditModel
    {
        $record = AuditRecord::findOne($id);

        if (!$record) {
            return null;
        }

        return AuditModel::createFromRecord($record);
    }

    /**
     * @param null $handle
     *
     * @return array|null
     */
    public function getEventsByHandle($handle = null): ?array
    {
        return $this->getEventsByAttributes(['eventHandle' => $handle]);
    }

    public function getEventsBySessionId($id = null): ?array
    {
        if (!$id) {
            return null;
        }

        return $this->getEventsByAttributes(['sessionId' => $id, 'parentId' => null]);
    }

    /**
     * @param array $attributes
     *
     * @return array|null
     */
    public function getEventsByAttributes(array $attributes = []): ?array
    {
        $models = null;
        $records = AuditRecord::findAll($attributes);

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $models;
    }

    public function getEventCountByParentId($parentId = null): bool|int|string|null
    {
        return AuditRecord::find()
                          ->where(['parentId' => $parentId])
                          ->count();
    }

    public function outputObjectAsTable($input, $end = true): string|\Twig\Markup
    {
        $output = '<table class="audit-snapshot-table">';

        foreach ($input as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $sub = $this->outputObjectAsTable($value, false);
                $output .= "<tr><td><strong>$key</strong>:</td><td>$sub</td></tr>";
            } else {
                $output .= "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
            }
        }
        $output .= "</table>";

        if ($end) {
            $output = Template::raw($output);
        }

        return $output;
    }

    /**
     * @return int|string
     */
    public function pruneLogs(): int|string
    {
        $pruneDays = Audit::$plugin->getSettings()->pruneDays ?? 30;
        $date = (new DateTime())->modify('-' . $pruneDays . ' days')->format('Y-m-d H:i:s');
        $query = AuditRecord::find()->where(['<=', 'dateCreated', $date]);
        $count = $query->count();

        // Delete
        AuditRecord::deleteAll(['<=', 'dateCreated', $date]);

        return $count;
    }
}

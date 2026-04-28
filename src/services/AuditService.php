<?php

namespace superbig\audit\services;

use craft\base\Component;
use craft\base\ElementInterface;
use craft\helpers\Template;

use DateTime;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
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

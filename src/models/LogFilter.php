<?php

namespace superbig\audit\models;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\Request;
use DateTime;
use superbig\audit\enums\AuditEvent;
use superbig\audit\enums\AuditEventKind;
use yii\db\QueryInterface;

/**
 * The filters on the log screen, read from the query string.
 */
class LogFilter extends Model
{
    public ?string $kind = null;
    public ?string $event = null;
    public ?int $userId = null;
    public ?string $elementType = null;
    public ?int $elementId = null;
    public ?int $siteId = null;
    public ?string $sessionId = null;
    public ?string $search = null;

    /** Raw date input, either a Y-m-d string or a Craft date field array. */
    public string|array|null $from = null;
    public string|array|null $to = null;

    public const PARAMS = ['kind', 'event', 'userId', 'elementType', 'elementId', 'siteId', 'sessionId', 'search', 'from', 'to'];

    public static function fromRequest(Request $request): self
    {
        $filter = new self();

        foreach (self::PARAMS as $param) {
            $value = $request->getQueryParam($param);

            if (is_array($value)) {
                $value = in_array($param, ['from', 'to'], true) && !empty(array_filter($value)) ? $value : null;
            } elseif (is_string($value)) {
                $value = trim($value) === '' ? null : trim($value);
            } else {
                $value = null;
            }

            if ($value === null) {
                continue;
            }

            if (in_array($param, ['userId', 'elementId', 'siteId'], true)) {
                $value = ctype_digit((string)$value) ? (int)$value : null;
            }

            $filter->$param = $value;
        }

        if ($filter->kind !== null && AuditEventKind::tryFrom($filter->kind) === null) {
            $filter->kind = null;
        }

        return $filter;
    }

    public function isActive(): bool
    {
        return $this->toParams() !== [];
    }

    public function getFromDate(): ?DateTime
    {
        return $this->parseDate($this->from);
    }

    /**
     * The end of the chosen day, so a single-day range covers the whole day.
     */
    public function getToDate(): ?DateTime
    {
        return $this->parseDate($this->to)?->setTime(23, 59, 59);
    }

    public function apply(QueryInterface $query): void
    {
        /** @var Query $query */
        // Batch children are only reachable through their parent until the log is narrowed down.
        if (!$this->isActive()) {
            $query->andWhere(['parentId' => null]);
            return;
        }

        if ($this->event !== null) {
            $query->andWhere(['event' => $this->event]);
        } elseif ($this->kind !== null) {
            $events = array_map(fn(AuditEvent $event) => $event->value, AuditEventKind::from($this->kind)->events());
            $query->andWhere(['event' => $events]);
        }

        foreach (['userId', 'elementType', 'elementId', 'siteId', 'sessionId'] as $column) {
            if ($this->$column !== null) {
                $query->andWhere([$column => $this->$column]);
            }
        }

        if ($from = $this->getFromDate()) {
            $query->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($from)]);
        }

        if ($to = $this->getToDate()) {
            $query->andWhere(['<=', 'dateCreated', Db::prepareDateForDb($to)]);
        }

        if ($this->search !== null) {
            $like = Craft::$app->getDb()->getIsPgsql() ? 'ilike' : 'like';
            $users = (new Query())
                ->select('id')
                ->from(Table::USERS)
                ->where(['or', [$like, 'username', $this->search], [$like, 'fullName', $this->search], [$like, 'email', $this->search]]);

            $condition = ['or', [$like, 'title', $this->search], [$like, 'ip', $this->search], ['userId' => $users]];

            if (ctype_digit($this->search)) {
                $condition[] = ['elementId' => (int)$this->search];
            }

            $query->andWhere($condition);
        }
    }

    /**
     * Non-empty filters as query params, for links and pagination.
     */
    public function toParams(array $except = []): array
    {
        $params = [];

        foreach (self::PARAMS as $param) {
            if ($this->$param !== null && !in_array($param, $except, true)) {
                $params[$param] = $this->$param;
            }
        }

        return $params;
    }

    public function url(array $except = []): string
    {
        return UrlHelper::cpUrl('audit', $this->toParams($except));
    }

    /**
     * Readable labels for the active filters, each with the URL that removes it.
     *
     * @return array<int, array{param: string, label: string, value: string, removeUrl: string}>
     */
    public function getActiveFilters(): array
    {
        $chips = [];
        $add = function(string $param, string $label, ?string $value, array $except = []) use (&$chips) {
            if ($value !== null && $value !== '') {
                $chips[] = [
                    'param' => $param,
                    'label' => $label,
                    'value' => $value,
                    'removeUrl' => $this->url(array_merge([$param], $except)),
                ];
            }
        };

        $formatter = Craft::$app->getFormatter();

        if ($this->kind !== null && $this->event === null) {
            $add('kind', Craft::t('audit', 'Kind'), AuditEventKind::from($this->kind)->label());
        }

        if ($this->event !== null) {
            $add('event', Craft::t('audit', 'Event'), AuditEvent::tryFrom($this->event)?->label() ?? $this->event, ['kind']);
        }

        if ($this->userId !== null) {
            $user = Craft::$app->getUsers()->getUserById($this->userId);
            $add('userId', Craft::t('app', 'User'), $user ? ($user->fullName ?: $user->username) : '#' . $this->userId);
        }

        if ($this->elementType !== null) {
            $add('elementType', Craft::t('audit', 'Element type'), self::elementTypeLabel($this->elementType));
        }

        if ($this->elementId !== null) {
            $element = Craft::$app->getElements()->getElementById($this->elementId);
            $add('elementId', Craft::t('audit', 'Element'), $element ? (string)$element : '#' . $this->elementId);
        }

        if ($this->siteId !== null) {
            $add('siteId', Craft::t('app', 'Site'), Craft::$app->getSites()->getSiteById($this->siteId)?->getName() ?? '#' . $this->siteId);
        }

        if ($this->sessionId !== null) {
            $add('sessionId', Craft::t('audit', 'Session'), substr($this->sessionId, 0, 8) . '…');
        }

        if ($from = $this->getFromDate()) {
            $add('from', Craft::t('audit', 'From'), $formatter->asDate($from, 'short'));
        }

        if ($to = $this->getToDate()) {
            $add('to', Craft::t('audit', 'To'), $formatter->asDate($to, 'short'));
        }

        $add('search', Craft::t('app', 'Search'), $this->search);

        return $chips;
    }

    public static function elementTypeLabel(string $class): string
    {
        return is_subclass_of($class, ElementInterface::class) ? $class::displayName() : $class;
    }

    private function parseDate(string|array|null $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        try {
            $date = DateTimeHelper::toDateTime($value, assumeSystemTimeZone: true);
        } catch (\Throwable) {
            return null;
        }

        return $date ?: null;
    }
}
